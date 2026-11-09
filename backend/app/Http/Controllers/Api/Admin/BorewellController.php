<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BorewellRequest;
use App\Models\Booking;
use App\Models\Borewell;
use App\Models\WaterType;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BorewellController extends Controller
{
    public function index(Request $request)
    {
        $q = Borewell::withCount(['bookings as bookings_today' => fn ($b) => $b->whereDate('preferred_date', today())->whereNotIn('status', ['cancelled', 'rejected'])])->orderBy('name');
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('water_type')) $q->where(fn ($w) => $w->whereNull('water_types')->orWhereJsonContains('water_types', $request->water_type));
        $rows = $q->get()->map(function ($b) {
            $b->committed_today = $b->committedLitres(today()->toDateString());
            return $b;
        });
        return $this->ok($rows);
    }

    public function store(BorewellRequest $request)
    {
        $b = Borewell::create($request->validated() + ['status' => $request->status ?? 'active']);
        AuditLogger::log('borewell.created', $b, null, $b->toArray());
        return $this->created($b, 'Water source added');
    }

    public function show(Borewell $borewell)
    {
        $stats = Booking::where('borewell_id', $borewell->id)->whereNotIn('status', ['cancelled', 'rejected'])
            ->selectRaw('count(*) bookings, COALESCE(SUM(quantity_litres),0) litres, COALESCE(SUM(total_amount),0) revenue')->first();
        return $this->ok(['source' => $borewell, 'stats' => $stats, 'recent_bookings' => $borewell->bookings()->latest()->limit(20)->get(['id', 'booking_number', 'status', 'quantity_litres', 'preferred_date', 'total_amount'])]);
    }

    public function update(BorewellRequest $request, Borewell $borewell)
    {
        $before = $borewell->toArray();
        $borewell->update($request->validated());
        AuditLogger::log('borewell.updated', $borewell, $before, $borewell->fresh()->toArray());
        return $this->ok($borewell, 'Water source updated');
    }

    /** Deactivate instead of hard delete so booking history stays intact. */
    public function destroy(Borewell $borewell)
    {
        $borewell->update(['status' => 'inactive']);
        AuditLogger::log('borewell.deactivated', $borewell);
        return $this->ok($borewell, 'Water source deactivated');
    }

    // ---- Water types (configurable list) ----
    public function waterTypes() { return $this->ok(WaterType::orderBy('sort_order')->get()); }

    public function storeWaterType(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:water_types,name', 'description' => 'nullable|string|max:255', 'sort_order' => 'nullable|integer']);
        return $this->created(WaterType::create($data + ['slug' => Str::slug($data['name']), 'is_active' => true]), 'Water type added');
    }

    public function updateWaterType(Request $request, WaterType $waterType)
    {
        $data = $request->validate(['description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer']);
        $waterType->update($data);
        return $this->ok($waterType, 'Water type updated');
    }
}
