import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useApi } from '../../hooks/useApi';
import { Empty, ErrorBox, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { date, litres, money } from '../../utils/format';

const TABS = [['', 'All'], ['new,under_review', 'To review'], ['quoted', 'Quoted'], ['confirmed', 'To assign'], ['assigned,accepted', 'Assigned'], ['in_transit,arrived', 'On the way'], ['delivered', 'To close'], ['completed', 'Completed'], ['cancelled,rejected', 'Cancelled']];

export default function AdminBookings() {
  const [params, setParams] = useSearchParams();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const status = params.get('status') || '';
  const [filters, setFilters] = useState({ date: '', payment_status: '' });
  const { data, meta, loading, error, reload } = useApi('/admin/bookings', { status, page, search: params.get('q') || '', ...filters }, { poll: 30 });
  const nav = useNavigate();
  const setP = (k, v) => { const p = Object.fromEntries(params); v ? (p[k] = v) : delete p[k]; setParams(p); setPage(1); };

  return (
    <div>
      <PageHeader title="Bookings" />
      <div className="d-flex gap-1 flex-nowrap overflow-auto mb-3 pb-1">
        {TABS.map(([k, l]) => <button key={k} className={`btn btn-sm text-nowrap ${status === k ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setP('status', k)}>{l}</button>)}
      </div>
      <div className="row g-2 mb-3">
        <form className="col-md-5" onSubmit={(e) => { e.preventDefault(); setP('q', search); }}>
          <div className="input-group"><input className="form-control" placeholder="Booking no., customer, mobile, area" value={search} onChange={(e) => setSearch(e.target.value)} /><button className="btn btn-outline-secondary"><i className="bi bi-search" /></button></div>
        </form>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={filters.date} onChange={(e) => setFilters({ ...filters, date: e.target.value })} aria-label="Delivery date" /></div>
        <div className="col-6 col-md-3"><select className="form-select" value={filters.payment_status} onChange={(e) => setFilters({ ...filters, payment_status: e.target.value })}><option value="">Any payment</option><option value="unpaid">Unpaid</option><option value="partial">Part paid</option><option value="paid">Paid</option></select></div>
      </div>
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty title="No bookings match" /> : (
          <div className="table-responsive">
            <table className="table table-hover mb-0 align-middle">
              <thead><tr><th>Booking</th><th>Customer</th><th>Delivery</th><th>Qty</th><th>Source</th><th>Tanker</th><th className="text-end">Amount</th><th>Status</th></tr></thead>
              <tbody>
                {data.map((b) => (
                  <tr key={b.id} onClick={() => nav(`/admin/bookings/${b.id}`)}>
                    <td className="fw-semibold text-nowrap">{b.booking_number}</td>
                    <td><div>{b.customer?.name}</div><div className="small text-muted">{b.customer?.mobile}</div></td>
                    <td className="text-nowrap"><div>{date(b.preferred_date)}</div><div className="small text-muted">{b.area}</div></td>
                    <td className="text-nowrap">{litres(b.quantity_litres)}</td>
                    <td className="small">{b.borewell?.name || <span className="text-muted">—</span>}</td>
                    <td className="small text-nowrap">{b.active_assignment?.vehicle?.registration_number || '—'}</td>
                    <td className="text-end text-nowrap">{Number(b.total_amount) > 0 ? money(b.total_amount) : '—'}<div><StatusBadge status={b.payment_status} className="mt-1" /></div></td>
                    <td><StatusBadge status={b.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
