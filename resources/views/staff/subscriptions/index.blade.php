<x-app-layout>
    <script>
        function subscriptionDeleteModal() {
            return {
                openDeleteModal: false,
                planToDelete: null,
                planNameToDelete: '',
                deleteUrl: '',
                openDeleteConfirmation(planId, planName, url) {
                    this.planToDelete = planId;
                    this.planNameToDelete = planName;
                    this.deleteUrl = url;
                    this.openDeleteModal = true;
                },
                closeDeleteModal() {
                    this.openDeleteModal = false;
                    this.planToDelete = null;
                    this.planNameToDelete = '';
                    this.deleteUrl = '';
                },
                submitDelete() {
                    if (!this.deleteUrl) {
                        console.error('Delete URL is not set');
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = this.deleteUrl;
                    form.style.display = 'none';

                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfMeta) {
                        console.error('CSRF meta tag not found');
                        alert('Error: Unable to find security token. Please refresh the page and try again.');
                        return;
                    }

                    const csrfToken = csrfMeta.getAttribute('content');
                    if (!csrfToken) {
                        console.error('CSRF token content is empty');
                        alert('Error: Security token is missing. Please refresh the page and try again.');
                        return;
                    }

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'DELETE';
                    form.appendChild(methodInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
    </script>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Subscription Plans') }}
            </h2>
            <div class="flex gap-3">
                <a href="{{ route('staff.subscriptions.edit-header') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    {{ __('Edit Subscription Header') }}
                </a>
                <a href="{{ route('staff.subscriptions.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ __('Create Plan') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">
                        @if (session('status') === 'subscription-plan-created')
                            {{ __('Subscription plan created successfully.') }}
                        @elseif (session('status') === 'subscription-plan-updated')
                            {{ __('Subscription plan updated successfully.') }}
                        @elseif (session('status') === 'subscription-plan-deleted')
                            {{ __('Subscription plan deleted successfully.') }}
                        @elseif (session('status') === 'client-header-text-updated')
                            {{ __('Client subscriptions header text updated successfully.') }}
                        @endif
                    </p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                 x-data="subscriptionDeleteModal()"
                 @open-delete-modal.window="openDeleteConfirmation($event.detail.planId, $event.detail.planName, $event.detail.url)"
            >
                {{-- Delete Confirmation Modal --}}
                <div
                    x-show="openDeleteModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    style="display: none;"
                    @click.away="closeDeleteModal()"
                    @keydown.escape.window="closeDeleteModal()"
                >
                    <div class="flex min-h-full items-center justify-center p-4">
                        {{-- Background overlay --}}
                        <div
                            x-show="openDeleteModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity"
                            @click="closeDeleteModal()"
                            style="display: none;"
                        ></div>

                        {{-- Modal panel --}}
                        <div
                            x-show="openDeleteModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            class="relative transform overflow-hidden rounded-xl bg-white shadow-2xl transition-all sm:w-full sm:max-w-md"
                            style="display: none;"
                            @click.stop
                        >
                            <div class="bg-white px-6 pt-6 pb-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-50">
                                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            {{ __('Delete Subscription Plan') }}
                                        </h3>
                                        <div class="mt-3">
                                            <p class="text-sm leading-6 text-gray-600">
                                                {{ __('Are you sure you want to delete') }} <span class="font-semibold text-gray-900" x-text="planNameToDelete"></span>?
                                                <span class="block mt-1">{{ __('This action cannot be undone.') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-3">
                                <button
                                    type="button"
                                    @click="submitDelete()"
                                    class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 transition-colors"
                                >
                                    {{ __('Delete') }}
                                </button>
                                <button
                                    type="button"
                                    @click="closeDeleteModal()"
                                    class="inline-flex items-center justify-center rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 transition-colors"
                                >
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 text-gray-900">
                    @if($plans->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prices</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($plans as $plan)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $plan->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $plan->category?->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $plan->currency }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @php
                                                    $dailyPrice = $plan->prices->where('billing_cycle', 'daily')->first()?->price;
                                                    $monthlyPrice = $plan->prices->where('billing_cycle', 'monthly')->first()?->price;
                                                @endphp
                                                @if($dailyPrice) Daily: {{ number_format($dailyPrice, 2) }}<br>@endif
                                                @if($monthlyPrice) Monthly: {{ number_format($monthlyPrice, 2) }}@endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $plan->is_active ? __('Active') : __('Inactive') }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $plan->preview_variant }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <x-dropdown align="right" width="48">
                                                    <x-slot name="trigger">
                                                        <button class="inline-flex items-center px-2 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                                            Actions
                                                        </button>
                                                    </x-slot>

                                                    <x-slot name="content">
                                                        <x-dropdown-link :href="route('staff.subscriptions.edit', $plan)">
                                                            {{ __('Edit') }}
                                                        </x-dropdown-link>

                                                        <button
                                                            type="button"
                                                            @click.stop="open = false; $dispatch('open-delete-modal', { planId: {{ $plan->id }}, planName: '{{ addslashes($plan->name) }}', url: '{{ route('staff.subscriptions.destroy', $plan) }}' })"
                                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 hover:bg-red-50 focus:outline-none focus:bg-red-50 transition duration-150 ease-in-out"
                                                        >
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </x-slot>
                                                </x-dropdown>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">{{ __('No subscription plans found.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
