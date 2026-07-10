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

    public function show(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $this->ensureAssigned($driver, $booking);

        $booking->load('user');

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromPickup($booking),
            'pickup' => DriverPickupPayload::make($booking),
        ]);
    }

    public function verifyCustomer(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $request->validate([
            'confirmed' => ['required', 'accepted'],
        ]);

        $booking = $this->pickupService->verifyCustomer($driver, $booking);

        return ApiResponse::success($this->pickupResponse($booking), 'Customer and jewellery verified.');
    }

    public function uploadProof(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

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

    public function verifyOtp(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'otp' => ['required', 'digits:'.config('driver.pickup.otp_length', 4)],
        ]);

        $booking = $this->pickupService->verifyOtp($driver, $booking, $data['otp']);

        return ApiResponse::success($this->pickupResponse($booking), 'Jewellery collected successfully.');
    }

    public function markUnableToPickup(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

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

    protected function ensureAssigned(Driver $driver, OldGoldBooking $booking): void
    {
        if ($booking->driver_id !== $driver->id) {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found.');
        }
    }
}
