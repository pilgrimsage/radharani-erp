# Radharani Jewellery ERP — Finalized Schema Reference

Single source of truth for every table as it currently stands (53 migrations). This supersedes the table-by-table sections scattered across earlier design docs — if anything conflicts, this file wins. See `docs/REQUIREMENTS.md` for the confirmed business rules each new table/column exists to satisfy.

## Update, 8 October 2026 (read this first)

The tables below are the baseline; the 8 October change list changed them as follows. Where the two disagree, this section wins.

**Removed:** `accounts`, `transactions`, `loyalty_transactions`, `loyalty_settings`, `invoice_counters`, `gst_rates`, `discount_rules`, `making_charge_presets`, `additional_charge_presets`. Columns removed: `customers.loyalty_points`; `sales.cgst/sgst/igst/payment_modes`; `purchases.vendor_id/total_amount/gst/payment_status`; `purchase_items.rate`; `vendors.balance`. `vendors.type` is now only `karigar` or `hallmark_center`. Permissions `ledger.view`, `ledger.manage` and `discount.manage` are gone.

**New tables:**
- `locations` (name, type vault/counter/display/other, sort_order, is_active); `movements.location_id` (where a `vault_out` went), `movements.done_by_employee_id` (who physically did it; `user_id` stays the logged-in actor).
- `item_categories` (metal, name, sort_order, is_active; unique per metal+name); `items.category_id`, `items.entry_batch_id`, `items.source_hallmark_batch_id`; soft deletes (`deleted_at`) on `boxes`, `packets`, `items`.
- `entry_batches` (kind, note, user_id): one per import, identified by its time.
- `stock_audits` + `stock_audit_lines` (present/missing/extra per scanned piece).
- `karigar_raw_batches` is now the karigar **batch** table: `description`, `categories` (json), `pieces_expected`, `advance_cash`, `advance_metal_weight/purity`, `order_id`, `photo_path`, `closed_by/closed_at/close_note`, status adds `closed`. `karigar_receipts` (part returns: pieces, weight_received, weight_loss, disposition stock/hallmark, `hallmark_batch_id`), `karigar_payments` (cash or metal), `raw_metal_entries` (the raw-metal balance by metal and carat; + in, - out).
- `hallmark_batches`, `hallmark_batch_items` (tagged pieces in a batch), `hallmark_receipts` (part returns with `tagged_by`).
- `rate_logs.purity` (a rate per metal and carat); `pricing_rules` (kind making/additional/discount/hallmark; scope product/category/price_range/metal/all; carat; metal-value band; calc percentage/per_gram/per_piece; validity).
- `sale_payments` (insert-only parts of a bill; the balance is worked out); `sales.adjustment_type/adjustment_value`, `sales.referral_customer_id`, `sales.order_override_note`; `sales.invoice_number` holds the Tally bill number once verified.
- `orders.sourcing` (stock, karigar, bought_finished, bought_unhallmarked, bought_unfinished), `order_images`; `purchases.notes`, `purchases.order_id` (raw material only).
- `exchange_deduction_presets` (metal, carat, percent); `exchange_transactions.metal` (edits logged, locked once settled); `refinery_batches.metal/deduction_percent/result_weight`.
- `referral_settings` (points per gram, first-sale bonus), `referral_points` (ledger); `customers.referral_opted_at` (codes are opt-in).
- `installment_schemes.total_months/opening_pending_amount/maturity_outcome/outcome_ref/outcome_at`.
- `pending_notifications.type` gains scheme_welcome, scheme_default, scheme_completed, order_accepted, order_from_karigar, order_to_hallmarking.
- Permissions added: `location.manage`, `category.manage`, `stock.audit`; `loyalty.manage` is now `referral.manage`.

---

## ER Diagrams

**Diagram 1 — Operational core:** Stock hierarchy (Boxes → Packets → Items), Movements, Sales, Purchases, Users/Employees
![ERD 1 — Stock, Movements, Sales, Purchases](./ERD-1-stock-movements-sales-purchases.png)

**Diagram 2 — Customer side:** Customers, Loyalty & Referral, Installments, Accounting Ledger
![ERD 2 — Customer, Loyalty, Accounting](./ERD-2-customer-loyalty-accounting.png)

*Not shown in either diagram (added after the diagrams were drawn, or standalone/reference tables with no hard FK relationships): `rate_logs`, `gst_rates`, `qr_codes`, `page_terms`, `karigar_raw_batches`, `customer_material_jobs`, `exchange_transactions`, `refinery_batches`, `orders`, `pending_notifications`. Full columns for these are below.*

