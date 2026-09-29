<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class MadisonIvySeeder extends Seeder
{
    public function run(): void
    {
        Profile::updateOrCreate(
            ['username' => 'madison420ivy'],
            [
                'display_name'               => 'Madison Ivy',
                'likes'                      => 120000,
                'revision'                   => 10,
                'last_attempted_at'          => '2026-09-29 03:00:00',
                'last_successful_refresh_at' => '2026-09-29 03:00:00',
                'attempt_count'              => 1,
                'next_refresh_at'            => '2026-09-30 03:00:00',
            ]
        );
    }
}
