import { useEffect, useState } from 'react';
import { openFile, post, put, toForm } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { useAuth } from '../../context/AuthContext';
import { ErrorBox, Field, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { MapPicker } from '../../components/Maps';
import { useToast } from '../../components/Toast';

export default function TankerProfile() {
  const { data, loading, error, reload } = useApi('/tanker/profile');
  const { user, refresh } = useAuth();
  const [f, setF] = useState(null);
  const [errors, setErrors] = useState({});
  const [driver, setDriver] = useState(null);
  const toast = useToast();
  const owner = user.tanker?.is_owner;
  useEffect(() => { if (data) setF({ owner_name: data.owner_name, business_name: data.business_name || '', alternate_mobile: data.alternate_mobile || '', address: data.address || '', base_latitude: data.base_latitude, base_longitude: data.base_longitude }); }, [data]);
  if (loading || !f) return error ? <ErrorBox error={error} onRetry={reload} /> : <Loading />;
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });

  const save = async (e) => { e.preventDefault(); setErrors({}); try { const r = await put('/tanker/profile', f); toast(r.message); reload(); refresh(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); } };
  const saveDriver = async (e) => {
    e.preventDefault(); setErrors({});
    try { const r = await post(driver.id ? `/tanker/drivers/${driver.id}` : '/tanker/drivers', toForm(driver)); toast(r.message); setDriver(null); reload(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); }
  };

  return (
    <div className="content narrow p-0">
      <PageHeader title="Profile" subtitle={data.user?.mobile}><StatusBadge status={data.verification_status} /></PageHeader>
      {data.review_notes && <div className="alert alert-warning">Admin note: {data.review_notes}</div>}
      {owner ? (
        <form className="card card-body mb-3" onSubmit={save}>
          <div className="row g-2">
            <div className="col-sm-6"><Field label="Owner name" name="owner_name" value={f.owner_name} onChange={on} errors={errors} required /></div>
            <div className="col-sm-6"><Field label="Business name" name="business_name" value={f.business_name} onChange={on} errors={errors} /></div>
          </div>
          <Field label="Alternate mobile" name="alternate_mobile" type="tel" value={f.alternate_mobile} onChange={on} errors={errors} />
          <Field label="Address" name="address" as="textarea" rows={2} value={f.address} onChange={on} errors={errors} required />
          <label className="form-label">Base location</label>
          <MapPicker lat={f.base_latitude} lng={f.base_longitude} onChange={(a, b) => setF({ ...f, base_latitude: a, base_longitude: b })} height={220} />
          <button className="btn btn-primary mt-3">{['correction_requested', 'rejected'].includes(data.verification_status) ? 'Save & resubmit for review' : 'Save profile'}</button>
        </form>
      ) : <div className="card card-body mb-3"><div className="fw-semibold">{data.owner_name}</div><div className="small text-muted">You are logged in as a driver for this operator.</div></div>}

      {owner && (
        <div className="card mb-3">
          <div className="card-header d-flex justify-content-between align-items-center">Drivers
            <button className="btn btn-sm btn-outline-primary" onClick={() => setDriver({ name: '', mobile: '', licence_number: '', create_login: false, password: '' })}><i className="bi bi-plus" /> Add driver</button>
          </div>
          <div className="list-group list-group-flush">
            {data.drivers.map((d) => (
              <div key={d.id} className="list-group-item d-flex align-items-center gap-2">
                <div className="flex-grow-1"><div className="fw-semibold">{d.name} {d.is_owner && <span className="badge bg-light text-dark">Owner</span>} {d.user_id && !d.is_owner && <span className="badge bg-info text-dark">Has login</span>}</div><div className="small text-muted">{d.mobile}{d.licence_number && ` · DL ${d.licence_number}`}</div></div>
                <StatusBadge status={d.status} />
                {d.has_licence_document && <button className="btn btn-sm btn-light" onClick={() => openFile(`/files/drivers/${d.id}/licence`)}><i className="bi bi-file-earmark" /></button>}
                <button className="btn btn-sm btn-light" onClick={() => setDriver({ ...d, create_login: false })}><i className="bi bi-pencil" /></button>
              </div>
            ))}
          </div>
        </div>
      )}
      {driver && (
        <form className="card card-body border-primary mb-3" onSubmit={saveDriver}>
          <h2 className="h6 fw-bold">{driver.id ? 'Edit driver' : 'New driver'}</h2>
          <div className="row g-2">
            <div className="col-sm-6"><Field label="Name" name="name" value={driver.name} onChange={(e) => setDriver({ ...driver, name: e.target.value })} errors={errors} required /></div>
            <div className="col-sm-6"><Field label="Mobile" name="mobile" type="tel" value={driver.mobile} onChange={(e) => setDriver({ ...driver, mobile: e.target.value })} errors={errors} required /></div>
          </div>
          <div className="row g-2">
            <div className="col-sm-6"><Field label="Licence number" name="licence_number" value={driver.licence_number || ''} onChange={(e) => setDriver({ ...driver, licence_number: e.target.value })} errors={errors} /></div>
            <div className="col-sm-6"><Field label="Licence document" name="licence_document" type="file" accept="image/*,application/pdf" onChange={(e) => setDriver({ ...driver, licence_document: e.target.files[0] })} errors={errors} /></div>
          </div>
          {driver.id && !driver.is_owner && <Field label="Status" name="status" as="select" value={driver.status} onChange={(e) => setDriver({ ...driver, status: e.target.value })} errors={errors}><option value="active">Active</option><option value="inactive">Inactive</option></Field>}
          {!driver.id && (<>
            <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="cl" checked={driver.create_login} onChange={(e) => setDriver({ ...driver, create_login: e.target.checked })} /><label className="form-check-label" htmlFor="cl">Give this driver their own login (to accept jobs and share GPS)</label></div>
            {driver.create_login && <Field label="Driver password" name="password" type="password" value={driver.password} onChange={(e) => setDriver({ ...driver, password: e.target.value })} errors={errors} hint="Driver logs in with their mobile number and this password." />}
          </>)}
          <div className="d-flex gap-2"><button className="btn btn-primary">Save driver</button><button type="button" className="btn btn-light" onClick={() => setDriver(null)}>Cancel</button></div>
        </form>
      )}
    </div>
  );
}
