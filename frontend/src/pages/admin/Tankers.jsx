import { useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { openFile, post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, Field, KV, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { MultiMap, icons } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, dateTime, label, litres, money, today } from '../../utils/format';

export function AdminTankers() {
  const [params, setParams] = useSearchParams();
  const status = params.get('status') || '';
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/admin/tankers', { status, search, page });
  const nav = useNavigate();
  return (
    <div>
      <PageHeader title="Tanker owners" />
      <div className="d-flex gap-1 overflow-auto mb-3">
        {[['', 'All'], ['pending_review', 'Pending review'], ['approved', 'Approved'], ['correction_requested', 'Correction requested'], ['rejected', 'Rejected'], ['suspended', 'Suspended']].map(([k, l]) => <button key={k} className={`btn btn-sm text-nowrap ${status === k ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setParams(k ? { status: k } : {})}>{l}</button>)}
      </div>
      <input className="form-control mb-3" style={{ maxWidth: 360 }} placeholder="Search name, business, mobile" value={search} onChange={(e) => setSearch(e.target.value)} />
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty title="No tanker owners" /> : (
          <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
            <thead><tr><th>Owner</th><th>Mobile</th><th>Vehicles</th><th>Drivers</th><th>Registered</th><th>Status</th></tr></thead>
            <tbody>{data.map((t) => (
              <tr key={t.id} onClick={() => nav(`/admin/tankers/${t.id}`)}>
                <td><div className="fw-semibold">{t.owner_name}</div><div className="small text-muted">{t.business_name}</div></td>
                <td>{t.user?.mobile}</td><td>{t.vehicles_count}</td><td>{t.drivers_count}</td><td className="small">{date(t.created_at)}</td><td><StatusBadge status={t.verification_status} /></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

function ReviewButtons({ onAction, status, vehicle }) {
  return (
    <div className="d-flex flex-wrap gap-1">
      {status !== 'approved' && status !== 'active' && <button className="btn btn-success btn-sm" onClick={() => onAction(vehicle && status === 'suspended' ? 'reactivate' : 'approve')}>{status === 'suspended' ? 'Reactivate' : 'Approve'}</button>}
      {!['rejected', 'suspended'].includes(status) && <button className="btn btn-outline-warning btn-sm" onClick={() => onAction('request_correction')}>Request correction</button>}
      {status === 'pending_review' && <button className="btn btn-outline-danger btn-sm" onClick={() => onAction('reject')}>Reject</button>}
      {['approved', 'active'].includes(status) && <button className="btn btn-outline-danger btn-sm" onClick={() => onAction('suspend')}>Suspend</button>}
    </div>
  );
}

function AdminRateForm({ v, onDone }) {
  const [f, setF] = useState({ pricing_type: 'per_litre', rate: '', delivery_charge: '', effective_from: today() });
  const [errors, setErrors] = useState({});
  const toast = useToast();
  const submit = async (e) => { e.preventDefault(); setErrors({}); try { const r = await post(`/admin/vehicles/${v.id}/pricing`, f); toast(r.message); onDone(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); } };
  return (
    <form className="row g-2 align-items-end mt-1" onSubmit={submit}>
      <div className="col-6 col-md-3"><Field className="" label="Type" name="pricing_type" as="select" value={f.pricing_type} onChange={(e) => setF({ ...f, pricing_type: e.target.value })} errors={errors}><option value="per_litre">Per litre</option><option value="per_trip">Per trip</option></Field></div>
      <div className="col-6 col-md-2"><Field className="" label="Rate ₹" name="rate" type="number" step="0.01" value={f.rate} onChange={(e) => setF({ ...f, rate: e.target.value })} errors={errors} required /></div>
      <div className="col-6 col-md-2"><Field className="" label="Delivery ₹" name="delivery_charge" type="number" value={f.delivery_charge} onChange={(e) => setF({ ...f, delivery_charge: e.target.value })} errors={errors} /></div>
      <div className="col-6 col-md-3"><Field className="" label="From" name="effective_from" type="date" value={f.effective_from} onChange={(e) => setF({ ...f, effective_from: e.target.value })} errors={errors} /></div>
      <div className="col-md-2"><button className="btn btn-outline-primary btn-sm w-100">Set rate</button></div>
    </form>
  );
}

export function AdminTankerDetail() {
  const { id } = useParams();
  const { data, loading, error, reload } = useApi(`/admin/tankers/${id}`);
  const [modal, setModal] = useState(null);
  const [rateFor, setRateFor] = useState(null);
  const toast = useToast();
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const p = data.profile;
  const reviewProfile = (action, notes) => post(`/admin/tankers/${id}/review`, { action, notes }).then((r) => { toast(r.message); reload(); });
  const reviewVehicle = (vid, action, notes) => post(`/admin/vehicles/${vid}/review`, { action, notes }).then((r) => { toast(r.message); reload(); });
  const reviewRate = (pid, action, notes) => post(`/admin/pricing/${pid}/review`, { action, notes }).then((r) => { toast(r.message); reload(); });
  const ask = (target, action) => (action === 'approve' || action === 'reactivate') ? target.run(action) : setModal({ ...target, action });

  return (
    <div>
      <PageHeader title={p.owner_name} back="/admin/tankers" subtitle={`${p.business_name || ''} ${p.user?.mobile}`}>
        <StatusBadge status={p.verification_status} />
      </PageHeader>
      <div className="row g-3">
        <div className="col-lg-5">
          <div className="card mb-3"><div className="card-body">
            <KV items={[['Mobile', p.user?.mobile], ['Email', p.user?.email], ['Alternate', p.alternate_mobile], ['Address', p.address], ['Registered', dateTime(p.user?.created_at)], ['Account', <StatusBadge status={p.user?.status} />], p.review_notes && ['Review notes', p.review_notes], p.reviewed_at && ['Reviewed', dateTime(p.reviewed_at)]]} />
            <div className="mt-3"><ReviewButtons status={p.verification_status} onAction={(a) => ask({ run: (x, n) => reviewProfile(x, n), title: 'tanker profile' }, a)} /></div>
          </div></div>
          {p.base_latitude && <MultiMap markers={[{ key: 'b', position: [p.base_latitude, p.base_longitude], icon: icons.tanker(false), popup: 'Base' }]} height={200} />}
          <div className="card mt-3"><div className="card-header">Drivers</div><div className="list-group list-group-flush">
            {p.drivers.map((d) => <div key={d.id} className="list-group-item small d-flex justify-content-between align-items-center"><span><b>{d.name}</b> {d.mobile}{d.licence_number && ` · DL ${d.licence_number}`}</span><span className="d-flex gap-1">{d.has_licence_document && <button className="btn btn-sm btn-light" onClick={() => openFile(`/files/drivers/${d.id}/licence`)}>Licence</button>}<StatusBadge status={d.status} /></span></div>)}
          </div></div>
          <div className="small text-muted mt-2">Assignments: {Object.entries(data.assignment_stats || {}).map(([k, v]) => `${label(k)} ${v}`).join(' · ') || 'none'}</div>
        </div>
        <div className="col-lg-7">
          {p.vehicles.length === 0 && <div className="alert alert-light border">No vehicles submitted yet.</div>}
          {p.vehicles.map((v) => (
            <div key={v.id} className="card mb-3"><div className="card-body">
              <div className="d-flex justify-content-between align-items-start">
                <div><div className="fw-bold fs-5">{v.registration_number}</div><div className="small text-muted">{litres(v.capacity_litres)} · {label(v.vehicle_type)}{v.driver && ` · ${v.driver.name}`} · radius {v.service_radius_km || '—'} km</div>{v.service_areas?.length > 0 && <div className="small">Areas: {v.service_areas.join(', ')}</div>}{v.water_types?.length > 0 && <div className="small">Water: {v.water_types.join(', ')}</div>}</div>
                <div className="text-end"><StatusBadge status={v.status} /><div className="mt-1"><StatusBadge status={v.availability} /></div></div>
              </div>
              <div className="d-flex gap-2 my-2">
                <button className="btn btn-sm btn-light" disabled={!v.has_rc} onClick={() => openFile(`/files/vehicles/${v.id}/rc`)}><i className="bi bi-file-earmark me-1" />RC {v.has_rc ? '' : '(missing)'}</button>
                <button className="btn btn-sm btn-light" disabled={!v.has_image} onClick={() => openFile(`/files/vehicles/${v.id}/image`)}><i className="bi bi-image me-1" />Photo {v.has_image ? '' : '(missing)'}</button>
              </div>
              {v.review_notes && <div className="small text-muted mb-2">Note: {v.review_notes}</div>}
              <ReviewButtons vehicle status={v.status} onAction={(a) => ask({ run: (x, n) => reviewVehicle(v.id, x, n), title: `vehicle ${v.registration_number}` }, a)} />
              <div className="mt-3 small fw-semibold">Rates</div>
              <table className="table table-sm small mb-1"><tbody>
                {v.pricing.map((r) => (
                  <tr key={r.id}><td>{r.pricing_type === 'per_litre' ? `₹${Number(r.rate)}/L` : `${money(r.rate)}/trip`}</td><td>+{money(r.delivery_charge)}</td><td>{date(r.effective_from)} → {r.effective_to ? date(r.effective_to) : 'open'}</td><td><StatusBadge status={r.approval_status} /></td>
                    <td className="text-end">{r.approval_status === 'pending' && <><button className="btn btn-success btn-sm py-0" onClick={() => reviewRate(r.id, 'approve')}>Approve</button> <button className="btn btn-outline-danger btn-sm py-0" onClick={() => setModal({ run: (x, n) => reviewRate(r.id, x, n), title: 'rate', action: 'reject' })}>Reject</button></>}</td></tr>
                ))}
                {!v.pricing.length && <tr><td className="text-muted">No rates submitted.</td></tr>}
              </tbody></table>
              <button className="btn btn-link btn-sm px-0" onClick={() => setRateFor(rateFor === v.id ? null : v.id)}>Set a rate directly</button>
              {rateFor === v.id && <AdminRateForm v={v} onDone={() => { setRateFor(null); reload(); }} />}
            </div></div>
          ))}
        </div>
      </div>
      <Confirm show={!!modal} title={modal && `${label(modal.action)} ${modal.title}`} tone={modal?.action === 'request_correction' ? 'warning' : 'danger'} requireReason reasonLabel="Note to the owner" confirmText={modal && label(modal.action)}
        onClose={() => setModal(null)} onConfirm={(notes) => modal.run(modal.action, notes)} />
    </div>
  );
}

export function AdminVehicles() {
  const [params, setParams] = useSearchParams();
  const status = params.get('status') || '';
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/admin/vehicles', { status, search, page });
  const nav = useNavigate();
  return (
    <div>
      <PageHeader title="Vehicles" />
      <div className="d-flex gap-1 overflow-auto mb-3">
        {[['', 'All'], ['pending_review', 'Pending review'], ['approved', 'Approved'], ['correction_requested', 'Correction'], ['suspended', 'Suspended'], ['rejected', 'Rejected']].map(([k, l]) => <button key={k} className={`btn btn-sm text-nowrap ${status === k ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setParams(k ? { status: k } : {})}>{l}</button>)}
      </div>
      <input className="form-control mb-3" style={{ maxWidth: 300 }} placeholder="Registration number" value={search} onChange={(e) => setSearch(e.target.value)} />
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty title="No vehicles" /> : (
          <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
            <thead><tr><th>Vehicle</th><th>Capacity</th><th>Owner</th><th>Driver</th><th>Last GPS</th><th>Availability</th><th>Status</th></tr></thead>
            <tbody>{data.map((v) => (
              <tr key={v.id} onClick={() => nav(`/admin/tankers/${v.tanker_profile_id}`)}>
                <td className="fw-semibold">{v.registration_number}</td><td>{litres(v.capacity_litres)}</td><td>{v.tanker_profile?.business_name || v.tanker_profile?.owner_name}</td><td className="small">{v.driver?.name || '—'}</td><td className="small">{v.last_location_at ? dateTime(v.last_location_at) : '—'}</td><td><StatusBadge status={v.availability} /></td><td><StatusBadge status={v.status} /></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

export function AdminPricing() {
  const [status, setStatus] = useState('pending');
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/admin/pricing', { status, page });
  const [rej, setRej] = useState(null);
  const toast = useToast();
  const approve = (p) => post(`/admin/pricing/${p.id}/review`, { action: 'approve' }).then((r) => { toast(r.message); reload(); }).catch((e) => toast(e.message, 'danger'));
  return (
    <div>
      <PageHeader title="Rate approvals" subtitle="Rates are used for quotations only after approval. Approving closes the previous rate on the day before." />
      <ul className="nav nav-pills mb-3">{['pending', 'approved', 'rejected', 'superseded'].map((s) => <li key={s} className="nav-item"><button className={`nav-link ${status === s ? 'active' : ''}`} onClick={() => { setStatus(s); setPage(1); }}>{label(s)}</button></li>)}</ul>
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty icon="tags" title={`No ${status} rates`} /> : (
          <div className="table-responsive"><table className="table mb-0 align-middle">
            <thead><tr><th>Vehicle</th><th>Owner</th><th>Type</th><th>Rate</th><th>Delivery</th><th>Min / rule</th><th>Effective</th><th>Submitted</th><th /></tr></thead>
            <tbody>{data.map((p) => (
              <tr key={p.id}>
                <td className="fw-semibold">{p.vehicle?.registration_number}<div className="small text-muted">{litres(p.vehicle?.capacity_litres)}</div></td>
                <td className="small">{p.vehicle?.tanker_profile?.owner_name}</td>
                <td>{p.pricing_type === 'per_litre' ? 'Per litre' : 'Per trip'}</td>
                <td className="fw-semibold">{p.pricing_type === 'per_litre' ? `₹${Number(p.rate)}` : money(p.rate)}</td>
                <td>{money(p.delivery_charge)}</td>
                <td className="small">{p.pricing_type === 'per_litre' ? (p.minimum_quantity ? litres(p.minimum_quantity) : '—') : label(p.partial_load_rule)}</td>
                <td className="small">{date(p.effective_from)} → {p.effective_to ? date(p.effective_to) : 'open'}</td>
                <td className="small">{dateTime(p.created_at)}</td>
                <td className="text-nowrap">{p.approval_status === 'pending' ? <><button className="btn btn-success btn-sm" onClick={() => approve(p)}>Approve</button> <button className="btn btn-outline-danger btn-sm" onClick={() => setRej(p)}>Reject</button></> : <StatusBadge status={p.approval_status} />}</td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
      <Confirm show={!!rej} title="Reject rate?" tone="danger" requireReason reasonLabel="Note to the owner" confirmText="Reject" onClose={() => setRej(null)} onConfirm={(notes) => post(`/admin/pricing/${rej.id}/review`, { action: 'reject', notes }).then(() => { toast('Rate rejected'); reload(); })} />
    </div>
  );
}
