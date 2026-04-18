<x-client-layout :title="__('Referral Program')">
    <style>
        .ref-page { max-width: 720px; margin: 0 auto; }
        .ref-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            padding: 22px 24px;
            margin-bottom: 20px;
        }
        .ref-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 8px;
        }
        .ref-card-title i { color: var(--purple-light); font-size: 1.1rem; }
        .ref-desc {
            font-size: 14px;
            color: var(--text2);
            margin: 0 0 18px;
            line-height: 1.5;
        }
        .ref-note {
            font-size: 13px;
            color: var(--text3);
            padding: 12px 16px;
            border-radius: 10px;
            background: rgba(253, 203, 110, 0.08);
            border: 1px solid rgba(253, 203, 110, 0.2);
            margin-top: 14px;
        }
        html[data-theme="light"] .ref-note {
            background: rgba(253, 203, 110, 0.12);
            color: #8a6d0b;
        }
        .ref-link-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 10px;
        }
        .ref-link-input {
            flex: 1;
            min-width: 200px;
            font-family: ui-monospace, monospace;
            font-size: 13px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.35);
            color: var(--text);
        }
        html[data-theme="light"] .ref-link-input { background: var(--card2, #f0f2f7); }
        .ref-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: filter 0.15s, transform 0.15s;
            background: var(--purple, #6c5ce7);
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(108, 92, 231, 0.35);
        }
        .ref-btn:hover { filter: brightness(1.06); }
        .ref-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .ref-stat {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            padding: 18px 18px;
            position: relative;
            overflow: hidden;
        }
        .ref-stat::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--purple), var(--teal));
        }
        .ref-stat-val {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--purple), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .ref-stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; }
        .ref-table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            overflow: hidden;
        }
        .ref-table-wrap h3 {
            padding: 16px 18px;
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .ref-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .ref-table th {
            text-align: left;
            padding: 10px 14px;
            color: var(--text3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 10px;
            border-bottom: 1px solid var(--border);
            background: var(--card2);
        }
        .ref-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text2);
        }
        .ref-table tr:last-child td { border-bottom: 0; }
        .ref-empty {
            text-align: center;
            padding: 32px 16px;
            color: var(--text3);
            font-size: 14px;
        }
        .ref-copied {
            display: none;
            font-size: 12px;
            color: #00b894;
            font-weight: 600;
            margin-left: 8px;
        }
        .ref-copied.show { display: inline; }
    </style>

    <div class="ref-page">
        <div class="ref-stats">
            <div class="ref-stat">
                <div class="ref-stat-val">{{ $referralsCount }}</div>
                <div class="ref-stat-label">{{ __('Referrals') }}</div>
            </div>
            <div class="ref-stat">
                <div class="ref-stat-val">${{ number_format($totalEarned, 2) }}</div>
                <div class="ref-stat-label">{{ __('Total Earned') }}</div>
            </div>
            <div class="ref-stat">
                <div class="ref-stat-val">{{ $bonusPercent }}%</div>
                <div class="ref-stat-label">{{ __('Your Bonus Rate') }}</div>
            </div>
        </div>

        <div class="ref-card">
            <h2 class="ref-card-title">
                <i class="fa-solid fa-link" aria-hidden="true"></i>
                {{ __('Your Referral Link') }}
            </h2>
            <p class="ref-desc">{{ __('Invite users and get :percent% of their payments.', ['percent' => $bonusPercent]) }}</p>
            <div class="ref-link-row">
                <input type="text" class="ref-link-input" id="referralLinkInput" value="{{ $referralLink }}" readonly>
                <button type="button" class="ref-btn" onclick="copyReferralLink()">
                    <i class="fa-regular fa-copy"></i> {{ __('Copy') }}
                </button>
                <span class="ref-copied" id="refCopied">{{ __('Copied!') }}</span>
            </div>
            <div class="ref-note">
                <i class="fa-solid fa-circle-info" style="margin-right: 4px;"></i>
                {{ __('Funds will be added to the referrer\'s balance. Money can\'t be withdrawn.') }}
            </div>
        </div>

        <div class="ref-table-wrap">
            <h3>{{ __('Recent Earnings') }}</h3>
            @if($recentEarnings->isEmpty())
                <div class="ref-empty">{{ __('No referral earnings yet. Share your link to start earning!') }}</div>
            @else
                <div style="overflow-x:auto">
                    <table class="ref-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Description') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentEarnings as $earning)
                                <tr>
                                    <td>{{ $earning->created_at->format('M j, Y H:i') }}</td>
                                    <td style="color: var(--teal); font-weight: 700;">+${{ number_format((float) $earning->amount, 4) }}</td>
                                    <td>{{ $earning->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <script>
        function copyReferralLink() {
            const input = document.getElementById('referralLinkInput');
            navigator.clipboard.writeText(input.value).then(function() {
                const badge = document.getElementById('refCopied');
                badge.classList.add('show');
                setTimeout(function() { badge.classList.remove('show'); }, 2000);
            });
        }
    </script>
</x-client-layout>
