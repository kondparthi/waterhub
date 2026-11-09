import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { openFile, post } from '../../api/client';
import { useApi } from '../../hooks/useApi';
import { Confirm, ErrorBox, KV, Loading, PageHeader, StatusBadge } from '../../components/ui';
import { GpsState, PriceBreakdown, ProgressSteps, StatusHistory } from '../../components/BookingParts';
import { TrackingMap } from '../../components/Maps';
import { useToast } from '../../components/Toast';
import { date, litres, money } from '../../utils/format';

export default function CustomerBookingDetail() {
  const { id } = useParams();
  const { data: b, loading, error, reload } = useApi(`/bookings/${id}`, null, { poll: 20 });
  const trackable = b?.trackable;
  const { data: track } = useApi(trackable ? `/bookings/${id}/tracking` : null, null, { poll: 8 });
  const [modal, setModal] = useState(null);
  const toast = useToast();

  if (loading) return <Loading />;
  if (error) return <ErrorBox error={error} onRetry={reload} />;
  const a = b.current_assignment;
  const act = async (fn, msg) => { await fn(); toast(msg); reload(); };

  return (
    <div className="content narrow p-0">
      <PageHeader title={b.booking_number} back="/customer/bookings" subtitle={`${litres(b.quantity_litres)} · ${b.water_type}`}><StatusBadge status={b.status} /></PageHeader>
      <ProgressSteps status={b.status} />

      {b.status === 'quoted' && (
        <div className="card border-primary mb-3">
          <div className="card-body">
            <h2 className="h6 fw-bold"><i className="bi bi-receipt me-1" />Your quotation</h2>
            <PriceBreakdown b={b} />
            <div className="d-flex gap-2 mt-3">
              <button className="btn btn-primary flex-fill" onClick={() => setModal('accept')}>Accept &amp; confirm</button>
              {b.can_cancel && <button className="btn btn-outline-danger" onClick={() => setModal('cancel')}>Decline</button>}
            </div>
          </div>
        </div>
      )}

      {trackable && (
        <div className="card mb-3">
          <div className="card-header d-flex justify-content-between align-items-center"><span><i className="bi bi-geo-alt me-1" />Live tracking</span><GpsState location={track?.location} trackable={track?.trackable ?? true} /></div>
          <div className="card-body p-2"><TrackingMap data={track} /></div>
        </div>
      )}

      {a && (
        <div className="card mb-3">
          <div className="card-header">Tanker</div>
          <div className="card-body">
            <KV items={[
              ['Vehicle', `${a.vehicle} (${litres(a.capacity_litres)})`],
              ['Driver', a.driver_name],
              a.driver_mobile && ['Driver phone', <a href={`tel:${a.driver_mobile}`}>{a.driver_mobile}</a>],
              ['Status', <StatusBadge status={a.status} />],
              a.delivered_litres && ['Delivered', litres(a.delivered_litres)],
              a.proof_notes && ['Driver note', a.proof_notes],
            ]} />
            {a.has_proof && <button className="btn btn-sm btn-outline-secondary mt-2" onClick={() => openFile(`/files/assignments/${a.id}/proof`)}><i className="bi bi-image me-1" />Delivery photo</button>}
          </div>
        </div>
      )}

      <div className="card mb-3">
        <div className="card-header">Details</div>
        <div className="card-body">
          <KV items={[
            ['Delivery', `${date(b.preferred_date)} ${b.preferred_time_slot || ''}`],
            ['Address', b.delivery_address],
            b.landmark && ['Landmark', b.landmark],
            b.requested_capacity && ['Tanker size', litres(b.requested_capacity)],
            b.source && ['Water source', b.source.name],
            b.notes && ['Notes', b.notes],
            ['Payment', <StatusBadge status={b.payment_status} />],
            b.status === 'cancelled' && ['Cancelled', `${b.cancel_reason}${Number(b.cancellation_fee) > 0 ? ` · fee ${money(b.cancellation_fee)}` : ''}`],
          ]} />
        </div>
      </div>

      {b.status !== 'quoted' && Number(b.total_amount) > 0 && <div className="card mb-3"><div className="card-header">Price</div><div className="card-body"><PriceBreakdown b={b} /></div></div>}

      <div className="card mb-3"><div className="card-header">History</div><div className="card-body"><StatusHistory items={b.status_history} /></div></div>

      {b.can_cancel && b.status !== 'quoted' && <button className="btn btn-outline-danger w-100 mb-4" onClick={() => setModal('cancel')}>Cancel booking</button>}

      <Confirm show={modal === 'accept'} title="Confirm booking?" message={`You agree to pay ${money(b.total_amount)} for this delivery.`} confirmText="Confirm booking"
        onClose={() => setModal(null)} onConfirm={() => act(() => post(`/my/bookings/${id}/accept-quote`), 'Booking confirmed')} />
      <Confirm show={modal === 'cancel'} title="Cancel this booking?" tone="danger" confirmText="Cancel booking" requireReason
        message={b.cancellation_fee_if_cancelled > 0 ? `A cancellation fee of ${money(b.cancellation_fee_if_cancelled)} applies at this stage.` : 'No cancellation fee applies at this stage.'}
        onClose={() => setModal(null)} onConfirm={(reason) => act(() => post(`/my/bookings/${id}/cancel`, { reason }), 'Booking cancelled')} />
    </div>
  );
}
