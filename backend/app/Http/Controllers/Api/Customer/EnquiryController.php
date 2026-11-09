<?php

namespace App\Http\Controllers\Api\Customer;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    public function index(Request $request)
    {
        $q = $request->user()->enquiries()->latest();
        if ($request->filled('status')) $q->where('status', $request->status);
        return $this->ok($q->paginate($this->perPage()));
    }

    public function store(StoreEnquiryRequest $request)
    {
        $data = $request->safe()->except('save_address', 'address_label');
        $enquiry = $request->user()->enquiries()->create($data + ['status' => 'new']);
        $enquiry->refresh();
        if ($request->boolean('save_address')) $this->saveAddress($request);
        $this->notifier->notifyAdmins('New enquiry', "{$enquiry->enquiry_number} from {$request->user()->name} — {$enquiry->water_type}", 'enquiry.new', ['enquiry_id' => $enquiry->id]);
        return $this->created($enquiry, 'Enquiry submitted. Reference ' . $enquiry->enquiry_number);
    }

    public function show(Request $request, Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);
        $enquiry->load(['booking:id,booking_number,status', 'notesLog' => fn ($q) => $q->whereIn('type', ['quotation', 'status'])]);
        return $this->ok($enquiry);
    }

    public function acceptQuote(Request $request, Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);
        if ($enquiry->status !== 'quotation_sent') throw new BusinessRuleException('There is no quotation to accept on this enquiry.');
        $enquiry->update(['customer_accepted_at' => now()]);
        $enquiry->notesLog()->create(['user_id' => $request->user()->id, 'type' => 'status', 'note' => 'Customer accepted quotation']);
        $this->notifier->notifyAdmins('Enquiry quotation accepted', "{$enquiry->enquiry_number} — convert to booking.", 'enquiry.accepted', ['enquiry_id' => $enquiry->id]);
        return $this->ok($enquiry, 'Quotation accepted. We will convert this to a booking shortly.');
    }

    public function cancel(Request $request, Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];
        if (in_array($enquiry->status, ['converted', 'closed', 'cancelled'], true)) throw new BusinessRuleException('Enquiry is already ' . $enquiry->status . '.');
        $enquiry->update(['status' => 'cancelled', 'close_reason' => $reason]);
        $enquiry->notesLog()->create(['user_id' => $request->user()->id, 'type' => 'status', 'note' => 'Cancelled by customer: ' . $reason]);
        return $this->ok($enquiry, 'Enquiry cancelled');
    }

    private function saveAddress(Request $request): void
    {
        $user = $request->user();
        $user->addresses()->firstOrCreate(
            ['address' => $request->delivery_address],
            ['label' => $request->address_label ?: 'Saved', 'area' => $request->area, 'landmark' => $request->landmark, 'latitude' => $request->latitude, 'longitude' => $request->longitude, 'is_default' => ! $user->addresses()->exists()]
        );
    }
}
