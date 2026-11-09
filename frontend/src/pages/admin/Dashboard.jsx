import { Link } from 'react-router-dom';
import { useApi } from '../../hooks/useApi';
import { ErrorBox, Loading, PageHeader, Stat } from '../../components/ui';
import { date, litres, money } from '../../utils/format';

export default function AdminDashboard() {
  const { data: d, loading, error, reload } = useApi('/admin/dashboard', null, { poll: 30 });
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const s = d.bookings || {};
  const att = d.attention;
  return (
    <div>
      <PageHeader title="Dashboard" subtitle="Today's operations at a glance">
        <Link to="/admin/live" className="btn btn-primary btn-sm"><i className="bi bi-broadcast me-1" />Live map</Link>
      </PageHeader>
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3"><Stat label="New bookings" value={s.new || 0} icon="inbox" to="/admin/bookings?status=new" tone="warning" /></div>
        <div className="col-6 col-md-3"><Stat label="Awaiting assignment" value={s.confirmed || 0} icon="truck" to="/admin/bookings?status=confirmed" /></div>
        <div className="col-6 col-md-3"><Stat label="On the way" value={d.deliveries.in_transit} icon="geo-alt" to="/admin/live" tone="success" /></div>
        <div className="col-6 col-md-3"><Stat label="Completed today" value={d.deliveries.completed_today} icon="patch-check" tone="success" /></div>
      </div>
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3"><Stat label="Booking value (month)" value={money(d.value.month)} icon="graph-up" /></div>
        <div className="col-6 col-md-3"><Stat label="Collected (month)" value={money(d.collected.month)} icon="wallet2" tone="success" /></div>
        <div className="col-6 col-md-3"><Stat label="Collected today" value={money(d.collected.today)} icon="cash" tone="success" /></div>
        <div className="col-6 col-md-3"><Stat label="Value this week" value={money(d.value.week)} icon="calendar-week" /></div>
      </div>
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-2"><Stat label="New enquiries" value={d.enquiries.new} to="/admin/enquiries?status=new" /></div>
        <div className="col-6 col-md-2"><Stat label="Follow-ups" value={d.enquiries.awaiting_follow_up} to="/admin/enquiries" /></div>
        <div className="col-6 col-md-2"><Stat label="Tanker approvals" value={d.tankers.pending_profiles} to="/admin/tankers?status=pending_review" tone="warning" /></div>
        <div className="col-6 col-md-2"><Stat label="Vehicle approvals" value={d.tankers.pending_vehicles} to="/admin/vehicles?status=pending_review" tone="warning" /></div>
        <div className="col-6 col-md-2"><Stat label="Rate approvals" value={d.tankers.pending_pricing} to="/admin/pricing" tone="warning" /></div>
        <div className="col-6 col-md-2"><Stat label="Vehicles available" value={`${d.tankers.available_now}/${d.tankers.active_vehicles}`} to="/admin/vehicles" /></div>
      </div>
      <div className="row g-3">
        <Attention title="New bookings to review" rows={att.new_bookings} render={(b) => <><b>{b.booking_number}</b> · {litres(b.quantity_litres)} · {b.area} · {date(b.preferred_date)}</>} link={(b) => `/admin/bookings/${b.id}`} />
        <Attention title="Confirmed — assign a tanker" rows={att.awaiting_assignment} render={(b) => <><b>{b.booking_number}</b> · {litres(b.quantity_litres)} · {b.area} · {date(b.preferred_date)}</>} link={(b) => `/admin/bookings/${b.id}`} />
        <Attention title="Driver response overdue" tone="danger" rows={att.overdue_driver_response} render={(a) => <><b>{a.booking?.booking_number}</b> · {a.vehicle?.registration_number} — reassign?</>} link={(a) => `/admin/bookings/${a.booking_id}`} />
        <Attention title="Delivered — verify & close" rows={att.delivered_to_verify} render={(b) => <><b>{b.booking_number}</b> · {litres(b.delivered_litres)} · {b.area}</>} link={(b) => `/admin/bookings/${b.id}`} />
      </div>
      <div className="small text-muted mt-3">Customers: {d.customers.total} total · {d.customers.active} active · {d.customers.new_this_week} new this week</div>
    </div>
  );
}

function Attention({ title, rows, render, link, tone }) {
  return (
    <div className="col-lg-6">
      <div className={`card h-100 ${tone === 'danger' && rows.length ? 'border-danger' : ''}`}>
        <div className="card-header d-flex justify-content-between">{title}<span className={`badge ${rows.length ? 'bg-' + (tone || 'primary') : 'bg-light text-muted'}`}>{rows.length}</span></div>
        <div className="list-group list-group-flush">
          {rows.length === 0 ? <div className="list-group-item small text-muted">All clear</div> : rows.map((r) => <Link key={r.id} to={link(r)} className="list-group-item list-group-item-action small">{render(r)}</Link>)}
        </div>
      </div>
    </div>
  );
}
