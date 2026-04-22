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
    class="smm-pagination-wrap"
>
    <nav class="smm-pagination" x-show="hasPages" x-cloak>
        {{-- Previous --}}
        <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="smm-pg-btn smm-pg-prev"
            :class="currentPage === 1 ? 'smm-pg-disabled' : ''"
            aria-label="{{ __('Previous') }}"
        >
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>

        {{-- Page numbers --}}
        <div class="smm-pg-pages">
            <template x-for="(page, index) in getPageNumbers()" :key="`${page}-${index}`">
                <span>
                    <span x-show="page === '...'" class="smm-pg-dots">···</span>
                    <button
                        x-show="page !== '...'"
                        @click="goToPage(page)"
                        class="smm-pg-btn smm-pg-num"
                        :class="page === currentPage ? 'smm-pg-active' : ''"
                        x-text="page"
                    ></button>
                </span>
            </template>
        </div>

        {{-- Next --}}
        <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="smm-pg-btn smm-pg-next"
            :class="currentPage === lastPage ? 'smm-pg-disabled' : ''"
            aria-label="{{ __('Next') }}"
        >
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>

        {{-- Page info (mobile) --}}
        <div class="smm-pg-info">
            <span x-text="currentPage"></span> / <span x-text="lastPage"></span>
        </div>
    </nav>
</div>

<style>
    .smm-pagination-wrap {
        padding: 12px 16px;
        border-top: 1px solid var(--border, rgba(255,255,255,0.08));
        background: var(--card, #1a1a2e);
    }
    .smm-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .smm-pg-pages {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .smm-pg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border: 1px solid var(--border, rgba(255,255,255,0.08));
        border-radius: 8px;
        background: transparent;
        color: var(--text2, #8892a4);
        font-size: 13px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.15s;
        flex-shrink: 0;
    }
    .smm-pg-btn:hover:not(:disabled):not(.smm-pg-disabled) {
        background: rgba(108, 92, 231, 0.12);
        border-color: rgba(108, 92, 231, 0.3);
        color: var(--text, #e2e8f0);
    }
    .smm-pg-active {
        background: var(--purple, #6c5ce7) !important;
        border-color: var(--purple, #6c5ce7) !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(108, 92, 231, 0.4);
    }
    .smm-pg-disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
    .smm-pg-prev,
    .smm-pg-next {
        padding: 0;
        min-width: 36px;
    }
    .smm-pg-dots {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 36px;
        color: var(--text3, #5a6178);
        font-size: 14px;
        letter-spacing: 1px;
        user-select: none;
    }
    .smm-pg-info {
        display: none;
        font-size: 12px;
        color: var(--text3, #5a6178);
        white-space: nowrap;
        margin-left: 8px;
    }

    /* Light theme */
    [data-theme="light"] .smm-pagination-wrap {
        background: var(--card2, #f0f2f7);
    }
    [data-theme="light"] .smm-pg-btn {
        background: #fff;
        border-color: rgba(0, 0, 0, 0.1);
        color: var(--text2, #5a6178);
    }
    [data-theme="light"] .smm-pg-btn:hover:not(:disabled):not(.smm-pg-disabled) {
        background: rgba(108, 92, 231, 0.08);
        border-color: rgba(108, 92, 231, 0.25);
        color: var(--purple-dark, #5a4fcf);
    }

    /* Tablet */
    @media (max-width: 768px) {
        .smm-pagination-wrap { padding: 10px 12px; }
        .smm-pg-btn { min-width: 34px; height: 34px; font-size: 12px; border-radius: 8px; }
        .smm-pg-dots { min-width: 24px; height: 34px; font-size: 12px; }
    }

    /* Mobile */
    @media (max-width: 480px) {
        .smm-pagination-wrap { padding: 10px 8px; }
        .smm-pagination { gap: 3px; }
        .smm-pg-pages { gap: 3px; }
        .smm-pg-btn { min-width: 32px; height: 32px; font-size: 12px; padding: 0 6px; border-radius: 7px; }
        .smm-pg-prev,
        .smm-pg-next { min-width: 32px; }
        .smm-pg-prev svg,
        .smm-pg-next svg { width: 14px; height: 14px; }
        .smm-pg-dots { min-width: 20px; height: 32px; font-size: 11px; }
        .smm-pg-info { display: inline-flex; }
    }

    /* Very small: hide middle numbers, show only prev/info/next */
    @media (max-width: 360px) {
        .smm-pg-pages { display: none; }
        .smm-pg-info { display: inline-flex; font-size: 13px; font-weight: 600; color: var(--text2, #8892a4); margin: 0 8px; }
        .smm-pg-prev,
        .smm-pg-next { min-width: 40px; height: 36px; }
    }
</style>
