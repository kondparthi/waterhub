import { Link } from 'react-router-dom';
import { useApi } from '../../hooks/useApi';
import { Empty, ErrorBox, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { MultiMap, icons } from '../../components/Maps';
import { ago } from '../../utils/format';

export default function LiveMap() {
  const { data, loading, error, reload } = useApi('/admin/tracking/active', null, { poll: 8 });
  if (loading) return <Loading />;
  const d = data?.deliveries || [];
  const markers = [
    ...d.filter((x) => x.location).map((x) => ({ key: 'v' + x.assignment_id, position: [x.location.latitude, x.location.longitude], icon: icons.tanker(x.location.stale), popup: <><b>{x.vehicle?.registration_number}</b><br />{x.booking?.booking_number} · {ago(x.location.age_seconds)}</> })),
    ...d.map((x) => ({ key: 'd' + x.assignment_id, position: [x.booking.latitude, x.booking.longitude], icon: icons.drop, popup: <>{x.booking.booking_number}<br />{x.booking.delivery_address}</> })),
    ...(data?.sources || []).map((s) => ({ key: 's' + s.id, position: [s.latitude, s.longitude], icon: icons.source, popup: s.name })),
  ];
  return (
    <div>
      <PageHeader title="Live deliveries" subtitle="Refreshes every few seconds. Amber trucks have not sent GPS recently." />
      <ErrorBox error={error} onRetry={reload} />
      <div className="row g-3">
        <div className="col-lg-8"><MultiMap markers={markers} tall /></div>
        <div className="col-lg-4">
          {d.length === 0 ? <div className="card"><Empty icon="truck" title="No active deliveries" /></div> : d.map((x) => (
            <Link key={x.assignment_id} to={`/admin/bookings/${x.booking.id}`} className="list-card mb-2 d-block">
              <div className="card card-body py-2">
                <div className="d-flex justify-content-between"><b>{x.vehicle?.registration_number}</b><StatusBadge status={x.status} /></div>
                <div className="small">{x.booking.booking_number} · {x.booking.area}</div>
                <div className="small text-muted">{x.driver?.name} {x.driver?.mobile}</div>
                <div className="small mt-1">
                  {!x.location ? <span className="text-muted"><span className="gps-dot gps-off me-1" />No GPS yet</span>
                    : x.location.stale ? <span className="text-warning fw-semibold"><span className="gps-dot gps-stale me-1" />GPS stale · {ago(x.location.age_seconds)}</span>
                    : <span className="text-success"><span className="gps-dot gps-live me-1" />Live · {ago(x.location.age_seconds)}</span>}
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
