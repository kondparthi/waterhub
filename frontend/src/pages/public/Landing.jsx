import { Link, Navigate } from 'react-router-dom';
import { homeFor, useAuth } from '../../context/AuthContext';

export default function Landing() {
  const { user, meta } = useAuth();
  if (user) return <Navigate to={homeFor(user.role)} replace />;
  const name = meta?.settings?.business_name || 'Water Tanker Services';
  return (
    <div>
      <section className="hero">
        <div className="container py-4">
          <nav className="d-flex align-items-center mb-5">
            <span className="fw-bold fs-5"><i className="bi bi-droplet-fill text-info me-2" />{name}</span>
            <Link to="/login" className="btn btn-outline-light btn-sm ms-auto">Log in</Link>
          </nav>
          <div className="row align-items-center g-4 pb-5">
            <div className="col-lg-7">
              <h1 className="display-5 mb-3">Water tanker at your door — booked in a minute, tracked live.</h1>
              <p className="lead opacity-75 mb-4">Tell us how many litres you need and where. We pick the right borewell and the nearest verified tanker, then you follow it on the map until it arrives.</p>
              <div className="d-flex flex-wrap gap-2">
                <Link to="/register" className="btn btn-info btn-lg text-dark"><i className="bi bi-plus-circle me-2" />Book a tanker</Link>
                <Link to="/login" className="btn btn-outline-light btn-lg">I have an account</Link>
              </div>
            </div>
            <div className="col-lg-5">
              <div className="card bg-white bg-opacity-10 border-0 text-white p-3">
                {[['1', 'Request', 'Water type, litres, date and a map pin.'], ['2', 'Get a quote', 'Per-litre or per-trip price, confirmed before dispatch.'], ['3', 'Track', 'Live tanker location once the driver starts.']].map(([n, t, d]) => (
                  <div key={n} className="d-flex gap-3 py-2"><span className="step-pill">{n}</span><div><div className="fw-bold">{t}</div><div className="small opacity-75">{d}</div></div></div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>
      <section className="container py-5">
        <div className="row g-4">
          <div className="col-md-6">
            <div className="card card-body h-100">
              <h2 className="h5 fw-bold"><i className="bi bi-truck text-primary me-2" />Own a water tanker?</h2>
              <p className="text-muted">Register your vehicles, set your per-litre or per-trip rates, and receive delivery jobs near you. Admin verifies every operator before live bookings.</p>
              <div><Link to="/register/tanker" className="btn btn-primary">Register as tanker owner</Link> <Link to="/login?as=tanker" className="btn btn-link">Tanker login</Link></div>
            </div>
          </div>
          <div className="col-md-6">
            <div className="card card-body h-100">
              <h2 className="h5 fw-bold"><i className="bi bi-chat-left-text text-primary me-2" />Not sure what you need?</h2>
              <p className="text-muted">Send an enquiry with your requirement. We'll call you back with a quotation and convert it to a booking when you're ready.</p>
              <div><Link to="/register" className="btn btn-outline-primary">Create account &amp; enquire</Link></div>
            </div>
          </div>
        </div>
        <div className="text-center small text-muted mt-5"><Link to="/terms">Terms</Link> · <Link to="/privacy">Privacy &amp; location notice</Link> · <Link to="/login?as=admin">Staff login</Link></div>
      </section>
    </div>
  );
}
