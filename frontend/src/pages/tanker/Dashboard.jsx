import { Link } from 'react-router-dom';
import { useApi } from '../../hooks/useApi';
import { ErrorBox, Loading, Stat, StatusBadge } from '../../components/ui';
import { litres } from '../../utils/format';

export default function TankerDashboard() {
  const { data, loading, error, reload } = useApi('/tanker/dashboard', null, { poll: 20 });
  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const pending = data.verification_status !== 'approved';
  return (
    <div className="content narrow p-0">
      <h1 className="page-title mb-3">Dashboard</h1>
      {pending && (
        <div className={`alert ${data.verification_status === 'rejected' || data.verification_status === 'suspended' ? 'alert-danger' : 'alert-warning'}`}>
          <div className="fw-bold mb-1"><StatusBadge status={data.verification_status} /> </div>
          {data.verification_status === 'pending_review' && 'Admin is reviewing your profile. Add your vehicles, documents and rates so approval is quick. You will not receive bookings until approved.'}
          {data.review_notes && <div className="mt-1">Admin note: {data.review_notes}</div>}
          {data.verification_status === 'correction_requested' && <Link to="/tanker/profile" className="btn btn-sm btn-warning mt-2">Update profile</Link>}
        </div>
      )}
      {data.vehicles.length === 0 && <div className="alert alert-info">Add your first vehicle to start receiving leads. <Link to="/tanker/vehicles?add=1" className="alert-link">Add vehicle →</Link></div>}
      <div className="row g-2 mb-3">
        <div className="col-6"><Stat label="New leads" value={data.new_leads} icon="bell" to="/tanker/leads" tone="warning" /></div>
        <div className="col-6"><Stat label="Active jobs" value={data.active_jobs} icon="truck" to="/tanker/jobs" /></div>
        <div className="col-6"><Stat label="Completed jobs" value={data.completed_jobs} icon="patch-check" to="/tanker/jobs?scope=completed" tone="success" /></div>
        <div className="col-6"><Stat label="Litres delivered" value={litres(data.litres_delivered)} icon="droplet" tone="info" /></div>
      </div>
      {data.pending_pricing > 0 && <div className="alert alert-light border small">{data.pending_pricing} rate change(s) awaiting admin approval.</div>}
      <div className="card">
        <div className="card-header d-flex justify-content-between">My vehicles <Link to="/tanker/vehicles" className="small">Manage</Link></div>
        <div className="list-group list-group-flush">
          {data.vehicles.map((v) => (
            <div key={v.id} className="list-group-item d-flex justify-content-between align-items-center">
              <div><div className="fw-semibold">{v.registration_number}</div><div className="small text-muted">{litres(v.capacity_litres)}</div></div>
              <div className="d-flex gap-1 flex-wrap justify-content-end"><StatusBadge status={v.status} /><StatusBadge status={v.availability} /></div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
