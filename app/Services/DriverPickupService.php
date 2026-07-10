<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\OldGoldBooking;
use App\Support\DriverPickupPayload;
use App\Support\SellJewelleryPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class DriverPickupService
{
    public function acceptPickup(Driver $driver, OldGoldBooking $booking): OldGoldBooking
    {
        $this->ensureAssigned($driver, $booking);
        $this->ensureActivePickup($booking);

        $status = DriverPickupPayload::resolveStatus($booking);

        if ($status['key'] !== 'processing') {
            throw ValidationException::withMessages([
                'pickup' => ['This pickup has already been accepted or can no longer be accepted.'],
            ]);
        }

        $updates = [
            'driver_accepted_at' => now(),
        ];

        if (in_array($booking->status, ['processing', 'pending', 'pickup_scheduling'], true)) {
            $updates['status'] = 'accepted';
            $updates['accepted_at'] = $booking->accepted_at ?? now();
        }

        $booking->update($updates);

        return $booking->fresh('user');
    }

    public function verifyCustomer(Driver $driver, OldGoldBooking $booking): OldGoldBooking
    {
        $this->ensureAssigned($driver, $booking);
        $this->ensureActivePickup($booking);

        $status = DriverPickupPayload::resolveStatus($booking);

        if ($status['key'] !== 'accepted') {
            throw ValidationException::withMessages([
                'pickup' => ['Customer verification is only available after accepting the pickup.'],
            ]);
        }

        $booking->update([
            'customer_verified_at' => now(),
        ]);

        return $booking->fresh('user');
    }

    /**
     * @param  list<UploadedFile>  $proofImages
     */
    public function uploadProof(Driver $driver, OldGoldBooking $booking, array $proofImages): OldGoldBooking
    {
        $this->ensureAssigned($driver, $booking);
        $this->ensureActivePickup($booking);

        $status = DriverPickupPayload::resolveStatus($booking);

        if ($status['key'] !== 'verified') {
            throw ValidationException::withMessages([
                'pickup' => ['Photo proof can only be uploaded after customer verification.'],
            ]);
        }

        $storedPaths = collect($proofImages)
            ->map(fn (UploadedFile $image): string => $image->store('pickup-proofs', 'public'))
            ->values()
            ->all();

        $booking->update([
            'pickup_proof_images' => $storedPaths,
        ]);

        return $booking->fresh('user');
    }

    public function verifyOtp(Driver $driver, OldGoldBooking $booking, string $otp): OldGoldBooking
    {
        $this->ensureAssigned($driver, $booking);
        $this->ensureActivePickup($booking);

        $status = DriverPickupPayload::resolveStatus($booking);

        if ($status['key'] !== 'proof_uploaded') {
            throw ValidationException::withMessages([
                'pickup' => ['OTP verification is only available after photo proof has been uploaded.'],
            ]);
        }

        if (! filled($booking->delivery_otp) || ! hash_equals((string) $booking->delivery_otp, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid delivery OTP. Please check with the customer and try again.'],
            ]);
        }

        $booking->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
            'pickup_failure_reason' => null,
        ]);

        return $booking->fresh('user');
    }

    public function markUnableToPickup(Driver $driver, OldGoldBooking $booking, string $reason): OldGoldBooking
    {
        $this->ensureAssigned($driver, $booking);
        $this->ensureActivePickup($booking);

        $status = DriverPickupPayload::resolveStatus($booking);

        if (! in_array($status['key'], ['processing', 'accepted', 'verified', 'proof_uploaded'], true)) {
            throw ValidationException::withMessages([
                'pickup' => ['This pickup can no longer be marked as undeliverable.'],
            ]);
        }

        if (! in_array($reason, DriverPickupPayload::failureReasonValues(), true)) {
            throw ValidationException::withMessages([
                'reason' => ['Please select a valid pickup failure reason.'],
            ]);
        }

        $booking->update([
            'status' => 'cancelled',
            'pickup_failure_reason' => $reason,
        ]);

        return $booking->fresh('user');
    }

    protected function ensureAssigned(Driver $driver, OldGoldBooking $booking): void
    {
        if ($booking->driver_id !== $driver->id) {
            throw ValidationException::withMessages([
                'pickup' => ['This pickup is not assigned to you.'],
            ]);
        }
    }

    protected function ensureActivePickup(OldGoldBooking $booking): void
    {
        if (DriverPickupPayload::isCollected($booking)) {
            throw ValidationException::withMessages([
                'pickup' => ['This pickup has already been completed.'],
            ]);
        }

        if (in_array(SellJewelleryPayload::normalizeStatus($booking->status), ['cancelled', 'failed'], true)) {
            throw ValidationException::withMessages([
                'pickup' => ['This pickup is no longer active.'],
            ]);
        }
    }
}
