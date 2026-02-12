<?php

namespace App\Jobs;

use App\Exports\ExportQueryBuilderInterface;
use App\Models\ExportFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateExportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $exportFileId
    ) {
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exportFile = ExportFile::findOrFail($this->exportFileId);

        // Mark as processing
        $exportFile->update([
            'status' => ExportFile::STATUS_PROCESSING,
        ]);

        try {
            // Get module configuration
            $moduleConfig = config("exports.modules.{$exportFile->module}");
            if (!$moduleConfig) {
                throw new \Exception("Module '{$exportFile->module}' not found in configuration.");
            }

            // Get query builder class
            $queryBuilderClass = $moduleConfig['query_builder_class'] ?? null;
            if (!$queryBuilderClass || !class_exists($queryBuilderClass)) {
                throw new \Exception("Query builder class not found for module '{$exportFile->module}'.");
            }

            // Instantiate query builder
            $queryBuilder = app($queryBuilderClass);
            if (!$queryBuilder instanceof ExportQueryBuilderInterface) {
                throw new \Exception("Query builder must implement ExportQueryBuilderInterface.");
            }

            // Build query
            $query = $queryBuilder->build($exportFile->filters ?? []);

            // Check row count limit
            $maxRows = $moduleConfig['max_rows'] ?? null;
            if ($maxRows !== null) {
                $totalRows = $query->count();
                if ($totalRows > $maxRows) {
                    throw new \Exception("Export exceeds maximum row limit of {$maxRows}. Found {$totalRows} rows.");
                }
            }

            // Get columns
            $columns = $exportFile->columns ?? $moduleConfig['default_columns'] ?? [];

            // Generate file based on format
            if ($exportFile->format === ExportFile::FORMAT_CSV) {
                $filePath = $this->generateCsv($queryBuilder, $query, $columns, $exportFile);
            } else {
                throw new \Exception("Unsupported format: {$exportFile->format}");
            }

            // Count rows (approximate for large files)
            $rowsCount = $query->count();

            // Update export file
            $exportFile->update([
                'status' => ExportFile::STATUS_READY,
                'file_path' => $filePath,
                'rows_count' => $rowsCount,
                'error' => null,
            ]);
        } catch (\Exception $e) {
            $exportFile->update([
                'status' => ExportFile::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Generate CSV file.
     */
    protected function generateCsv(
        ExportQueryBuilderInterface $queryBuilder,
        $query,
        array $columns,
        ExportFile $exportFile
    ): string {
        $disk = $exportFile->file_disk;
        $storagePath = config('exports.storage_path', 'exports');
        $year = now()->format('Y');
        $month = now()->format('m');
        $fileName = "{$exportFile->module}_{$exportFile->id}_" . Str::random(8) . '.csv';
        $filePath = "{$storagePath}/{$exportFile->module}/{$year}/{$month}/{$fileName}";

        // Ensure directory exists
        Storage::disk($disk)->makeDirectory(dirname($filePath));

        // Open file for writing
        $fullPath = Storage::disk($disk)->path($filePath);
        $handle = fopen($fullPath, 'w');

        // Add BOM for Excel compatibility
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        $headings = $queryBuilder->headings($columns);
        fputcsv($handle, $headings);

        // Write rows using cursor to avoid loading all into memory
        $rowCount = 0;
        foreach ($query->cursor() as $model) {
            $row = $queryBuilder->mapRow($model, $columns);
            fputcsv($handle, $row);
            $rowCount++;
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $exportFile = ExportFile::find($this->exportFileId);
        if ($exportFile) {
            $exportFile->update([
                'status' => ExportFile::STATUS_FAILED,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
