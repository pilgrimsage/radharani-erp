# Radharani Jewellery ERP — Developer Guide

> **Updated 8 October 2026.** The accounting ledger, Loyalty, GST and invoice numbering, vendors and finished-goods purchases described in places below were removed. See `SCHEMA_REFERENCE.md` ("Update, 8 October 2026") and `CLAUDE.md` for what replaced them. Sections that describe removed features are marked *(removed)*.

System design, schema, and reasoning for every structural decision. Read this before touching migrations or models.

---

## 1. What This System Is

An internal stock-audit ERP for a jewellery shop, with a read-only ecommerce catalog attached. **Not primarily an ecommerce app** — the core problem being solved is tamper-evident tracking of physical stock as it moves between vault, counter, karigars, hallmarking centers, and customers.

Everything in the schema serves one of these goals:
1. Know where every item physically is, right now, and prove it later.
2. Reprice the entire catalog instantly when gold/silver rates change.
3. Never let a staff member quietly edit history.
4. Run on shared hosting with SSH + cron only — no VPS, no Redis, no S3.

---

## 2. Stack & Why

| Choice | Why |
|---|---|
| Laravel 13 (PHP 8.4) | Long support window, everything else here builds on it |
| Blade + Livewire (not Vue/React SPA) | No separate API layer, no frontend build pipeline to break on shared hosting, matches small-team size |
| MySQL 8+ | JSON columns for flexible fields, what Hostinger provides |
| Database queue driver | No Redis available — `jobs` table needs zero extra infrastructure |
| Local disk + WebP | No S3 on this hosting plan — WebP compression is what keeps 20GB from filling up |
| `spatie/laravel-permission` | Real roles/permissions instead of a flat `role` enum — don't hand-roll this |
| `spatie/laravel-activitylog` | Full audit trail on every write — proven package, not custom code |

**If hosting ever upgrades:** storage swaps from `local` to `s3` in `filesystems.php`, queue swaps from `database` to `redis` in `.env`. No schema or business-logic change either way — this is intentional.

---

## 3. Schema — Full Reference

The complete, current schema (all 53 migrations, every column, every relationship) lives in **`SCHEMA_REFERENCE.md`**, including two ER diagrams — read that alongside this section. What follows here is the *reasoning* behind the non-obvious decisions; the reference doc is the source of truth for exact columns.

### Stock Hierarchy — `boxes`, `packets`, `items`

Three-level containment: Box → Packet → Item. All nullable at the parent level, because an item can exist before it's assigned a packet (matches the "fill up later" workflow).

**Why `pair_group_id` on items, not a separate pairs table:** earrings/bangles are two physical pieces with potentially different weights, but sold and displayed as one product. One row per physical piece, linked by a shared group ID, keeps weight-based pricing accurate per piece while still letting the UI show them as a pair.

