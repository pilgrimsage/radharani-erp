# Radharani Jewellery ERP — Updated Project Context Pack (Post-Clarification)

Supersedes the original requirement document wherever the two conflict. Everything below reflects confirmed answers received from the client after the original requirement document was sent. Pair with `DEVELOPER_GUIDE.md`, `SCHEMA_REFERENCE.md`, and `CLAUDE.md`. As of this document being added to the repo, all of the schema/behavior changes it implies have been implemented — see `CLAUDE.md`'s Build status table and "still open" list for exactly what's done vs. genuinely unresolved.

## What this project is

Internal stock-audit ERP + read-only ecommerce catalogue for Radharani Jewellery Works, a jewellery shop. The core problem is tamper-evident tracking of physical stock (vault, counter, karigars, hallmarking, customers) — not primarily an online store.

## Confirmed business rules (from client clarification rounds)

These supersede the original requirement document wherever they conflict with it:

1. Packets get QR codes too, not just boxes.
2. Internal product code is 5 characters, alphanumeric, and must avoid visually ambiguous characters (e.g. 0/O, 1/I) — confirmed non-ambiguous character set required; exact character list finalized in code (`Item::CODE_ALPHABET`), not a business question.
3. QR code creation has two open paths: staff can generate one on the spot, or admin can pre-assign one in a batch — no approval gate between the two.
4. Two separate activity views are required: product-wise history (already planned) and staff-wise history — a log filterable by staff member across everything they've handled.
5. Karigar dispatch covers three distinct sub-flows, not one generic out/in:
   - A tagged, already-in-stock item sent for repair/maintenance (symmetric — same item returns).
   - A customer's own untagged gold/silver sent for repair — this is never shop stock, has no item ID, and is tracked against a customer reference instead (`customer_material_jobs`).
   - Raw material issued, finished (untagged) product received — the returned item(s) do not exist as records until the return is processed (`karigar_raw_batches`); one dispatch can return as more than one finished piece (current implementation supports one piece per return — see "still open" below).
6. Weight loss/wastage is expected and normal on karigar and hallmarking returns. It is not auto-calculated — staff manually enter the loss figure at the time of return (`movements.weight_loss`).
7. Karigar and Hallmarking can chain: after a karigar return, staff can check a box indicating the item needs to go to hallmarking next, which carries the item's "untagged, needs tagging" status directly into a hallmarking dispatch, rather than requiring two disconnected entries.
8. Hallmarking returns require a "tagged by" field — whoever performs the tagging on return is recorded by name (`movements.tagged_by`, free text — may be an external hallmarking centre person, not necessarily a system user).
9. Items/products returning from karigar or hallmarking sit in a "pending" status (`items.status = 'pending_review'`) until confirmed. Admin is exclusively responsible for closing/confirming pending items and moving them into normal stock (Pending Review queue, gated on `movement.approve`).
10. Making charges have three confirmed types (not two): (1) a percentage of rate × weight, (2) a flat predefined value per piece, (3) a flat predefined value per gram (`items.making_type` enum `percentage`/`flat_per_piece`/`flat_per_gram`).
11. Order rate-locking rule: if a customer pays the full gold/product value at the time of ordering, the rate is locked to that date; otherwise the current rate at delivery applies. Orders can be placed on products not currently in stock (checkbox at entry).
12. A sale only becomes final once an admin verifies it — the item does not leave live availability/the catalogue at the moment staff enters the sale, only once admin confirms. Implemented as `items.status = 'reserved'` between entry and verification.
13. Purchases are admin-only and split into two types: finished product (possibly untagged, tagged later) and raw material (untagged) — `purchases.type` enum, `purchase_items.tag_pending`.
14. A customer's phone number is their unique login identity on the customer portal; customers set/change their own password.
15. Loyalty program and the monthly instalment scheme are both fully manual — no automatic point-earning calculation, no automatic payment detection. Staff/admin enter and update everything by hand.
16. Since WhatsApp auto-send is not being integrated in this phase, every customer-facing notification (sale confirmation, order ready, loyalty award, instalment reminder, exchange valuation ready) follows one shared pattern: system generates a copyable message → staff copies and sends it manually → staff marks it "sent" in a central log (`pending_notifications`).
17. Sorting/filtering for stock must support, at minimum: category, box, and metal type (`items.metal`).
18. Staff login uses phone number or email (not phone-only — phone-only login is reserved for the customer portal).

## Changes confirmed after the meeting of 2 October 2026

The detailed change list of 8 October 2026 (`docs/feedback-2026-10-02/Radharani-ERP-Detailed-Change-List-2026-10-08.docx`) is implemented. In short: GST, invoices, the accounting module, vendors and Loyalty are removed; money disappears from the dashboard; locations, categories (metal then subcategory) and batches are owner-managed or timestamped; Karigar and Hallmarking are one screen each with part receipts, advances, payments and ledgers; Sales is a step form with part payments, a vault check and the Tally bill number at verification; pricing is per carat with rules by product, category or price range; Exchange is resumable and locked once settled; Referral replaces Loyalty.

## Design decisions made on this project (not client-specified, but adopted)

- Karigar raw-material flow gets its own tables (`karigar_raw_batches`), separate from the standard symmetric `movements` pattern, because what leaves and what returns are not the same physical thing (batch-out, one-or-many-items-back).
- Old Gold/Silver Exchange has its own dedicated multi-step entry (`exchange_transactions`) mirroring the client's exact 4-step process (gross weight → net weight after melt → two independent purity readings, auto-averaged → preset deduction), since the client was explicit that no step in this process should be abstracted away or combined.
- Refinery round-trip (`refinery_batches`) reuses the same simple out/in batch shape conceptually, kept as its own table since the actual fields differ (a refined-purity readback, not new finished items).
- Orders (`orders` table) are a distinct lifecycle from Sales, not a sale sub-type — a pre-commitment that later converts into a sale, with its own status pipeline (placed → confirmed → ready → delivered → cancelled).
- Notifications use one shared, generic table (`pending_notifications`) across all message types rather than a separate feature per type.
- Loyalty is a points ledger (`loyalty_transactions`), not issued/redeemed codes; redemption happens as a field inside the Billing screen, not a separate page.

## Still open — genuinely unresolved, needs client input

(Updated 8 October 2026.) The client still owes: the Excel sheet showing their pricing calculation (pricing is provisional), the Google review link for the sale message, the uncollected-order expiry timing, and the referral points rules (the owner sets numbers in the ERP, but the automatic award mechanism is still to be designed). The items below are the older list and are kept for context.


- **Karigar raw-material batch mechanics**: does one raw-material dispatch always split cleanly, and who assigns the new item's tag on return? Current implementation supports exactly one new item per raw-material return (not a repeatable multi-piece split) pending this answer.
- **Vendor payable trigger**: does a karigar raw-material dispatch automatically create an amount owed to that karigar, or is that always a separate manual purchase entry? Not automated either way yet.
- **Pricing calculation mechanics**: the client was to send an Excel sheet explaining their actual calculation logic — `PricingService` and the Making-Charge Configuration screen should be treated as provisional until that's reviewed.
- **Custom/order product expiry**: the original ~2.5-month uncollected-order-releases-to-stock rule — exact timing and customer-warning process not yet confirmed, not implemented.

Everything else previously open (loyalty rules, instalment scheme edge cases, wastage handling, pending-closure authority, 5-character code format) has been resolved by the confirmations above.
