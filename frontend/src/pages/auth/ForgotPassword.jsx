import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { post } from '../../api/client';
import { Field } from '../../components/ui';
import { useToast } from '../../components/Toast';

export default function ForgotPassword() {
  const [step, setStep] = useState(1);
  const [f, setF] = useState({ mobile: '', otp: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [msg, setMsg] = useState(null);
  const [busy, setBusy] = useState(false);
  const toast = useToast();
  const nav = useNavigate();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });

  const run = async (fn) => { setBusy(true); setErrors({}); setMsg(null); try { await fn(); } catch (er) { setErrors(er.errors); if (!Object.keys(er.errors).length) setMsg(er.message); } finally { setBusy(false); } };
  const send = (e) => { e.preventDefault(); run(async () => { const r = await post('/auth/forgot-password', { mobile: f.mobile }); setMsg(r.message); setStep(2); }); };
  const reset = (e) => { e.preventDefault(); run(async () => { const r = await post('/auth/reset-password', f); toast(r.message); nav('/login'); }); };

  return (
    <div className="auth-wrap">
      <div className="auth-card">
        <div className="card"><div className="card-body p-4">
          <h1 className="h4 fw-bold mb-3">Reset password</h1>
          {step === 1 ? (
            <form onSubmit={send}>
              <Field label="Registered mobile number" name="mobile" type="tel" value={f.mobile} onChange={on} errors={errors} required />
              {msg && <div className="alert alert-danger py-2">{msg}</div>}
              <button className="btn btn-primary w-100" disabled={busy}>Send OTP</button>
            </form>
          ) : (
            <form onSubmit={reset}>
              {msg && <div className="alert alert-info py-2 small">{msg}</div>}
              <Field label="6-digit OTP" name="otp" inputMode="numeric" maxLength={6} value={f.otp} onChange={on} errors={errors} required />
              <Field label="New password" name="password" type="password" value={f.password} onChange={on} errors={errors} required />
              <Field label="Confirm new password" name="password_confirmation" type="password" value={f.password_confirmation} onChange={on} errors={errors} required />
              <button className="btn btn-primary w-100" disabled={busy}>Reset password</button>
              <button type="button" className="btn btn-link w-100 mt-1" onClick={() => setStep(1)}>Resend OTP</button>
            </form>
          )}
          <div className="text-center small mt-3"><Link to="/login">Back to login</Link></div>
        </div></div>
      </div>
    </div>
  );
}
