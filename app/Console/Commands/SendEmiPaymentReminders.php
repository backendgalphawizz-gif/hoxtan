<?php

namespace App\Console\Commands;

use App\Services\EmiPaymentReminderService;
use Illuminate\Console\Command;

class SendEmiPaymentReminders extends Command
{
    protected $signature = 'jewellery:send-emi-reminders';

    protected $description = 'Notify users about EMI payments from 3 days before due date, daily until paid';

    public function handle(EmiPaymentReminderService $reminders): int
    {
        $result = $reminders->sendDueReminders();

        $this->info("EMI reminders sent: {$result['sent']} (skipped: {$result['skipped']}).");

        return self::SUCCESS;
    }
}
