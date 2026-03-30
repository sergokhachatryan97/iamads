<?php

namespace App\Services\Telegram\Folder;

use Amp\CancelledException;
use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\MtprotoClientFactory;
use App\Support\TelegramLinkParser;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;

class TelegramFolderService
{
    private const FOLDER_MAX_PEERS = 200;

    public function __construct(
        private MtprotoClientFactory $mtprotoClientFactory
    ) {}

    /* =========================================
     * ADD PEER TO FOLDER
     * ========================================= */
    public function addPeerToFolder(MtprotoTelegramAccount $account, string $link, int $folderId, array $parsed = []): array
    {
        return $this->withRuntimeClient($account, function (API $api) use ($link, $folderId, $parsed): array {

            // 1) Find the folder filter
            $targetFilter = $this->findFilter($api, $folderId);
            if (! $targetFilter) {
                return ['ok' => false, 'error' => "Filter not found: {$folderId}"];
            }

            $filterTitle = $this->normalizeFilterTitle($targetFilter['title'] ?? null);

            // 2) Resolve the channel/group to an inputPeer
            $inputPeer = $this->resolveInputPeer($api, $link, $parsed);
            if (! $inputPeer) {
                return ['ok' => false, 'error' => 'Could not resolve peer'];
            }

            // 3) Check if peer already in folder
            $includePeers = $this->normalizeInputPeerList($targetFilter['include_peers'] ?? []);

            foreach ($includePeers as $existing) {
                if ($this->samePeerId($existing, $inputPeer)) {
                    // Already in folder — just retrieve the share link (filter is untouched,
                    // so chatlist state is consistent and getExportedInvites is safe).
                    $share = $this->getOrCreateShareLink(
                        $api, $folderId, $filterTitle, $this->filterShareablePeers($includePeers)
                    );

                    return [
                        'ok' => true,
                        'folder_id' => (int) ($targetFilter['id'] ?? $folderId),
                        'already_exists' => true,
                        'was_added' => false,
                        'input_peer' => $inputPeer,
                        'folder_share_link' => $share['link'] ?? null,
                        'folder_share_slug' => $share['slug'] ?? null,
                    ];
                }
            }

            // 4) Capacity check
            if (count($includePeers) >= self::FOLDER_MAX_PEERS) {
                return ['ok' => false, 'error' => 'Folder capacity reached (max 200 chats/groups)'];
            }

            // 5) Capture existing share invite BEFORE modifying the filter
            $existingInvite = $this->getExistingInvite($api, $folderId);

            // 6) Join the channel/group first (idempotent)
            $username = $parsed['username'] ?? null;
            if (! empty($username)) {
                $joinResult = $this->joinChannel($api, (string) $username);
                if ($joinResult !== null) {
                    return $joinResult;
                }
            }

            // 7) Re-resolve every existing peer through MadelineProto.
            //    getDialogFilters returns peers that may have stale access_hash
            //    in MadelineProto's internal DB. Passing them back to
            //    updateDialogFilter causes MadelineProto to drop the ones it
            //    can't serialize → peers disappear from the folder.
            //    getInfo() populates MadelineProto's cache and returns a clean InputPeer.
            $resolvedPeers = $this->resolveExistingPeers($api, $includePeers);
            $resolvedPeers[] = $inputPeer; // new peer is already resolved

            $targetFilter['include_peers'] = $resolvedPeers;

            $api->messages->updateDialogFilter(
                id: (int) $targetFilter['id'],
                filter: $this->sanitizeFilterForUpdate($targetFilter),
            );

            // 8) Update the folder invite link so it shows ALL current channels
            //    (including the one just added). The peers list in the invite
            //    controls what users see when they click the link.
            $shareablePeers = $this->filterShareablePeers($resolvedPeers);
            $share = $this->updateFolderInviteLink($api, (int) $targetFilter['id'], $filterTitle, $shareablePeers, $existingInvite);

            return [
                'ok' => true,
                'folder_id' => (int) $targetFilter['id'],
                'already_exists' => false,
                'was_added' => true,
                'input_peer' => $inputPeer,
                'folder_share_link' => $share['link'] ?? null,
                'folder_share_slug' => $share['slug'] ?? null,
            ];
        });
    }

