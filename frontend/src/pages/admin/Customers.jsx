import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, KV, Loading, PageHeader, Pagination, Stat, StatusBadge } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { date, dateTime, litres, money } from '../../utils/format';

export function AdminCustomers() {
  const [f, setF] = useState({ search: '', status: '', from: '', to: '' });
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/admin/customers', { ...f, page });
  const nav = useNavigate();
  return (
    <div>
      <PageHeader title="Customers" />
      <div className="row g-2 mb-3">
        <div className="col-md-4"><input className="form-control" placeholder="Name, mobile or email" value={f.search} onChange={(e) => setF({ ...f, search: e.target.value })} /></div>
        <div className="col-6 col-md-2"><select className="form-select" value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}><option value="">Any status</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="inactive">Inactive</option></select></div>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label="Registered from" /></div>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label="Registered to" /></div>
      </div>
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty title="No customers" /> : (
          <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
            <thead><tr><th>Name</th><th>Mobile</th><th>Area</th><th>Bookings</th><th>Enquiries</th><th>Registered</th><th>Status</th></tr></thead>
            <tbody>{data.map((c) => (
              <tr key={c.id} onClick={() => nav(`/admin/customers/${c.id}`)}>
                <td className="fw-semibold">{c.name}</td><td>{c.mobile}</td><td>{c.customer_profile?.area || '—'}</td><td>{c.bookings_count}</td><td>{c.enquiries_count}</td><td className="small">{date(c.created_at)}</td><td><StatusBadge status={c.status} /></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}

export function AdminCustomerDetail() {
  const { id } = useParams();
  const { data, loading, error, reload } = useApi(`/admin/customers/${id}`);
  const [modal, setModal] = useState(null);
  const toast = useToast();
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const c = data.customer;
  const setStatus = (status, reason) => post(`/admin/customers/${id}/status`, { status, reason }).then((r) => { toast(r.message); reload(); });
  return (
    <div>
      <PageHeader title={c.name} back="/admin/customers" subtitle={c.mobile}>
        <StatusBadge status={c.status} />
        {c.status === 'active'
          ? <button className="btn btn-outline-danger btn-sm" onClick={() => setModal('suspended')}>Suspend</button>
          : <button className="btn btn-outline-success btn-sm" onClick={() => setStatus('active')}>Reactivate</button>}
      </PageHeader>
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3"><Stat label="Bookings" value={data.totals.bookings} /></div>
        <div className="col-6 col-md-3"><Stat label="Completed" value={data.totals.completed} /></div>
        <div className="col-6 col-md-3"><Stat label="Completed value" value={money(data.totals.value)} /></div>
        <div className="col-6 col-md-3"><Stat label="Outstanding" value={money(data.totals.outstanding)} tone="danger" /></div>
      </div>
      <div className="row g-3">
        <div className="col-lg-4"><div className="card card-body">
          <KV items={[['Email', c.email], ['Alternate', c.customer_profile?.alternate_mobile], ['Address', c.customer_profile?.address], ['Area', c.customer_profile?.area], ['Registered', dateTime(c.created_at)], c.status_reason && ['Status reason', c.status_reason]]} />
          {c.addresses?.length > 0 && <><hr /><div className="fw-semibold small mb-1">Saved addresses</div>{c.addresses.map((a) => <div key={a.id} className="small"><b>{a.label}:</b> {a.address}</div>)}</>}
        </div></div>
        <div className="col-lg-8">
          <div className="card mb-3"><div className="card-header">Bookings</div><div className="list-group list-group-flush">
            {data.bookings.length === 0 && <div className="list-group-item small text-muted">None</div>}
            {data.bookings.map((b) => <Link key={b.id} to={`/admin/bookings/${b.id}`} className="list-group-item list-group-item-action d-flex justify-content-between small"><span><b>{b.booking_number}</b> · {litres(b.quantity_litres)} · {date(b.preferred_date)} · {money(b.total_amount)}</span><StatusBadge status={b.status} /></Link>)}
          </div></div>
          <div className="card"><div className="card-header">Enquiries</div><div className="list-group list-group-flush">
            {data.enquiries.length === 0 && <div className="list-group-item small text-muted">None</div>}
            {data.enquiries.map((e) => <Link key={e.id} to={`/admin/enquiries/${e.id}`} className="list-group-item list-group-item-action d-flex justify-content-between small"><span><b>{e.enquiry_number}</b> · {e.water_type} · {dateTime(e.created_at)}</span><StatusBadge status={e.status} /></Link>)}
          </div></div>
        </div>
      </div>
      <Confirm show={modal === 'suspended'} title="Suspend customer?" tone="danger" requireReason confirmText="Suspend" message="They will be logged out and cannot make bookings." onClose={() => setModal(null)} onConfirm={(reason) => setStatus('suspended', reason)} />
    </div>
  );
}
