import { Fragment, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { STATUS, label } from '../utils/format';

export function StatusBadge({ status, className = '' }) {
  const [text, tone, icon] = STATUS[status] || [label(status), 'secondary', 'circle'];
  const light = ['warning', 'info'].includes(tone);
  return (
    <span className={`status-badge bg-${tone} ${light ? 'text-dark' : 'text-white'} ${className}`}>
      <i className={`bi bi-${icon}`} aria-hidden="true" />{text}
    </span>
  );
}

export const Loading = ({ text = 'Loading…' }) => (
  <div className="text-center text-muted py-5"><div className="spinner-border spinner-border-sm me-2" role="status" />{text}</div>
);

export const Empty = ({ icon = 'inbox', title, children }) => (
  <div className="text-center py-5 px-3">
    <i className={`bi bi-${icon} display-6 text-muted`} />
    <div className="fw-bold mt-2">{title}</div>
    {children && <div className="text-muted small mt-1">{children}</div>}
  </div>
);

export const ErrorBox = ({ error, onRetry }) => error ? (
  <div className="alert alert-danger d-flex align-items-center gap-2">
    <i className={`bi ${error.offline ? 'bi-wifi-off' : 'bi-exclamation-triangle'}`} />
    <div className="flex-grow-1">{error.message || String(error)}</div>
    {onRetry && <button className="btn btn-sm btn-outline-danger" onClick={onRetry}>Retry</button>}
  </div>
) : null;

export function PageHeader({ title, subtitle, back, children }) {
  return (
    <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
      {back && <Link to={back} className="btn btn-light btn-sm" aria-label="Back"><i className="bi bi-arrow-left" /></Link>}
      <div className="flex-grow-1 min-w-0">
        <h1 className="page-title text-truncate">{title}</h1>
        {subtitle && <div className="text-muted small">{subtitle}</div>}
      </div>
      {children && <div className="d-flex gap-2 flex-wrap">{children}</div>}
    </div>
  );
}

export const Stat = ({ label: l, value, icon, to, tone = 'primary' }) => {
  const body = (
    <div className="card stat h-100">
      <div className="d-flex justify-content-between align-items-start">
        <div className="l">{l}</div>
        {icon && <i className={`bi bi-${icon} text-${tone} fs-5`} />}
      </div>
      <div className="v">{value}</div>
    </div>
  );
  return to ? <Link to={to} className="list-card h-100 d-block">{body}</Link> : body;
};

export function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null;
  return (
    <div className="d-flex justify-content-between align-items-center mt-3 small">
      <span className="text-muted">Page {meta.current_page} of {meta.last_page} · {meta.total} records</span>
      <div className="btn-group">
        <button className="btn btn-outline-secondary btn-sm" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Prev</button>
        <button className="btn btn-outline-secondary btn-sm" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next</button>
      </div>
    </div>
  );
}

/** Text/select/textarea field with server-side error display. */
export function Field({ label: l, name, errors, as = 'input', children, hint, className = 'mb-3', ...props }) {
  const err = errors?.[name]?.[0];
  const cls = `${as === 'select' ? 'form-select' : 'form-control'}${err ? ' is-invalid' : ''}`;
  const Tag = as;
  return (
    <div className={className}>
      {l && <label className="form-label" htmlFor={name}>{l}</label>}
      <Tag id={name} name={name} className={cls} {...props}>{children}</Tag>
      {hint && !err && <div className="form-text">{hint}</div>}
      {err && <div className="invalid-feedback">{err}</div>}
    </div>
  );
}

/** Confirmation modal; set requireReason to collect a reason (cancellation, rejection, etc.). */
export function Confirm({ show, title, message, confirmText = 'Confirm', tone = 'primary', requireReason, reasonLabel = 'Reason', onConfirm, onClose, children }) {
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  useEffect(() => { if (show) { setReason(''); setError(null); } }, [show]);
  if (!show) return null;
  const go = async () => {
    if (requireReason && !reason.trim()) { setError(`${reasonLabel} is required`); return; }
    setBusy(true); setError(null);
    try { await onConfirm(reason.trim()); onClose(); } catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  return (
    <>
      <div className="modal d-block" tabIndex="-1" role="dialog" aria-modal="true">
        <div className="modal-dialog modal-dialog-centered">
          <div className="modal-content">
            <div className="modal-header"><h5 className="modal-title fw-bold">{title}</h5><button className="btn-close" onClick={onClose} aria-label="Close" /></div>
            <div className="modal-body">
              {message && <p className="mb-3">{message}</p>}
              {children}
              {requireReason && (
                <div>
                  <label className="form-label">{reasonLabel}</label>
                  <textarea className="form-control" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
                </div>
              )}
              {error && <div className="alert alert-danger mt-3 mb-0 py-2">{error}</div>}
            </div>
            <div className="modal-footer">
              <button className="btn btn-light" onClick={onClose} disabled={busy}>Back</button>
              <button className={`btn btn-${tone}`} onClick={go} disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}{confirmText}</button>
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show" onClick={onClose} />
    </>
  );
}

export const KV = ({ items }) => (
  <dl className="kv mb-0">
    {items.filter(Boolean).map(([k, v]) => (<Fragment key={k}><dt>{k}</dt><dd>{v ?? '—'}</dd></Fragment>))}
  </dl>
);
