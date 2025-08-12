# Class-First Studio SaaS — Product Blueprint

## 1) Why (Problem + Vision)
- Studios struggle with dated, heavy platforms built for appointments, not classes.
- Pain points: clunky checkout, confusing passes/memberships, slow admin, hard embeddability, poor analytics, expensive add‑ons.
- Vision: A class‑first, modern, embeddable, AI‑assisted platform for Pilates, yoga, sound bath, and fitness classes — plus private sessions — that’s fast, delightful, and revenue‑smart.

## 2) Who (Target Customers)
- Boutique studios (1–5 locations) across Pilates, yoga, barre, sound bath, HIIT/fitness.
- Owners/operators, managers, instructors who need quick scheduling, easy client management, clean money flow, and actionable insights.
- Not a marketplace; a studio OS with optional integrations to lead-gen networks.

## 3) What (Differentiated Value)
- Class‑first UX: fast calendar, recurring rules that match reality, waitlist auto‑promotion, multi‑select booking, series/courses/workshops, resource constraints (rooms/mats), private sessions.
- Modern embeddability: drop‑in widgets/web components for any CMS + hosted client portal.
- AI assistants that matter:
  - Schedule Builder: suggests class times from demand and constraints.
  - Demand Forecasting: capacity and spot release guidance.
  - Pricing Guidance: intro offer/membership vs. class packs.
  - Revenue/Retention Nudges: churn risk, waitlist promotion, fill last‑minute spots.
- “Accounting that just works”: Stripe Checkout + Connect + Stripe Tax; QuickBooks Online sales receipts.
- Transparent pricing and sane add‑ons (e.g., SMS), no lock‑ins.

## 4) Market Landscape (Condensed)
- Legacy: Mindbody/Booker (feature‑rich, expensive, dated UX), WellnessLiving (all‑in‑one; complex), Vagaro (salon‑leaning; supports classes), Zen Planner (gyms/martial arts), ABC/GloFox/Mariana Tek/Walla (boutique fitness; gym‑centric), TeamUp/FitDegree/Punchpass/Pike13 (classes/passes; mid‑market), Arketa/Momence (creator/studio hybrid).
- Gap: class‑first product with modern checkout, embeddability, and AI scheduling/revenue tools focused on studio needs (classes + private sessions).

## 5) MVP Scope (Table‑Stakes)
- Classes & Schedules: recurring rules (daily/weekly/biweekly/monthly), capacity, resources, waitlist + auto‑promotion, multi‑location.
- Private Sessions: request → approve → schedule.
- Products: memberships (recurring), class packs/credits, promo codes (via Stripe), gift cards, intro offers.
- Checkout: Stripe Checkout; Stripe Tax; refunds; late cancel/no‑show fees.
- Client UX: fast calendar, mobile‑friendly; client portal (upcoming/past, credits, receipts, intake docs).
- Admin UX: roster/check‑in, manual booking, credits adjustments, instructor payouts report.
- Notifications: email + SMS confirmations, reminders, waitlist moves (A2P 10DLC compliance).
- Intake/Waivers: signature capture, profile questions; secure storage and export.
- Reporting: revenue, attendance, offer conversion basics.
- Integrations: QuickBooks Online (SalesReceipt), Zoom/Meet (hybrid), calendar feeds (iCal/Google), webhooks.

## 6) v1.5 Enhancements (High‑Impact)
- AI schedule/pricing assistant; cohort retention analytics; referral programs.
- Branded PWA; content/video library gating; migrations importer (Mindbody/WL).
- Deeper roles/permissions; staff payroll; labels/tags for clients.

## 7) Architecture (Initial)
- Multi‑tenant monolith (services extracted later):
  - Backend: TypeScript (NestJS) or Ruby on Rails or Laravel. Choose 1 for team velocity.
  - DB: PostgreSQL (row‑level tenant_key + indexes), Redis (queues/cache), S3‑compatible storage.
  - Frontend: Next.js (App Router), Tailwind; shared UI for embedded widgets + portal + admin.
  - Payments: Stripe Checkout + Connect + Stripe Tax.
  - SMS/Email: Twilio (A2P 10DLC), Postmark/SendGrid.
  - Jobs/Queues: BullMQ (NestJS) or Sidekiq (Rails) for reminders, waitlist, receipts.
- Multi‑tenant isolation:
  - tenancy via tenant_id on all rows; scoped queries; row‑level policies (optional Postgres RLS later).
  - separate Stripe Connect accounts per tenant; restrict webhooks by tenant secret.
- Observability: structured logs, health checks, metrics (Prometheus/OpenTelemetry later).

## 8) Suggested Tech Stack (Opinionated Option)
- Backend: TypeScript + NestJS + Prisma (Postgres) + Zod for input validation.
- Frontend: Next.js + React; UI library: Radix + Tailwind; componentized widgets.
- Infra (v0): Fly.io/Render/Heroku for speed; S3 (Backblaze/AWS); Cloudflare for DNS/edge cache.
- Auth: Clerk/Supabase Auth or self‑hosted (session/JWT); roles: owner/admin/instructor/staff/customer.
- IaC: Terraform later; start minimal.

