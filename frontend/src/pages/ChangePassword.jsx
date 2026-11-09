import { useState } from 'react';
import { post } from '../api/client';
import { Field, PageHeader } from '../components/ui';
import { useToast } from '../components/Toast';

export default function ChangePassword() {
  const [f, setF] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const toast = useToast();
  const on = (e) => setF({ ...f, [e.target.name]: e.target.value });
  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setErrors({});
    try { await post('/auth/change-password', f); toast('Password changed'); setF({ current_password: '', password: '', password_confirmation: '' }); }
    catch (er) { setErrors(er.errors); if (!Object.keys(er.errors).length) toast(er.message, 'danger'); } finally { setBusy(false); }
  };
  return (
    <div style={{ maxWidth: 460 }}>
      <PageHeader title="Change password" />
      <form className="card card-body" onSubmit={submit}>
        <Field label="Current password" name="current_password" type="password" value={f.current_password} onChange={on} errors={errors} required />
        <Field label="New password" name="password" type="password" value={f.password} onChange={on} errors={errors} hint="At least 8 characters with letters and numbers" required />
        <Field label="Confirm new password" name="password_confirmation" type="password" value={f.password_confirmation} onChange={on} errors={errors} required />
        <button className="btn btn-primary" disabled={busy}>Update password</button>
      </form>
    </div>
  );
}