*Keep both PNGs in the same folder as this file — the links above are relative.*

---

## Stock Hierarchy

**`jobs`, `job_batches`, `failed_jobs`** — standard Laravel queue tables, required because `QUEUE_CONNECTION=database`. Easy to forget since they're not part of any business-domain migration — omitting them breaks `queue:work` with "table jobs doesn't exist."

**`employees`** — id, name, phone, address, designation, salary, joining_date, status(active/inactive)

**`users`** — id, name, email, **phone(null,unique)**, password, employee_id→employees(null), is_active, remember_token
*(+ password_reset_tokens, sessions — Laravel defaults, kept)*
*`phone` added so staff can log in with phone or email (Requirement #19) — customer-portal login stays phone-only on the separate `customers` table.*

**`boxes`** — id, code(unique), label

**`packets`** — id, box_id→boxes(null), code(unique), label

**`items`** — id, packet_id→packets(null), **metal(gold/silver/titanium/platinum, null)**, huid_code(null,idx), internal_code(null,idx), category, purity, weight, description, hsn_code(null), making_type(**percentage/flat_per_piece/flat_per_gram**), making_value, pair_group_id(null), **source_karigar_batch_id→karigar_raw_batches(null)**, **source_purchase_item_id→purchase_items(null)**, status(in_stock/dispatched/sold/**pending_review**/**reserved**, idx), **net_weight(null)**, **stones(null)**, **stone_value(default 0)**, *website:* **show_on_website(default false, idx)**, **web_name(null)**, **slug(unique, null)**, **web_description(null)**, **storefront_collection_id→storefront_collections(null)**, **audiences(json, null)**, **occasions(json, null)**, **dimensions(null)**, **size_type(null)**, **size_label(null)**, **is_bestseller(default false)**, **listed_at(null)**
*`net_weight`/`stone_value` feed `PricingService` (metal charged on net weight when known; stones as their own line). The website columns make the piece itself the public listing (one listing = one physical piece); `slug` and `listed_at` are set once, the first time it's published.*
*`metal` split out from the free-text `purity` string so stock can be filtered by metal type (Requirement #17). `making_type` widened from 2 to 3 values (Requirement #10). `status` gained `pending_review` (items returned from karigar/hallmark awaiting admin confirmation, Requirement #9) and `reserved` (items in an unverified sale, Requirement #12). `source_karigar_batch_id`/`source_purchase_item_id` link a newly-tagged item back to whichever raw source produced it — both null for normally-bought/made items.*
*`internal_code` is always generated via `Item::generateInternalCode()` — 5 characters, from a curated charset that excludes visually ambiguous characters (0/O, 1/I, and lookalikes). Never hand-roll a second generator.*

---

## Movement Tracking

**`movements`** — id, trackable_type(item/packet/box), trackable_id, movement_type(vault_out/in, karigar_out/in, hallmark_out/in, photo_out/in, custom_out/in, melt_out/in, correction), purpose_label(null), user_id→users, counterparty(null), expected_return(null), actual_return(null), weight_at_dispatch(null), **weight_at_return(null)**, **weight_loss(null)**, **tagged_by(null)**, photo_path(null), bill_path(null), note(null), reverses_movement_id→movements(null), approved_by→users(null)
*idx: (trackable_type, trackable_id, created_at), (movement_type, created_at)*
*`weight_at_return`/`weight_loss` capture the manually-entered weight at return and the loss figure (Requirement #6 — never auto-calculated). `tagged_by` is free text recording who performed hallmark tagging on return (Requirement #8) — may be an external hallmarking-centre person, so it's not a `users` FK.*

**`karigar_raw_batches`** — id, vendor_id→vendors, weight_out, metal(gold/silver/titanium/platinum), purity(null), purpose_label(null), expected_return(null), actual_return(null), **weight_returned(null)**, **weight_loss(null)**, status(dispatched/partially_returned/returned, idx), note(null), user_id→users, **returned_by→users(null)**
*Karigar raw-material sub-flow (Requirement #5c): raw metal issued, finished untagged product(s) returned. Kept separate from `movements` because what leaves and what returns are not the same physical thing. Current implementation supports one new `Item` per return (linked via `items.source_karigar_batch_id`) — multi-piece splitting per batch is still open, see `docs/REQUIREMENTS.md`. `weight_returned`/`weight_loss` are typed in at Karigar Return (Requirement #6, never computed); `returned_by` records who processed the return, which is often not whoever dispatched it (rule 3).*

**`customer_material_jobs`** — id, customer_id→customers, vendor_id→vendors, description, weight_out, metal(gold/silver/titanium/platinum), expected_return(null), actual_return(null), weight_in(null), weight_loss(null), status(out/returned, idx), note(null), user_id→users, **returned_by→users(null)**
*Karigar customer-material sub-flow (Requirement #5b): a customer's own untagged gold/silver sent for repair. Never shop stock — has no item ID — so it can't go through `movements` (item/packet/box only) or `karigar_raw_batches` (shop-owned raw material). Tracked against the customer directly. Closed at Karigar Return (Customers' material tab), which records `weight_in`/`weight_loss` by hand, sets `returned_by`, and can queue a "ready to collect" `pending_notifications` row.*

**`rate_logs`** — id, metal(**gold/silver/titanium/platinum**), rate, source(manual/api), updated_by→users(null), created_at
*idx: (metal, created_at)*
*Enum widened alongside `items.metal`.*

---

## Old Gold/Silver Exchange & Refinery

**`exchange_transactions`** — id, customer_id→customers, gross_weight, description(null), net_weight(null), purity_test_1(null), purity_test_2(null), purity_averaged(null), preset_deduction_percent(default 2.00), deductable_weight(null), stage(received/melted/tested/valued/settled, idx), final_value(null), settled_by→users(null), settled_at(null), created_by→users
*The client's exact 4-step guided process (gross weight → net weight after melt → two independent purity readings, auto-averaged → preset deduction → valuation), confirmed as never to be abstracted or combined into fewer steps. `stage` tracks progress for the Status Tracker screen.*

**`refinery_batches`** — id, weight, photo_path(null), status(sent/returned, idx), refined_weight(null), refined_purity(null), sent_at(null), returned_at(null), created_by→users
*Simple out/in round-trip: accumulated scrap sent to an external refinery, refined material with a purity reading comes back. Photos go through `PhotoCompressionService`.*

---

## Custom Orders

**`orders`** — id, customer_id→customers, product_description, category(null), metal(gold/silver/titanium/platinum, null), estimated_weight(null), estimated_value, advance_amount(default 0), full_payment_now(default false), rate_locked(default false), locked_rate(null), locked_at(null), in_stock_item_id→items(null), out_of_stock(default false), status(placed/confirmed/ready/delivered/cancelled, idx), expected_ready_date(null), converted_sale_id→sales(null), created_by→users
*A distinct lifecycle from Sales, not a sale sub-type — a pre-commitment that later converts into a sale. Rate-locking (Requirement #11): if `full_payment_now` was true at entry, `rate_locked`/`locked_rate`/`locked_at` freeze the rate at order time; otherwise the rate at delivery applies (nothing frozen). `out_of_stock` supports ordering a product not currently in stock; `in_stock_item_id` links to a real item when one exists.*

---

## Pricing & Discounts

**`discount_rules`** — id, scope(item/category/box/packet/weight_tier), scope_ref_id(null), category(null), min_weight(null), max_weight(null), discount_type(flat/percentage), value, active, valid_from(null), valid_to(null), created_by→users
*idx: (scope, scope_ref_id), (active, valid_from, valid_to)*
*Precedence when computing a price: item → packet → box → category → weight_tier, first match wins.*

**`gst_rates`** — id, category, rate_percent

---

## Customers, Loyalty & Referral

**`customers`** — id, name, phone(idx), address(null), email(null), **password(null)**, **remember_token**, gstin(null), balance, status(past_customer/order_given/order_pending), loyalty_points, referral_code(unique,null), referred_by→customers(null), imported_from_tally
*Authenticatable — logs into the separate `customer` guard via phone+password (Requirement #14).*

**`loyalty_settings`** — id, points_per_rupee(default 0.001), referral_bonus_points(default 100), min_redeemable_points(default 0), point_value_in_rupees(default 1), updated_by→users(null)
*Single-row config table — always accessed via `LoyaltySetting::current()`. Not actually used for auto-calculation (Requirement #15 — loyalty is fully manual); kept for reference/possible future use.*

**`loyalty_transactions`** — id, customer_id→customers, points(+/-), reason(purchase/referral/redemption), related_sale_id→sales(null)
*Append-only ledger — `customers.loyalty_points` is a cached total derived from this. Awards and redemptions are manual entries (Requirement #15), not calculated.*

**`installment_schemes`** — id, customer_id→customers, monthly_amount, months_paid, start_date, status(active/completed/defaulted)

**`installment_payments`** — id, scheme_id→installment_schemes, amount, paid_on
*Fully manual — no automatic payment detection (Requirement #15).*

---

## Sales & Billing

**`sales`** — id, customer_id→customers, invoice_number(unique), type(sale/order_delivery), cgst, sgst, igst, additional_charges(json,null), discount(default 0), payment_modes(json,null), accountant_note(null), total, confirmed_by_accountant(default false), created_by→users
*Insert-only except `confirmed_by_accountant` and `invoice_number`, both set exactly once, together, via the Sale Verification Queue (Requirement #12) — `invoice_number` holds a `RESV-...` placeholder from entry until then, when it's replaced with the real sequential number from `invoice_counters`. The narrow, intentional exception to "never update a sales row."*

**`invoice_counters`** — id, financial_year(unique, e.g. "2026-27"), next_number(default 1)
*One row per Indian financial year (Apr–Mar); `InvoiceCounter::nextFor()` increments it under a row lock so verified sales get a sequential, gap-free GST invoice number, formatted `INV/2026-27/00001`.*

**`sale_items`** — sale_id→sales, item_id→items, price_at_sale — *composite PK, price frozen permanently at time of sale*
*On sale entry, each item's `items.status` is set to `reserved`, not `sold` — verification is what flips it to `sold`.*

---

## Purchases & Vendors

**`vendors`** — id, name, type(karigar/supplier/hallmark_center), phone(null), address(null), balance

**`purchases`** — id, vendor_id→vendors, **type(finished_product/raw_material, default finished_product)**, invoice_number(null), total_weight(null), total_amount, gst(null), payment_status(paid/partial/pending), created_by→users
*`type` added (Requirement #13) — finished-product purchases attach real tagged items; raw-material purchases attach description-only lines that get tagged later.*

**`purchase_items`** — **id(own PK, was composite purchase_id+item_id)**, purchase_id→purchases, item_id→items(**nullable**), **description(null)**, **category(null)**, **metal(null)**, **purity(null)**, **tag_pending(default false)**, rate, weight
*Restructured to support raw-material lines that have no item yet at entry time: `item_id` is nullable, `tag_pending=true` marks a line as "still needs tagging," and `description`/`category`/`metal`/`purity` describe what was bought before it becomes a real item. When staff later tag it (Stock → Items → Pending Tags), the line's `item_id` is filled in and `tag_pending` flips false — this is a `purchase_items` update, which is fine; `purchases` rows themselves are still never updated.*

---

## Accounting Ledger

**`accounts`** — id, name, type(asset/liability/income/expense)

**`transactions`** — id, account_id→accounts, reference_type(sale/purchase/installment/manual), reference_id(null), debit(default 0), credit(default 0), **note(null)**, created_by→users
*Auto-write-on-sale/purchase is not yet wired — see `docs/DEVELOPER_GUIDE.md` and `CLAUDE.md`'s build status. `note` supports the one write path that does exist today: a manual journal entry (`reference_type = 'manual'`) from the Ledger View, gated on `ledger.manage` (owner only).*

---

## Notifications

**`pending_notifications`** — id, customer_id→customers(null), type(sale_confirmation/order_ready/loyalty_award/installment_reminder/exchange_valuation_ready/other), recipient_name(null), recipient_phone(null), message, status(pending/sent, idx), sent_by→users(null), sent_at(null), related_type(null), related_id(null), created_by→users(null)
*One shared, generic table across every customer-facing message type (Requirement #16 — no WhatsApp auto-send this phase). A triggering action (sale verified, order marked ready, loyalty awarded, installment due) generates a row with a ready-to-copy `message`; staff copies it out manually and marks it `sent`. `related_type`/`related_id` optionally point back at the record that triggered it, without a rigid FK per type.*

---

## QR / Identification

**`qr_codes`** — id, target_type(item/packet/box), target_id, code, file_path(null)
*idx: (target_type, target_id)*
*Packets get QR codes too, not just boxes (Requirement #1) — already supported by `target_type` including `packet`.*

---

## Public Website

**`storefront_categories`** — id, slug(unique), name, blurb(null), image(null), stock_categories(json, null), sort_order, in_menu(default false), is_active(default true)
*A website category gathers one or more free-text `items.category` values (plus its own name) so stock never has to be re-categorised to be published.*

**`storefront_collections`** — id, slug(unique), name, blurb(null), image(null), sort_order, is_active(default true)

**`item_images`** — id, item_id→items(cascade), path, sort_order, created_by→users(null)
*Catalogue photos of a piece (first = product card), stored through `PhotoCompressionService`. Separate from `movements.photo_path`, which documents dispatches.*

**`storefront_settings`** — id, key(unique), value(null), updated_by→users(null)
*Shop details for the site (WhatsApp, phone, address, hours, map search, social links); unset keys fall back to `config/storefront.php` defaults.*

## CMS / Misc

**`page_terms`** — id, page_key(unique), content(longtext,null), updated_by→users(null)
*Editable terms & conditions per public page — client's exit-clause requirement.*

---

## Roles & Permissions (via `spatie/laravel-permission`, not hand-rolled)

Standard package tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

**Seeded roles** (`RolePermissionSeeder`): `owner` (all permissions), `manager`, `accountant`, `counter_staff`, `karigar_handler` — each scoped, see seeder for exact permission lists.

**Full permission list:** `stock.manage`, `movement.create`, `movement.approve`, `sale.create`, `sale.approve`, `purchase.manage`, `rate.update`, `ledger.view`, `ledger.manage`, `employee.manage`, `user.manage`, `role.manage`, `audit.view`, `discount.manage`, `referral.manage`, `customer.manage`, `orders.manage`, `exchange.manage`, `website.manage`
*`movement.approve` gates the Pending Review queue's confirm action (Requirement #9). `ledger.manage` gates manual journal entry on the Ledger View — seeded to `owner` only, unlike `ledger.view` which `accountant` also has. `orders.manage`/`exchange.manage` gate Custom Orders and Old Gold/Silver Exchange & Refinery respectively — added after an audit found those route groups, plus Stock and Pricing, had no `permission:` middleware at all despite `stock.manage`/`rate.update` already being seeded for exactly that purpose; all four are now gated (`stock.manage`, `rate.update`/`discount.manage` split across Pricing's five pages, `orders.manage`, `exchange.manage`), and `sales.verification` is now also gated on `sale.approve` (previously the queue was viewable, though not actionable, by any authenticated staff).*

## Activity Log (via `spatie/laravel-activitylog`)

Standard package table: `activity_log`. Captures every write on `movements`/`sales`/`purchases` with `user_id`, action, timestamp — this plus the "never hard-update/delete" rule on those three tables is what actually enforces tamper-proofing.

---

## Two Separate Auth Systems — Do Not Merge

| | Staff | Customers |
|---|---|---|
| Table | `users` | `customers` |
| Guard | `web` | `customer` |
| Login field | email **or phone** | phone only |
| Roles/permissions | Yes (Spatie) | No — customers never get staff roles |
| Portal | `/admin/*`, `/stock/*` | `/portal/*` |

A customer must never be promoted to a `users` row, and vice versa — they're structurally separate for a reason: a customer account compromised should never be a path into staff-only screens.

---

## Relationships At a Glance

```
employees ──< users ──< movements (as user_id, approved_by)
                  │
                  ├──< sales (as created_by)
                  ├──< purchases (as created_by)
                  ├──< karigar_raw_batches / customer_material_jobs (as user_id)
                  ├──< exchange_transactions / refinery_batches (as created_by)
                  └──< orders (as created_by)

boxes ──< packets ──< items ──< movements (polymorphic trackable)
                          │  ├──< sale_items >── sales ──> customers
                          │  ├──< purchase_items >── purchases ──> vendors
                          │  └── source_karigar_batch_id ──> karigar_raw_batches
                          └──< purchase_items >── purchases ──> vendors

vendors ──< karigar_raw_batches, customer_material_jobs

customers ──< loyalty_transactions
          ──< installment_schemes ──< installment_payments
          ──< referrals (self-referential via referred_by)
          ──< sales, orders, exchange_transactions, customer_material_jobs
          ──< pending_notifications

orders ──> customers, items (in_stock_item_id, null), sales (converted_sale_id, null)

discount_rules ──> items / packets / boxes / (category string) / (weight range)
rate_logs ──> feeds PricingService, never joined directly by other tables
accounts ──< transactions ── polymorphic reference to sales/purchases/installments
pending_notifications ──> customers (null), polymorphic reference (related_type/id)
```
