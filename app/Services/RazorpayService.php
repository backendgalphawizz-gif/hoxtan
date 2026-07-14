<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Throwable;

class RazorpayService
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id'))
            && filled(config('services.razorpay.key_secret'));
    }

    public function api(): Api
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => ['Razorpay is not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.'],
            ]);
        }

        return new Api(
            (string) config('services.razorpay.key_id'),
            (string) config('services.razorpay.key_secret'),
        );
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    /**
     * @param  array<string, mixed>  $notes
     * @return array{id: string, amount: int, currency: string, receipt: string, status: string}
     */
    public function createOrder(float $amountInr, string $receipt, array $notes = []): array
    {
        $amountPaise = (int) round($amountInr * 100);

        if ($amountPaise < 100) {
            throw ValidationException::withMessages([
                'amount' => ['Minimum payable amount is ₹1.00.'],
            ]);
        }

        try {
            $order = $this->api()->order->create([
                'receipt' => substr($receipt, 0, 40),
                'amount' => $amountPaise,
                'currency' => (string) config('services.razorpay.currency', 'INR'),
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            Log::error('Razorpay order create failed', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'payment' => ['Unable to create Razorpay order. Please try again.'],
            ]);
        }

        return [
            'id' => (string) $order['id'],
            'amount' => (int) $order['amount'],
            'currency' => (string) $order['currency'],
            'receipt' => (string) ($order['receipt'] ?? $receipt),
            'status' => (string) ($order['status'] ?? 'created'),
        ];
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): void
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
        } catch (SignatureVerificationError $e) {
            throw ValidationException::withMessages([
                'razorpay_signature' => ['Invalid Razorpay payment signature.'],
            ]);
        }
    }

    /**
     * @return array{id: string, status: string, amount: int, order_id: string, method?: string}
     */
    public function fetchPayment(string $paymentId): array
    {
        try {
            $payment = $this->api()->payment->fetch($paymentId);
        } catch (Throwable $e) {
            Log::error('Razorpay payment fetch failed', ['error' => $e->getMessage(), 'payment_id' => $paymentId]);

            throw ValidationException::withMessages([
                'razorpay_payment_id' => ['Unable to verify Razorpay payment.'],
            ]);
        }

        return [
            'id' => (string) $payment['id'],
            'status' => (string) $payment['status'],
            'amount' => (int) $payment['amount'],
            'order_id' => (string) ($payment['order_id'] ?? ''),
            'method' => isset($payment['method']) ? (string) $payment['method'] : null,
        ];
    }
}
