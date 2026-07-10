<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Support\DriverDeliveryPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DriverDeliveryService
{
    public function markPickedUp(Driver $driver, JewelleryOrder $order): JewelleryOrder
    {
        $this->ensureAssigned($driver, $order);
        $this->ensureActiveDelivery($order);

        $status = DriverDeliveryPayload::resolveStatus($order);

        if ($status['key'] !== 'accepted') {
            throw ValidationException::withMessages([
                'order' => ['Only accepted orders can be marked as picked up.'],
            ]);
        }

        $order->update([
            'picked_up_at' => now(),
        ]);

        return $order->fresh(['items.product', 'payment', 'user']);
    }

    public function verifyDelivery(
        Driver $driver,
        JewelleryOrder $order,
        string $otp,
        ?UploadedFile $proofImage = null,
    ): JewelleryOrder {
        $this->ensureAssigned($driver, $order);
        $this->ensureActiveDelivery($order);

        $status = DriverDeliveryPayload::resolveStatus($order);

        if ($status['key'] !== 'picked_up') {
            throw ValidationException::withMessages([
                'order' => ['Order must be picked up before delivery can be verified.'],
            ]);
        }

        if (! filled($order->delivery_otp) || ! hash_equals((string) $order->delivery_otp, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid delivery OTP. Please check with the customer and try again.'],
            ]);
        }

        $proofPath = $proofImage
            ? $proofImage->store('delivery-proofs', 'public')
            : $order->delivery_proof_image;

        $order->update([
            'status' => 'completed',
            'delivered_at' => now(),
            'delivery_proof_image' => $proofPath,
            'delivery_failure_reason' => null,
        ]);

        return $order->fresh(['items.product', 'payment', 'user']);
    }

    public function markUnableToDeliver(Driver $driver, JewelleryOrder $order, string $reason): JewelleryOrder
    {
        $this->ensureAssigned($driver, $order);
        $this->ensureActiveDelivery($order);

        $status = DriverDeliveryPayload::resolveStatus($order);

        if (! in_array($status['key'], ['accepted', 'picked_up'], true)) {
            throw ValidationException::withMessages([
                'order' => ['This delivery can no longer be marked as undeliverable.'],
            ]);
        }

        if (! in_array($reason, DriverDeliveryPayload::failureReasonValues(), true)) {
            throw ValidationException::withMessages([
                'reason' => ['Please select a valid delivery failure reason.'],
            ]);
        }

        $order->update([
            'status' => 'cancelled',
            'delivery_failure_reason' => $reason,
        ]);

        return $order->fresh(['items.product', 'payment', 'user']);
    }

    protected function ensureAssigned(Driver $driver, JewelleryOrder $order): void
    {
        if ($order->driver_id !== $driver->id || $order->status === 'cart') {
            throw ValidationException::withMessages([
                'order' => ['This delivery is not assigned to you.'],
            ]);
        }
    }

    protected function ensureActiveDelivery(JewelleryOrder $order): void
    {
        if (DriverDeliveryPayload::isDelivered($order)) {
            throw ValidationException::withMessages([
                'order' => ['This delivery has already been completed.'],
            ]);
        }
    }
}
