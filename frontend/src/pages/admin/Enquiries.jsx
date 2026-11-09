import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, Field, KV, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { MultiMap, icons } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, dateTime, label, litres, money } from '../../utils/format';

export function AdminEnquiries() {
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1);
  const [q, setQ] = useState('');
  const [area, setArea] = useState('');
  const status = params.get('status') || '';
  const { data, meta, loading, error, reload } = useApi('/admin/enquiries', { status, page, search: q, area });
  const nav = useNavigate();
  return (
    <div>
      <PageHeader title="Enquiries" />
      <div className="d-flex gap-1 overflow-auto mb-3">
        {[['', 'All'], ['new', 'New'], ['contacted', 'Contacted'], ['quotation_sent', 'Quotation sent'], ['converted', 'Converted'], ['closed', 'Closed'], ['cancelled', 'Cancelled']].map(([k, l]) => <button key={k} className={`btn btn-sm text-nowrap ${status === k ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => { setParams(k ? { status: k } : {}); setPage(1); }}>{l}</button>)}
      </div>
      <div className="row g-2 mb-3">
        <div className="col-md-5"><input className="form-control" placeholder="Search number, customer, mobile" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-md-3"><input className="form-control" placeholder="Area" value={area} onChange={(e) => setArea(e.target.value)} /></div>
      </div>
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty title="No enquiries" /> : (
          <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
            <thead><tr><th>Enquiry</th><th>Customer</th><th>Requirement</th><th>Area</th><th>Received</th><th>Status</th></tr></thead>
            <tbody>{data.map((e) => (
              <tr key={e.id} onClick={() => nav(`/admin/enquiries/${e.id}`)}>
                <td className="fw-semibold">{e.enquiry_number}</td>
                <td>{e.customer?.name}<div className="small text-muted">{e.customer?.mobile}</div></td>
                <td>{e.water_type}<div className="small text-muted">{litres(e.quantity_litres || e.requested_capacity)}</div></td>
                <td>{e.area || '—'}</td>
                <td className="small">{dateTime(e.created_at)}</td>
                <td><StatusBadge status={e.status} />{e.customer_accepted_at && <div className="small text-success">Customer accepted</div>}</td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

export function AdminEnquiryDetail() {
  const { id } = useParams();
  const { data: e, loading, error, reload } = useApi(`/admin/enquiries/${id}`);
  const [note, setNote] = useState({ note: '', type: 'call' });
  const [quote, setQuote] = useState({ quoted_amount: '', quotation_details: '' });
  const [errors, setErrors] = useState({});
  const [modal, setModal] = useState(null);
  const toast = useToast();
  const nav = useNavigate();
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const open = !['converted', 'closed', 'cancelled'].includes(e.status);
  const run = async (fn) => { setErrors({}); try { const r = await fn(); toast(r.message); reload(); return r; } catch (er) { setErrors(er.errors); toast(er.message, 'danger'); throw er; } };

  return (
    <div>
      <PageHeader title={e.enquiry_number} back="/admin/enquiries" subtitle={`Received ${dateTime(e.created_at)}`}>
        <StatusBadge status={e.status} />
        {open && <button className="btn btn-primary btn-sm" onClick={() => setModal('convert')}><i className="bi bi-arrow-right-circle me-1" />Convert to booking</button>}
        {open && <button className="btn btn-outline-danger btn-sm" onClick={() => setModal('close')}>Close</button>}
      </PageHeader>
      {e.booking && <Link className="alert alert-success d-block" to={`/admin/bookings/${e.booking.id}`}>Converted to <b>{e.booking.booking_number}</b> ({label(e.booking.status)}) →</Link>}
      <div className="row g-3">
        <div className="col-lg-6">
          <div className="card mb-3"><div className="card-body">
            <KV items={[
              ['Customer', <Link to={`/admin/customers/${e.customer?.id}`}>{e.customer?.name}</Link>],
              ['Mobile', <a href={`tel:${e.customer?.mobile}`}>{e.customer?.mobile}</a>],
              e.alternate_contact && ['Alternate', e.alternate_contact],
              ['Water type', e.water_type], ['Quantity', litres(e.quantity_litres)], e.requested_capacity && ['Tanker size', litres(e.requested_capacity)],
              ['Preferred', `${date(e.preferred_date)} ${e.preferred_time_slot || ''}`], ['Address', e.delivery_address], e.landmark && ['Landmark', e.landmark], e.notes && ['Notes', e.notes],
              e.quoted_amount && ['Quoted', `${money(e.quoted_amount)}${e.customer_accepted_at ? ' · accepted ' + dateTime(e.customer_accepted_at) : ''}`],
              e.close_reason && ['Closed', e.close_reason],
            ]} />
          </div></div>
          <MultiMap markers={[{ key: 'd', position: [e.latitude, e.longitude], icon: icons.drop }]} height={240} />
        </div>
        <div className="col-lg-6">
          {open && (
            <div className="card mb-3"><div className="card-header">Record contact</div><div className="card-body">
              <form onSubmit={(ev) => { ev.preventDefault(); run(() => post(`/admin/enquiries/${id}/notes`, note)).then(() => setNote({ ...note, note: '' })); }}>
                <div className="btn-group btn-group-sm mb-2">{['call', 'note'].map((t) => <button type="button" key={t} className={`btn ${note.type === t ? 'btn-primary' : 'btn-outline-primary'}`} onClick={() => setNote({ ...note, type: t })}>{t === 'call' ? 'Call log' : 'Note'}</button>)}</div>
                <Field name="note" as="textarea" rows={2} value={note.note} onChange={(ev) => setNote({ ...note, note: ev.target.value })} errors={errors} placeholder="What was discussed?" required />
                <button className="btn btn-outline-primary btn-sm">Save</button>
              </form>
            </div></div>
          )}
          {open && (
            <div className="card mb-3"><div className="card-header">Send quotation</div><div className="card-body">
              <form onSubmit={(ev) => { ev.preventDefault(); run(() => post(`/admin/enquiries/${id}/quote`, quote)); }}>
                <Field label="Amount ₹" name="quoted_amount" type="number" step="0.01" value={quote.quoted_amount} onChange={(ev) => setQuote({ ...quote, quoted_amount: ev.target.value })} errors={errors} required />
                <Field label="Details" name="quotation_details" as="textarea" rows={2} value={quote.quotation_details} onChange={(ev) => setQuote({ ...quote, quotation_details: ev.target.value })} errors={errors} placeholder="e.g. 5,000 L × ₹0.50 + ₹200 delivery" required />
                <button className="btn btn-primary btn-sm">Send to customer</button>
              </form>
            </div></div>
          )}
          <div className="card"><div className="card-header">Activity</div><div className="card-body">
            <ul className="timeline">{e.notes_log?.map((n) => <li key={n.id}><div className="d-flex justify-content-between"><b className="small">{label(n.type)}</b><small className="text-muted">{dateTime(n.created_at)}</small></div><div>{n.note}</div>{n.user && <div className="small text-muted">{n.user.name}</div>}</li>)}</ul>
            {!e.notes_log?.length && <div className="small text-muted">No activity yet.</div>}
          </div></div>
        </div>
      </div>
      <Confirm show={modal === 'convert'} title="Convert to booking?" confirmText="Create booking" onClose={() => setModal(null)}
        message="A booking is created with this enquiry's customer, location and requirement. You'll then select a source and quote it."
        onConfirm={() => post(`/admin/enquiries/${id}/convert`).then((r) => { toast(r.message); nav(`/admin/bookings/${r.data.id}`); })} />
      <Confirm show={modal === 'close'} title="Close enquiry" tone="danger" requireReason confirmText="Close enquiry" onClose={() => setModal(null)} onConfirm={(reason) => post(`/admin/enquiries/${id}/close`, { status: 'closed', reason }).then(() => { toast('Enquiry closed'); reload(); })} />
    </div>
  );
}
