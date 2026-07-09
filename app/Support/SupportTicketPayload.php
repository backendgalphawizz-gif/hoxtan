<?php

namespace App\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportTicketPayload
{
    public static function make(SupportTicket $ticket, bool $detailed = false): array
    {
        $payload = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'ticket_number_display' => '#'.$ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'category_label' => self::categoryLabel($ticket->category),
            'status' => $ticket->status,
            'status_label' => self::statusLabel($ticket->status),
            'message' => $ticket->message,
            'message_preview' => Str::limit(trim($ticket->message), 120),
            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            'last_activity_date' => $ticket->last_activity_at?->format('M d, Y'),
            'last_activity_time' => $ticket->last_activity_at?->format('H:i').' GMT',
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $ticket->loadMissing('messages');

            $payload['submitted_at_display'] = $ticket->created_at?->format('M d, Y | h:i A');
            $payload['tracking'] = self::tracking($ticket);
            $payload['messages'] = $ticket->messages
                ->map(fn (SupportTicketMessage $message) => self::message($message))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * Vertical status tracker for the Ticket Status screen.
     *
     * @return list<array{key: string, label: string, completed: bool, current: bool, completed_at: ?string}>
     */
    public static function tracking(SupportTicket $ticket): array
    {
        $steps = config('support.tracking_steps', []);
        $currentIndex = (int) (config('support.status_tracking_index.'.$ticket->status) ?? 1);

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) use ($ticket, $currentIndex): array {
                $completed = $index <= $currentIndex;
                $current = $index === $currentIndex;

                return [
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'completed' => $completed,
                    'current' => $current,
                    'completed_at' => $completed
                        ? self::trackingTimestamp($ticket, $step['key'])
                        : null,
                ];
            })
            ->all();
    }

    protected static function trackingTimestamp(SupportTicket $ticket, string $stepKey): ?string
    {
        $at = match ($stepKey) {
            'submitted' => $ticket->created_at,
            'under_review' => $ticket->created_at,
            'action_pending', 'accepted' => $ticket->last_activity_at ?? $ticket->updated_at,
            'resolved' => $ticket->resolved_at ?? $ticket->last_activity_at ?? $ticket->updated_at,
            default => null,
        };

        return $at?->toIso8601String();
    }

    /**
     * @param  Collection<int, SupportTicket>  $tickets
     */
    public static function collection(Collection $tickets): array
    {
        return $tickets
            ->map(fn (SupportTicket $ticket) => self::make($ticket))
            ->values()
            ->all();
    }

    public static function message(SupportTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'message' => $message->message,
            'is_staff_reply' => $message->is_staff_reply,
            'sender_type' => $message->is_staff_reply ? 'staff' : 'user',
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_display' => $message->created_at?->format('M d, Y, H:i').' GMT',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        $match = collect(config('support.categories', []))
            ->firstWhere('value', $category);

        return $match['label'] ?? Str::headline(str_replace('_', ' ', $category));
    }

    public static function statusLabel(string $status): string
    {
        return config('support.statuses.'.$status, Str::upper(str_replace('_', ' ', $status)));
    }
}
