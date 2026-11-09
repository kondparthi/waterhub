<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to) : now()->endOfMonth();
        return [$from->toDateString(), $to->toDateString()];
    }

    public function summary(Request $request)
    {
        [$from, $to] = $this->range($request);
        $type = $request->get('type', 'overview');
        $base = fn () => Booking::whereBetween('preferred_date', [$from, $to]);

        $data = match ($type) {
            'daily' => $base()->selectRaw("preferred_date as day, count(*) bookings, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) completed, SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) cancelled, COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN quantity_litres ELSE 0 END),0) litres, COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','rejected') THEN total_amount ELSE 0 END),0) value")
                ->groupBy('preferred_date')->orderBy('preferred_date')->get(),
            'status' => $base()->selectRaw('status, count(*) bookings, COALESCE(SUM(total_amount),0) value')->groupBy('status')->get(),
            'sources' => $base()->whereNotIn('bookings.status', ['cancelled', 'rejected'])->leftJoin('borewells', 'borewells.id', '=', 'bookings.borewell_id')
                ->selectRaw("COALESCE(borewells.name,'Not selected') source, count(bookings.id) bookings, COALESCE(SUM(bookings.quantity_litres),0) litres, COALESCE(SUM(bookings.total_amount),0) revenue")
                ->groupBy('borewells.name')->orderByDesc('litres')->get(),
            'tankers' => BookingAssignment::join('bookings', 'bookings.id', '=', 'booking_assignments.booking_id')->join('vehicles', 'vehicles.id', '=', 'booking_assignments.vehicle_id')
                ->whereBetween('bookings.preferred_date', [$from, $to])
                ->selectRaw("vehicles.registration_number vehicle, vehicles.capacity_litres capacity, count(*) assignments, SUM(CASE WHEN assignment_status IN ('delivered','completed') THEN 1 ELSE 0 END) completed, SUM(CASE WHEN assignment_status='rejected' THEN 1 ELSE 0 END) rejected, COALESCE(SUM(CASE WHEN assignment_status IN ('delivered','completed') THEN booking_assignments.delivered_litres ELSE 0 END),0) litres")
                ->groupBy('vehicles.registration_number', 'vehicles.capacity_litres')->orderByDesc('litres')->get(),
            'customers' => $base()->join('users', 'users.id', '=', 'bookings.customer_id')
                ->selectRaw("users.name customer, users.mobile, count(bookings.id) bookings, SUM(CASE WHEN bookings.status='completed' THEN 1 ELSE 0 END) completed, COALESCE(SUM(CASE WHEN bookings.status NOT IN ('cancelled','rejected') THEN bookings.total_amount ELSE 0 END),0) value, COALESCE(SUM(bookings.amount_paid),0) paid")
                ->groupBy('users.name', 'users.mobile')->orderByDesc('value')->get(),
            'cancellations' => $base()->where('status', 'cancelled')->with('customer:id,name,mobile')->orderByDesc('cancelled_at')
                ->get(['id', 'booking_number', 'customer_id', 'preferred_date', 'cancelled_at', 'cancel_reason', 'cancellation_fee', 'total_amount']),
            'payments' => [
                'collected' => Payment::where('status', 'received')->whereBetween(DB::raw('DATE(paid_at)'), [$from, $to])->selectRaw('method, count(*) payments, SUM(amount) amount')->groupBy('method')->get(),
                'outstanding' => Booking::whereIn('status', ['delivered', 'completed'])->whereRaw('total_amount > amount_paid')->with('customer:id,name,mobile')
                    ->orderBy('preferred_date')->get(['id', 'booking_number', 'customer_id', 'preferred_date', 'total_amount', 'amount_paid', 'payment_status']),
            ],
            default => [
                'bookings' => $base()->count(),
                'completed' => $base()->where('status', 'completed')->count(),
                'cancelled' => $base()->where('status', 'cancelled')->count(),
                'litres_delivered' => (int) $base()->whereIn('status', ['delivered', 'completed'])->sum(DB::raw('COALESCE(delivered_litres, quantity_litres)')),
                'booking_value' => (float) $base()->whereNotIn('status', ['cancelled', 'rejected'])->sum('total_amount'),
                'collected' => (float) Payment::where('status', 'received')->whereBetween(DB::raw('DATE(paid_at)'), [$from, $to])->sum('amount'),
                'outstanding' => (float) Booking::whereIn('status', ['delivered', 'completed'])->sum(DB::raw('total_amount - amount_paid')),
            ],
        };

        if ($request->get('format') === 'csv') return $this->csv($type, $data, $from, $to);
        return $this->ok($data, 'OK', 200, ['from' => $from, 'to' => $to, 'type' => $type]);
    }

    private function csv(string $type, $data, string $from, string $to)
    {
        $rows = $type === 'payments' ? collect($data['outstanding']) : collect($data instanceof \Illuminate\Support\Collection ? $data : [$data]);
        $rows = $rows->map(fn ($r) => collect(is_array($r) ? $r : $r->toArray())->map(fn ($v) => is_array($v) ? ($v['name'] ?? json_encode($v)) : $v)->all());
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) fputcsv($out, array_keys($rows->first()));
            foreach ($rows as $r) fputcsv($out, array_values($r));
            fclose($out);
        }, "report-{$type}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }
}
