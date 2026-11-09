import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { get, openFile, post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, ErrorBox, Field, KV, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { GpsState, PriceBreakdown, StatusHistory } from '../../components/BookingParts';
import { TrackingMap } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, dateTime, label, litres, money } from '../../utils/format';

function SourceStep({ b, onDone }) {
  const { data, loading } = useApi(`/admin/bookings/${b.id}/sources`);
  const toast = useToast();
  const pick = async (id) => { try { await post(`/admin/bookings/${b.id}/source`, { borewell_id: id }); toast('Source selected'); onDone(); } catch (e) { toast(e.message, 'danger'); } };
  if (loading) return <Loading />;
  return (
    <div className="list-group">
      {data.map((s) => (
        <button key={s.id} disabled={!s.selectable} onClick={() => pick(s.id)} className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${b.borewell_id === s.id ? 'active' : ''}`}>
          <div className="text-start">
            <div className="fw-semibold"><i className={`bi bi-${s.source_type === 'bavi' ? 'water' : 'droplet'} me-1`} />{s.name} <small className="opacity-75">({s.source_code})</small></div>
            <div className="small opacity-75">{s.area} · {s.distance_km} km from customer{s.remaining_litres != null && ` · ${litres(s.remaining_litres)} left`}{s.opening_time && ` · ${s.opening_time.slice(0, 5)}–${s.closing_time?.slice(0, 5)}`}</div>
            {s.issues.length > 0 && <div className="small text-danger">{s.issues.join(' · ')}</div>}
          </div>
          {b.borewell_id === s.id ? <i className="bi bi-check-circle-fill" /> : s.selectable && <span className="btn btn-sm btn-outline-primary">Select</span>}
        </button>
      ))}
    </div>
  );
}

function QuoteStep({ b, onDone }) {
  const [vehicles, setVehicles] = useState(null);
  const [f, setF] = useState({ pricing_type: 'per_litre', vehicle_id: '', rate: '', vehicle_capacity: '', delivery_charge: '', discount: '', quantity_litres: b.quantity_litres, trips_override: '', trip_override_reason: '', quotation_notes: '' });
  const [preview, setPreview] = useState(null);
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const toast = useToast();
  useEffect(() => { get(`/admin/bookings/${b.id}/eligible-tankers`).then((r) => setVehicles(r.data.eligible)).catch(() => setVehicles([])); }, [b.id]);
  const on = (e) => { setF({ ...f, [e.target.name]: e.target.value }); setPreview(null); };
  const payload = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
  const run = async (isPreview) => {
    setBusy(true); setErrors({});
    try {
      const r = await post(`/admin/bookings/${b.id}/quote`, { ...payload(), preview: isPreview });
      if (isPreview) setPreview(r.data); else { toast(r.message); onDone(); }
    } catch (e) { setErrors(e.errors); toast(e.message, 'danger'); } finally { setBusy(false); }
  };
  const manual = !f.vehicle_id;
  return (
    <div>
      <div className="row g-2">
        <div className="col-sm-6"><Field label="Pricing method" name="pricing_type" as="select" value={f.pricing_type} onChange={on} errors={errors}><option value="per_litre">Per litre</option><option value="per_trip">Per trip</option></Field></div>
        <div className="col-sm-6">
          <Field label="Rate from vehicle" name="vehicle_id" as="select" value={f.vehicle_id} onChange={on} errors={errors} hint={vehicles && !vehicles.length ? 'No eligible vehicles — enter a manual rate.' : null}>
            <option value="">Manual rate</option>
            {(vehicles || []).filter((v) => v.prices[f.pricing_type]).map((v) => <option key={v.vehicle_id} value={v.vehicle_id}>{v.registration_number} · {litres(v.capacity_litres)} · {f.pricing_type === 'per_litre' ? `₹${v.prices.per_litre.rate}/L` : `${money(v.prices.per_trip.rate)}/trip`} · ≈{money(v.prices[f.pricing_type].estimated_total)}</option>)}
          </Field>
        </div>
      </div>
      {manual && (
        <div className="row g-2">
          <div className="col-6 col-sm-4"><Field label={f.pricing_type === 'per_litre' ? 'Rate ₹/litre' : 'Rate ₹/trip'} name="rate" type="number" step="0.01" value={f.rate} onChange={on} errors={errors} /></div>
          <div className="col-6 col-sm-4"><Field label="Tanker capacity (L)" name="vehicle_capacity" type="number" value={f.vehicle_capacity} onChange={on} errors={errors} hint={f.pricing_type === 'per_trip' ? 'Required for per-trip' : 'Optional'} /></div>
        </div>
      )}
      <div className="row g-2">
        <div className="col-6 col-sm-3"><Field label="Quantity (L)" name="quantity_litres" type="number" value={f.quantity_litres} onChange={on} errors={errors} /></div>
        <div className="col-6 col-sm-3"><Field label="Delivery ₹" name="delivery_charge" type="number" step="0.01" value={f.delivery_charge} onChange={on} errors={errors} hint={!manual ? 'Blank = vehicle default' : null} /></div>
        <div className="col-6 col-sm-3"><Field label="Discount ₹" name="discount" type="number" step="0.01" value={f.discount} onChange={on} errors={errors} /></div>
        <div className="col-6 col-sm-3"><Field label="Trips override" name="trips_override" type="number" value={f.trips_override} onChange={on} errors={errors} /></div>
      </div>
      {f.trips_override && <Field label="Reason for trip override" name="trip_override_reason" value={f.trip_override_reason} onChange={on} errors={errors} />}
      <Field label="Note to customer (optional)" name="quotation_notes" value={f.quotation_notes} onChange={on} errors={errors} />
      {preview && (
        <div className="alert alert-light border">
          <div className="d-flex justify-content-between"><span>{preview.pricing_type === 'per_litre' ? `${litres(preview.billable_litres)} × ₹${preview.rate_snapshot}` : `${preview.trips} trip(s) × ${money(preview.rate_snapshot)}`}</span><span>{money(preview.subtotal)}</span></div>
          {preview.delivery_charge > 0 && <div className="d-flex justify-content-between"><span>Delivery</span><span>{money(preview.delivery_charge)}</span></div>}
          {preview.discount > 0 && <div className="d-flex justify-content-between"><span>Discount</span><span>− {money(preview.discount)}</span></div>}
          {preview.tax > 0 && <div className="d-flex justify-content-between"><span>Tax ({preview.tax_percent}%)</span><span>{money(preview.tax)}</span></div>}
          <div className="d-flex justify-content-between fw-bold border-top pt-1 mt-1"><span>Total</span><span>{money(preview.total_amount)}</span></div>
          {preview.calculated_trips !== preview.trips && <div className="small text-warning">Calculated trips: {preview.calculated_trips}</div>}
        </div>
      )}
      <div className="d-flex gap-2">
        <button className="btn btn-outline-primary" disabled={busy} onClick={() => run(true)}>Preview price</button>
        <button className="btn btn-primary" disabled={busy} onClick={() => run(false)}>Send quotation</button>
      </div>
    </div>
  );
}

function AssignStep({ b, onDone, reassign }) {
  const { data, loading, error } = useApi(`/admin/bookings/${b.id}/eligible-tankers`);
  const [showEx, setShowEx] = useState(false);
  const [pick, setPick] = useState(null);
  const toast = useToast();
  if (loading) return <Loading text="Finding nearest eligible tankers…" />;
  if (error) return <ErrorBox error={error} />;
  return (
    <div>
      {data.eligible.length === 0 && <div className="alert alert-warning">No eligible tankers for this booking. See excluded vehicles below.</div>}
      <div className="table-responsive">
        <table className="table table-sm align-middle">
          <thead><tr><th>Vehicle</th><th>Owner / driver</th><th>Capacity</th><th>Distance</th><th>Price</th><th>GPS</th><th /></tr></thead>
          <tbody>
            {data.eligible.map((v, i) => (
              <tr key={v.vehicle_id}>
                <td className="fw-semibold text-nowrap">{i === 0 && <span className="badge bg-info text-dark me-1">Nearest</span>}{v.registration_number}<div><StatusBadge status={v.availability} /></div></td>
                <td className="small">{v.owner?.business_name || v.owner?.name}<div className="text-muted">{v.driver?.name} {v.driver?.mobile}</div></td>
                <td className="text-nowrap">{litres(v.capacity_litres)}<div className="small text-muted">{v.trips_needed} trip(s)</div></td>
                <td className="text-nowrap">{v.distance_km != null ? `${v.distance_km} km` : '—'}<div className="small text-muted">{v.location_source === 'gps' ? 'from GPS' : v.location_source === 'base' ? 'from base' : ''}</div></td>
                <td className="small text-nowrap">{v.prices.per_litre && <div>₹{v.prices.per_litre.rate}/L ≈ {money(v.prices.per_litre.estimated_total)}</div>}{v.prices.per_trip && <div>{money(v.prices.per_trip.rate)}/trip ≈ {money(v.prices.per_trip.estimated_total)}</div>}</td>
                <td className="small text-nowrap">{v.last_gps_at ? dateTime(v.last_gps_at) : '—'}</td>
                <td><button className="btn btn-sm btn-primary" onClick={() => setPick(v)}>Assign</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {data.excluded.length > 0 && (
        <div>
          <button className="btn btn-link btn-sm px-0" onClick={() => setShowEx(!showEx)}>{showEx ? 'Hide' : 'Show'} {data.excluded.length} excluded vehicle(s)</button>
          {showEx && <ul className="small text-muted">{data.excluded.map((v) => <li key={v.vehicle_id}><b>{v.registration_number}</b> ({litres(v.capacity_litres)}): {v.reasons.join('; ')}</li>)}</ul>}
        </div>
      )}
      <Confirm show={!!pick} title={reassign ? 'Reassign booking?' : 'Assign tanker?'} confirmText={reassign ? 'Reassign' : 'Assign'} tone={reassign ? 'warning' : 'primary'}
        message={pick && `${pick.registration_number} (${litres(pick.capacity_litres)}) — the driver will be asked to accept.${reassign ? ' The current assignment will be withdrawn and kept in history.' : ''}`}
        onClose={() => setPick(null)} onConfirm={() => post(`/admin/bookings/${b.id}/assign`, { vehicle_id: pick.vehicle_id }).then((r) => { toast(r.message); onDone(); })} />
    </div>
  );
}

function PaymentForm({ b, onDone }) {
  const [f, setF] = useState({ amount: b.balance_due || '', method: 'cash', transaction_reference: '', notes: '' });
  const [errors, setErrors] = useState({});
  const toast = useToast();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });
  const submit = async (e) => {
    e.preventDefault(); setErrors({});
    try { const r = await post('/admin/payments', { ...f, booking_id: b.id }); toast(r.message); onDone(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };
  return (
    <form onSubmit={submit} className="row g-2 align-items-end">
      <div className="col-6"><Field className="" label="Amount ₹" name="amount" type="number" step="0.01" value={f.amount} onChange={on} errors={errors} required /></div>
      <div className="col-6"><Field className="" label="Method" name="method" as="select" value={f.method} onChange={on} errors={errors}><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></Field></div>
      <div className="col-8"><Field className="" label="Reference / UTR" name="transaction_reference" value={f.transaction_reference} onChange={on} errors={errors} /></div>
      <div className="col-4"><button className="btn btn-success w-100 text-nowrap">Record</button></div>
    </form>
  );
}

export default function AdminBookingDetail() {
  const { id } = useParams();
  const { data: b, loading, error, reload } = useApi(`/bookings/${id}`, null, { poll: 20 });
  const live = b && ['accepted', 'in_transit', 'arrived'].includes(b.status);
  const { data: track } = useApi(live ? `/bookings/${id}/tracking` : null, null, { poll: 8 });
  const [panel, setPanel] = useState(null);
  const [modal, setModal] = useState(null);
  const [offline, setOffline] = useState(false);
  const toast = useToast();

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const done = () => { setPanel(null); reload(); };
  const act = (path, body, msg) => post(`/admin/bookings/${id}/${path}`, body).then((r) => { toast(r.message || msg); reload(); });
  const s = b.status;
  const a = b.current_assignment;
  const preAssign = ['new', 'under_review', 'quoted', 'confirmed'].includes(s);

  // The single next action an operator should take.
  const next = {
    new: ['Start review', () => act('review', null, 'Under review')],
    under_review: [b.borewell_id ? 'Prepare quotation' : 'Select water source', () => setPanel(b.borewell_id ? 'quote' : 'source')],
    quoted: ['Confirm booking', () => setModal('confirm')],
    confirmed: ['Assign tanker', () => setPanel('assign')],
    delivered: ['Verify & complete', () => setModal('complete')],
  }[s];

  return (
    <div>
      <PageHeader title={b.booking_number} back="/admin/bookings" subtitle={`Created ${dateTime(b.created_at)}${b.enquiry ? ' · from ' + b.enquiry.enquiry_number : ''}`}>
        <StatusBadge status={s} /><StatusBadge status={b.payment_status} />
      </PageHeader>

      <div className="row g-3">
        <div className="col-lg-7">
          <div className="card mb-3 border-primary">
            <div className="card-body">
              <div className="d-flex flex-wrap gap-2 align-items-center">
                {next && <button className="btn btn-primary" onClick={next[1]}><i className="bi bi-arrow-right-circle me-1" />{next[0]}</button>}
                {preAssign && s !== 'new' && <button className="btn btn-outline-secondary btn-sm" onClick={() => setPanel(panel === 'source' ? null : 'source')}>{b.borewell_id ? 'Change source' : 'Select source'}</button>}
                {preAssign && b.borewell_id && <button className="btn btn-outline-secondary btn-sm" onClick={() => setPanel(panel === 'quote' ? null : 'quote')}>{Number(b.total_amount) > 0 ? 'Re-quote' : 'Quote'}</button>}
                {['assigned', 'accepted'].includes(s) && <button className="btn btn-outline-warning btn-sm" onClick={() => setPanel('reassign')}>Reassign</button>}
                {['new', 'under_review', 'quoted'].includes(s) && <button className="btn btn-outline-danger btn-sm" onClick={() => setModal('reject')}>Reject</button>}
                {b.can_cancel && <button className="btn btn-outline-danger btn-sm" onClick={() => setModal('cancel')}>Cancel</button>}
              </div>
              {s === 'quoted' && !b.customer_accepted_at && <div className="small text-muted mt-2"><i className="bi bi-hourglass me-1" />Waiting for customer to accept the quotation in the app, or confirm on their behalf.</div>}
              {s === 'assigned' && a && <div className={`small mt-2 ${a.response_overdue ? 'text-danger fw-semibold' : 'text-muted'}`}><i className="bi bi-clock me-1" />Waiting for driver{a.response_due_at && ` — respond by ${dateTime(a.response_due_at)}`}{a.response_overdue && ' (overdue — consider reassigning)'}</div>}
            </div>
          </div>

          {panel && (
            <div className="card mb-3">
              <div className="card-header d-flex justify-content-between">{{ source: 'Select water source', quote: 'Prepare quotation', assign: 'Assign tanker — nearest eligible first', reassign: 'Reassign to another tanker' }[panel]}<button className="btn-close" onClick={() => setPanel(null)} /></div>
              <div className="card-body">
                {panel === 'source' && <SourceStep b={b} onDone={() => { reload(); setPanel(s === 'under_review' || s === 'new' ? 'quote' : null); }} />}
                {panel === 'quote' && <QuoteStep b={b} onDone={done} />}
                {(panel === 'assign' || panel === 'reassign') && <AssignStep b={b} onDone={done} reassign={panel === 'reassign'} />}
              </div>
            </div>
          )}

          {live && (
            <div className="card mb-3">
              <div className="card-header d-flex justify-content-between align-items-center"><span>Live tracking</span><GpsState location={track?.location} trackable={track?.trackable} /></div>
              <div className="card-body p-2"><TrackingMap data={track} /></div>
            </div>
          )}

          <div className="card mb-3">
            <div className="card-header">Request</div>
            <div className="card-body">
              <KV items={[
                ['Customer', <Link to={`/admin/customers/${b.customer?.id}`}>{b.customer?.name}</Link>],
                ['Mobile', <a href={`tel:${b.customer?.mobile}`}>{b.customer?.mobile}</a>],
                ['Water', `${litres(b.quantity_litres)} · ${b.water_type}`],
                b.requested_capacity && ['Requested tanker', litres(b.requested_capacity)],
                ['Delivery', `${date(b.preferred_date)} ${b.preferred_time_slot || ''}`],
                ['Address', <>{b.delivery_address} <a className="small" target="_blank" rel="noreferrer" href={`https://www.google.com/maps?q=${b.latitude},${b.longitude}`}>map ↗</a></>],
                b.landmark && ['Landmark', b.landmark],
                b.notes && ['Notes', b.notes],
                ['Source', b.borewell ? `${b.borewell.name} (${b.borewell.source_code})` : <span className="text-warning">Not selected</span>],
                b.customer_accepted_at && ['Customer accepted', dateTime(b.customer_accepted_at)],
                s === 'cancelled' && ['Cancelled', `${b.cancel_reason} · fee ${money(b.cancellation_fee)}`],
              ]} />
            </div>
          </div>

          <div className="card mb-3">
            <div className="card-header">Assignments ({b.assignments?.length || 0})</div>
            <div className="list-group list-group-flush">
              {!b.assignments?.length && <div className="list-group-item small text-muted">Not assigned yet.</div>}
              {b.assignments?.map((x) => (
                <div key={x.id} className="list-group-item small">
                  <div className="d-flex justify-content-between"><b>{x.vehicle?.registration_number}</b><StatusBadge status={x.assignment_status} /></div>
                  <div className="text-muted">Driver {x.driver?.name || '—'} {x.driver?.mobile} · assigned {dateTime(x.assigned_at)} by {x.assigned_by?.name}</div>
                  {x.rejection_reason && <div className="text-danger">Reason: {x.rejection_reason}</div>}
                  {x.delivered_litres && <div>Delivered {litres(x.delivered_litres)} {x.proof_notes && `— ${x.proof_notes}`}</div>}
                  {x.has_proof && <button className="btn btn-link btn-sm p-0" onClick={() => openFile(`/files/assignments/${x.id}/proof`)}>View proof photo</button>}
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="col-lg-5">
          <div className="card mb-3"><div className="card-header">Price</div><div className="card-body"><PriceBreakdown b={b} /></div></div>
          <div className="card mb-3">
            <div className="card-header">Payments</div>
            <div className="card-body">
              {b.payments?.map((p) => <div key={p.id} className="d-flex justify-content-between small border-bottom py-1"><span>{dateTime(p.paid_at)} · {label(p.method)} {p.transaction_reference && `· ${p.transaction_reference}`}</span><b>{money(p.amount)}</b></div>)}
              {!b.payments?.length && <div className="small text-muted mb-2">No payments recorded.</div>}
              {s !== 'rejected' && (Number(b.total_amount) > 0 || Number(b.cancellation_fee) > 0) && b.payment_status !== 'paid' && <div className="mt-3"><PaymentForm b={b} onDone={reload} /></div>}
            </div>
          </div>
          <div className="card"><div className="card-header">Status history</div><div className="card-body"><StatusHistory items={b.status_history} /></div></div>
        </div>
      </div>

      <Confirm show={modal === 'confirm'} title="Confirm booking" confirmText="Confirm" onClose={() => setModal(null)}
        message={`Total ${money(b.total_amount)} · source ${b.borewell?.name || '—'}`}
        onConfirm={(notes) => act('confirm', { customer_accepted_offline: offline, notes }, 'Confirmed')}>
        {!b.customer_accepted_at && <div className="form-check"><input className="form-check-input" type="checkbox" id="off" checked={offline} onChange={(e) => setOffline(e.target.checked)} /><label className="form-check-label" htmlFor="off">Customer accepted the quotation by phone / offline</label></div>}
      </Confirm>
      <Confirm show={modal === 'complete'} title="Verify delivery and close?" message={`Driver reported ${litres(b.delivered_litres)} delivered.`} confirmText="Complete booking" tone="success" onClose={() => setModal(null)} onConfirm={() => act('complete', null, 'Completed')} />
      <Confirm show={modal === 'reject'} title="Reject booking?" tone="danger" requireReason confirmText="Reject" onClose={() => setModal(null)} onConfirm={(reason) => act('reject', { reason }, 'Rejected')} />
      <CancelModal show={modal === 'cancel'} b={b} onClose={() => setModal(null)} onConfirm={(reason, fee) => act('cancel', { reason, cancellation_fee: fee }, 'Cancelled')} />
    </div>
  );
}

function CancelModal({ show, b, onClose, onConfirm }) {
  const [fee, setFee] = useState(b.cancellation_fee_if_cancelled);
  return (
    <Confirm show={show} title="Cancel booking?" tone="danger" requireReason confirmText="Cancel booking" onClose={onClose} onConfirm={(reason) => onConfirm(reason, fee)}
      message={b.current_assignment ? `The active assignment (${b.current_assignment.vehicle}) will be cancelled and the driver notified.` : null}>
      <label className="form-label">Cancellation fee ₹</label>
      <input type="number" className="form-control mb-3" value={fee} onChange={(e) => setFee(e.target.value)} />
    </Confirm>
  );
}
