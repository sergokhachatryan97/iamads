<?php

namespace App\Jobs;

use App\Models\AccountProfileSeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Import profile seeds from Google Sheet (CSV export or API).
 */
class ImportProfileSeedsFromSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        if (!config('telegram_mtproto.sheet.enabled', false)) {
            Log::debug('Sheet import disabled, skipping');
            return;
        }

        $csvUrl = config('telegram_mtproto.sheet.csv_url');
        $useApi = config('telegram_mtproto.sheet.private.use_api', false);

        if ($csvUrl) {
            $this->importFromCsv($csvUrl);
        } elseif ($useApi) {
            $this->importFromApi();
        } else {
            Log::warning('SHEET_IMPORT_FAIL: No CSV URL or API enabled', [
                'csv_url_set' => !empty($csvUrl),
                'api_enabled' => $useApi,
            ]);
        }
    }

    /**
     * Import from CSV export URL.
     */
    private function importFromCsv(string $csvUrl): void
    {
        try {
            Log::info('SHEET_IMPORT_START', ['source' => 'csv', 'url_hash' => sha1($csvUrl)]);

            $response = Http::timeout(30)->get($csvUrl);

            if (!$response->successful()) {
                throw new \RuntimeException("Failed to download CSV: HTTP {$response->status()}");
            }

            $csvContent = $response->body();
            $lines = explode("\n", trim($csvContent));

            if (empty($lines)) {
                throw new \RuntimeException('CSV is empty');
            }

            // Parse header row
            $headers = str_getcsv(array_shift($lines));
            $headerMap = $this->mapHeaders($headers);

            $imported = 0;
            $errors = 0;

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                try {
                    $row = str_getcsv($line);
                    $data = $this->parseRow($row, $headerMap);

                    if (empty($data['username'])) {
                        Log::debug('Skipping row with empty username', ['line' => $lineNum + 2]);
                        continue;
                    }

                    $this->upsertSeed($data);
                    $imported++;

                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Failed to import row', [
                        'line' => $lineNum + 2,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SHEET_IMPORT_OK', [
                'source' => 'csv',
                'imported' => $imported,
                'errors' => $errors,
            ]);

        } catch (\Throwable $e) {
            Log::error('SHEET_IMPORT_FAIL', [
                'source' => 'csv',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Import from Google Sheets API.
     */
    private function importFromApi(): void
    {
        try {
            $spreadsheetId = config('telegram_mtproto.sheet.private.spreadsheet_id');
            $range = config('telegram_mtproto.sheet.private.range', 'Sheet1!A:E');

            if (!$spreadsheetId) {
                throw new \RuntimeException('Google Sheets API enabled but spreadsheet_id not configured');
            }

            // Check if Google Sheets API credentials exist
            $credentialsPath = storage_path('app/google-sheets-credentials.json');
            if (!file_exists($credentialsPath)) {
                Log::error('SHEET_IMPORT_FAIL: Google Sheets API credentials not found', [
                    'expected_path' => $credentialsPath,
                ]);
                return;
            }

            Log::info('SHEET_IMPORT_START', [
                'source' => 'api',
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
            ]);

            // Use Google Sheets API client
            // Note: This requires google/apiclient package
            // For now, log error if not available
            if (!class_exists(\Google_Client::class)) {
                throw new \RuntimeException('Google Sheets API client not installed. Run: composer require google/apiclient');
            }

            $client = new \Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
            $client->setAccessType('offline');

            $service = new \Google_Service_Sheets($client);
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                throw new \RuntimeException('Sheet is empty');
            }

            // Parse header row
            $headers = array_shift($values);
            $headerMap = $this->mapHeaders($headers);

            $imported = 0;
            $errors = 0;

            foreach ($values as $rowNum => $row) {
                try {
                    $data = $this->parseRow($row, $headerMap);

                    if (empty($data['username'])) {
                        Log::debug('Skipping row with empty username', ['row' => $rowNum + 2]);
                        continue;
                    }

                    $this->upsertSeed($data);
                    $imported++;

                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Failed to import row', [
                        'row' => $rowNum + 2,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SHEET_IMPORT_OK', [
                'source' => 'api',
                'imported' => $imported,
                'errors' => $errors,
            ]);

        } catch (\Throwable $e) {
            Log::error('SHEET_IMPORT_FAIL', [
                'source' => 'api',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Map CSV headers to expected column names.
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        $expected = [
            'username' => ['telegram unique username', 'username', 'telegram username', 'handle'],
            'name' => ['name', 'display name', 'display_name'],
            'bio' => ['bio', 'biography', 'about'],
            'profile_photo' => ['profile photo', 'profile_photo', 'photo', 'avatar'],
            'story' => ['story', 'story media'],
        ];

        foreach ($headers as $idx => $header) {
            $normalized = strtolower(trim($header));

            foreach ($expected as $key => $variants) {
                if (in_array($normalized, $variants, true)) {
                    $map[$key] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Parse a CSV row into seed data.
     */
    private function parseRow(array $row, array $headerMap): array
    {
        $get = function (string $key, $default = null) use ($row, $headerMap) {
            $idx = $headerMap[$key] ?? null;
            if ($idx === null || !isset($row[$idx])) {
                return $default;
            }
            return trim((string) $row[$idx]) ?: $default;
        };

        $username = $get('username');
        if ($username) {
            $username = AccountProfileSeed::normalizeUsername($username);
        }

        return [
            'username' => $username,
            'display_name' => $get('name'),
            'bio' => $get('bio'),
            'profile_photo_url' => $get('profile_photo'),
            'story_url' => $get('story'),
        ];
    }

    /**
     * Upsert a seed row.
     */
    private function upsertSeed(array $data): void
    {
        $username = $data['username'];
        if (!$username) {
            return;
        }

        $seed = AccountProfileSeed::updateOrCreate(
            ['username' => $username],
            [
                'display_name' => $data['display_name'] ?? null,
                'bio' => $data['bio'] ?? null,
                'profile_photo_url' => $data['profile_photo_url'] ?? null,
                'story_url' => $data['story_url'] ?? null,
                // Don't overwrite local paths if they exist
            ]
        );

        // Determine status
        $needsDownload = false;
        if ($seed->profile_photo_url && !$seed->profile_photo_local_path) {
            $needsDownload = true;
        }
        if ($seed->story_url && !$seed->story_local_path) {
            $needsDownload = true;
        }

        $seed->update([
            'status' => $needsDownload ? AccountProfileSeed::STATUS_NEEDS_DOWNLOAD : AccountProfileSeed::STATUS_READY,
            'last_error' => null,
        ]);
    }
}
