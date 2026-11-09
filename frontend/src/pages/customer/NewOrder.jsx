import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { post } from '../../api/client';
import OrderForm from '../../components/OrderForm';
import { PageHeader } from '../../components/ui';
import { useToast } from '../../components/Toast';

export default function NewOrder({ mode }) {
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const [done, setDone] = useState(null);
  const toast = useToast();
  const nav = useNavigate();
  const submit = async (payload) => {
    setBusy(true); setErrors({});
    try {
      const r = await post(mode === 'booking' ? '/bookings' : '/enquiries', payload);
      setDone(r.data);
      window.scrollTo(0, 0);
    } catch (e) { setErrors(e.errors); toast(e.message, 'danger'); } finally { setBusy(false); }
  };
  if (done) {
    const ref = done.booking_number || done.enquiry_number;
    return (
      <div className="content narrow p-0 text-center">
        <div className="card card-body py-5">
          <i className="bi bi-check-circle-fill text-success display-4" />
          <h1 className="h4 fw-bold mt-3">{mode === 'booking' ? 'Booking request received' : 'Enquiry sent'}</h1>
          <p className="text-muted">Your reference number</p>
          <div className="display-6 fw-bold mb-3" style={{ letterSpacing: '.02em' }}>{ref}</div>
          <p className="small text-muted">{mode === 'booking' ? "We'll review it, choose the water source and send you a quotation." : "Our team will call you with a quotation."}</p>
          <button className="btn btn-primary" onClick={() => nav(mode === 'booking' ? `/customer/bookings/${done.id}` : `/customer/enquiries/${done.id}`)}>View details</button>
        </div>
      </div>
    );
  }
  return (
    <div className="content narrow p-0">
      <PageHeader title={mode === 'booking' ? 'Book a water tanker' : 'Water enquiry'} back="/customer" subtitle={mode === 'enquiry' ? 'Not ready to book? Tell us what you need and we will call you back with a quote.' : null} />
      <OrderForm mode={mode} onSubmit={submit} busy={busy} errors={errors} />
    </div>
  );
}
