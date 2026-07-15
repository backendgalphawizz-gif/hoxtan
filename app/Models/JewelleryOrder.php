<?php

namespace App\Models;

use App\Services\DriverAssignmentNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class JewelleryOrder extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'user_address_id',
        'driver_id',
        'driver_assigned_at',
        'picked_up_at',
        'payment_id',
        'subtotal',
        'metal_value',
        'making_charge_amount',
        'gst_percent',
        'gst_amount',
        'discount_amount',
        'total_amount',
        'payment_mode',
        'jewellery_emi_plan_id',
        'emi_tenure',
        'total_emi_cost',
        'monthly_emi_amount',
        'status',
        'shipping_address',
        'shipping_name',
        'shipping_phone',
        'shipping_address_type',
        'expected_delivery_date',
        'delivery_otp',
        'tracking_number',
        'courier_name',
        'dispatched_at',
        'delivered_at',
        'delivery_failure_reason',
        'delivery_proof_image',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'metal_value' => 'decimal:2',
            'making_charge_amount' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_emi_cost' => 'decimal:2',
            'monthly_emi_amount' => 'decimal:2',
            'emi_tenure' => 'integer',
            'expected_delivery_date' => 'date',
            'driver_assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JewelleryOrder $order): void {
            if (! $order->isDirty('driver_id')) {
                return;
            }

            if ($order->driver_id) {
                if (! $order->isDeliveryEligible()) {
                    throw ValidationException::withMessages([
                        'driver_id' => ['EMI order cannot be delivered until all monthly EMIs are paid.'],
                    ]);
                }

                $order->driver_assigned_at = now();

                if ($order->status === 'pending') {
                    $order->status = 'processing';
                }

                return;
            }

            $order->driver_assigned_at = null;
        });

        static::saved(function (JewelleryOrder $order): void {
            if (! $order->wasChanged('driver_id') || blank($order->driver_id)) {
                return;
            }

            app(DriverAssignmentNotificationService::class)
                ->notifyJewelleryDeliveryAssigned($order);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function emiPlan(): BelongsTo
    {
        return $this->belongsTo(JewelleryEmiPlan::class, 'jewellery_emi_plan_id');
    }

    public function emiInstallments(): HasMany
    {
        return $this->hasMany(JewelleryOrderEmiInstallment::class)->orderBy('installment_number');
    }

    public function emiRefundRequests(): HasMany
    {
        return $this->hasMany(JewelleryEmiRefundRequest::class);
    }

    public function latestEmiRefundRequest(): ?JewelleryEmiRefundRequest
    {
        return $this->emiRefundRequests()->latest('id')->first();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(JewelleryOrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isEmi(): bool
    {
        return $this->payment_mode === 'emi';
    }

    public function emiPaidCount(): int
    {
        return (int) $this->emiInstallments()->where('status', 'paid')->count();
    }

    public function emiTotalCount(): int
    {
        return max(0, (int) ($this->emi_tenure ?? $this->emiInstallments()->count()));
    }

    public function emiInstallmentsFullyPaid(): bool
    {
        if (! $this->isEmi()) {
            return true;
        }

        $total = $this->emiInstallments()->count();

        if ($total === 0) {
            return false;
        }

        return $this->emiInstallments()->where('status', 'pending')->doesntExist();
    }

    /**
     * EMI jewellery is held until every monthly installment is paid.
     */
    public function isDeliveryEligible(): bool
    {
        if (! $this->isEmi()) {
            return true;
        }

        return $this->emiInstallmentsFullyPaid();
    }

    public function emiProgressLabel(): string
    {
        if (! $this->isEmi()) {
            return '—';
        }

        return $this->emiPaidCount().'/'.$this->emiTotalCount().' paid';
    }
}
