import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { date, dateTime, litres, money } from '../../utils/format';

function JobCard({ a, children }) {
  const b = a.booking;
  return (
    <div className="card mb-2">
      <div className="card-body">
        <div className="d-flex justify-content-between align-items-start gap-2">
          <div><div className="fw-bold">{b.booking_number}</div><div className="small text-muted">{a.vehicle?.registration_number}</div></div>
          <StatusBadge status={a.status} />
        </div>
        <div className="row g-2 small mt-1">
          <div className="col-6"><i className="bi bi-droplet me-1 text-info" />{litres(b.quantity_litres)} · {b.water_type}</div>
          <div className="col-6"><i className="bi bi-calendar me-1 text-info" />{date(b.preferred_date)} {b.preferred_time_slot}</div>
          <div className="col-6"><i className="bi bi-geo me-1 text-info" />{b.area || 'Area not given'}{b.approx_distance_km != null && ` · ~${b.approx_distance_km} km`}</div>
          <div className="col-6"><i className="bi bi-cash me-1 text-info" />{b.estimated_amount ? money(b.estimated_amount) : '—'}{b.trips > 1 && ` · ${b.trips} trips`}</div>
          {b.source && <div className="col-12"><i className="bi bi-droplet-half me-1 text-info" />Load from {b.source.name}</div>}
        </div>
        {children}
      </div>
    </div>
  );
}

export function Leads() {
  const { data, loading, error, reload } = useApi('/tanker/leads', null, { poll: 15 });
  const [rej, setRej] = useState(null);
  const toast = useToast();
  const accept = async (id) => { try { await post(`/tanker/assignments/${id}/accept`); toast('Accepted — customer details unlocked'); reload(); } catch (e) { toast(e.message, 'danger'); } };
  return (
    <div className="content narrow p-0">
      <PageHeader title="New leads" subtitle="Customer contact and exact address are shared after you accept." />
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : !data?.length ? <Empty icon="bell" title="No new leads">Leads appear here when admin assigns a booking to your vehicle.</Empty> : data.map((a) => (
        <JobCard key={a.id} a={a}>
          <div className="small text-muted mt-2">Assigned {dateTime(a.assigned_at)}{a.response_due_at && ` · respond by ${dateTime(a.response_due_at)}`}{a.response_overdue && <span className="text-danger fw-semibold"> · overdue</span>}</div>
          <div className="d-flex gap-2 mt-3">
            <button className="btn btn-success flex-fill" onClick={() => accept(a.id)}><i className="bi bi-check-lg me-1" />Accept</button>
            <button className="btn btn-outline-danger" onClick={() => setRej(a.id)}>Reject</button>
          </div>
        </JobCard>
      ))}
      <Confirm show={!!rej} title="Reject this lead?" tone="danger" confirmText="Reject" requireReason reasonLabel="Reason (helps admin reassign)" onClose={() => setRej(null)}
        onConfirm={(reason) => post(`/tanker/assignments/${rej}/reject`, { reason }).then(() => { toast('Lead rejected'); reload(); })} />
    </div>
  );
}

export function Jobs() {
  const [params, setParams] = useSearchParams();
  const scope = params.get('scope') || 'active';
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/tanker/assignments', { scope, page }, { poll: scope === 'active' ? 20 : null });
  return (
    <div className="content narrow p-0">
      <PageHeader title="My jobs" />
      <ul className="nav nav-pills mb-3">
        {[['active', 'Active'], ['completed', 'Completed'], ['closed', 'Rejected / closed']].map(([k, l]) => <li key={k} className="nav-item"><button className={`nav-link ${scope === k ? 'active' : ''}`} onClick={() => { setParams({ scope: k }); setPage(1); }}>{l}</button></li>)}
      </ul>
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : !data?.length ? <Empty icon="truck" title="Nothing here" /> : data.map((a) => (
        <Link key={a.id} to={`/tanker/jobs/${a.id}`} className="list-card"><JobCard a={a}>
          {a.booking.customer_name && <div className="small mt-2"><i className="bi bi-person me-1" />{a.booking.customer_name} · {a.booking.delivery_address}</div>}
        </JobCard></Link>
      ))}
      <Pagination meta={meta} onPage={setPage} />
    </div>
  );
}