## 9) Core Domain Model (Draft)
- Tenant (studio): id, name, plan, billing, locale.
- Location: tenant_id, name, timezone, address.
- Instructor: tenant_id, name, bio, email, availability, payout_percent, stripe_account_id.
- Resource: tenant_id, location_id, name, type, capacity.
- Class: tenant_id, name, description, duration_mins, default_location_id, price_cents, color.
- Schedule: tenant_id, class_id, instructor_id, resource_id, location_id, start_time_utc, end_time_utc, capacity, status (active/cancelled), recurrence_group_id.
- Booking: tenant_id, schedule_id, user_id, customer_email, status (pending/confirmed/canceled/refunded), credits_used, amount_cents, currency.
- Package (credits): tenant_id, user_id, name, credits_total, credits_remaining, price_cents, expires_at.
- Membership: tenant_id, user_id, plan_id, status, renew_at, cancel_at.
- Transaction: tenant_id, booking_id?, user_id?, amount_cents, currency, type (class_payment, package_purchase, refund), processor (stripe, qb), processor_id, status.
- Intake: tenant_id, user_id, data(json), version, signed_at, ip, ua.
- Note: tenant_id, user_id, note, visible_to_user, created_by, created_at.
- Label: tenant_id, name, color; UserLabel: (user_id, label_id).
- WebhookSecret: tenant_id, stripe, twilio, qb.

## 10) Key Flows
- Booking (client): browse → select → login/guest → pay (Stripe Checkout) → success → receipts (Stripe + QB) → reminders.
- Waitlist: join → auto‑promotion when seat frees → timed hold → payment.
- Credits & memberships: decrement per class; handle coverage/partial; expiration.
- Private session: request → approve (time/instructor) → pay → reminders.
- Refund/cancel/no‑show: policy engine applies fees/credits.

## 11) Security & Compliance
- Data isolation by tenant; PII encryption at rest for sensitive fields; secrets in KMS.
- Access control: roles/permissions; audit logs for admin actions.
- GDPR basics (export/delete), CCPA; secure file storage; signed URLs.
- Stripe PCI offload via Checkout; Stripe Tax for compliance; QB receipts for accounting.
- A2P 10DLC registration and opt‑in/out handling.

## 12) Pricing & Packaging (Draft)
- By location; usage add‑ons for SMS and email volume.
- Tiers:
  - Starter: classes, packs, basic reporting, email; 1 location.
  - Growth: memberships, automations (reminders/waitlist), SMS; multi‑location; QB.
  - Pro: AI assistants, advanced analytics, priority support.
- No long contracts; monthly/annual with discount.

## 13) Metrics & KPIs
- GMV, net revenue, churn, ARPA, LTV/CAC, payback.
- Studio: attendance, fill rate, conversion (lead→trial→member), membership churn, pack utilization, campaign ROI.

## 14) Competitive Proof Points
- Faster checkout and embeddability than legacy.
- AI schedule/pricing assistant not present in most incumbents.
- Cleaner accounting (Stripe+QB) than DIY setups.
- Class‑first features (series/workshops/resources/waitlist) deeper than appointment tools.

## 15) Migration & GTM
- Migration: CSV importers (customers, packs, memberships, classes), concierge onboarding, dual‑run guides.
- GTM: pilot 3–5 studios across niches; founder‑led sales; partnerships with instructor communities; content (playbooks, landing page templates), referral incentives.

## 16) Risks & Mitigations
- Switching cost: robust imports + white‑glove onboarding.
- SMS compliance/cost: register brands, monitor deliverability, clear opt‑in.
- Scope creep: lock MVP wedge; roadmaps with phases.
- Multi‑tenant security: rigorous tenancy tests; static analysis; secrets scanning.
- Support load: in‑app guides, templates, sensible defaults.

## 17) Roadmap (First 6 Months)
- Month 0–1: Design system, data model, auth/tenancy, classes/schedules, Stripe Checkout.
- Month 2: Packs/memberships, waitlist, notifications (email/SMS), client portal v1.
- Month 3: Admin roster/check‑in, credits adjustments, refunds/cancel policy engine, QB receipts.
- Month 4: Reporting v1, imports, Zoom integration, embeddable widgets polished.
- Month 5: AI schedule/pricing MVP, migration tooling, beta with pilots.
- Month 6: Harden, billing/subscriptions, observability, PWA, public launch.

## 18) Repo Structure (New SaaS)
- /apps
  - /web (Next.js client: portal + widgets)
  - /admin (Next.js admin or share routes via monorepo)
  - /api (NestJS/Rails/Laravel backend)
- /packages
  - /ui (shared components)
  - /types (zod/ts types or protobufs)
  - /utils (shared helpers)
- /infra (Terraform/IaC later; Docker, compose for dev)
- /docs (specs, ADRs, runbooks)

## 19) Coding Standards
- Type‑safe contracts; DTO validation; linters/formatters CI‑enforced.
- Tenancy guardrails in services; no raw SQL in controllers; repository pattern with tenant filters.
- Tests: unit for services; integration for core flows (booking, checkout, waitlist, refund).

## 20) Open Questions
- Membership complexity (freeze/hold/proration) in MVP?
- Courses vs. drop‑ins: prerequisites, attendance windows.
- Native apps vs. PWA timeline.
- Internal marketplace (client discovery) or partner with existing networks?

---

## Quick Start Plan
1) Bootstrap monorepo (Turbo or pnpm workspaces) with Next.js and NestJS.
2) Implement auth + multi‑tenant scaffolding (owner/admin/instructor/staff/customer).
3) Ship class/schedule models + booking → Stripe Checkout flow end‑to‑end.
4) Add packs/memberships + waitlist + notifications.
5) Polish embeddable calendar/booking widget and client portal.
6) Connect QuickBooks Online (SalesReceipt on payment). 
7) Onboard first pilots; iterate; then add AI assistants (phase 2).

---

This blueprint is the high‑level map. Use it to seed a new repo’s README, docs, tickets, and milestone planning.

