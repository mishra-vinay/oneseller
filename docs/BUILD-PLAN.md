# OneSeller — Build Plan (Web MVP)

Source: `OneSeller-MVP-Functional-Scope.docx` (Sep 20, 2026).
Scope: web MVP for Admin, Seller and Buyer. The iOS and Android apps come later, on the same backend.

---

## 1. Recommended stack

| Concern | Choice | Why |
|---|---|---|
| Language / framework | **PHP 8.3+ with Laravel** (current stable release) | Runs on shared hosting with no Node or long-running process. Auth, queues, mail, policies and scheduling are built in. Laravel Sanctum later serves the mobile API from the same codebase. |
| Database | **MySQL 8 / MariaDB 10.6+** | What shared hosting provides. Supports transactions and row locks (for stock) and FULLTEXT search. |
| UI | **Blade + Tailwind CSS**, plain HTML forms | Server-rendered pages are good for SEO on the storefront and work on any shared host. Every screen, including the inventory table and checkout steps, is a plain form, so no JavaScript framework is needed. Assets are built locally or in CI, so the server never needs Node. |
| Payments | **Razorpay** (Standard Checkout + webhooks) + COD | India and INR, works with GST-registered businesses. All money settles into one account, which matches the MVP. Razorpay Route can do split settlement in phase 2. |
| Email | **Brevo, Amazon SES or Postmark** over SMTP/API | The scope requires a dedicated delivery service. Set up SPF, DKIM and DMARC on talkforweb.in. |
| Background jobs | Laravel **database queue**, run by cron (`schedule:run` every minute) | Shared hosting has no supervisor. Emails and the low-stock digest run as queued or scheduled jobs. |
| Images | Local `storage/` + resize on upload (Intervention Image). Store paths through Laravel's filesystem layer | Moving to S3 or R2 later is a config change. |
| Search | MySQL FULLTEXT on product name + description | Enough for MVP. Laravel Scout + Meilisearch later without changing the pages. |
| CI / deploy | GitHub Actions: tests → build assets → deploy over SSH/rsync (or FTP if SSH isn't available) | Staging subdomain first, then production. |

**Mobile readiness.** Business rules live in service/action classes such as `PlaceOrder`, `AcceptSubOrder` and `ShipSubOrder`, not in controllers or Livewire components. The web UI calls them now. In the app phase, a `/api/v1` layer (Sanctum tokens) calls the same classes, so nothing is rebuilt.

---

## 2. Data model

Money is stored as **integer paise**. Every money and commission value on an order is a **snapshot** taken at order time.

```
users              id, role(admin|seller|buyer), name, email, phone, password,
                   email_verified_at, status(active|deactivated), timestamps
stores             id, user_id, name, slug (fixed), description, logo, phone,
                   address fields, city, gstin?, status(pending|approved|rejected|suspended),
                   rejection_reason?, commission_rate? (overrides default)
addresses          id, user_id, name, phone, line1, line2, city, state, pincode, is_default
categories         id, parent_id?, name, slug, image, sort_order, is_active   (2 levels)
products           id, store_id, category_id, name, slug (unique per store), sku,
                   price, compare_at_price?, stock, weight_grams,
                   short_description, description,
                   status(draft|pending|approved|rejected|inactive),
                   rejection_reason?, is_published, admin_disabled
product_images     id, product_id, path, sort_order            (max 5)
carts / cart_items cart: id, user_id? , guest_token?   item: cart_id, product_id, qty
orders             id, number (OS-2026-000123), buyer_id, shipping address snapshot,
                   items_total, shipping_total, grand_total,
                   payment_method(online|cod), payment_status(pending|paid|failed|refunded|partially_refunded),
                   status(pending_payment|placed|partially_fulfilled|completed|cancelled)
sub_orders         id, order_id, store_id, number (…-1, -2), status
                   (pending_payment|placed|accepted|shipped|delivered|cancelled),
                   items_subtotal, shipping, commission_rate, commission_amount, net_payout,
                   courier?, tracking_no?, cancel_reason?, cancelled_by?, timestamps per stage
order_items        id, sub_order_id, product_id, name/sku/price snapshot, qty, line_total
payments           id, order_id, gateway, gateway_order_id, gateway_payment_id,
                   amount, status, raw payload
ledger_entries     id, store_id, sub_order_id?, type(sale|commission|refund|payout|adjustment),
                   amount (+/-), balance_after, note, created_by
settings           key/value: site name, default commission, flat shipping,
                   free-shipping threshold, emails, cod_enabled,
                   auto_approve_sellers, auto_approve_products
activity_log       id, actor_id?, event, subject_type/id, meta, created_at
notifications      Laravel database notifications (in-panel bell for sellers/admins)
order_sequences    year, last_value   (gap-safe numbering under row lock)
```

---

## 3. Core rules to get right

**Seller isolation.** Every seller-side lookup goes through the signed-in seller's store (`currentStore()->products()->findOrFail($id)`, `currentStore()->subOrders()…`), so an ID from another store returns 404. Automated tests try cross-store access on every seller route and must get 404. Sellers' customer views never select buyer emails.
> Note: MySQL on shared hosting has no row-level security. The scope's "enforced at the database layer" is met in the data-access layer, which every query must pass through. True DB-level row security (PostgreSQL RLS) would need a VPS or managed database. See open questions.

**Product approval.** Saving as draft or submitting sets status to `pending`, unless auto-approve is on. Editing name, description, price or images on an approved product sends it back to `pending`. Editing stock or publish state does not. Rejection stores a reason, and the seller is emailed.

**Storefront visibility.** A product shows only when `approved` AND published AND not admin-disabled AND its store is `approved` AND the seller's user is active AND its category is active. This is one reusable `visible()` query scope, so a seller suspension hides their products instantly.

**Checkout and placing an order** (one DB transaction):
1. Re-read cart items, lock product rows (`SELECT … FOR UPDATE`), and check stock.
2. Create the order with a number from `order_sequences`, then one sub-order per store with a commission-rate snapshot. Shipping per store group = flat rate unless the group subtotal ≥ free-shipping threshold.
3. Decrement stock (it is reserved now).
4. Online: create the Razorpay order → buyer pays → **webhook + signature check** marks it paid (idempotent). Unpaid orders past N minutes are cancelled by a scheduled job and stock is returned. COD: the order is placed immediately.
5. On paid/placed: send emails (buyer gets everything, each seller gets only their sub-order, admin gets an alert) and log the activity.

**Sub-order lifecycle.** `placed → accepted → shipped (courier + tracking required) → delivered`. A cancel with a reason is allowed before shipped. Cancelling returns stock, reverses that seller's ledger credit if one was posted, and notifies the buyer, seller and admin. The parent order is `completed` when every non-cancelled sub-order is delivered.

**Ledger.** Entries are append-only, and `balance_after` is kept per store. Each sub-order posts one `sale` (+net items + shipping) and one `commission` (−). Refunds and admin-recorded payouts post their own entries. Admins never edit amounts, they add an adjustment.

**Guest cart.** A cookie `guest_token` holds the cart. On login it merges into the user's cart, capped at stock.

**Email verification** is required before the first checkout, not for browsing. Changing an email requires re-verification.

**Deactivation.** A middleware checks the user's status on every request and logs out deactivated users on their next click.

---

## 4. Build order (milestones)

Each milestone ends deployable to staging, with tests.

**M0 — Foundation**
- Laravel project, Tailwind/Livewire, coding standards, GitHub Actions CI, staging deploy to a talkforweb.in subdomain.
- `users.role`, three login areas (`/login`, `/seller/login`, `/admin/login`), role middleware, shared forgot/reset password, email verification, deactivation middleware.
- Settings table + admin settings page. Admin user seeder/command.
- Email provider wired up, plus the cron and queue setup on hosting.

**M1 — Sellers & catalogue**
- Seller registration (account + store in one form), holding page, admin approve/reject/suspend/reactivate, per-seller commission override.
- Categories (2 levels, reorder, active toggle, reassign-before-delete).
- Seller products CRUD, 5 images, draft/submit, approval rules, rejection reasons. Admin product queue with bulk approve and force-deactivate.
- Seller isolation scope + cross-store access tests.

**M2 — Storefront**
- Home (categories, new arrivals, featured sellers), category pages (24/page, URL-based filters & sort), search, product detail (gallery, breadcrumb, seller card, qty capped at stock), store page.
- SEO basics: slugs, meta tags, sitemap.xml, canonical URLs.

**M3 — Cart, checkout, payment**
- Cart grouped by seller with per-group shipping, guest cart + merge on login.
- Buyer register/profile/address book.
- 3-step checkout, order + sub-order creation with stock locking, order numbering.
- Razorpay integration (test mode → live), webhook, payment-timeout job, COD toggle.
- Order confirmation page.

**M4 — Fulfilment & money**
- Seller orders: accept / cancel with reason / ship (courier + tracking) / deliver. Buyer address/phone shown, email hidden.
- Buyer order history with per-seller status and tracking.
- Admin orders view (order → sub-orders), cancel a portion, mark refunded.
- Ledger: auto entries, seller earnings statement, admin ledger with manual payout recording.

**M5 — Dashboards, notifications, polish**
- Seller dashboard, inventory inline-edit table, customers page, store profile.
- Admin dashboard with 7-day chart, buyers page, activity feed.
- All emails from the notification matrix + in-panel notifications; daily low-stock digest.

**M6 — Hardening & launch**
- Test suite focused on isolation, money maths, stock races, and the order state machine.
- Security pass (CSRF, rate-limited login/reset, upload validation, webhook signatures), backups, error monitoring (Sentry or similar), performance check on shared hosting.
- Content: terms, privacy and refund policies (needed for Razorpay activation). Go-live on the final subdomain.

**Phase 2 — Mobile & beyond**
- `/api/v1` (Sanctum) over the same services, plus push notifications.
- Buyer app, and optionally a seller app, in Flutter or React Native (one codebase for iOS and Android).
- Backlog from "Not in MVP": variants and per-seller shipping first, then Razorpay Route split settlement, reviews, coupons, wishlist, returns.

---

## 5. Decisions (confirmed 2026-10-29: "go with your recommendations")

| # | Question | Decision |
|---|---|---|
| 1 | Payment gateway | **Razorpay** (Standard Checkout + webhooks) plus COD. Start merchant KYC early. |
| 2 | Hosting | Shared hosting per `docs/DEPLOYMENT.md`. Provider details still to be shared before the first deploy. |
| 3 | Seller isolation | **Data-access layer** enforcement (scoped queries + scoped route binding + policies), proven by automated cross-store tests. No PostgreSQL RLS. |
| 4 | When sellers are credited | **On delivery** of their sub-order. For **COD**, the seller's courier collects the cash, so the ledger posts the sale and commission plus a matching "COD collected by seller" debit. The balance then shows the commission the seller owes the platform. |
| 5 | Commission base | **Item subtotal only.** The per-seller shipping charge goes to the seller, who ships. |
| 6 | GST | Prices are entered **GST-inclusive**. GSTIN is stored per store. Tax invoices and e-commerce TCS reporting are deferred past MVP; confirm with an accountant before launch. |
| 7 | Variants | **Variant-ready schema:** price, stock and SKU live on a `product_variants` table, and every product gets one default variant. The MVP UI shows a single variant, so size/colour later needs no data migration. |
| 8 | Subdomain and branding | Still pending. Examples use `shop.talkforweb.in`. |

## 6. Progress

- [x] **M0 — Foundation**: Laravel 13 app, three login areas with role middleware, deactivation logout, shared password reset, email verification, buyer registration and profile, settings page, `oneseller:create-admin`, CI (SQLite + MySQL), deployment guide.
- [x] **M1 — Sellers & catalogue**: seller registration (account + store) with holding page; admin approve/reject/suspend/reactivate and per-seller commission; two-level categories with images, ordering, active toggle and reassign-before-delete; seller products with up to 5 resized images, draft/submit, re-review on live edits, rejection reasons; admin approval queue with bulk approve and force-deactivate; cross-store isolation tests.
- [x] **M2 — Storefront**: home (categories, new arrivals, featured sellers), category pages with sub-categories, price/seller/in-stock filters and sorting in the URL, search (MySQL FULLTEXT, LIKE on SQLite), product page, store page, sitemap.xml, meta/canonical tags.
- [x] **M3 — Cart, checkout, payment**: cart grouped by seller with per-seller shipping and free-shipping threshold; guest cart merged on login; address book; 3-step checkout; order + sub-orders in one locked transaction with stock reservation and commission snapshot; OS-YYYY-NNNNNN numbering; Razorpay (signed callback + webhook, idempotent) and COD; unpaid orders expire after 30 minutes and return stock.
- [x] **M4 — Fulfilment & money**: seller accept / ship (courier + tracking) / deliver / cancel; buyer order history with per-seller status and tracking; admin order view, cancel a portion, record refunds; append-only ledger credited on delivery (with COD handling), seller earnings statement, admin payouts and adjustments.
- [x] **M5 — Dashboards, notifications, polish**: seller and admin dashboards (7-day chart), inventory table, customers page (no emails), admin buyers page with deactivate, activity feed, every email in the notification matrix, in-panel notifications, daily low-stock digest.
- [x] **M6 — Hardening & launch prep**: security headers, rate-limited login/reset, upload validation, webhook signatures, terms/privacy/refund page templates, demo seeder, browser run-through of the full buyer → seller → admin flow. **Remaining before go-live (needs you):** hosting details, Razorpay live keys, email provider, final subdomain/branding, legal review of the policy pages, GST/TCS check.

## 7. Original open questions

1. **Payment gateway:** Is Razorpay OK, or do you prefer Cashfree, PayU or another? Who owns the merchant account (KYC takes time, so start early)?
2. **Hosting details:** Which provider? Is SSH available, which PHP version, can it run cron every minute, and is MySQL 8 or MariaDB available? This decides the deploy method.
3. **"Database-layer" isolation:** Is application-layer enforcement plus tests acceptable on shared hosting, or is true DB-level row security required (needs PostgreSQL on a VPS/managed DB)?
4. **When the seller is credited:** At payment, or at delivery (recommended: at delivery, so the balance payable only includes delivered orders)? For COD, does the seller or the platform collect the cash?
5. **Commission base:** Charged on item subtotal only, or also on shipping? Does shipping income go to the seller or the platform?
6. **GST / tax:** Are prices tax-inclusive? Do you need GST invoices per sub-order, and TCS handling as an e-commerce operator? (Not covered in the scope doc, but it matters for an Indian marketplace.)
7. **Variants and per-seller shipping:** The scope says to decide early. Should the product table be built variant-ready now (small extra cost, avoids a migration later)?
8. **Final subdomain**, plus branding (logo, colours) and the email provider account.
