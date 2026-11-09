<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borewell;
use App\Services\TrackingService;

class TrackingController extends Controller
{
    public function __construct(private TrackingService $tracking) {}

    public function active()
    {
        return $this->ok([
            'deliveries' => $this->tracking->activeDeliveries(),
            'sources' => Borewell::where('status', 'active')->get(['id', 'name', 'source_type', 'latitude', 'longitude']),
        ]);
    }
}
