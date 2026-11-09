import { Navigate, Route, Routes } from 'react-router-dom';
import Protected from './routes/Protected';
import AppLayout from './layouts/AppLayout';
import Landing from './pages/public/Landing';
import Legal from './pages/public/Legal';
import Login from './pages/auth/Login';
import Register from './pages/auth/Register';
import ForgotPassword from './pages/auth/ForgotPassword';
import Notifications from './pages/Notifications';
import ChangePassword from './pages/ChangePassword';
import CustomerDashboard from './pages/customer/Dashboard';
import NewOrder from './pages/customer/NewOrder';
import CustomerBookings from './pages/customer/Bookings';
import CustomerBookingDetail from './pages/customer/BookingDetail';
import { CustomerEnquiries, CustomerEnquiryDetail } from './pages/customer/Enquiries';
import CustomerProfile from './pages/customer/Profile';
import TankerDashboard from './pages/tanker/Dashboard';
import { Jobs, Leads } from './pages/tanker/Jobs';
import JobDetail from './pages/tanker/JobDetail';
import Vehicles from './pages/tanker/Vehicles';
import TankerProfile from './pages/tanker/Profile';
import AdminDashboard from './pages/admin/Dashboard';
import AdminBookings from './pages/admin/Bookings';
import AdminBookingDetail from './pages/admin/BookingDetail';
import { AdminEnquiries, AdminEnquiryDetail } from './pages/admin/Enquiries';
import { AdminCustomerDetail, AdminCustomers } from './pages/admin/Customers';
import { AdminPricing, AdminTankerDetail, AdminTankers, AdminVehicles } from './pages/admin/Tankers';
import Sources from './pages/admin/Sources';
import LiveMap from './pages/admin/LiveMap';
import Payments from './pages/admin/Payments';
import Reports from './pages/admin/Reports';
import Settings from './pages/admin/Settings';

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Landing />} />
      <Route path="/terms" element={<Legal kind="terms" />} />
      <Route path="/privacy" element={<Legal kind="privacy" />} />
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register kind="customer" />} />
      <Route path="/register/tanker" element={<Register kind="tanker" />} />
      <Route path="/forgot-password" element={<ForgotPassword />} />

      <Route element={<Protected role="customer" />}>
        <Route path="/customer" element={<AppLayout />}>
          <Route index element={<CustomerDashboard />} />
          <Route path="book" element={<NewOrder mode="booking" />} />
          <Route path="enquiry" element={<NewOrder mode="enquiry" />} />
          <Route path="bookings" element={<CustomerBookings />} />
          <Route path="bookings/:id" element={<CustomerBookingDetail />} />
          <Route path="enquiries" element={<CustomerEnquiries />} />
          <Route path="enquiries/:id" element={<CustomerEnquiryDetail />} />
          <Route path="profile" element={<CustomerProfile />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="password" element={<ChangePassword />} />
        </Route>
      </Route>

      <Route element={<Protected role="tanker" />}>
        <Route path="/tanker" element={<AppLayout />}>
          <Route index element={<TankerDashboard />} />
          <Route path="leads" element={<Leads />} />
          <Route path="jobs" element={<Jobs />} />
          <Route path="jobs/:id" element={<JobDetail />} />
          <Route path="vehicles" element={<Vehicles />} />
          <Route path="profile" element={<TankerProfile />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="password" element={<ChangePassword />} />
        </Route>
      </Route>

      <Route element={<Protected role="admin" />}>
        <Route path="/admin" element={<AppLayout />}>
          <Route index element={<AdminDashboard />} />
          <Route path="bookings" element={<AdminBookings />} />
          <Route path="bookings/:id" element={<AdminBookingDetail />} />
          <Route path="enquiries" element={<AdminEnquiries />} />
          <Route path="enquiries/:id" element={<AdminEnquiryDetail />} />
          <Route path="customers" element={<AdminCustomers />} />
          <Route path="customers/:id" element={<AdminCustomerDetail />} />
          <Route path="tankers" element={<AdminTankers />} />
          <Route path="tankers/:id" element={<AdminTankerDetail />} />
          <Route path="vehicles" element={<AdminVehicles />} />
          <Route path="pricing" element={<AdminPricing />} />
          <Route path="sources" element={<Sources />} />
          <Route path="live" element={<LiveMap />} />
          <Route path="payments" element={<Payments />} />
          <Route path="reports" element={<Reports />} />
          <Route path="settings" element={<Settings />} />
          <Route path="notifications" element={<Notifications />} />
          <Route path="password" element={<ChangePassword />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
