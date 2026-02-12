<?php

namespace App\Console\Commands;

use App\Models\MtprotoTelegramAccount;
use App\Models\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TelegramMtprotoMap extends Command
{
    protected $signature = 'telegram:mtproto:map
        {--force : Overwrite existing mtproto_account_id}
        {--dry-run : Do not write anything, only show what would change}
        {--limit=0 : Limit mtproto rows processed (0 = no limit)}';

    protected $description = 'Ensure telegram_accounts exist for each mtproto account and set telegram_accounts.mtproto_account_id (by canonical phone).';

    public function handle(): int
    {
        $force  = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int)  $this->option('limit');

        $q = MtprotoTelegramAccount::query()
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->orderBy('id');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $stats = [
            'processed'    => 0,
            'created'      => 0,
            'mapped'       => 0,
            'overwritten'  => 0,
            'skipped_same' => 0,
            'skipped_set'  => 0,
            'skipped_bad'  => 0,
            'errors'       => 0,
        ];

        $q->chunkById(200, function ($rows) use ($force, $dryRun, &$stats) {
            foreach ($rows as $mtproto) {
                $stats['processed']++;

                $canonical = $this->canonicalPhone($mtproto->phone_number);
                if ($canonical === null) {
                    $stats['skipped_bad']++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($mtproto, $canonical, $force, $dryRun, &$stats) {

                        // 1) Find telegram_account by canonical phone
                        $tg = TelegramAccount::query()
                            ->where('phone', $canonical)
                            ->first();

                        // 2) Create if missing
                        if (!$tg) {
                            $stats['created']++;

                            if ($dryRun) {
                                // simulate created row (do not write)
                                $tg = new TelegramAccount([
                                    'phone' => $canonical,
                                    'is_active' => true,
                                    'subscription_count' => 0,
                                    'max_subscriptions' => 1000,
                                    'status' => 'ready',
                                ]);
                            } else {
                                // IMPORTANT: phone should ideally be UNIQUE in DB.
                                // If a concurrent create happens, we will catch and refetch.
                                try {
                                    $tg = TelegramAccount::create([
                                        'phone' => $canonical,
                                        'is_active' => true,
                                        'subscription_count' => 0,
                                        'max_subscriptions' => 1000,
                                        'status' => 'ready',
                                    ]);
                                } catch (\Throwable $e) {
                                    // if unique collision happened, re-fetch
                                    $tg = TelegramAccount::query()
                                        ->where('phone', $canonical)
                                        ->first();

                                    if (!$tg) {
                                        throw $e;
                                    }
                                }
                            }
                        }

                        // 3) Decide if we should set/overwrite mapping
                        $current = $tg->mtproto_account_id;

                        if ($current !== null) {
                            if ((int)$current === (int)$mtproto->id) {
                                $stats['skipped_same']++;
                                return;
                            }

                            if (!$force) {
                                $stats['skipped_set']++;
                                return;
                            }
                        }

                        // 4) Write mapping
                        if ($dryRun) {
                            if ($current !== null) $stats['overwritten']++;
                            $stats['mapped']++;
                            return;
                        }

                        $tg->mtproto_account_id = $mtproto->id;
                        $tg->save();

                        if ($current !== null) $stats['overwritten']++;
                        $stats['mapped']++;
                    });

                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->error("Error for mtproto_id={$mtproto->id} phone={$mtproto->phone_number}: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info('MTProto mapping finished:');
        $this->line("processed:    {$stats['processed']}");
        $this->line("created:      {$stats['created']}" . ($dryRun ? " (dry-run)" : ""));
        $this->line("mapped:       {$stats['mapped']}" . ($dryRun ? " (dry-run)" : ""));
        $this->line("overwritten:  {$stats['overwritten']}" . ($dryRun ? " (dry-run)" : ""));
        $this->line("skipped_same: {$stats['skipped_same']}");
        $this->line("skipped_set:  {$stats['skipped_set']} (mapping exists, use --force to overwrite)");
        $this->line("skipped_bad:  {$stats['skipped_bad']} (bad/empty phone)");
        $this->line("errors:       {$stats['errors']}");

        return self::SUCCESS;
    }


    private function canonicalPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') return null;

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        return '+' . $digits;
    }
}
