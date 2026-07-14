<?php

return [
    /*
    | Days before the EMI due date to start daily reminders.
    | Reminder continues every day while the installment stays pending.
    */
    'days_before' => (int) env('EMI_REMINDER_DAYS_BEFORE', 3),

    'schedule_at' => env('EMI_REMINDER_SCHEDULE_AT', '09:00'),

    'notification_type' => 'emi_payment_reminder',
];
