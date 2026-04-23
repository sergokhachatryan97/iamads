<x-client-layout :title="__('New Order')">
    <style>
        .new-order-page { max-width: 760px; margin: 0 auto; padding: 8px 0 48px; }
        @media (max-width: 640px) {
            .new-order-page { padding: 4px 0 32px; }
        }
        .new-order-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px 32px 34px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        @media (max-width: 640px) {
            .new-order-card { padding: 22px 18px 24px; border-radius: 16px; }
        }
        [data-theme="light"] .new-order-card { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06); }
        .new-order-card-head { display: flex; align-items: center; gap: 14px; margin-bottom: 26px; }
        .new-order-card-icon {
            width: 44px; height: 44px; border-radius: 50%;
            background: var(--purple);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .new-order-card-title { margin: 0; font-size: 1.35rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
        .new-order-lab {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600; color: var(--text2);
            margin-bottom: 8px;
        }
        .new-order-lab i { width: 18px; text-align: center; color: var(--purple-light); opacity: 0.95; }
        .new-order-page select,
        .new-order-page input[type="text"],
        .new-order-page input[type="number"],
        .new-order-page input[type="url"],
        .new-order-page textarea {
            width: 100%;
            padding: 12px 14px !important;
            border-radius: 12px !important;
            border: 1px solid var(--border) !important;
            background: rgba(0, 0, 0, 0.28) !important;
            color: var(--text) !important;
            font-size: 14px !important;
            box-shadow: none !important;
        }
        [data-theme="light"] .new-order-page select,
        [data-theme="light"] .new-order-page input[type="text"],
        [data-theme="light"] .new-order-page input[type="number"],
        [data-theme="light"] .new-order-page input[type="url"],
        [data-theme="light"] .new-order-page textarea {
            background: var(--card2) !important;
        }
        .new-order-page select:focus,
        .new-order-page input:focus,
        .new-order-page textarea:focus,
        .new-order-page select:focus-visible,
        .new-order-page input:focus-visible,
        .new-order-page textarea:focus-visible,
        .new-order-page input:active,
        .new-order-page textarea:active {
            border-color: var(--purple-light) !important;
            outline: none !important;
            ring: none !important;
            box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.2) !important;
            background: rgba(0, 0, 0, 0.28) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .new-order-page input:focus,
        [data-theme="light"] .new-order-page textarea:focus,
        [data-theme="light"] .new-order-page select:focus,
        [data-theme="light"] .new-order-page input:active,
        [data-theme="light"] .new-order-page textarea:active {
            background: var(--card2) !important;
        }
        /* Kill Chrome / Edge autofill white-ish/yellow background on inputs */
        .new-order-page input:-webkit-autofill,
        .new-order-page input:-webkit-autofill:hover,
        .new-order-page input:-webkit-autofill:focus,
        .new-order-page input:-webkit-autofill:active,
        .new-order-page textarea:-webkit-autofill,
        .new-order-page textarea:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px rgba(20, 24, 38, 1) inset !important;
            -webkit-text-fill-color: var(--text) !important;
            caret-color: var(--text);
            border-color: var(--border) !important;
            transition: background-color 5000s ease-in-out 0s, color 5000s ease-in-out 0s;
        }
        [data-theme="light"] .new-order-page input:-webkit-autofill,
        [data-theme="light"] .new-order-page input:-webkit-autofill:focus,
        [data-theme="light"] .new-order-page textarea:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px var(--card2) inset !important;
            -webkit-text-fill-color: var(--text) !important;
        }
        /* Kill any stray Tailwind focus:bg-white / hover:bg-white that may apply */
        .new-order-page input.bg-white,
        .new-order-page input:focus.bg-white,
        .new-order-page textarea.bg-white {
            background: rgba(0, 0, 0, 0.28) !important;
        }
        .new-order-page select:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .new-order-page select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238892a4'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 18px !important;
            padding-right: 40px !important;
        }
        .new-order-page label.block.text-sm.font-medium.text-gray-700,
        .new-order-page label.block.text-sm.font-semibold.text-gray-800 {
            color: var(--text2) !important;
        }
        .new-order-tabs { border-bottom-color: var(--border) !important; margin-bottom: 22px !important; }
        .new-order-tabs button {
            color: var(--text3) !important;
            border-color: transparent !important;
        }
        .new-order-tabs button.border-indigo-500 {
            color: var(--purple-light) !important;
            border-color: var(--purple) !important;
        }
        .new-order-page .bg-red-100 {
            background: rgba(225, 112, 85, 0.12) !important;
            border-color: rgba(225, 112, 85, 0.4) !important;
            color: #fab1a0 !important;
        }
        .new-order-page .text-red-600, .new-order-page .text-red-700 { color: #ff7675 !important; }
        .new-order-page .bg-gray-50,
        .new-order-page .bg-indigo-50 {
            background: rgba(0, 0, 0, 0.2) !important;
            border-color: var(--border) !important;
        }
        .new-order-page .text-gray-700, .new-order-page .text-gray-800, .new-order-page .text-gray-900 {
            color: var(--text2) !important;
        }
        .new-order-page .text-gray-500, .new-order-page .text-gray-600 { color: var(--text3) !important; }
        .new-order-page .border-gray-200, .new-order-page .border-gray-300 { border-color: var(--border) !important; }
        .new-order-total-box {
            text-align: center;
            padding: 20px 16px;
            border-radius: 14px;
            margin: 22px 0 8px;
            background: linear-gradient(145deg, rgba(0, 210, 211, 0.12), rgba(108, 92, 231, 0.1));
            border: 1px solid rgba(0, 210, 211, 0.25);
        }
        .new-order-total-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text3); margin-bottom: 6px; }
        .new-order-total-amount { font-size: 2rem; font-weight: 800; color: var(--teal); letter-spacing: -0.02em; }
        .new-order-total-hint { font-size: 12px; color: var(--text3); margin-top: 8px; }
        .new-order-submit {
            width: 100%;
            margin-top: 20px;
            padding: 15px 20px;
            border: none;
            border-radius: 14px;
            background: var(--purple);
            color: #fff !important;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 24px rgba(108, 92, 231, 0.4);
            transition: filter 0.15s;
        }
        .new-order-submit:hover:not(:disabled) { filter: brightness(1.06); }
        .new-order-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .new-order-actions { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
        .new-order-cancel { text-align: center; font-size: 13px; color: var(--text3); text-decoration: none; }
        .new-order-cancel:hover { color: var(--text2); }
        .new-order-icon-x {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 10px;
            border: 1px solid rgba(255, 118, 117, 0.45);
            background: rgba(255, 118, 117, 0.1);
            color: #ff7675;
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.12s ease;
        }
        .new-order-icon-x:hover {
            background: rgba(255, 118, 117, 0.22);
            border-color: rgba(255, 154, 153, 0.7);
            color: #fab1a0;
        }
        .new-order-icon-x:active { transform: scale(0.96); }
        [data-theme="light"] .new-order-icon-x {
            border-color: rgba(220, 38, 38, 0.35);
            background: rgba(220, 38, 38, 0.08);
            color: #dc2626;
        }
        [data-theme="light"] .new-order-icon-x:hover {
            background: rgba(220, 38, 38, 0.14);
            border-color: rgba(220, 38, 38, 0.5);
            color: #b91c1c;
        }
        .new-order-charge-legacy { display: none !important; }

        /* Highlighted category section */
        .new-order-category-wrap {
            position: relative;
            padding: 18px 18px 18px;
            border-radius: 16px;
            background: linear-gradient(145deg, rgba(0, 210, 211, 0.10), rgba(108, 92, 231, 0.10));
            border: 1px solid rgba(0, 210, 211, 0.32);
            margin-bottom: 24px;
        }
        .new-order-category-wrap .new-order-lab {
            color: var(--teal);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .new-order-category-wrap .new-order-lab i {
            color: var(--teal);
        }
        [data-theme="light"] .new-order-category-wrap {
            background: linear-gradient(145deg, rgba(0, 184, 184, 0.08), rgba(108, 92, 231, 0.08));
            border-color: rgba(0, 184, 184, 0.35);
        }

        /* Service-row layout in the info block (ID badge + name) */
        .new-order-svc-value {
            display: inline-flex !important;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .new-order-id-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 6px;
            background: rgba(108, 92, 231, 0.18);
            color: var(--purple-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border: 1px solid rgba(108, 92, 231, 0.28);
        }
        [data-theme="light"] .new-order-id-badge {
            background: rgba(108, 92, 231, 0.10);
            color: #5546d6;
            border-color: rgba(108, 92, 231, 0.30);
        }
        .new-order-svc-name {
            font-weight: 600;
            color: var(--text);
            word-break: break-word;
        }

        /* Service info block (above link inputs) */
        .new-order-info-block {
            margin-bottom: 22px;
            padding: 16px 18px;
            border-radius: 14px;
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid var(--border);
        }
        [data-theme="light"] .new-order-info-block {
            background: var(--card2);
        }
        .new-order-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .new-order-info-row + .new-order-info-row { margin-top: 10px; }
        .new-order-info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .new-order-info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        .new-order-price-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(0, 210, 211, 0.15);
            border: 1px solid rgba(0, 210, 211, 0.4);
            color: var(--teal);
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 0.01em;
        }
        .new-order-price-pill i { font-size: 12px; }
        [data-theme="light"] .new-order-price-pill {
            background: rgba(0, 184, 184, 0.10);
            border-color: rgba(0, 184, 184, 0.45);
            color: #008b8b;
        }
        .new-order-info-desc {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
            font-size: 13px;
            line-height: 1.55;
            color: var(--text2);
            white-space: pre-line;     /* preserve newlines from the description */
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        .new-order-rate-line {
            font-size: 13px;
            color: var(--text2);
        }
        .new-order-rate-line .strike {
            text-decoration: line-through;
            color: var(--text3);
            margin-right: 8px;
            font-weight: 500;
        }
        .new-order-discount-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(108, 92, 231, 0.18);
            color: var(--purple-light);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        /* Hide legacy big total price block */
        .new-order-total-box { display: none !important; }

        /* Custom category (social media) dropdown */
        .custom-category-select { position: relative; }
        .custom-category-select-trigger {
            width: 100%;
            padding: 14px 44px 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.28);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            line-height: 1.3;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            transition: border-color 0.15s, box-shadow 0.15s;
            min-height: 52px;
            -webkit-appearance: none;
            appearance: none;
        }
        .custom-category-select-trigger > * { color: inherit; }
        [data-theme="light"] .custom-category-select-trigger { background: var(--card2); }
        .custom-category-select-trigger:hover:not(:disabled) { border-color: var(--teal); }
        .custom-category-select-trigger.open {
            border-color: var(--teal);
            box-shadow: 0 0 0 2px rgba(0, 210, 211, 0.2);
        }
        .custom-category-select-trigger .chevron {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.2s;
            color: var(--text3);
            pointer-events: none;
            font-size: 12px;
        }
        .custom-category-select-trigger.open .chevron { transform: translateY(-50%) rotate(180deg); }
        .custom-category-select-trigger .css-trigger-placeholder {
            color: var(--text3) !important;
            font-size: 14px;
            font-weight: 500;
            opacity: 1 !important;
            display: inline-flex;
            align-items: center;
            line-height: 1.2;
            background: transparent !important;
        }
        .custom-category-select-trigger .selected-content {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }
        .custom-category-select-trigger .selected-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
        }
        .category-icon-wrap {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        [data-theme="light"] .category-icon-wrap { background: rgba(0, 0, 0, 0.05); }
        .category-icon-wrap img,
        .category-icon-wrap svg {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }
        .category-icon-wrap i { font-size: 14px; color: var(--text2); }
        .category-icon-wrap .fallback {
            font-size: 12px;
            font-weight: 800;
            color: var(--text2);
        }
        .custom-category-select-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 50;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            max-height: 360px;
            display: flex;
            flex-direction: column;
        }
        [data-theme="light"] .custom-category-select-panel { box-shadow: 0 16px 50px rgba(0, 0, 0, 0.12); }
        .custom-category-select-list {
            overflow-y: auto;
            flex: 1;
            padding: 6px 0;
        }
        .custom-category-select-list::-webkit-scrollbar { width: 8px; }
        .custom-category-select-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.08);
            border-radius: 4px;
        }
        .custom-category-select-option {
            width: 100%;
            padding: 11px 14px;
            background: transparent;
            border: 0;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text2);
            font-size: 14px;
            transition: background 0.1s;
        }
        .custom-category-select-option:hover {
            background: rgba(0, 210, 211, 0.12);
            color: var(--text);
        }
        .custom-category-select-option.selected {
            background: rgba(0, 210, 211, 0.18);
            color: var(--text);
            font-weight: 600;
        }
        .custom-category-select-option .opt-name { flex: 1; min-width: 0; }
        .custom-category-select-option .opt-driver {
            flex-shrink: 0;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(108, 92, 231, 0.14);
            color: var(--purple-light);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid rgba(108, 92, 231, 0.3);
        }

        /* Custom searchable service dropdown */
        .custom-service-select { position: relative; }
        .custom-service-select-trigger {
            width: 100%;
            padding: 14px 44px 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.28);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            line-height: 1.3;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            transition: border-color 0.15s, box-shadow 0.15s;
            min-height: 52px;
            -webkit-appearance: none;
            appearance: none;
        }
        .custom-service-select-trigger > * { color: inherit; }
        [data-theme="light"] .custom-service-select-trigger { background: var(--card2); }
        .custom-service-select-trigger:hover:not(:disabled) { border-color: var(--purple-light); }
        .custom-service-select-trigger:disabled { opacity: 0.6; cursor: not-allowed; }
        .custom-service-select-trigger.open {
            border-color: var(--purple-light);
            box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.2);
        }
        .custom-service-select-trigger .chevron {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.2s;
            color: var(--text3);
            pointer-events: none;
            font-size: 12px;
        }
        .custom-service-select-trigger.open .chevron { transform: translateY(-50%) rotate(180deg); }
        .custom-service-select-trigger .css-trigger-placeholder {
            color: var(--text3) !important;
            font-size: 14px;
            font-weight: 500;
            opacity: 1 !important;
            display: inline-flex;
            align-items: center;
            line-height: 1.2;
            background: transparent !important;
        }
        .custom-service-select-trigger .css-trigger-placeholder > span {
            color: inherit !important;
            background: transparent !important;
        }
        .custom-service-select-trigger .selected-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        .custom-service-select-trigger .selected-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            min-width: 0;
        }
        .custom-service-select-trigger .id-tag {
            font-weight: 800;
            color: var(--purple-light);
            margin-right: 6px;
        }
        .custom-service-select-trigger .price-tag {
            flex-shrink: 0;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0, 210, 211, 0.15);
            border: 1px solid rgba(0, 210, 211, 0.4);
            color: var(--teal);
            font-weight: 800;
            font-size: 12px;
            white-space: nowrap;
        }
        [data-theme="light"] .custom-service-select-trigger .price-tag {
            background: rgba(0, 184, 184, 0.10);
            border-color: rgba(0, 184, 184, 0.45);
            color: #008b8b;
        }
        .custom-service-select-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 50;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            max-height: 440px;
            display: flex;
            flex-direction: column;
        }
        [data-theme="light"] .custom-service-select-panel { box-shadow: 0 16px 50px rgba(0, 0, 0, 0.12); }
        .custom-service-select-search {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            position: relative;
        }
        /* Use input[type="text"] to match specificity of the global .new-order-page rules */
        .new-order-page .custom-service-select-search input[type="text"] {
            width: 100%;
            padding: 10px 12px 10px 38px !important;
            border-radius: 8px !important;
            border: 1px solid var(--border) !important;
            background: rgba(0, 0, 0, 0.28) !important;
            color: var(--text) !important;
            font-size: 13px !important;
            box-shadow: none !important;
        }
        [data-theme="light"] .new-order-page .custom-service-select-search input[type="text"] {
            background: var(--card2) !important;
        }
        .custom-service-select-search .search-icon {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 13px;
            pointer-events: none;
            z-index: 1;
        }
        .custom-service-select-list { overflow-y: auto; flex: 1; padding: 4px 0 8px; }
        .custom-service-select-list::-webkit-scrollbar { width: 8px; }
        .custom-service-select-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.08);
            border-radius: 4px;
        }
        .custom-service-select-group {
            padding: 10px 14px 6px;
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--teal);
            background: linear-gradient(
                to right,
                rgba(0,224,198,0.12),
                rgba(0,224,198,0.02)
            );
            border-bottom: 1px solid var(--border);
            position: relative;
            top: 0;
            z-index: 2;
        }
        [data-theme="light"] .custom-service-select-group { background: var(--card); }
        .custom-service-select-option {
            width: 100%;
            padding: 11px 14px;
            background: transparent;
            border: 0;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--text2);
            font-size: 13.5px;
            transition: background 0.1s;
        }
        .custom-service-select-option:hover {
            background: rgba(108, 92, 231, 0.14);
            color: var(--text);
        }
        .custom-service-select-option.selected {
            background: rgba(108, 92, 231, 0.22);
            color: var(--text);
            font-weight: 600;
        }
        .custom-service-select-option .opt-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            min-width: 0;
        }
        .custom-service-select-option .opt-id {
            font-weight: 800;
            color: var(--purple-light);
            margin-right: 6px;
            display: inline-block;
            min-width: 32px;
        }
        .custom-service-select-option .opt-price {
            flex-shrink: 0;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(0, 210, 211, 0.12);
            color: var(--teal);
            font-weight: 700;
            font-size: 11.5px;
            border: 1px solid rgba(0, 210, 211, 0.3);
            white-space: nowrap;
        }
        [data-theme="light"] .custom-service-select-option .opt-price {
            background: rgba(0, 184, 184, 0.08);
            color: #008b8b;
            border-color: rgba(0, 184, 184, 0.35);
        }
        .custom-service-select-empty {
            padding: 22px 14px;
            text-align: center;
            color: var(--text3);
            font-size: 13px;
        }
        @media (max-width: 640px) {
            .custom-service-select-trigger { padding: 12px 40px 12px 14px; flex-wrap: wrap; }
            .custom-service-select-trigger .price-tag { font-size: 11px; padding: 3px 8px; }
            .custom-service-select-option { padding: 10px 12px; font-size: 13px; gap: 8px; }
            .custom-service-select-option .opt-price { font-size: 11px; padding: 2px 7px; }
        }

        /* Link-type expectation pill ("Channel link required") */
        .new-order-link-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(108, 92, 231, 0.14);
            border: 1px solid rgba(108, 92, 231, 0.32);
            color: var(--purple-light);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-left: 8px;
            white-space: nowrap;
        }
        .new-order-link-hint i { font-size: 10px; }
        [data-theme="light"] .new-order-link-hint {
            background: rgba(108, 92, 231, 0.10);
            color: #5546d6;
            border-color: rgba(108, 92, 231, 0.35);
        }

        /* "Accepted: ..." helper line under the link section */
        .new-order-link-accepted {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: -4px 0 12px;
            padding: 8px 12px;
            border-radius: 10px;
            background: rgba(0, 210, 211, 0.07);
            border: 1px dashed rgba(0, 210, 211, 0.28);
            color: var(--text2);
            font-size: 12.5px;
            line-height: 1.4;
        }
        .new-order-link-accepted i {
            color: var(--teal);
            font-size: 12px;
            flex-shrink: 0;
        }
        [data-theme="light"] .new-order-link-accepted {
            background: rgba(0, 184, 184, 0.06);
            border-color: rgba(0, 184, 184, 0.30);
        }

        /* Section header (e.g. Links & Quantities) */
        .new-order-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .new-order-section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .new-order-section-title i {
            color: var(--purple-light);
            font-size: 13px;
        }
        /* "Add Link" / "Add Service" pill button */
        .new-order-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(108, 92, 231, 0.16);
            border: 1px dashed rgba(108, 92, 231, 0.5);
            color: var(--purple-light);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, transform 0.1s;
            font-family: inherit;
            line-height: 1;
            -webkit-appearance: none;
            appearance: none;
        }
        .new-order-add-btn:hover {
            background: rgba(108, 92, 231, 0.26);
            border-color: var(--purple-light);
            border-style: solid;
        }
        .new-order-add-btn:active { transform: scale(0.97); }
        .new-order-add-btn .plus {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(108, 92, 231, 0.28);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }
        [data-theme="light"] .new-order-add-btn {
            background: rgba(108, 92, 231, 0.10);
            color: #5546d6;
        }
        [data-theme="light"] .new-order-add-btn:hover {
            background: rgba(108, 92, 231, 0.18);
        }

        /* Compact charge summary line above submit */
        .new-order-charge-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 14px 0 4px;
            padding: 12px 16px;
            border-radius: 12px;
            background: rgba(108, 92, 231, 0.10);
            border: 1px solid rgba(108, 92, 231, 0.28);
        }
        .new-order-charge-summary .label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text3);
        }
        .new-order-charge-summary .amount {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--teal);
            letter-spacing: -0.01em;
        }
    </style>

    <div class="new-order-page">
        <div class="new-order-card">
                    @if ($errors->any())
                        @php
                            $errorLabels = [
                                'targets' => __('Links & Quantities'),
                                'services' => __('Services'),
                                'link' => __('Link'),
                                'link_2' => __('Source Channel'),
                                'comments' => __('Comments'),
                                'comment_text' => __('Custom Comment'),
                                'star_rating' => __('Star Rating'),
                                'category_id' => __('Category'),
                                'service_id' => __('Service'),
                                'dripfeed_quantity' => __('Dripfeed Quantity'),
                                'dripfeed_interval' => __('Dripfeed Interval'),
                                'dripfeed_interval_unit' => __('Dripfeed Interval Unit'),
                                'speed_tier' => __('Speed Tier'),
                            ];
                            $formatErrorKey = function ($key) use ($errorLabels) {
                                if (preg_match('/^targets\.(\d+)\.link$/', $key, $m)) {
                                    return __('Link') . ' ' . ((int)$m[1] + 1);
                                }
                                if (preg_match('/^targets\.(\d+)\.quantity$/', $key, $m)) {
                                    return __('Link') . ' ' . ((int)$m[1] + 1) . ' – ' . __('Quantity');
                                }
                                if (preg_match('/^services\.(\d+)\.service_id$/', $key, $m)) {
                                    return __('Service') . ' ' . ((int)$m[1] + 1);
                                }
                                if (preg_match('/^services\.(\d+)\.quantity$/', $key, $m)) {
                                    return __('Service') . ' ' . ((int)$m[1] + 1) . ' – ' . __('Quantity');
                                }
                                return $errorLabels[$key] ?? $key;
                            };
                        @endphp
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->messages() as $key => $messages)
                                    @foreach ($messages as $message)
                                        <li><strong>{{ $formatErrorKey($key) }}:</strong> {{ $message }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="new-order-card-head">
                        <span class="new-order-card-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        <h1 class="new-order-card-title">{{ __('New Order') }}</h1>
                    </div>

                    <div x-data="orderForm()" x-init="init()">
                        <!-- Tabs -->
{{--                        <div class="mb-6 border-b border-gray-200 new-order-tabs">--}}
{{--                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">--}}
{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    @click="orderType = 'single'"--}}
{{--                                    :class="orderType === 'single' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"--}}
{{--                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">--}}
{{--                                    {{ __('Single Service Order') }}--}}
{{--                                </button>--}}
{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    @click="orderType = 'multi'"--}}
{{--                                    :class="orderType === 'multi' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"--}}
{{--                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">--}}
{{--                                    {{ __('Multi-Service Order') }}--}}
{{--                                </button>--}}
{{--                            </nav>--}}
{{--                        </div>--}}
                        <!-- Single Service Order Form -->
                        <form method="POST" action="{{ route('client.orders.store') }}"
                              x-show="orderType === 'single'"
                              @submit.prevent="submitForm">
                            @csrf
                            <input type="hidden" name="form_type" value="single">

                            <!-- Category (platform / social media) -->
                            <div class="new-order-category-wrap">
                                <label class="new-order-lab">
                                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                                    {{ __('common.social_media') }} <span class="text-red-400">*</span>
                                </label>
                                <div class="custom-category-select"
                                     x-data="{ open: false }"
                                     @click.outside="open = false"
                                     @keydown.escape.window="open = false">
                                    <input type="hidden" name="category_id" :value="categoryId" required>
                                    <button type="button"
                                            class="custom-category-select-trigger"
                                            :class="open ? 'open' : ''"
                                            @click="open = !open">
                                        <template x-if="selectedCategory">
                                            <span class="selected-content">
                                                <span class="category-icon-wrap" x-html="renderCategoryIcon(selectedCategory)"></span>
                                                <span class="selected-name" x-text="selectedCategory.name"></span>
                                            </span>
                                        </template>
                                        <template x-if="!selectedCategory">
                                            <span class="css-trigger-placeholder">{{ __('common.select_platform') }}</span>
                                        </template>
                                        <i class="fa-solid fa-chevron-down chevron"></i>
                                    </button>

                                    <div class="custom-category-select-panel" x-show="open" x-transition.opacity x-cloak>
                                        <div class="custom-category-select-list">
                                            <template x-for="cat in categoriesData" :key="'cat-' + cat.id">
                                                <button type="button"
                                                        class="custom-category-select-option"
                                                        :class="categoryId == cat.id ? 'selected' : ''"
                                                        @click="categoryId = cat.id; onCategoryChange(); loadServices(); open = false">
                                                    <span class="category-icon-wrap" x-html="renderCategoryIcon(cat)"></span>
                                                    <span class="opt-name" x-text="cat.name"></span>
                                                    <span class="opt-driver" x-show="cat.link_driver" x-text="cat.link_driver"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Service (custom searchable dropdown) -->
                            <div class="mb-6">
                                <label class="new-order-lab">
                                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                                    {{ __('Service') }} <span class="text-red-400">*</span>
                                </label>
                                <div class="custom-service-select"
                                     x-data="{ open: false }"
                                     @click.outside="open = false; serviceSearch = ''"
                                     @keydown.escape.window="open = false">
                                    <input type="hidden" name="service_id" :value="serviceId">
                                    <button type="button"
                                            class="custom-service-select-trigger"
                                            :class="open ? 'open' : ''"
                                            :disabled="!categoryId || loading"
                                            @click="if (!loading && categoryId) { open = !open; if (open) $nextTick(() => $refs.csearch?.focus()); }">
                                        <template x-if="selectedService">
                                            <span class="selected-content">
                                                <span class="selected-name">
                                                    <span class="id-tag">ID&nbsp;<span x-text="selectedService.id"></span></span>
                                                    <span x-text="selectedService.name"></span>
                                                </span>
                                                <span class="price-tag" x-show="selectedService.hide_quantity !== true">
                                                    $<span x-text="formatMoney(selectedService.rate_per_1000 || 0)"></span> / 1000
                                                </span>
                                            </span>
                                        </template>
                                        <template x-if="!selectedService">
                                            <span class="css-trigger-placeholder">
                                                <span x-show="loading" x-cloak>{{ __('Loading...') }}</span>
                                                <span x-show="!loading && categoryId" x-cloak>{{ __('common.select_service_placeholder') }}</span>
                                                <span x-show="!loading && !categoryId" x-cloak>{{ __('common.select_platform') }}</span>
                                            </span>
                                        </template>
                                        <i class="fa-solid fa-chevron-down chevron"></i>
                                    </button>

                                    <div class="custom-service-select-panel" x-show="open" x-transition.opacity x-cloak>
                                        <div class="custom-service-select-search">
                                            <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                            <input type="text"
                                                   x-ref="csearch"
                                                   x-model="serviceSearch"
                                                   @click.stop
                                                   autocomplete="off"
                                                   placeholder="{{ __('Search services…') }}"
                                                   style="padding: 10px 12px 10px 38px !important; font-size: 13px;">
                                        </div>
                                        <div class="custom-service-select-list">
                                            <template x-for="group in filteredServiceGroups(serviceSearch)" :key="'g-' + group.label">
                                                <div>
                                                    <div class="custom-service-select-group" x-text="group.label"></div>
                                                    <template x-for="service in group.services" :key="'sopt-' + service.id">
                                                        <button type="button"
                                                                class="custom-service-select-option"
                                                                :class="serviceId == service.id ? 'selected' : ''"
                                                                @click="serviceId = service.id; updateServiceInfo(true); open = false; serviceSearch = ''">
                                                            <span class="opt-name">
                                                                <span class="opt-id">ID&nbsp;<span x-text="service.id"></span></span>
                                                                <span x-text="service.name"></span>
                                                            </span>
                                                            <span class="opt-price" x-show="service.hide_quantity !== true">
                                                                $<span x-text="formatMoney(service.rate_per_1000 || 0)"></span> / 1000
                                                            </span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>
                                            <div x-show="filteredServiceGroups(serviceSearch).length === 0"
                                                 class="custom-service-select-empty">
                                                {{ __('No matching services') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @error('service_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Service Info Block (visible BEFORE link/quantity inputs) -->
                            <div class="new-order-info-block" x-show="selectedService" x-cloak>
                                <div class="new-order-info-row">
                                    <div class="new-order-info-label">{{ __('Service') }}</div>
                                    <div class="new-order-info-value new-order-svc-value">
                                        <span class="new-order-svc-name" x-text="selectedService?.name"></span>
                                    </div>
                                </div>

                                <!-- Price (Pill, prominently visible) -->
                                <div class="new-order-info-row" x-show="selectedService?.hide_quantity !== true">
                                    <div class="new-order-info-label">{{ __('Price') }}</div>
                                    <div class="new-order-price-pill">
                                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                        <span>$<span x-text="formatMoney(selectedService?.rate_per_1000 || 0)"></span> / 1000</span>
                                    </div>
                                </div>

                                <!-- Fixed order price (e.g. premium folder) -->
                                <div class="new-order-info-row" x-show="selectedService?.hide_quantity === true" x-cloak>
                                    <div class="new-order-info-label">{{ __('Order price') }}</div>
                                    <div class="new-order-price-pill">
                                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                        <span>$<span x-text="formatMoney(calculateCharge() || 0)"></span></span>
                                    </div>
                                </div>

                                <!-- Discount applied -->
                                <div class="new-order-info-row" x-show="selectedService?.discount_applies && selectedService?.client_discount > 0">
                                    <div class="new-order-info-label">{{ __('Discount') }}</div>
                                    <div class="new-order-rate-line">
                                        <span class="strike">$<span x-text="formatMoney(selectedService?.default_rate_per_1000 || 0)"></span></span>
                                        <span class="new-order-discount-tag">
                                            -<span x-text="selectedService?.client_discount || 0"></span>%
                                        </span>
                                    </div>
                                </div>

                                <!-- Custom rate applied -->
                                <div class="new-order-info-row" x-show="selectedService?.has_custom_rate && selectedService?.custom_rate">
                                    <div class="new-order-info-label">{{ __('Custom Rate') }}</div>
                                    <div class="new-order-rate-line">
                                        <template x-if="selectedService?.custom_rate?.type === 'fixed'">
                                            <span>{{ __('Fixed') }} ${{ '' }}<span x-text="formatMoney(selectedService?.custom_rate?.value || 0)"></span> / 1000</span>
                                        </template>
                                        <template x-if="selectedService?.custom_rate?.type === 'percent'">
                                            <span>
                                                <span x-text="selectedService?.custom_rate?.value || 0"></span>% {{ __('of default') }}
                                                <span class="strike ml-2">$<span x-text="formatMoney(selectedService?.default_rate_per_1000 || 0)"></span></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Quantity Rules / Row Count Rules -->
                                <div class="new-order-info-row" x-show="selectedService?.hide_quantity !== true">
                                    <div class="new-order-info-label">
                                        <span x-show="selectedService?.service_type === 'custom_comments'">{{ __('Row Count Rules') }}</span>
                                        <span x-show="selectedService?.service_type !== 'custom_comments'">{{ __('Quantity Rules') }}</span>
                                    </div>
                                    <div class="new-order-info-value">
                                        <span x-text="'Min: ' + (selectedService?.min_quantity || 1)"></span>
                                        <span x-show="selectedService?.max_quantity" x-text="' , Max: ' + (selectedService?.max_quantity || '')"></span>
                                        <span x-show="selectedService?.service_type !== 'custom_comments' && selectedService?.increment > 0"
                                              x-text="' • Increment: ' + (selectedService?.increment || 0)"></span>
                                    </div>
                                </div>

                                <!-- Service description (visible before user enters link) -->
                                <div class="new-order-info-desc" x-show="selectedService?.description" x-text="selectedService?.description"></div>
                            </div>

                            <!-- Custom Comments Field (only for custom_comments service type) -->
                            <div
                                x-show="selectedService?.service_type === 'custom_comments'"
                                x-transition
                                class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4"
                            >
                                <label for="comments" class="block text-sm font-semibold text-gray-800 mb-2">
                                    {{ __('Comments') }}
                                    <span class="text-red-500">*</span>
                                </label>

                                <textarea
                                    id="comments"
                                    name="comments"
                                    x-model="comments"
                                    @input="calculateCommentsTotal(); validateCommentsCount()"
                                    @keyup="calculateCommentsTotal(); validateCommentsCount()"
                                    rows="6"
                                    placeholder="{{ __('Enter comments, one per line') }}"
                                    :required="selectedService?.service_type === 'custom_comments'"
                                    :class="commentsCountError ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                                    class="block w-full resize-y rounded-md bg-white shadow-sm sm:text-sm"
                                ></textarea>

                                <div class="mt-2 flex items-start gap-2 text-xs text-gray-600">
                                    <svg class="h-4 w-4 text-indigo-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M13 16h-1v-4h-1m1-4h.01M12 18a6 6 0 100-12 6 6 0 000 12z" />
                                    </svg>
                                    <p>
                                        {{ __('Each non-empty line will be processed as a separate comment and will create its own order.') }}
                                        <span x-show="selectedService?.min_quantity" class="font-medium">
                                            {{ __('Minimum') }}: <span x-text="selectedService?.min_quantity || 1"></span> {{ __('comments required') }}.
                                        </span>
                                    </p>
                                </div>

                                <p x-show="commentsCountError" class="mt-2 text-sm text-red-600 font-medium" x-text="commentsCountError"></p>

                                <!-- Real-time Comments Count (rate shown above in info block) -->
                                <div class="mt-3 p-3 bg-indigo-50 rounded-md border border-indigo-200" x-show="selectedService?.service_type === 'custom_comments'">
                                    <div class="text-sm text-gray-700">
                                        <span class="font-medium">{{ __('Comments Count') }}:</span>
                                        <span x-text="commentsCount || 0"></span>
                                    </div>
                                </div>

                                @error('comments')
                                <p class="mt-2 text-sm text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>


                            <!-- App: Star (app_download_custom_review_star, app_download_positive_review_star) -->
                            <div class="mb-6" x-show="['app_download_custom_review_star','app_download_positive_review_star','app_download_positive_review'].includes(selectedService?.template_key)" x-cloak>
                                <div class="p-4 bg-gray-50 rounded-md border border-gray-200">
                                    <div class="mt-4">
                                        <label for="star_rating" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Star Rating') }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="star_rating"
                                            name="star_rating"
                                            x-model.number="starRating"
                                            :required="['app_download_custom_review_star','app_download_positive_review_star','app_download_positive_review'].includes(selectedService?.template_key)"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm max-w-xs">
                                            <option value="5">5 {{ __('stars') }} ({{ __('default') }})</option>
                                            <option value="4">4 {{ __('stars') }}</option>
                                            <option value="3">3 {{ __('stars') }}</option>
                                            <option value="2">2 {{ __('stars') }}</option>
                                            <option value="1">1 {{ __('star') }}</option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Rating from 1 to 5 stars.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Link field for custom_comments (uses category link type) -->
                            <div class="mb-6" x-show="selectedService?.service_type === 'custom_comments'">
                                <label for="comments_link" class="new-order-lab">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                    {{ __('Link') }}
                                    <span class="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="comments_link"
                                    :required="selectedService?.service_type === 'custom_comments'"
                                    name="link"
                                    x-model="commentsLink"
                                    @input="commentsLinkValid = validateLink(commentsLink)"
                                    :class="commentsLinkValid ? 'border-gray-300' : 'border-red-300'"
                                    :placeholder="linkPlaceholder()"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p x-show="!commentsLinkValid && commentsLink" class="mt-1 text-xs text-red-600" x-text="linkErrorForType(linkType)"></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Enter the target link for comments. Format depends on the selected category.') }}
                                </p>
                                @error('link')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Invite Subscribers (2 links: source + target) -->
                            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4"
                                 x-show="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                 x-cloak>
                                <p class="text-sm font-medium text-amber-900 mb-3">
                                    {{ __('Invite Subscribers From Other Channel') }}
                                </p>
                                <p class="text-xs text-amber-800 mb-4">
                                    {{ __('Enter the source channel (invite FROM) and target channel/group (invite TO).') }}
                                </p>
                                <div class="space-y-4">
                                    <div>
                                        <label for="invite_source_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Source Channel (invite FROM)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="invite_source_link"
                                            name="link_2"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model="inviteSourceLink"
                                            @input="inviteSourceLinkValid = validateTelegramLink(inviteSourceLink)"
                                            :class="(inviteSourceLinkValid && !getFieldError('link_2')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/source_channel"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!inviteSourceLinkValid && inviteSourceLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('link_2')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="invite_target_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Target Channel/Group (invite TO)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="invite_target_link"
                                            name="targets[0][link]"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model="inviteTargetLink"
                                            @input="inviteTargetLinkValid = validateTelegramLink(inviteTargetLink)"
                                            :class="(inviteTargetLinkValid && !getFieldError('targets.0.link')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/target_channel or t.me/+inviteHash"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!inviteTargetLinkValid && inviteTargetLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('targets.0.link')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-48">
                                        <label for="invite_quantity" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Quantity') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            id="invite_quantity"
                                            name="targets[0][quantity]"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model.number="inviteQuantity"
                                            :min="selectedService?.min_quantity || 1"
                                            :max="selectedService?.max_quantity || null"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            :class="getFieldError('targets.0.quantity') ? 'border-red-300' : 'border-gray-300'"
                                            class="block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @error('targets.0.quantity')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Telegram Premium Folder (system-managed, quantity fixed to 1) -->
                            <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50/80 p-4"
                                 x-show="selectedService?.template_key === 'telegram_premium_folder'"
                                 x-cloak>
                                <p class="text-sm font-medium text-indigo-900 mb-1">
                                    {{ __('Premium folder placement') }}
                                </p>
                                <p class="text-xs text-indigo-800 mb-4" x-show="selectedService?.display_note">
                                    <span>{{ __('Informational') }}:</span>
                                    <span class="font-medium" x-text="selectedService?.display_note"></span>
                                </p>
                                <div class="space-y-4">
                                    <div>
                                        <label for="premium_folder_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Channel or group link') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="premium_folder_link"
                                            name="targets[0][link]"
                                            x-model="premiumFolderLink"
                                            @input="premiumFolderLinkValid = validateTelegramLink(premiumFolderLink)"
                                            :class="(premiumFolderLinkValid && !getFieldError('targets.0.link')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/channel or t.me/+invite"
                                            :required="selectedService?.template_key === 'telegram_premium_folder'"
                                            :disabled="selectedService?.template_key !== 'telegram_premium_folder'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!premiumFolderLinkValid && premiumFolderLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('targets.0.link')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-full max-w-xs">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Duration') }}
                                        </label>
                                        <p class="text-sm text-gray-800">30 {{ __('days') }} ({{ __('1 month') }})</p>
                                        <input type="hidden" name="duration_days" value="30">
                                        @error('duration_days')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <input type="hidden" name="targets[0][quantity]" value="1">
                                </div>
                            </div>

                            <!-- Targets (Link + Quantity pairs) — x-if removes fields so premium-folder block is the only targets[0] submitter -->
                            <template x-if="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel' && selectedService?.template_key !== 'telegram_premium_folder'">
                            <div class="mb-6" x-cloak>
                                <div class="new-order-section-head">
                                    <span class="new-order-section-title">
                                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                                        {{ __('Links & Quantities') }} <span class="text-red-400">*</span>
{{--                                        <span class="new-order-link-hint" x-show="linkTypeLabel()" x-cloak>--}}
{{--                                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>--}}
{{--                                            <span x-text="linkTypeLabel() + ' ' + '{{ __('link') }}'"></span>--}}
{{--                                        </span>--}}
                                    </span>
                                    <button
                                        type="button"
                                        @click="addTargetRow"
                                        class="new-order-add-btn"
                                        title="{{ __('Add Link') }}">
                                        <span class="plus" aria-hidden="true">+</span>
                                        <span>{{ __('Add Link') }}</span>
                                    </button>
                                </div>

{{--                                <p class="new-order-link-accepted" x-show="linkAcceptedHint()" x-cloak>--}}
{{--                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>--}}
{{--                                    <span x-text="linkAcceptedHint()"></span>--}}
{{--                                </p>--}}

                                <div class="space-y-3">
                                    <template x-for="(target, index) in targets" :key="index">
                                        <div class="flex gap-3 items-start"
                                             :class="getTargetError(index, 'link') || getTargetError(index, 'quantity') ? 'p-3 rounded-md border-2 border-red-300 bg-red-50' : ''">
                                            <div class="flex-1">
                                                <label :for="'targets_' + index + '_link'" class="block text-xs font-medium text-gray-700 mb-1">
                                                    {{ __('Link') }} <span x-text="index + 1"></span> <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    :id="'targets_' + index + '_link'"
                                                    :name="'targets[' + index + '][link]'"
                                                    x-model="target.link"
                                                    @input="target.linkValid = validateLink(target.link)"
                                                    :class="(target.linkValid && !getTargetError(index, 'link')) ? 'border-gray-300' : 'border-red-300'"
                                                    :placeholder="linkPlaceholder()"
                                                    :required="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                                    :disabled="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <p x-show="!target.linkValid && target.link" class="mt-1 text-xs text-red-600" x-text="linkErrorForType(linkType)"></p>
                                                <p x-show="getTargetError(index, 'link')" class="mt-1 text-xs text-red-600" x-text="getTargetError(index, 'link')"></p>
                                            </div>
                                            <div class="w-32">
                                                <label :for="'targets_' + index + '_quantity'" class="block text-xs font-medium text-gray-700 mb-1">
                                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        :id="'targets_' + index + '_quantity'"
                                                        :name="'targets[' + index + '][quantity]'"
                                                        x-model.number="target.quantity"
                                                        :min="selectedService?.min_quantity || 1"
                                                        :max="selectedService?.max_quantity || null"
                                                        @keydown.arrow-up.prevent="stepQuantity(+1, index)"
                                                        @keydown.arrow-down.prevent="stepQuantity(-1, index)"
                                                        @wheel.prevent="stepQuantity($event.deltaY > 0 ? -1 : +1, index)"
                                                        @change="target.quantity = adjustQuantityToIncrement(target.quantity, index)"
                                                        @blur="$el.dispatchEvent(new Event('change'))"
                                                        :required="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                                        :disabled="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                                        :class="getTargetError(index, 'quantity') ? 'border-red-300' : 'border-gray-300'"
                                                        placeholder="{{ __('common.enter_quantity') }}"
                                                        class="no-spinner block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
                                                    <div class="absolute inset-y-0 right-0 flex flex-col justify-center">
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-t-md border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepQuantity(+1, index)"
                                                            aria-label="Increase quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 6a1 1 0 0 1 .707.293l4 4a1 1 0 1 1-1.414 1.414L10 8.414 6.707 11.707A1 1 0 0 1 5.293 10.293l4-4A1 1 0 0 1 10 6z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-b-md border border-t-0 border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepQuantity(-1, index)"
                                                            aria-label="Decrease quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 14a1 1 0 0 1-.707-.293l-4-4a1 1 0 1 1 1.414-1.414L10 11.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4A1 1 0 0 1 10 14z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p x-show="getTargetError(index, 'quantity')" class="mt-1 text-xs text-red-600" x-text="getTargetError(index, 'quantity')"></p>
                                            </div>
                                            <div class="flex items-center pt-6 flex-shrink-0">
                                                <button
                                                    type="button"
                                                    @click="removeTargetRow(index)"
                                                    x-show="targets.length > 1"
                                                    class="new-order-icon-x"
                                                    aria-label="{{ __('Remove') }}"
                                                    title="{{ __('Remove') }}">
                                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                @error('targets')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            </template>
                            <!-- Dripfeed Toggle Switch (only when service has dripfeed enabled) -->
                            <div class="mb-6" x-show="selectedService?.dripfeed_enabled === true">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-md border border-gray-200 mb-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-900">{{ __('Enable Dripfeed') }}</label>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Configure dripfeed settings to deliver orders gradually over time') }}</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            x-model="dripfeedEnabled"
                                            class="sr-only peer"
                                        >
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>
                                <!-- Hidden input to ensure value is always submitted -->
                                <input type="hidden" name="dripfeed_enabled" :value="dripfeedEnabled ? '1' : '0'">
                            </div>

                            <!-- Dripfeed Fields (only when service has dripfeed enabled AND toggle is ON) -->
                            <div class="mb-6" x-show="selectedService?.dripfeed_enabled === true && dripfeedEnabled === true">
                                <div class="p-4 bg-blue-50 rounded-md border border-blue-200 mb-4">
                                    <h3 class="text-sm font-medium text-blue-900 mb-3">{{ __('Dripfeed Settings') }}</h3>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- Dripfeed Quantity -->
                                        <div>
                                            <label for="dripfeed_quantity" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Quantity per Step') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                id="dripfeed_quantity"
                                                name="dripfeed_quantity"
                                                x-model.number="dripfeedQuantity"
                                                :min="1"
                                                :max="getTotalQuantity()"
                                                :required="dripfeedEnabled"
                                                @input="validateDripfeedQuantity"
                                                :class="dripfeedQuantityError ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                                                class="block w-full rounded-md shadow-sm sm:text-sm">
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Amount to deliver per drip step') }}
                                                <span x-show="getTotalQuantity() > 0">
                                                    ({{ __('Max') }}: <span x-text="getTotalQuantity()"></span>)
                                                </span>
                                            </p>
                                            <p x-show="dripfeedQuantityError" class="mt-1 text-sm text-red-600" x-text="dripfeedQuantityError"></p>
                                            @error('dripfeed_quantity')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Dripfeed Interval -->
                                        <div>
                                            <label for="dripfeed_interval" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Interval') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                id="dripfeed_interval"
                                                name="dripfeed_interval"
                                                x-model.number="dripfeedInterval"
                                                min="1"
                                                :required="dripfeedEnabled"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Interval value') }}
                                            </p>
                                            @error('dripfeed_interval')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Dripfeed Interval Unit -->
                                        <div>
                                            <label for="dripfeed_interval_unit" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Interval Unit') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                id="dripfeed_interval_unit"
                                                name="dripfeed_interval_unit"
                                                x-model="dripfeedIntervalUnit"
                                                :required="dripfeedEnabled"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">{{ __('Select unit') }}</option>
                                                <option value="minutes">{{ __('Minutes') }}</option>
                                                <option value="hours">{{ __('Hours') }}</option>
                                                <option value="days">{{ __('Days') }}</option>
                                            </select>
                                            @error('dripfeed_interval_unit')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6" x-show="selectedService?.speed_limit_enabled === true" x-cloak>
                                <input type="hidden" name="speed_tier" :value="speedTier">
                                @error('speed_tier')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Compact charge summary (no big total card) -->
                            <div class="new-order-charge-summary"
                                 x-show="selectedService && (selectedService?.hide_quantity === true || (selectedService?.service_type === 'custom_comments' ? commentsCount > 0 : getTotalQuantity() > 0))"
                                 x-cloak>
                                <div>
                                    <div class="label">{{ __('Total') }}</div>
                                    <div class="text-xs" style="color: var(--text3); margin-top: 2px;"
                                         x-show="selectedService?.hide_quantity !== true">
                                        <span x-show="selectedService?.service_type === 'custom_comments'">
                                            <span x-text="commentsCount || 0"></span> × $<span x-text="formatMoney(chargePerComment || 0)"></span>
                                        </span>
                                        <span x-show="selectedService?.service_type !== 'custom_comments'">
                                            <span x-text="getTotalQuantity()"></span> × $<span x-text="formatMoney(selectedService?.rate_per_1000 || 0)"></span> / 1000
                                        </span>
                                    </div>
                                </div>
                                <div class="amount">
                                    $<span x-text="formatMoney(displayOrderTotalAmount)"></span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="new-order-actions">
                                <button
                                    type="submit"
                                    :disabled="submitting"
                                    class="new-order-submit">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                    <span x-show="!submitting">{{ __('home.place_order') }}</span>
                                    <span x-show="submitting">{{ __('Creating...') }}</span>
                                </button>
                                <a href="{{ route('client.orders.index') }}" class="new-order-cancel">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                        </form>

                        <!-- Multi-Service Order Form -->
                        <form method="POST" action="{{ route('client.orders.multi-store') }}"
                              x-show="orderType === 'multi'"
                              @submit.prevent="submitMultiForm">
                            @csrf
                            <input type="hidden" name="form_type" value="multi">

                            <!-- Category (platform) -->
                            <div class="mb-6">
                                <label for="multi_category_id" class="new-order-lab">
                                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                                    {{ __('common.social_media') }} <span class="text-red-400">*</span>
                                </label>
                                <select
                                    id="multi_category_id"
                                    name="category_id"
                                    x-model="multiCategoryId"
                                    @change="loadMultiServices"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('common.select_platform') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $preselectedCategoryId ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Link (Single) -->
                            <div class="mb-6">
                                <label for="multi_link" class="new-order-lab">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                    {{ __('Link') }} <span class="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="multi_link"
                                    name="link"
                                    x-model="multiLink"
                                    @input="multiLinkValid = validateLink(multiLink, multiLinkType)"
                                    placeholder="{{ __('common.paste_link') }}"
                                    :class="multiLinkValid ? 'border-gray-300' : 'border-red-300'"
                                    :placeholder="linkPlaceholder(multiLinkType)"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p x-show="!multiLinkValid && multiLink" class="mt-1 text-sm text-red-600" x-text="linkErrorForType(multiLinkType)"></p>
                                @error('link')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Services Multi-Select -->
                            <div class="mb-6">
                                <div class="new-order-section-head">
                                    <span class="new-order-section-title">
                                        <i class="fa-solid fa-gear" aria-hidden="true"></i>
                                        {{ __('Select Services') }} <span class="text-red-400">*</span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="addMultiServiceRow"
                                        class="new-order-add-btn"
                                        title="{{ __('Add Service') }}">
                                        <span class="plus" aria-hidden="true">+</span>
                                        <span>{{ __('Add Service') }}</span>
                                    </button>
                                </div>
                                <div x-show="multiLoading" class="text-sm text-gray-500 mb-2">
                                    {{ __('Loading services...') }}
                                </div>
                                <div x-show="!multiLoading && multiServices.length === 0 && multiCategoryId" class="text-sm text-gray-500 mb-2">
                                    {{ __('No active services found for this category.') }}
                                </div>
                                <div class="space-y-3" x-show="!multiLoading && multiServices.length > 0">
                                    <template x-for="(serviceRow, index) in multiSelectedServices" :key="index">
                                        <div class="flex flex-col gap-3 p-3 rounded-md border-2"
                                             :class="getServiceError(index, 'service_id') || getServiceError(index, 'quantity') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50'">
                                            <div class="flex gap-3 items-start flex-wrap">
                                                <div class="flex-1 min-w-[200px]">
                                                    <label :for="'multi_service_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                        {{ __('Service') }} <span class="text-red-500">*</span>
                                                    </label>
                                                    <select
                                                        :id="'multi_service_' + index"
                                                        :name="'services[' + index + '][service_id]'"
                                                        x-model="serviceRow.service_id"
                                                        @change="updateMultiServiceInfo(index)"
                                                        required
                                                        :class="getServiceError(index, 'service_id') ? 'border-red-300' : 'border-gray-300'"
                                                        class="block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">{{ __('Select a service') }}</option>
                                                        <template x-for="service in getFilteredServicesForRow(index)" :key="'multi-service-' + service.id">
                                                            <option :value="service.id" x-text="'ID' + service.id + ' - ' + service.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="w-32">
                                                    <label :for="'multi_qty_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                        {{ __('Quantity') }} <span class="text-red-500" x-show="!isMultiRowCustomComments(index)">*</span>
                                                    </label>
                                                    <template x-if="!isMultiRowCustomComments(index)">
                                                        <div class="relative">
                                                            <input
                                                                type="number"
                                                                :id="'multi_qty_' + index"
                                                                :name="'services[' + index + '][quantity]'"
                                                                x-model.number="serviceRow.quantity"
                                                                :min="serviceRow.min_quantity || 1"
                                                                :max="serviceRow.max_quantity || null"
                                                                @keydown.arrow-up.prevent="stepMultiQuantity(+1, index)"
                                                                @keydown.arrow-down.prevent="stepMultiQuantity(-1, index)"
                                                                @wheel.prevent="stepMultiQuantity($event.deltaY > 0 ? -1 : +1, index)"
                                                                @change="serviceRow.quantity = adjustMultiQuantityToIncrement(serviceRow.quantity, index)"
                                                                @blur="$el.dispatchEvent(new Event('change'))"
                                                                required
                                                                :class="getServiceError(index, 'quantity') ? 'border-red-300' : 'border-gray-300'"
                                                                class="no-spinner block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
                                                            <div class="absolute inset-y-0 right-0 flex flex-col justify-center">
                                                                <button type="button" class="h-5 w-6 rounded-t-md border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none" @mousedown.prevent @click="stepMultiQuantity(+1, index)" aria-label="Increase"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M10 6a1 1 0 0 1 .707.293l4 4a1 1 0 1 1-1.414 1.414L10 8.414 6.707 11.707A1 1 0 0 1 5.293 10.293l4-4A1 1 0 0 1 10 6z" clip-rule="evenodd"/></svg></button>
                                                                <button type="button" class="h-5 w-6 rounded-b-md border border-t-0 border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none" @mousedown.prevent @click="stepMultiQuantity(-1, index)" aria-label="Decrease"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M10 14a1 1 0 0 1-.707-.293l-4-4a1 1 0 1 1 1.414-1.414L10 11.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4A1 1 0 0 1 10 14z" clip-rule="evenodd"/></svg></button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="isMultiRowCustomComments(index)">
                                                        <div class="py-2 text-sm text-gray-600">
                                                            {{ __('From comments') }}: <span x-text="getRowCommentsLineCount(index)"></span>
                                                            <input type="hidden" :name="'services[' + index + '][quantity]'" :value="getRowCommentsLineCount(index)">
                                                        </div>
                                                    </template>
                                                    <p x-show="getServiceError(index, 'quantity')" class="mt-1 text-xs text-red-600" x-text="getServiceError(index, 'quantity')"></p>
                                                </div>
                                                <div class="flex items-center justify-center pt-6 flex-shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="removeMultiServiceRow(index)"
                                                        x-show="multiSelectedServices.length > 1"
                                                        class="new-order-icon-x"
                                                        aria-label="{{ __('Remove') }}"
                                                        title="{{ __('Remove') }}">
                                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                                <div class="w-36 pt-6" x-show="serviceRow.service_id">
                                                    <div class="text-xs text-gray-600">
                                                        <div>{{ __('Price') }}: $<span x-text="formatMoney(serviceRow.rate_per_1000 || 0)"></span> / 1000</div>
                                                        <div>{{ __('Charge') }}: $<span x-text="formatMoney(calculateMultiServiceCharge(index))"></span></div>
                                                        <div class="mt-1 text-gray-500" x-show="!isMultiRowCustomComments(index)">
                                                            <span>{{ __('Min') }}: <span x-text="serviceRow.min_quantity || 1"></span></span>
                                                            <span x-show="serviceRow.max_quantity"> | {{ __('Max') }}: <span x-text="serviceRow.max_quantity"></span></span>
                                                            <span x-show="serviceRow.increment > 0"> | {{ __('Inc') }}: <span x-text="serviceRow.increment"></span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Per-service: Comments (custom_comments) -->
                                            <div x-show="isMultiRowCustomComments(index)" class="mt-2 pl-0">
                                                <label :for="'multi_comments_' + index" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Comments') }} <span class="text-red-500">*</span></label>
                                                <textarea
                                                    :id="'multi_comments_' + index"
                                                    :name="'services[' + index + '][comments]'"
                                                    x-model="serviceRow.comments"
                                                    rows="4"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    placeholder="{{ __('One comment per line') }}"></textarea>
                                                <p class="mt-1 text-xs text-gray-500">{{ __('One comment per line. Quantity = number of comments.') }}</p>
                                            </div>
                                            <!-- Per-service: Star Rating (App review) -->
                                            <div x-show="isMultiRowNeedsStarRating(index)" class="mt-2 pl-0">
                                                <label :for="'multi_star_' + index" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Star Rating') }} <span class="text-red-500">*</span></label>
                                                <select
                                                    :id="'multi_star_' + index"
                                                    :name="'services[' + index + '][star_rating]'"
                                                    x-model.number="serviceRow.star_rating"
                                                    :required="isMultiRowNeedsStarRating(index)"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm max-w-xs">
                                                    <option value="5">5 {{ __('stars') }} ({{ __('default') }})</option>
                                                    <option value="4">4 {{ __('stars') }}</option>
                                                    <option value="3">3 {{ __('stars') }}</option>
                                                    <option value="2">2 {{ __('stars') }}</option>
                                                    <option value="1">1 {{ __('star') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                @error('services')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Compact total summary (multi) -->
                            <div class="new-order-charge-summary" x-show="multiSelectedServices.length > 0" x-cloak>
                                <div>
                                    <div class="label">{{ __('Total') }}</div>
                                    <div class="text-xs" style="color: var(--text3); margin-top: 2px;">
                                        <span x-text="multiSelectedServices.length"></span> {{ __('service(s) selected') }}
                                    </div>
                                </div>
                                <div class="amount">
                                    $<span x-text="formatMoney(calculateMultiTotalCharge())"></span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="new-order-actions">
                                <button
                                    type="submit"
                                    :disabled="submitting || multiSelectedServices.length === 0"
                                    class="new-order-submit">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                    <span x-show="!submitting">{{ __('common.place_orders') }}</span>
                                    <span x-show="submitting">{{ __('Creating...') }}</span>
                                </button>
                                <a href="{{ route('client.orders.index') }}" class="new-order-cancel">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                        </form>
                    </div>
        </div>
    </div>

    <script>
        function orderForm() {
            return {
                orderType: @json(old('form_type') === 'multi' ? 'multi' : 'single'),
                categoryIdsWithTargetType: @js($categoryIdsWithTargetType ?? []),
                categoryLinkTypes: @js($categoryLinkTypes ?? []),
                /** Categories list passed to the custom dropdown (id, name, icon, link_driver). */
                categoriesData: @js(collect($categories)->map(fn($c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'icon' => $c->icon,
                    'link_driver' => $c->link_driver,
                ])->values()->all()),
                get selectedCategory() {
                    if (!this.categoryId) return null;
                    return (this.categoriesData || []).find(c => c.id == this.categoryId) || null;
                },
                /**
                 * Render a category icon as inline HTML.
                 * Supports raw <svg>, data: URIs, FontAwesome class strings, and a fallback initial.
                 */
                renderCategoryIcon(cat) {
                    if (!cat) return '';
                    const icon = (cat.icon || '').trim();
                    if (!icon) {
                        const initial = (cat.name || '?').charAt(0).toUpperCase();
                        return '<span class="fallback">' + initial + '</span>';
                    }
                    if (icon.startsWith('<svg')) return icon;
                    if (icon.startsWith('data:')) {
                        const safe = icon.replace(/"/g, '&quot;');
                        return '<img src="' + safe + '" alt="" />';
                    }
                    if (/^(fas|far|fab|fal|fad)\s/.test(icon)) {
                        return '<i class="' + icon + '"></i>';
                    }
                    // Plain text / emoji fallback
                    const text = icon.replace(/</g, '&lt;');
                    return '<span class="fallback">' + text + '</span>';
                },
                get categoryHasTargetType() {
                    if (!this.categoryId) return false;
                    const id = Number(this.categoryId);
                    return Array.isArray(this.categoryIdsWithTargetType) && (this.categoryIdsWithTargetType.includes(id) || this.categoryIdsWithTargetType.includes(String(this.categoryId)));
                },
                get linkType() {
                    const id = this.categoryId;
                    return (id && (this.categoryLinkTypes[id] ?? this.categoryLinkTypes[Number(id)])) || 'generic';
                },
                get multiLinkType() {
                    const id = this.multiCategoryId;
                    return (id && (this.categoryLinkTypes[id] ?? this.categoryLinkTypes[Number(id)])) || 'generic';
                },
                get multiNeedsCommentText() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && (s.needs_comment_text === true || s.needs_comment_text === 1);
                    });
                },
                get multiNeedsStarRating() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && (s.needs_star_rating === true || s.needs_star_rating === 1);
                    });
                },
                get multiNeedsComments() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && s.service_type === 'custom_comments';
                    });
                },
                get multiCommentsLineCount() {
                    if (!this.multiComments || typeof this.multiComments !== 'string') return 0;
                    return this.multiComments.split('\n').map(l => l.trim()).filter(l => l !== '').length;
                },
                getRowCommentsLineCount(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row || !row.comments || typeof row.comments !== 'string') return 0;
                    return row.comments.split('\n').map(l => l.trim()).filter(l => l !== '').length;
                },
                isMultiRowCustomComments(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row?.service_id) return false;
                    const s = (this.multiServices || []).find(x => x.id == row.service_id);
                    return s && s.service_type === 'custom_comments';
                },
                isMultiRowNeedsStarRating(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row?.service_id) return false;
                    const s = (this.multiServices || []).find(x => x.id == row.service_id);
                    return s && (s.needs_star_rating === true || s.needs_star_rating === 1);
                },
                get displayOrderTotalAmount() {
                    if (!this.selectedService) {
                        return '0.00';
                    }
                    if (this.selectedService.service_type === 'custom_comments') {
                        return this.commentsTotalCharge || 0;
                    }
                    return this.calculateCharge() || 0;
                },

                /**
                 * Safely format a money value for display.
                 * - Avoids float artifacts (0.11220000000000001 → 0.11)
                 * - Whole numbers and 2-decimal values: 2 decimals (e.g. $0.60, $5.00)
                 * - Smaller values that need more precision: up to 4 decimals, trimmed
                 */
                formatMoney(value) {
                    const n = Number(value);
                    if (!Number.isFinite(n)) return '0.00';
                    // Round to 4 decimals first to drop float artifacts, then format
                    const rounded4 = Math.round(n * 10000) / 10000;
                    const rounded2 = Math.round(n * 100) / 100;
                    // If value is "clean" at 2 decimals (no precision lost), show 2 decimals.
                    if (Math.abs(rounded4 - rounded2) < 1e-9) {
                        return rounded2.toFixed(2);
                    }
                    // Otherwise show up to 4 decimals, but always at least 2
                    let s = rounded4.toFixed(4);
                    // Trim trailing zeros but keep at least 2 decimals
                    s = s.replace(/0+$/, '').replace(/\.$/, '');
                    if (!s.includes('.')) s += '.00';
                    else if (s.split('.')[1].length < 2) s += '0';
                    return s;
                },

                linkErrorForType(type) {
                    const m = {
                        telegram: @json(__('common.link_error_telegram')),
                        youtube: @json(__('common.link_error_youtube')),
                        app: @json(__('common.link_error_app')),
                        max: @json(__('common.link_error_max')),
                        whatsapp: @json(__('common.link_error_whatsapp')),
                        tiktok: @json(__('common.link_error_generic')),
                        instagram: @json(__('common.link_error_generic')),
                        facebook: @json(__('common.link_error_generic')),
                        url: @json(__('common.link_error_generic')),
                        generic: @json(__('common.link_error_generic')),
                    };
                    return m[type] || m.generic;
                },
                get serviceGroups() {
                    const list = Array.isArray(this.services) ? this.services : [];
                    const grouped = {};
                    const order = [];
                    list.forEach(service => {
                        const group = service.dropdown_group || 'Other';
                        const label = service.dropdown_label || group;
                        const priority = service.dropdown_priority ?? 99;
                        if (!grouped[group]) {
                            grouped[group] = { label: label, services: [], priority: priority };
                            order.push(group);
                        }
                        grouped[group].services.push(service);
                    });
                    order.sort((a, b) => (grouped[a].priority) - (grouped[b].priority));
                    return order.map(g => grouped[g]);
                },

                /** Filter service groups for the custom dropdown search box. */
                filteredServiceGroups(query) {
                    const q = (query || '').trim().toLowerCase().replace(/^#/, '');
                    if (!q) return this.serviceGroups;
                    return this.serviceGroups
                        .map(group => ({
                            label: group.label,
                            services: group.services.filter(s => {
                                const name = (s.name || '').toLowerCase();
                                const id = String(s.id);
                                const desc = (s.description || '').toLowerCase();
                                return name.includes(q) || id.includes(q) || desc.includes(q);
                            }),
                        }))
                        .filter(g => g.services.length > 0);
                },

                // Single service order data
                categoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                targetType: '{{ old('target_type', $preselectedTargetType ?? '') }}',
                serviceId: '{{ old('service_id', $preselectedServiceId ?? '') }}',
                services: [],
                selectedService: null,
                serviceSearch: '',
                targets: @js(old('targets') ?: [['link' => '', 'quantity' => 1000]]),
                loading: false,
                submitting: false,
                // Custom comments
                comments: @json(old('comments', '')),
                commentsLink: @json(old('link', '')),
                commentsLinkValid: true,
                commentsCount: 0,
                commentsTotalCharge: 0,
                chargePerComment: 0,
                commentsCountError: '',
                // Dripfeed
                dripfeedEnabled: {{ old('dripfeed_enabled', 'false') === 'true' ? 'true' : 'false' }},
                dripfeedQuantity: @json(old('dripfeed_quantity')),
                dripfeedInterval: @json(old('dripfeed_interval')),
                dripfeedIntervalUnit: @json(old('dripfeed_interval_unit')),
                dripfeedQuantityError: '',
                // Speed Tier (default 'fast' when service has speed enabled, set in updateServiceInfo)
                speedTier: '{{ old('speed_tier', 'fast') }}',
                // YouTube combo custom comment / App custom review
                commentText: @json(old('comment_text', '')),
                commentTextError: '',
                starRating: {{ old('star_rating', 5) }},
                // Invite Subscribers (2-link service)
                inviteSourceLink: @json(old('link_2', '')),
                inviteTargetLink: @json(old('targets.0.link', '')),
                inviteSourceLinkValid: true,
                inviteTargetLinkValid: true,
                inviteQuantity: {{ old('targets.0.quantity', 1) }},
                premiumFolderLink: @json(old('targets.0.link', '')),
                premiumFolderLinkValid: true,
                premiumFolderDurationDays: {{ (int) old('duration_days', 30) }},
                // Multi-service order data
                multiCategoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                multiLink: @json(old('link', '')),
                multiLinkValid: true,
                multiCommentText: @json(old('comment_text', '')),
                multiComments: @json(old('comments', '')),
                multiStarRating: {{ old('star_rating', 5) }},
                multiServices: [],
                validationErrors: @json($errors->messages()),
                multiSelectedServices: @js(
                    !empty(old('services'))
                        ? collect(old('services'))->map(fn($s) => [
                            'service_id' => (string)($s['service_id'] ?? ''),
                            'target_type' => '',
                            'quantity' => (int)($s['quantity'] ?? 1),
                            'min_quantity' => 1,
                            'max_quantity' => null,
                            'increment' => 0,
                            'rate_per_1000' => 0,
                            'comments' => (string)($s['comments'] ?? ''),
                            'star_rating' => (int)($s['star_rating'] ?? 5),
                        ])->values()->all()
                        : [['service_id' => '', 'target_type' => '', 'quantity' => 1000, 'min_quantity' => 1, 'max_quantity' => null, 'increment' => 0, 'rate_per_1000' => 0, 'comments' => '', 'star_rating' => 5]]
                ),
                multiLoading: false,

                init() {
                    // Ensure services is always an array
                    if (!Array.isArray(this.services)) {
                        this.services = [];
                    }
                    if (!Array.isArray(this.multiServices)) {
                        this.multiServices = [];
                    }
                    if (!Array.isArray(this.multiSelectedServices)) {
                        this.multiSelectedServices = [];
                    }
                    if (this.categoryId) {
                        this.loadServices().then(() => {
                            if (this.serviceId && this.services.length > 0) {
                                this.$nextTick(() => {
                                    this.updateServiceInfo();
                                    // Ensure all targets have min_quantity set
                                    if (this.selectedService) {
                                        const minQty = this.selectedService.min_quantity || 1;
                                        this.targets.forEach(target => {
                                            if (!target.quantity || target.quantity < minQty) {
                                                target.quantity = minQty;
                                            }
                                        });
                                        if (this.selectedService.service_type === 'custom_comments' && this.comments) {
                                            this.calculateCommentsTotal();
                                        }
                                    }
                                });
                            }
                        });
                    }
                    if (this.multiCategoryId) {
                        this.loadMultiServices();
                    }
                    // Ensure at least one target row exists
                    if (!this.targets || this.targets.length === 0) {
                        this.targets = [{ link: '', quantity: 1000, linkValid: true }];
                    } else {
                        this.targets.forEach((target, index) => {
                            if (target.linkValid === undefined) {
                                target.linkValid = !target.link || this.validateLink(target.link);
                            }
                            if (!target.quantity || target.quantity < 1) {
                                target.quantity = 1000;
                            }
                        });
                    }
                    // Validate multi link on init
                    if (this.multiLink) {
                        this.multiLinkValid = this.validateLink(this.multiLink, this.multiLinkType);
                    }
                    // Validate comments link on init
                    if (this.commentsLink) {
                        this.commentsLinkValid = this.validateLink(this.commentsLink);
                    }
                    // Calculate comments total on init if comments exist
                    if (this.comments) {
                        this.$nextTick(() => {
                            this.calculateCommentsTotal();
                        });
                    }
                },

                onCategoryChange() {},

                validateTelegramLink(link) {
                    if (!link || link.trim() === '') return true;
                    const regex = /^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{3,32}$/i;
                    return regex.test(link.trim());
                },
                validateYoutubeLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=[A-Za-z0-9_\-]+(\&[^&\s#]+)*|shorts\/[A-Za-z0-9_\-]+|embed\/[A-Za-z0-9_\-]+|live\/[A-Za-z0-9_\-]+)|youtube\.com\/@[A-Za-z0-9_.\-]+|youtube\.com\/channel\/UC[A-Za-z0-9_\-]+|youtube\.com\/c\/[A-Za-z0-9_.\-]+|youtu\.be\/[A-Za-z0-9_\-]+(\?[^\s#]*)?)/i.test(v);
                },
                validateAppLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    const playStore = /^(https?:\/\/)?(www\.)?play\.google\.com\/store\/apps\/details\?id=[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+/i;
                    const appStore = /^(https?:\/\/)?(www\.)?apps\.apple\.com\/[a-z]{2}\/app\/[^/]+\/id\d+/i;
                    return playStore.test(v) || appStore.test(v);
                },
                validateMaxLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    if (/^@[a-zA-Z0-9_]{3,64}$/.test(v)) return true;
                    if (/^[a-zA-Z0-9_]{3,64}$/.test(v)) return true;
                    return /^(https?:\/\/)?(www\.)?(max\.ru\/[^\s]+|maxapp\.ru\/[^\s]+|web\.maxapp\.ru\/[^\s]+)/i.test(v);
                },
                validateWhatsAppLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(wa\.me\/\d+[?\d\w\-=]*|chat\.whatsapp\.com\/[A-Za-z0-9_-]+|api\.whatsapp\.com\/send\?[^\s]+)/i.test(v);
                },
                validateTiktokLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(tiktok\.com\/|vm\.tiktok\.com\/|vt\.tiktok\.com\/)[^\s]+/i.test(v);
                },
                validateInstagramLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(instagram\.com\/|instagr\.am\/)[^\s]+/i.test(v);
                },
                validateFacebookLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.|m\.)?(facebook\.com\/|fb\.com\/|fb\.watch\/|fb\.me\/)[^\s]+/i.test(v);
                },
                validateGenericLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return v.length >= 5 && (/^https?:\/\//i.test(v) || /^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}/.test(v));
                },
                validateLink(link, type) {
                    const t = type || this.linkType;
                    if (t === 'youtube') return this.validateYoutubeLink(link);
                    if (t === 'app') return this.validateAppLink(link);
                    if (t === 'max') return this.validateMaxLink(link);
                    if (t === 'whatsapp') return this.validateWhatsAppLink(link);
                    if (t === 'tiktok') return this.validateTiktokLink(link);
                    if (t === 'instagram') return this.validateInstagramLink(link);
                    if (t === 'facebook') return this.validateFacebookLink(link);
                    if (t === 'url' || t === 'generic') return this.validateGenericLink(link);
                    return this.validateTelegramLink(link);
                },
                getFieldError(key) {
                    const arr = this.validationErrors?.[key];
                    return Array.isArray(arr) && arr[0] ? arr[0] : null;
                },
                getTargetError(index, field) {
                    return this.getFieldError(`targets.${index}.${field}`);
                },
                getServiceError(index, field) {
                    return this.getFieldError(`services.${index}.${field}`);
                },
                /**
                 * Per-service link placeholder + accepted-link helper text.
                 * Keyed by Telegram template_key. Easy to extend for new services.
                 */
                serviceLinkProfiles: {
                    // Bot services
                    bot_start:                              { ph: 'https://t.me/botusername',                   label: 'Bot',     hint: @json(__('Accepted: bot link')) },
                    bot_start_referral:                     { ph: 'https://t.me/botusername?start=ref123',      label: 'Bot',     hint: @json(__('Accepted: bot start link with referral parameter')) },
                    bot_start_from_search:                  { ph: 'https://t.me/botusername',                   label: 'Bot',     hint: @json(__('Accepted: bot link')) },
                    premium_bot_start:                      { ph: 'https://t.me/botusername',                   label: 'Bot',     hint: @json(__('Accepted: bot link')) },
                    premium_bot_start_referral:             { ph: 'https://t.me/botusername?start=ref123',      label: 'Bot',     hint: @json(__('Accepted: bot start link with referral parameter')) },

                    // Channel / group subscribe
                    channel_subscribe:                                       { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel link or invite link (https://t.me/+abc... or /joinchat/...)')) },
                    channel_subscribe_private_public:                        { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel link or invite link (https://t.me/+abc... or /joinchat/...)')) },
                    channel_subscribe_daily:                                 { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel link or invite link (https://t.me/+abc... or /joinchat/...)')) },
                    real_channel_subscribe_from_search:                      { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel link only')) },
                    subscribe_by_geo_account:                                { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel/group link or invite link')) },
                    subscribe_daily_by_geo_account:                          { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel/group link or invite link')) },
                    group_join:                                              { ph: 'https://t.me/groupusername',    label: 'Group',   hint: @json(__('Accepted: public group link or invite link')) },
                    premium_daily_subscribe_public_private_group_channel:    { ph: 'https://t.me/channelusername',  label: 'Channel', hint: @json(__('Accepted: public channel link or invite link')) },

                    // Posts (views / reactions / repost / comment reaction / poll)
                    channel_post_views:             { ph: 'https://t.me/channelusername/123', label: 'Post', hint: @json(__('Accepted: post link (e.g. https://t.me/channel/123)')) },
                    channel_post_reactions:         { ph: 'https://t.me/channelusername/123', label: 'Post', hint: @json(__('Accepted: post link (e.g. https://t.me/channel/123)')) },
                    channel_post_repost:            { ph: 'https://t.me/channelusername/123', label: 'Post', hint: @json(__('Accepted: post link (e.g. https://t.me/channel/123)')) },
                    channel_post_comment_reaction:  { ph: 'https://t.me/channelusername/123', label: 'Post', hint: @json(__('Accepted: post link (comment reactions will be applied)')) },
                    channel_poll:                   { ph: 'https://t.me/channelusername/123', label: 'Poll', hint: @json(__('Accepted: post link to a poll (e.g. https://t.me/channel/123)')) },

                    // Stories
                    story_repost:   { ph: 'https://t.me/username/s/1', label: 'Story', hint: @json(__('Accepted: story link (e.g. https://t.me/username/s/1)')) },
                    story_like:     { ph: 'https://t.me/username/s/1', label: 'Story', hint: @json(__('Accepted: story link (e.g. https://t.me/username/s/1)')) },

                    // Premium / boost
                    premium_boost:  { ph: 'https://t.me/channelusername?boost', label: 'Boost', hint: @json(__('Accepted: boost link (e.g. https://t.me/channelusername?boost)')) },

                    // Folder / add list
                    telegram_premium_folder: { ph: 'https://t.me/addlist/xxxxx', label: 'Folder', hint: @json(__('Accepted: folder link (e.g. https://t.me/addlist/xxxxx)')) },

                    // Invite (uses 2-link UI but keep example for safety)
                    invite_subscribers_from_other_channel: { ph: 'https://t.me/channelusername', label: 'Channel', hint: @json(__('Accepted: public channel link or invite link')) },
                },

                /**
                 * Resolve the active link "profile" for the current selected service.
                 * Falls back to a generic profile based on category linkType / target_type.
                 */
                linkProfile() {
                    const s = this.selectedService;
                    if (!s) return null;
                    // 1. Direct lookup by template_key
                    if (s.template_key && this.serviceLinkProfiles[s.template_key]) {
                        return this.serviceLinkProfiles[s.template_key];
                    }
                    // 2. Fallbacks for non-telegram categories
                    if (this.linkType === 'youtube') {
                        return { ph: @json(__('home.link_placeholder_youtube')), label: 'Video', hint: @json(__('Accepted: YouTube video URL')) };
                    }
                    if (this.linkType === 'app') {
                        return { ph: @json(__('home.link_placeholder_app')), label: 'App', hint: @json(__('Accepted: Google Play or App Store link')) };
                    }
                    if (this.linkType === 'max') {
                        return { ph: @json(__('home.link_placeholder_max')), label: 'MAX', hint: @json(__('Accepted: MAX link or @username')) };
                    }
                    // 3. Generic telegram fallback by target_type
                    const targetMap = {
                        bot:     { ph: 'https://t.me/botusername',     label: 'Bot',     hint: @json(__('Accepted: bot link')) },
                        channel: { ph: 'https://t.me/channelusername', label: 'Channel', hint: @json(__('Accepted: public channel link or invite link')) },
                        group:   { ph: 'https://t.me/groupusername',   label: 'Group',   hint: @json(__('Accepted: public group link or invite link')) },
                    };
                    if (s.target_type && targetMap[s.target_type]) return targetMap[s.target_type];
                    // 4. Last resort
                    return { ph: @json(__('home.link_placeholder_tg')), label: '', hint: '' };
                },

                linkPlaceholder(type) {
                    // Service-aware override
                    const profile = this.linkProfile();
                    if (profile && profile.ph) return profile.ph;
                    // Category-fallback (used by multi-form / pre-selection)
                    const t = type || this.linkType;
                    const placeholders = {
                        telegram: @json(__('home.link_placeholder_tg')),
                        youtube: @json(__('home.link_placeholder_youtube')),
                        app: @json(__('home.link_placeholder_app')),
                        max: @json(__('home.link_placeholder_max')),
                        whatsapp: @json(__('home.link_placeholder_tg')),
                        tiktok: @json(__('home.link_placeholder_tg')),
                        instagram: @json(__('home.link_placeholder_tg')),
                        facebook: @json(__('home.link_placeholder_tg')),
                        url: @json(__('home.link_placeholder_tg')),
                        generic: @json(__('home.link_placeholder_tg')),
                    };
                    return placeholders[t] || placeholders.generic;
                },
                /** Short label (Channel / Bot / Post / Story / Boost / Folder / Group / Poll) for the section pill. */
                linkTypeLabel() {
                    const profile = this.linkProfile();
                    return profile ? (profile.label || '') : '';
                },
                /** Detailed "Accepted: ..." helper text shown under the input. */
                linkAcceptedHint() {
                    const profile = this.linkProfile();
                    return profile ? (profile.hint || '') : '';
                },

                // Single service order methods
                async loadServices() {
                    if (!this.categoryId) {
                        this.services = [];
                        return Promise.resolve();
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`{{ route('client.orders.services.by-category') }}?category_id=${this.categoryId}`);
                        const data = await response.json();
                        this.services = Array.isArray(data) ? data : [];
                        // Clear selected service if not in filtered list
                        if (this.serviceId && !this.services.find(s => s.id == this.serviceId)) {
                            this.serviceId = '';
                            this.selectedService = null;
                        }
                    } catch (error) {
                        console.error('Error loading services:', error);
                        this.services = [];
                    } finally {
                        this.loading = false;
                    }
                },

                updateServiceInfo(forceResetQty = false) {
                    if (!this.serviceId) {
                        this.selectedService = null;
                        return;
                    }
                    this.selectedService = this.services.find(s => s.id == this.serviceId) || null;
                    // Quantity defaulting:
                    //   - on a user-initiated service change → always reset to 1000 (clamped to min/max)
                    //   - on init / silent updates → only fix invalid values (out of range or empty)
                    if (this.selectedService) {
                        const min = Number(this.selectedService.min_quantity || 1);
                        const max = this.selectedService.max_quantity != null ? Number(this.selectedService.max_quantity) : null;
                        let preferred = 1000;
                        if (max !== null && max < preferred) preferred = max;
                        if (min > preferred) preferred = min;
                        this.targets.forEach(target => {
                            const qty = parseInt(target.quantity) || 0;
                            if (forceResetQty) {
                                target.quantity = preferred;
                            } else if (qty <= 0) {
                                target.quantity = preferred;
                            } else if (qty < min) {
                                target.quantity = min;
                            } else if (max !== null && qty > max) {
                                target.quantity = max;
                            }
                        });
                        // Recalculate comments total if service changed and comments exist
                        if (this.comments && this.selectedService.service_type === 'custom_comments') {
                            this.$nextTick(() => {
                                this.calculateCommentsTotal();
                                this.validateCommentsCount();
                            });
                        }
                    }
                    // Reset dripfeed fields if service doesn't have dripfeed enabled
                    if (this.selectedService && !this.selectedService.dripfeed_enabled) {
                        this.dripfeedEnabled = false;
                        this.dripfeedQuantity = null;
                        this.dripfeedInterval = null;
                        this.dripfeedIntervalUnit = '';
                        this.dripfeedQuantityError = '';
                    }
                    if (this.selectedService) {
                        if (!this.selectedService.speed_limit_enabled) {
                            this.speedTier = 'normal';
                        } else {
                            const tierMode = this.selectedService.speed_limit_tier_mode === 'super_fast' ? 'super_fast' : 'fast';
                            this.speedTier = tierMode;
                        }
                    }
                    // Invite subscribers: sync invite quantity with min
                    if (this.selectedService?.template_key === 'invite_subscribers_from_other_channel') {
                        const minQty = this.selectedService.min_quantity || 1;
                        if (!this.inviteQuantity || this.inviteQuantity < minQty) {
                            this.inviteQuantity = minQty;
                        }
                    }
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        const opts = this.selectedService.duration_options || [30];
                        if (!opts.map(Number).includes(Number(this.premiumFolderDurationDays))) {
                            this.premiumFolderDurationDays = opts[0];
                        }
                        const existing = (this.targets[0] && this.targets[0].link) ? this.targets[0].link : this.premiumFolderLink;
                        this.premiumFolderLink = existing || '';
                        this.targets = [{ link: this.premiumFolderLink || '', quantity: 1, linkValid: true }];
                    }
                },

                calculateCommentsTotal() {
                    // Reset values first
                    this.commentsCount = 0;
                    this.commentsTotalCharge = 0;
                    this.chargePerComment = 0;

                    if (!this.selectedService || this.selectedService.service_type !== 'custom_comments') {
                        return;
                    }

                    if (!this.comments || typeof this.comments !== 'string' || this.comments.trim() === '') {
                        return;
                    }

                    // Parse comments: split by newline, trim, filter empty
                    const lines = this.comments.split('\n')
                        .map(line => line.trim())
                        .filter(line => line !== '');

                    this.commentsCount = lines.length;

                    if (this.commentsCount === 0) {
                        return;
                    }

                    // Charge per comment: rate_per_1000 / 1000 (speed tier does not change price)
                    const rate = parseFloat(this.selectedService.rate_per_1000) || 0;
                    this.chargePerComment = Math.round((rate / 1000) * 100) / 100;

                    // Total charge = charge per comment * number of comments
                    this.commentsTotalCharge = Math.round(this.chargePerComment * this.commentsCount * 100) / 100;
                },

                validateCommentsCount() {
                    this.commentsCountError = '';

                    if (!this.selectedService || this.selectedService.service_type !== 'custom_comments') {
                        return;
                    }

                    if (!this.comments || typeof this.comments !== 'string' || this.comments.trim() === '') {
                        if (this.selectedService.min_quantity > 0) {
                            this.commentsCountError = `{{ __('Minimum') }} ${this.selectedService.min_quantity} {{ __('comments required') }}.`;
                        }
                        return;
                    }

                    const minQuantity = this.selectedService.min_quantity || 1;

                    if (this.commentsCount < minQuantity) {
                        this.commentsCountError = `{{ __('Minimum') }} ${minQuantity} {{ __('comments required') }}. {{ __('You have entered') }} ${this.commentsCount} {{ __('comment(s)') }}.`;
                    }
                },

                // getTotalRowQuantity() {
                //     return this.commentsCount
                // },
                //
                // getCommentsTotalCharge() {
                //     return this.commentsTotalCharge
                // },

                validateDripfeedQuantity() {
                    this.dripfeedQuantityError = '';
                    if (!this.dripfeedEnabled || !this.dripfeedQuantity) {
                        return;
                    }
                    const totalQty = this.getTotalQuantity();
                    if (totalQty > 0 && this.dripfeedQuantity > totalQty) {
                        this.dripfeedQuantityError = '{{ __('Quantity per step cannot be greater than total order quantity') }}';
                    }
                },

                addTargetRow() {
                    let defaultQty = 1000;
                    if (this.selectedService) {
                        const min = Number(this.selectedService.min_quantity || 1);
                        const max = this.selectedService.max_quantity != null ? Number(this.selectedService.max_quantity) : null;
                        defaultQty = Math.max(min, defaultQty);
                        if (max !== null) defaultQty = Math.min(max, defaultQty);
                    }
                    this.targets.push({ link: '', quantity: defaultQty, linkValid: true });
                },

                removeTargetRow(index) {
                    if (this.targets.length > 1) {
                        this.targets.splice(index, 1);
                    }
                },

                getTotalQuantity() {
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        return 1;
                    }
                    if (this.selectedService?.template_key === 'invite_subscribers_from_other_channel') {
                        return parseInt(this.inviteQuantity, 10) || 0;
                    }
                    return this.targets.reduce((sum, target) => {
                        return sum + (parseInt(target.quantity) || 0);
                    }, 0);
                },

                getMinQuantity() {
                    if (!this.selectedService) {
                        return 1;
                    }
                    return this.selectedService.min_quantity || 1;
                },

                getStepValue() {
                    if (!this.selectedService) {
                        return 1;
                    }
                    const increment = this.selectedService.increment || 0;
                    return increment > 0 ? increment : 1;
                },

                adjustQuantityToIncrement(value, index) {
                    if (!this.selectedService) return value;

                    const min = Number(this.selectedService.min_quantity || 1);
                    const step = Number(this.selectedService.increment || 0);
                    const max = this.selectedService.max_quantity != null
                        ? Number(this.selectedService.max_quantity)
                        : null;

                    let v = Number(value);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        this.targets[index].quantity = v;
                        return v;
                    }

                    // Allowed values: min OR multiples of step (step, 2*step, 3*step...)
                    if (v <= min) {
                        v = min;
                    } else if (v < step) {
                        v = step; // between min and first step => go to step
                    } else {
                        // Round to nearest multiple of step
                        const remainder = v % step;
                        if (remainder === 0) {
                            // Already a multiple, keep it
                        } else {
                            // Round up to next multiple
                            v = Math.ceil(v / step) * step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    this.targets[index].quantity = v;
                    return v;
                },

                stepQuantity(direction, index) {
                    if (!this.selectedService) return;

                    const min = Number(this.selectedService.min_quantity || 1);
                    const step = Number(this.selectedService.increment || 0);
                    const max = this.selectedService.max_quantity != null
                        ? Number(this.selectedService.max_quantity)
                        : null;

                    let v = Number(this.targets[index].quantity ?? min);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        // No increment: just add/subtract 1
                        v = direction > 0 ? v + 1 : v - 1;
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        this.targets[index].quantity = v;
                        return;
                    }

                    // Sequence: min -> step -> 2*step -> 3*step -> ...
                    if (direction > 0) {
                        // Increment: 10 -> 50 -> 100 -> 150
                        if (v < min) {
                            v = min;
                        } else if (v === min) {
                            v = step;
                        } else {
                            v = v + step;
                        }
                    } else {
                        // Decrement: 150 -> 100 -> 50 -> 10
                        if (v <= min) {
                            v = min;
                        } else if (v <= step) {
                            v = min; // step -> min
                        } else {
                            v = v - step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    this.targets[index].quantity = v;
                    // Trigger change event to adjust to increment
                    this.$nextTick(() => {
                        this.targets[index].quantity = this.adjustQuantityToIncrement(v, index);
                    });
                },

                calculateCharge() {
                    if (!this.selectedService) return 0;
                    const rate = Number(this.selectedService.rate_per_1000) || 0;
                    if (this.selectedService.hide_quantity === true) {
                        return rate;
                    }
                    const totalQty = this.getTotalQuantity();
                    return ((totalQty * rate) / 1000);
                },

                submitForm(event) {
                    // For custom_comments, validate comments count and remove empty targets before submit
                    if (this.selectedService?.service_type === 'custom_comments') {
                        this.validateCommentsCount();
                        if (this.commentsCountError) {
                            this.submitting = false;
                            return;
                        }

                        const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.store') }}"]');
                        if (form) {
                            // Remove all targets inputs for custom_comments
                            const targetInputs = form.querySelectorAll('input[name^="targets["]');
                            targetInputs.forEach(input => {
                                input.remove();
                            });
                        }
                    }

                    // Validate dripfeed quantity before submit
                    if (this.dripfeedEnabled) {
                        this.validateDripfeedQuantity();
                        if (this.dripfeedQuantityError) {
                            this.submitting = false;
                            return;
                        }
                    }
                    // Validate invite_subscribers 2-link mode
                    if (this.selectedService?.template_key === 'invite_subscribers_from_other_channel') {
                        if (!this.inviteSourceLinkValid || !this.inviteTargetLinkValid || !this.inviteSourceLink?.trim() || !this.inviteTargetLink?.trim()) {
                            alert('{{ __('Please enter valid source and target Telegram links.') }}');
                            this.submitting = false;
                            return;
                        }
                    }
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        if (!this.premiumFolderLinkValid || !this.premiumFolderLink?.trim()) {
                            alert('{{ __('Please enter a valid Telegram channel or group link.') }}');
                            this.submitting = false;
                            return;
                        }
                    }
                    this.submitting = true;
                    const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.store') }}"]');
                    if (form) {
                        form.submit();
                    }
                },

                // Multi-service order methods
                async loadMultiServices() {
                    if (!this.multiCategoryId) {
                        this.multiServices = [];
                        this.multiSelectedServices = [];
                        return;
                    }

                    this.multiLoading = true;
                    try {
                        // Load all services for the category (no target_type filter at global level)
                        const url = `{{ route('client.orders.services.by-category') }}?category_id=${this.multiCategoryId}`;
                        const response = await fetch(url);
                        const data = await response.json();
                        this.multiServices = Array.isArray(data) ? data : [];
                        // Clear selected services only if no old input (validation redirect keeps selections)
                        const hasOldSelections = Array.isArray(this.multiSelectedServices) &&
                            this.multiSelectedServices.some(row => row.service_id);
                        if (!hasOldSelections) {
                            this.multiSelectedServices = [{ service_id: '', target_type: '', quantity: 1000, min_quantity: 1, max_quantity: null, increment: 0, rate_per_1000: 0 }];
                        } else {
                            // Populate service metadata for old selections
                            this.multiSelectedServices.forEach((row, i) => this.updateMultiServiceInfo(i));
                        }
                    } catch (error) {
                        console.error('Error loading services:', error);
                        this.multiServices = [];
                    } finally {
                        this.multiLoading = false;
                    }
                },

                addMultiServiceRow() {
                    this.multiSelectedServices.push({
                        service_id: '',
                        target_type: '',
                        quantity: 1000,
                        min_quantity: 1,
                        max_quantity: null,
                        increment: 0,
                        rate_per_1000: 0,
                        comments: '',
                        star_rating: 5,
                    });
                },

                removeMultiServiceRow(index) {
                    if (this.multiSelectedServices.length > 1) {
                        this.multiSelectedServices.splice(index, 1);
                    }
                },

                getFilteredServicesForRow(index) {
                    return Array.isArray(this.multiServices) ? this.multiServices : [];
                },

                updateMultiServiceInfo(index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow.service_id) {
                        return;
                    }
                    const service = this.multiServices.find(s => s.id == serviceRow.service_id);
                    if (service) {
                        serviceRow.min_quantity = service.min_quantity || 1;
                        serviceRow.max_quantity = service.max_quantity || null;
                        serviceRow.increment = service.increment || 0;
                        serviceRow.rate_per_1000 = service.rate_per_1000 || 0;
                        const min = Number(service.min_quantity || 1);
                        const max = service.max_quantity != null ? Number(service.max_quantity) : null;
                        let preferred = 1000;
                        preferred = Math.max(min, preferred);
                        if (max !== null) preferred = Math.min(max, preferred);
                        const currentQty = parseInt(serviceRow.quantity) || 0;
                        if (currentQty <= 0) {
                            serviceRow.quantity = preferred;
                        } else if (currentQty < min) {
                            serviceRow.quantity = min;
                        } else if (max !== null && currentQty > max) {
                            serviceRow.quantity = max;
                        }
                        if (service.service_type === 'custom_comments' && serviceRow.comments === undefined) {
                            serviceRow.comments = serviceRow.comments ?? '';
                        }
                        if ((service.needs_star_rating === true || service.needs_star_rating === 1) && serviceRow.star_rating === undefined) {
                            serviceRow.star_rating = serviceRow.star_rating ?? 5;
                        }
                    }
                },

                adjustMultiQuantityToIncrement(value, index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return value;

                    const min = Number(serviceRow.min_quantity || 1);
                    const step = Number(serviceRow.increment || 0);
                    const max = serviceRow.max_quantity != null ? Number(serviceRow.max_quantity) : null;

                    let v = Number(value);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        serviceRow.quantity = v;
                        return v;
                    }

                    // Allowed values: min OR multiples of step (step, 2*step, 3*step...)
                    if (v <= min) {
                        v = min;
                    } else if (v < step) {
                        v = step; // between min and first step => go to step
                    } else {
                        // Round to nearest multiple of step
                        const remainder = v % step;
                        if (remainder === 0) {
                            // Already a multiple, keep it
                        } else {
                            // Round up to next multiple
                            v = Math.ceil(v / step) * step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    serviceRow.quantity = v;
                    return v;
                },

                stepMultiQuantity(direction, index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return;

                    const min = Number(serviceRow.min_quantity || 1);
                    const step = Number(serviceRow.increment || 0);
                    const max = serviceRow.max_quantity != null ? Number(serviceRow.max_quantity) : null;

                    let v = Number(serviceRow.quantity ?? min);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        // No increment: just add/subtract 1
                        v = direction > 0 ? v + 1 : v - 1;
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        serviceRow.quantity = v;
                        return;
                    }

                    // Sequence: min -> step -> 2*step -> 3*step -> ...
                    if (direction > 0) {
                        // Increment: 10 -> 50 -> 100 -> 150
                        if (v < min) {
                            v = min;
                        } else if (v === min) {
                            v = step;
                        } else {
                            v = v + step;
                        }
                    } else {
                        // Decrement: 150 -> 100 -> 50 -> 10
                        if (v <= min) {
                            v = min;
                        } else if (v <= step) {
                            v = min; // step -> min
                        } else {
                            v = v - step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    serviceRow.quantity = v;
                    // Trigger change event to adjust to increment
                    this.$nextTick(() => {
                        serviceRow.quantity = this.adjustMultiQuantityToIncrement(v, index);
                    });
                },

                calculateMultiServiceCharge(index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return 0;
                    const qty = this.isMultiRowCustomComments(index) ? this.getRowCommentsLineCount(index) : (Number(serviceRow.quantity) || 0);
                    const rate = Number(serviceRow.rate_per_1000) || 0;
                    return Math.round((qty / 1000) * rate * 100) / 100;
                },

                calculateMultiTotalCharge() {
                    return this.multiSelectedServices.reduce((sum, serviceRow, index) => {
                        if (!serviceRow.service_id) return sum;
                        const qty = this.isMultiRowCustomComments(index) ? this.getRowCommentsLineCount(index) : (Number(serviceRow.quantity) || 0);
                        const rate = Number(serviceRow.rate_per_1000) || 0;
                        const charge = Math.round((qty / 1000) * rate * 100) / 100;
                        return sum + charge;
                    }, 0);
                },

                submitMultiForm(event) {
                    if (this.multiSelectedServices.length === 0) {
                        alert('{{ __('Please select at least one service.') }}');
                        return;
                    }

                    if (!this.multiLinkValid) {
                        alert('{{ __('Please enter a valid link.') }}');
                        return;
                    }

                    for (let i = 0; i < this.multiSelectedServices.length; i++) {
                        if (this.isMultiRowCustomComments(i) && this.getRowCommentsLineCount(i) < 1) {
                            alert('{{ __('Please enter at least one comment (one per line) for custom comments service.') }}');
                            return;
                        }
                    }

                    this.submitting = true;
                    const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.multi-store') }}"]');
                    if (form) {
                        form.submit();
                    } else {
                        // Fallback: try to find form by x-show condition
                        const forms = this.$el.querySelectorAll('form');
                        for (let f of forms) {
                            if (f.action && f.action.includes('multi-store')) {
                                f.submit();
                                return;
                            }
                        }
                        console.error('Could not find multi-store form');
                    }
                }
            }
        }
    </script>
    <style>
        /* Chrome, Safari, Edge */
        .no-spinner::-webkit-outer-spin-button,
        .no-spinner::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        /* Firefox */
        .no-spinner {
            -moz-appearance: textfield;
        }
    </style>

</x-client-layout>
