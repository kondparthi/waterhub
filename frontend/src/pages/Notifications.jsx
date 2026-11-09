import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { post } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import { Empty, ErrorBox, Loading, PageHeader, Pagination } from '../components/ui';
import { dateTime } from '../utils/format';

export default function Notifications() {
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/notifications', { page });
  const nav = useNavigate();

  const open = async (n) => {
    if (!n.read_at) await post(`/notifications/${n.id}/read`).catch(() => {});
    const d = n.data_json || {};
    const base = `/${user.role}`;
    if (user.role === 'tanker' && d.assignment_id) return nav(`${base}/jobs/${d.assignment_id}`);
    if (user.role === 'tanker' && d.vehicle_id) return nav(`${base}/vehicles`);
    if (d.booking_id && user.role !== 'tanker') return nav(`${base}/bookings/${d.booking_id}`);
    if (d.enquiry_id && user.role !== 'tanker') return nav(`${base}/enquiries/${d.enquiry_id}`);
    if (user.role === 'admin' && d.tanker_profile_id) return nav(`/admin/tankers/${d.tanker_profile_id}`);
    if (user.role === 'admin' && d.pricing_id) return nav('/admin/pricing');
    if (user.role === 'admin' && d.vehicle_id) return nav('/admin/vehicles');
    reload(true);
  };

  return (
    <div className="content narrow p-0">
      <PageHeader title="Notifications">
        <button className="btn btn-outline-primary btn-sm" onClick={() => post('/notifications/read-all').then(() => reload())}>Mark all read</button>
      </PageHeader>
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : !data?.length ? <Empty icon="bell-slash" title="No notifications yet" /> : (
        <div className="list-group">
          {data.map((n) => (
            <button key={n.id} className={`list-group-item list-group-item-action py-3 ${n.read_at ? '' : 'bg-info bg-opacity-10'}`} onClick={() => open(n)}>
              <div className="d-flex justify-content-between gap-2">
                <span className="fw-bold">{!n.read_at && <span className="gps-dot gps-live me-2" />}{n.title}</span>
                <small className="text-muted text-nowrap">{dateTime(n.created_at)}</small>
              </div>
              <div className="small text-muted mt-1">{n.message}</div>
            </button>
          ))}
        </div>
      )}
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
