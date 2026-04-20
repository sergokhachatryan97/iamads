<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-4">
                <a href="{{ route('staff.settings.roles.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                    ← {{ __('Back to Roles') }}
                </a>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Edit Role') }}: {{ $role->name }}</h3>

            @if($role->name === 'super_admin')
                <div class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <p class="text-sm text-indigo-800">
                        <strong>{{ __('Super Admin') }}</strong> — {{ __('This role has full access to all features. Permissions cannot be restricted.') }}
                    </p>
                </div>
            @else
                <p class="text-sm text-gray-500 mb-6">{{ __('Toggle permissions for this role. Changes apply immediately to all users with this role.') }}</p>

                <form method="POST" action="{{ route('staff.settings.roles.update', $role) }}" x-data="{
                    checkAll(group) {
                        const boxes = this.$refs[group].querySelectorAll('input[type=checkbox]');
                        const allChecked = [...boxes].every(b => b.checked);
                        boxes.forEach(b => b.checked = !allChecked);
                    }
                }">
                    @csrf
                    @method('PUT')

                    @php
                        $grouped = $permissions->groupBy(function ($perm) {
                            return explode('.', $perm->name)[0];
                        })->sortKeys();

                        $groupLabels = [
                            'clients' => 'Clients',
                            'orders' => 'Orders',
                            'payments' => 'Payments',
                            'services' => 'Services',
                            'categories' => 'Categories',
                            'subscriptions' => 'Subscriptions',
                            'exports' => 'Exports',
                            'settings' => 'Settings',
                            'users' => 'Managers',
                            'telegram-stats' => 'Telegram Stats',
                            'provider-order-stats' => 'Provider Stats',
                            'activity-logs' => 'Activity Logs',
                            'socpanel-moderation' => 'Socpanel',
                        ];

                        $groupIcons = [
                            'clients' => 'fa-users',
                            'orders' => 'fa-clipboard-list',
                            'payments' => 'fa-credit-card',
                            'services' => 'fa-layer-group',
                            'categories' => 'fa-tags',
                            'subscriptions' => 'fa-receipt',
                            'exports' => 'fa-file-export',
                            'settings' => 'fa-gear',
                            'users' => 'fa-user-tie',
                            'telegram-stats' => 'fa-chart-bar',
                            'provider-order-stats' => 'fa-chart-pie',
                            'activity-logs' => 'fa-clock-rotate-left',
                            'socpanel-moderation' => 'fa-shield-halved',
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        @foreach($grouped as $group => $perms)
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid {{ $groupIcons[$group] ?? 'fa-circle' }} text-gray-400 text-sm"></i>
                                        <span class="text-sm font-semibold text-gray-700">{{ $groupLabels[$group] ?? ucfirst($group) }}</span>
                                        <span class="text-xs text-gray-400">({{ $perms->count() }})</span>
                                    </div>
                                    <button type="button" @click="checkAll('group_{{ $group }}')"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        {{ __('Toggle All') }}
                                    </button>
                                </div>
                                <div class="px-4 py-3 space-y-2" x-ref="group_{{ $group }}">
                                    @foreach($perms as $permission)
                                        @php
                                            $action = str_replace($group . '.', '', $permission->name);
                                            $actionLabel = ucfirst(str_replace('-', ' ', $action));
                                        @endphp
                                        <label class="flex items-center gap-2.5 cursor-pointer group">
                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission->name }}"
                                                {{ $role->hasPermissionTo($permission) ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-4 h-4"
                                            >
                                            <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ $actionLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @error('permissions')
                        <p class="mb-4 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save Permissions') }}</x-primary-button>

                        @if (session('status') === 'role-updated')
                            <p
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 3000)"
                                class="text-sm text-green-600 font-medium"
                            >{{ __('Permissions saved.') }}</p>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-settings-layout>
