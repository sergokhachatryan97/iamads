@props([
    'name',
    'title' => 'Confirm Action',
    'message' => 'Are you sure you want to proceed?',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'confirmButtonClass' => 'bg-red-600 hover:bg-red-700',
    'show' => false,
])

<x-modal :name="$name" :show="$show" maxWidth="lg">
    <div class="p-6">
        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h3 class="text-lg font-medium text-gray-900 text-center mb-3 px-2">
            {{ $title }}
        </h3>

        <p class="text-sm text-gray-600 text-center mb-6 px-4 leading-relaxed w-full whitespace-normal break-words">
            {{ $message }}
        </p>

        <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
            <button
                type="button"
                @click="$dispatch('close-modal', '{{ $name }}')"
                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                {{ $cancelText }}
            </button>
            <button
                type="button"
                @click="$dispatch('confirm-action', '{{ $name }}')"
                class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 {{ $confirmButtonClass }}"
            >
                {{ $confirmText }}
            </button>
        </div>

        {{ $slot }}
    </div>
</x-modal>

