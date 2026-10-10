# Radharani Jewellery ERP

Internal stock-audit ERP + read-only ecommerce catalog for Radharani Jewellery Works. The core problem is tamper-evident tracking of physical stock (vault, counter, karigars, hallmarking, customers) — this is not primarily an online store.

Full reasoning behind every schema decision: @docs/DEVELOPER_GUIDE.md
Exact columns/relationships for all tables + ER diagrams: @docs/SCHEMA_REFERENCE.md
UI design tokens, layout shell, shared components: @docs/DESIGN_SYSTEM.md
Confirmed client requirements this build is based on: @docs/REQUIREMENTS.md

## Non-negotiable rules

1. Never `UPDATE` or `DELETE` a `movements`, `sales`, or `purchases` row — corrections are new rows referencing the original, approved by an owner. The narrow exceptions, matching the schema's own design: `sales.confirmed_by_accountant`, `sales.invoice_number` (the placeholder `RESV-...` number is replaced with the original Tally bill number an admin types in, both set together at the same verification moment), and a movement's `approved_by` column may be set once, by an admin, as part of the verification/review flows described below — nothing else on those rows ever changes.
2. Never store a calculated price, except `sale_items.price_at_sale` (a deliberate snapshot at time of sale).
3. Every write needs a real `user_id` — no anonymous or shared-login actions.
4. Photos go through `PhotoCompressionService` — never save an upload directly.
5. New slow/async work goes through the queue (`database` driver) — never the request cycle.
6. New modules convert their matching wireframe in `resources/views/wireframes/` where one exists — several newer modules (Exchange, Refinery, Orders, Notifications, Referral, Installments) have no matching wireframe (they emerged after the original 13 were approved) and instead follow `docs/DESIGN_SYSTEM.md`.
7. Staff (`users`) and customers (`customers`) are two entirely separate auth systems (different guards) — never merge them. Staff log in with email **or phone** (`users.phone`); customers log in with phone only.
8. A full-page Livewire component's Blade view must **never** wrap itself in `<x-layouts.app>`/`<x-layouts.guest>`. Set the layout from PHP instead: `return view('livewire.x.y', [...])->layout('components.layouts.app', ['title' => '...']);`. See "The Livewire double-layout trap" below — getting this wrong silently breaks every button/form on the page.
9. Items returning from karigar or hallmarking sit in `items.status = 'pending_review'` until an admin confirms them via the Pending Review queue (`movement.approve` permission) — never write them straight back to `in_stock`.
10. A sale is not final until an admin verifies it. On entry, sold items get `items.status = 'reserved'`, not `'sold'`; the Sale Verification Queue is what flips them to `'sold'`, sets `sales.confirmed_by_accountant`, and records the Tally bill number (see rule 1). A sale with a balance can still be verified.

## Stack

- Laravel 13 (PHP 8.4), Blade + Livewire — no separate frontend framework, no API layer
- Tailwind CSS (the "Aurum" design system, see `docs/DESIGN_SYSTEM.md`) — Alpine.js is provided by Livewire itself (`@livewireStyles`/`@livewireScripts` in the shared layouts); `resources/js/app.js` must **not** import/start its own copy of Alpine — see the trap below.
- MySQL 8+, database-driven queue (no Redis), local disk storage with WebP-compressed photos (no S3)
- Hosting: Hostinger shared plan — SSH + cron + phpMyAdmin only, no VPS, 20GB storage
- Auth: Laravel Breeze (blade stack); `spatie/laravel-permission`, `spatie/laravel-activitylog`, `intervention/image`
- Designed to swap infra later via config only (`local`→`s3`, `database`→`redis` queue) — never a rebuild

## Build status

