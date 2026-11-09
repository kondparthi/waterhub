import { useEffect, useState } from 'react';
import { del, post, put } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, ErrorBox, Field, Loading, PageHeader } from '../../components/ui';
import { MapPicker } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { useAuth } from '../../context/AuthContext';

export default function CustomerProfile() {
  const { data, loading, error, reload } = useApi('/customer/profile');
  const { refresh } = useAuth();
  const [f, setF] = useState(null);
  const [errors, setErrors] = useState({});
  const [addr, setAddr] = useState(null);
  const [delId, setDelId] = useState(null);
  const toast = useToast();

  useEffect(() => { if (data) setF({ name: data.name, email: data.email || '', alternate_mobile: data.customer_profile?.alternate_mobile || '', address: data.customer_profile?.address || '', area: data.customer_profile?.area || '', landmark: data.customer_profile?.landmark || '' }); }, [data]);
  if (loading || !f) return error ? <ErrorBox error={error} onRetry={reload} /> : <Loading />;
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });

  const save = async (e) => {
    e.preventDefault(); setErrors({});
    try { await put('/customer/profile', f); toast('Profile updated'); refresh(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };
  const saveAddr = async (e) => {
    e.preventDefault(); setErrors({});
    try {
      if (addr.id) await put(`/customer/addresses/${addr.id}`, addr); else await post('/customer/addresses', addr);
      toast('Address saved'); setAddr(null); reload();
    } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };

  return (
    <div className="content narrow p-0">
      <PageHeader title="Profile" subtitle={data.mobile} />
      <form className="card card-body mb-3" onSubmit={save}>
        <Field label="Name" name="name" value={f.name} onChange={on} errors={errors} required />
        <div className="row g-2">
          <div className="col-sm-6"><Field label="Email" name="email" type="email" value={f.email} onChange={on} errors={errors} /></div>
          <div className="col-sm-6"><Field label="Alternate phone" name="alternate_mobile" type="tel" value={f.alternate_mobile} onChange={on} errors={errors} /></div>
        </div>
        <Field label="Address" name="address" as="textarea" rows={2} value={f.address} onChange={on} errors={errors} />
        <div className="row g-2">
          <div className="col-sm-6"><Field label="Area" name="area" value={f.area} onChange={on} errors={errors} /></div>
          <div className="col-sm-6"><Field label="Landmark" name="landmark" value={f.landmark} onChange={on} errors={errors} /></div>
        </div>
        <button className="btn btn-primary">Save profile</button>
      </form>

      <div className="card mb-3">
        <div className="card-header d-flex justify-content-between align-items-center">Saved delivery addresses
          <button className="btn btn-sm btn-outline-primary" onClick={() => setAddr({ label: '', address: '', area: '', landmark: '', latitude: null, longitude: null, is_default: false })}><i className="bi bi-plus" /> Add</button>
        </div>
        <div className="list-group list-group-flush">
          {data.addresses.length === 0 && <div className="list-group-item text-muted small">No saved addresses yet.</div>}
          {data.addresses.map((a) => (
            <div key={a.id} className="list-group-item d-flex gap-2 align-items-start">
              <i className="bi bi-geo-alt text-primary mt-1" />
              <div className="flex-grow-1 min-w-0"><div className="fw-semibold">{a.label} {a.is_default && <span className="badge bg-info text-dark ms-1">Default</span>}</div><div className="small text-muted">{a.address}</div></div>
              <button className="btn btn-sm btn-light" onClick={() => setAddr(a)} aria-label="Edit"><i className="bi bi-pencil" /></button>
              <button className="btn btn-sm btn-light text-danger" onClick={() => setDelId(a.id)} aria-label="Delete"><i className="bi bi-trash" /></button>
            </div>
          ))}
        </div>
      </div>

      {addr && (
        <form className="card card-body mb-3 border-primary" onSubmit={saveAddr}>
          <h2 className="h6 fw-bold">{addr.id ? 'Edit address' : 'New address'}</h2>
          <Field label="Label" name="label" value={addr.label} onChange={(e) => setAddr({ ...addr, label: e.target.value })} errors={errors} placeholder="Home, Office, Site…" required />
          <MapPicker lat={addr.latitude} lng={addr.longitude} onChange={(la, lo) => setAddr({ ...addr, latitude: la, longitude: lo })} height={240} />
          <Field className="mt-3 mb-3" label="Address" name="address" as="textarea" rows={2} value={addr.address} onChange={(e) => setAddr({ ...addr, address: e.target.value })} errors={errors} required />
          <div className="row g-2">
            <div className="col-6"><Field label="Area" name="area" value={addr.area || ''} onChange={(e) => setAddr({ ...addr, area: e.target.value })} errors={errors} /></div>
            <div className="col-6"><Field label="Landmark" name="landmark" value={addr.landmark || ''} onChange={(e) => setAddr({ ...addr, landmark: e.target.value })} errors={errors} /></div>
          </div>
          <div className="form-check mb-3"><input type="checkbox" className="form-check-input" id="def" checked={!!addr.is_default} onChange={(e) => setAddr({ ...addr, is_default: e.target.checked })} /><label className="form-check-label" htmlFor="def">Default address</label></div>
          <div className="d-flex gap-2"><button className="btn btn-primary" disabled={addr.latitude == null}>Save address</button><button type="button" className="btn btn-light" onClick={() => setAddr(null)}>Cancel</button></div>
        </form>
      )}
      <Confirm show={!!delId} title="Remove address?" tone="danger" confirmText="Remove" onClose={() => setDelId(null)} onConfirm={() => del(`/customer/addresses/${delId}`).then(() => { toast('Address removed'); reload(); })} />
    </div>
  );
}
