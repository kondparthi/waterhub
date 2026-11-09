import { useEffect, useState } from 'react';
import { get } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { Field } from './ui';
import { MapPicker } from './Maps';
import { tomorrow, today } from '../utils/format';

/** Shared booking / enquiry form. Mobile-first, few steps, map pin preview before submit. */
export default function OrderForm({ mode = 'booking', onSubmit, busy, errors = {} }) {
  const { meta } = useAuth();
  const [addresses, setAddresses] = useState([]);
  const [f, setF] = useState({
    water_type: '', quantity_litres: '', requested_capacity: '', delivery_address: '', area: '', landmark: '',
    latitude: null, longitude: null, preferred_date: tomorrow(), preferred_time_slot: '', notes: '', alternate_contact: '',
    save_address: false, address_label: 'Home',
  });
  const on = (e) => setF({ ...f, [e.target.name]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  useEffect(() => {
    get('/customer/addresses').then((r) => {
      setAddresses(r.data);
      const d = r.data.find((a) => a.is_default) || r.data[0];
      if (d) setF((x) => ({ ...x, delivery_address: d.address, area: d.area || '', landmark: d.landmark || '', latitude: d.latitude, longitude: d.longitude }));
    }).catch(() => {});
  }, []);
  useEffect(() => { if (meta?.water_types?.length && !f.water_type) setF((x) => ({ ...x, water_type: meta.water_types[0].name })); }, [meta]); // eslint-disable-line

  const useAddress = (a) => setF({ ...f, delivery_address: a.address, area: a.area || '', landmark: a.landmark || '', latitude: a.latitude, longitude: a.longitude });
  const submit = (e) => { e.preventDefault(); onSubmit({ ...f, quantity_litres: f.quantity_litres || null, requested_capacity: f.requested_capacity || null }); };
  const missingPin = f.latitude == null;
  const caps = meta?.capacities || [1000, 2000, 5000, 10000];

  return (
    <form onSubmit={submit}>
      <div className="card mb-3"><div className="card-body">
        <h2 className="h6 fw-bold mb-3"><span className="step-pill me-2">1</span>What do you need?</h2>
        <div className="mb-3">
          <label className="form-label">Water type</label>
          <div className="d-flex flex-wrap gap-2">
            {(meta?.water_types || []).map((w) => (
              <button type="button" key={w.id} className={`btn ${f.water_type === w.name ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setF({ ...f, water_type: w.name })}>{w.name}</button>
            ))}
          </div>
          {errors.water_type && <div className="text-danger small mt-1">{errors.water_type[0]}</div>}
        </div>
        <label className="form-label">Quantity (litres){mode === 'enquiry' && ' — or choose a tanker size'}</label>
        <div className="d-flex flex-wrap gap-2 mb-2">
          {caps.map((c) => <button type="button" key={c} className={`btn btn-sm ${Number(f.quantity_litres) === c ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setF({ ...f, quantity_litres: c })}>{c.toLocaleString('en-IN')} L</button>)}
        </div>
        <Field name="quantity_litres" type="number" min={100} step={100} inputMode="numeric" placeholder="Or enter litres" value={f.quantity_litres} onChange={on} errors={errors} required={mode === 'booking'} />
        <Field label="Preferred tanker capacity (optional)" name="requested_capacity" as="select" value={f.requested_capacity} onChange={on} errors={errors} hint="Leave blank and we'll pick a suitable tanker.">
          <option value="">Let admin choose</option>
          {caps.map((c) => <option key={c} value={c}>{c.toLocaleString('en-IN')} L tanker</option>)}
        </Field>
      </div></div>

      <div className="card mb-3"><div className="card-body">
        <h2 className="h6 fw-bold mb-3"><span className="step-pill me-2">2</span>Where?</h2>
        {addresses.length > 0 && (
          <div className="d-flex flex-wrap gap-2 mb-3">
            {addresses.map((a) => <button type="button" key={a.id} className={`btn btn-sm ${f.delivery_address === a.address ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => useAddress(a)}><i className="bi bi-geo-alt me-1" />{a.label}</button>)}
          </div>
        )}
        <MapPicker lat={f.latitude} lng={f.longitude} onChange={(a, b) => setF({ ...f, latitude: a, longitude: b })} />
        {(errors.latitude || errors.longitude) && <div className="text-danger small">Set the delivery pin on the map.</div>}
        <div className="mt-3">
          <Field label="Delivery address" name="delivery_address" as="textarea" rows={2} value={f.delivery_address} onChange={on} errors={errors} required />
          <div className="row g-2">
            <div className="col-sm-6"><Field label="Area / locality" name="area" value={f.area} onChange={on} errors={errors} /></div>
            <div className="col-sm-6"><Field label="Landmark" name="landmark" value={f.landmark} onChange={on} errors={errors} /></div>
          </div>
          <div className="form-check">
            <input className="form-check-input" type="checkbox" id="save_address" name="save_address" checked={f.save_address} onChange={on} />
            <label className="form-check-label" htmlFor="save_address">Save this address</label>
          </div>
          {f.save_address && <Field className="mt-2" name="address_label" placeholder="Label e.g. Home, Site" value={f.address_label} onChange={on} errors={errors} />}
        </div>
      </div></div>

      <div className="card mb-3"><div className="card-body">
        <h2 className="h6 fw-bold mb-3"><span className="step-pill me-2">3</span>When?</h2>
        <div className="row g-2">
          <div className="col-sm-6"><Field label="Preferred date" name="preferred_date" type="date" min={today()} value={f.preferred_date} onChange={on} errors={errors} required={mode === 'booking'} /></div>
          <div className="col-sm-6">
            <Field label="Time window" name="preferred_time_slot" as="select" value={f.preferred_time_slot} onChange={on} errors={errors}>
              <option value="">Any time</option>
              {(meta?.settings?.time_slots || []).map((s) => <option key={s} value={s}>{s}</option>)}
            </Field>
          </div>
        </div>
        <Field label="Notes for the driver (optional)" name="notes" as="textarea" rows={2} value={f.notes} onChange={on} errors={errors} placeholder="Gate code, sump location, access details…" />
        {mode === 'enquiry' && <Field label="Alternate contact (optional)" name="alternate_contact" type="tel" value={f.alternate_contact} onChange={on} errors={errors} />}
      </div></div>

      {mode === 'booking' && <div className="alert alert-light border small"><i className="bi bi-info-circle me-1" />Your booking is a request until we confirm the water source, price and tanker. You'll see the quotation before anything is final.</div>}
      <button className="btn btn-primary btn-lg w-100" disabled={busy || missingPin}>
        {busy && <span className="spinner-border spinner-border-sm me-2" />}
        {missingPin ? 'Set delivery pin to continue' : mode === 'booking' ? 'Submit booking request' : 'Send enquiry'}
      </button>
    </form>
  );
}
