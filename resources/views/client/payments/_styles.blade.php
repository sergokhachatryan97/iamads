{{-- Shared by the payment history page and the dashboard "Recent payments" block --}}
<style>
    .ph-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 600;
        border: none; text-decoration: none !important; white-space: nowrap; cursor: pointer;
        background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
        color: #fff !important; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
        transition: filter 0.15s;
    }
    .ph-btn:hover { filter: brightness(1.08); }
    .ph-btn--sm { padding: 7px 12px; font-size: 12px; box-shadow: none; }
    .ph-method-cell { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .ph-ico {
        width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 17px;
        background: rgba(139,92,246,0.12); color: var(--purple-light);
    }
    .ph-ico--sm { width: 30px; height: 30px; border-radius: 9px; font-size: 13px; }
    .ph-ico--heleket   { background: rgba(253,121,43,0.15); color: #fd7b2b; }
    .ph-ico--cryptomus { background: rgba(6,182,212,0.15);  color: var(--cyan); }
    .ph-ico--usdt      { background: rgba(38,161,123,0.15); color: #26a17b; }
    .ph-ico--bep20     { background: rgba(243,186,47,0.15); color: #f3ba2f; }
    .ph-ico--trx       { background: rgba(235,0,41,0.15);   color: #eb0029; }
    .ph-method { font-size: 14px; font-weight: 700; color: var(--text); }
    .ph-method span { font-weight: 500; color: var(--text3); }
    .ph-badge {
        display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px;
        font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .ph-badge--paid     { background: rgba(0,184,148,0.12);  color: #00b894; }
    .ph-badge--pending  { background: rgba(253,203,110,0.14); color: #e1a91a; }
    .ph-badge--muted    { background: rgba(136,146,164,0.14); color: var(--text3); }
    .ph-badge--failed   { background: rgba(255,107,107,0.13); color: #ff6b6b; }
    .ph-badge--refunded { background: rgba(9,132,227,0.13);  color: #3a9bf0; }
</style>
