<?php

namespace App\Console\Commands;

use App\Models\MtprotoTelegramAccount;
use danog\MadelineProto\API;
use Illuminate\Console\Command;

class TelegramSetFolderCommand extends Command
{
    protected $signature = 'telegram:set-folder {--account=} {--title=} {--yes : Skip confirmation before saving}';

    protected $description = 'Fetch Telegram dialog folders and save premium_folder_id by folder title';

    public function handle(): int
    {
        $accountId = (int) ($this->option('account') ?? 0);
        $titleInput = trim((string) ($this->option('title') ?? ''));

        if ($accountId <= 0) {
            $this->error('Option --account=ID is required.');

            return self::FAILURE;
        }

        if ($titleInput === '') {
            $this->error('Option --title=FOLDER_TITLE is required.');

            return self::FAILURE;
        }

        $account = MtprotoTelegramAccount::query()->find($accountId);
        if (! $account) {
            $this->error("Account not found: {$accountId}");

            return self::FAILURE;
        }

        $sessionFile = $this->resolveSessionFile($account);
        if ($sessionFile === null) {
            $this->error('Session file is not configured for this account.');

            return self::FAILURE;
        }

        if (! file_exists($sessionFile)) {
            $this->error("Session file does not exist: {$sessionFile}");

            return self::FAILURE;
        }

        try {
            $api = new API($sessionFile);
            $api->start();
        } catch (\Throwable $e) {
            $this->error('Session invalid or not authorized: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $response = $api->messages->getDialogFilters();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch dialog folders: '.$e->getMessage());

            return self::FAILURE;
        }

        $filters = $this->extractFilters($response);
        if ($filters === []) {
            $this->warn('No custom dialog folders found.');

            return self::FAILURE;
        }

        $this->info('Available folders:');
        foreach ($filters as $f) {
            $this->line("id={$f['id']} title={$f['title']}");
        }
        $this->line('Total folders: '.count($filters));

        $target = $this->findByTitle($filters, $titleInput);
        if ($target === null) {
            $this->error('Folder not found');

            return self::FAILURE;
        }

        if (! $this->option('yes')) {
            $ok = $this->confirm("Save folder '{$target['title']}' (id={$target['id']}) to account #{$account->id}?");
            if (! $ok) {
                $this->warn('Canceled.');

                return self::SUCCESS;
            }
        }

        $account->premium_folder_id = (int) $target['id'];
        $account->premium_folder_title = (string) $target['title'];
        $account->save();

        $this->info('Saved folder ID: '.$target['id']);

        return self::SUCCESS;
    }

    private function resolveSessionFile(MtprotoTelegramAccount $account): ?string
    {
        $rawSessionFile = trim((string) ($account->getAttribute('session_file') ?? ''));
        if ($rawSessionFile !== '') {
            if (str_starts_with($rawSessionFile, '/')) {
                return $rawSessionFile;
            }

            return base_path($rawSessionFile);
        }

        $sessionName = trim((string) ($account->session_name ?? ''));
        if ($sessionName === '') {
            return null;
        }

        return storage_path("app/telegram/sessions/{$sessionName}.madeline");
    }

    /**
     * @return list<array{id:int,title:string}>
     */
    private function extractFilters(mixed $response): array
    {
        $rawFilters = [];
        if (is_array($response)) {
            if (isset($response['filters']) && is_array($response['filters'])) {
                $rawFilters = $response['filters'];
            } else {
                $rawFilters = $response;
            }
        }

        $out = [];
        foreach ($rawFilters as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['_'] ?? '') !== 'dialogFilter') {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $title = $this->normalizeFolderTitle($item['title'] ?? null);

            if ($id <= 0 || $title === '') {
                continue;
            }

            $out[] = ['id' => $id, 'title' => $title];
        }

        return $out;
    }

    private function normalizeFolderTitle(mixed $title): string
    {
        if (is_string($title)) {
            return trim($title);
        }

        if (is_array($title)) {
            if (isset($title['text']) && is_string($title['text'])) {
                return trim($title['text']);
            }

            if (isset($title['_']) && $title['_'] === 'textWithEntities' && isset($title['text']) && is_string($title['text'])) {
                return trim($title['text']);
            }
        }

        return '';
    }

    /**
     * @param  list<array{id:int,title:string}>  $folders
     * @return array{id:int,title:string}|null
     */
    private function findByTitle(array $folders, string $title): ?array
    {
        $needle = mb_strtolower(trim($title));
        foreach ($folders as $folder) {
            if (mb_strtolower(trim($folder['title'])) === $needle) {
                return $folder;
            }
        }

        return null;
    }
}
