import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function Legal({ kind }) {
  const { meta } = useAuth();
  const name = meta?.settings?.business_name || 'Water Tanker Services';
  return (
    <div className="container py-4" style={{ maxWidth: 780 }}>
      <Link to="/" className="btn btn-light btn-sm mb-3"><i className="bi bi-arrow-left" /> Home</Link>
      {kind === 'terms' ? (
        <div className="card card-body">
          <h1 className="page-title mb-3">Terms of service</h1>
          <p className="text-muted small">Template — review with the business owner before go-live.</p>
          <ol className="d-grid gap-2">
            <li>Bookings are requests until {name} confirms the quotation, water source and availability.</li>
            <li>Prices are calculated per litre or per trip from the approved rate on the delivery date and shown before confirmation.</li>
            <li>Cancellation fees, if configured, are shown before you cancel.</li>
            <li>Payment may be collected by cash, UPI or bank transfer and is recorded by our staff.</li>
            <li>Tanker owners must keep vehicle, registration and driver details accurate and current.</li>
          </ol>
        </div>
      ) : (
        <div className="card card-body">
          <h1 className="page-title mb-3">Privacy &amp; location notice</h1>
          <p className="text-muted small">Template — review with the business owner before go-live.</p>
          <h2 className="h6 fw-bold mt-2">What we collect</h2>
          <p>Name, mobile number, delivery address and map pin, booking and payment records. For tanker owners: vehicle, registration documents and driver details.</p>
          <h2 className="h6 fw-bold">Driver location</h2>
          <p>Drivers share GPS location only after they start an accepted delivery, and sharing stops when it is delivered or cancelled. The location is visible only to our admin team and the customer of that booking. Location history is kept for a limited period and then deleted.</p>
          <h2 className="h6 fw-bold">Contact details</h2>
          <p>A customer's exact address and phone number are shared with a driver only after the driver accepts the assignment.</p>
        </div>
      )}
    </div>
  );
}
