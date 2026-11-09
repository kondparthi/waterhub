import { useState } from 'react';
import { Link } from 'react-router-dom';
import { post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, Empty, ErrorBox, Loading, PageHeader, Pagination, StatusBadge } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { dateTime, label, money } from '../../utils/format';

export default function Payments() {
  const [f, setF] = useState({ method: '', status: 'received', from: '', to: '' });
  const [page, setPage] = useState(1);
  const { data, meta, loading, error, reload } = useApi('/admin/payments', { ...f, page });
  const [voidP, setVoidP] = useState(null);
  const toast = useToast();
  return (
    <div>
      <PageHeader title="Payments" subtitle="Cash, UPI and bank transfers recorded against bookings. Record new payments from the booking page." />
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3"><select className="form-select" value={f.method} onChange={(e) => setF({ ...f, method: e.target.value })}><option value="">All methods</option><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></div>
        <div className="col-6 col-md-2"><select className="form-select" value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}><option value="received">Received</option><option value="void">Void</option><option value="">All</option></select></div>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label="From" /></div>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label="To" /></div>
      </div>
      {meta && <div className="alert alert-light border py-2"><b>{money(meta.total_received)}</b> received for the selected filters</div>}
      <ErrorBox error={error} onRetry={reload} />
      <div className="card">
        {loading ? <Loading /> : !data?.length ? <Empty icon="wallet2" title="No payments" /> : (
          <div className="table-responsive"><table className="table mb-0 align-middle">
            <thead><tr><th>Date</th><th>Booking</th><th>Customer</th><th>Method</th><th>Reference</th><th className="text-end">Amount</th><th>Received by</th><th>Status</th><th /></tr></thead>
            <tbody>{data.map((p) => (
              <tr key={p.id}>
                <td className="small text-nowrap">{dateTime(p.paid_at)}</td>
                <td><Link to={`/admin/bookings/${p.booking_id}`}>{p.booking?.booking_number}</Link></td>
                <td className="small">{p.booking?.customer?.name}</td>
                <td>{label(p.method)}</td><td className="small">{p.transaction_reference || '—'}</td>
                <td className="text-end fw-semibold">{money(p.amount)}</td>
                <td className="small">{p.receiver?.name}</td>
                <td><StatusBadge status={p.status} />{p.void_reason && <div className="small text-muted">{p.void_reason}</div>}</td>
                <td>{p.status === 'received' && <button className="btn btn-sm btn-light text-danger" onClick={() => setVoidP(p)}>Void</button>}</td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Pagination meta={meta} onPage={setPage} />
      <Confirm show={!!voidP} title="Void payment?" tone="danger" requireReason confirmText="Void" message={voidP && `${money(voidP.amount)} on ${voidP.booking?.booking_number}. The booking's payment status is recalculated.`} onClose={() => setVoidP(null)} onConfirm={(reason) => post(`/admin/payments/${voidP.id}/void`, { reason }).then(() => { toast('Payment voided'); reload(); })} />
    </div>
  );
}
