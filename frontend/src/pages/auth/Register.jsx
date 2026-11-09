import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Field } from '../../components/ui';
import { MapPicker } from '../../components/Maps';
import { useToast } from '../../components/Toast';

export default function Register({ kind = 'customer' }) {
  const tanker = kind === 'tanker';
  const [f, setF] = useState({ name: '', business_name: '', mobile: '', email: '', password: '', password_confirmation: '', address: '', area: '', base_latitude: null, base_longitude: null, is_driver: true, agree: false });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const { register } = useAuth();
  const toast = useToast();
  const nav = useNavigate();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

  const submit = async (e) => {
    e.preventDefault();
    if (!f.agree) return setError('Please accept the terms and privacy notice.');
    setBusy(true); setErrors({}); setError(null);
    try {
      const r = await register(kind, f);
      toast(r.message);
      nav(tanker ? '/tanker/vehicles?add=1' : '/customer/book', { replace: true });
    } catch (er) { setErrors(er.errors); setError(Object.keys(er.errors).length ? 'Please fix the highlighted fields.' : er.message); } finally { setBusy(false); }
  };

  return (
    <div className="auth-wrap">
      <div className="auth-card" style={{ maxWidth: tanker ? 560 : 440 }}>
        <Link to="/" className="d-block text-center text-white fw-bold fs-5 mb-3 text-decoration-none"><i className="bi bi-droplet-fill text-info me-2" />Water Tanker</Link>
        <form className="card" onSubmit={submit}>
          <div className="card-body p-4">
            <h1 className="h4 fw-bold mb-1">{tanker ? 'Register as tanker owner' : 'Create your account'}</h1>
            <p className="text-muted small mb-3">{tanker ? 'Admin reviews every operator before live bookings are sent.' : 'Book water tankers and track deliveries.'}</p>
            <Field label={tanker ? 'Owner name' : 'Your name'} name="name" value={f.name} onChange={on} errors={errors} required />
            {tanker && <Field label="Business name (optional)" name="business_name" value={f.business_name} onChange={on} errors={errors} />}
            <Field label="Mobile number" name="mobile" type="tel" inputMode="tel" value={f.mobile} onChange={on} errors={errors} placeholder="98765 43210" required />
            <Field label="Email (optional)" name="email" type="email" value={f.email} onChange={on} errors={errors} />
            {tanker && <Field label="Address" name="address" as="textarea" rows={2} value={f.address} onChange={on} errors={errors} required />}
            {tanker && (
              <div className="mb-3">
                <label className="form-label">Base location (where your tankers usually park)</label>
                <MapPicker lat={f.base_latitude} lng={f.base_longitude} onChange={(a, b) => setF({ ...f, base_latitude: a, base_longitude: b })} height={220} />
              </div>
            )}
            <div className="row g-2">
              <div className="col-sm-6"><Field label="Password" name="password" type="password" value={f.password} onChange={on} errors={errors} autoComplete="new-password" required /></div>
              <div className="col-sm-6"><Field label="Confirm password" name="password_confirmation" type="password" value={f.password_confirmation} onChange={on} errors={errors} autoComplete="new-password" required /></div>
            </div>
            <div className="form-text mt-n2 mb-3">At least 8 characters with letters and numbers.</div>
            {tanker && <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="is_driver" name="is_driver" checked={f.is_driver} onChange={on} /><label className="form-check-label" htmlFor="is_driver">I also drive my tanker</label></div>}
            <div className="form-check mb-3"><input className="form-check-input" type="checkbox" id="agree" name="agree" checked={f.agree} onChange={on} /><label className="form-check-label small" htmlFor="agree">I accept the <Link to="/terms" target="_blank">terms</Link> and <Link to="/privacy" target="_blank">privacy &amp; location notice</Link></label></div>
            {error && <div className="alert alert-danger py-2">{error}</div>}
            <button className="btn btn-primary btn-lg w-100" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Register</button>
            <div className="text-center small mt-3">Already registered? <Link to={`/login?as=${kind}`}>Log in</Link></div>
          </div>
        </form>
      </div>
    </div>
  );
}
