<div class="gs-permissions-matrix">
    <div class="gs-permissions-matrix__toolbar">
        <p class="gs-permissions-matrix__note">
            Sidebar menu items require the <strong>View</strong> permission. Create, Edit, Delete, or Export also show the tab when saved.
        </p>
        <div class="gs-permissions-matrix__actions">
            <x-filament::button size="sm" color="gray" type="button" wire:click="selectAllPermissions">
                Select all
            </x-filament::button>
            <x-filament::button size="sm" color="gray" type="button" wire:click="clearAllPermissions">
                Clear all
            </x-filament::button>
        </div>
    </div>

    <div class="gs-permissions-matrix__table-wrap">
        <table class="gs-permissions-matrix__table">
            <thead>
                <tr>
                    <th>Module</th>
                    @foreach ($actions as $actionLabel)
                        <th>{{ $actionLabel }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($modules as $moduleKey => $module)
                    <tr>
                        <td>
                            <span class="gs-permissions-matrix__module">{{ $module['label'] }}</span>
                            <span class="gs-permissions-matrix__group">{{ $module['group'] }}</span>
                        </td>
                        @foreach (array_keys($actions) as $actionKey)
                            <td class="text-center">
                                <input
                                    type="checkbox"
                                    class="gs-permissions-matrix__checkbox"
                                    wire:model.live="data.permissions.{{ $moduleKey }}.{{ $actionKey }}"
                                >
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
