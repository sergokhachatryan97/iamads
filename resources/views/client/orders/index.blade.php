<x-client-layout :title="__('Orders')">
    <style>
        .client-orders-table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        [data-theme="light"] .client-orders-table-wrap {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .client-orders-table-wrap #client-orders-table {
            background: var(--card) !important;
        }
        .client-orders-table-wrap #client-orders-table table.min-w-full {
            border-color: var(--border);
        }
        .client-orders-table-wrap #client-orders-table thead {
            background: rgba(0, 0, 0, 0.32) !important;
        }
        [data-theme="light"] .client-orders-table-wrap #client-orders-table thead {
            background: var(--card2) !important;
        }
        .client-orders-table-wrap #client-orders-table tbody {
            background: var(--card) !important;
        }
        .client-orders-table-wrap #client-orders-table tbody > tr,
        .client-orders-table-wrap #client-orders-table thead > tr {
            border-color: var(--border) !important;
        }
        .client-orders-table-wrap #client-orders-table .divide-y > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--border) !important;
        }
        .client-orders-table-wrap .co-th {
            color: var(--text3) !important;
        }
        .client-orders-table-wrap .co-text {
            color: var(--text) !important;
        }
        .client-orders-table-wrap .co-text-muted {
            color: var(--text3) !important;
        }
        .client-orders-table-wrap .co-text-secondary {
            color: var(--text2) !important;
        }
        .client-orders-table-wrap .co-link {
            color: var(--purple-light) !important;
        }
        .client-orders-table-wrap .co-copy-btn {
            color: var(--text3) !important;
        }
        .client-orders-table-wrap .co-copy-btn:hover {
            color: var(--text) !important;
        }
        .client-orders-table-wrap .order-source-api {
            background: rgba(255, 255, 255, 0.08) !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] .client-orders-table-wrap .order-source-api {
            background: rgba(0, 0, 0, 0.06) !important;
            color: var(--text2) !important;
        }
        .client-orders-table-wrap .order-source-web {
            background: rgba(108, 92, 231, 0.2) !important;
            color: var(--purple-light) !important;
        }
        [data-theme="light"] .client-orders-table-wrap .order-source-web {
            background: rgba(108, 92, 231, 0.12) !important;
            color: var(--purple-dark) !important;
        }
        .client-orders-table-wrap .client-order-avatar-cutout {
            background: var(--card) !important;
        }
        .client-orders-table-wrap .client-order-avatar-inner {
            background: var(--card2) !important;
            border-color: var(--border) !important;
        }
        .client-orders-table-wrap .co-actions-btn-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            padding: 0;
            border-radius: 10px;
            border: 1px solid var(--border) !important;
            background: rgba(0, 0, 0, 0.2) !important;
            color: var(--text2) !important;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .client-orders-table-wrap .co-actions-btn-icon:hover {
            background: rgba(108, 92, 231, 0.12) !important;
            color: var(--purple-light) !important;
            border-color: rgba(108, 92, 231, 0.35) !important;
        }
        [data-theme="light"] .client-orders-table-wrap .co-actions-btn-icon {
            background: #fff !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] .client-orders-table-wrap .co-actions-btn-icon:hover {
            background: rgba(108, 92, 231, 0.08) !important;
        }
        .client-orders-table-wrap .co-th-actions {
            width: 52px;
            text-align: center;
        }
        .client-orders-table-wrap #client-orders-table > div.border-t.border-gray-200 {
            background: rgba(0, 0, 0, 0.22) !important;
            border-top-color: var(--border) !important;
        }
        [data-theme="light"] .client-orders-table-wrap #client-orders-table > div.border-t.border-gray-200 {
            background: var(--card2) !important;
        }
        .client-orders-table-wrap #client-orders-table .border-gray-300 {
            border-color: var(--border) !important;
        }
        .client-orders-table-wrap #client-orders-table .text-gray-700 {
            color: var(--text2) !important;
        }
        .client-orders-table-wrap #client-orders-table .text-gray-500 {
            color: var(--text3) !important;
        }
        .client-orders-table-wrap #client-orders-table .bg-white {
            background: rgba(0, 0, 0, 0.18) !important;
        }
        [data-theme="light"] .client-orders-table-wrap #client-orders-table .bg-white {
            background: #fff !important;
        }
        .client-orders-table-wrap #client-orders-table .hover\:bg-gray-50:hover:not(:disabled) {
            background: rgba(108, 92, 231, 0.1) !important;
        }
        [data-theme="light"] .client-orders-table-wrap #client-orders-table .hover\:bg-gray-50:hover:not(:disabled) {
            background: rgba(0, 0, 0, 0.04) !important;
        }
        .client-orders-table-wrap #client-orders-table .bg-indigo-50 {
            background: rgba(108, 92, 231, 0.15) !important;
        }
        .client-orders-table-wrap .hover\:bg-gray-100:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        [data-theme="light"] .client-orders-table-wrap .hover\:bg-gray-100:hover {
            background: rgba(0, 0, 0, 0.06) !important;
        }
        .client-orders-empty-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        [data-theme="light"] .client-orders-empty-card {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .client-orders-empty-card .co-empty-text {
            color: var(--text3);
        }
        .client-orders-empty-card .co-empty-outline {
            border-color: var(--border) !important;
            background: rgba(0, 0, 0, 0.15) !important;
            color: var(--text2) !important;
        }
        .client-orders-empty-card .co-empty-outline:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        [data-theme="light"] .client-orders-empty-card .co-empty-outline {
            background: #fff !important;
        }

        /* Orders filter bar (matches SMM client theme) */
        .client-orders-filter-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            padding: 1rem;
        }
        [data-theme="light"] .client-orders-filter-panel {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }
        .client-orders-filter-panel .co-filter-toggle-idle {
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text2) !important;
            border: 1px solid var(--border);
        }
        .client-orders-filter-panel .co-filter-toggle-idle:hover {
            background: rgba(108, 92, 231, 0.15) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-orders-filter-panel .co-filter-toggle-idle {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        .client-orders-filter-panel .co-filter-toggle-active {
            background: var(--purple) !important;
            color: #fff !important;
            border: 1px solid var(--purple);
        }
        .client-orders-filter-panel .co-filter-tab-idle {
            color: var(--text3) !important;
            border: 1px solid transparent;
        }
        .client-orders-filter-panel .co-filter-tab-idle:hover {
            color: var(--text) !important;
            background: rgba(108, 92, 231, 0.1) !important;
        }
        .client-orders-filter-panel .co-filter-tab-active {
            background: var(--purple) !important;
            color: #fff !important;
            border-color: var(--purple) !important;
        }
        .client-orders-filter-panel .co-filter-tab-badge-idle {
            background: rgba(0, 0, 0, 0.25) !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] .client-orders-filter-panel .co-filter-tab-badge-idle {
            background: rgba(0, 0, 0, 0.08) !important;
            color: var(--text2) !important;
        }
        .client-orders-filter-panel .co-filter-tab-badge-active {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #fff !important;
        }
        .client-orders-filter-panel .co-filter-search {
            border-color: var(--border) !important;
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text) !important;
        }
        .client-orders-filter-panel .co-filter-search::placeholder {
            color: var(--text3);
        }
        [data-theme="light"] .client-orders-filter-panel .co-filter-search {
            background: var(--card2) !important;
        }
        .client-orders-filter-panel .co-filter-search-icon {
            color: var(--text3) !important;
        }
        .client-orders-filter-panel .co-filter-search-clear {
            color: var(--text3) !important;
        }
        .client-orders-filter-panel .co-filter-search-clear:hover {
            background: rgba(108, 92, 231, 0.15) !important;
            color: var(--text) !important;
        }
        .client-orders-filter-panel .co-filter-advanced {
            border-top-color: var(--border) !important;
        }
        .client-orders-filter-panel .co-filter-label {
            color: var(--text3) !important;
        }
        .client-orders-filter-panel .co-filter-field {
            border-color: var(--border) !important;
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-orders-filter-panel .co-filter-field {
            background: var(--card2) !important;
        }
        .client-orders-filter-panel .co-filter-apply {
            background: var(--purple) !important;
            border: 1px solid var(--purple) !important;
            color: #fff !important;
            box-sizing: border-box;
        }
        .client-orders-filter-panel .co-filter-apply:hover {
            filter: brightness(1.06);
        }
        .client-orders-filter-panel .co-filter-reset {
            border: 1px solid var(--border) !important;
            background: rgba(0, 0, 0, 0.18) !important;
            color: var(--text2) !important;
            box-sizing: border-box;
        }
        .client-orders-filter-panel .co-filter-reset:hover {
            background: rgba(108, 92, 231, 0.12) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-orders-filter-panel .co-filter-reset {
            background: #fff !important;
        }

        /* Native controls ignore theme tokens in some browsers */
        [data-theme="dark"] .client-orders-filter-panel select.co-filter-field {
            color-scheme: dark;
        }
        [data-theme="dark"] .client-orders-filter-panel select.co-filter-field option {
            background-color: #1a1a2e;
            color: #ffffff;
        }
        [data-theme="dark"] .client-orders-filter-panel input[type="date"].co-filter-field {
            color-scheme: dark;
        }
        [data-theme="dark"] .client-orders-filter-panel input[type="date"].co-filter-field::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: 0.85;
        }
        [data-theme="dark"] .client-orders-filter-panel input[type="date"].co-filter-field::-webkit-datetime-edit-fields-wrapper,
        [data-theme="dark"] .client-orders-filter-panel input[type="date"].co-filter-field::-webkit-datetime-edit-text {
            color: #ffffff;
        }

        /* Row actions dropdown (SMM theme) */
        .client-orders-table-wrap .client-order-dropdown-panel {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.45);
            --tw-ring-shadow: 0 0 #0000 !important;
            --tw-ring-offset-shadow: 0 0 #0000 !important;
        }
        [data-theme="light"] .client-orders-table-wrap .client-order-dropdown-panel {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }
        .client-orders-table-wrap .co-order-dropdown-action {
            color: var(--text2) !important;
            background: transparent;
        }
        .client-orders-table-wrap .co-order-dropdown-action:hover,
        .client-orders-table-wrap .co-order-dropdown-action:focus {
            background: rgba(108, 92, 231, 0.14) !important;
            color: var(--text) !important;
        }
        .client-orders-table-wrap .co-order-dropdown-action-danger {
            color: #f87171 !important;
        }
        .client-orders-table-wrap .co-order-dropdown-action-danger:hover,
        .client-orders-table-wrap .co-order-dropdown-action-danger:focus {
            color: #fca5a5 !important;
        }
        .client-orders-table-wrap .co-order-dropdown-action-warn {
            color: #fb923c !important;
        }
        .client-orders-table-wrap .co-order-dropdown-action-warn:hover,
        .client-orders-table-wrap .co-order-dropdown-action-warn:focus {
            color: #fdba74 !important;
        }
        [data-theme="light"] .client-orders-table-wrap .co-order-dropdown-action-danger {
            color: #b91c1c !important;
        }
        [data-theme="light"] .client-orders-table-wrap .co-order-dropdown-action-warn {
            color: #c2410c !important;
        }

        /* Bulk actions menu (outside table wrap; same look as row dropdown) */
        .co-bulk-bar .co-bulk-dropdown-root {
            position: relative;
            z-index: 40;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.45);
        }
        [data-theme="light"] .co-bulk-wrap .co-bulk-dropdown-panel {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action {
            color: var(--text2) !important;
            background: transparent;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action:hover,
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action:focus {
            background: rgba(108, 92, 231, 0.14) !important;
            color: var(--text) !important;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-danger {
            color: #f87171 !important;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-danger:hover,
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-danger:focus {
            color: #fca5a5 !important;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-warn {
            color: #fb923c !important;
        }
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-warn:hover,
        .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-warn:focus {
            color: #fdba74 !important;
        }
        [data-theme="light"] .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-danger {
            color: #b91c1c !important;
        }
        [data-theme="light"] .co-bulk-wrap .co-bulk-dropdown-panel .co-order-dropdown-action-warn {
            color: #c2410c !important;
        }

        /* Modals: theme=smm (order details + confirm) */
        .client-smm-modal-backdrop {
            background: rgba(0, 0, 0, 0.72) !important;
        }
        [data-theme="light"] .client-smm-modal-backdrop {
            background: rgba(15, 23, 42, 0.45) !important;
        }
        .client-smm-modal-panel {
            background: var(--card) !important;
            color: var(--text);
        }
        #client-order-modals-root .client-smm-modal-panel .text-gray-900,
        .client-orders-table-wrap .client-smm-modal-panel .text-gray-900 {
            color: var(--text) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .text-gray-500,
        .client-orders-table-wrap .client-smm-modal-panel .text-gray-500 {
            color: var(--text3) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .text-gray-600,
        .client-orders-table-wrap .client-smm-modal-panel .text-gray-600 {
            color: var(--text2) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .text-gray-400,
        .client-orders-table-wrap .client-smm-modal-panel .text-gray-400 {
            color: var(--text3) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .hover\:text-gray-500:hover,
        .client-orders-table-wrap .client-smm-modal-panel .hover\:text-gray-500:hover {
            color: var(--text2) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .bg-gray-50,
        .client-orders-table-wrap .client-smm-modal-panel .bg-gray-50 {
            background: rgba(0, 0, 0, 0.22) !important;
        }
        [data-theme="light"] #client-order-modals-root .client-smm-modal-panel .bg-gray-50,
        [data-theme="light"] .client-orders-table-wrap .client-smm-modal-panel .bg-gray-50 {
            background: var(--card2) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .text-indigo-600,
        .client-orders-table-wrap .client-smm-modal-panel .text-indigo-600 {
            color: var(--purple-light) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .border-gray-300,
        .client-orders-table-wrap .client-smm-modal-panel .border-gray-300 {
            border-color: var(--border) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .bg-white,
        .client-orders-table-wrap .client-smm-modal-panel .bg-white {
            background: rgba(0, 0, 0, 0.2) !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] #client-order-modals-root .client-smm-modal-panel .bg-white,
        [data-theme="light"] .client-orders-table-wrap .client-smm-modal-panel .bg-white {
            background: var(--card2) !important;
        }
        #client-order-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover,
        .client-orders-table-wrap .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        [data-theme="light"] #client-order-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover,
        [data-theme="light"] .client-orders-table-wrap .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: rgba(0, 0, 0, 0.06) !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .bg-red-100,
        #client-order-modals-root .client-smm-modal-panel .bg-red-100 {
            background: rgba(239, 68, 68, 0.18) !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .text-red-600,
        #client-order-modals-root .client-smm-modal-panel .text-red-600 {
            color: #f87171 !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .text-red-700,
        #client-order-modals-root .client-smm-modal-panel .text-red-700 {
            color: #fca5a5 !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .text-gray-700,
        #client-order-modals-root .client-smm-modal-panel .text-gray-700 {
            color: var(--text2) !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .text-red-800,
        #client-order-modals-root .client-smm-modal-panel .text-red-800 {
            color: #fecaca !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .bg-red-50,
        #client-order-modals-root .client-smm-modal-panel .bg-red-50 {
            background: rgba(239, 68, 68, 0.12) !important;
        }
        .client-orders-table-wrap .client-smm-modal-panel .border-red-200,
        #client-order-modals-root .client-smm-modal-panel .border-red-200 {
            border-color: rgba(239, 68, 68, 0.35) !important;
        }
        #client-order-modals-root .client-smm-modal-panel svg circle[stroke="#e5e7eb"],
        .client-orders-table-wrap .client-smm-modal-panel svg circle[stroke="#e5e7eb"] {
            stroke: var(--border) !important;
        }
        #client-order-modals-root .client-smm-modal-panel svg circle[stroke="#4f46e5"],
        .client-orders-table-wrap .client-smm-modal-panel svg circle[stroke="#4f46e5"] {
            stroke: var(--purple) !important;
        }

        .fo-cred-overlay {
            position: fixed;
            inset: 0;
            z-index: 10050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.72);
        }
        .fo-cred-panel {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
        }
        .fo-cred-panel h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        .fo-cred-panel p.intro {
            margin: 0 0 16px;
            font-size: 13px;
            color: var(--text2);
            line-height: 1.45;
        }
        .fo-cred-row {
            margin-bottom: 12px;
        }
        .fo-cred-row label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text3);
            margin-bottom: 4px;
        }
        .fo-cred-field {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .fo-cred-field input {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.2);
            color: var(--text);
            font-size: 13px;
            font-family: ui-monospace, monospace;
        }
        [data-theme="light"] .fo-cred-field input {
            background: #fff;
        }
        .fo-cred-copy {
            flex-shrink: 0;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(108, 92, 231, 0.15);
            color: var(--purple-light);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .fo-cred-copy:hover {
            background: rgba(108, 92, 231, 0.25);
        }
        .fo-cred-dismiss {
            margin-top: 18px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 9999px;
            border: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            background: linear-gradient(135deg, var(--purple), var(--teal));
            color: #fff;
        }
        .fo-cred-dismiss:hover {
            filter: brightness(1.06);
        }
        .co-bulk-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            background: var(--card2);
            overflow: visible;
        }
        .co-bulk-select-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        .co-bulk-select-row button {
            color: var(--purple-light);
            font-weight: 600;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-family: inherit;
        }
        .co-bulk-select-row button:hover {
            color: var(--teal);
        }
        .co-bulk-select-row .co-bulk-sep {
            color: var(--text3);
            user-select: none;
        }
    </style>
    <div class="py-12">
        <div class="m-auto sm:px-6 lg:px-8">
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 3000)"
                 x-show="show"
                 x-transition.opacity.duration.300ms>
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('bulk_result'))
                    @php $result = session('bulk_result'); @endphp
                    <div class="co-bulk-result mb-4 rounded-xl border px-4 py-3" style="border-color: var(--border); background: rgba(108,92,231,0.08); color: var(--text2);" role="status">
                        <div class="font-semibold mb-2" style="color: var(--text);">{{ __('Bulk Action Results') }}</div>
                        <div class="text-sm space-y-1">
                            <p><strong>{{ __('Total Matched') }}:</strong> {{ $result['total_matched'] ?? 0 }}</p>
                            <p><strong>{{ __('Processed') }}:</strong> {{ $result['processed_count'] ?? 0 }}</p>
                            <p><strong>{{ __('Succeeded') }}:</strong> {{ $result['succeeded_count'] ?? 0 }}</p>
                            @if(($result['failed_count'] ?? 0) > 0)
                                <p style="color: #ff7675;"><strong>{{ __('Failed') }}:</strong> {{ $result['failed_count'] }}</p>
                                @if(!empty($result['failures']))
                                    <details class="mt-2">
                                        <summary class="cursor-pointer font-semibold" style="color: var(--purple-light);">{{ __('View Failures') }}</summary>
                                        <ul class="list-disc list-inside mt-2 ml-4">
                                            @foreach(array_slice($result['failures'], 0, 10) as $failure)
                                                <li>#{{ $failure['order_id'] }}: {{ $failure['reason'] }}</li>
                                            @endforeach
                                            @if(count($result['failures']) > 10)
                                                <li class="co-text-muted">... {{ __('and :count more', ['count' => count($result['failures']) - 10]) }}</li>
                                            @endif
                                        </ul>
                                    </details>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @php
                $fastOrderCreds = session('fast_order_credentials');
            @endphp
            @if(is_array($fastOrderCreds))
                <div
                    class="fo-cred-overlay"
                    x-data="{
                        open: true,
                        cred: @js($fastOrderCreds),
                        copied: null,
                        copyField(field) {
                            const v = this.cred[field] || '';
                            if (!v) return;
                            navigator.clipboard.writeText(v).then(() => {
                                this.copied = field;
                                setTimeout(() => { this.copied = null; }, 2000);
                            });
                        }
                    }"
                    x-show="open"
                    x-cloak
                    @keydown.escape.window="open = false"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="fo-cred-title"
                >
                    <div class="fo-cred-panel" @click.outside="open = false">
                        <h2 id="fo-cred-title">{{ __('common.fast_order_account_title') }}</h2>
                        <p class="intro">{{ __('common.fast_order_account_intro') }}</p>
                        @if(!empty($fastOrderCreds['name']))
                            <div class="fo-cred-row">
                                <label>{{ __('common.fast_order_username') }}</label>
                                <div class="fo-cred-field">
                                    <input type="text" readonly value="{{ $fastOrderCreds['name'] }}">
                                    <button type="button" class="fo-cred-copy" @click="copyField('name')">
                                        <span x-show="copied !== 'name'">{{ __('common.fast_order_copy') }}</span>
                                        <span x-show="copied === 'name'" x-cloak>{{ __('common.fast_order_copied') }}</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                        <div class="fo-cred-row">
                            <label>{{ __('common.fast_order_email') }}</label>
                            <div class="fo-cred-field">
                                <input type="text" readonly value="{{ $fastOrderCreds['email'] ?? '' }}">
                                <button type="button" class="fo-cred-copy" @click="copyField('email')">
                                    <span x-show="copied !== 'email'">{{ __('common.fast_order_copy') }}</span>
                                    <span x-show="copied === 'email'" x-cloak>{{ __('common.fast_order_copied') }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="fo-cred-row">
                            <label>{{ __('common.fast_order_password') }}</label>
                            <div class="fo-cred-field">
                                <input type="text" readonly value="{{ $fastOrderCreds['password'] ?? '' }}" autocomplete="off">
                                <button type="button" class="fo-cred-copy" @click="copyField('password')">
                                    <span x-show="copied !== 'password'">{{ __('common.fast_order_copy') }}</span>
                                    <span x-show="copied === 'password'" x-cloak>{{ __('common.fast_order_copied') }}</span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="fo-cred-dismiss" @click="open = false">
                            {{ __('common.fast_order_dismiss') }}
                        </button>
                    </div>
                </div>
            @endif

            <!-- Filter Bar -->
            <div class="client-orders-filter-panel"
                 x-data="clientOrdersFilters({
                     filtersOpen: {{ ($filterCategoryId || $filterServiceId || $filterDateFrom || $filterDateTo || ($filterSearch ?? null)) ? 'true' : 'false' }},
                     searchValue: @js($filterSearch ?? ''),
                     currentStatus: @js($currentStatus ?? 'all'),
                     indexUrl: '{{ route('client.orders.index') }}',
                 })"
                 @fetch-client-orders.window="fetchOrdersByUrl($event.detail.url)"
                 @submit.capture.prevent="fetchOrdersFromForm($event.target)">
                {{-- Row 1: Burger + Status Tabs + Search --}}
                <form method="GET" action="{{ route('client.orders.index') }}" class="client-orders-filter-form flex flex-wrap items-center gap-3">
                    @if(request()->has('status') && request('status') !== 'all')
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    @if(request()->has('source') && in_array(request('source'), ['web', 'api']))
                        <input type="hidden" name="source" value="{{ request('source') }}">
                    @endif
                    {{-- Burger / Filter toggle --}}
                    <button type="button"
                            @click="filtersOpen = !filtersOpen"
                            :class="filtersOpen ? 'co-filter-toggle-active' : 'co-filter-toggle-idle'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]"
                            title="{{ __('Filters') }}"
                            :aria-expanded="filtersOpen">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </button>
                        {{-- Search bar with clear button --}}
                        <div class="ml-auto flex-1 min-w-[200px] max-w-md">
                            <div class="relative">
                                <svg class="co-filter-search-icon absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" name="search" x-model="searchValue"
                                       @input.debounce.400ms="fetchOrdersFromForm($event.target.closest('form'))"
                                       placeholder="{{ __('URL or order id') }}"
                                       class="co-filter-search w-full rounded-full border py-2 pl-10 pr-10 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                                <button type="button" x-show="searchValue"
                                        @click="searchValue = ''; fetchOrdersFromForm($event.target.closest('form'))"
                                        x-cloak
                                        class="co-filter-search-clear absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    {{-- Status Tabs (synced on AJAX filter so counts match category/service/date/search) --}}
                    <div id="client-orders-status-tabs-root" class="flex flex-wrap items-center gap-1.5">
                        @php
                            $statusButtons = [
                                'all' => __('All'),
                                \App\Models\Order::STATUS_VALIDATING => __('Validating'),
                                \App\Models\Order::STATUS_AWAITING => __('Awaiting'),
                                \App\Models\Order::STATUS_IN_PROGRESS => __('In Progress'),
                                \App\Models\Order::STATUS_PARTIAL => __('Partial'),
                                \App\Models\Order::STATUS_COMPLETED => __('Completed'),
                                \App\Models\Order::STATUS_CANCELED => __('Canceled'),
                                \App\Models\Order::STATUS_INVALID_LINK => __('Invalid Link'),
                                \App\Models\Order::STATUS_FAIL => __('Failed'),
                            ];
                        @endphp
                        @foreach($statusButtons as $statusValue => $statusLabel)
                            @php
                                $urlParams = request()->except(['status', 'page']);
                                if ($statusValue !== 'all') $urlParams['status'] = $statusValue;
                                $url = route('client.orders.index', $urlParams);
                                $count = $statusValue === 'all' ? array_sum($statusCounts ?? []) : ($statusCounts[$statusValue] ?? 0);
                            @endphp
                            <a href="{{ $url }}"
                               class="orders-ajax-link inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                               :class="currentStatus === @js($statusValue) ? 'co-filter-tab-active' : 'co-filter-tab-idle'"
                               @click.prevent="fetchOrdersByUrl('{{ $url }}')">
                                {{ $statusLabel }}
                                @if($count > 0)
                                    <span class="rounded-full px-1.5 py-0.5 text-xs font-bold"
                                          :class="currentStatus === @js($statusValue) ? 'co-filter-tab-badge-active' : 'co-filter-tab-badge-idle'">{{ number_format($count) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </form>

                {{-- Row 2: Advanced filters (expandable) --}}
                <div x-show="filtersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak
                     class="co-filter-advanced mt-4 border-t pt-4">
                    <div id="client-orders-advanced-filters-root">
                    <form method="GET" action="{{ route('client.orders.index') }}" class="client-orders-filter-form co-filter-advanced-form flex w-full min-w-0 flex-wrap items-end gap-x-4 gap-y-3">
                        @if(request()->has('status') && request('status') !== 'all')
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        @if(request()->has('source') && in_array(request('source'), ['web', 'api']))
                            <input type="hidden" name="source" value="{{ request('source') }}">
                        @endif
                        <div class="min-w-0 w-full shrink-0 basis-full sm:basis-[11rem] sm:w-auto sm:flex-1 sm:min-w-[10rem]">
                            <label for="filter-category" class="co-filter-label mb-1 block text-xs font-medium">{{ __('Category') }}</label>
                            <select name="category_id" id="filter-category" class="co-filter-field w-full max-w-full rounded-full border px-4 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                                <option value="">{{ __('All') }}</option>
                                @foreach($categories ?? [] as $cat)
                                    <option value="{{ $cat->id }}" {{ ($filterCategoryId ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0 w-full shrink-0 basis-full sm:basis-[14rem] sm:w-auto sm:flex-[1.35] sm:min-w-[12rem]">
                            <label for="filter-service" class="co-filter-label mb-1 block text-xs font-medium">{{ __('Service') }}</label>
                            <select name="service_id" id="filter-service" class="co-filter-field w-full max-w-full rounded-full border px-4 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                                <option value="">{{ __('All') }}</option>
                                @foreach($services ?? [] as $svc)
                                    <option value="{{ $svc->id }}" {{ ($filterServiceId ?? '') == $svc->id ? 'selected' : '' }}>ID{{ $svc->id }} - {{ Str::limit($svc->name, 30) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0 w-full shrink-0 basis-full sm:w-auto sm:max-w-full">
                            <label for="filter-date-from" class="co-filter-label mb-1 block text-xs font-medium">{{ __('Date') }}</label>
                            <div class="flex min-w-0 flex-wrap gap-2">
                                <input type="date" name="date_from" id="filter-date-from" value="{{ $filterDateFrom ?? '' }}"
                                       placeholder="{{ __('From') }}"
                                       class="co-filter-field min-w-0 flex-1 rounded-full border px-3 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40 sm:min-w-[9.5rem] sm:flex-none">
                                <input type="date" name="date_to" id="filter-date-to" value="{{ $filterDateTo ?? '' }}"
                                       placeholder="{{ __('To') }}"
                                       class="co-filter-field min-w-0 flex-1 rounded-full border px-3 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40 sm:min-w-[9.5rem] sm:flex-none">
                            </div>
                        </div>
                        <div class="flex w-full min-w-0 shrink-0 items-center justify-end gap-2 sm:ml-auto sm:w-auto sm:justify-start">
                            <button type="submit" class="co-filter-apply inline-flex h-10 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]">
                                {{ __('Apply') }}
                            </button>
                            <a href="{{ route('client.orders.index', array_filter(['status' => request('status') !== 'all' ? request('status') : null, 'source' => in_array(request('source'), ['web', 'api']) ? request('source') : null])) }}"
                               class="co-filter-reset inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-full px-5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                {{ __('Reset filter') }}
                            </a>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <div id="client-orders-container">
            @if($orders->count() > 0)
                <div
                    class="co-bulk-wrap"
                    x-data="clientBulkOrderSelection()"
                >
                    <div class="co-bulk-bar" x-show="hasSelection()" x-cloak>
                        <div class="text-sm" style="color: var(--text2);">
                            <span x-text="getSelectionText()"></span>
                        </div>
                        <div class="co-bulk-dropdown-root flex flex-wrap items-center gap-2" @click.outside="bulkMenuOpen = false">
                            <button
                                type="button"
                                class="co-filter-apply inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold"
                                @click="bulkMenuOpen = !bulkMenuOpen"
                                :aria-expanded="bulkMenuOpen"
                                aria-haspopup="true"
                            >
                                {{ __('Bulk Actions') }}
                                <i class="fa-solid fa-chevron-down ms-2 text-xs opacity-80 transition-transform duration-150" :class="bulkMenuOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
                            </button>
                            <div
                                x-show="bulkMenuOpen"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="co-bulk-dropdown-panel absolute end-0 top-full mt-2 w-56 rounded-xl py-1 z-50"
                                style="display: none;"
                                role="menu"
                            >
                                <button
                                    type="button"
                                    role="menuitem"
                                    @click="openConfirmModal('cancel_full')"
                                    class="co-order-dropdown-action co-order-dropdown-action-danger block w-full px-4 py-2 text-start text-sm"
                                >
                                    {{ __('Cancel (Full Refund)') }}
                                </button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    @click="openConfirmModal('cancel_partial')"
                                    class="co-order-dropdown-action co-order-dropdown-action-warn block w-full px-4 py-2 text-start text-sm"
                                >
                                    {{ __('Cancel (Partial Refund)') }}
                                </button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    @click="clearSelection()"
                                    class="co-order-dropdown-action block w-full px-4 py-2 text-start text-sm"
                                >
                                    {{ __('Clear Selection') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="co-bulk-select-row mb-3">
                        <button type="button" @click="selectPage()">{{ __('Select page') }}</button>
                        <span class="co-bulk-sep">|</span>
                        <button type="button" @click="selectAllMatching()">{{ __('Select all matching filters') }}</button>
                    </div>

                    <div
                        id="client-orders-live-region"
                        x-data="{ sortBy: @js($sortBy), sortDir: @js($sortDir) }"
                    >
                        @include('client.orders.orders-table-inner', ['orders' => $orders])
                    </div>

                    <form id="client-bulk-action-form" action="{{ route('client.orders.bulk-action') }}" method="POST" class="hidden">
                        @csrf
                        <input type="hidden" name="action" id="client-bulk-action-type">
                        <input type="hidden" name="select_all" id="client-bulk-select-all">
                        <input type="hidden" name="selected_ids" id="client-bulk-selected-ids">
                        <input type="hidden" name="excluded_ids" id="client-bulk-excluded-ids">
                        <input type="hidden" name="filters" id="client-bulk-filters">
                    </form>

                    <x-modal name="bulk-action-confirm" maxWidth="lg" theme="smm">
                        <div class="p-6" style="color: var(--text);">
                            <h3 class="text-lg font-semibold mb-4" x-text="confirmModalTitle"></h3>
                            <p class="text-sm mb-6" style="color: var(--text2);" x-text="confirmModalMessage"></p>
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'bulk-action-confirm' }))"
                                    class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
                                    style="border-color: var(--border); color: var(--text2);"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="button"
                                    @click="submitBulkAction()"
                                    class="px-4 py-2 text-sm font-semibold rounded-lg text-white"
                                    style="background: linear-gradient(135deg, var(--purple), var(--teal));"
                                >
                                    {{ __('Confirm') }}
                                </button>
                            </div>
                        </div>
                    </x-modal>
                </div>
            @else
                <div class="client-orders-empty-card overflow-hidden sm:rounded-lg">
                    <div class="p-6 text-center">
                        <p class="co-empty-text">
                            @if($currentStatus !== 'all' || $filterCategoryId || $filterServiceId || $filterDateFrom || $filterDateTo || ($filterSearch ?? null))
                                {{ __('No orders found matching your filters.') }}
                            @else
                                {{ __('No orders found.') }}
                            @endif
                        </p>
                        @if($currentStatus !== 'all')
                            <a href="{{ route('client.orders.index') }}"
                               class="co-empty-outline mt-4 inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                {{ __('Clear Filter') }}
                            </a>
                        @else
                            <a href="{{ route('client.orders.create') }}"
                               class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Create Your First Order') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
            </div>

            <div id="client-order-modals-root">
                @if($orders->count() > 0)
                @foreach($orders as $order)
                <x-modal name="client-order-details-{{ $order->id }}" maxWidth="2xl" theme="smm">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Order Details') }} #{{ $order->id }}</h3>
                            <button
                                type="button"
                                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
                                class="text-gray-400 hover:text-gray-500"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date') }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('M d, Y H:i') }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Status') }}</div>
                                    @php
                                        $detailStatusColors = [
                                            'validating' => 'bg-cyan-100 text-cyan-800',
                                            'awaiting' => 'bg-yellow-100 text-yellow-800',
                                            'pending' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-indigo-100 text-indigo-800',
                                            'processing' => 'bg-purple-100 text-purple-800',
                                            'partial' => 'bg-orange-100 text-orange-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'canceled' => 'bg-red-100 text-red-800',
                                            'fail' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $detailStatusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Service') }}</div>
                                <div class="text-sm font-semibold text-gray-900">{{ $order->service->name ?? 'N/A' }}</div>
                            </div>

                            @if($order->link || $order->link_2)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Link') }}</div>
                                <div class="space-y-2 text-sm font-mono break-all">
                                    @if($order->link)
                                        @php
                                            $telegramUrl = $order->link;
                                            if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link, $matches)) {
                                                $username = explode('?', explode('/', $matches[3])[0])[0];
                                                $telegramUrl = 'tg://resolve?domain=' . $username;
                                            } elseif (preg_match('/^@([A-Za-z0-9_]{3,32})$/i', $order->link, $matches)) {
                                                $telegramUrl = 'tg://resolve?domain=' . $matches[1];
                                            }
                                        @endphp
                                        @if($order->link_2)<div class="text-xs text-gray-500">{{ __('Target') }}</div>@endif
                                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ Str::limit($order->link, 30) }}</a>
                                    @endif
                                    @if($order->link_2)
                                        @php
                                            $telegramUrl2 = $order->link_2;
                                            if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link_2, $matches)) {
                                                $username = explode('?', explode('/', $matches[3])[0])[0];
                                                $telegramUrl2 = 'tg://resolve?domain=' . $username;
                                            } elseif (preg_match('/^@([A-Za-z0-9_]{3,32})$/i', $order->link_2, $matches)) {
                                                $telegramUrl2 = 'tg://resolve?domain=' . $matches[1];
                                            }
                                        @endphp
                                        <div class="text-xs text-gray-500">{{ __('Source') }}</div>
                                        <a href="{{ $telegramUrl2 }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ Str::limit($order->link_2, 30) }}</a>
                                    @endif
                                </div>
                            </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Quantity') }}</div>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Ordered') }}:</span>
                                            <span class="font-medium">{{ number_format($order->quantity) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Delivered') }}:</span>
                                            <span class="font-medium">{{ number_format($order->delivered ?? 0) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Remaining') }}:</span>
                                            <span class="font-medium">{{ number_format($order->remains) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Progress') }}</div>
                                    @php
                                        $dDelivered = $order->delivered ?? 0;
                                        $dQuantity = $order->quantity ?? 1;
                                        $dProgress = $dQuantity > 0 ? round(($dDelivered / $dQuantity) * 100, 1) : 0;
                                        $dp = min(100, max(0, $dProgress));
                                        $dr = 14;
                                        $dcirc = 2 * pi() * $dr;
                                        $ddashOffset = $dcirc * (1 - ($dp / 100));
                                    @endphp
                                    <div class="flex flex-col items-center">
                                        <div class="relative h-11 w-11 shrink-0">
                                            <svg class="h-10 w-10 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                                <circle cx="18" cy="18" r="{{ $dr }}" fill="none" stroke="#e5e7eb" stroke-width="4"></circle>
                                                <circle
                                                    cx="18" cy="18" r="{{ $dr }}"
                                                    fill="none"
                                                    stroke="#4f46e5"
                                                    stroke-width="4"
                                                    stroke-linecap="round"
                                                    stroke-dasharray="{{ number_format($dcirc, 3, '.', '') }}"
                                                    stroke-dashoffset="{{ number_format($ddashOffset, 3, '.', '') }}"
                                                ></circle>
                                            </svg>
                                            <div class="absolute inset-0 grid place-items-center">
                                                <span class="text-[11px] font-semibold text-gray-900">{{ number_format($dProgress, 0) }}%</span>
                                            </div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600 whitespace-nowrap">
                                            {{ number_format($order->remains) }} / {{ number_format($dQuantity) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Charge') }}</div>
                                <div class="text-sm font-semibold text-gray-900">${{ $order->charge }}</div>
                            </div>

                            @if($order->provider_last_error)
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    <span class="text-xs font-semibold text-red-700 uppercase">{{ __('Error') }}</span>
                                </div>
                                <p class="text-sm text-red-800 break-all">{{ $order->provider_last_error }}</p>
                            </div>
                            @endif
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                </x-modal>
                @endforeach
                @endif
            </div>
        </div>
    </div>

    <script>
        (function() {
            document.addEventListener('click', function(e) {
                const container = document.getElementById('client-orders-container');
                if (!container || !container.contains(e.target)) return;
                const link = e.target.closest('a');
                if (!link || link.classList.contains('orders-ajax-link')) return;
                const href = link.getAttribute('href') || link.href;
                if (href && (href.includes('orders') || href.includes(window.location.pathname))) {
                    e.preventDefault();
                    const url = href.startsWith('http') ? href : (window.location.origin + (href.startsWith('/') ? '' : '/') + href);
                    window.dispatchEvent(new CustomEvent('fetch-client-orders', { detail: { url } }));
                }
            });
        })();

        function clientBulkOrderSelection() {
            return {
                bulkMenuOpen: false,
                selectedIds: new Set(),
                excludedIds: new Set(),
                selectAll: false,
                orderIds: @json($ordersIds ?? []),
                pendingAction: null,
                confirmModalTitle: '',
                confirmModalMessage: '',
                sortBy: @json($sortBy),
                sortDir: @json($sortDir),
                actionLabels: {
                    cancel_full: @json(__('Cancel (Full Refund)')),
                    cancel_partial: @json(__('Cancel (Partial Refund)')),
                },
                getCurrentPageIds() {
                    return Array.from(document.querySelectorAll('#client-orders-table input[name="order_ids[]"]'))
                        .map((cb) => String(cb.value));
                },
                syncPageCheckboxes() {
                    const checkboxes = Array.from(document.querySelectorAll('#client-orders-table input[name="order_ids[]"]'));
                    checkboxes.forEach((cb) => {
                        cb.checked = this.isOrderSelected(cb.value);
                    });
                },
                toggleOrder(orderId, checked) {
                    const id = String(orderId);
                    if (this.selectAll) {
                        checked ? this.excludedIds.delete(id) : this.excludedIds.add(id);
                    } else {
                        checked ? this.selectedIds.add(id) : this.selectedIds.delete(id);
                    }
                },
                isOrderSelected(orderId) {
                    const id = String(orderId);
                    if (this.selectAll) {
                        return !this.excludedIds.has(id);
                    }
                    return this.selectedIds.has(id);
                },
                toggleSelectAll() {
                    this.selectAllMatching();
                },
                isAllSelected() {
                    const pageIds = this.getCurrentPageIds();
                    if (pageIds.length === 0) {
                        return false;
                    }
                    return pageIds.every((id) => this.isOrderSelected(id));
                },
                selectPage() {
                    this.selectAll = false;
                    this.excludedIds.clear();
                    this.getCurrentPageIds().forEach((id) => this.selectedIds.add(id));
                    this.syncPageCheckboxes();
                },
                selectAllMatching() {
                    this.selectAll = true;
                    this.selectedIds.clear();
                    this.excludedIds.clear();
                    (this.orderIds || []).forEach((id) => this.selectedIds.add(String(id)));
                },
                clearSelection() {
                    this.bulkMenuOpen = false;
                    this.selectedIds.clear();
                    this.excludedIds.clear();
                    this.selectAll = false;
                    this.syncPageCheckboxes();
                },
                getCurrentFilters() {
                    const urlParams = new URLSearchParams(window.location.search);
                    return {
                        status: urlParams.get('status') || 'all',
                        search: urlParams.get('search') || '',
                        service_id: urlParams.get('service_id') || '',
                        category_id: urlParams.get('category_id') || '',
                        date_from: urlParams.get('date_from') || '',
                        date_to: urlParams.get('date_to') || '',
                        source: urlParams.get('source') || '',
                    };
                },
                hasSelection() {
                    return this.selectAll || this.selectedIds.size > 0;
                },
                getSelectionText() {
                    if (this.selectAll) {
                        const excluded = this.excludedIds.size;
                        return excluded > 0
                            ? @json(__('All matching orders selected')) + ' (' + excluded + ' ' + @json(__('excluded')) + ')'
                            : @json(__('All matching orders selected'));
                    }
                    const n = this.selectedIds.size;
                    if (n === 1) {
                        return @json(__('1 order selected'));
                    }
                    return n + ' ' + @json(__('orders selected'));
                },
                openConfirmModal(action) {
                    this.bulkMenuOpen = false;
                    this.pendingAction = action;
                    const label = this.actionLabels[action] || action;
                    this.confirmModalTitle = label;
                    const lower = String(label).toLowerCase();
                    this.confirmModalMessage = @json(__('Are you sure you want to')) + ' ' + lower + ' — ' + this.getSelectionText() + '?';
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'bulk-action-confirm' }));
                },
                submitBulkAction() {
                    if (!this.pendingAction) {
                        return;
                    }
                    const form = document.getElementById('client-bulk-action-form');
                    form.querySelector('#client-bulk-action-type').value = this.pendingAction;
                    form.querySelector('#client-bulk-select-all').value = this.selectAll ? '1' : '0';
                    const selectedIdsArray = Array.from(this.selectedIds).map((id) => parseInt(id, 10));
                    const excludedIdsArray = Array.from(this.excludedIds).map((id) => parseInt(id, 10));
                    form.querySelector('#client-bulk-selected-ids').value = JSON.stringify(selectedIdsArray);
                    form.querySelector('#client-bulk-excluded-ids').value = JSON.stringify(excludedIdsArray);
                    form.querySelector('#client-bulk-filters').value = JSON.stringify(this.getCurrentFilters());
                    form.submit();
                },
            };
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('clientOrdersFilters', (config) => ({
                filtersOpen: config.filtersOpen,
                searchValue: config.searchValue,
                currentStatus: config.currentStatus ?? 'all',
                indexUrl: config.indexUrl,

                fetchOrdersFromForm(form) {
                    const params = new URLSearchParams();
                    const forms = document.querySelectorAll('.client-orders-filter-form');
                    forms.forEach(f => {
                        new FormData(f).forEach((val, key) => {
                            if (key !== 'search' && val) params.set(key, val);
                        });
                    });
                    if (this.searchValue) params.set('search', this.searchValue);
                    const url = this.indexUrl + (params.toString() ? '?' + params.toString() : '');
                    this.fetchOrdersByUrl(url);
                },

                async fetchOrdersByUrl(url) {
                    const status = new URL(url, window.location.origin).searchParams.get('status');
                    this.currentStatus = status || 'all';
                    const container = document.getElementById('client-orders-container');
                    const modalsRoot = document.getElementById('client-order-modals-root');
                    if (!container) return;
                    container.style.opacity = '0.6';
                    container.style.pointerEvents = 'none';
                    if (modalsRoot) {
                        modalsRoot.style.opacity = '0.6';
                        modalsRoot.style.pointerEvents = 'none';
                    }
                    try {
                        const resp = await fetch(url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                        });
                        const html = await resp.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('client-orders-container');
                        const newModals = doc.getElementById('client-order-modals-root');
                        if (newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                            if (modalsRoot && newModals) {
                                modalsRoot.innerHTML = newModals.innerHTML;
                            }
                            const newStatusRoot = doc.getElementById('client-orders-status-tabs-root');
                            const curStatusRoot = document.getElementById('client-orders-status-tabs-root');
                            if (newStatusRoot && curStatusRoot) {
                                curStatusRoot.innerHTML = newStatusRoot.innerHTML;
                                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                                    window.Alpine.initTree(curStatusRoot);
                                }
                            }
                            const newAdvRoot = doc.getElementById('client-orders-advanced-filters-root');
                            const curAdvRoot = document.getElementById('client-orders-advanced-filters-root');
                            if (newAdvRoot && curAdvRoot) {
                                curAdvRoot.innerHTML = newAdvRoot.innerHTML;
                            }
                            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                                container.querySelectorAll('[x-data]').forEach((el) => Alpine.initTree(el));
                                if (modalsRoot) {
                                    modalsRoot.querySelectorAll('[x-data]').forEach((el) => Alpine.initTree(el));
                                }
                            }
                            history.pushState({}, '', url);
                        }
                    } catch (e) {
                        console.error(e);
                        window.location.href = url;
                    } finally {
                        container.style.opacity = '';
                        container.style.pointerEvents = '';
                        if (modalsRoot) {
                            modalsRoot.style.opacity = '';
                            modalsRoot.style.pointerEvents = '';
                        }
                    }
                }
            }));
        });

        window.addEventListener('pagination-change', (e) => {
            if (e.detail.componentId === 'client-orders-pagination' && typeof window.fetchClientOrdersPage === 'function') {
                window.fetchClientOrdersPage(e.detail.page);
            }
        });

        window.fetchClientOrdersPage = function fetchClientOrdersPage(page, sortByParam = null, sortDirParam = null, customUrl = null) {
            const live = document.getElementById('client-orders-live-region');
            const alpineData = live?.__x?.$data;
            const url = customUrl ? new URL(customUrl, window.location.origin) : new URL(window.location.href);
            url.searchParams.set('page', String(page));

            let sortBy = sortByParam ?? alpineData?.sortBy ?? url.searchParams.get('sort_by');
            let sortDir = sortDirParam ?? alpineData?.sortDir ?? url.searchParams.get('sort_dir');
            if (sortBy) {
                url.searchParams.set('sort_by', sortBy);
            }
            if (sortDir) {
                url.searchParams.set('sort_dir', sortDir);
            }

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            })
                .then((r) => r.text())
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newTbody = doc.querySelector('#client-orders-table tbody');
                    const currentTbody = document.querySelector('#client-orders-table tbody');

                    if (!newTbody || !currentTbody) {
                        window.location.href = url.toString();
                        return;
                    }

                    currentTbody.innerHTML = newTbody.innerHTML;
                    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(currentTbody);
                    }

                    const newPagination = doc.querySelector('#client-orders-table [data-pagination-root]');
                    const currentPagination = document.querySelector('#client-orders-table [data-pagination-root]');
                    if (newPagination && currentPagination) {
                        currentPagination.innerHTML = newPagination.innerHTML;
                        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                            window.Alpine.initTree(currentPagination);
                        }
                    }

                    const newModals = doc.getElementById('client-order-modals-root');
                    const curModals = document.getElementById('client-order-modals-root');
                    if (newModals && curModals && window.Alpine && typeof window.Alpine.initTree === 'function') {
                        curModals.innerHTML = newModals.innerHTML;
                        curModals.querySelectorAll('[x-data]').forEach((el) => window.Alpine.initTree(el));
                    }

                    window.history.pushState({}, '', url);

                    if (alpineData) {
                        alpineData.sortBy = url.searchParams.get('sort_by') || alpineData.sortBy;
                        alpineData.sortDir = url.searchParams.get('sort_dir') || alpineData.sortDir;
                    }

                    const bulkWrap = document.querySelector('.co-bulk-wrap');
                    const bulkData = bulkWrap && bulkWrap.__x ? bulkWrap.__x.$data : null;
                    if (bulkData && typeof bulkData.syncPageCheckboxes === 'function') {
                        bulkData.syncPageCheckboxes();
                    }
                })
                .catch(() => {
                    window.location.href = url.toString();
                });
        };

        (function clientOrderStatusPolling() {
            const ofLabel = @json(__('of'));

            function formatStatusLabel(status) {
                return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            }

            function updateOrderStatus(orderId, statusData) {
                const statusBadge = document.querySelector(`.order-status-badge[data-order-id="${orderId}"]`);
                if (statusBadge) {
                    const currentStatus = statusBadge.getAttribute('data-status');
                    if (currentStatus !== statusData.status) {
                        statusBadge.setAttribute('data-status', statusData.status);
                        const dot = statusBadge.querySelector('span.rounded-full');
                        const label = statusBadge.querySelectorAll('span')[1];

                        const dotClasses = {
                            validating: 'bg-cyan-500',
                            invalid_link: 'bg-red-500',
                            restricted: 'bg-orange-500',
                            awaiting: 'bg-yellow-500',
                            pending: 'bg-blue-500',
                            in_progress: 'bg-indigo-500',
                            processing: 'bg-purple-500',
                            partial: 'bg-orange-500',
                            completed: 'bg-green-500',
                            canceled: 'bg-red-500',
                            fail: 'bg-gray-500',
                        };

                        if (dot) {
                            dot.className = `h-2.5 w-2.5 rounded-full ${dotClasses[statusData.status] || 'bg-gray-500'}`;
                        }
                        if (label) {
                            label.textContent = formatStatusLabel(statusData.status);
                        }

                        statusBadge.classList.add('animate-pulse');
                        setTimeout(() => statusBadge.classList.remove('animate-pulse'), 1000);
                    }
                }

                const progressDetail = document.querySelector(`.order-progress-detail[data-order-id="${orderId}"]`);
                if (progressDetail) {
                    progressDetail.textContent = `${new Intl.NumberFormat().format(statusData.delivered)} ${ofLabel} ${new Intl.NumberFormat().format(statusData.quantity)}`;
                }

                const serviceRing = document.querySelector(`.order-service-ring[data-order-id="${orderId}"]`);
                if (serviceRing) {
                    const p = Math.min(100, Math.max(0, statusData.progress));
                    serviceRing.style.background = `conic-gradient(#0ea5f5 calc(${p} * 1%), rgba(255,255,255,.25) 0)`;
                    serviceRing.setAttribute('data-progress', p.toFixed(1));
                }
            }

            function pollOrderStatuses() {
                const orderBadges = document.querySelectorAll('.order-status-badge');
                const orderIds = Array.from(orderBadges)
                    .map((badge) => parseInt(badge.getAttribute('data-order-id'), 10))
                    .filter((id) => !Number.isNaN(id));

                if (orderIds.length === 0) {
                    return;
                }

                const pollUrl = new URL(@json(route('client.orders.statuses')), window.location.origin);
                orderIds.forEach((id) => pollUrl.searchParams.append('order_ids[]', id));

                fetch(pollUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (data.statuses) {
                            Object.entries(data.statuses).forEach(([orderId, statusData]) => {
                                updateOrderStatus(parseInt(orderId, 10), statusData);
                            });
                        }
                    })
                    .catch((error) => console.error('Error polling order statuses:', error));
            }

            let pollInterval;

            function startPolling() {
                stopPolling();
                pollOrderStatuses();
                pollInterval = setInterval(pollOrderStatuses, 3000);
            }

            function stopPolling() {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }

            if (document.visibilityState === 'visible') {
                startPolling();
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    startPolling();
                } else {
                    stopPolling();
                }
            });

            window.addEventListener('beforeunload', stopPolling);
        })();
    </script>
</x-client-layout>

