import { dateTime, label, litres, money } from '../utils/format';
import { StatusBadge } from './ui';

export function PriceBreakdown({ b }) {
  const item = b.items?.[0];
  if (!b.total_amount || Number(b.total_amount) === 0) return <div className="text-muted small">Quotation pending.</div>;
  return (
    <div>
      {item && (
        <div className="small text-muted mb-2">
          {item.pricing_type === 'per_litre'
            ? <>{litres(item.billable_litres)} × {money(item.rate_snapshot).replace('.00', '')}/L{item.billable_litres > item.quantity_litres && ' (minimum quantity)'}</>
            : <>{item.trips} trip{item.trips > 1 ? 's' : ''} × {money(item.rate_snapshot)}{item.vehicle_capacity && ` · ${litres(item.vehicle_capacity)} tanker`}</>}
          {item.trip_override_reason && <div>Trips adjusted: {item.trip_override_reason}</div>}
        </div>
      )}
      <table className="table table-sm mb-0">
        <tbody>
          <tr><td>Water charge</td><td className="text-end">{money(b.subtotal)}</td></tr>
          {Number(b.delivery_charge) > 0 && <tr><td>Delivery charge</td><td className="text-end">{money(b.delivery_charge)}</td></tr>}
          {Number(b.discount) > 0 && <tr><td>Discount</td><td className="text-end">− {money(b.discount)}</td></tr>}
          {Number(b.tax) > 0 && <tr><td>Tax</td><td className="text-end">{money(b.tax)}</td></tr>}
          <tr className="fw-bold"><td>Total</td><td className="text-end">{money(b.total_amount)}</td></tr>
          {Number(b.amount_paid) > 0 && <tr><td>Paid</td><td className="text-end">{money(b.amount_paid)}</td></tr>}
          {b.balance_due > 0 && ['delivered', 'completed'].includes(b.status) && <tr className="text-danger fw-bold"><td>Balance due</td><td className="text-end">{money(b.balance_due)}</td></tr>}
        </tbody>
      </table>
      {b.quotation_notes && <div className="small mt-2 text-muted"><i className="bi bi-info-circle me-1" />{b.quotation_notes}</div>}
    </div>
  );
}

export function StatusHistory({ items = [] }) {
  return (
    <ul className="timeline">
      {[...items].reverse().map((h) => (
        <li key={h.id}>
          <div className="d-flex justify-content-between gap-2"><StatusBadge status={h.new_status} /><small className="text-muted">{dateTime(h.created_at)}</small></div>
          {h.notes && <div className="mt-1">{h.notes}</div>}
          {h.actor && <div className="small text-muted">by {h.actor.name} ({h.actor.role})</div>}
        </li>
      ))}
    </ul>
  );
}

/** Progress strip customers understand at a glance. */
const STEPS = [['new', 'Requested'], ['quoted', 'Quoted'], ['confirmed', 'Confirmed'], ['assigned', 'Tanker assigned'], ['in_transit', 'On the way'], ['delivered', 'Delivered']];
const ORDER = ['new', 'under_review', 'quoted', 'confirmed', 'assigned', 'accepted', 'in_transit', 'arrived', 'delivered', 'completed'];
export function ProgressSteps({ status }) {
  if (['cancelled', 'rejected'].includes(status)) return null;
  const idx = ORDER.indexOf(status);
  return (
    <div className="d-flex gap-1 mb-3" aria-label={`Progress: ${label(status)}`}>
      {STEPS.map(([s, l]) => {
        const done = idx >= ORDER.indexOf(s);
        return (
          <div key={s} className="flex-fill text-center">
            <div style={{ height: 6, borderRadius: 3, background: done ? 'var(--wt-aqua)' : 'var(--wt-line)' }} />
            <div className={`d-none d-sm-block small mt-1 ${done ? 'fw-semibold' : 'text-muted'}`} style={{ fontSize: '.7rem' }}>{l}</div>
          </div>
        );
      })}
    </div>
  );
}

export function GpsState({ location, trackable }) {
  if (!trackable) return <span className="small text-muted"><span className="gps-dot gps-off me-1" />Tracking starts when the driver begins the trip</span>;
  if (!location) return <span className="small text-warning"><span className="gps-dot gps-stale me-1" />Waiting for the driver's first GPS update…</span>;
  return location.stale
    ? <span className="small text-warning fw-semibold"><span className="gps-dot gps-stale me-1" />GPS not updating — last seen {Math.round(location.age_seconds / 60)} min ago</span>
    : <span className="small text-success fw-semibold"><span className="gps-dot gps-live me-1" />Live · updated {location.age_seconds}s ago</span>;
}
