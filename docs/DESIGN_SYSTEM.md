# Aurum ERP Design System

The canonical look for every staff-facing screen: a restrained luxury feel (ink sidebar, gold accent,
warm ivory surfaces) built for daily work. Implemented as Tailwind tokens (`tailwind.config.js`), a small
component CSS layer (`resources/css/app.css`) and shared Blade components (`resources/views/components/ui/`).
Never hand-roll inline `style="..."` colours; use the tokens and components below so every page stays in sync.

After changing any Blade view or CSS, run `npm run build` (or keep `npm run dev` running). Tailwind only
emits classes it finds in the templates at build time.

## Typography

| Role | Font | Where |
|---|---|---|
| UI text | **Manrope** (`font-sans`) | Everything by default |
| Display | **Cormorant Garamond** (`font-display`) | Wordmark, page titles, modal titles, headline figures (stat values, prices, weights) |
| Codes | **JetBrains Mono** (`font-mono`, or `.rj-code`) | HUIDs, internal codes, packet/box codes, sticker codes |

`font-display` forces lining numerals (Cormorant defaults to old-style figures). Use `.tabular` on any
column of numbers so digits line up.

## Tokens (Tailwind classes)

| Token | Hex | Tailwind class |
|---|---|---|
| Sidebar / darkest surface | `#151515` | `bg-ink` |
| Charcoal / raised dark | `#1F1F1F` / `#242320` | `bg-ink-charcoal` / `bg-ink-soft` |
| Text on dark | `#C9C4B8` / `#8E8A80` | `text-ink-fg` / `text-ink-dim` |
| Gold (primary accent) | `#B8862D` | `bg-gold` / `text-gold` |
| Gold dark / light / soft / tint | `#946B20` / `#D4AF5A` / `#E8D3A2` / `#FBF4E4` | `gold-dark` / `gold-light` / `gold-soft` / `gold-tint` |
| Page background | `#FAF8F3` | `bg-surface-bg` |
| Card / surface | `#FFFFFF` | `bg-surface` |
| Muted / sunken surface | `#F3F1EC` / `#F7F5F0` | `bg-surface-muted` / `bg-surface-sunken` |
| Text primary / secondary / muted | `#1A1A1A` / `#6F6B63` / `#9A958B` | `text-ink_text-*` |
| Border / light / strong | `#E7E3DA` / `#EFEBE3` / `#D9D3C6` | `border-line` / `border-line-light` / `border-line-strong` |
| Success / Warning / Danger / Info | see `tailwind.config.js` | `text-success` + `bg-success-bg`, etc. |

Utilities: `gold-sheen` (the gold gradient used on primary buttons and the logo mark), `ink-grain` (subtle
gold glow on dark panels), `press` (tactile 1px press on click), `tabular`.

**Radius:** 10px controls (`rounded-control`), 14px cards (`rounded-card`), 18px modals, full pills for badges.
**Shadows:** `shadow-card` (resting), `shadow-raised` (hover / emphasis), `shadow-pop` (dropdowns),
`shadow-modal`, `shadow-gold` (primary button), `shadow-focus` (focus ring). All are warm-tinted, never black.
**Layers:** `z-sidebar` < `z-topbar` < `z-dropdown` < `z-drawer` < `z-modal` < `z-toast`. Don't invent z-index values.

## Layout shell (`components/layouts/app.blade.php`)

