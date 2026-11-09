<?php

use App\Models\TankerLocation;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gps:prune', function () {
    $days = (int) SettingsService::get('gps_retention_days');
    $n = TankerLocation::where('recorded_at', '<', now()->subDays($days))->delete();
    $this->info("Deleted {$n} GPS points older than {$days} days.");
})->purpose('Delete GPS history older than the configured retention period');

Schedule::command('gps:prune')->dailyAt('02:30');
