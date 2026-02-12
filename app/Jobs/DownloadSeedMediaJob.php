<?php

namespace App\Jobs;

use App\Models\AccountProfileSeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Download media for a profile seed (one URL at a time).
 *
 * Uses per-URL lock to prevent duplicate downloads.
 */
class DownloadSeedMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $seedId,
        public ?string $urlType = null // 'profile_photo' or 'story', null = both
    ) {}

    public function handle(): void
    {
        $seed = AccountProfileSeed::query()->find($this->seedId);

        if (!$seed) {
            Log::warning('Seed not found for media download', ['seed_id' => $this->seedId]);
            return;
        }

        if ($this->urlType === 'profile_photo' || $this->urlType === null) {
            $this->downloadMedia($seed, 'profile_photo');
        }

        if ($this->urlType === 'story' || $this->urlType === null) {
            $this->downloadMedia($seed, 'story');
        }

        // Update status if all media downloaded
        if ($seed->profile_photo_url && $seed->profile_photo_local_path &&
            $seed->story_url && $seed->story_local_path) {
            $seed->update(['status' => AccountProfileSeed::STATUS_READY]);
        } elseif (($seed->profile_photo_url && $seed->profile_photo_local_path) ||
                  ($seed->story_url && $seed->story_local_path)) {
            // At least one downloaded, check if other is needed
            if (!$seed->needsDownload()) {
                $seed->update(['status' => AccountProfileSeed::STATUS_READY]);
            }
        }
    }

    /**
     * Download media for a specific type.
     */
    private function downloadMedia(AccountProfileSeed $seed, string $type): void
    {
        $urlField = "{$type}_url";
        $pathField = "{$type}_local_path";
        $mimeField = "{$type}_mime";

        $url = $seed->$urlField;
        if (!$url) {
            return; // No URL to download
        }

        if ($seed->$pathField && Storage::exists($seed->$pathField)) {
            // Already downloaded and file exists
            return;
        }

        // Acquire per-URL lock
        $lockKey = 'tg:media:url:' . sha1($url);
        $lock = Cache::lock($lockKey, 180);

        if (!$lock->block(1)) {
            // Another job is downloading this URL
            Log::debug('Media download already in progress', [
                'seed_id' => $seed->id,
                'type' => $type,
                'url_hash' => substr(sha1($url), 0, 8),
            ]);
            return;
        }

        try {
            // Convert Google Drive URL to direct download
            $downloadUrl = $this->convertGoogleDriveUrl($url);

            Log::info('MEDIA_DOWNLOAD_START', [
                'seed_id' => $seed->id,
                'type' => $type,
                'url_hash' => substr(sha1($url), 0, 8),
            ]);

            // Download with timeout
            $timeout = config('telegram_mtproto.media.download_timeout_seconds', 300);
            $response = Http::timeout($timeout)
                ->withOptions(['allow_redirects' => true])
                ->get($downloadUrl);

            if (!$response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()}");
            }

            $content = $response->body();
            $size = strlen($content);

            // Validate size
            $maxBytes = config('telegram_mtproto.media.max_bytes', 30 * 1024 * 1024);
            if ($size > $maxBytes) {
                throw new \RuntimeException("File too large: {$size} bytes (max: {$maxBytes})");
            }

            // Detect mime type and extension
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $content);
            finfo_close($finfo);

            // Validate mime
            $allowedMimes = config('telegram_mtproto.media.allowed_mimes', []);
            if (!in_array($mime, $allowedMimes, true)) {
                throw new \RuntimeException("MIME type not allowed: {$mime}");
            }

            // Determine extension
            $extension = $this->getExtensionFromMime($mime);
            if (!$extension) {
                throw new \RuntimeException("Could not determine extension for MIME: {$mime}");
            }

            // Save to storage
            $storageDir = config('telegram_mtproto.media.storage_dir', 'telegram_media');
            $filename = sha1($url) . '.' . $extension;
            $path = "{$storageDir}/{$filename}";

            // Ensure directory exists
            $fullPath = Storage::path($storageDir);
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            Storage::put($path, $content);

            // Update seed
            $seed->update([
                $pathField => $path,
                $mimeField => $mime,
                'last_error' => null,
            ]);

            Log::info('MEDIA_DOWNLOAD_OK', [
                'seed_id' => $seed->id,
                'type' => $type,
                'url_hash' => substr(sha1($url), 0, 8),
                'size' => $size,
                'mime' => $mime,
            ]);

        } catch (\Throwable $e) {
            $seed->update([
                'status' => AccountProfileSeed::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);

            Log::error('MEDIA_DOWNLOAD_FAIL', [
                'seed_id' => $seed->id,
                'type' => $type,
                'url_hash' => substr(sha1($url), 0, 8),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Convert Google Drive URL to direct download URL.
     */
    private function convertGoogleDriveUrl(string $url): string
    {
        // Already a direct download URL
        if (str_contains($url, 'uc?id=') || str_contains($url, 'export=download')) {
            return $url;
        }

        // Extract file ID from various Google Drive URL formats
        $fileId = null;

        // Format: https://drive.google.com/file/d/<ID>/view...
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
            $fileId = $m[1];
        }
        // Format: https://drive.google.com/open?id=<ID>
        elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $m)) {
            $fileId = $m[1];
        }

        if (!$fileId) {
            // Not a Google Drive URL, return as-is
            return $url;
        }

        // Convert to direct download URL
        return "https://drive.google.com/uc?id={$fileId}&export=download";
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtensionFromMime(string $mime): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
        ];

        return $map[$mime] ?? null;
    }
}
