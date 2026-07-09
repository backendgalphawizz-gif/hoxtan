<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OldGoldBooking;
use App\Models\User;
use App\Services\BlockedPincodeService;
use App\Services\SellJewelleryService;
use App\Support\ApiResponse;
use App\Support\SellJewelleryPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellJewelleryController extends Controller
{
    public function __construct(
        protected BlockedPincodeService $blockedPincodeService,
    ) {}
    public function config(Request $request, SellJewelleryService $service): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', 'string', Rule::in(['gold', 'silver'])],
        ]);

        return ApiResponse::success($service->getConfig($data['metal_type'] ?? null));
    }

    public function estimate(Request $request, SellJewelleryService $service): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['required', 'string', Rule::in(['gold', 'silver'])],
            'weight_grams' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'purity' => ['required', 'string', 'max:20'],
        ]);

        $service->assertValidPurity($data['metal_type'], $data['purity']);

        return ApiResponse::success([
            'estimate' => $service->estimate(
                $data['metal_type'],
                (float) $data['weight_grams'],
                $data['purity'],
            ),
        ]);
    }

    public function recent(Request $request, SellJewelleryService $service): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return ApiResponse::success([
            'recently_sold' => $service->recentSold(
                $request->user(),
                (int) ($data['limit'] ?? 5),
            ),
        ]);
    }

    public function store(Request $request, SellJewelleryService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedRequest($request);
        $files = $this->validatedDocuments($request, $data['sell_location']);

        $booking = $service->createRequest($user, $data, $files);

        return ApiResponse::success([
            'request' => SellJewelleryPayload::make($booking, detailed: true),
        ], 'Sell request submitted successfully.', 201);
    }

    public function index(Request $request, SellJewelleryService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'accepted', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return ApiResponse::success($service->listRequests(
            $request->user(),
            $data['status'] ?? 'all',
            (int) ($data['per_page'] ?? 10),
        ));
    }

    public function show(Request $request, OldGoldBooking $booking): JsonResponse
    {
        $this->ensureOwnedByUser($request, $booking);

        return ApiResponse::success([
            'request' => SellJewelleryPayload::make($booking, detailed: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedRequest(Request $request): array
    {
        $identityValues = collect(config('sell_jewellery.identity_owners', []))->pluck('value')->all();
        $locationValues = collect(config('sell_jewellery.sell_locations', []))->pluck('value')->all();

        $data = $request->validate([
            'metal_type' => ['required', 'string', Rule::in(['gold', 'silver'])],
            'weight_grams' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'purity' => ['required', 'string', 'max:20'],
            'identity_owner' => ['required', 'string', Rule::in($identityValues)],
            'sell_location' => ['required', 'string', Rule::in($locationValues)],
            'confirmed' => ['required', 'accepted'],
            'address_id' => ['nullable', 'integer', 'exists:user_addresses,id'],
            'full_name' => ['required_without:address_id', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'address_line' => ['required_without:address_id', 'nullable', 'string', 'max:500'],
            'city' => ['required_without:address_id', 'nullable', 'string', 'max:100'],
            'state' => ['required_without:address_id', 'nullable', 'string', 'max:100'],
            'pincode' => ['required_without:address_id', 'nullable', 'string', 'regex:/^\d{6}$/'],
            'phone' => ['required_without:address_id', 'nullable', 'string', 'regex:/^\d{10}$/'],
        ], [
            'full_name.regex' => 'Full name may only contain letters and spaces.',
            'pincode.regex' => 'Pincode must be exactly 6 digits.',
            'phone.regex' => 'Phone number must be exactly 10 digits.',
            'confirmed.accepted' => 'Please confirm that the details are correct.',
        ]);

        if (filled($data['address_id'] ?? null)) {
            $address = $request->user()->addresses()->find($data['address_id']);

            if ($address === null) {
                throw ValidationException::withMessages([
                    'address_id' => ['Address not found.'],
                ]);
            }

            $this->blockedPincodeService->assertNotBlocked($address->pincode, 'address_id');
        } elseif (filled($data['pincode'] ?? null)) {
            $this->blockedPincodeService->assertNotBlocked($data['pincode']);
        }

        return $data;
    }

    /**
     * @return array<string, \Illuminate\Http\UploadedFile>
     */
    protected function validatedDocuments(Request $request, string $sellLocation): array
    {
        $rules = [
            'id_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'purchase_receipt' => [Rule::requiredIf($sellLocation === 'at_home'), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bank_receipt' => [Rule::requiredIf($sellLocation === 'at_bank'), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        $request->validate($rules);

        $files = [];

        foreach (array_keys($rules) as $field) {
            if ($request->hasFile($field)) {
                $files[$field] = $request->file($field);
            }
        }

        if ($sellLocation === 'at_home' && ! isset($files['purchase_receipt'])) {
            throw ValidationException::withMessages([
                'purchase_receipt' => ['Purchase receipt is required when selling from home.'],
            ]);
        }

        if ($sellLocation === 'at_bank' && ! isset($files['bank_receipt'])) {
            throw ValidationException::withMessages([
                'bank_receipt' => ['Bank receipt is required when selling from bank.'],
            ]);
        }

        return $files;
    }

    protected function ensureOwnedByUser(Request $request, OldGoldBooking $booking): void
    {
        if ($booking->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'request' => ['Sell request not found.'],
            ])->status(404);
        }
    }
}
