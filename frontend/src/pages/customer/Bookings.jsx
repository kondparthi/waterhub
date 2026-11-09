import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../../hooks/useApi';
import { Empty, ErrorBox, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { date, litres, money } from '../../utils/format';

export default function CustomerBookings() {
  const [scope, setScope] = useState('active');
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/my/bookings', { scope, page });
  return (
    <div className="content narrow p-0">
      <PageHeader title="My bookings"><Link to="/customer/book" className="btn btn-primary btn-sm"><i className="bi bi-plus" /> New</Link></PageHeader>
      <ul className="nav nav-pills mb-3">
        {[['active', 'Active'], ['history', 'History'], ['', 'All']].map(([k, l]) => <li key={k} className="nav-item"><button className={`nav-link ${scope === k ? 'active' : ''}`} onClick={() => { setScope(k); setPage(1); }}>{l}</button></li>)}
      </ul>
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : !data?.length ? <Empty icon="card-list" title="No bookings here">Book a tanker to get started.</Empty> : (
        <div className="d-grid gap-2">
          {data.map((b) => (
            <Link key={b.id} to={`/customer/bookings/${b.id}`} className="list-card">
              <div className="card card-body py-3">
                <div className="d-flex justify-content-between align-items-start gap-2">
                  <div className="min-w-0"><div className="fw-bold">{b.booking_number}</div><div className="small text-muted text-truncate">{b.area || b.delivery_address}</div></div>
                  <StatusBadge status={b.status} />
                </div>
                <div className="d-flex justify-content-between small mt-2"><span>{litres(b.quantity_litres)} · {b.water_type} · {date(b.preferred_date)}</span>{Number(b.total_amount) > 0 && <span className="fw-semibold">{money(b.total_amount)}</span>}</div>
              </div>
            </Link>
          ))}
        </div>
      )}
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
