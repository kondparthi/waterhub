<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WaterType;
use App\Services\SettingsService;

class MetaController extends Controller
{
    /** Public reference data for forms: water types, time slots, public settings. */
    public function index()
    {
        $settings = [];
        foreach (SettingsService::DEFAULTS as $key => [$v, $t, $label, $public]) {
            if ($public) $settings[$key] = SettingsService::get($key);
        }
        return $this->ok([
            'water_types' => WaterType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'description']),
            'settings' => $settings,
            'capacities' => [1000, 2000, 3000, 5000, 10000, 12000, 15000],
        ]);
    }
}
