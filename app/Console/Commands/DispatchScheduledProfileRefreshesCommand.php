<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Jobs\RefreshOnlyFansProfileJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchScheduledProfileRefreshesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'profiles:dispatch-refreshes {--limit=1000 : Max profiles to dispatch per cycle}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatches background refresh jobs for creators due per 24h (>100k) or 72h (<=100k) schedule cadence';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dispatched = 0;

        $this->info("Scanning profiles due for refresh (next_refresh_at <= now)...");

        Profile::dueForRefresh()
            ->orderBy('id')
            ->chunkById(100, function ($profiles) use (&$dispatched, $limit) {
                foreach ($profiles as $profile) {
                    if ($dispatched >= $limit) {
                        return false; // Stop chunking when requested limit is reached
                    }
                    $accountId = $profile->account_id ?? $profile->username;
                    RefreshOnlyFansProfileJob::dispatch($profile->username, $accountId);
                    $dispatched++;
                }
            });

        $this->info("Successfully dispatched {$dispatched} profile refresh jobs to Queue.");
        Log::info("Dispatched scheduled profile refresh batch", ['count' => $dispatched]);

        return Command::SUCCESS;
    }
}
