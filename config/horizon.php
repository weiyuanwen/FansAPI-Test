<?php

use Illuminate\Support\Str;

/**
 * CRITICAL TIMEOUT RELATIONSHIP:
 * -------------------------------------------------------------
 * 1. Job Timeout ($timeout = 30s): Max duration code in handle() may run.
 * 2. Worker Timeout (--timeout = 60s): Max time Horizon supervisor allows PHP worker process before sending SIGKILL.
 * 3. Redis retry_after (retry_after = 90s): Time Redis waits before assuming worker died and re-releasing job.
 * 
 * GOLDEN RULE:
 * retry_after (90s) > Worker Timeout (60s) > Job Timeout (30s)
 * 
 * If retry_after <= Worker Timeout, Redis will hand the job to a second worker
 * while Worker 1 is still processing it, causing disastrous DUPLICATE execution!
 */

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path'   => env('HORIZON_PATH', 'horizon'),
    'use'    => 'default',

    'defaults' => [
        'supervisor-profiles-high' => [
            'connection'          => 'redis',
            'queue'               => ['profiles-high-priority'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 16,
            'minProcesses'        => 4,
            'tries'               => 3,
            'timeout'             => 60,
        ],
        'supervisor-profiles-standard' => [
            'connection'   => 'redis',
            'queue'        => ['profiles-standard', 'profiles-retry'],
            'balance'      => 'simple',
            'maxProcesses' => 32,
            'minProcesses' => 8,
            'tries'        => 4,
            'timeout'      => 60,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-profiles-high'     => ['maxProcesses' => 32],
            'supervisor-profiles-standard' => ['maxProcesses' => 64],
        ],
        'local' => [
            'supervisor-profiles-high'     => ['maxProcesses' => 4],
            'supervisor-profiles-standard' => ['maxProcesses' => 8],
        ],
    ],
];
