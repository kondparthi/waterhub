import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useApi } from '../../hooks/useApi';
import { ErrorBox, Loading, StatusBadge } from '../../components/ui';
import { date, litres, money } from '../../utils/format';

export default function CustomerDashboard() {
  const { user } = useAuth();
  const { data, loading, error, reload } = useApi('/customer/dashboard', null, { poll: 30 });
  if (loading) return <Loading />;
  const a = data?.active_booking;
  return (
    <div className="content narrow p-0">
      <h1 className="page-title mb-1">Hi {user.name.split(' ')[0]} 👋</h1>
      <p className="text-muted mb-3">Need water? Book a tanker or ask us first.</p>
      <ErrorBox error={error} onRetry={reload} />
      <div className="row g-2 mb-3">
        <div className="col-6"><Link to="/customer/book" className="btn btn-primary btn-lg w-100 py-3"><i className="bi bi-truck d-block fs-3" />Book tanker</Link></div>
        <div className="col-6"><Link to="/customer/enquiry" className="btn btn-outline-primary btn-lg w-100 py-3 bg-white"><i className="bi bi-chat-left-text d-block fs-3" />Send enquiry</Link></div>
      </div>
      {a && (
        <Link to={`/customer/bookings/${a.id}`} className="list-card mb-3 d-block">
          <div className="card border-info">
            <div className="card-body">
              <div className="d-flex justify-content-between align-items-start mb-2">
                <div><div className="small text-muted">Active booking</div><div className="fw-bold">{a.booking_number}</div></div>
                <StatusBadge status={a.status} />
              </div>
              <div className="small">{litres(a.quantity_litres)} · {a.water_type} · {date(a.preferred_date)} {a.preferred_time_slot}</div>
              {Number(a.total_amount) > 0 && <div className="small fw-semibold mt-1">{money(a.total_amount)}</div>}
              {a.status === 'quoted' && <div className="alert alert-primary py-2 mt-2 mb-0 small"><i className="bi bi-receipt me-1" />Quotation ready — tap to review and confirm.</div>}
              {['in_transit', 'arrived'].includes(a.status) && <div className="btn btn-success btn-sm mt-2"><i className="bi bi-geo-alt me-1" />Track my tanker</div>}
            </div>
          </div>
        </Link>
      )}
      <div className="row g-2 mb-3 text-center">
        <div className="col-4"><div className="card stat"><div className="v">{data?.active_count ?? 0}</div><div className="l">Active</div></div></div>
        <div className="col-4"><div className="card stat"><div className="v">{data?.completed_count ?? 0}</div><div className="l">Completed</div></div></div>
        <div className="col-4"><Link to="/customer/enquiries" className="list-card"><div className="card stat"><div className="v">{data?.open_enquiries ?? 0}</div><div className="l">Open enquiries</div></div></Link></div>
      </div>
      {data?.recent_bookings?.length > 0 && (
        <div className="card">
          <div className="card-header d-flex justify-content-between">Recent bookings <Link to="/customer/bookings" className="small">View all</Link></div>
          <div className="list-group list-group-flush">
            {data.recent_bookings.map((b) => (
              <Link key={b.id} to={`/customer/bookings/${b.id}`} className="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div><div className="fw-semibold">{b.booking_number}</div><div className="small text-muted">{litres(b.quantity_litres)} · {date(b.preferred_date)}</div></div>
                <StatusBadge status={b.status} />
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