    /* =========================================
     * REMOVE PEER FROM FOLDER
     * ========================================= */
    public function removePeerFromFolder(
        MtprotoTelegramAccount $account,
        string $link,
        int $folderId,
        array $parsed = []
    ): array {
        return $this->withRuntimeClient($account, function (API $api) use ($link, $folderId, $parsed): array {

            $targetFilter = $this->findFilter($api, $folderId);
            if (! $targetFilter) {
                return ['ok' => false, 'error' => 'Filter not found'];
            }

            $inputPeer = $this->resolveInputPeer($api, $link, $parsed);
            if (! $inputPeer) {
                return ['ok' => false, 'error' => 'Could not resolve peer'];
            }

            $rawPeers = $this->normalizeInputPeerList($targetFilter['include_peers'] ?? []);
            $filterTitle = $this->normalizeFilterTitle($targetFilter['title'] ?? null);

            // Capture invite BEFORE updateDialogFilter (getExportedInvites fails right after update)
            $existingInvite = $this->getExistingInvite($api, $folderId);

            // Re-resolve all peers so MadelineProto can serialize them
            $resolvedPeers = $this->resolveExistingPeers($api, $rawPeers);
            $before = count($resolvedPeers);

            // Remove the target peer (compare by ID, ignore _ type differences)
            $remainingPeers = array_values(array_filter(
                $resolvedPeers,
                fn ($p) => ! $this->samePeerId($p, $inputPeer)
            ));

            if (count($remainingPeers) === $before) {
                return ['ok' => true, 'not_found' => true, 'was_removed' => false];
            }

            if (empty($remainingPeers)) {
                return ['ok' => false, 'error' => 'Cannot remove last peer (FILTER_INCLUDE_EMPTY)'];
            }

            // 1) Remove channel from owner's folder
            $targetFilter['include_peers'] = $remainingPeers;

            $api->messages->updateDialogFilter(
                id: (int) $targetFilter['id'],
                filter: $this->sanitizeFilterForUpdate($targetFilter),
            );

            // 2) Update invite link WITHOUT the removed channel.
            //    Performers who synced this folder will see a suggestion
            //    to remove the channel from their copy of the folder.
            //    NOTE: Telegram does NOT auto-unsubscribe them from the channel.
            $shareablePeers = $this->filterShareablePeers($remainingPeers);

            if ($existingInvite !== null && ! empty($shareablePeers)) {
                $this->updateFolderInviteLink($api, $folderId, $filterTitle, $shareablePeers, $existingInvite);
            }

            return ['ok' => true, 'was_removed' => true];
        });
    }

