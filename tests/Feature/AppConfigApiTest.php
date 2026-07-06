<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_config_returns_faqs_terms_and_privacy(): void
    {
        Faq::query()->create([
            'question' => 'What are the physical storage protocols for Hoxtan Gold?',
            'answer' => 'Allocated vault storage with quarterly audits.',
            'category' => 'vault_security',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Terms & Conditions',
            'slug' => 'terms-and-conditions',
            'content' => '<p>Terms content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => '<p>Privacy content</p>',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v1/app/config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.faqs_screen.headline', 'How may we assist you?')
            ->assertJsonPath('data.terms.version', 'V.4.02')
            ->assertJsonPath('data.privacy.tagline', 'Your trust is our most valuable asset.')
            ->assertJsonStructure([
                'data' => [
                    'app',
                    'faq_categories',
                    'faqs',
                    'concierge',
                    'support',
                    'terms' => ['sections', 'agreement_summary'],
                    'privacy' => ['sections'],
                ],
            ]);
    }

    public function test_faqs_endpoint_supports_search_and_category_filter(): void
    {
        Faq::query()->create([
            'question' => 'Vault question',
            'answer' => 'Vault answer',
            'category' => 'vault_security',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Faq::query()->create([
            'question' => 'Trading question',
            'answer' => 'Trading answer',
            'category' => 'trading',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/app/faqs?category=vault_security')
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.category', 'vault_security');

        $this->getJson('/api/v1/app/faqs?search=Trading')
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'Trading question');
    }
}
