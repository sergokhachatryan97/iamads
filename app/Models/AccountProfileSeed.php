<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountProfileSeed extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_READY = 'ready';
    public const STATUS_NEEDS_DOWNLOAD = 'needs_download';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'username',
        'display_name',
        'bio',
        'profile_photo_url',
        'story_url',
        'profile_photo_local_path',
        'story_local_path',
        'profile_photo_mime',
        'story_mime',
        'status',
        'last_error',
    ];

    /**
     * Normalize username (lowercase, remove @).
     */
    public static function normalizeUsername(string $username): string
    {
        $username = trim($username);
        $username = ltrim($username, '@');
        return strtolower($username);
    }

    /**
     * Check if seed is ready for use (has all required data).
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if seed needs media downloads.
     */
    public function needsDownload(): bool
    {
        if ($this->status === self::STATUS_NEEDS_DOWNLOAD) {
            return true;
        }

        // Check if has URL but no local path
        if ($this->profile_photo_url && !$this->profile_photo_local_path) {
            return true;
        }

        if ($this->story_url && !$this->story_local_path) {
            return true;
        }

        return false;
    }
}
