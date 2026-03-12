<?php

namespace App\Services\Telegram;

class TelegramHtmlParser
{
    public function parse(string $input, string $html): array
    {
        $normalizedInput = trim($input);

        $result = [
            'input' => $normalizedInput,
            'exists' => null,
            'status' => 'unknown', // ok|expired_invite|revoked_invite|restricted|deleted|invalid|ambiguous|unknown
            'entity_kind' => 'unknown',
            'link_kind' => 'unknown', // username|invite|post|comment|story|unknown
            'can_join' => null,

            'username' => $this->extractUsernameFromInput($normalizedInput),
            'invite_hash' => $this->extractInviteHash($normalizedInput),
            'title' => null,
            'og_title' => null,
            'description' => null,
            'action_text' => null,

            'members_count' => null,
            'subscribers_count' => null,
            'monthly_users_count' => null,

            'post' => [
                'message_id' => $this->extractMessageIdFromInput($normalizedInput),
                'views' => null,
                'comments_count' => null,
                'reactions' => [],
                'total_reactions' => null,
                'poll' => [
                    'is_poll' => false,
                    'question' => null,
                    'options' => [],
                    'is_closed' => null,
                    'total_voter_count' => null,
                    'type' => null,
                ],
            ],

            'story' => [
                'story_id' => $this->extractStoryIdFromInput($normalizedInput),
                'available' => null,
            ],

            'debug' => [
                'matched_rules' => [],
                'signals' => [],
            ],
        ];

        $html = $this->normalizeHtml($html);

        if ($html === '') {
            $result['exists'] = false;
            $result['status'] = 'invalid';
            $result['can_join'] = false;
            $result['debug']['matched_rules'][] = 'empty_html';

            return $result;
        }

        $result['title'] = $this->extractTitle($html);
        $result['og_title'] = $this->extractOgTitle($html);
        $result['description'] = $this->extractDescription($html);
        $result['action_text'] = $this->extractActionText($html);

        $counts = $this->extractCounts($html);
        $result['members_count'] = $counts['members_count'];
        $result['subscribers_count'] = $counts['subscribers_count'];
        $result['monthly_users_count'] = $counts['monthly_users_count'];

        $result['post']['views'] = $this->extractViews($html);
        $result['post']['comments_count'] = $this->extractCommentsCount($html);
        $result['post']['reactions'] = $this->extractReactionCounts($html);
        $result['post']['total_reactions'] = array_sum($result['post']['reactions']);
        $result['post']['poll'] = $this->extractPoll($html);

        $result['link_kind'] = $this->detectLinkKind($normalizedInput, $html);

        $result['debug']['signals'] = [
            'has_page_title_block' => $this->hasPageTitleBlock($html),
            'has_page_extra_block' => $this->hasPageExtraBlock($html),
            'has_page_photo_block' => $this->hasPagePhotoBlock($html),
            'has_page_additional_block' => $this->hasPageAdditionalBlock($html),
            'has_user_icon' => $this->containsAnyInsensitive($html, ['tgme_icon_user']),
            'has_group_icon' => $this->containsAnyInsensitive($html, ['tgme_icon_group']),
            'has_channel_icon' => $this->containsAnyInsensitive($html, ['tgme_icon_channel']),
            'has_resolve_proto' => $this->containsAnyInsensitive($html, ['tg://resolve?domain=']),
            'has_join_proto' => $this->containsAnyInsensitive($html, ['tg://join?invite=']),
        ];

        $classification = $this->classify($normalizedInput, $html, $result);

        $result['exists'] = $classification['exists'];
        $result['status'] = $classification['status'];
        $result['entity_kind'] = $classification['entity_kind'];
        $result['can_join'] = $classification['can_join'] ?? null;
        $result['debug']['matched_rules'] = $classification['matched_rules'];

        return $result;
    }

