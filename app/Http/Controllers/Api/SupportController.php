<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AppSettingService;
use App\Support\ApiResponse;
use App\Support\AppConfigPayload;
use App\Support\SupportTicketPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    public function config(AppSettingService $settings): JsonResponse
    {
        return ApiResponse::success([
            'categories' => config('support.categories', []),
            'status_filters' => config('support.filters', []),
            'customer_care' => AppConfigPayload::customerCare($settings),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['all', 'open', 'pending', 'resolved'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 10);
        $status = $data['status'] ?? 'all';

        $query = $request->user()
            ->supportTickets()
            ->latest('last_activity_at')
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (filled($data['search'] ?? null)) {
            $search = $data['search'];
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('ticket_number', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
            });
        }

        $tickets = $query->paginate($perPage);

        return ApiResponse::success([
            'tickets' => SupportTicketPayload::collection($tickets->getCollection()),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
                'has_more' => $tickets->hasMorePages(),
                'showing' => $tickets->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedTicket($request);

        $ticket = $user->supportTickets()->create([
            'ticket_number' => $this->generateTicketNumber(),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'status' => 'open',
            'message' => $data['message'],
            'last_activity_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'],
            'is_staff_reply' => false,
        ]);

        return ApiResponse::success([
            'ticket' => SupportTicketPayload::make($ticket->fresh(), detailed: true),
        ], 'Support request submitted successfully.', 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->ensureOwnedByUser($request, $ticket);

        return ApiResponse::success([
            'ticket' => SupportTicketPayload::make($ticket->load('messages'), detailed: true),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureOwnedByUser($request, $ticket);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            throw ValidationException::withMessages([
                'ticket' => ['This ticket is closed. Please raise a new support request.'],
            ]);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'],
            'is_staff_reply' => false,
        ]);

        $ticket->update([
            'last_activity_at' => now(),
            'status' => $ticket->status === 'pending' ? 'pending' : 'open',
        ]);

        return ApiResponse::success([
            'ticket' => SupportTicketPayload::make($ticket->fresh()->load('messages'), detailed: true),
        ], 'Reply sent successfully.');
    }

    protected function validatedTicket(Request $request): array
    {
        $categoryValues = collect(config('support.categories', []))
            ->pluck('value')
            ->all();

        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in($categoryValues)],
            'message' => ['required', 'string', 'max:5000'],
        ]);
    }

    protected function ensureOwnedByUser(Request $request, SupportTicket $ticket): void
    {
        if ($ticket->user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'ticket' => ['Ticket not found.'],
            ])->status(404);
        }
    }

    protected function generateTicketNumber(): string
    {
        $prefix = config('support.ticket_prefix', 'HXT');

        do {
            $number = $prefix.'-'.random_int(1000, 99999);
        } while (SupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
