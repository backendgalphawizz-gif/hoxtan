<?php

namespace App\Services;

use App\Models\OldGoldBooking;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\DeliveryOtp;
use App\Support\KycPayload;
use App\Support\SellJewelleryPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellJewelleryService
{
    public function __construct(
        protected MetalRateService $metalRates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(?string $metalType = null): array
    {
        $rates = $this->metalRates->getApiRates($metalType);

        return [
            'metal_types' => config('sell_jewellery.metal_types', []),
            'purities' => config('sell_jewellery.purities', []),
            'identity_owners' => config('sell_jewellery.identity_owners', []),
            'sell_locations' => config('sell_jewellery.sell_locations', []),
            'document_types' => config('sell_jewellery.document_types', []),
            'status_filters' => config('sell_jewellery.list_filters', []),
            'rates' => $rates,
        ];
    }

    /**
     * @return array{
     *     metal_type: string,
     *     purity: string,
     *     weight_grams: float,
     *     rate_per_gram: float,
     *     purity_factor: float,
     *     estimated_value: float,
     *     estimated_value_display: string
     * }
     */
    public function estimate(string $metalType, float $weightGrams, string $purity): array
    {
        $this->assertValidPurity($metalType, $purity);

        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $factor = $this->purityFactor($purity);
        $estimated = round($weightGrams * $rate * $factor, 2);

        return [
            'metal_type' => $metalType,
            'purity' => $purity,
            'weight_grams' => $weightGrams,
            'rate_per_gram' => round($rate, 2),
            'purity_factor' => $factor,
            'estimated_value' => $estimated,
            'estimated_value_display' => '₹'.number_format($estimated, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentSold(User $user, int $limit = 5): array
    {
        $bookings = $user->oldGoldBookings()
            ->whereIn('status', ['completed', 'picked_up'])
            ->latest('id')
            ->limit($limit)
            ->get();

        return SellJewelleryPayload::collection($bookings);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $files
     */
    public function createRequest(User $user, array $data, array $files): OldGoldBooking
    {
        KycPayload::assertCanPerformTransactions($user);

        $estimate = $this->estimate(
            $data['metal_type'],
            (float) $data['weight_grams'],
            $data['purity'],
        );

        $pickup = $this->resolvePickupAddress($user, $data);
        $bookingNumber = $this->generateBookingNumber();

        return DB::transaction(function () use ($user, $data, $files, $estimate, $pickup, $bookingNumber): OldGoldBooking {
            $documents = $this->storeDocuments($user, $bookingNumber, $files);

            return $user->oldGoldBookings()->create([
                'booking_number' => $bookingNumber,
                'metal_type' => $data['metal_type'],
                'purity' => $data['purity'],
                'item_name' => $this->buildItemName($data['metal_type'], $data['purity'], (float) $data['weight_grams']),
                'estimated_weight_grams' => $data['weight_grams'],
                'rate_per_gram' => $estimate['rate_per_gram'],
                'quoted_amount' => $estimate['estimated_value'],
                'identity_owner' => $data['identity_owner'],
                'sell_location' => $data['sell_location'],
                'user_address_id' => $pickup['user_address_id'],
                'pickup_name' => $pickup['pickup_name'],
                'pickup_phone' => $pickup['pickup_phone'],
                'pickup_address' => $pickup['pickup_address'],
                'documents' => $documents,
                'delivery_otp' => DeliveryOtp::generate(),
                'status' => 'pending',
            ]);
        });
    }

    public function listRequests(User $user, string $filter = 'all', int $perPage = 10): array
    {
        $query = $user->oldGoldBookings()->with('driver')->latest('id');

        if ($filter === 'pending') {
            $query->where('status', 'pending');
        } elseif ($filter === 'accepted') {
            $query->whereIn('status', ['accepted', 'pickup_scheduling', 'picked_up', 'processing']);
        } elseif ($filter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $bookings = $query->paginate($perPage);

        return [
            'requests' => SellJewelleryPayload::collection($bookings->getCollection()),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'last_page' => $bookings->lastPage(),
                'has_more' => $bookings->hasMorePages(),
                'showing' => $bookings->count(),
            ],
        ];
    }

    public function purityFactor(string $purity): float
    {
        return (float) (config('sell_jewellery.purity_factors.'.$purity) ?? 1.0);
    }

    public function assertValidPurity(string $metalType, string $purity): void
    {
        $allowed = config('sell_jewellery.purities.'.$metalType, []);

        if (! in_array($purity, $allowed, true)) {
            throw ValidationException::withMessages([
                'purity' => ['Invalid purity for the selected metal type.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user_address_id: ?int, pickup_name: string, pickup_phone: string, pickup_address: string}
     */
    protected function resolvePickupAddress(User $user, array $data): array
    {
        if (filled($data['address_id'] ?? null)) {
            /** @var UserAddress $address */
            $address = $user->addresses()->findOrFail($data['address_id']);

            return [
                'user_address_id' => $address->id,
                'pickup_name' => $address->full_name,
                'pickup_phone' => $address->phone,
                'pickup_address' => collect([
                    $address->address_line,
                    $address->city,
                    $address->state.' '.$address->pincode,
                ])->filter()->implode(', '),
            ];
        }

        return [
            'user_address_id' => null,
            'pickup_name' => $data['full_name'],
            'pickup_phone' => $data['phone'],
            'pickup_address' => collect([
                $data['address_line'],
                $data['city'],
                $data['state'].' '.$data['pincode'],
            ])->filter()->implode(', '),
        ];
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @return array<string, string>
     */
    protected function storeDocuments(User $user, string $bookingNumber, array $files): array
    {
        $stored = [];

        foreach ($files as $key => $file) {
            $stored[$key] = $file->store(
                'sell-jewellery/'.$user->id.'/'.$bookingNumber,
                'public',
            );
        }

        return $stored;
    }

    protected function buildItemName(string $metalType, string $purity, float $weightGrams): string
    {
        $metal = collect(config('sell_jewellery.metal_types', []))
            ->firstWhere('value', $metalType)['label'] ?? ucfirst($metalType);

        $weight = rtrim(rtrim(number_format($weightGrams, 3, '.', ''), '0'), '.').'gm';

        return $metal.' | '.$purity.' | '.$weight;
    }

    protected function generateBookingNumber(): string
    {
        $prefix = config('sell_jewellery.booking_prefix', 'SELL');

        do {
            $number = $prefix.random_int(10000, 99999);
        } while (OldGoldBooking::query()->where('booking_number', $number)->exists());

        return $number;
    }
}
