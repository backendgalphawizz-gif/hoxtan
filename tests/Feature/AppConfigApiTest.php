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

        StaticPage::query()->create([
            'title' => 'Delete Your Account',
            'slug' => 'delete-account',
            'content' => '<p>Delete account instructions</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'User Privacy Policy',
            'slug' => 'user-privacy-policy',
            'content' => '<p>User privacy content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'User Terms & Conditions',
            'slug' => 'user-terms-and-conditions',
            'content' => '<p>User terms content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Driver Privacy Policy',
            'slug' => 'driver-privacy-policy',
            'content' => '<p>Driver privacy content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Driver Terms & Conditions',
            'slug' => 'driver-terms-and-conditions',
            'content' => '<p>Driver terms content</p>',
            'is_published' => true,
        ]);

        $response = $this->getJson('/api/v1/app/config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.surepass_token', (string) config('kyc.surepass.token', ''))
            ->assertJsonPath('data.surepass.token', (string) config('kyc.surepass.token', ''))
            ->assertJsonPath('data.privacy.slug', 'user-privacy-policy')
            ->assertJsonPath('data.terms.slug', 'user-terms-and-conditions')
            ->assertJsonPath('data.driver_privacy.slug', 'driver-privacy-policy')
            ->assertJsonPath('data.driver_terms.slug', 'driver-terms-and-conditions')
            ->assertJsonPath('data.driver.privacy.slug', 'driver-privacy-policy')
            ->assertJsonPath('data.driver.terms.slug', 'driver-terms-and-conditions')
            ->assertJsonPath('data.faqs_screen.headline', 'How may we assist you?')
            ->assertJsonPath('data.terms.version', 'V.4.02')
            ->assertJsonPath('data.privacy.tagline', 'Your trust is our most valuable asset.')
            ->assertJsonStructure([
                'data' => [
                    'app',
                    'play_store' => ['privacy_policy_url', 'delete_account_url', 'terms_url'],
                    'faq_categories',
                    'faqs',
                    'concierge',
                    'support',
                    'terms' => ['sections', 'agreement_summary', 'url', 'slug'],
                    'privacy' => ['sections', 'url', 'slug'],
                    'driver_privacy' => ['title', 'content', 'url', 'slug'],
                    'driver_terms' => ['title', 'content', 'url', 'slug'],
                    'driver' => [
                        'privacy' => ['title', 'content', 'url', 'slug'],
                        'terms' => ['title', 'content', 'url', 'slug'],
                        'play_store',
                    ],
                    'delete_account' => ['url', 'steps', 'close_account'],
                ],
            ]);
    }

    public function test_driver_app_config_returns_driver_privacy_and_terms(): void
    {
        StaticPage::query()->create([
            'title' => 'Driver Privacy Policy',
            'slug' => 'driver-privacy-policy',
            'content' => '<p>Driver privacy content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Driver Terms & Conditions',
            'slug' => 'driver-terms-and-conditions',
            'content' => '<p>Driver terms content</p>',
            'is_published' => true,
        ]);

        $this->getJson('/api/v1/driver/app/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.privacy.slug', 'driver-privacy-policy')
            ->assertJsonPath('data.terms.slug', 'driver-terms-and-conditions')
            ->assertJsonStructure([
                'data' => [
                    'app',
                    'privacy' => ['title', 'content', 'url', 'embed_url'],
                    'terms' => ['title', 'content', 'url', 'embed_url'],
                    'play_store',
                ],
            ]);
    }

    public function test_play_store_pages_are_public_on_website(): void
    {
        StaticPage::query()->create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => '<p>Privacy content</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Delete Your Account',
            'slug' => 'delete-account',
            'content' => '<p>Delete account instructions</p>',
            'is_published' => true,
        ]);

        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
        $this->get('/delete-account')->assertOk()->assertSee('Delete Your Account');
    }

    public function test_embed_pages_show_content_without_header_or_footer(): void
    {
        StaticPage::query()->create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => '<h2>Introduction</h2><p>Privacy content only.</p>',
            'is_published' => true,
        ]);

        StaticPage::query()->create([
            'title' => 'Delete Your Account',
            'slug' => 'delete-account',
            'content' => '<h2>Delete your account</h2><p>Delete account instructions only.</p>',
            'is_published' => true,
        ]);

        $this->get('/embed/privacy-policy')
            ->assertOk()
            ->assertSee('Introduction')
            ->assertSee('Privacy content only.')
            ->assertDontSee('Get the App')
            ->assertDontSee('All rights reserved');

        $this->get('/embed/delete-account')
            ->assertOk()
            ->assertSee('Delete your account')
            ->assertSee('Delete account instructions only.')
            ->assertDontSee('Get the App')
            ->assertDontSee('All rights reserved');
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
