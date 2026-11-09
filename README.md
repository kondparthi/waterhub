# Water Tanker Booking & Management System — v1.0

One React web app (PWA-ready) with three role-based experiences (**Customer**, **Tanker owner/driver**, **Admin**), one Laravel 12 REST API (Sanctum), and one MySQL database. Built from the *Full Project Requirements* document v1.0 (07 Nov 2026).

```
water-tanker-management/
├── backend/    Laravel 12 API  (/api/v1)
├── frontend/   React 18 + Vite + Bootstrap 5 + Leaflet
└── docs/       API reference
```

## Quick start (local)

**Requirements:** PHP 8.2+, Composer, MySQL 8 (or MariaDB 10.6+), Node 20+.

```bash
# API
cd backend
composer install
cp .env.example .env          # set DB_*, ADMIN_EMAIL, ADMIN_PASSWORD, CORS_ALLOWED_ORIGINS
php artisan key:generate
php artisan migrate --seed    # settings, water types, first admin
php artisan db:seed --class=DemoSeeder   # OPTIONAL demo data — never on production
php artisan serve             # http://localhost:8000

# Web app
cd ../frontend
cp .env.example .env          # VITE_API_URL=http://localhost:8000/api/v1
npm install
npm run dev                   # http://localhost:5173
```

**Demo logins** (after `DemoSeeder`):

| Role | Login | Password |
|---|---|---|
| Admin | `ADMIN_EMAIL` from `.env` | `ADMIN_PASSWORD` from `.env` |
| Customer | 9000000001 | Customer@123 |
| Tanker owner (2 vehicles) | 9000000002 | Tanker@123 |
| Tanker owner (1 vehicle) | 9000000003 | Tanker@123 |

Demo data also includes 5 sources (Borewell 1–3, Bavi 1–2) around Gachibowli/Kondapur.

## Tests

```bash
cd backend && php artisan test     # or vendor/bin/phpunit
```

21 tests / 174 assertions: both pricing examples from the spec (₹2,700 per-litre, ₹2,600 per-trip), min-quantity, tax/discount, trip override rules; the full booking lifecycle (request → review → source → quote → customer accept → assign → driver accept → start → GPS → arrive → deliver → complete → partial + full payment) with the exact status-history trail; reject + reassign keeps history; enquiry → booking conversion; cancellation fee; cross-customer and cross-driver access denial; role-route protection; pending vehicles not assignable; same-day double-booking blocked; illegal transitions blocked; upload type validation.

A Playwright run also walked the UI through every role end to end with no console errors.

## Deployment

**Recommended:** a VPS (Nginx + PHP-FPM 8.2+) with `app.yourdomain.com` (static React build) and `api.yourdomain.com` (Laravel `public/`). HTTPS is mandatory because browsers only allow GPS on secure origins.

1. `composer install --no-dev --optimize-autoloader`. In `.env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `CORS_ALLOWED_ORIGINS=https://app.yourdomain.com` and the DB credentials (use a restricted DB user).
2. Run `php artisan migrate --force` and `php artisan db:seed --force`. The seed runs **once** and creates the admin; change that password after first login.
3. Run `php artisan config:cache route:cache` and make `storage/` and `bootstrap/cache` writable.
4. Scheduler cron (prunes GPS history daily): `* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`
5. Frontend: set `VITE_API_URL=https://api.yourdomain.com/api/v1`, run `npm ci && npm run build`, then upload `dist/`. Configure the web server to serve `index.html` for unknown paths (SPA fallback).
6. Back up MySQL daily and test a restore.

**Hostinger shared hosting** also works. Point the API subdomain's document root at `backend/public`, run the cron from hPanel, and add an `.htaccess` SPA fallback for the frontend (`RewriteRule ^ index.html [L]` when the file is missing).

Uploaded RC, licence and proof photos are stored privately (`storage/app/private`). They are served only through authorised `/files/...` endpoints.

## How the spec was implemented

