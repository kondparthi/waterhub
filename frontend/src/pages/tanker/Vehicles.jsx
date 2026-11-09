import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { get, openFile, post, toForm } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { useAuth } from '../../context/AuthContext';
import { Empty, ErrorBox, Field, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { MapPicker } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, litres, money, today } from '../../utils/format';

const blank = { registration_number: '', capacity_litres: '', vehicle_type: 'water_tanker', driver_profile_id: '', service_areas: '', service_radius_km: 25, water_types: [], base_latitude: null, base_longitude: null, image: null, rc_document: null };

function VehicleForm({ vehicle, onDone, onCancel }) {
  const { meta, user } = useAuth();
  const [f, setF] = useState(vehicle ? { ...blank, ...vehicle, service_areas: (vehicle.service_areas || []).join(', '), water_types: vehicle.water_types || [], driver_profile_id: vehicle.driver_profile_id || '', image: null, rc_document: null } : blank);
  const [drivers, setDrivers] = useState([]);
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const toast = useToast();
  useEffect(() => { if (user.tanker?.is_owner) get('/tanker/drivers').then((r) => setDrivers(r.data)).catch(() => {}); }, [user]);
  const on = (e) => setF({ ...f, [e.target.name]: e.target.files ? e.target.files[0] : e.target.value });
  const toggleType = (n) => setF({ ...f, water_types: f.water_types.includes(n) ? f.water_types.filter((x) => x !== n) : [...f.water_types, n] });

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setErrors({});
    const payload = { ...f, service_areas: f.service_areas ? JSON.stringify(f.service_areas.split(',').map((s) => s.trim()).filter(Boolean)) : null, water_types: f.water_types.length ? JSON.stringify(f.water_types) : null };
    ['id', 'status', 'availability', 'pricing', 'driver', 'has_image', 'has_rc', 'review_notes', 'created_at', 'updated_at', 'deleted_at', 'tanker_profile_id', 'last_latitude', 'last_longitude', 'last_location_at'].forEach((k) => delete payload[k]);
    try {
      const r = await post(vehicle ? `/tanker/vehicles/${vehicle.id}` : '/tanker/vehicles', toForm(payload));
      toast(r.message); onDone();
    } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); } finally { setBusy(false); }
  };

  return (
    <form className="card card-body mb-3 border-primary" onSubmit={submit}>
      <h2 className="h6 fw-bold">{vehicle ? `Edit ${vehicle.registration_number}` : 'Add vehicle'}</h2>
      {vehicle && ['approved', 'active'].includes(vehicle.status) && <div className="alert alert-warning small py-2">Changing registration number, capacity or RC document sends the vehicle back for admin review.</div>}
      <div className="row g-2">
        <div className="col-sm-6"><Field label="Registration number" name="registration_number" value={f.registration_number} onChange={on} errors={errors} placeholder="TS09AB1234" required style={{ textTransform: 'uppercase' }} /></div>
        <div className="col-sm-6">
          <Field label="Capacity (litres)" name="capacity_litres" type="number" list="caps" value={f.capacity_litres} onChange={on} errors={errors} required />
          <datalist id="caps">{(meta?.capacities || []).map((c) => <option key={c} value={c} />)}</datalist>
        </div>
      </div>
      {drivers.length > 0 && (
        <Field label="Driver" name="driver_profile_id" as="select" value={f.driver_profile_id} onChange={on} errors={errors}>
          <option value="">— Owner drives —</option>
          {drivers.filter((d) => d.status === 'active').map((d) => <option key={d.id} value={d.id}>{d.name} ({d.mobile})</option>)}
        </Field>
      )}
      <div className="mb-3">
        <label className="form-label">Water types carried</label>
        <div className="d-flex flex-wrap gap-2">{(meta?.water_types || []).map((w) => <button type="button" key={w.id} onClick={() => toggleType(w.name)} className={`btn btn-sm ${f.water_types.includes(w.name) ? 'btn-primary' : 'btn-outline-secondary'}`}>{w.name}</button>)}</div>
        <div className="form-text">None selected = all types.</div>
      </div>
      <div className="row g-2">
        <div className="col-sm-8"><Field label="Service areas (comma separated)" name="service_areas" value={f.service_areas} onChange={on} errors={errors} placeholder="Gachibowli, Kondapur, Madhapur" /></div>
        <div className="col-sm-4"><Field label="Service radius (km)" name="service_radius_km" type="number" value={f.service_radius_km || ''} onChange={on} errors={errors} /></div>
      </div>
      <label className="form-label">Base / parking location (optional — defaults to your profile location)</label>
      <MapPicker lat={f.base_latitude} lng={f.base_longitude} onChange={(a, b) => setF({ ...f, base_latitude: a, base_longitude: b })} height={200} />
      <div className="row g-2 mt-2">
        <div className="col-sm-6"><Field label="Vehicle photo" name="image" type="file" accept="image/*" onChange={on} errors={errors} hint={vehicle?.has_image ? 'Uploaded — choose to replace' : 'JPG/PNG/WebP, max 5 MB'} /></div>
        <div className="col-sm-6"><Field label="RC document" name="rc_document" type="file" accept="image/*,application/pdf" onChange={on} errors={errors} hint={vehicle?.has_rc ? 'Uploaded — choose to replace' : 'Image or PDF, max 5 MB'} /></div>
      </div>
      <div className="d-flex gap-2"><button className="btn btn-primary" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Save vehicle</button><button type="button" className="btn btn-light" onClick={onCancel}>Cancel</button></div>
    </form>
  );
}

