import { useState } from 'react';
import { del, post, put } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { useAuth } from '../../context/AuthContext';
import { Confirm, Empty, ErrorBox, Field, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { MapPicker, MultiMap, icons } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { label, litres } from '../../utils/format';

const blank = { name: '', source_code: '', source_type: 'borewell', address: '', area: '', latitude: null, longitude: null, water_types: [], internal_cost_per_litre: '', daily_capacity_litres: '', track_capacity: false, opening_time: '05:00', closing_time: '21:00', contact_person: '', contact_mobile: '', notes: '', status: 'active' };

export default function Sources() {
  const { data, loading, error, reload } = useApi('/admin/borewells');
  const { meta } = useAuth();
  const [f, setF] = useState(null);
  const [errors, setErrors] = useState({});
  const [off, setOff] = useState(null);
  const toast = useToast();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });
  const edit = (s) => { setErrors({}); setF(s ? { ...blank, ...s, opening_time: s.opening_time?.slice(0, 5) || '', closing_time: s.closing_time?.slice(0, 5) || '', water_types: s.water_types || [], internal_cost_per_litre: s.internal_cost_per_litre ?? '', daily_capacity_litres: s.daily_capacity_litres ?? '' } : blank); window.scrollTo(0, 0); };
  const save = async (e) => {
    e.preventDefault(); setErrors({});
    const body = Object.fromEntries(Object.entries(f).filter(([k, v]) => v !== '' && !['id', 'created_at', 'updated_at', 'deleted_at', 'bookings_today', 'committed_today'].includes(k)));
    try { const r = f.id ? await put(`/admin/borewells/${f.id}`, body) : await post('/admin/borewells', body); toast(r.message); setF(null); reload(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };

  return (
    <div>
      <PageHeader title="Water sources" subtitle="Borewells and bavis. Admin selects the source for every booking.">
        {!f && <button className="btn btn-primary btn-sm" onClick={() => edit(null)}><i className="bi bi-plus" /> Add source</button>}
      </PageHeader>
      <ErrorBox error={error} onRetry={reload} />
      {f && (
        <form className="card card-body mb-3 border-primary" onSubmit={save}>
          <h2 className="h6 fw-bold">{f.id ? `Edit ${f.name}` : 'New water source'}</h2>
          <div className="row g-2">
            <div className="col-md-4"><Field label="Name" name="name" value={f.name} onChange={on} errors={errors} placeholder="Borewell 1" required /></div>
            <div className="col-6 col-md-3"><Field label="Code" name="source_code" value={f.source_code} onChange={on} errors={errors} placeholder="BW-01" required /></div>
            <div className="col-6 col-md-2"><Field label="Type" name="source_type" as="select" value={f.source_type} onChange={on} errors={errors}><option value="borewell">Borewell</option><option value="bavi">Bavi / well</option></Field></div>
            <div className="col-md-3"><Field label="Status" name="status" as="select" value={f.status} onChange={on} errors={errors}><option value="active">Active</option><option value="maintenance">Maintenance</option><option value="inactive">Inactive</option></Field></div>
          </div>
          <div className="row g-2">
            <div className="col-md-8"><Field label="Address" name="address" value={f.address || ''} onChange={on} errors={errors} /></div>
            <div className="col-md-4"><Field label="Area" name="area" value={f.area || ''} onChange={on} errors={errors} /></div>
          </div>
          <label className="form-label">Location</label>
          <MapPicker lat={f.latitude} lng={f.longitude} onChange={(a, b) => setF({ ...f, latitude: a, longitude: b })} height={240} />
          {(errors.latitude) && <div className="text-danger small">Set the source location on the map.</div>}
          <div className="my-3">
            <label className="form-label">Water types available</label>
            <div className="d-flex flex-wrap gap-2">{(meta?.water_types || []).map((w) => <button type="button" key={w.id} className={`btn btn-sm ${f.water_types.includes(w.name) ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setF({ ...f, water_types: f.water_types.includes(w.name) ? f.water_types.filter((x) => x !== w.name) : [...f.water_types, w.name] })}>{w.name}</button>)}</div>
            <div className="form-text">None selected = supplies all types.</div>
          </div>
          <div className="row g-2">
            <div className="col-6 col-md-3"><Field label="Opens" name="opening_time" type="time" value={f.opening_time} onChange={on} errors={errors} /></div>
            <div className="col-6 col-md-3"><Field label="Closes" name="closing_time" type="time" value={f.closing_time} onChange={on} errors={errors} /></div>
            <div className="col-6 col-md-3"><Field label="Internal cost ₹/L" name="internal_cost_per_litre" type="number" step="0.0001" value={f.internal_cost_per_litre} onChange={on} errors={errors} hint="Not shown to customers" /></div>
            <div className="col-6 col-md-3"><Field label="Daily capacity (L)" name="daily_capacity_litres" type="number" value={f.daily_capacity_litres} onChange={on} errors={errors} /></div>
          </div>
          <div className="form-check mb-3"><input className="form-check-input" type="checkbox" id="tc" name="track_capacity" checked={!!f.track_capacity} onChange={on} /><label className="form-check-label" htmlFor="tc">Block bookings once daily capacity is used (only if capacity is maintained)</label></div>
          <div className="row g-2">
            <div className="col-md-4"><Field label="Contact person" name="contact_person" value={f.contact_person || ''} onChange={on} errors={errors} /></div>
            <div className="col-md-4"><Field label="Contact mobile" name="contact_mobile" value={f.contact_mobile || ''} onChange={on} errors={errors} /></div>
            <div className="col-md-4"><Field label="Notes" name="notes" value={f.notes || ''} onChange={on} errors={errors} /></div>
          </div>
          <div className="d-flex gap-2"><button className="btn btn-primary" disabled={f.latitude == null}>Save source</button><button type="button" className="btn btn-light" onClick={() => setF(null)}>Cancel</button></div>
        </form>
      )}
      {loading ? <Loading /> : !data?.length ? <Empty icon="droplet" title="No water sources yet">Add your 4–6 borewells / bavis to start quoting bookings.</Empty> : (
        <div className="row g-3">
          <div className="col-lg-7">
            <div className="card"><div className="table-responsive"><table className="table mb-0 align-middle">
              <thead><tr><th>Source</th><th>Area</th><th>Water</th><th>Hours</th><th>Today</th><th>Status</th><th /></tr></thead>
              <tbody>{data.map((s) => (
                <tr key={s.id}>
                  <td><div className="fw-semibold">{s.name}</div><div className="small text-muted">{s.source_code} · {label(s.source_type)}</div></td>
                  <td className="small">{s.area}</td>
                  <td className="small">{s.water_types?.length ? s.water_types.join(', ') : 'All'}</td>
                  <td className="small text-nowrap">{s.opening_time?.slice(0, 5)}–{s.closing_time?.slice(0, 5)}</td>
                  <td className="small text-nowrap">{s.bookings_today} bk · {litres(s.committed_today)}{s.track_capacity && s.daily_capacity_litres && <div className="text-muted">of {litres(s.daily_capacity_litres)}</div>}</td>
                  <td><StatusBadge status={s.status} /></td>
                  <td className="text-nowrap"><button className="btn btn-sm btn-light" onClick={() => edit(s)}><i className="bi bi-pencil" /></button> {s.status !== 'inactive' && <button className="btn btn-sm btn-light text-danger" onClick={() => setOff(s)} title="Deactivate"><i className="bi bi-power" /></button>}</td>
                </tr>
              ))}</tbody>
            </table></div></div>
          </div>
          <div className="col-lg-5"><MultiMap markers={data.map((s) => ({ key: s.id, position: [s.latitude, s.longitude], icon: icons.source, popup: `${s.name} (${label(s.status)})` }))} height={420} /></div>
        </div>
      )}
      <Confirm show={!!off} title={`Deactivate ${off?.name}?`} tone="danger" confirmText="Deactivate" message="It will no longer be selectable for bookings. Booking history is kept." onClose={() => setOff(null)} onConfirm={() => del(`/admin/borewells/${off.id}`).then(() => { toast('Source deactivated'); reload(); })} />
    </div>
  );
}
