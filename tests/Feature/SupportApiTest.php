<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_raise_and_list_support_tickets(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $config = $this->getJson('/api/v1/support/config');

        $config->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'status_filters',
                    'customer_care' => ['voice_support', 'email_concierge'],
                ],
            ]);

        $create = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Vault Access Inquiry',
            'category' => 'vault_access',
            'message' => 'I am requesting immediate priority access to my physical vault holdings.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket.subject', 'Vault Access Inquiry')
            ->assertJsonPath('data.ticket.status', 'open')
            ->assertJsonPath('data.ticket.status_label', 'UNDER REVIEW');

        $ticketId = $create->json('data.ticket.id');

        $list = $this->getJson('/api/v1/support/tickets?status=open');

        $list->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.tickets.0.id', $ticketId);

        $search = $this->getJson('/api/v1/support/tickets?search=Vault');

        $search->assertOk()
            ->assertJsonPath('data.tickets.0.subject', 'Vault Access Inquiry');

        $show = $this->getJson("/api/v1/support/tickets/{$ticketId}");

        $show->assertOk()
            ->assertJsonStructure(['data' => ['ticket' => ['messages']]]);

        $reply = $this->postJson("/api/v1/support/tickets/{$ticketId}/replies", [
            'message' => 'Please update me on the timeline.',
        ]);

        $reply->assertOk()
            ->assertJsonPath('message', 'Reply sent successfully.');
    }

    public function test_user_cannot_access_another_users_ticket(): void
    {
        $owner = User::factory()->create(['phone' => '9876543210', 'mpin' => '1234']);
        $other = User::factory()->create(['phone' => '9876543211', 'mpin' => '1234']);

        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'ticket_number' => 'HXT-12345',
            'subject' => 'Private ticket',
            'category' => 'general',
            'status' => 'open',
            'message' => 'Private message',
            'last_activity_at' => now(),
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/v1/support/tickets/{$ticket->id}")
            ->assertNotFound();
    }
}
