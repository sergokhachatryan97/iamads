<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class ExportFile extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    // Format constants
    public const FORMAT_CSV = 'csv';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'module',
        'format',
        'filters',
        'columns',
        'status',
        'file_disk',
        'file_path',
        'rows_count',
        'error',
        'created_by_type',
        'created_by_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'rows_count' => 'integer',
    ];

    /**
     * Get the creator (polymorphic).
     */
    public function creator(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * Check if the export is ready for download.
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->file_path !== null;
    }

    /**
     * Get the full file path.
     */
    public function getFullPath(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk($this->file_disk)->path($this->file_path);
    }

    /**
     * Get the download URL.
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->isReady()) {
            return null;
        }

        return route('staff.exports.download', $this);
    }

    /**
     * Check if user can download this export.
     */
    public function canBeDownloadedBy($user): bool
    {
        // Admin/Staff can download any export
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        // Creator can download their own exports
        return $this->created_by_type === get_class($user) && $this->created_by_id === $user->id;
    }

    /**
     * Delete the file when the model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (ExportFile $exportFile) {
            if ($exportFile->file_path) {
                Storage::disk($exportFile->file_disk)->delete($exportFile->file_path);
            }
        });
    }
}
