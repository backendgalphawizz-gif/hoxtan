<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DriverAssignmentNotificationService
{
    public function __construct(
        protected NotificationInboxService $inbox,
    ) {}

    public function notifyJewelleryDeliveryAssigned(JewelleryOrder $order): void
    {
        if (! $order->driver_id) {
            return;
        }

        $driver = Driver::query()->find($order->driver_id);
        if (! $driver) {
            return;
        }

        $order->loadMissing(['items.product', 'user', 'address']);
        $ref = $order->order_number ?: ('#'.$order->id);
        $area = $this->areaHint($order->shipping_address);
        $title = 'New delivery assigned';
        $body = $area
            ? "A jewellery delivery ({$ref}) has been assigned near {$area}. Open the app to view details."
            : "You have been assigned jewellery delivery {$ref}. Open the app to view details.";

        $this->notifyAfterCommit($driver, $title, $body, 'new_assigned_order', [
            'task_type' => 'delivery',
            'order_id' => (string) $order->id,
            'order_number' => (string) $ref,
            'screen' => 'driver_delivery_detail',
        ]);

        if ($order->user) {
            $this->notifyCustomerAfterCommit(
                $order->user,
                'Driver assigned',
                "A delivery driver has been assigned for your order {$ref}.",
                'order_driver_assigned',
                [
                    'order_id' => (string) $order->id,
                    'order_number' => (string) $ref,
                    'screen' => 'order_detail',
                ],
            );
        }
    }

    public function notifySellPickupAssigned(OldGoldBooking $booking): void
    {
        if (! $booking->driver_id) {
            return;
        }

        $driver = Driver::query()->find($booking->driver_id);
        if (! $driver) {
            return;
        }

        $booking->loadMissing(['user']);
        $ref = $booking->booking_number ?: ('#'.$booking->id);
        $area = $this->areaHint($booking->pickup_address);
        $title = 'New Assigned Order';
        $body = $area
            ? "A gold jewellery pickup has been assigned near {$area}. Please accept the request."
            : "You have been assigned sell-jewellery pickup {$ref}. Open the app to view details.";

        $this->notifyAfterCommit($driver, $title, $body, 'new_assigned_order', [
            'task_type' => 'pickup',
            'booking_id' => (string) $booking->id,
            'booking_number' => (string) $ref,
            'screen' => 'driver_pickup_detail',
        ]);

        if ($booking->user) {
            $this->notifyCustomerAfterCommit(
                $booking->user,
                'Pickup driver assigned',
                "A driver has been assigned for your sell jewellery pickup {$ref}.",
                'sell_driver_assigned',
                [
                    'booking_id' => (string) $booking->id,
                    'booking_number' => (string) $ref,
                    'screen' => 'sell_detail',
                ],
            );
        }
    }

    public function notifyJewelleryDeliveryCompleted(JewelleryOrder $order): void
    {
        if (! $order->driver_id) {
            return;
        }

        $driver = Driver::query()->find($order->driver_id);
        if (! $driver) {
            return;
        }

        $ref = $order->order_number ?: ('#'.$order->id);
        $title = 'Delivery Successfully Completed';
        $body = "Order {$ref} has been delivered successfully. Customer OTP has been verified.";

        $this->notifyAfterCommit($driver, $title, $body, 'delivery_update', [
            'task_type' => 'delivery',
            'order_id' => (string) $order->id,
            'order_number' => (string) $ref,
            'screen' => 'driver_delivery_detail',
        ]);
    }

    public function notifyPickupReminder(OldGoldBooking $booking, ?string $beforeTime = null): void
    {
        if (! $orderDriverId = $booking->driver_id) {
            return;
        }

        $driver = Driver::query()->find($orderDriverId);
        if (! $driver) {
            return;
        }

        $ref = $booking->booking_number ?: ('#'.$booking->id);
        $deadline = $beforeTime ?: '5:00 PM';
        $title = 'Pickup Scheduled Today';
        $body = "Gold jewellery pickup for Sell Request {$ref} is scheduled before {$deadline}.";

        $this->notifyAfterCommit($driver, $title, $body, 'pickup_reminder', [
            'task_type' => 'pickup',
            'booking_id' => (string) $booking->id,
            'booking_number' => (string) $ref,
            'screen' => 'driver_pickup_detail',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function notifyAfterCommit(Driver $driver, string $title, string $body, string $type, array $data = []): void
    {
        $send = function () use ($driver, $title, $body, $type, $data): void {
            $this->notify($driver, $title, $body, $type, $data);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function notifyCustomerAfterCommit(User $user, string $title, string $body, string $type, array $data = []): void
    {
        $send = function () use ($user, $title, $body, $type, $data): void {
            try {
                $this->inbox->notifyUser($user, $title, $body, $type, $data, push: true);
            } catch (Throwable $e) {
                Log::warning('Customer assignment notification failed', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function notify(Driver $driver, string $title, string $body, string $type, array $data = []): void
    {
        try {
            $this->inbox->notifyDriver($driver, $title, $body, $type, $data, push: true);
        } catch (Throwable $e) {
            Log::warning('Driver notification failed', [
                'driver_id' => $driver->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function areaHint(?string $address): ?string
    {
        if (blank($address)) {
            return null;
        }

        $parts = preg_split('/[,\n]/', $address) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if ($parts === []) {
            return null;
        }

        // Prefer a short locality-like fragment.
        foreach (array_reverse($parts) as $part) {
            if (strlen($part) >= 3 && strlen($part) <= 40 && ! preg_match('/^\d{6}$/', $part)) {
                return $part;
            }
        }

        return $parts[0] ?? null;
    }
}
