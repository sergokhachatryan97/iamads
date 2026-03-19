<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Payments') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto sm:px-6 lg:px-8" style="max-width: 90rem;">
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif
            @if (session('error') || $errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ session('error') ?? $errors->first() }}</p>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('staff.payments.index') }}" class="mb-6 flex flex-wrap gap-3 items-end">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                            <select name="status" id="status" class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">{{ __('All') }}</option>
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="provider" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Provider') }}</label>
                            <input type="text" name="provider" id="provider" value="{{ request('provider') }}"
                                placeholder="{{ __('e.g. heleket') }}"
                                class="block w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ID') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Order ID') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Client') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Provider') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Amount') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Paid At') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Created') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $payment->id }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $payment->order_id }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            @if ($payment->client)
                                                <a href="{{ route('staff.clients.edit', $payment->client) }}" class="text-indigo-600 hover:text-indigo-800">
                                                    {{ $payment->client->name ?? $payment->client->email }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $payment->provider }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $payment->amount }} {{ $payment->currency ?? 'USD' }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                                @if ($payment->status === 'paid') bg-green-100 text-green-800
                                                @elseif ($payment->status === 'failed' || $payment->status === 'expired') bg-red-100 text-red-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ $payment->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            {{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-2">
                                            <form method="POST" action="{{ route('staff.payments.update-status', $payment) }}" class="inline-flex items-center gap-2">
                                                @csrf
                                                <select name="status" class="block w-28 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    @foreach ($statuses as $s)
                                                        <option value="{{ $s }}" {{ $payment->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">
                                                    {{ __('Update') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">
                                            {{ __('No payments found.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $payments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
