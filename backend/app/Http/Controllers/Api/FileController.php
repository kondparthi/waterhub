<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingAssignment;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Authorised downloads for privately stored documents. */
class FileController extends Controller
{
    public function vehicle(Request $request, Vehicle $vehicle, string $type)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->operatingTankerProfile()?->id === $vehicle->tanker_profile_id, 403);
        $path = $type === 'rc' ? $vehicle->rc_path : $vehicle->image_path;
        return $this->send($path);
    }

    public function licence(Request $request, DriverProfile $driver)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tankerProfile?->id === $driver->tanker_profile_id, 403);
        return $this->send($driver->licence_document);
    }

    public function proof(Request $request, BookingAssignment $assignment)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $assignment->belongsToTankerUser($user) || $assignment->booking?->customer_id === $user->id, 403);
        return $this->send($assignment->proof_path);
    }

    private function send(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }
}
