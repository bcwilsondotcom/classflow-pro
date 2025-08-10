# Repository Guidelines

## Project Structure & Module Organization
- Root plugin entry: `classflow-pro.php` (hooks, autoloader, constants).
- PHP source: `includes/` organized by domain: `Admin/`, `PostTypes/`, `REST/`, `Booking/`, `Packages/`, `Payments/`, `Accounting/`, `Elementor/`.
- Frontend assets: `assets/js/booking.js`, `assets/css/frontend.css`.
- Local stack: `docker-compose.yml`, MailHog mu-plugin in `docker/mu-plugins/`.
- Packaging: `build.sh` creates `build/classflow-pro.zip`.

## Build, Test, and Development Commands
- Build zip: `bash build.sh` → outputs `build/classflow-pro.zip`.
- Start local WordPress: `docker compose up -d` (WP at http://localhost:8080).
- Activate plugin: `docker compose run --rm --profile tools wpcli plugin activate classflow-pro`.
- Stop stack: `docker compose down -v`.

## Coding Style & Naming Conventions
- PHP: Target PHP 8+, follow WordPress Coding Standards conventions (spacing, escaping, i18n) and PSR-4 namespace `ClassFlowPro\` mapped to `includes/`.
- Files/Folders: StudlyCaps for namespaces, one class per file; kebab-case for assets (e.g., `frontend.css`).
- Security: Always escape output (`esc_html`, `esc_attr`), sanitize inputs, verify REST nonces, and check capabilities.

## Testing Guidelines
- Automated tests are not included. Validate flows manually:
  - Booking: create Class, Instructor, Schedule → book via Elementor widget → pay with Stripe test keys → verify webhook updates.
  - Packages: buy package → confirm credits granted.
  - QuickBooks: connect OAuth → verify SalesReceipt after payment.
- Use MailHog at http://localhost:8025 to inspect emails.

## Commit & Pull Request Guidelines
- Commits: concise, imperative subject (≤72 chars), include scope when useful (e.g., `payments: verify Stripe webhook`).
- PRs: include description, screenshots for UI, reproduction steps, and risk notes. Link related issues. Describe rollout/rollback steps.

## Security & Configuration Tips
- Configure Stripe keys, webhook secret, and QuickBooks OAuth in `ClassFlow Pro > Settings`; never commit secrets.
- Stripe webhook URL: `/wp-json/classflow/v1/stripe/webhook`.
- Prefer server-side APIs via provided gateways (`Payments/StripeGateway.php`, `Accounting/QuickBooks.php`).