- **Sidebar** (`partials/sidebar.blade.php`): the whole navigation is one PHP array at the top of the file:
  groups, routes, permission gates, live badge counts. Add pages there, not as markup. Groups are
  accordions (the current group opens automatically; others remember their state). Collapsible to a 78px
  icon rail (`Ctrl + \` or the topbar button); in rail mode, hovering a group shows a flyout of its pages.
  The collapsed state is stored in `localStorage` and applied before first paint. Below `lg` it becomes an
  off-canvas drawer.
- **Topbar** (`partials/topbar.blade.php`): sidebar toggle, global search (`/` or `Ctrl + K` focuses it),
  today's gold/silver rate chip (flags a stale rate), pending-message bell, user menu.
- **Toasts:** from any Livewire component, `$this->dispatch('toast', message: '...', type: 'success|error|info|warning')`.
  `session('toast')` / `session('status')` also show as a toast after a redirect.
- **Confirm dialog:** instead of `confirm()`, dispatch
  `$dispatch('rj-confirm', { title, message, confirm: 'Label', tone: 'danger', action: () => $wire.method(id) })`.

## Auth pages (`components/auth/shell.blade.php`)

Every sign-in / password page (staff login, register, forgot/reset/confirm password, verify email, customer
portal login and change password, and the public welcome page) uses `<x-auth.shell audience="staff|customer|welcome">`
inside `<x-layouts.guest>`. On `lg` and up it shows a jewellery photo carousel beside the form; below `lg` only the
brand mark and the form. Photos live in `public/images/auth/` (public-domain museum photography, see `CREDITS.md`
there); swap in the shop's own photos by replacing those files. Use `<x-ui.password-input>` for passwords and
`<x-auth.submit>` for plain-form submit buttons. Phone is always the first sign-in option, entered with a `+91`
prefix and normalised by `App\Support\Phone`.

## Components (`resources/views/components/ui/`)

| Component | Use |
|---|---|
| `<x-ui.page-header title subtitle :crumbs>` | Page title (display serif) with breadcrumbs, `meta` slot (badges) and `actions` slot |
| `<x-ui.button variant size icon icon-right href target>` | Variants: `primary`, `secondary`, `ghost`, `soft`, `dark`, `danger`, `danger-soft`. Sizes: `xs`, `sm`, `md`, `lg`, `icon`, `icon-sm`. Shows a spinner automatically while its own `wire:click` (or `target`) is running. With `href` it renders a link and `target` means the HTML attribute |
| `<x-ui.card title subtitle icon :padding>` | Surface. With a title it gets a header row and an `actions` slot |
| `<x-ui.modal wire:model="showX" title subtitle icon max-width submit>` | Livewire-bound dialog (`sm`/`md`/`lg`/`xl`/`2xl`). `submit="save"` wraps body and `footer` slot in a form. Escape, backdrop and close button all close it. Put `autofocus` on the first field |
| `<x-ui.field label for error hint optional>` | Label above, control in slot, validation error (from `error="prop"`) or hint below |
| `<x-ui.datatable :paginator>` | Server-side table shell: `toolbar`, `bulk`, `head` slots; progress bar and dimming while loading; per-page selector and pager |
| `<x-ui.th field :sort-field :sort-direction align>` | Sortable header cell for `WithDataTable` components |
| `<x-ui.table :headers>` | Simple (non-paginated) table |
| `<x-ui.search-input wire:model.live.debounce... scan>` | Search box with icon and clear button. `scan` adds a camera button that fills it from a QR sticker or barcode (`scan-continuous` keeps the camera open) |
| `<x-ui.scan-button target="#input-id" submit continuous title variant>` | Opens the phone-camera scanner for one field. `submit`: omit to just fill, `'enter'` presses Enter on the field, `'form'` submits its form. `continuous` keeps scanning tag after tag. `variant="button"` for a labelled button |
| `<x-ui.logo size>` | The Radharani "R" mark (`public/images/auth/mark.png`). The only place the logo file is referenced |
| `<x-ui.dropdown>` + `<x-ui.dropdown-item icon href tone>` | Anchored menu |
| `<x-ui.badge tone size dot>` | Pill. Tones: `success`, `warning`, `danger`, `info`, `gold`, `dark`, `neutral` |
| `<x-ui.status :status>` | The **only** way to render `items.status`, so labels/colours stay consistent |
| `<x-ui.stat-card icon label value hint href>` | KPI tile |
| `<x-ui.empty-state icon title message compact>` | Empty lists; put the next action in the slot |
| `<x-ui.stepper :steps :current go-to reach>` | Guided-form progress: numbered steps plus a progress bar. Clicking a step calls the component's `go-to` method (default `goToStep`), so earlier steps can be reopened. Used by Exchange; Sales next |
| `<x-ui.timeline :events>` | Renders `StockHistoryService` events (Item/Packet/Box Detail) |
| `<x-ui.password-input>` | Password field with show/hide toggle (auth pages) |
| `<x-ui.icon name size>` | Lucide paths kept in one file. Add new names there; never inline raw `<svg>` in pages |

Stock-specific: `<x-stock.qr-panel>` (QR sticker panel on detail pages, calls the component's `issueQr()`),
`<x-stock.status-mix>` (where a container's pieces are, as a stacked bar + legend).

Form controls are plain elements with classes: `.rj-input`, `.rj-select`, `.rj-textarea`, `.rj-checkbox`,
`.rj-radio`, `.rj-input-sm`, plus `.is-invalid` for the error state. `.rj-segment` (with `.is-active` on the
current button) is the segmented control for switching modes or quick filters. `.rj-dl` is the key/value
list used on detail pages.

The default Livewire pager is overridden in `resources/views/vendor/livewire/tailwind.blade.php`, so every
`$paginator->links()` in the app uses the new style and pages over AJAX.

## Patterns

- **List pages** use `App\Livewire\Concerns\WithDataTable` + `<x-ui.datatable>`: search, sortable columns,
  filters and page size are all server-side, kept in the URL (`#[Url]`), and never reload the page.
  Declare `sortableColumns()`, `defaultSort()` and `filterProperties()`.
- **Add / edit forms open in a modal**, never inline above a list. Larger forms that are shared between
  pages become their own component opened by an event (see `Stock\ItemForm` and `open-item-form`).
- **Bulk actions**: row checkboxes bound to `$selected`; when anything is selected, the dark bulk bar
  appears in the datatable's `bulk` slot.
- **Destructive or irreversible actions** go through the `rj-confirm` dialog.
- **Copy:** plain, specific sentences. No em dashes in UI text.

## Rules

1. Never invent new colours; pick the closest token.
2. Keep every `wire:model`, `wire:click`, form action, route and Livewire method intact when restyling.
3. The customer portal (`livewire/portal/**`) is customer-facing, so it wears the **public website's** design,
   not Aurum: `components/layouts/portal.blade.php` loads the site's header/footer (`core.js`), `base.css` and
   `public/storefront/css/portal.css` (`pt-*` classes, phone-first). No Tailwind there.
4. Wireframes in `resources/views/wireframes/` are reference-only static views; don't restyle them.
5. The public website (`resources/views/storefront/**`, `public/storefront/**`) is **not** part of Aurum. It keeps its
   own design (from the `rr-web-ui` repo: Bodoni Moda + Jost, sage/green palette, GSAP motion) and its own CSS/JS.
   Don't apply Aurum tokens or Tailwind there. The staff-side Website screens (Listings, Categories...) are Aurum as usual.
