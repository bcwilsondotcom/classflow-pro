ClassFlow Pro — Pilates Studio Manager
=====================================

Overview
--------
ClassFlow Pro is a WordPress plugin for end‑to‑end Pilates studio management: classes, instructors, schedules, bookings, packages/credits, Stripe payments (with Stripe Tax/Connect), and QuickBooks Online accounting integration. Frontend booking works with or without Elementor.

Key Features
------------
- Classes and scheduling
  - First‑class entities stored in dedicated DB tables (no CPTs)
  - Fixed and private sessions; capacity, price, currency per schedule
  - Conflict checks for instructors/resources; availability + blackout rules
- Online booking and payments
  - Stripe integration, Stripe Tax (optional), Stripe Connect revenue split
  - Packages/credits with purchase and balance tracking
- Accounting
  - QuickBooks Online OAuth2; SalesReceipts on successful payment
  - Optional items per class; receipt PDF retrieval
- Admin experience
  - Custom admin pages for Classes, Instructors, Locations, Resources
  - Schedules dashboard with add/list views
  - Reports, Payouts, Logs, QuickBooks tools, Settings
- Frontend
  - Calendar and step‑booking widgets (Elementor optional)
  - WordPress REST API under `/wp-json/classflow/v1/*`

Requirements
------------
- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ (or MariaDB equivalent)

Quick Start (Local Dev)
-----------------------
1) Start the local stack
   - `docker compose up -d`
   - WordPress: http://localhost:8080
2) Activate the plugin
   - `docker compose run --rm --profile tools wpcli plugin activate classflow-pro`
3) Configure settings
   - In WP Admin, go to ClassFlow Pro > Settings:
     - Stripe: publishable key, secret key, webhook secret (see Webhook URL below)
     - QuickBooks: client ID/secret, realm ID, redirect URI
     - General: currency, timezone, country
4) Test booking end‑to‑end (use Stripe test keys)

Build / Packaging
-----------------
- Create distributable ZIP: `bash build.sh`
- Output: `build/classflow-pro.zip`

Data Model (First‑Class Entities)
---------------------------------
Stored in dedicated tables (no CPTs):
- `wp_cfp_classes`: name, description, duration_mins, capacity, price_cents, currency, status (active/draft/inactive), scheduling_type, featured_image_id, default_location_id
- `wp_cfp_instructors`: name, email, bio, payout_percent, stripe_account_id, availability_weekly, blackout_dates, featured_image_id
- `wp_cfp_locations`: name, address fields, timezone
- `wp_cfp_resources`: name, type, capacity, location_id
- `wp_cfp_schedules`: class_id, instructor_id, resource_id, location_id, start_time, end_time, capacity, price_cents, currency, is_private
- `wp_cfp_bookings`: schedule_id, user_id/email, status, amount_cents, currency, credits_used, payment fields
- `wp_cfp_packages`, `wp_cfp_transactions`, `wp_cfp_customers`, `wp_cfp_waitlist`, `wp_cfp_private_requests`, `wp_cfp_intake_forms`, `wp_cfp_logs`

Admin Pages
-----------
- ClassFlow Pro (main) Dashboard
- Classes: list/add/edit/delete
- Instructors: list/add/edit/delete
- Locations: list/add/edit/delete
- Resources: list/add/edit/delete
- Schedules: add/list, conflict checks, default location auto‑fill
- Bookings, Reports, Payouts, QuickBooks Tools, Settings, Logs

Frontend Widgets / Flows
------------------------
- Calendar booking widget (`assets/js/calendar.js`)
- Step booking widget (`assets/js/step-booking.js`)
- Elementor widgets are registered when Elementor is present

REST API (Selected)
-------------------
Base: `/wp-json/classflow/v1/`
- Entities (for UI filters):
  - `GET /entities/classes` (query: `s`, `per_page`, `page`)
  - `GET /entities/instructors`
  - `GET /entities/locations`
  - `GET /entities/resources`
- Schedules and booking:
  - `GET /schedules` (query: `class_id`, `location_id`, `instructor_id`, `date_from`, `date_to`)
  - `GET /schedules/available` (query: `class_id`, optional `date_from`, `date_to`)
  - `POST /book` (nonce) → creates pending/confirmed booking
  - `POST /payment_intent` (nonce) → returns Stripe PaymentIntent info
- Packages and client portal:
  - `POST /packages/purchase` (nonce)
  - `GET /me/overview` (auth + nonce)
  - `GET/POST /me/intake` (auth + nonce)
- Integrations:
  - Stripe webhook: `POST /stripe/webhook` (no auth), set endpoint to `/wp-json/classflow/v1/stripe/webhook`
  - QuickBooks OAuth: `GET /quickbooks/connect`, `GET /quickbooks/callback`

Configuration & Secrets
-----------------------
Set under ClassFlow Pro > Settings:
- General: currency, timezone, country, email toggles, intake required
- Stripe: publishable/secret keys, webhook secret, Stripe Tax enable, Connect enable, platform fee percent
- QuickBooks: env, client ID/secret, realm ID, redirect URI
- QuickBooks item mapping: item per class, default item name, income account ref, tax code ref

Security
--------
- PHP 8+, nonce‑protected REST endpoints, capability checks for admin actions
- Inputs sanitized, outputs escaped
- Do not commit live keys or secrets to the repo

Development Notes
-----------------
- First‑class DB entities are used throughout (no CPTs)
- Custom admin pages live in `includes/Admin/` and repositories in `includes/DB/Repositories/`
- REST routes live in `includes/REST/Routes.php`
- To modify schema, update `includes/Activator.php` and bump `CFP_DB_VERSION` in `classflow-pro.php`

Manual Test Checklist
---------------------
1) Create classes, instructors, locations, resources in admin
2) Add schedules (verify conflict checks and availability)
3) Book via calendar/step widget; pay with Stripe test card
4) Confirm booking status; verify emails and Stripe receipts
5) If QuickBooks is connected, verify SalesReceipt creation
6) Buy package and verify credits are applied on booking

Build & Release
---------------
- `bash build.sh` → `build/classflow-pro.zip`
- Upload/install ZIP in WordPress > Plugins > Add New

Support & Issues
----------------
- Please include WP version, PHP version, steps to reproduce, and any relevant logs when reporting issues.