| Module | Status |
|---|---|
| Stock (Box/Packet/Item + detail/QR/bulk-import/configurator) | ✅ Live. Also: Boxes & Packets in one list with soft delete of empty ones, product soft delete, unassigned items by entry batch, Product View by metal, Stock Audit per box, HUID Export / Update (Excel round trip), Change Item location with multi-scan, Metal > Subcategory category tree |
| Locations (owner-managed: Vault, counters, displays; place changes; per-location report pages) | ✅ Live |
| Movements: Vault ↔ Counter (one screen, multi-scan, one-click return, place change), Karigar (one screen: issue with advance, part receipts, payments, repairs/customer metal), Hallmarking (one screen: counted + tagged pieces, part returns), Photo/Custom (piece, packet or box), Pending Review, Movement Log | ✅ Live |
| Raw-metal balance (by metal and carat; purchases add, karigar advances and metal payments deduct) | ✅ Live |
| Ledgers: Karigar, Hallmarker (weights only), Customer (on the customer page), PDF and Excel | ✅ Live |
| Old Gold/Silver Exchange (resumable step form, edits until settled, deduction presets by metal and carat, Exchanges list, status tracker, final valuation) + Refinery (auto-calculated return) | ✅ Live |
| Custom Orders (3-step entry with reference images, sourcing paths to karigar/hallmark/sales, in-stock hold with sale warning and admin override) | ✅ Live |
| Pricing: per-carat daily rates, unified pricing rules (making, additional, discount, hallmark by product/category/price range/metal), price simulator, making charges by category | ✅ Live (calculation provisional, see below) |
| Sales (5-step form, vault check, part payments with calculated balance, adjustment, referral code, verification with Tally bill number, printable bill) | ✅ Live |
| Purchases (raw material only: bill reference, notes, adds to the raw-metal balance) | ✅ Live |
| Referral (opt-in codes, metal bought per code, owner-set points rules, awarding from verified sales) | ✅ Live. Replaces Loyalty |
| Monthly scheme (length, existing members, months pending, completion date, maturity outcomes: order, sale or reserve) | ✅ Live |
| Messages (one shared copy-and-send queue, grouped Sales / Installment / Order) | ✅ Live |
| Customer portal (login, purchases with balance, installments, referrals, change password) | ✅ Live: in the public website's design and header, phone-first |
| Public website / storefront at `/` + Website admin | ✅ Live. Menu built from the category tree; no GST on prices |
| Admin (Employees/Users/Roles/Locations/Karigars & Centres/Customers/Audit log/Bulk import) | ✅ Live. The audit log shows what changed with before and after |
| Owner dashboard (no money figures), Daily logbook, Staff activity, Location report | ✅ Live |
| Wireframes (all 13 original) | ✅ Static reference views, routed at `/wireframes` |
| Queue infrastructure (`jobs` table) | ✅ Fixed |
| Removed (8 Oct change list, section 18) | Accounting, Tally export, Loyalty, vendors/suppliers, finished-goods purchases, invoices and invoice numbering, GST, per-piece QR reprint, separate karigar/hallmark in/out screens |
| Pricing calculation mechanics vs. the client's Excel sheet | ⬜ Provisional: `PricingService` and the rules to be reviewed once the client sends it |
| Review link in the sale confirmation message | ⬜ Waiting for the URL (`SHOP_REVIEW_LINK`) |
| Custom-order uncollected-order-expiry timing | ⬜ Still open |
| Referral points award mechanism | ⬜ Rules are set by the owner, the automatic mechanism is still to be designed |

See `docs/REQUIREMENTS.md` for the full confirmed-requirements document this build was implemented against, including everything listed above as "still open."

## Stock module conventions

- **Regrouping is history.** `Item`, `Packet` and `Box` log changes via `spatie/laravel-activitylog` (log name `stock`); `StockHistoryService` merges those with `movements`, sales and QR scans into the Item/Packet/Box Detail timelines. So always move things with a per-model `->update(['packet_id' => ...])` / `->update(['box_id' => ...])`. A mass `Item::whereIn(...)->update(...)` bypasses model events and silently drops the move from history.
- **QR stickers** (`QrCode`) encode `route('stock.qr.resolve', $code)`; resolving logs a `scanned` activity and redirects to the detail page. `QrCode::forTarget()` reuses an existing code rather than minting a second one. SVGs are rendered by `chillerlan/php-qrcode` (no GD needed); print sheets are at `stock.qr.print?ids=...`.
- **Scanner input** goes through `App\Support\StockLookup`, which accepts HUIDs, internal codes, packet/box codes, sticker codes and full scan URLs.
- **The shop has no handheld scanners; phones scan with the camera.** Every field that takes a code gets a camera button: `<x-ui.scan-button target="#id" …>` or `<x-ui.search-input scan>`. Both open the one shared scanner in `components/layouts/partials/scanner.blade.php` (`html5-qrcode`, lazy-loaded via `window.rjLoadScanner` in `resources/js/app.js`), which fills the field as if it were typed. The top-bar Scan button opens any scanned code via `stock.scan`. The camera only works over **HTTPS** (or localhost). Spreadsheets (CSV/XLSX) go through `App\Support\SpreadsheetReader` (`openspout/openspout`).
- **The Add/Edit Item form** is its own component (`Stock\ItemForm`), opened with `Livewire.dispatch('open-item-form', { id })` / `{ purchaseItemId }` and emitting `item-saved`. Don't duplicate it into other pages; embed `<livewire:stock.item-form />`.
- **List pages** use `App\Livewire\Concerns\WithDataTable` with `<x-ui.datatable>` (see `docs/DESIGN_SYSTEM.md`).