    /* =========================================
     * FIND FILTER BY ID
     * ========================================= */
    private function findFilter(API $api, int $folderId): ?array
    {
        $response = $api->messages->getDialogFilters();
        $filters = $response['filters'] ?? $response;

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }
            if (($filter['_'] ?? '') === 'dialogFilterDefault') {
                continue;
            }
            if ((int) ($filter['id'] ?? 0) === $folderId) {
                return $filter;
            }
        }

        return null;
    }

    /* =========================================
     * SANITIZE FILTER FOR UPDATE
     * ========================================= */

    /**
     * Strip response-only fields before sending the filter back to updateDialogFilter.
     *
     * dialogFilterChatlist and dialogFilter have different TL schemas.
     * The getDialogFilters response includes extra fields (has_my_invites,
     * boolean flags like contacts/groups on chatlist, etc.) that cause
     * Telegram to downgrade a chatlist to a regular filter — which then
     * breaks every chatlists.* API call with FILTER_ID_INVALID.
     */
    private function sanitizeFilterForUpdate(array $filter): array
    {
        $type = $filter['_'] ?? 'dialogFilter';

        if ($type === 'dialogFilterChatlist') {
            // TL schema for dialogFilterChatlist:
            //   _ , id, title, emoticon?, color?, pinned_peers, include_peers
            // Everything else (has_my_invites, contacts, groups, etc.) must be stripped.
            $clean = [
                '_' => 'dialogFilterChatlist',
                'id' => (int) ($filter['id'] ?? 0),
                'title' => $filter['title'] ?? 'Premium Folder',
                'pinned_peers' => $filter['pinned_peers'] ?? [],
                'include_peers' => $filter['include_peers'] ?? [],
            ];

            if (isset($filter['emoticon']) && $filter['emoticon'] !== '') {
                $clean['emoticon'] = $filter['emoticon'];
            }
            if (isset($filter['color'])) {
                $clean['color'] = (int) $filter['color'];
            }

            return $clean;
        }

        // Regular dialogFilter — pass through as-is
        return $filter;
    }

    /* =========================================
     * RESOLVE INPUT PEER
     * ========================================= */
    private function resolveInputPeer(API $api, string $link, array $parsed): ?array
    {
        try {
            $username = $parsed['username']
                ?? TelegramLinkParser::parse($link)['username']
                ?? null;

            if (! is_string($username) || trim($username) === '') {
                return null;
            }

            $username = ltrim(trim($username), '@');

            $peer = $api->getInfo($username, API::INFO_TYPE_PEER);

            if (is_array($peer) && isset($peer['_']) && str_starts_with((string) $peer['_'], 'inputPeer')) {
                return $peer;
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('resolveInputPeer failed', [
                'link' => $link,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /* =========================================
     * JOIN CHANNEL (idempotent)
     * ========================================= */
    private function joinChannel(API $api, string $username): ?array
    {
        try {
            $api->channels->joinChannel([
                'channel' => $username,
            ]);

            return null; // success
        } catch (\Throwable $e) {
            $msg = strtoupper((string) $e->getMessage());

            if (str_contains($msg, 'USER_ALREADY_PARTICIPANT')) {
                return null;
            }
            if (str_contains($msg, 'CHANNEL_INVALID') || str_contains($msg, 'CHANNEL_PRIVATE')) {
                return null;
            }

            return ['ok' => false, 'error' => 'Join failed: '.$e->getMessage()];
        }
    }

    /* =========================================
     * PEER COMPARISON
     * ========================================= */
    private function normalizeInputPeerList($peers): array
    {
        return is_array($peers) ? array_values($peers) : [];
    }

    /**
     * Keep only inputPeerChannel peers — the only type that chatlist invite APIs accept.
     * inputPeerChat (basic groups) and inputPeerUser cause CHANNEL_INVALID.
     * Stale/deleted channels also cause it, but we can't detect those without calling
     * getInfo on each — so the fallback-to-single-peer retry handles that case.
     */
    private function filterShareablePeers(array $peers): array
    {
        return array_values(array_filter($peers, function (array $peer): bool {
            return ($peer['_'] ?? '') === 'inputPeerChannel';
        }));
    }

    /**
     * Re-resolve each peer through MadelineProto's getInfo() to get clean InputPeer
     * objects with valid access_hash in MadelineProto's internal cache.
     * Peers that can't be resolved (deleted/kicked/left) are silently skipped.
     */
    private function resolveExistingPeers(API $api, array $peers): array
    {
        $resolved = [];

        foreach ($peers as $peer) {
            try {
                $info = $api->getInfo($peer, API::INFO_TYPE_PEER);
                if (is_array($info) && isset($info['_'])) {
                    $resolved[] = $info;
                }
            } catch (\Throwable $e) {
                Log::debug('resolveExistingPeers: skipping stale peer', [
                    'peer' => $peer['_'] ?? 'unknown',
                    'id' => $peer['channel_id'] ?? $peer['chat_id'] ?? $peer['user_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $resolved;
    }

    /**
     * Compare two peers by numeric ID, ignoring _ type differences.
     * MadelineProto may return 'peerChannel' from getDialogFilters but
     * 'inputPeerChannel' from getInfo — same channel, different constructor.
     */
    private function samePeerId(array $a, array $b): bool
    {
        $idA = $this->extractPeerId($a);
        $idB = $this->extractPeerId($b);

        if ($idA === 0 || $idB === 0) {
            return false;
        }

        return $idA === $idB;
    }

    /**
     * Extract a normalized numeric ID from any peer type.
     */
    private function extractPeerId(array $peer): int
    {
        // channel_id covers: inputPeerChannel, peerChannel
        if (isset($peer['channel_id'])) {
            return $this->stripBotApiPrefix($peer['channel_id']);
        }
        // chat_id covers: inputPeerChat, peerChat
        if (isset($peer['chat_id'])) {
            return $this->stripBotApiPrefix($peer['chat_id']);
        }
        // user_id covers: inputPeerUser, peerUser
        if (isset($peer['user_id'])) {
            return (int) $peer['user_id'];
        }

        return 0;
    }

    private function stripBotApiPrefix(int|string $id): int
    {
        $id = (int) $id;
        $s = (string) $id;

        if (str_starts_with($s, '-100')) {
            return (int) substr($s, 4);
        }

        return abs($id);
    }

    /* =========================================
     * RUNTIME CLIENT WRAPPER
     * ========================================= */
    private function withRuntimeClient(MtprotoTelegramAccount $account, callable $fn): array
    {
        try {
            $api = $this->mtprotoClientFactory->makeForRuntime($account);
            $api->start();

            return $fn($api);
        } catch (CancelledException $e) {
            $this->mtprotoClientFactory->forgetRuntimeInstance($account);

            Log::warning('TelegramFolderService: operation cancelled (transient)', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'CANCELLED: '.$e->getMessage(), 'retryable' => true];
        } catch (\Throwable $e) {
            Log::error('TelegramFolderService runtime error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /* =========================================
     * SHARE LINK METHODS
     *
     * Telegram chatlist API overview:
     *
     *   chatlists.getExportedInvites  → list existing invites for a chatlist folder
     *   chatlists.exportChatlistInvite → create a NEW invite link (converts folder to chatlist if needed)
     *   chatlists.editExportedInvite   → update an existing invite's peer list / title
     *
     * All three require the folder to be a dialogFilterChatlist.
     * After messages.updateDialogFilter, getExportedInvites frequently
     * returns FILTER_ID_INVALID (transient server-side inconsistency).
     * editExportedInvite and exportChatlistInvite are more reliable
     * in that window because they address a specific slug or create fresh.
     *
     * Strategy:
     *   BEFORE updateDialogFilter → capture existing invite (slug + url)
     *   AFTER  updateDialogFilter → editExportedInvite(slug) || exportChatlistInvite(new)
     * ========================================= */

    /**
     * Capture the first existing invite for a folder BEFORE the filter is modified.
     * Returns [slug, link] or null if no invites exist yet.
     *
     * @return array{slug: string, link: string}|null
     */
    private function getExistingInvite(API $api, int $folderId): ?array
    {
        try {
            $res = $api->chatlists->getExportedInvites(
                chatlist: ['_' => 'inputChatlistDialogFilter', 'filter_id' => $folderId],
            );

            $invites = $res['invites'] ?? [];
            if (empty($invites) || ! is_array($invites)) {
                return null;
            }

            $invite = is_array($invites[0] ?? null) ? $invites[0] : [];
            $url = $this->stringFromInviteField($invite['url'] ?? null);

            if ($url === '') {
                return null;
            }

            $slug = $this->extractChatlistSlug($url);
            if ($slug === null) {
                return null;
            }

            return ['slug' => $slug, 'link' => $url];
        } catch (\Throwable $e) {
            // Not a chatlist yet, or no invites — expected for first-time folders
            Log::debug('getExistingInvite: none found', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update or create folder invite link so it shows ALL current channels.
     *
     * 1) If existing invite → editExportedInvite with all resolved peers
     * 2) If no invite yet  → exportChatlistInvite to create one
     * 3) Fallback           → return existing link as-is (better than nothing)
     *
     * All peers are already resolved through resolveExistingPeers + filterShareablePeers,
     * so CHANNEL_INVALID should not occur.
     *
     * @return array{link?: string, slug?: string}
     */
    private function updateFolderInviteLink(
        API $api,
        int $folderId,
        string $title,
        array $peers,
        ?array $existingInvite,
    ): array {
        $chatlist = ['_' => 'inputChatlistDialogFilter', 'filter_id' => $folderId];
        $displayTitle = $title !== '' ? $title : 'Premium Folder';

        // 1) Edit existing invite to include all current peers
        if ($existingInvite !== null && ! empty($peers)) {
            try {
                $res = $api->chatlists->editExportedInvite(
                    chatlist: $chatlist,
                    slug: $existingInvite['slug'],
                    title: $displayTitle,
                    peers: $peers,
                );

                $url = $this->stringFromInviteField($res['url'] ?? null);
                if ($url !== '') {
                    return ['link' => $url, 'slug' => $this->extractChatlistSlug($url)];
                }
            } catch (\Throwable $e) {
                Log::warning('editExportedInvite failed', [
                    'folder_id' => $folderId,
                    'slug' => $existingInvite['slug'],
                    'peers_count' => count($peers),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2) No existing invite — create a new one with all peers
        if (! empty($peers)) {
            $result = $this->exportFolderShareLink($api, $folderId, $displayTitle, $peers);
            if (! empty($result)) {
                return $result;
            }
        }

        // 3) Fallback — return the old link if we have it
        if ($existingInvite !== null) {
            return ['link' => $existingInvite['link'], 'slug' => $existingInvite['slug']];
        }

        return [];
    }

    /**
     * Get an existing share link or create one.
     * Used ONLY when the filter has NOT been modified (e.g. peer already in folder),
     * so getExportedInvites is safe to call.
     *
     * @return array{link?: string, slug?: string}
     */
    private function getOrCreateShareLink(API $api, int $folderId, string $title, array $peers): array
    {
        $chatlist = ['_' => 'inputChatlistDialogFilter', 'filter_id' => $folderId];

        try {
            $res = $api->chatlists->getExportedInvites(chatlist: $chatlist);
            $invites = $res['invites'] ?? [];

            if (! empty($invites) && is_array($invites)) {
                $invite = is_array($invites[0] ?? null) ? $invites[0] : [];
                $url = $this->stringFromInviteField($invite['url'] ?? null);

                if ($url !== '') {
                    return ['link' => $url, 'slug' => $this->extractChatlistSlug($url)];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('getExportedInvites failed, will create new', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->exportFolderShareLink($api, $folderId, $title, $peers);
    }

    /**
     * Create a brand new chatlist invite link.
     *
     * chatlists.exportChatlistInvite returns:
     *   { _: 'chatlists.exportedChatlistInvite', filter: DialogFilter, invite: ExportedChatlistInvite }
     *
     * The invite is in $res['invite']['url'].
     *
     * @return array{link?: string, slug?: string}
     */
    private function exportFolderShareLink(API $api, int $folderId, string $title, array $peers): array
    {
        try {
            $res = $api->chatlists->exportChatlistInvite(
                chatlist: ['_' => 'inputChatlistDialogFilter', 'filter_id' => $folderId],
                title: trim($title) !== '' ? $title : 'Premium Folder',
                peers: $peers,
            );

            // Response wraps the invite in an 'invite' key
            $invite = is_array($res['invite'] ?? null) ? $res['invite'] : [];
            $url = $this->stringFromInviteField($invite['url'] ?? null);

            if ($url === '') {
                Log::warning('exportChatlistInvite returned empty URL', [
                    'folder_id' => $folderId,
                    'response_keys' => is_array($res) ? array_keys($res) : 'not_array',
                ]);

                return [];
            }

            return ['link' => $url, 'slug' => $this->extractChatlistSlug($url)];
        } catch (\Throwable $e) {
            Log::warning('exportFolderShareLink failed', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /* =========================================
     * STRING HELPERS
     * ========================================= */

    private function extractChatlistSlug(string $url): ?string
    {
        if (preg_match('#t\.me/addlist/([A-Za-z0-9_\-]+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function stringFromInviteField(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    /**
     * DialogFilter.title in newer TL layers is TextWithEntities (array), not a plain string.
     * Extract the plain text for use in API calls that expect a string.
     */
    private function normalizeFilterTitle(mixed $title): string
    {
        if (is_string($title)) {
            $t = trim($title);

            return $t !== '' ? $t : 'Premium Folder';
        }

        if (is_array($title) && isset($title['text']) && is_string($title['text'])) {
            $t = trim($title['text']);

            return $t !== '' ? $t : 'Premium Folder';
        }

        return 'Premium Folder';
    }
}
