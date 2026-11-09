import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, KV, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { date, dateTime, litres, money } from '../../utils/format';

export function CustomerEnquiries() {
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/my/enquiries', { page });
  return (
    <div className="content narrow p-0">
      <PageHeader title="My enquiries"><Link to="/customer/enquiry" className="btn btn-primary btn-sm"><i className="bi bi-plus" /> New</Link></PageHeader>
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : !data?.length ? <Empty icon="chat-left-text" title="No enquiries yet" /> : (
        <div className="d-grid gap-2">
          {data.map((e) => (
            <Link key={e.id} to={`/customer/enquiries/${e.id}`} className="list-card">
              <div className="card card-body py-3">
                <div className="d-flex justify-content-between"><span className="fw-bold">{e.enquiry_number}</span><StatusBadge status={e.status} /></div>
                <div className="small text-muted mt-1">{e.water_type} · {litres(e.quantity_litres || e.requested_capacity)} · {dateTime(e.created_at)}</div>
              </div>
            </Link>
          ))}
        </div>
      )}
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

export function CustomerEnquiryDetail() {
  const { id } = useParams();
  const { data: e, loading, error, reload } = useApi(`/my/enquiries/${id}`);
  const [modal, setModal] = useState(null);
  const toast = useToast();
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const open = !['converted', 'closed', 'cancelled'].includes(e.status);
  return (
    <div className="content narrow p-0">
      <PageHeader title={e.enquiry_number} back="/customer/enquiries"><StatusBadge status={e.status} /></PageHeader>
      {e.status === 'quotation_sent' && (
        <div className="card border-primary mb-3"><div className="card-body">
          <h2 className="h6 fw-bold">Quotation: {money(e.quoted_amount)}</h2>
          <p className="small mb-2">{e.quotation_details}</p>
          {e.customer_accepted_at
            ? <div className="alert alert-success py-2 mb-0 small">You accepted on {dateTime(e.customer_accepted_at)}. We'll convert it to a booking shortly.</div>
            : <button className="btn btn-primary" onClick={() => post(`/my/enquiries/${id}/accept-quote`).then((r) => { toast(r.message); reload(); })}>Accept quotation</button>}
        </div></div>
      )}
      {e.booking && <Link className="alert alert-success d-block" to={`/customer/bookings/${e.booking.id}`}>Converted to booking <b>{e.booking.booking_number}</b> → view</Link>}
      <div className="card mb-3"><div className="card-body">
        <KV items={[
          ['Water type', e.water_type], ['Quantity', litres(e.quantity_litres)], e.requested_capacity && ['Tanker size', litres(e.requested_capacity)],
          ['Preferred', `${date(e.preferred_date)} ${e.preferred_time_slot || ''}`], ['Address', e.delivery_address], e.notes && ['Notes', e.notes],
          e.close_reason && ['Closed', e.close_reason],
        ]} />
      </div></div>
      {open && <button className="btn btn-outline-danger w-100" onClick={() => setModal('cancel')}>Cancel enquiry</button>}
      <Confirm show={modal === 'cancel'} title="Cancel enquiry?" tone="danger" requireReason confirmText="Cancel enquiry" onClose={() => setModal(null)}
        onConfirm={(reason) => post(`/my/enquiries/${id}/cancel`, { reason }).then(() => { toast('Enquiry cancelled'); reload(); })} />
    </div>
  );
}
