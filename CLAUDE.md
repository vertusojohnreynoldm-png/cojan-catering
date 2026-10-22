# Cojan Catering Services

BSIT capstone: Laravel 13 / PHP 8.3 web ordering, delivery and QR-tracking system (Blade, MySQL, Pusher/Echo). Roles: customer, admin, delivery rider.

## Environment

- **Local:** Laragon on Windows at `C:\laragon\www\cojan_catering` (moving to `D:\www\cojan_catering`). Local queue = `database`. Laragon vhost has `SetEnv APP_ENV "local"`.
- **Live:** Railway (https://cojan-catering-production.up.railway.app), MySQL, Volume at `/app/storage/app/public`, GitHub auto-deploy from `main`. Railway `QUEUE_CONNECTION=sync`. Start command runs `config:cache`, `route:cache`, `migrate --force`, `storage:link --force`, then `artisan serve`.
- **NEVER run `php artisan config:cache` locally.**
- Migrations must be additive and safe for `migrate --force` on production MySQL. Tests run on SQLite, so avoid MySQL-only raw SQL (or guard it).
- **Email:** Brevo HTTPS API (`symfony/brevo-mailer`, `Mail::extend` in `AppServiceProvider`). Never put real keys in the repo.
- **Frontend:** Blade + `public/css/cojan.css` (served directly, no Vite build).
  - Tokens: `--fs-*`, `--space-*`, `--radius-sm/base/md`, `--status-*-bg/text`, `--required`, `--danger`, `--text-muted`.
  - `--green-dark` is really dark terracotta.
  - Palette: terracotta `#C1441E`, turmeric `#E8A93B`, cream `#FBF3E7`.
  - Inline styles beat stylesheet rules unless `!important`.

## Standing rules

- **Build every UI for both desktop and phone.** Everything must fit on a phone: no horizontal overflow, 44px tap targets, safe-area / `100dvh` aware.
- Verify with real Playwright screenshots at **1366px, 390px and 360px**. Use Playwright device presets and explicit load waits (`networkidle` hangs because of the Pusher websocket).
- Work in stages. After each stage report: what changed, files touched, migration needed (Y/N), `npm run build` needed (Y/N), test steps, and test results.
- **Do not commit; the user commits.**
- Ask before anything risky, ambiguous, or expensive to redo; show plans first for big features.
- Full test suite baseline: **87 pass, 9 pre-existing unrelated failures** (Breeze route names). Do not regress.

## What already exists (do not rebuild)

- **Brevo emails:** welcome, verification (MustVerifyEmail, signed URLs, trustProxies + forceScheme https), order-status. `App\Services\OrderNotifier`, `App\Mail\*`, `resources/views/emails/*`. `NotCommonEmailTypo` rule, `email:rfc,dns`.
- **Real-time:** `MessageSent`, `DeliveryLocationUpdated`, `AdminInboxUpdated`, `MessagesRead`, `ActivityLogged` (ShouldBroadcastNow), private channels in `routes/channels.php`, `ChatController`. Chat JS/CSS inline in `layouts/customer.blade.php` and `admin/dashboard.blade.php`.
- **Messenger-style admin chat inbox** (unread on top, highlighted, fades when read).
- **Admin activity log:** `activity_logs` table, `App\Services\ActivityLogger` (actor snapshot, no FK, JSON diffs, `withoutLogging()`, failed-login cap), observers in `app/Observers` via `#[ObservedBy]`, `Admin\ActivityLogController`, `admin/activity.blade.php`, dashboard live widget.
- **Delivery time frame at checkout:** `App\Services\DeliverySlots`.
- **Proof of delivery photo:** `ProofPhotoController`, camera capture + canvas compression, UUID filenames, served only through an authenticated route.
- **Validation:** `app/Rules/PhilippineMobileNumber.php`, `app/Rules/ValidPersonName.php`, prices `gt:0`, client-side tel hints.
- **Service areas:** `database/data/psgc.php` (Occidental + Oriental Mindoro PSGC tree), `database/data/excluded_barangays.php` (boat-only: Lubang and Looc whole municipalities; Ilin Island's 10 barangays and Ambulong in San Jose), `app/Services/ServiceArea.php`.
  - Menu orders = San Jose, Occidental Mindoro only.
  - Catering packages = all of Occidental + Oriental Mindoro minus exclusions.
  - Mixed cart uses the stricter San Jose rule.
  - Structured address columns (`province`, `municipality`, `barangay`, `street`, `landmark`) on `users` and `orders`; `delivery_address` / `address` still dual-written as a combined string.
- Mobile/design pass done on customer + admin nav; design tokens unified.

## Open work (do only when asked)

- Real-time stage 2b: admin new-order alerts, live customer status, rider assignment alerts, low-stock.
- Chat bubble covers checkout Place Order button: add bottom padding on checkout, package checkout, and Avail pages.
- Remaining mobile checks: order list / detail / tracking / profile / footer / rider screens.
- Live-site cleanup: replace seeded passwords (`admin123` / `customer123` / `delivery123`), rename odd demo utensil "John Reynold Vertuso".
- Optional: fix the 9 failing Breeze tests.