## Conventions added with the 8 October change list

- **Batches are identified by date and time.** Karigar issues (`karigar_raw_batches`), hallmark dispatches (`hallmark_batches`), imports (`entry_batches`) and raw-material purchases show `label` (`j M Y, g:i a`). Part receipts (`karigar_receipts`, `hallmark_receipts`) are insert-only; the owner or manager closes what has not come back, with a note.
- **Categories are a tree**: `item_categories` (metal, then subcategory); `items.category` is kept as the subcategory's name and `items.category_id` links it. `Item::saving` links name-only code paths to the tree.
- **Locations** are owner-managed (`locations`); `movements.location_id` records where a `vault_out` went and a second `vault_out` to another place is a place change. `Location::isInVault()` is the vault check Sales uses. Old `vault_out` rows with no location read as the first counter (movements are never edited).
- **Pricing**: `RateLog::latestFor($metal, $carat)` is the rate for a carat; `PricingRule` rows (making, additional, discount, hallmark) are matched most-specific-first (product, category, price range, metal, all); the price range is measured on the metal value only. A making value set on the piece itself is its own product-level setting.
- **Money is only in Sales and the price simulator.** Karigar and hallmarker ledgers are weights and counts only; the dashboard shows no money.
- **Soft deletes** on boxes, packets and items. Deleting is blocked for a sold or out-of-store piece and for a non-empty container.
- **Front-end build**: `public/build` is tracked. Run `npm run build` after adding Blade views or Tailwind classes or the new classes will not exist in the browser.
- **PDF downloads** use `dompdf/dompdf`; Excel downloads use `openspout`.
- Livewire reads a dot in a `wire:model` key as a path, so inputs keyed by a carat such as `99.9` use `DailyRateEntry::key()`.

## Public website (storefront) conventions

