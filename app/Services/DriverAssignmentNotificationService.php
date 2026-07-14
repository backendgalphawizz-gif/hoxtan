<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use Illuminate\Support\Facades\Log;
use Throwable;

class DriverAssignmentNotificationService
{
    public function __construct(
        protected FirebaseCloudMessagingService $fcm,
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

        $ref = $order->order_number ?: ('#'.$order->id);
        $title = 'New delivery assigned';
        $body = 'You have been assigned jewellery delivery '.$ref.'. Open the app to view details.';

        $this->push($driver, $title, $body, 'driver_delivery_assigned', [
            'task_type' => 'delivery',
            'order_id' => (string) $order->id,
            'order_number' => (string) $ref,
            'screen' => 'driver_delivery_detail',
        ]);
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

        $ref = $booking->booking_number ?: ('#'.$booking->id);
        $title = 'New pickup assigned';
        $body = 'You have been assigned sell-jewellery pickup '.$ref.'. Open the app to view details.';

        $this->push($driver, $title, $body, 'driver_pickup_assigned', [
            'task_type' => 'pickup',
            'booking_id' => (string) $booking->id,
            'booking_number' => (string) $ref,
            'screen' => 'driver_pickup_detail',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function push(Driver $driver, string $title, string $body, string $type, array $data = []): void
    {
        try {
            $result = $this->fcm->sendToOwners([$driver], $title, $body, $data, $type);

            Log::info('Driver assignment push sent', [
                'driver_id' => $driver->id,
                'type' => $type,
                'success' => $result['success'] ?? 0,
                'failure' => $result['failure'] ?? 0,
            ]);
        } catch (Throwable $e) {
            Log::warning('Driver assignment push failed', [
                'driver_id' => $driver->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