    protected function classify(string $input, string $html, array $context): array
    {
        $matched = [];

        $hasPageTitle = $this->hasPageTitleBlock($html);
        $hasPageExtra = $this->hasPageExtraBlock($html);
        $hasPagePhoto = $this->hasPagePhotoBlock($html);
        $hasPageAdditional = $this->hasPageAdditionalBlock($html);

        $hasStrongUserSignals = $hasPageTitle && $hasPageExtra;
        $hasStrongInviteSignals = $hasPageTitle || $hasPageExtra || $hasPagePhoto || $hasPageAdditional;

        // 1. Restricted / TOS violation
        if ($this->containsAnyInsensitive($html, [
            'This group can’t be displayed because it violated Telegram\'s Terms of Service',
            'This group can\'t be displayed because it violated Telegram\'s Terms of Service',
            'violated Telegram\'s Terms of Service',
            'violated Telegram’s Terms of Service',
            'This group can’t be displayed',
            'This group can\'t be displayed',
        ])) {
            $matched[] = 'restricted_tos';

            return [
                'exists' => true,
                'status' => 'restricted',
                'entity_kind' => 'restricted_group',
                'can_join' => false,
                'matched_rules' => $matched,
            ];
        }

        // 2. Explicit expired / invalid invite
        if ($this->containsAnyInsensitive($html, [
            'Sorry, this invite link is invalid',
            'invite link is invalid',
            'invite link has expired',
            'this invite link has expired',
        ])) {
            $matched[] = 'expired_invite_phrase';

            return [
                'exists' => false,
                'status' => 'expired_invite',
                'entity_kind' => 'unknown',
                'can_join' => false,
                'matched_rules' => $matched,
            ];
        }

        // 3. Explicit deleted / not found
        if ($this->containsAnyInsensitive($html, [
            'Telegram user not found',
            'page not found',
            'username not found',
        ])) {
            $matched[] = 'deleted_or_not_found';

            return [
                'exists' => false,
                'status' => 'deleted',
                'entity_kind' => 'deleted_entity',
                'can_join' => false,
                'matched_rules' => $matched,
            ];
        }

// 4. Invite links
        if ($context['link_kind'] === 'invite') {
            // 4.1 Strong private channel invite
            if (
                $this->containsAnyInsensitive($html, [
                    'Join Channel',
                    'invited to the channel',
                    'tg://join?invite=',
                ]) &&
                $hasPageTitle &&
                $hasPageAdditional &&
                ($hasPagePhoto || $hasPageExtra || $context['subscribers_count'] !== null)
            ) {
                $matched[] = 'private_channel_invite_strong';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'private_channel_invite',
                    'can_join' => true,
                    'matched_rules' => $matched,
                ];
            }

            // 4.2 Strong private group invite
            if (
                $this->containsAnyInsensitive($html, [
                    'Join Group',
                    'group chat',
                    'you are invited to the group',
                    'invited to a <strong>group chat</strong>',
                    'tg://join?invite=',
                ]) &&
                $hasPageTitle &&
                $hasPageAdditional &&
                ($hasPagePhoto || $hasPageExtra || $context['members_count'] !== null)
            ) {
                $matched[] = 'private_group_invite_strong';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'private_group_invite',
                    'can_join' => true,
                    'matched_rules' => $matched,
                ];
            }

