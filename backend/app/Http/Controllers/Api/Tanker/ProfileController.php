<?php

namespace App\Http\Controllers\Api\Tanker;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BookingAssignment;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    private function profile(Request $request)
    {
        $p = $request->user()->operatingTankerProfile();
        abort_unless($p, 404, 'Tanker profile not found');
        return $p;
    }

    private function ownerOnly(Request $request)
    {
        $p = $request->user()->tankerProfile;
        if (! $p) throw new BusinessRuleException('Only the tanker owner can manage this.', 403);
        return $p;
    }

    public function show(Request $request)
    {
        return $this->ok($this->profile($request)->load('user:id,name,mobile,email', 'drivers', 'vehicles'));
    }

    public function update(Request $request)
    {
        $p = $this->ownerOnly($request);
        if ($request->filled('alternate_mobile')) $request->merge(['alternate_mobile' => Mobile::normalize($request->alternate_mobile)]);
        $data = $request->validate([
            'owner_name' => 'sometimes|required|string|max:120', 'business_name' => 'nullable|string|max:150',
            'alternate_mobile' => 'nullable|string|max:20', 'address' => 'sometimes|required|string|max:500',
            'base_latitude' => 'nullable|numeric|between:-90,90', 'base_longitude' => 'nullable|numeric|between:-180,180',
        ]);
        if (in_array($p->verification_status, ['correction_requested', 'rejected'], true)) {
            $data['verification_status'] = 'pending_review';
            $this->notifier->notifyAdmins('Tanker profile resubmitted', "{$p->owner_name} updated their profile for review.", 'tanker.resubmitted', ['tanker_profile_id' => $p->id]);
        }
        $p->update($data);
        if (isset($data['owner_name'])) $request->user()->update(['name' => $data['owner_name']]);
        return $this->ok($p->fresh(), 'Profile updated');
    }

    public function dashboard(Request $request)
    {
        $p = $this->profile($request);
        $base = BookingAssignment::query()->where(fn ($q) => $request->user()->tankerProfile
            ? $q->whereHas('vehicle', fn ($v) => $v->where('tanker_profile_id', $p->id))
            : $q->where('driver_user_id', $request->user()->id));
        return $this->ok([
            'verification_status' => $p->verification_status,
            'review_notes' => $p->review_notes,
            'vehicles' => $p->vehicles()->get(['id', 'registration_number', 'capacity_litres', 'status', 'availability']),
            'new_leads' => (clone $base)->where('assignment_status', 'assigned')->count(),
            'active_jobs' => (clone $base)->whereIn('assignment_status', ['accepted', 'in_transit', 'arrived'])->count(),
            'completed_jobs' => (clone $base)->whereIn('assignment_status', ['delivered', 'completed'])->count(),
            'litres_delivered' => (int) (clone $base)->whereIn('assignment_status', ['delivered', 'completed'])->sum('delivered_litres'),
            'pending_pricing' => \App\Models\TankerPricing::whereIn('vehicle_id', $p->vehicles()->pluck('id'))->where('approval_status', 'pending')->count(),
        ]);
    }

    // ---- Drivers (owner can maintain multiple drivers) ----

    public function drivers(Request $request)
    {
        return $this->ok($this->ownerOnly($request)->drivers()->with('user:id,mobile,status')->get());
    }

    public function storeDriver(Request $request)
    {
        $p = $this->ownerOnly($request);
        $data = $this->validateDriver($request);
        $driver = DB::transaction(function () use ($p, $data, $request) {
            $userId = null;
            if ($request->boolean('create_login')) {
                if (User::where('mobile', $data['mobile'])->exists()) throw new BusinessRuleException('A login already exists for this mobile number.', 422);
                $userId = User::create(['name' => $data['name'], 'mobile' => $data['mobile'], 'password' => $data['password'], 'role' => 'tanker', 'status' => 'active'])->id;
            }
            $driver = $p->drivers()->create(collect($data)->only('name', 'mobile', 'licence_number')->all() + ['user_id' => $userId]);
            if ($request->hasFile('licence_document')) $driver->update(['licence_document' => $request->file('licence_document')->store('drivers/' . $driver->id, 'local')]);
            return $driver;
        });
        return $this->created($driver, 'Driver added');
    }

    public function updateDriver(Request $request, DriverProfile $driver)
    {
        $p = $this->ownerOnly($request);
        abort_unless($driver->tanker_profile_id === $p->id, 404);
        $data = $this->validateDriver($request, true);
        $driver->update(collect($data)->only('name', 'mobile', 'licence_number', 'status')->all());
        if ($request->hasFile('licence_document')) $driver->update(['licence_document' => $request->file('licence_document')->store('drivers/' . $driver->id, 'local')]);
        if (isset($data['status']) && $driver->user_id && ! $driver->is_owner) {
            User::where('id', $driver->user_id)->update(['status' => $data['status'] === 'active' ? 'active' : 'inactive']);
        }
        return $this->ok($driver->fresh(), 'Driver updated');
    }

    private function validateDriver(Request $request, bool $update = false): array
    {
        if ($request->filled('mobile')) $request->merge(['mobile' => Mobile::normalize($request->mobile)]);
        return $request->validate([
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:120'],
            'mobile' => [$update ? 'sometimes' : 'required', 'string', 'max:20', fn ($a, $v, $f) => Mobile::isValid($v) ?: $f('Enter a valid mobile number.')],
            'licence_number' => 'nullable|string|max:40',
            'licence_document' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'status' => 'nullable|in:active,inactive',
            'create_login' => 'nullable|boolean',
            'password' => ['required_if:create_login,1,true', 'nullable', Password::min(8)->letters()->numbers()],
        ]);
    }
}
