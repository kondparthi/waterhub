import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { get } from '../api/client';

const NAV = {
  customer: [
    ['/customer', 'house', 'Home', true],
    ['/customer/book', 'plus-circle', 'Book'],
    ['/customer/bookings', 'card-list', 'Bookings'],
    ['/customer/enquiries', 'chat-left-text', 'Enquiries'],
    ['/customer/profile', 'person', 'Profile'],
  ],
  tanker: [
    ['/tanker', 'speedometer2', 'Home', true],
    ['/tanker/leads', 'bell', 'Leads'],
    ['/tanker/jobs', 'truck', 'Jobs'],
    ['/tanker/vehicles', 'car-front', 'Vehicles'],
    ['/tanker/profile', 'person', 'Profile'],
  ],
  admin: [
    ['Operations'],
    ['/admin', 'speedometer2', 'Dashboard', true],
    ['/admin/bookings', 'card-list', 'Bookings'],
    ['/admin/enquiries', 'chat-left-text', 'Enquiries'],
    ['/admin/live', 'broadcast', 'Live map'],
    ['/admin/payments', 'wallet2', 'Payments'],
    ['Network'],
    ['/admin/tankers', 'people', 'Tanker owners'],
    ['/admin/vehicles', 'truck', 'Vehicles'],
    ['/admin/pricing', 'tags', 'Rate approvals'],
    ['/admin/sources', 'droplet', 'Water sources'],
    ['/admin/customers', 'person-lines-fill', 'Customers'],
    ['Insights'],
    ['/admin/reports', 'bar-chart', 'Reports'],
    ['/admin/settings', 'gear', 'Settings'],
  ],
};

export default function AppLayout() {
  const { user, logout, meta } = useAuth();
  const [open, setOpen] = useState(false);
  const [menu, setMenu] = useState(false);
  const [unread, setUnread] = useState(user?.unread_notifications || 0);
  const loc = useLocation();
  const nav = useNavigate();
  const items = NAV[user.role];

  useEffect(() => { setOpen(false); setMenu(false); }, [loc.pathname]);
  useEffect(() => {
    const load = () => document.visibilityState === 'visible' && get('/notifications', { unread: 1, per_page: 1 }).then((r) => setUnread(r.meta?.unread ?? 0)).catch(() => {});
    load();
    const t = setInterval(load, 30000);
    return () => clearInterval(t);
  }, [loc.pathname]);

  const bottom = user.role === 'admin' ? null : items.filter((i) => i.length > 1);

  return (
    <div className="app-shell">
      <aside className={`sidebar${open ? ' open' : ''}`} onClick={(e) => e.target === e.currentTarget && setOpen(false)}>
        <div className="brand"><i className="bi bi-droplet-fill text-info fs-4" />{meta?.settings?.business_name || 'Water Tanker'}</div>
        <nav className="nav flex-column pb-4">
          {items.map((i) => i.length === 1
            ? <div key={i[0]} className="nav-section">{i[0]}</div>
            : <NavLink key={i[0]} to={i[0]} end={i[3]} className="nav-link"><i className={`bi bi-${i[1]}`} />{i[2]}</NavLink>)}
          <NavLink to={`/${user.role}/notifications`} className="nav-link"><i className="bi bi-bell" />Notifications {unread > 0 && <span className="badge bg-info text-dark ms-auto">{unread}</span>}</NavLink>
        </nav>
      </aside>
      <div className="main">
        <header className="topbar">
          <button className="btn btn-light d-lg-none" onClick={() => setOpen(true)} aria-label="Menu"><i className="bi bi-list" /></button>
          <Link to={`/${user.role}`} className="fw-bold text-decoration-none text-dark d-lg-none text-truncate"><i className="bi bi-droplet-fill text-info me-1" />{meta?.settings?.business_name || 'Water Tanker'}</Link>
          <div className="ms-auto d-flex align-items-center gap-2">
            <Link to={`/${user.role}/notifications`} className="btn btn-light position-relative" aria-label="Notifications">
              <i className="bi bi-bell" />
              {unread > 0 && <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{unread > 9 ? '9+' : unread}</span>}
            </Link>
            <div className="dropdown position-relative">
              <button className="btn btn-light dropdown-toggle d-flex align-items-center gap-2" aria-expanded={menu} onClick={() => setMenu(!menu)}>
                <i className="bi bi-person-circle" /><span className="d-none d-sm-inline text-truncate" style={{ maxWidth: 140 }}>{user.name}</span>
              </button>
              {menu && <div className="position-fixed top-0 start-0 w-100 h-100" style={{ zIndex: 999 }} onClick={() => setMenu(false)} />}
              <ul className={`dropdown-menu dropdown-menu-end${menu ? ' show' : ''}`} style={{ right: 0, zIndex: 1000 }} onClick={() => setMenu(false)}>
                <li><span className="dropdown-item-text small text-muted">{user.mobile || user.email} · {user.role}</span></li>
                {user.role !== 'admin' && <li><Link className="dropdown-item" to={`/${user.role}/profile`}>Profile</Link></li>}
                <li><Link className="dropdown-item" to={`/${user.role}/password`}>Change password</Link></li>
                <li><hr className="dropdown-divider" /></li>
                <li><button className="dropdown-item text-danger" onClick={async () => { await logout(); nav('/'); }}>Log out</button></li>
              </ul>
            </div>
          </div>
        </header>
        <main className="content"><Outlet /></main>
      </div>
      {bottom && (
        <nav className="bottom-nav" aria-label="Primary">
          {bottom.map((i) => <NavLink key={i[0]} to={i[0]} end={i[3]}><i className={`bi bi-${i[1]}`} />{i[2]}</NavLink>)}
        </nav>
      )}
    </div>
  );
}
