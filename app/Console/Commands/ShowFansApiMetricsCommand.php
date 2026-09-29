<?php

namespace App\Console\Commands;

use App\Services\FansApiMetricsService;
use Illuminate\Console\Command;

class ShowFansApiMetricsCommand extends Command
{
    protected $signature = 'profiles:metrics {--json : Output metrics as JSON}';
    protected $description = 'Displays live FansAPI refresh metrics: successful refreshes, attempts per refresh, and oldest waiting job age';

    public function handle(): int
    {
        $metrics = FansApiMetricsService::getMetricsSummary();

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->info("=== FansAPI Production Refresh Observability Metrics ===");
        $this->table(
            ['Metric Name', 'Observed Value', 'Benchmark / Target'],
            [
                ['Successful Refreshes', $metrics['successful_refreshes'], 'Higher is better'],
                ['Total Job Attempts', $metrics['total_attempts'], 'Monitored for capacity'],
                ['Failed Attempts', $metrics['failed_attempts'], '< 5% of total'],
                ['Attempts per Successful Refresh', $metrics['attempts_per_successful_refresh'], 'Ideal: 1.00 - 1.15'],
                ['Oldest Waiting Job Age', "{$metrics['oldest_waiting_job_age_sec']}s", '< 180s (SLA)'],
            ]
        );

        return Command::SUCCESS;
    }
}
