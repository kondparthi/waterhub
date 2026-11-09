import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { post, toForm } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { useGeoShare } from '../../hooks/useGeoShare';
import { Confirm, ErrorBox, KV, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { MultiMap, icons } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, dateTime, litres, money } from '../../utils/format';

const GPS_TEXT = {
  idle: ['gps-off', 'Location sharing is off'],
  requesting: ['gps-stale', 'Waiting for location permission…'],
  live: ['gps-live', 'Sharing live location'],
  denied: ['gps-off', 'Location permission denied — enable it in browser settings'],
  unsupported: ['gps-off', 'This browser cannot share location'],
  error: ['gps-stale', 'Location unavailable — move to open sky'],
};

export default function JobDetail() {
  const { id } = useParams();
  const { data: a, loading, error, reload, setData } = useApi(`/tanker/assignments/${id}`);
  const tracking = !!a?.tracking?.active;
  const gps = useGeoShare(a?.id, tracking, a?.tracking?.interval_seconds || 12);
  const [modal, setModal] = useState(null);
  const [proof, setProof] = useState({ delivered_litres: '', proof_notes: '', proof: null });
  const toast = useToast();

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const b = a.booking;
  const act = async (path, body, msg) => {
    const r = await post(`/tanker/assignments/${id}/${path}`, body);
    toast(r.message || msg);
    if (r.data) setData(r.data); else reload();
  };
  const nav = (lat, lng) => `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  const [dot, text] = GPS_TEXT[gps.status];
  const markers = [
    b.latitude && { key: 'd', position: [b.latitude, b.longitude], icon: icons.drop, popup: 'Delivery' },
    b.source?.latitude && { key: 's', position: [b.source.latitude, b.source.longitude], icon: icons.source, popup: b.source.name },
  ].filter(Boolean);

  return (
    <div className="content narrow p-0">
      <PageHeader title={b.booking_number} back="/tanker/jobs" subtitle={a.vehicle?.registration_number}><StatusBadge status={a.status} /></PageHeader>

      {tracking && (
        <div className={`alert ${gps.status === 'live' ? 'alert-success' : 'alert-warning'} d-flex gap-2 align-items-start`}>
          <span className={`gps-dot ${dot} mt-2`} />
          <div className="small">
            <div className="fw-bold">{text}</div>
            <div>{gps.lastSentAt ? `Last sent ${dateTime(gps.lastSentAt)}` : 'Not sent yet'}{gps.lastError && ` · ${gps.lastError}`}</div>
            <div className="text-muted">{gps.background
              ? 'Location keeps sharing in the background (see the WaterHub notification). Allow location "All the time" if Android asks.'
              : 'Keep this screen open while driving. Location can pause if the phone locks — use the WaterHub Android app for background tracking.'}</div>
          </div>
        </div>
      )}

      <div className="card mb-3">
        <div className="card-body d-grid gap-2">
          {a.status === 'assigned' && (<>
            <button className="btn btn-success btn-lg" onClick={() => act('accept', null, 'Accepted')}><i className="bi bi-check-lg me-1" />Accept job</button>
            <button className="btn btn-outline-danger" onClick={() => setModal('reject')}>Reject</button>
          </>)}
          {a.status === 'accepted' && (<>
            <button className="btn btn-primary btn-lg" onClick={() => setModal('start')}><i className="bi bi-play-fill me-1" />Start delivery</button>
            <button className="btn btn-outline-danger" onClick={() => setModal('reject')}>Can't do this job</button>
          </>)}
          {a.status === 'in_transit' && <button className="btn btn-primary btn-lg" onClick={() => setModal('arrive')}><i className="bi bi-pin-map me-1" />Mark arrived</button>}
          {['in_transit', 'arrived'].includes(a.status) && <button className={`btn btn-lg ${a.status === 'arrived' ? 'btn-success' : 'btn-outline-success'}`} onClick={() => { setProof({ delivered_litres: b.quantity_litres, proof_notes: '', proof: null }); setModal('deliver'); }}><i className="bi bi-droplet-half me-1" />Mark delivered</button>}
          {['delivered', 'completed'].includes(a.status) && <div className="text-center text-success fw-semibold"><i className="bi bi-patch-check me-1" />Delivered {litres(a.delivered_litres)} on {dateTime(a.delivered_at)}</div>}
          {b.latitude && ['accepted', 'in_transit', 'arrived'].includes(a.status) && <a className="btn btn-light" href={nav(b.latitude, b.longitude)} target="_blank" rel="noreferrer"><i className="bi bi-sign-turn-right me-1" />Navigate to customer</a>}
          {b.source?.latitude && a.status === 'accepted' && <a className="btn btn-light" href={nav(b.source.latitude, b.source.longitude)} target="_blank" rel="noreferrer"><i className="bi bi-droplet me-1" />Navigate to {b.source.name}</a>}
        </div>
      </div>

      {markers.length > 0 && <div className="mb-3"><MultiMap markers={markers} height={260} /></div>}

      <div className="card mb-3"><div className="card-body">
        <KV items={[
          ['Water', `${litres(b.quantity_litres)} · ${b.water_type}`],
          ['When', `${date(b.preferred_date)} ${b.preferred_time_slot || ''}`],
          ['Area', b.area],
          b.trips && ['Trips', b.trips],
          ['Amount', b.estimated_amount ? money(b.estimated_amount) : '—'],
          b.source && ['Load from', `${b.source.name}${b.source.address ? ' — ' + b.source.address : ''}`],
          ['Customer', b.customer_name || <span className="text-muted">Shown after you accept</span>],
          ['Phone', b.delivery_address ? <a href={`tel:${b.customer_mobile}`}>{b.customer_mobile}</a> : b.customer_mobile],
          b.delivery_address && ['Address', b.delivery_address],
          b.landmark && ['Landmark', b.landmark],
          b.notes && ['Customer notes', b.notes],
          a.rejection_reason && ['Rejected', a.rejection_reason],
        ]} />
      </div></div>

      <Confirm show={modal === 'reject'} title="Reject job?" tone="danger" requireReason confirmText="Reject" onClose={() => setModal(null)} onConfirm={(reason) => act('reject', { reason }, 'Rejected')} />
      <Confirm show={modal === 'start'} title="Start delivery?" message="Your phone will ask to share location. The customer and admin will see your tanker on the map until delivery." confirmText="Start & share location" onClose={() => setModal(null)} onConfirm={() => act('start', null, 'Started')} />
      <Confirm show={modal === 'arrive'} title="Arrived at the customer?" confirmText="Yes, arrived" onClose={() => setModal(null)} onConfirm={() => act('arrive', null, 'Arrived')} />
      <Confirm show={modal === 'deliver'} title="Confirm delivery" tone="success" confirmText="Submit delivery" onClose={() => setModal(null)}
        onConfirm={() => post(`/tanker/assignments/${id}/delivered`, toForm(proof)).then((r) => { toast(r.message); setData(r.data); })}>
        <label className="form-label">Litres delivered</label>
        <input type="number" className="form-control mb-3" value={proof.delivered_litres} onChange={(e) => setProof({ ...proof, delivered_litres: e.target.value })} />
        <label className="form-label">Photo proof (optional)</label>
        <input type="file" accept="image/*" capture="environment" className="form-control mb-3" onChange={(e) => setProof({ ...proof, proof: e.target.files[0] })} />
        <label className="form-label">Note (optional)</label>
        <textarea className="form-control" rows={2} value={proof.proof_notes} onChange={(e) => setProof({ ...proof, proof_notes: e.target.value })} placeholder="e.g. Filled sump, customer signed" />
      </Confirm>
    </div>
  );
}
