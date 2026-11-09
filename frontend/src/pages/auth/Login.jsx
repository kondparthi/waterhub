import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { homeFor, useAuth } from '../../context/AuthContext';
import { Field } from '../../components/ui';

const ROLES = { customer: ['Customer', 'person'], tanker: ['Tanker owner / driver', 'truck'], admin: ['Admin', 'shield-lock'] };

export default function Login() {
  const [params] = useSearchParams();
  const [role, setRole] = useState(ROLES[params.get('as')] ? params.get('as') : 'customer');
  const [f, setF] = useState({ login: '', password: '' });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const { login } = useAuth();
  const nav = useNavigate();

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setErrors({}); setError(null);
    try {
      const u = await login(f.login, f.password, role);
      const next = params.get('next');
      nav(next && next.startsWith('/' + u.role) ? next : homeFor(u.role), { replace: true });
    } catch (er) { setErrors(er.errors); setError(Object.keys(er.errors).length ? null : er.message); } finally { setBusy(false); }
  };

  return (
    <div className="auth-wrap">
      <div className="auth-card">
        <Link to="/" className="d-block text-center text-white fw-bold fs-5 mb-3 text-decoration-none"><i className="bi bi-droplet-fill text-info me-2" />Water Tanker</Link>
        <div className="card">
          <div className="card-body p-4">
            <h1 className="h4 fw-bold mb-3">Log in</h1>
            <div className="btn-group w-100 mb-3" role="group">
              {Object.entries(ROLES).map(([k, [l, i]]) => (
                <button key={k} type="button" className={`btn btn-sm ${role === k ? 'btn-primary' : 'btn-outline-primary'}`} onClick={() => setRole(k)}><i className={`bi bi-${i} me-1`} /><span className="d-none d-sm-inline">{l.split(' /')[0]}</span><span className="d-sm-none">{k === 'tanker' ? 'Tanker' : l}</span></button>
              ))}
            </div>
            <form onSubmit={submit}>
              <Field label={role === 'admin' ? 'Email' : 'Mobile number'} name="login" value={f.login} onChange={(e) => setF({ ...f, login: e.target.value })} errors={errors} inputMode={role === 'admin' ? 'email' : 'tel'} autoComplete="username" placeholder={role === 'admin' ? 'admin@company.com' : '98765 43210'} required />
              <Field label="Password" name="password" type="password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} errors={errors} autoComplete="current-password" required />
              {error && <div className="alert alert-danger py-2">{error}</div>}
              <button className="btn btn-primary btn-lg w-100" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Log in</button>
            </form>
            <div className="d-flex justify-content-between mt-3 small">
              {role !== 'admin' && <Link to="/forgot-password">Forgot password?</Link>}
              {role === 'customer' && <Link to="/register">New customer? Register</Link>}
              {role === 'tanker' && <Link to="/register/tanker">Register your tanker</Link>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
