<?php

namespace App\Http\Controllers\Api\Tanker;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Models\BookingAssignment;
use App\Services\SettingsService;
use App\Services\TankerAssignmentService;
use App\Services\TrackingService;
use App\Support\Geo;
use App\Support\Mobile;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private TankerAssignmentService $service, private TrackingService $tracking) {}

    private function scope(Request $request)
    {
        $user = $request->user();
        $profile = $user->tankerProfile;
        return BookingAssignment::query()->with(['booking.customer:id,name,mobile', 'booking.borewell:id,name,latitude,longitude,address', 'vehicle:id,registration_number,capacity_litres,base_latitude,base_longitude,last_latitude,last_longitude', 'driver:id,name,mobile'])
            ->where(fn ($q) => $profile
                ? $q->whereHas('vehicle', fn ($v) => $v->where('tanker_profile_id', $profile->id))->orWhere('driver_user_id', $user->id)
                : $q->where('driver_user_id', $user->id));
    }

    /** New leads assigned to this tanker (awaiting accept/reject). */
    public function leads(Request $request)
    {
        $rows = $this->scope($request)->where('assignment_status', 'assigned')->latest('assigned_at')->get();
        return $this->ok($rows->map(fn ($a) => $this->present($a)));
    }

    public function index(Request $request)
    {
        $q = $this->scope($request)->latest('assigned_at');
        $scope = $request->get('scope', 'active');
        if ($scope === 'active') $q->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived']);
        if ($scope === 'completed') $q->whereIn('assignment_status', ['delivered', 'completed']);
        if ($scope === 'closed') $q->whereIn('assignment_status', ['rejected', 'superseded', 'withdrawn', 'cancelled']);
        $page = $q->paginate($this->perPage());
        $page->setCollection($page->getCollection()->map(fn ($a) => $this->present($a)));
        return $this->ok($page);
    }

    public function show(Request $request, BookingAssignment $assignment)
    {
        $a = $this->scope($request)->findOrFail($assignment->id);
        return $this->ok($this->present($a, true));
    }

    public function accept(Request $request, BookingAssignment $assignment)
    {
        return $this->ok($this->present($this->service->accept($assignment, $request->user())->fresh($this->relations()), true), 'Assignment accepted');
    }

    public function reject(Request $request, BookingAssignment $assignment)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $this->service->reject($assignment, $request->user(), $data['reason'] ?? null);
        return $this->ok(null, 'Assignment rejected');
    }

    public function start(Request $request, BookingAssignment $assignment)
    {
        return $this->ok($this->present($this->service->start($assignment, $request->user())->fresh($this->relations()), true), 'Delivery started — keep this screen open to share location');
    }

    public function arrive(Request $request, BookingAssignment $assignment)
    {
        return $this->ok($this->present($this->service->arrive($assignment, $request->user())->fresh($this->relations()), true), 'Marked as arrived');
    }

    public function delivered(Request $request, BookingAssignment $assignment)
    {
        $data = $request->validate([
            'delivered_litres' => 'nullable|integer|min:1|max:200000',
            'proof_notes' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $a = $this->service->delivered($assignment, $request->user(), $data, $request->file('proof'));
        return $this->ok($this->present($a->fresh($this->relations()), true), 'Delivery recorded');
    }

    public function location(LocationRequest $request)
    {
        $loc = $this->tracking->record($request->user(), $request->validated());
        return $this->created(['id' => $loc->id, 'recorded_at' => $loc->recorded_at, 'next_in_seconds' => (int) SettingsService::get('gps_update_interval_seconds')], 'Location received');
    }

    private function relations(): array
    {
        return ['booking.customer:id,name,mobile', 'booking.borewell:id,name,latitude,longitude,address', 'vehicle', 'driver'];
    }

    /** Lead privacy: customer contact/exact address are shared only after the driver accepts. */
    private function present(BookingAssignment $a, bool $detail = false): array
    {
        $b = $a->booking;
        $revealed = ! in_array($a->assignment_status, ['assigned', 'rejected', 'superseded', 'withdrawn'], true);
        $from = $a->vehicle?->last_latitude ? [$a->vehicle->last_latitude, $a->vehicle->last_longitude] : ($a->vehicle?->base_latitude ? [$a->vehicle->base_latitude, $a->vehicle->base_longitude] : null);
        $item = $b->items()->first();
        return [
            'id' => $a->id,
            'status' => $a->assignment_status,
            'assigned_at' => $a->assigned_at, 'response_due_at' => $a->response_due_at, 'response_overdue' => $a->response_overdue,
            'accepted_at' => $a->accepted_at, 'started_at' => $a->started_at, 'arrived_at' => $a->arrived_at, 'delivered_at' => $a->delivered_at,
            'rejection_reason' => $a->rejection_reason, 'delivered_litres' => $a->delivered_litres, 'proof_notes' => $a->proof_notes, 'has_proof' => $a->has_proof,
            'vehicle' => $a->vehicle ? ['id' => $a->vehicle->id, 'registration_number' => $a->vehicle->registration_number, 'capacity_litres' => $a->vehicle->capacity_litres] : null,
            'driver' => $a->driver ? ['name' => $a->driver->name, 'mobile' => $a->driver->mobile] : null,
            'booking' => [
                'id' => $b->id, 'booking_number' => $b->booking_number, 'status' => $b->status,
                'water_type' => $b->water_type, 'quantity_litres' => $b->quantity_litres,
                'area' => $b->area, 'preferred_date' => $b->preferred_date?->toDateString(), 'preferred_time_slot' => $b->preferred_time_slot,
                'estimated_amount' => (float) $b->total_amount, 'trips' => $item?->trips, 'pricing_type' => $b->pricing_type,
                'approx_distance_km' => $from ? round(Geo::distanceKm($from[0], $from[1], $b->latitude, $b->longitude), 1) : null,
                'customer_name' => $revealed ? $b->customer?->name : null,
                'customer_mobile' => $revealed ? $b->customer?->mobile : Mobile::mask($b->customer?->mobile),
                'delivery_address' => $revealed ? $b->delivery_address : null,
                'landmark' => $revealed ? $b->landmark : null,
                'latitude' => $revealed ? $b->latitude : null, 'longitude' => $revealed ? $b->longitude : null,
                'notes' => $revealed ? $b->notes : null,
                'source' => $b->borewell ? ['name' => $b->borewell->name, 'address' => $revealed ? $b->borewell->address : null, 'latitude' => $revealed ? $b->borewell->latitude : null, 'longitude' => $revealed ? $b->borewell->longitude : null] : null,
            ],
            'tracking' => $detail ? [
                'active' => in_array($a->assignment_status, BookingAssignment::TRACKABLE, true),
                'interval_seconds' => (int) SettingsService::get('gps_update_interval_seconds'),
                'last_location_at' => $a->last_location_at,
            ] : null,
        ];
    }
}
