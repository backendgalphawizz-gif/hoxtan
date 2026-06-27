<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            heading="Report Categories"
            description="Select a report type to view records and export data."
        >
            <x-filament::tabs contained>
                <x-filament::tabs.item
                    :active="$activeTab === 'investment'"
                    wire:click="setActiveTab('investment')"
                    icon="heroicon-o-arrow-trending-up"
                >
                    Investment Reports
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'revenue'"
                    wire:click="setActiveTab('revenue')"
                    icon="heroicon-o-currency-rupee"
                >
                    Revenue Reports
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'users'"
                    wire:click="setActiveTab('users')"
                    icon="heroicon-o-users"
                >
                    User Reports
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'tax'"
                    wire:click="setActiveTab('tax')"
                    icon="heroicon-o-calculator"
                >
                    Tax Reports
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'redemption'"
                    wire:click="setActiveTab('redemption')"
                    icon="heroicon-o-gift"
                >
                    Redemption Reports
                </x-filament::tabs.item>
            </x-filament::tabs>
        </x-filament::section>

        <x-filament::section>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
