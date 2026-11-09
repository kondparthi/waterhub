import { useState } from 'react';
import { downloadFile } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Empty, ErrorBox, Loading, PageHeader, Stat } from '../../components/ui';
import { useToast } from '../../components/Toast';
import { date, label, litres, money } from '../../utils/format';

const TYPES = [['overview', 'Overview'], ['daily', 'Daily'], ['status', 'By status'], ['sources', 'By source'], ['tankers', 'By tanker'], ['customers', 'By customer'], ['cancellations', 'Cancellations'], ['payments', 'Payments']];
const firstOfMonth = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toLocaleDateString('en-CA'); };
const endOfMonth = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth() + 1, 0).toLocaleDateString('en-CA'); };
const MONEY = ['value', 'revenue', 'paid', 'amount', 'total_amount', 'amount_paid', 'cancellation_fee'];
const LITRES = ['litres', 'capacity'];

function Table({ rows }) {
  if (!rows?.length) return <Empty icon="bar-chart" title="No data for this range" />;
  const cols = Object.keys(rows[0]).filter((c) => !['id', 'customer_id'].includes(c));
  const fmt = (c, v) => v && typeof v === 'object' ? (v.name || '') : MONEY.includes(c) ? money(v) : LITRES.includes(c) ? litres(v) : ['day', 'preferred_date'].includes(c) ? date(v) : c === 'status' || c === 'payment_status' || c === 'method' ? label(v) : v ?? '—';
  return (
    <div className="table-responsive"><table className="table mb-0">
      <thead><tr>{cols.map((c) => <th key={c} className={MONEY.includes(c) || LITRES.includes(c) ? 'text-end' : ''}>{label(c)}</th>)}</tr></thead>
      <tbody>{rows.map((r, i) => <tr key={i}>{cols.map((c) => <td key={c} className={MONEY.includes(c) || LITRES.includes(c) ? 'text-end' : ''}>{fmt(c, r[c])}</td>)}</tr>)}</tbody>
    </table></div>
  );
}

function DailyBars({ rows }) {
  if (!rows?.length) return null;
  const max = Math.max(...rows.map((r) => Number(r.value) || 0), 1);
  return (
    <div className="d-flex align-items-end gap-1 px-2 pt-3" style={{ height: 160 }} role="img" aria-label="Daily booking value">
      {rows.map((r) => (
        <div key={r.day} className="flex-fill d-flex flex-column align-items-center" title={`${date(r.day)}: ${money(r.value)} · ${r.bookings} bookings`}>
          <div style={{ width: '100%', maxWidth: 28, height: `${(Number(r.value) / max) * 120}px`, minHeight: 2, background: 'var(--wt-aqua)', borderRadius: '4px 4px 0 0' }} />
          <div className="text-muted" style={{ fontSize: '.6rem' }}>{r.day.slice(8)}</div>
        </div>
      ))}
    </div>
  );
}

export default function Reports() {
  const [type, setType] = useState('overview');
  const [range, setRange] = useState({ from: firstOfMonth(), to: endOfMonth() });
  const { data, loading, error, reload } = useApi('/admin/reports/summary', { type, ...range });
  const toast = useToast();
  const csv = () => downloadFile('/admin/reports/summary', { type, ...range, format: 'csv' }, `report-${type}-${range.from}-${range.to}.csv`).catch((e) => toast(e.message, 'danger'));

  return (
    <div>
      <PageHeader title="Reports" subtitle="Bookings are grouped by delivery date.">
        {type !== 'overview' && <button className="btn btn-outline-primary btn-sm" onClick={csv}><i className="bi bi-download me-1" />CSV</button>}
      </PageHeader>
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} aria-label="From" /></div>
        <div className="col-6 col-md-3"><input type="date" className="form-control" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} aria-label="To" /></div>
      </div>
      <div className="d-flex gap-1 overflow-auto mb-3">{TYPES.map(([k, l]) => <button key={k} className={`btn btn-sm text-nowrap ${type === k ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => setType(k)}>{l}</button>)}</div>
      <ErrorBox error={error} onRetry={reload} />
      {loading ? <Loading /> : type === 'overview' ? (
        <div className="row g-2">
          <div className="col-6 col-md-3"><Stat label="Bookings" value={data.bookings} /></div>
          <div className="col-6 col-md-3"><Stat label="Completed" value={data.completed} tone="success" /></div>
          <div className="col-6 col-md-3"><Stat label="Cancelled" value={data.cancelled} tone="danger" /></div>
          <div className="col-6 col-md-3"><Stat label="Litres delivered" value={litres(data.litres_delivered)} /></div>
          <div className="col-6 col-md-4"><Stat label="Booking value" value={money(data.booking_value)} /></div>
          <div className="col-6 col-md-4"><Stat label="Collected" value={money(data.collected)} tone="success" /></div>
          <div className="col-12 col-md-4"><Stat label="Outstanding (all delivered)" value={money(data.outstanding)} tone="danger" /></div>
        </div>
      ) : type === 'payments' ? (
        <>
          <div className="card mb-3"><div className="card-header">Collected by method</div><Table rows={data.collected} /></div>
          <div className="card"><div className="card-header">Outstanding balances</div><Table rows={data.outstanding} /></div>
        </>
      ) : (
        <div className="card">{type === 'daily' && <DailyBars rows={data} />}<Table rows={data} /></div>
      )}
    </div>
  );
}