**Why `huid_code` and `internal_code` are separate columns:** an item has exactly one active ID type — government HUID (mandatory over 2g) or an auto-generated 5-character fallback (`Item::generateInternalCode()`, from a charset that excludes visually ambiguous characters like 0/O and 1/I, per the client's confirmed code-format requirement) — never both. Reporting needs to know which kind of ID it's looking at.

**Why `items.metal` is a separate column from `purity`:** `purity` is a free-text field ("22K", "92.5") whose *format* depends on the metal, so it can't double as a filterable metal field. Stock filtering has to support metal type at minimum (confirmed requirement), hence the split.

**Why `items.status` has `pending_review` and `reserved`, not just `in_stock`/`dispatched`/`sold`:** both are confirmed client rules, not internal conveniences. `pending_review` is where a karigar/hallmark return lands — admin is *exclusively* responsible for confirming it into real stock, so the item must be visibly distinct from both "still out" (`dispatched`) and "available" (`in_stock`) in the meantime. `reserved` is the same pattern applied to sales: a sale is only final once an admin verifies it, so the item can't show as `sold` (final) or `in_stock` (still sellable) between entry and verification.

**Why `items.source_karigar_batch_id` / `source_purchase_item_id` exist:** raw material (from a karigar dispatch or a raw-material purchase) doesn't become a real, taggable item until someone processes the return/purchase line — these columns are the paper trail back to whichever raw source produced a given tagged item, without forcing every item to go through the same creation path.

### Movements — `movements`

**The core table.** One polymorphic table (`trackable_type` + `trackable_id`) instead of seven separate tables for vault, karigar, hallmarking, photography, custom-purpose, order, and melt movements — because every one of those workflows is structurally identical: something leaves, gets photographed, comes back, gets photographed, expires after 90 days.

**Why this matters practically:**
- "Where is item X right now" is one query (`ORDER BY created_at DESC LIMIT 1`), not seven joins
- One cleanup job handles all photo expiry, not seven
- A new movement type is a new enum value, not a new table

**Why rows are never updated or deleted:** every dispatch and return is its own row. A correction is a new row (`movement_type = 'correction'`, `reverses_movement_id` pointing at the mistake, `approved_by` set to the owner's user ID) — the original is never touched. This is the actual mechanism behind "no tampering," not just a label on the table. The one exception is the Pending Review confirm action, which sets `approved_by` on the return movement itself (not a correction) — that's an admin recording their review, the same field doing the job it was designed for, not a backdoor edit.

**Photo expiry doesn't touch the row either:** `app/Console/Commands/CleanupOldPhotos.php` deletes movement photo files older than 90 days off disk, but deliberately never writes to `movements.photo_path` — an earlier version nulled it, which was a real rule-1 violation (an unattended cron job editing history with no admin review, unlike the two sanctioned exceptions above). The column is left pointing at a now-missing file; the UI (`movement-log.blade.php`, `custom-purpose-move.blade.php`) checks `Storage::disk('public')->exists(...)` before showing the photo link, so a stale path shows an "expired" indicator instead of a dead link, without ever mutating the row.

**Why karigar raw-material and customer-material flows aren't just more `movements` rows:** the polymorphic `movements` table assumes the thing that goes out is the same thing that comes back (an item, packet, or box with a stable ID). Two of the three confirmed karigar-dispatch sub-flows break that assumption — a customer's own untagged material has no item ID at all, and raw metal issued to a karigar can come back as several separate finished pieces from one dispatch. Forcing either into `movements` would mean inventing a fake item row before one physically exists. `customer_material_jobs` and `karigar_raw_batches` exist instead, each shaped for what it actually tracks.

**Why weight loss is a manual field, never computed:** `movements.weight_loss` (and `weight_at_return`) are entered by staff at return time. The client was explicit that wastage on karigar/hallmark returns is expected and normal, but the shop's own measurement is what's authoritative — not `weight_at_dispatch - weight_at_return` computed after the fact, which could disagree with what was actually weighed.

### Pricing — `rate_logs`

Price is **never stored on an item.** It's calculated live in `PricingService` from the latest `rate_logs` row at read time:

```
price = (net weight × rate) + making_charge + stone_value + huid_charge − automatic discount
```

`net weight` is `items.net_weight` when it's been entered (gross minus stones), otherwise `items.weight`. `stone_value` is its own line (0 for plain metal). GST is added on top at the category's `gst_rates` rate, both at billing and in the public website's displayed price.

This is why a single rate update reprices the entire catalog instantly — there's nothing to update, because nothing was stored. `source` (`manual`/`api`) tracks whether the rate came from a live market API or a manual override; manual always wins if entered, since the shop must never depend on a third-party API for something this operationally critical.

### Sales — `sales`, `sale_items`, `sale_payments` (GST and invoice numbering *(removed)*: the admin enters the Tally bill number at verification; a bill is paid in parts and the balance is worked out)

**Why `sale_items.price_at_sale` freezes the price:** live pricing is correct *before* a sale, but once sold, the price must never move again even if the gold rate changes the next day. This is the one deliberate exception to "never store a price."

GST fields (`cgst`, `sgst`, `igst`, `invoice_number`) are split, not a flat total, because GST law requires the split shown on the invoice and requires sequential, gap-free invoice numbering per financial year.

**Why a sale isn't final at entry:** the client confirmed a sale only becomes final once an admin verifies it — the item shouldn't disappear from live availability the moment staff enters the sale, only once admin confirms. Rather than a separate locking table, this reuses the same `items.status` mechanism already doing this job elsewhere (`pending_review`): items go to `reserved` on entry, and the Sale Verification Queue is the only thing that flips them to `sold` and sets `sales.confirmed_by_accountant`.

**Why `invoice_number` is also allowed to change once, at verification:** `invoice_number` is `required`+`unique` at row creation, but GST law requires sequential, gap-free numbering — which can only be known once a sale is actually final, not at entry (an abandoned/never-verified reservation must not burn a real number). `NewSale` writes a `RESV-...` placeholder at entry; `SaleVerificationQueue::verify()` replaces it with the real number from `InvoiceCounter` (one row per financial year, incremented under a row lock) in the same update that sets `confirmed_by_accountant`. This widens the schema's one documented insert-only exception to two fields, both set once, by an admin, at that same verification moment — not a new editing pathway.

### Custom Orders — `orders`

A distinct lifecycle from Sales, not a sale sub-type — an order is a pre-commitment (placed → confirmed → ready → delivered → cancelled) that later *converts into* a sale (`converted_sale_id`), rather than being one. Rate-locking lives here, not on `sales`: if the customer paid in full at order time, `locked_rate`/`locked_at` freeze what the price will be; otherwise nothing is frozen and the rate at delivery applies. `out_of_stock` + nullable `in_stock_item_id` let an order exist against a product that doesn't physically exist yet.

### Purchases — `purchases`, `purchase_items` (vendors and finished-goods purchases *(removed)*: raw material only, bill reference and notes, adds to `raw_metal_entries`)

Separate from `movements` on purpose. **Movements answer "where is it physically"; purchases answer "what do we owe for it."** A karigar delivering finished goods is both a `karigar_in` movement and a `purchase` — conflating the two would make vendor payables untrackable independent of stock location.

**Why `purchase_items` needed restructuring:** the original composite `(purchase_id, item_id)` primary key assumed every purchase line has a real item at the moment of purchase. Raw-material purchases don't — the vendor delivers untagged metal, described but not yet a taggable `Item`. `purchase_items` now has its own `id`, a nullable `item_id`, and description/category/metal/purity/`tag_pending` fields so a raw-material line can exist before tagging, then get its `item_id` filled in once staff tag it in Stock — updating a `purchase_items` row this way is fine; the parent `purchases` row itself is still never touched after insert.

### Accounting Ledger — `accounts`, `transactions` *(removed)*

**Not a Tally replacement.** A thin double-entry-style ledger that every `sale` and `purchase` writes to automatically (debit/credit rows), so a clean Tally-compatible export is always possible without manual reconciliation. Trial balance, P&L, balance sheet — deliberately out of scope, Tally already does that well.

**Why auto-posting isn't wired yet, beyond just being unconfirmed:** it's not just that the client hasn't confirmed the account mapping — the schema as it stands can't post a fully correct entry even with a mapping. `purchases.payment_status` is a tri-state flag (paid/partial/pending) with no amount-paid field, so a "partial" purchase can't be split into a correct paid-portion/owed-portion double entry. `sales.payment_modes` records cash/bank/upi/card, but the chart of accounts has no per-mode account, and there's no GST-payable account for the GST collected on a sale to post to. Solving this needs the client's real chart of accounts and a payment-amount field on purchases, not a guess.

**Manual journal entries, in the meantime:** since nothing auto-posts, Ledger View has a narrow manual-entry action (`reference_type = 'manual'`) — pick an account, debit or credit, an optional `note`. Gated on `ledger.manage`, seeded to `owner` only (not `manager`/`accountant`, who only get `ledger.view`) — this is a separate, narrower capability from auto-posting and doesn't touch how Sales/Purchases work.

### Users, Roles, Employees — `users`, `roles`, `permissions`, `employees`

**Why `users` and `employees` are separate tables:** not every employee needs system login (a helper or cleaner, for instance), and HR data (salary, designation) shouldn't live next to authentication credentials. `users.employee_id` links the two only where a login is actually needed.

**Why roles/permissions instead of a flat enum:** a flat `role` column can't express "this accountant can approve sales but not edit rates." Spatie's package gives configurable per-role permissions without a schema change every time a new role is needed.

### Customer features — `customers`, `installment_schemes`, `installment_payments` (loyalty tables *(removed)*, see Referral below)

Loyalty is a ledger (`loyalty_transactions`), not a running counter, for the same audit reason as movements — any dispute is answerable from history, not just a trusted total. Installments split scheme (the plan) from payments (each actual payment) so partial/late payments are traceable and a 12th-month bonus can be triggered from real payment history.

**Why `loyalty_settings` is a table, not config values in code:** points-per-rupee and the referral bonus are business decisions the owner needs to tune without a developer redeploying anything. It's a deliberately single-row table (`LoyaltySetting::current()` is the only access pattern) — one place to change the rate, immediately live everywhere `LoyaltyService` is called.

**Referral bonus timing:** paid to the referrer only on the referred customer's *first confirmed sale*, not at signup — otherwise the bonus is gameable by creating empty accounts. The owner-facing `ReferralOverview` screen shows exactly which referrals are still "pending first purchase" vs. "bonus earned," so this isn't a black box.

**Customer portal auth is fully separate** from staff auth — see Section 3 of `SCHEMA_REFERENCE.md`. A customer logs in with phone+password on the `customer` guard and can never reach staff-only routes; the two tables (`users` vs `customers`) are never merged.

### Old Gold/Silver Exchange & Refinery — `exchange_transactions`, `refinery_batches`

The exchange flow is a client-mandated 4-step guided process (gross weight → net weight after melt → two independent purity readings, auto-averaged → preset deduction) that the client was explicit must never be abstracted or combined — so `exchange_transactions` has a column for every one of those steps individually rather than a generic "readings" JSON blob, and `stage` tracks which step a given transaction has reached. Refinery is a simpler out/in round-trip (scrap sent, refined weight+purity comes back) and got its own table since its fields genuinely differ from the exchange flow, even though both are conceptually "batch out, batch in."

### Notifications — `pending_notifications`

WhatsApp auto-send isn't in scope this phase, so every customer-facing message (sale confirmed, order ready, loyalty awarded, installment due, exchange valuation ready) follows one shared pattern instead of a bespoke integration per type: the triggering action generates a row with a ready-to-copy `message`, staff copies it into WhatsApp/SMS themselves, then marks it `sent`. One generic table with a `type` enum, rather than a table per message type, because the underlying action (generate → copy → send → mark sent) is identical regardless of what triggered it.

### Public website — `storefront_categories`, `storefront_collections`, `item_images`, `storefront_settings`, website columns on `items`

**Why listings live on `items`, not a separate products table:** the client's decision is one listing per physical, tagged piece (not a grouped "design" with a size picker). That keeps the website honest about stock without any syncing: the listing *is* the piece, so the moment a sale reserves it, a karigar dispatch takes it out, or verification marks it sold, it's off the site. A size shown on the website is the piece's own size.

**Why website categories map stock categories instead of replacing them:** `items.category` is free text typed at the counter ("Chudi", "Bangle", "Kada"). A website category lists which of those strings roll up into it (`stock_categories`, plus its own name), so publishing never means re-categorising stock, and a stock category nobody has mapped yet simply can't appear (Website > Categories lists those).

**Why `slug`/`listed_at` are write-once:** a shared WhatsApp link to `/shop/{slug}` must keep working after the piece is renamed, and "New" badges/newest-first sorting shouldn't reset every time someone edits the piece.

**Why it isn't Livewire or Tailwind:** the website design (from the `rr-web-ui` repo) is GSAP-driven (ScrollSmoother, pinned scroll sections, SplitText) and renders from one data object. Porting it into Livewire/Tailwind would have meant rewriting every animation; instead it runs as-is from `public/storefront/` and the ERP only supplies `window.RJ_DATA` (`StorefrontCatalog`).

### Activity Log — via `spatie/laravel-activitylog`

Every write to `movements`, `sales`, `purchases` is attributed to an authenticated `user_id` and logged with IP and timestamp. This — combined with never allowing hard edits — is what actually delivers "no tampering," not the movements table alone.

---

## 4. The Scaffold — What's Actually Built vs. What's Next

Every module listed in `CLAUDE.md`'s Build status table is live, wired to a real table, and UI-consistent (see `docs/DESIGN_SYSTEM.md`). What's still genuinely open is a short, specific list — not "which modules exist" but a few unresolved business questions and one piece of automation:

- **Vendor payable auto-trigger** — does a karigar raw-material dispatch automatically create an amount owed to that karigar, or is that always a separate manual purchase entry? Not automated either way yet; `karigar_raw_batches` doesn't currently write anything to `purchases`/`transactions`.
- **Pricing calculation mechanics** — `PricingService` and the making-charge config are provisional pending the client's own Excel sheet showing their actual calculation logic.
- **Custom-order expiry** — the ~2.5-month uncollected-order-releases-to-stock rule has no confirmed timing or customer-warning process, so it isn't implemented.
- **Accounting ledger auto-posting** — `transactions` rows aren't yet auto-written from Sales/Purchases; the ledger view itself is live but reads whatever's manually entered.
- **Karigar raw-material multi-piece splits** — one dispatch returning as several finished pieces is only partially built (one new item per return works; a repeatable multi-line split doesn't yet), pending client confirmation of exactly how that should work and who assigns tags.

Full detail and context for each: `docs/REQUIREMENTS.md`'s "Still open" section.

**Four setup steps that live outside generated files** — required once on any fresh clone, see `README.md`: Spatie middleware aliases in `bootstrap/app.php`, the `customer` guard in `config/auth.php`, running `breeze:install` (not just requiring the package), and re-applying `LoginRequest.php` after any Breeze reinstall.

---

## 5. Non-Negotiable Rules When Extending This

- **Never `UPDATE` or `DELETE` a `movements`, `sales`, or `purchases` row**, except the narrow, intentional cases the schema was built around: `sales.confirmed_by_accountant` and `sales.invoice_number` (both set once, by an admin, together, on verification) and a movement's `approved_by` (set once, by an admin, on Pending Review confirm). Corrections are otherwise always new rows referencing the original.
- **Never store a calculated price** except in `sale_items.price_at_sale`, which is a deliberate snapshot.
- **Every write needs a `user_id`.** No anonymous or shared-login actions anywhere.
- **Photos go through `PhotoCompressionService`.** Never save an uploaded file directly — WebP + resize is what keeps disk usage bounded.
- **New async work goes through the queue (`database` driver), not the request cycle.** Anything slow (compression, WhatsApp, exports) blocks a staff member on a phone otherwise.
- **Never self-wrap a full-page Livewire component's Blade view in `<x-layouts.app>`.** Set the layout from PHP (`->layout('components.layouts.app', [...])`) instead — see `CLAUDE.md`'s "Livewire double-layout trap." Getting this wrong breaks every interactive action on the page, not just the initial load.
- **Internal item codes always come from `Item::generateInternalCode()`.** Never hand-roll a second `Str::random(...)`-based generator — the client's non-ambiguous-character requirement lives in exactly one place.


---

## 6. Added with the 8 October 2026 change list

- **Karigar and Hallmarking are batches.** One issue to a karigar or one dispatch to a centre is a batch identified by its date and time. Pieces come back in as many part receipts as it takes; the pending balance is always shown; the owner or manager closes the rest with a note. Weight loss is typed in, never worked out. Returned pieces still wait in Pending Review.
- **Raw-metal balance** (`raw_metal_entries`): raw-material purchases add; a karigar's metal advance and metal payments deduct. Both are blocked if the balance is short.
- **Ledgers** (`LedgerService`, `HallmarkLedger`): per karigar and per hallmarking centre, weights and counts only, plus a customer ledger on the customer page. PDF (dompdf) and Excel (openspout).
- **Pricing**: rates per metal and carat; `PricingRule` for making charge, additional charge, discount and hallmarking charge, most specific rule wins, price range measured on metal value only. Provisional until the client's Excel sheet is reviewed.
- **Locations, categories, batches, soft deletes, audits**: see `CLAUDE.md` "Conventions added with the 8 October change list".
- **Referral** replaced Loyalty: opt-in codes, points by the owner's rules, awarded from verified sales by staff (not automatic: the mechanism is still to be designed).
- **Exchange** is resumable and edit-until-settled with every edit logged; deduction presets live per metal and carat on the daily rates page.
