@props([
    'currentPage' => 1,
    'lastPage' => 1,
    'hasPages' => false,
    'id' => null,
])

@php
    $paginationId = $id ?? 'pagination-' . uniqid();
@endphp

<div
    x-data="paginationComponent({{ $currentPage }}, {{ $lastPage }}, {{ $hasPages ? 'true' : 'false' }}, '{{ $paginationId }}')"
    class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6"
>
    <nav class="flex items-center justify-between" x-show="hasPages">
        <div class="flex-1 flex justify-between sm:hidden">
            <button
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage === 1"
                :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white"
            >
                {{ __('Previous') }}
            </button>
            <button
                @click="goToPage(currentPage + 1)"
                :disabled="currentPage === lastPage"
                :class="currentPage === lastPage ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white"
            >
                {{ __('Next') }}
            </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    {{ __('Showing') }}
                    <span class="font-medium" x-text="currentPage"></span>
                    {{ __('of') }}
                    <span class="font-medium" x-text="lastPage"></span>
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500"
                    >
                        <span class="sr-only">{{ __('Previous') }}</span>
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                    <template x-for="page in getPageNumbers()" :key="page">
                        <span>
                            <span x-show="page === '...'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                            <button
                                x-show="page !== '...'"
                                @click="goToPage(page)"
                                :class="page === currentPage ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                x-text="page"
                            ></button>
                        </span>
                    </template>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === lastPage"
                        :class="currentPage === lastPage ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500"
                    >
                        <span class="sr-only">{{ __('Next') }}</span>
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </nav>
</div>