function PricingPanel({ v, onDone }) {
  const [f, setF] = useState({ pricing_type: 'per_litre', rate: '', minimum_quantity: '', delivery_charge: '', partial_load_rule: 'full_trip', effective_from: today(), effective_to: '' });
  const [errors, setErrors] = useState({});
  const toast = useToast();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });
  const submit = async (e) => {
    e.preventDefault(); setErrors({});
    try { const r = await post(`/tanker/vehicles/${v.id}/pricing`, f); toast(r.message); onDone(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };
  return (
    <div className="mt-3 pt-3 border-top">
      <div className="fw-semibold mb-2">Rates</div>
      {v.pricing?.length ? (
        <div className="table-responsive"><table className="table table-sm small">
          <thead><tr><th>Type</th><th>Rate</th><th>Delivery</th><th>From</th><th>To</th><th>Status</th></tr></thead>
          <tbody>{v.pricing.map((p) => (
            <tr key={p.id}><td>{p.pricing_type === 'per_litre' ? 'Per litre' : 'Per trip'}</td><td>{p.pricing_type === 'per_litre' ? `₹${Number(p.rate)}` : money(p.rate)}</td><td>{money(p.delivery_charge)}</td><td>{date(p.effective_from)}</td><td>{p.effective_to ? date(p.effective_to) : 'Open'}</td><td><StatusBadge status={p.approval_status} />{p.review_notes && <div className="text-muted">{p.review_notes}</div>}</td></tr>
          ))}</tbody>
        </table></div>
      ) : <div className="small text-muted mb-2">No rates yet — add at least one so admin can quote with this vehicle.</div>}
      <form onSubmit={submit} className="row g-2 align-items-end">
        <div className="col-6 col-md-3"><Field className="" label="Type" name="pricing_type" as="select" value={f.pricing_type} onChange={on} errors={errors}><option value="per_litre">Per litre</option><option value="per_trip">Per trip</option></Field></div>
        <div className="col-6 col-md-2"><Field className="" label={f.pricing_type === 'per_litre' ? '₹ / litre' : '₹ / trip'} name="rate" type="number" step="0.01" value={f.rate} onChange={on} errors={errors} required /></div>
        <div className="col-6 col-md-2"><Field className="" label="Delivery ₹" name="delivery_charge" type="number" step="0.01" value={f.delivery_charge} onChange={on} errors={errors} /></div>
        {f.pricing_type === 'per_litre'
          ? <div className="col-6 col-md-2"><Field className="" label="Min litres" name="minimum_quantity" type="number" value={f.minimum_quantity} onChange={on} errors={errors} /></div>
          : <div className="col-6 col-md-2"><Field className="" label="Partial load" name="partial_load_rule" as="select" value={f.partial_load_rule} onChange={on} errors={errors}><option value="full_trip">Full trip</option><option value="pro_rata">Pro-rata</option></Field></div>}
        <div className="col-6 col-md-3"><Field className="" label="Effective from" name="effective_from" type="date" value={f.effective_from} onChange={on} errors={errors} required /></div>
        <div className="col-12"><button className="btn btn-outline-primary btn-sm">Submit rate for approval</button></div>
      </form>
    </div>
  );
}

export default function Vehicles() {
  const [params, setParams] = useSearchParams();
  const { data, loading, error, reload } = useApi('/tanker/vehicles');
  const { user } = useAuth();
  const [editing, setEditing] = useState(params.get('add') ? 'new' : null);
  const [open, setOpen] = useState(null);
  const toast = useToast();
  const owner = user.tanker?.is_owner;
  const done = () => { setEditing(null); setParams({}); reload(); };
  const setAvail = async (v, availability) => { try { await post(`/tanker/vehicles/${v.id}/availability`, { availability }); toast('Availability updated'); reload(); } catch (e) { toast(e.message, 'danger'); } };

  return (
    <div className="content narrow p-0">
      <PageHeader title="Vehicles">{owner && !editing && <button className="btn btn-primary btn-sm" onClick={() => setEditing('new')}><i className="bi bi-plus" /> Add vehicle</button>}</PageHeader>
      <ErrorBox error={error} onRetry={reload} />
      {editing && <VehicleForm vehicle={editing === 'new' ? null : editing} onDone={done} onCancel={() => { setEditing(null); setParams({}); }} />}
      {loading ? <Loading /> : !data?.length ? (!editing && <Empty icon="truck" title="No vehicles yet">Add your tanker with registration number, capacity and RC document.</Empty>) : data.map((v) => (
        <div key={v.id} className="card mb-2"><div className="card-body">
          <div className="d-flex justify-content-between align-items-start gap-2">
            <div><div className="fw-bold fs-5">{v.registration_number}</div><div className="small text-muted">{litres(v.capacity_litres)}{v.driver && ` · Driver: ${v.driver.name}`}</div></div>
            <StatusBadge status={v.status} />
          </div>
          {v.review_notes && <div className="alert alert-warning small py-2 mt-2 mb-0">Admin: {v.review_notes}</div>}
          <div className="d-flex flex-wrap gap-2 mt-3 align-items-center">
            <div className="btn-group btn-group-sm" role="group" aria-label="Availability">
              <button className={`btn ${v.availability === 'available' ? 'btn-success' : 'btn-outline-success'}`} onClick={() => setAvail(v, 'available')}>Available</button>
              <button className={`btn ${v.availability === 'unavailable' ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={() => setAvail(v, 'unavailable')}>Unavailable</button>
            </div>
            {v.availability === 'busy' && <StatusBadge status="busy" />}
            {owner && <button className="btn btn-sm btn-light" onClick={() => setEditing(v)}><i className="bi bi-pencil me-1" />Edit</button>}
            <button className="btn btn-sm btn-light" onClick={() => setOpen(open === v.id ? null : v.id)}><i className="bi bi-tags me-1" />Rates</button>
            {v.has_rc && <button className="btn btn-sm btn-light" onClick={() => openFile(`/files/vehicles/${v.id}/rc`)}><i className="bi bi-file-earmark me-1" />RC</button>}
            {v.has_image && <button className="btn btn-sm btn-light" onClick={() => openFile(`/files/vehicles/${v.id}/image`)}><i className="bi bi-image me-1" />Photo</button>}
          </div>
          {open === v.id && (owner ? <PricingPanel v={v} onDone={reload} /> : <div className="small text-muted mt-2">Only the owner can change rates.</div>)}
        </div></div>
      ))}
    </div>
  );
}
