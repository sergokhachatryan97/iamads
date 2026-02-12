<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TelegramAccountImportNames extends Command
{
    protected $signature = 'tg:assign-names
                            {file : Path to CSV file with unique names (one per line or first column)}
                            {--group= : Filter by group_key if exists}
                            {--limit=0 : Max accounts to assign (0 = no limit)}
                            {--dry-run : Show what would be assigned without saving}
                            {--skip-header : Skip first row (header)}';

    protected $description = 'Assign unique desired_profile_name values to telegram_accounts that are missing names';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $group = $this->option('group');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $skipHeader = (bool) $this->option('skip-header');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("File not found or not readable: {$filePath}");
            return self::FAILURE;
        }

        // 1) Read names
        $names = $this->readNames($filePath, $skipHeader);
        $names = array_values(array_filter($names));

        // Normalize names and deduplicate
        $normalizedNames = [];
        $nameMap = []; // normalized -> original
        foreach ($names as $original) {
            $normalized = $this->normalizeName($original);
            if ($normalized && !isset($normalizedNames[$normalized])) {
                $normalizedNames[$normalized] = true;
                $nameMap[$normalized] = $original; // keep original for storage
            }
        }
        $uniqueNormalized = array_keys($normalizedNames);
        $uniqueOriginals = array_values($nameMap);

        if (empty($uniqueOriginals)) {
            $this->error('No valid names found in file after normalization.');
            return self::FAILURE;
        }

        // 2) Query accounts needing names
        $q = TelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('desired_profile_name')
            ->orderBy('id');

        if ($group && Schema::hasColumn('telegram_accounts', 'group_key')) {
            $q->where('group_key', $group);
        }

        if ($limit > 0) {
            $q->limit($limit);
        }

        $accounts = $q->get(['id']);

        if ($accounts->isEmpty()) {
            $this->info('No accounts need names (desired_profile_name already set).');
            return self::SUCCESS;
        }

        // Filter out names already in use (check normalized column)
        $usedNorm = TelegramAccount::whereNotNull('desired_profile_name_norm')
            ->whereIn('desired_profile_name_norm', $uniqueNormalized)
            ->pluck('desired_profile_name_norm')
            ->toArray();

        $availableNormalized = array_diff($uniqueNormalized, $usedNorm);
        $availableOriginals = array_filter($nameMap, fn($norm) => in_array($norm, $availableNormalized), ARRAY_FILTER_USE_KEY);
        $availableOriginals = array_values($availableOriginals);

        $need = $accounts->count();
        $have = count($availableOriginals);

        $assignCount = min($need, $have);

        $this->info("Accounts needing names: {$need}");
        $this->info("Unique names available: {$have}");
        $this->info("Will assign: {$assignCount}");

        if ($assignCount === 0) {
            $this->warn('Nothing to assign.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - no DB updates');
            $preview = [];
            for ($i = 0; $i < min($assignCount, 20); $i++) {
                $preview[] = [$accounts[$i]->id, $availableOriginals[$i]];
            }
            $this->table(['account_id', 'desired_profile_name (preview)'], $preview);
            if (count($availableOriginals) < $need) {
                $this->warn("Note: " . (count($uniqueOriginals) - count($availableOriginals)) . " names were skipped (already in use or duplicates)");
            }
            return self::SUCCESS;
        }

        // 3) Update in transaction
        DB::transaction(function () use ($accounts, $availableOriginals, $assignCount) {
            for ($i = 0; $i < $assignCount; $i++) {
                TelegramAccount::where('id', $accounts[$i]->id)
                    ->whereNull('desired_profile_name') // safety
                    ->update(['desired_profile_name' => $availableOriginals[$i]]);
            }
        });

        $this->info("✅ Assigned {$assignCount} names.");
        if ($have > $need) {
            $this->warn("Unused names: " . ($have - $need));
        } elseif ($need > $have) {
            $this->warn("Unassigned accounts remaining: " . ($need - $have));
        }

        return self::SUCCESS;
    }

    private function readNames(string $filePath, bool $skipHeader): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        $names = [];

        if ($skipHeader) {
            fgetcsv($handle);
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (!isset($row[0])) continue;
            $names[] = $row[0]; // first column = name
        }

        fclose($handle);
        return $names;
    }

    private function normalizeName(string $name): ?string
    {
        $trimmed = trim($name);
        if (empty($trimmed)) {
            return null;
        }
        // Trim, collapse multiple spaces to single space, lowercase
        return strtolower(preg_replace('/\s+/', ' ', $trimmed));
    }
}
