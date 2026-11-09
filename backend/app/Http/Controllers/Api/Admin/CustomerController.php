<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = User::where('role', 'customer')->with('customerProfile:id,user_id,area')->withCount('bookings', 'enquiries')->latest();
        if ($s = $request->get('search')) $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from')) $q->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('created_at', '<=', $request->to);
        return $this->ok($q->paginate($this->perPage()));
    }

    public function show(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);
        $customer->load('customerProfile', 'addresses');
        return $this->ok([
            'customer' => $customer,
            'bookings' => $customer->bookings()->latest()->limit(50)->get(),
            'enquiries' => $customer->enquiries()->latest()->limit(50)->get(),
            'totals' => [
                'bookings' => $customer->bookings()->count(),
                'completed' => $customer->bookings()->where('status', 'completed')->count(),
                'value' => (float) $customer->bookings()->where('status', 'completed')->sum('total_amount'),
                'outstanding' => (float) $customer->bookings()->whereIn('status', ['delivered', 'completed'])->selectRaw('COALESCE(SUM(total_amount - amount_paid),0) v')->value('v'),
            ],
        ]);
    }

    public function updateStatus(Request $request, User $customer)
    {
        abort_unless($customer->isCustomer(), 404);
        $data = $request->validate(['status' => 'required|in:active,suspended,inactive', 'reason' => 'required_unless:status,active|nullable|string|max:255']);
        $before = $customer->only('status', 'status_reason');
        $customer->update(['status' => $data['status'], 'status_reason' => $data['reason'] ?? null]);
        if ($data['status'] !== 'active') $customer->tokens()->delete();
        AuditLogger::log('customer.status_changed', $customer, $before, $customer->only('status', 'status_reason'));
        return $this->ok($customer, 'Customer status updated');
    }
}
