<?php

namespace App\Services;

use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderItem;
use App\Models\JewelleryProduct;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\BlockedPincodeService;
use App\Support\AddressPayload;
use App\Support\DeliveryOtp;
use App\Support\JewelleryEmiPayload;
use App\Support\JewelleryPricing;
use App\Support\JewelleryProductPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JewelleryCheckoutService
{
    public function __construct(
        protected GstService $gst,
        protected AppSettingService $settings,
        protected BlockedPincodeService $blockedPincodeService,
        protected JewelleryEmiService $emi,
        protected InvoiceService $invoices,
    ) {}

    /**
     * @return array{
     *     product: array,
     *     quantity: int,
     *     address: ?array,
     *     price_breakup: array,
     *     expected_delivery: array,
     *     order_date: string,
     *     order_date_display: string
     * }
     */
    public function summary(
        User $user,
        int $productId,
        int $quantity = 1,
        ?int $addressId = null,
        ?int $emiPlanId = null,
        ?int $tenure = null,
        ?float $totalEmiCost = null,
    ): array {
        $product = $this->resolveProduct($productId);
        $address = $this->resolveAddress($user, $addressId);
        $breakup = $this->priceBreakup($product, $quantity);
        $delivery = $this->expectedDelivery();
        $emi = $this->emiContext($breakup['total'], $emiPlanId, $tenure, $totalEmiCost);

        return [
            'product' => JewelleryProductPayload::make($product),
            'quantity' => $quantity,
            'address' => $address ? AddressPayload::make($address) : null,
            'price_breakup' => $breakup,
            'expected_delivery' => $delivery,
            'order_date' => now()->toDateString(),
            'order_date_display' => now()->format('d F Y'),
            'payment_types' => [
                ['key' => 'full', 'label' => 'Pay in Full'],
                ['key' => 'emi', 'label' => 'EMI'],
            ],
            'emi' => $emi,
        ];
    }

    /**
     * @return array{
     *     product: array,
     *     quantity: int,
     *     address: array,
     *     price_breakup: array,
     *     expected_delivery: array,
     *     order: array,
     *     payment: array
     * }
     */
    public function buyNow(
        User $user,
        int $productId,
        int $quantity = 1,
        ?int $addressId = null,
        string $paymentType = 'full',
        ?int $emiPlanId = null,
        ?int $tenure = null,
        ?float $totalEmiCost = null,
        ?string $paymentMethod = null,
        ?string $transactionId = null,
    ): array {
        $product = $this->resolveProduct($productId);
        $address = $this->resolveAddress($user, $addressId, required: true);
        $breakup = $this->priceBreakup($product, $quantity);
        $delivery = $this->expectedDelivery();
        $emiSelection = $paymentType === 'emi'
            ? $this->emi->resolveForCheckout($breakup['total'], $emiPlanId, $tenure, $totalEmiCost)
            : null;
        $emiFields = $this->emiOrderFields($emiSelection);

        /** @var JewelleryOrder $order */
        $order = DB::transaction(function () use (
            $user,
            $product,
            $quantity,
            $address,
            $breakup,
            $delivery,
            $paymentType,
            $emiFields,
            $paymentMethod,
            $transactionId,
        ): JewelleryOrder {
            $isEmi = $paymentType === 'emi';

            $order = JewelleryOrder::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'user_address_id' => $address->id,
                'subtotal' => $breakup['subtotal'],
                'metal_value' => $breakup['metal_value'],
                'making_charge_amount' => $breakup['making_charges'],
                'gst_percent' => $breakup['gst_percent'],
                'gst_amount' => $breakup['gst_amount'],
                'discount_amount' => $breakup['discount_amount'],
                'total_amount' => $breakup['total'],
                'payment_mode' => $paymentType,
                'jewellery_emi_plan_id' => $emiFields['jewellery_emi_plan_id'],
                'emi_tenure' => $emiFields['emi_tenure'],
                'total_emi_cost' => $emiFields['total_emi_cost'],
                'monthly_emi_amount' => $emiFields['monthly_emi_amount'],
                'status' => 'pending',
                'shipping_address' => AddressPayload::make($address)['full_address'],
                'shipping_name' => $address->full_name,
                'shipping_phone' => $address->phone,
                'shipping_address_type' => $address->address_type,
                // EMI: hold delivery until all installments are paid.
                'expected_delivery_date' => $isEmi ? null : $delivery['date'],
                'delivery_otp' => $isEmi ? null : DeliveryOtp::generate(),
            ]);

            JewelleryOrderItem::query()->create([
                'jewellery_order_id' => $order->id,
                'jewellery_product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $breakup['unit_price'],
                'line_total' => $breakup['subtotal'],
            ]);

            if ($isEmi) {
                $this->emi->createInstallmentSchedule($order);
            }

            // Full payment is treated as paid at checkout (same as metal buy).
            $payment = Payment::query()->create([
                'reference_id' => $transactionId
                    ? (string) $transactionId
                    : 'PAY-'.strtoupper(uniqid()),
                'user_id' => $user->id,
                'payable_type' => JewelleryOrder::class,
                'payable_id' => $order->id,
                'amount' => $isEmi
                    ? (float) ($emiFields['monthly_emi_amount'] ?? $breakup['total'])
                    : $breakup['total'],
                'currency' => 'INR',
                'status' => $isEmi ? 'pending' : 'completed',
                'gateway' => $isEmi ? null : ($paymentMethod ?: 'direct'),
                'paid_at' => $isEmi ? null : now(),
            ]);

            $order->update(['payment_id' => $payment->id]);

            return $order->fresh([
                'items.product.category',
                'items.product.subCategory',
                'payment',
                'emiPlan',
                'emiInstallments',
            ]);
        });

        $invoice = null;
        if ($paymentType === 'full') {
            $invoice = $this->invoices->generateForJewelleryOrder($order);
        }

        $emi = $this->emiContext(
            $breakup['total'],
            $emiFields['jewellery_emi_plan_id'] ?? $emiPlanId,
            $emiFields['emi_tenure'] ?? $tenure,
            $emiFields['total_emi_cost'] ?? $totalEmiCost,
        );

        $expectedDelivery = $paymentType === 'emi'
            ? [
                'date' => null,
                'date_display' => 'After all EMI installments are paid',
                'held' => true,
                'message' => 'Jewellery will be delivered only after all monthly EMIs are paid.',
            ]
            : $delivery;

        return [
            'product' => JewelleryProductPayload::make($product),
            'quantity' => $quantity,
            'address' => AddressPayload::make($address),
            'price_breakup' => $breakup,
            'expected_delivery' => $expectedDelivery,
            'order_date' => $order->created_at?->toDateString() ?? now()->toDateString(),
            'order_date_display' => ($order->created_at ?? now())->format('d F Y'),
            'payment_types' => [
                ['key' => 'full', 'label' => 'Pay in Full'],
                ['key' => 'emi', 'label' => 'EMI'],
            ],
            'emi' => $emi,
            'order' => $this->orderPayload($order),
            'payment' => $this->paymentPayload($order->payment),
            'invoice' => $invoice ? $this->invoices->apiPayload($invoice) : null,
        ];
    }

    /**
     * @return array{
     *     metal_value: float,
     *     gold_value: float,
     *     making_charges: float,
     *     making_charge_percent: float,
     *     weight_grams: ?float,
     *     rate_per_gram: ?float,
     *     unit_price: float,
     *     subtotal_before_discount: float,
     *     discount_type: ?string,
     *     discount_value: ?float,
     *     subtotal: float,
     *     gst_percent: float,
     *     gst_amount: float,
     *     cgst: float,
     *     sgst: float,
     *     original_amount: float,
     *     discount_amount: float,
     *     discounted_amount: float,
     *     total: float
     * }
     */
    public function priceBreakup(JewelleryProduct $product, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $pricing = JewelleryPricing::calculate(
            $product->metal_type,
            $product->weight_grams,
            $product->making_charge_percent,
            $product->discount_type,
            $product->discount_value,
        );

        $unitPrice = $pricing['total'] > 0 ? $pricing['total'] : (float) $product->price;
        $metalValue = round($pricing['metal_value'] * $quantity, 2);
        $makingCharges = round($pricing['making_charge_amount'] * $quantity, 2);
        $subtotalBeforeDiscount = round($pricing['subtotal_before_discount'] * $quantity, 2);
        $discountAmount = round($pricing['discount_amount'] * $quantity, 2);
        $subtotal = round($unitPrice * $quantity, 2);

        $gst = $this->gst->calculateGstAmount($subtotal);
        $total = $gst['total'];

        return [
            'metal_value' => $metalValue,
            'gold_value' => $metalValue,
            'making_charges' => $makingCharges,
            'making_charge_percent' => $pricing['making_charge_percent'],
            'weight_grams' => $product->weight_grams !== null
                ? round((float) $product->weight_grams * $quantity, 3)
                : null,
            'rate_per_gram' => $pricing['rate_per_gram'],
            'unit_price' => $unitPrice,
            'subtotal_before_discount' => $subtotalBeforeDiscount,
            'discount_type' => $product->discount_type,
            'discount_value' => $product->discount_value !== null
                ? (float) $product->discount_value
                : null,
            'subtotal' => $subtotal,
            'gst_percent' => $this->gst->ratePercent(),
            'gst_amount' => $gst['gst_amount'],
            'cgst' => $gst['cgst'],
            'sgst' => $gst['sgst'],
            'original_amount' => $total,
            'discount_amount' => $discountAmount,
            'discounted_amount' => $total,
            'total' => $total,
        ];
    }

    /**
     * @return array{
     *     days: int,
     *     date: string,
     *     date_display: string,
     *     label: string
     * }
     */
    public function expectedDelivery(?Carbon $from = null): array
    {
        $from ??= now();
        $days = $this->settings->jewelleryDeliveryDays();
        $date = $from->copy()->addDays($days);

        return [
            'days' => $days,
            'date' => $date->toDateString(),
            'date_display' => $date->format('d F Y'),
            'label' => 'Delivery expected by '.$date->format('d F Y'),
        ];
    }

    protected function resolveProduct(int $productId): JewelleryProduct
    {
        $product = JewelleryProduct::query()
            ->with(['category', 'subCategory'])
            ->where('is_active', true)
            ->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found.'],
            ]);
        }

        return $product;
    }

    protected function resolveAddress(User $user, ?int $addressId, bool $required = false): ?UserAddress
    {
        if ($addressId) {
            $address = $user->addresses()->whereKey($addressId)->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => ['Address not found.'],
                ]);
            }

            $this->blockedPincodeService->assertNotBlocked($address->pincode, 'address_id');

            return $address;
        }

        $address = $user->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();

        if ($required && ! $address) {
            throw ValidationException::withMessages([
                'address_id' => ['Please add a delivery address before buying.'],
            ]);
        }

        if ($address) {
            $this->blockedPincodeService->assertNotBlocked($address->pincode, 'address_id');
        }

        return $address;
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'HOX'.random_int(10000, 99999);
        } while (JewelleryOrder::query()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * @return array{
     *     options: list<array<string, mixed>>,
     *     selected: ?array<string, mixed>
     * }
     */
    protected function emiContext(
        float $orderTotal,
        ?int $emiPlanId = null,
        ?int $tenure = null,
        ?float $totalEmiCost = null,
    ): array {
        $options = $this->emi->optionsForAmount($orderTotal);

        $selected = null;

        if ($emiPlanId !== null) {
            $selected = collect($options)->firstWhere('id', $emiPlanId);
        } elseif ($tenure !== null && $totalEmiCost !== null) {
            $direct = $this->emi->resolveDirect($tenure, $totalEmiCost);
            $selected = [
                'tenure_months' => $direct['tenure_months'],
                'tenure_label' => $direct['tenure_months'].' month'.($direct['tenure_months'] === 1 ? '' : 's'),
                'total_emi_cost' => $direct['total_emi_cost'],
                'total_emi_cost_display' => '₹'.number_format($direct['total_emi_cost'], 2),
                'monthly_emi_amount' => $direct['monthly_emi_amount'],
                'monthly_emi_amount_display' => '₹'.number_format($direct['monthly_emi_amount'], 2),
            ];
        }

        return [
            'options' => $options,
            'selected' => $selected,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $emiSelection
     * @return array{
     *     jewellery_emi_plan_id: ?int,
     *     emi_tenure: ?int,
     *     total_emi_cost: ?float,
     *     monthly_emi_amount: ?float
     * }
     */
    protected function emiOrderFields(?array $emiSelection): array
    {
        if ($emiSelection === null) {
            return [
                'jewellery_emi_plan_id' => null,
                'emi_tenure' => null,
                'total_emi_cost' => null,
                'monthly_emi_amount' => null,
            ];
        }

        return [
            'jewellery_emi_plan_id' => $emiSelection['plan']?->id,
            'emi_tenure' => $emiSelection['tenure_months'] ?? null,
            'total_emi_cost' => $emiSelection['total_emi_cost'] ?? null,
            'monthly_emi_amount' => $emiSelection['monthly_emi_amount'] ?? null,
        ];
    }

    protected function orderPayload(JewelleryOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_number_display' => '#'.$order->order_number,
            'status' => $order->status,
            'payment_type' => $order->payment_mode,
            'subtotal' => (float) $order->subtotal,
            'metal_value' => (float) $order->metal_value,
            'making_charge_amount' => (float) $order->making_charge_amount,
            'gst_percent' => (float) $order->gst_percent,
            'gst_amount' => (float) $order->gst_amount,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'emi' => JewelleryEmiPayload::forOrder($order),
            'shipping_name' => $order->shipping_name,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address,
            'shipping_address_type' => $order->shipping_address_type,
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
            'delivery_otp' => $order->delivery_otp,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn (JewelleryOrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->jewellery_product_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'product' => $item->product
                    ? JewelleryProductPayload::make($item->product)
                    : null,
            ])->values()->all(),
        ];
    }

    protected function paymentPayload(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'reference_id' => $payment->reference_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'gateway' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