- **`/` is the public website**, not a login chooser. The staff/customer chooser lives at `/sign-in` (linked from the site footer); `/login` and `/portal/login` are unchanged.
- **It is deliberately outside the ERP's front-end stack.** `resources/views/storefront/*` use their own layout with the site's own CSS/JS in `public/storefront/` (plain files, cache-busted by `App\Support\StorefrontAsset`, not Vite) and a self-hosted GSAP 3.15 bundle (core + ScrollTrigger, ScrollSmoother, SplitText, ScrollToPlugin). No Tailwind, Livewire or Alpine on those pages, and no Aurum tokens: the storefront keeps the `rr-web-ui` design pixel-for-pixel. Plain controllers (`StorefrontController`), not Livewire.
- **Data flows one way:** `App\Services\StorefrontCatalog` builds `window.RJ_DATA` (pieces, categories, collections, today's rates, shop details, URLs) and the scripts render from it. Prices arrive already computed (`PricingService`, no GST); the browser never calculates a price.
- **One listing = one physical piece.** A piece shows only when `show_on_website` is ticked, it has a `web_name`, its subcategory in the category tree is switched on, and `status = 'in_stock'`, so reserved/dispatched/sold pieces drop off by themselves. `slug` and `listed_at` are set once on first publish (`Item::booted()`), never regenerated.
- **Staff manage it** under Website in the sidebar (`website.manage`, owner + manager). Website fields on the Add/Edit Item form are only shown to, and saved for, that permission. Catalogue photos are `item_images` rows via `PhotoCompressionService` (not `movements.photo_path`).
- `php artisan db:seed --class=StorefrontDemoSeeder` (local/testing only; also run by `DemoDataSeeder`) loads the design's sample catalogue from `database/seeders/data/storefront-demo.json`, using Unsplash URLs for images.

## The Livewire double-layout trap (read before touching any full-page component)

Every full-page Livewire component (anything bound directly to a route, e.g. `Route::get('/x', SomeComponent::class)`) gets auto-wrapped by Livewire in a layout on **every** request if it doesn't call `->layout()` itself — including AJAX responses for `wire:click`/`wire:submit`. If the component's own Blade view *also* wraps its content in `<x-layouts.app>` (a full `<html>`/`<head>`/`<body>` document), every interactive action returns an entire second HTML document as the morph payload, which breaks the page (it goes blank) after literally any button click. This exact bug shipped for a while — every full-page component was self-wrapping and had zero working interactions beyond the initial page load.

**The fix, and the only correct pattern going forward:**
- Blade view: no `<x-layouts.app>`/`<x-layouts.guest>` wrapper — just the inner `<div>...</div>`.
- PHP class: `render()` ends with `->layout('components.layouts.app', ['title' => '...'])` (or `components.layouts.guest` for guest pages; the Customer Portal components use `components.layouts.portal`).

Plain **non-Livewire** pages (a regular Controller returning `view(...)`, e.g. `auth/login.blade.php`, `welcome.blade.php`) are the one place `<x-layouts.app>`/`<x-layouts.guest>` self-wrapping is still correct — there's no Livewire auto-layout involved for those.

## Alpine.js trap

Livewire v3 bundles its own copy of Alpine and unconditionally sets `window.Alpine` when its script runs. If `resources/js/app.js` *also* `import`s and starts a separate `alpinejs` package copy, you get two competing Alpine instances ("Detected multiple instances of Alpine running" in the console) and `x-data` elements stop reacting to `wire:model` correctly. `resources/js/app.js` must stay Alpine-free. Every page still gets Alpine via `@livewireStyles`/`@livewireScripts`, which are included directly in `components/layouts/app.blade.php` and `components/layouts/guest.blade.php` (safe to include even on pages with no actual Livewire component — Livewire won't double-inject if a real component is also present).

## Environment setup traps (easy to reintroduce on a fresh clone)

1. `bootstrap/app.php` needs Spatie's middleware aliases manually added (Laravel 11+ removed `Kernel.php`) — already applied there; reapply if it goes missing.
2. `config/auth.php` needs the `customer` guard/provider/passwords block — see `config/auth-additions.md`.
3. `composer require laravel/breeze` alone does nothing — must also run `php artisan breeze:install blade`.
4. Portal Livewire components must use `->layout('components.layouts.portal')` (the public website's shell, fed `RJ_DATA` by a view composer in `AppServiceProvider`), never Breeze's default — that default assumes a staff login and crashes on any guest/customer page. Redirect between portal pages with a full page load (`$this->redirect(...)`, not `navigate: true`) so the site's header and scripts boot fresh. `bootstrap/app.php` sends signed-out `portal/*` requests to `portal.login` (and signed-in customers to `portal.dashboard`), everything else to the staff login.
5. The `jobs`/`job_batches`/`failed_jobs` tables aren't part of any business migration — easy to forget, breaks the queue silently until first dispatch.
6. `public/storage` must be a symlink to `storage/app/public` on **this machine** (`php artisan storage:link`) — it silently breaks (points at a stale path) if the project directory is ever moved or cloned somewhere new.
7. Time is **IST everywhere**: `config/app.php` timezone defaults to `Asia/Kolkata` (`APP_TIMEZONE`) and the MySQL session is set to `+05:30` (`DB_TIMEZONE`) so `CURRENT_TIMESTAMP` defaults agree. Don't reintroduce `'UTC'`. Rows written before 25 Sep 2026 were stored as UTC and display 5h30m early; they were deliberately not rewritten because `movements`/`sales`/`purchases` are insert-only.
8. After a fresh clone/migrate, run `php artisan db:seed --class=DemoDataSeeder` (local/testing environments only — it's gated out of anything else) to get realistic test data across every module instead of empty screens.
