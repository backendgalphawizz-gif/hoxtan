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
    public function summary(User $user, int $productId, int $quantity = 1, ?int $addressId = null): array
    {
        $product = $this->resolveProduct($productId);
        $address = $this->resolveAddress($user, $addressId);
        $breakup = $this->priceBreakup($product, $quantity);
        $delivery = $this->expectedDelivery();

        return [
            'product' => JewelleryProductPayload::make($product),
            'quantity' => $quantity,
            'address' => $address ? AddressPayload::make($address) : null,
            'price_breakup' => $breakup,
            'expected_delivery' => $delivery,
            'order_date' => now()->toDateString(),
            'order_date_display' => now()->format('d F Y'),
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
    public function buyNow(User $user, int $productId, int $quantity = 1, ?int $addressId = null): array
    {
        $product = $this->resolveProduct($productId, requireInStock: true);
        $address = $this->resolveAddress($user, $addressId, required: true);
        $breakup = $this->priceBreakup($product, $quantity);
        $delivery = $this->expectedDelivery();

        /** @var JewelleryOrder $order */
        $order = DB::transaction(function () use ($user, $product, $quantity, $address, $breakup, $delivery): JewelleryOrder {
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
                'status' => 'pending',
                'shipping_address' => AddressPayload::make($address)['full_address'],
                'shipping_name' => $address->full_name,
                'shipping_phone' => $address->phone,
                'shipping_address_type' => $address->address_type,
                'expected_delivery_date' => $delivery['date'],
            ]);

            JewelleryOrderItem::query()->create([
                'jewellery_order_id' => $order->id,
                'jewellery_product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $breakup['unit_price'],
                'line_total' => $breakup['subtotal'],
            ]);

            $payment = Payment::query()->create([
                'reference_id' => 'PAY-'.strtoupper(uniqid()),
                'user_id' => $user->id,
                'payable_type' => JewelleryOrder::class,
                'payable_id' => $order->id,
                'amount' => $breakup['total'],
                'currency' => 'INR',
                'status' => 'pending',
            ]);

            $order->update(['payment_id' => $payment->id]);

            return $order->fresh(['items.product.category', 'items.product.subCategory', 'payment']);
        });

        return [
            'product' => JewelleryProductPayload::make($product),
            'quantity' => $quantity,
            'address' => AddressPayload::make($address),
            'price_breakup' => $breakup,
            'expected_delivery' => $delivery,
            'order_date' => $order->created_at?->toDateString() ?? now()->toDateString(),
            'order_date_display' => ($order->created_at ?? now())->format('d F Y'),
            'order' => $this->orderPayload($order),
            'payment' => $this->paymentPayload($order->payment),
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
        );

        $unitPrice = $pricing['total'] > 0 ? $pricing['total'] : (float) $product->price;
        $metalValue = round($pricing['metal_value'] * $quantity, 2);
        $makingCharges = round($pricing['making_charge_amount'] * $quantity, 2);
        $subtotal = round($unitPrice * $quantity, 2);

        $gst = $this->gst->calculateGstAmount($subtotal);
        $originalAmount = $gst['total'];
        $discountAmount = 0.0;
        $total = round($originalAmount - $discountAmount, 2);

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
            'subtotal' => $subtotal,
            'gst_percent' => $this->gst->ratePercent(),
            'gst_amount' => $gst['gst_amount'],
            'cgst' => $gst['cgst'],
            'sgst' => $gst['sgst'],
            'original_amount' => $originalAmount,
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

    protected function resolveProduct(int $productId, bool $requireInStock = false): JewelleryProduct
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

        if ($requireInStock && $product->stock_status !== 'in_stock') {
            throw ValidationException::withMessages([
                'product_id' => ['This product is currently unavailable.'],
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

    protected function orderPayload(JewelleryOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_number_display' => '#'.$order->order_number,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'metal_value' => (float) $order->metal_value,
            'making_charge_amount' => (float) $order->making_charge_amount,
            'gst_percent' => (float) $order->gst_percent,
            'gst_amount' => (float) $order->gst_amount,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'shipping_name' => $order->shipping_name,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address,
            'shipping_address_type' => $order->shipping_address_type,
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
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