            // 4.3 Weak private channel invite landing page
            if (
                $this->containsAnyInsensitive($html, [
                    'Join Channel',
                    'invited to the channel',
                    'tg://join?invite=',
                ])
            ) {
                $matched[] = 'private_channel_invite_weak';

                return [
                    'exists' => null,
                    'status' => 'ambiguous',
                    'entity_kind' => 'private_channel_invite',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            // 4.4 Weak private group invite landing page
            if (
                $this->containsAnyInsensitive($html, [
                    'Join Group',
                    'group chat',
                    'invited to a <strong>group chat</strong>',
                    'tg://join?invite=',
                ])
            ) {
                $matched[] = 'private_group_invite_weak';

                return [
                    'exists' => null,
                    'status' => 'ambiguous',
                    'entity_kind' => 'private_group_invite',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            // 4.5 Generic invite proto seen, but unclear
            if ($this->containsAnyInsensitive($html, ['tg://join?invite=', 'https://t.me/+'])) {
                $matched[] = 'generic_invite_unknown_state';

                return [
                    'exists' => null,
                    'status' => 'ambiguous',
                    'entity_kind' => 'unknown',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }
        }

        // 5. Bot / referral / startapp
        if ($this->containsAnyInsensitive($html, [
            'Start Bot',
            'Launch @',
        ])) {
            if (str_contains($input, 'startapp=')) {
                $matched[] = 'bot_startapp';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'bot_startapp',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            if (str_contains($input, 'start=')) {
                $matched[] = 'bot_start_with_referral';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'bot_start_with_referral',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            $matched[] = 'bot_start';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'bot_start',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 6. Channel post comment
        if ($this->isCommentInput($input)) {
            $matched[] = 'channel_post_comment_url_pattern';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'channel_post_comment',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 7. Story
        if ($context['link_kind'] === 'story') {
            $matched[] = 'story_url_pattern';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'channel_story',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 8. Channel post
        if ($context['link_kind'] === 'post') {
            $matched[] = 'channel_post';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'channel_post',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 9. Public group
        if (
            $context['members_count'] !== null &&
            $this->containsAnyInsensitive($html, [
                'View in Telegram',
                'view and join',
                'right away',
                'tg://resolve?domain=',
            ])
        ) {
            $matched[] = 'public_group';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'group',
                'can_join' => true,
                'matched_rules' => $matched,
            ];
        }

        // 9. Public channel
        if (
            $this->containsAnyInsensitive($html, ['Preview channel']) ||
            ($context['subscribers_count'] !== null && $this->containsAnyInsensitive($html, ['View in Telegram']))
        ) {
            $matched[] = 'public_channel';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'channel',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 10. Strong user profile page
        if (
            $this->containsAnyInsensitive($html, ['Send Message']) &&
            $this->containsAnyInsensitive($html, ['tg://resolve?domain=']) &&
            $hasStrongUserSignals
        ) {
            $matched[] = 'user_strong_profile_page';

            return [
                'exists' => true,
                'status' => 'ok',
                'entity_kind' => 'user',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 11. Weak/generic contact landing page
        if (
            $this->containsAnyInsensitive($html, ['Send Message']) &&
            $this->containsAnyInsensitive($html, ['tg://resolve?domain=']) &&
            !$hasStrongUserSignals
        ) {
            $matched[] = 'generic_contact_page';

            return [
                'exists' => null,
                'status' => 'ambiguous',
                'entity_kind' => 'user',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        // 12. Generic public resolved entity
        if ($this->containsAnyInsensitive($html, ['tg://resolve?domain='])) {
            $matched[] = 'resolved_public_entity';

            if ($context['subscribers_count'] !== null) {
                $matched[] = 'fallback_channel_by_subscribers';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'channel',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            if ($context['monthly_users_count'] !== null) {
                $matched[] = 'fallback_bot_by_monthly_users';

                return [
                    'exists' => true,
                    'status' => 'ok',
                    'entity_kind' => 'bot_start',
                    'can_join' => null,
                    'matched_rules' => $matched,
                ];
            }

            return [
                'exists' => null,
                'status' => 'ambiguous',
                'entity_kind' => 'unknown',
                'can_join' => null,
                'matched_rules' => $matched,
            ];
        }

        $matched[] = 'no_rule_matched';

        return [
            'exists' => null,
            'status' => 'unknown',
            'entity_kind' => 'unknown',
            'can_join' => null,
            'matched_rules' => $matched,
        ];
    }

    protected function detectLinkKind(string $input, string $html): string
    {
        if ($this->extractInviteHash($input) !== null || $this->containsAnyInsensitive($html, ['tg://join?invite='])) {
            return 'invite';
        }

        if ($this->isCommentInput($input)) {
            return 'comment';
        }

        if ($this->extractStoryIdFromInput($input) !== null) {
            return 'story';
        }

        if ($this->extractMessageIdFromInput($input) !== null) {
            return 'post';
        }

        if ($this->extractUsernameFromInput($input) !== null) {
            return 'username';
        }

        return 'unknown';
    }

    protected function normalizeHtml(string $html): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $html));
    }

    protected function extractUsernameFromInput(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^@([A-Za-z0-9_]{3,32})$/', $input, $m)) {
            return $m[1];
        }

        if (preg_match('~(?:https?://)?(?:www\.)?(?:t|telegram)\.me/(?:s/)?([A-Za-z0-9_]{3,32})(?:$|/|\?|#)~i', $input, $m)) {
            if (!in_array(strtolower($m[1]), ['joinchat', 'c', 's'], true)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function extractInviteHash(string $input): ?string
    {
        if (preg_match('~(?:https?://)?(?:www\.)?t\.me/\+([A-Za-z0-9_-]+)~i', $input, $m)) {
            return $m[1];
        }

        if (preg_match('~(?:https?://)?(?:www\.)?t\.me/joinchat/([A-Za-z0-9_-]+)~i', $input, $m)) {
            return $m[1];
        }

        if (preg_match('~tg://join\?invite=([A-Za-z0-9_-]+)~i', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function extractMessageIdFromInput(string $input): ?int
    {
        if (preg_match('~(?:https?://)?(?:www\.)?t\.me/(?:s/)?[A-Za-z0-9_]+/(\d+)(?:\?.*)?(?:#.*)?$~i', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function extractStoryIdFromInput(string $input): ?int
    {
        $input = trim($input);

        // t.me/username/story/40 or t.me/username/stories/40
        if (preg_match('~(?:https?://)?(?:www\.)?t\.me/[A-Za-z0-9_]+/(?:story|stories)/(\d+)~i', $input, $m)) {
            return (int) $m[1];
        }

        // tg://resolve?domain=username&story=40
        if (preg_match('~(?:^|[?&])story=(\d+)(?:$|&)~i', $input, $m)) {
            return (int) $m[1];
        }

        // t.me/username/s/40  (Telegram sometimes uses /s/<id> in story-like public links)
        if (preg_match('~(?:https?://)?(?:www\.)?t\.me/[A-Za-z0-9_]+/s/(\d+)~i', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function isCommentInput(string $input): bool
    {
        return preg_match('~(?:\?|&)comment=\d+~i', $input) === 1
            || preg_match('~#comment~i', $input) === 1;
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<div class="tgme_page_title"[^>]*>(.*?)<\/div>/su', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        if (preg_match('/<meta property="og:title" content="([^"]*)"/u', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/<title>(.*?)<\/title>/su', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        return null;
    }

    protected function extractOgTitle(string $html): ?string
    {
        if (preg_match('/<meta property="og:title" content="([^"]*)"/u', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    protected function extractDescription(string $html): ?string
    {
        if (preg_match('/<div class="tgme_page_description[^"]*"[^>]*>(.*?)<\/div>/su', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        if (preg_match('/<meta property="og:description" content="([^"]*)"/u', $html, $m)) {
            $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $value !== '' ? $value : null;
        }

        return null;
    }

    protected function extractActionText(string $html): ?string
    {
        if (preg_match('/<a class="tgme_action_button(?:_new)?[^"]*"[^>]*>(.*?)<\/a>/su', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        return null;
    }

    protected function extractCounts(string $html): array
    {
        $members = null;
        $subscribers = null;
        $monthlyUsers = null;

        if (preg_match('/([\d\s.,]+(?:\s*[KMB])?)\s+members?\b/ui', $html, $m)) {
            $members = $this->parseHumanInt($m[1]);
        }

        if (preg_match('/([\d\s.,]+(?:\s*[KMB])?)\s+subscribers?\b/ui', $html, $m)) {
            $subscribers = $this->parseHumanInt($m[1]);
        }

        if (preg_match('/([\d\s.,]+(?:\s*[KMB])?)\s+monthly users\b/ui', $html, $m)) {
            $monthlyUsers = $this->parseHumanInt($m[1]);
        }

        return [
            'members_count' => $members,
            'subscribers_count' => $subscribers,
            'monthly_users_count' => $monthlyUsers,
        ];
    }

    protected function extractViews(string $html): ?int
    {
        if (preg_match('/([\d.,]+\s*[KMB]?)\s+views\b/ui', $html, $m)) {
            return $this->parseHumanInt($m[1]);
        }

        return null;
    }

    protected function extractCommentsCount(string $html): ?int
    {
        if (preg_match('/([\d.,]+\s*[KMB]?)\s+comments?\b/ui', $html, $m)) {
            return $this->parseHumanInt($m[1]);
        }

        return null;
    }

    protected function extractReactionCounts(string $html): array
    {
        $counts = [];

        if (preg_match_all('/reaction[^>]*>\s*([0-9][0-9\s.,KMB]*)\s*</ui', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $parsed = $this->parseHumanInt($value);
                if ($parsed !== null) {
                    $counts[] = $parsed;
                }
            }
        }

        if (empty($counts) && preg_match_all('/emoji[^>]*>\s*([0-9][0-9\s.,KMB]*)\s*</ui', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $parsed = $this->parseHumanInt($value);
                if ($parsed !== null) {
                    $counts[] = $parsed;
                }
            }
        }

        return array_values($counts);
    }

    protected function extractPoll(string $html): array
    {
        $result = [
            'is_poll' => false,
            'question' => null,
            'options' => [],
            'is_closed' => null,
            'total_voter_count' => null,
            'type' => null,
        ];

        if (!$this->containsAnyInsensitive($html, [
            'tgme_widget_message_poll',
            'Poll',
            'Quiz',
            'voters',
            'votes',
        ])) {
            return $result;
        }

        $result['is_poll'] = true;
        $result['type'] = 'unknown';

        if ($this->containsAnyInsensitive($html, ['Quiz'])) {
            $result['type'] = 'quiz';
        } elseif ($this->containsAnyInsensitive($html, ['Poll'])) {
            $result['type'] = 'regular';
        }

        if (preg_match('/poll_question[^>]*>(.*?)<\/div>/su', $html, $m)) {
            $result['question'] = $this->cleanText($m[1]);
        }

        if (preg_match_all('/poll_option[^>]*>(.*?)<\/div>/su', $html, $matches)) {
            foreach ($matches[1] as $optionHtml) {
                $option = $this->cleanText($optionHtml);
                if ($option !== '') {
                    $result['options'][] = $option;
                }
            }
        }

        if (preg_match('/([\d.,]+\s*[KMB]?)\s+voters\b/ui', $html, $m)) {
            $result['total_voter_count'] = $this->parseHumanInt($m[1]);
        } elseif (preg_match('/([\d.,]+\s*[KMB]?)\s+votes\b/ui', $html, $m)) {
            $result['total_voter_count'] = $this->parseHumanInt($m[1]);
        }

        if ($this->containsAnyInsensitive($html, ['closed poll', 'Poll is closed'])) {
            $result['is_closed'] = true;
        }

        return $result;
    }

    protected function cleanText(string $htmlFragment): string
    {
        $value = preg_replace('/<br\s*\/?>/iu', "\n", $htmlFragment);
        $value = strip_tags($value ?? '');
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value ?? '');

        return trim($value ?? '');
    }

    protected function parseHumanInt(string $value): ?int
    {
        $value = trim(str_replace([' ', ','], '', $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)([KMB])$/i', $value, $m)) {
            $number = (float) $m[1];
            $suffix = strtoupper($m[2]);

            $multiplier = match ($suffix) {
                'K' => 1000,
                'M' => 1000000,
                'B' => 1000000000,
                default => 1,
            };

            return (int) round($number * $multiplier);
        }

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    protected function containsAnyInsensitive(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function hasPageTitleBlock(string $html): bool
    {
        return preg_match('/<div class="tgme_page_title"[^>]*>.*?<\/div>/su', $html) === 1;
    }

    protected function hasPageExtraBlock(string $html): bool
    {
        return preg_match('/<div class="tgme_page_extra"[^>]*>.*?<\/div>/su', $html) === 1;
    }

    protected function hasPagePhotoBlock(string $html): bool
    {
        return preg_match('/<div class="tgme_page_photo"[^>]*>.*?<\/div>/su', $html) === 1;
    }

    protected function hasPageAdditionalBlock(string $html): bool
    {
        return preg_match('/<div class="tgme_page_additional"[^>]*>.*?<\/div>/su', $html) === 1;
    }
}