| Spec area | Implementation |
|---|---|
| Status model (§8.3) | `BookingService::TRANSITIONS` defines allowed moves per role. Every change goes through `transition()` and writes `booking_status_history`. The browser can't set an arbitrary status. |
| Pricing (§5.3, §8.4) | `PricingService`: per-litre (≥ minimum qty) or per-trip (`ceil(qty ÷ capacity)`, optional pro-rata partial load). Delivery charge applies once per booking. Tax % comes from settings. Overriding the trip count needs a reason. A rate snapshot is stored in `booking_items`. |
| Rates & approval (§5.3) | Owners submit rates, which stay *pending* until admin approval. Approving end-dates the previous rate, so history stays continuous. Only approved rates effective on the delivery date are used. |
| Source selection (§7) | Admin only. Sources must be active and support the water type. The daily capacity check applies **only** when "track capacity" is on (as the spec requires). |
| Nearest-tanker suggestions (§6.6) | `TankerAssignmentService::suggestions()` filters by approved vehicle/owner, availability, requested capacity, water type, service area/radius, same-day conflicts and an approved rate. It ranks by distance from latest GPS (if under 24 h old) or base location. Excluded vehicles are listed with reasons. |
| Concurrency (§8.5) | Assignment runs in a DB transaction with `lockForUpdate` on booking and vehicle, and eligibility is re-checked at assignment time. Old assignments become *superseded/rejected* and are kept. |
| Lead privacy (§8.5) | Before acceptance, drivers see area, quantity, date, amount and a masked phone. The full address, name and phone appear after acceptance. Drivers can't see other tankers' assignments. |
| GPS (§9) | Driver page posts every 12 s (configurable) while *in transit/arrived* only. The API rejects inactive assignments, invalid coordinates and impossible jumps (>120 km/h, logged to audit). The latest point is stored on the assignment/vehicle for fast polling. The customer/admin map polls every 8 s with a stale indicator. Points older than 90 days are pruned. |
| Notifications (§14.1) | In-app (MySQL) for every event in the spec's table. `NotificationService::sendSms()` is the single hook for adding an SMS/WhatsApp provider later. |
| Payments (§14.2) | Manual cash/UPI/bank/other, with UTR required for UPI/bank. Partial and multiple payments are supported, plus void with a reason. Payment status stays separate from booking status. |
| Reports (§14.3) | Overview, daily, status, source-wise, tanker-wise, customer-wise, cancellations, collected/outstanding — all exportable to CSV. |
| Security (§15) | Sanctum tokens; role middleware plus policies on every route; rate limits on login/OTP/GPS; hashed passwords; OTP reset that doesn't reveal whether a number is registered; audit log of admin changes (secrets redacted); file type/size validation; CORS restricted to configured origins. |

## Open business rules (§19) — defaults chosen, all configurable in Admin → Settings

| Question | Default in v1.0 |
|---|---|
| Who sets prices? | Owner proposes, admin approves. Admin can also set a rate directly or quote a manual rate. |
| Must customer accept the quote? | Yes (setting). Admin can confirm on the customer's behalf by ticking "accepted by phone/offline". |
| Delivery charge | Once per booking, defaulting from the vehicle's approved rate; admin can edit it when quoting. |
| Taxes | 0 % (setting `tax_percent`). |
| Partial loads (per trip) | Full trip charged by default; owner can choose pro-rata per rate. |
| Cancellation | Customer can cancel until *assigned*. A fee (setting, default ₹0) applies once confirmed and is shown before cancelling. Admin can override the fee. |
| Driver response timeout | 15 min. Overdue assignments are flagged on the dashboard; admin reassigns manually (no auto-dispatch in v1.0). |
| GPS retention | 90 days. |
| Source quantity tracking | Off per source unless enabled. |
| OTP | Used for password reset. SMS is logged to `storage/logs` until a provider is configured. |

## Maps

The app uses **Leaflet + OpenStreetMap**, which works without an API key. Address search uses OSM Nominatim, which is fine for low volume. To switch to Google or Mapbox tiles, change `VITE_MAP_TILE_URL`/`VITE_MAP_ATTRIBUTION`. Driver "Navigate" buttons open Google Maps directions.

**Known limitation (spec §9.3):** browser GPS pauses when the phone locks or the browser is backgrounded. The driver screen says so, and customers/admin see a "GPS not updating" indicator. Reliable background tracking needs the future native Android app.

## Not in v1.0 (per spec §2.2 / §20.2)

Online payment gateway, SMS/WhatsApp provider, auto-dispatch, route optimisation, invoice PDF/GST, ratings, recurring bookings, native apps.
