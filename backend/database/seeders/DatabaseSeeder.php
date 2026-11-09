<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Models\WaterType;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Required reference data only: settings, water types, first admin. Safe to re-run. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsService::DEFAULTS as $key => [$value, $type, $label, $public]) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'type' => $type, 'label' => $label, 'is_public' => $public]);
        }
        Setting::flush();

        foreach (['Drinking Water' => 'Treated / potable water', 'General Use Water' => 'Household and construction use', 'Borewell Water' => 'Raw borewell water'] as $i => $desc) {
            WaterType::firstOrCreate(['slug' => Str::slug($i)], ['name' => $i, 'description' => $desc, 'sort_order' => 0]);
        }

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            User::create(['name' => 'Administrator', 'email' => $email, 'password' => $password, 'role' => 'admin', 'status' => 'active']);
            $this->command?->warn("Admin created: {$email} / {$password}  — change this password after first login.");
        }
    }
}
