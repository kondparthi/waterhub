# API reference (v1)

Base URL: `/api/v1`. Auth: `Authorization: Bearer <token>` from `POST /auth/login`.

All responses: `{ success, message, data, meta? , errors? }`. Status codes: 200/201 ok, 401 not logged in, 403 role/ownership, 404, 409 business-rule conflict, 422 validation.

| Method | Endpoint | Access |
|---|---|---|
| GET | `/admin/admins` | admin |
| POST | `/admin/admins` | admin |
| PUT | `/admin/admins/{admin}` | admin |
| GET | `/admin/audit-logs` | admin |
| GET | `/admin/bookings` | admin |
| POST | `/admin/bookings/{booking}/assign` | admin |
| POST | `/admin/bookings/{booking}/cancel` | admin |
| POST | `/admin/bookings/{booking}/complete` | admin |
| POST | `/admin/bookings/{booking}/confirm` | admin |
| GET | `/admin/bookings/{booking}/eligible-tankers` | admin |
| POST | `/admin/bookings/{booking}/quote` | admin |
| POST | `/admin/bookings/{booking}/reject` | admin |
| POST | `/admin/bookings/{booking}/review` | admin |
| POST | `/admin/bookings/{booking}/source` | admin |
| GET | `/admin/bookings/{booking}/sources` | admin |
| GET | `/admin/borewells` | admin |
| POST | `/admin/borewells` | admin |
| GET | `/admin/borewells/{borewell}` | admin |
| PUT | `/admin/borewells/{borewell}` | admin |
| DELETE | `/admin/borewells/{borewell}` | admin |
| GET | `/admin/customers` | admin |
| GET | `/admin/customers/{customer}` | admin |
| POST | `/admin/customers/{customer}/status` | admin |
| GET | `/admin/dashboard` | admin |
| GET | `/admin/enquiries` | admin |
| GET | `/admin/enquiries/{enquiry}` | admin |
| POST | `/admin/enquiries/{enquiry}/close` | admin |
| POST | `/admin/enquiries/{enquiry}/convert` | admin |
| POST | `/admin/enquiries/{enquiry}/notes` | admin |
| POST | `/admin/enquiries/{enquiry}/quote` | admin |
| GET | `/admin/payments` | admin |
| POST | `/admin/payments` | admin |
| POST | `/admin/payments/{payment}/void` | admin |
| GET | `/admin/pricing` | admin |
| POST | `/admin/pricing/{pricing}/review` | admin |
| GET | `/admin/reports/summary` | admin |
| GET | `/admin/settings` | admin |
| PUT | `/admin/settings` | admin |
| GET | `/admin/tankers` | admin |
| GET | `/admin/tankers/{tanker}` | admin |
| POST | `/admin/tankers/{tanker}/approve` | admin |
| POST | `/admin/tankers/{tanker}/review` | admin |
| GET | `/admin/tracking/active` | admin |
| GET | `/admin/vehicles` | admin |
| GET | `/admin/vehicles/{vehicle}` | admin |
| POST | `/admin/vehicles/{vehicle}/pricing` | admin |
| POST | `/admin/vehicles/{vehicle}/review` | admin |
| GET | `/admin/water-types` | admin |
| POST | `/admin/water-types` | admin |
| PUT | `/admin/water-types/{waterType}` | admin |
| POST | `/auth/change-password` | any logged-in user (ownership checked) |
| POST | `/auth/forgot-password` | public |
| POST | `/auth/login` | public |
| POST | `/auth/logout` | any logged-in user (ownership checked) |
| GET | `/auth/me` | any logged-in user (ownership checked) |
| POST | `/auth/register/customer` | public |
| POST | `/auth/register/tanker` | public |
| POST | `/auth/reset-password` | public |
| POST | `/bookings` | customer |
| GET | `/bookings/{booking}` | admin / owning customer |
| GET | `/bookings/{booking}/tracking` | admin / owning customer |
| GET | `/customer/addresses` | customer |
| POST | `/customer/addresses` | customer |
| PUT | `/customer/addresses/{address}` | customer |
| DELETE | `/customer/addresses/{address}` | customer |
| GET | `/customer/dashboard` | customer |
| GET | `/customer/profile` | customer |
| PUT | `/customer/profile` | customer |
| POST | `/enquiries` | customer |
| GET | `/files/assignments/{assignment}/proof` | any logged-in user (ownership checked) |
| GET | `/files/drivers/{driver}/licence` | any logged-in user (ownership checked) |
| GET | `/files/vehicles/{vehicle}/{type}` | any logged-in user (ownership checked) |
| GET | `/meta` | public |
| GET | `/my/bookings` | customer |
| POST | `/my/bookings/{booking}/accept-quote` | customer |
| POST | `/my/bookings/{booking}/cancel` | customer |
| GET | `/my/enquiries` | customer |
| GET | `/my/enquiries/{enquiry}` | customer |
| POST | `/my/enquiries/{enquiry}/accept-quote` | customer |
| POST | `/my/enquiries/{enquiry}/cancel` | customer |
| GET | `/notifications` | any logged-in user (ownership checked) |
| POST | `/notifications/read-all` | any logged-in user (ownership checked) |
| POST | `/notifications/{notification}/read` | any logged-in user (ownership checked) |
| GET | `/tanker/assignments` | tanker |
| GET | `/tanker/assignments/{assignment}` | tanker |
| POST | `/tanker/assignments/{assignment}/accept` | tanker |
| POST | `/tanker/assignments/{assignment}/arrive` | tanker |
| POST | `/tanker/assignments/{assignment}/delivered` | tanker |
| POST | `/tanker/assignments/{assignment}/reject` | tanker |
| POST | `/tanker/assignments/{assignment}/start` | tanker |
| GET | `/tanker/dashboard` | tanker |
| GET | `/tanker/drivers` | tanker |
| POST | `/tanker/drivers` | tanker |
| POST | `/tanker/drivers/{driver}` | tanker |
| GET | `/tanker/leads` | tanker |
| POST | `/tanker/locations` | tanker |
| GET | `/tanker/profile` | tanker |
| PUT | `/tanker/profile` | tanker |
| GET | `/tanker/vehicles` | tanker |
| POST | `/tanker/vehicles` | tanker |
| GET | `/tanker/vehicles/{vehicle}` | tanker |
| POST | `/tanker/vehicles/{vehicle}` | tanker |
| POST | `/tanker/vehicles/{vehicle}/availability` | tanker |
| GET | `/tanker/vehicles/{vehicle}/pricing` | tanker |
| POST | `/tanker/vehicles/{vehicle}/pricing` | tanker |
