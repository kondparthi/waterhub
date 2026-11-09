import { useEffect, useState } from 'react';
import { get, post, put } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { useAuth } from '../../context/AuthContext';
import { ErrorBox, Field, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { dateTime, label } from '../../utils/format';

function General() {
  const { data, loading, error, reload } = useApi('/admin/settings');
  const [vals, setVals] = useState({});
  const toast = useToast();
  const { refresh } = useAuth();
  useEffect(() => { if (data) setVals(Object.fromEntries(data.map((s) => [s.key, s.type === 'json' ? JSON.stringify(s.value) : s.value]))); }, [data]);
  if (loading) return <Loading />;
  const save = async (e) => {
    e.preventDefault();
    try { await put('/admin/settings', { settings: vals }); toast('Settings saved'); reload(); refresh(); get('/meta'); } catch (er) { toast(er.message, 'danger'); }
  };
  return (
    <form onSubmit={save}>
      <ErrorBox error={error} />
      <div className="row g-3">
        {data.map((s) => (
          <div key={s.key} className="col-md-6">
            {s.type === 'bool' ? (
              <div className="form-check form-switch mt-4"><input className="form-check-input" type="checkbox" id={s.key} checked={!!vals[s.key]} onChange={(e) => setVals({ ...vals, [s.key]: e.target.checked })} /><label className="form-check-label" htmlFor={s.key}>{s.label}</label></div>
            ) : (
              <Field className="" label={s.label} name={s.key} type={['int', 'decimal'].includes(s.type) ? 'number' : 'text'} step={s.type === 'decimal' ? '0.01' : undefined} value={vals[s.key] ?? ''} onChange={(e) => setVals({ ...vals, [s.key]: e.target.value })} hint={s.type === 'json' ? 'JSON list, e.g. ["06:00-09:00","09:00-12:00"]' : null} />
            )}
          </div>
        ))}
      </div>
      <button className="btn btn-primary mt-3">Save settings</button>
    </form>
  );
}

function WaterTypes() {
  const { data, loading, reload } = useApi('/admin/water-types');
  const [f, setF] = useState({ name: '', description: '' });
  const [errors, setErrors] = useState({});
  const toast = useToast();
  if (loading) return <Loading />;
  const add = async (e) => { e.preventDefault(); setErrors({}); try { await post('/admin/water-types', f); setF({ name: '', description: '' }); toast('Water type added'); reload(); } catch (er) { setErrors(er.errors); } };
  const toggle = (w) => put(`/admin/water-types/${w.id}`, { is_active: !w.is_active }).then(reload);
  return (
    <div>
      <div className="list-group mb-3">{data.map((w) => <div key={w.id} className="list-group-item d-flex justify-content-between align-items-center"><div><b>{w.name}</b><div className="small text-muted">{w.description}</div></div><button className={`btn btn-sm ${w.is_active ? 'btn-outline-secondary' : 'btn-outline-success'}`} onClick={() => toggle(w)}>{w.is_active ? 'Disable' : 'Enable'}</button></div>)}</div>
      <form className="row g-2 align-items-end" onSubmit={add}>
        <div className="col-md-4"><Field className="" label="New water type" name="name" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} errors={errors} required /></div>
        <div className="col-md-6"><Field className="" label="Description" name="description" value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} errors={errors} /></div>
        <div className="col-md-2"><button className="btn btn-primary w-100">Add</button></div>
      </form>
    </div>
  );
}

function Admins() {
  const { data, loading, reload } = useApi('/admin/admins');
  const { user } = useAuth();
  const [f, setF] = useState({ name: '', email: '', password: '' });
  const [errors, setErrors] = useState({});
  const toast = useToast();
  if (loading) return <Loading />;
  const add = async (e) => { e.preventDefault(); setErrors({}); try { await post('/admin/admins', f); setF({ name: '', email: '', password: '' }); toast('Admin created'); reload(); } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); } };
  const toggle = (a) => put(`/admin/admins/${a.id}`, { status: a.status === 'active' ? 'suspended' : 'active' }).then(() => { toast('Updated'); reload(); }).catch((e) => toast(e.message, 'danger'));
  return (
    <div>
      <div className="list-group mb-3">{data.map((a) => <div key={a.id} className="list-group-item d-flex justify-content-between align-items-center"><div><b>{a.name}</b> <span className="text-muted small">{a.email}</span><div className="small text-muted">Last login {dateTime(a.last_login_at)}</div></div><div className="d-flex gap-2 align-items-center"><StatusBadge status={a.status} />{a.id !== user.id && <button className="btn btn-sm btn-light" onClick={() => toggle(a)}>{a.status === 'active' ? 'Suspend' : 'Activate'}</button>}</div></div>)}</div>
      <form className="row g-2 align-items-end" onSubmit={add}>
        <div className="col-md-3"><Field className="" label="Name" name="name" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} errors={errors} required /></div>
        <div className="col-md-4"><Field className="" label="Email" name="email" type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} errors={errors} required /></div>
        <div className="col-md-3"><Field className="" label="Password" name="password" type="password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} errors={errors} hint="10+ chars, letters & numbers" required /></div>
        <div className="col-md-2"><button className="btn btn-primary w-100">Add admin</button></div>
      </form>
    </div>
  );
}

function Audit() {
  const [page, setPage] = useState(1);
  const { data, meta, loading } = useApi('/admin/audit-logs', { page });
  if (loading) return <Loading />;
  return (
    <div>
      <div className="table-responsive"><table className="table table-sm small mb-0">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Change</th><th>IP</th></tr></thead>
        <tbody>{data.map((l) => <tr key={l.id}><td className="text-nowrap">{dateTime(l.created_at)}</td><td>{l.actor?.name || 'System'}</td><td>{label(l.action.replace('.', ' '))}</td><td>{l.entity_type} #{l.entity_id}</td><td className="text-muted" style={{ maxWidth: 320, wordBreak: 'break-word' }}>{l.after_json ? JSON.stringify(l.after_json).slice(0, 160) : ''}</td><td>{l.ip_address}</td></tr>)}</tbody>
      </table></div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

export default function Settings() {
  const [tab, setTab] = useState('general');
  const TABS = { general: ['Business rules', General], types: ['Water types', WaterTypes], admins: ['Admin users', Admins], audit: ['Audit log', Audit] };
  const C = TABS[tab][1];
  return (
    <div>
      <PageHeader title="Settings" />
      <ul className="nav nav-tabs mb-3">{Object.entries(TABS).map(([k, [l]]) => <li key={k} className="nav-item"><button className={`nav-link ${tab === k ? 'active' : ''}`} onClick={() => setTab(k)}>{l}</button></li>)}</ul>
      <div className="card card-body"><C /></div>
    </div>
  );
}
