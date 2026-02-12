<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelegramAccountsSeeder extends Seeder
{
    private const TOTAL_ACCOUNTS = 2000000;
    private const CHUNK_SIZE = 1000;

    public function run(): void
    {
        $this->command->info('Starting TelegramAccount seeder for ' . number_format(self::TOTAL_ACCOUNTS) . ' accounts...');
        $this->command->warn('This will create ' . number_format(self::TOTAL_ACCOUNTS) . ' accounts. This may take a while...');

        $startTime = microtime(true);

        $totalChunks = (int) ceil(self::TOTAL_ACCOUNTS / self::CHUNK_SIZE);
        $created = 0;
        $baseId = $this->getNextId();

        // Use progress bar if available
        $bar = $this->command->getOutput()->createProgressBar($totalChunks);
        $bar->start();

        for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
            $accounts = $this->generateAccountsChunk(
                self::CHUNK_SIZE,
                $baseId + ($chunk * self::CHUNK_SIZE)
            );

            // Bulk insert for performance (bypasses model events)
            DB::table('telegram_accounts')->insert($accounts);

            $created += count($accounts);
            $bar->advance();

            // Log progress every 100 chunks
            if (($chunk + 1) % 100 === 0) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $this->command->newLine();
                $this->command->info("Progress: " . number_format($created) . " / " . number_format(self::TOTAL_ACCOUNTS) . " accounts ({$elapsed}s)");
            }
        }

        $bar->finish();
        $this->command->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->command->info("✅ Created " . number_format($created) . " TelegramAccount records in {$elapsed}s");
    }

    private function generateAccountsChunk(int $count, int $baseId): array
    {
        $accounts = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            // Estimate future ID (will be auto-incremented by DB)
            // This is approximate but ensures unique phone numbers
            $estimatedId = $baseId + $i;

            // Generate unique phone number (+1XXXXXXXXXX format)
            // Using estimated ID to ensure uniqueness
            $phone = $this->generatePhoneNumber($estimatedId);

            $accounts[] = [
                'phone' => $phone,
                'name' => $phone,
                'is_active' => true,
                'status' => 'ready',
                'subscription_count' => 0,
                'max_subscriptions' => 400,
                'weight' => 1,
                'fail_count' => 0,
                'onboarding_status' => 'new',
                'is_visible' => true,
                'should_hide_after_name_change' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $accounts;
    }

    private function generatePhoneNumber(int $accountId): string
    {
        // Generate phone number: +1XXXXXXXXXX format (11 digits total)
        // Using a unique counter ensures uniqueness across all accounts
        // Format: +1 + 10-digit number

        // Start from base number to avoid collisions with real numbers
        // Use accountId (which is sequential) to ensure uniqueness
        $baseNumber = 1000000000; // Start from 1 billion
        $phoneDigits = $baseNumber + ($accountId % 9000000000); // 9 billion possible numbers

        // Format as +1XXXXXXXXXX
        return '+1' . (string) $phoneDigits;
    }

    private function getNextId(): int
    {
        // Get the next ID to start from (for sequential IDs)
        $maxId = DB::table('telegram_accounts')->max('id');
        return ($maxId ?? 0) + 1;
    }
}
