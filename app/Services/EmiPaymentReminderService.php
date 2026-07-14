<?php

namespace App\Services;

use App\Models\JewelleryOrderEmiInstallment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EmiPaymentReminderService
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
    ) {}

    /**
     * Send EMI payment reminders for installments due within the reminder window
     * (default: 3 days before due date) and every day until paid.
     *
     * @return array{sent: int, skipped: int}
     */
    public function sendDueReminders(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        $daysBefore = max(0, (int) config('emi_reminders.days_before', 3));
        $windowEnd = $now->copy()->addDays($daysBefore)->toDateString();
        $type = (string) config('emi_reminders.notification_type', 'emi_payment_reminder');

        $sent = 0;
        $skipped = 0;

        JewelleryOrderEmiInstallment::query()
            ->with(['order.user'])
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $windowEnd)
            ->where(function ($query) use ($now): void {
                $query->whereNull('last_reminded_at')
                    ->orWhereDate('last_reminded_at', '<', $now->toDateString());
            })
            ->whereHas('order', function ($query): void {
                $query->where('payment_mode', 'emi')
                    ->whereNotIn('status', ['cancelled', 'failed']);
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->chunkById(100, function ($installments) use ($now, $type, &$sent, &$skipped): void {
                foreach ($installments as $installment) {
                    if ($this->remindOne($installment, $now, $type)) {
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function remindOne(JewelleryOrderEmiInstallment $installment, CarbonInterface $today, string $type): bool
    {
        $order = $installment->order;
        $user = $order?->user;

        if (! $order || ! $user || $user->is_blocked) {
            return false;
        }

        $dueDate = $installment->due_date?->copy()->startOfDay();
        if (! $dueDate) {
            return false;
        }

        $daysUntilDue = $this->daysUntil($today, $dueDate);
        [$title, $body] = $this->copyFor($installment, $order->order_number, $dueDate, $daysUntilDue);

        try {
            $this->inbox->notifyUser(
                $user,
                $title,
                $body,
                $type,
                [
                    'emi_installment_id' => (string) $installment->id,
                    'jewellery_order_id' => (string) $order->id,
                    'order_number' => (string) $order->order_number,
                    'installment_number' => (string) $installment->installment_number,
                    'amount' => (string) $installment->amount,
                    'due_date' => $dueDate->toDateString(),
                    'days_until_due' => (string) $daysUntilDue,
                ],
            );

            $installment->update(['last_reminded_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::error('EMI reminder failed', [
                'installment_id' => $installment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function daysUntil(CarbonInterface $today, CarbonInterface $dueDate): int
    {
        $today = Carbon::parse($today)->startOfDay();
        $due = Carbon::parse($dueDate)->startOfDay();

        if ($due->equalTo($today)) {
            return 0;
        }

        $days = (int) $today->diffInDays($due);

        return $due->greaterThan($today) ? $days : -$days;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copyFor(
        JewelleryOrderEmiInstallment $installment,
        string $orderNumber,
        CarbonInterface $dueDate,
        int $daysUntilDue,
    ): array {
        $emiLabel = $installment->label();
        $amount = '₹'.number_format((float) $installment->amount, 2);
        $dueLabel = $dueDate->format('d M Y');

        if ($daysUntilDue > 1) {
            return [
                'EMI payment reminder',
                "{$emiLabel} of {$amount} for order {$orderNumber} is due in {$daysUntilDue} days ({$dueLabel}). Please pay on time.",
            ];
        }

        if ($daysUntilDue === 1) {
            return [
                'EMI due tomorrow',
                "{$emiLabel} of {$amount} for order {$orderNumber} is due tomorrow ({$dueLabel}). Please pay on time.",
            ];
        }

        if ($daysUntilDue === 0) {
            return [
                'EMI due today',
                "{$emiLabel} of {$amount} for order {$orderNumber} is due today. Please complete your payment.",
            ];
        }

        $overdueDays = abs($daysUntilDue);

        return [
            'EMI payment overdue',
            "{$emiLabel} of {$amount} for order {$orderNumber} was due on {$dueLabel} ({$overdueDays} day".($overdueDays === 1 ? '' : 's').' overdue). Please pay now.',
        ];
    }
}
