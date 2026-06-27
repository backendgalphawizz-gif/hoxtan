<div>
    <div class="gs-auth-card">
        <div class="gs-auth-card__brand">
            <img
                src="{{ asset('images/hoxtan-icon.png') }}"
                alt="hoxtan"
                class="gs-auth-card__brand-logo"
            >
            <h1 class="gs-auth-card__brand-title">HOXTAN</h1>
            <p class="gs-auth-card__brand-subtitle">Gold &bull; Silver &bull; Jewellery Admin Management System</p>
        </div>

        <div class="gs-auth-card__form">
            <img
                src="{{ asset('images/hoxtan-icon.png') }}"
                alt="hoxtan"
                class="gs-auth-card__form-logo"
            >

            <div class="gs-auth-card__form-header">
                <h2 class="gs-auth-card__form-title">Sign in to your account</h2>
                <p class="gs-auth-card__form-subtitle">Enter your credentials to access the admin dashboard.</p>
            </div>

            @if (session('status'))
                <div class="gs-auth-alert gs-auth-alert--success" role="status">
                    {{ session('status') }}
                </div>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate" class="gs-auth-form">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

            <p class="gs-auth-card__footer">Secure admin access only</p>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
