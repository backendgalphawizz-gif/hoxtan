<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\OldGoldBooking;
use App\Services\DriverPickupService;
use App\Support\ApiResponse;
use App\Support\DriverPickupPayload;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DriverPickupController extends Controller
{
    public function __construct(
        protected DriverPickupService $pickupService,
    ) {}

    public function config(): JsonResponse
    {
        return ApiResponse::success(DriverPickupPayload::config());
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $booking = $this->resolvePickupForDriver($driver, $booking);
        $booking->load('user');

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromPickup($booking),
            'pickup' => DriverPickupPayload::make($booking),
        ]);
    }

    public function verifyCustomer(Request $request, string $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $booking = $this->resolvePickupForDriver($driver, $booking);

        $request->validate([
            'confirmed' => ['required', 'accepted'],
        ]);

        $booking = $this->pickupService->verifyCustomer($driver, $booking);

        return ApiResponse::success($this->pickupResponse($booking), 'Customer and jewellery verified.');
    }

    public function uploadProof(Request $request, string $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $booking = $this->resolvePickupForDriver($driver, $booking);

        $data = $request->validate([
            'proof_images' => ['required', 'array', 'min:1', 'max:5'],
            'proof_images.*' => ['required', 'image', 'max:4096'],
        ]);

        $booking = $this->pickupService->uploadProof(
            $driver,
            $booking,
            $data['proof_images'],
        );

        return ApiResponse::success($this->pickupResponse($booking), 'Photo proof uploaded successfully.');
    }

    public function verifyOtp(Request $request, string $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $booking = $this->resolvePickupForDriver($driver, $booking);

        $data = $request->validate([
            'otp' => ['required', 'digits:'.config('driver.pickup.otp_length', 4)],
        ]);

        $booking = $this->pickupService->verifyOtp($driver, $booking, $data['otp']);

        return ApiResponse::success($this->pickupResponse($booking), 'Jewellery collected successfully.');
    }

    public function markUnableToPickup(Request $request, string $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $booking = $this->resolvePickupForDriver($driver, $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(DriverPickupPayload::failureReasonValues())],
        ]);

        $booking = $this->pickupService->markUnableToPickup($driver, $booking, $data['reason']);

        return ApiResponse::success($this->pickupResponse($booking), 'Pickup marked as undeliverable.');
    }

    /**
     * @return array{task: array<string, mixed>, pickup: array<string, mixed>}
     */
    protected function pickupResponse(OldGoldBooking $booking): array
    {
        return [
            'task' => DriverTaskPayload::fromPickup($booking),
            'pickup' => DriverPickupPayload::make($booking),
        ];
    }

    protected function resolvePickupForDriver(Driver $driver, string $identifier): OldGoldBooking
    {
        $identifier = ltrim(trim($identifier), '#');

        if ($identifier === '') {
            abort(Response::HTTP_NOT_FOUND, 'Pickup identifier is required.');
        }

        $assignedBooking = OldGoldBooking::query()
            ->where('driver_id', $driver->id)
            ->where(function ($query) use ($identifier): void {
                if (ctype_digit($identifier)) {
                    $query->whereKey((int) $identifier);
                } else {
                    $query->where('booking_number', $identifier);
                }
            })
            ->first();

        if ($assignedBooking) {
            return $assignedBooking;
        }

        $booking = OldGoldBooking::query()
            ->when(ctype_digit($identifier), fn ($query) => $query->whereKey((int) $identifier))
            ->when(! ctype_digit($identifier), fn ($query) => $query->where('booking_number', $identifier))
            ->first();

        if ($booking === null) {
            abort(Response::HTTP_NOT_FOUND, 'Pickup not found. Use pickup_id from GET /api/v1/driver/tasks?type=pickup.');
        }

        if ($booking->driver_id === null) {
            abort(Response::HTTP_NOT_FOUND, 'No driver assigned to this pickup yet. Ask admin to assign a driver first.');
        }

        abort(Response::HTTP_FORBIDDEN, 'This pickup is assigned to another driver account.');
    }
}
