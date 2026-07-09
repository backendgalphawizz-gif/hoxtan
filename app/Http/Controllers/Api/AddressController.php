<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\BlockedPincodeService;
use App\Support\AddressPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    public function __construct(
        protected BlockedPincodeService $blockedPincodeService,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();

        return ApiResponse::success([
            'addresses' => AddressPayload::collection($addresses),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedAddress($request);

        $address = $user->addresses()->create($data);

        if ($address->is_default) {
            $this->clearOtherDefaults($user, $address->id);
        } elseif (! $user->addresses()->where('is_default', true)->exists()) {
            $address->update(['is_default' => true]);
            $address->refresh();
        }

        return ApiResponse::success([
            'address' => AddressPayload::make($address),
        ], 'Address saved successfully.', 201);
    }

    public function show(Request $request, UserAddress $address): JsonResponse
    {
        $this->ensureOwnedByUser($request, $address);

        return ApiResponse::success([
            'address' => AddressPayload::make($address),
        ]);
    }

    public function update(Request $request, UserAddress $address): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureOwnedByUser($request, $address);

        $data = $this->validatedAddress($request);
        $address->update($data);

        if ($address->is_default) {
            $this->clearOtherDefaults($user, $address->id);
        }

        return ApiResponse::success([
            'address' => AddressPayload::make($address->fresh()),
        ], 'Address updated successfully.');
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureOwnedByUser($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $nextDefault = $user->addresses()->latest('id')->first();

            if ($nextDefault) {
                $nextDefault->update(['is_default' => true]);
            }
        }

        return ApiResponse::success([], 'Address deleted successfully.');
    }

    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureOwnedByUser($request, $address);

        $address->update(['is_default' => true]);
        $this->clearOtherDefaults($user, $address->id);

        return ApiResponse::success([
            'address' => AddressPayload::make($address->fresh()),
        ], 'Default address updated successfully.');
    }

    protected function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            'address_type' => ['required', 'string', Rule::in(['home', 'work', 'other'])],
            'is_default' => ['nullable', 'boolean'],
            'full_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'address_line' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^\d{6}$/'],
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
        ], [
            'full_name.regex' => 'Full name may only contain letters and spaces.',
            'pincode.regex' => 'Pincode must be exactly 6 digits.',
            'phone.regex' => 'Phone number must be exactly 10 digits.',
        ]);

        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        $this->blockedPincodeService->assertNotBlocked($data['pincode']);

        return $data;
    }

    protected function ensureOwnedByUser(Request $request, UserAddress $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'address' => ['Address not found.'],
            ])->status(404);
        }
    }

    protected function clearOtherDefaults(User $user, int $exceptId): void
    {
        $user->addresses()
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
