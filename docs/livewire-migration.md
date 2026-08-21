# Inertia/React → Livewire migration

**Finished.** The front end was moved from Inertia + React to Livewire + Blade
one module at a time (strangler pattern), with both stacks running side by side
so the application stayed usable throughout. Inertia, React and Wayfinder have
since been removed; what follows is the record of how it was built and the
traps worth remembering.

## Decisions

| Topic | Choice |
|---|---|
| Strategy | Strangler — migrated module by module, each owning its real URLs |
| UI kit | DaisyUI 5 (Tailwind 4 plugin) + Alpine (bundled with Livewire) |
| Icons | `blade-ui-kit/blade-heroicons`, wrapped by `<x-icon name="…" />` |
| Tables | One shared component + `WithDataTable` trait, server-side search/sort/paging |
| Tests | None — verified by rendering components in `php artisan tinker` and by manual passes |
| Auth pages | Blade forms posting to Fortify's own endpoints |

## The stack

Blade + Livewire + Alpine, and nothing else:

- **One CSS and one JS entry** — `resources/css/app.css` and
  `resources/js/app.js`, both listed in `vite.config.ts`. `address-cascade.js`
  and `chart.js` are dynamic imports, so only the screens that need them pay.
- **Routes are the application.** Every screen is a Livewire component mounted
  directly on its route; the only controller left is `FileController`, which
  streams stored files.
- **`wire:navigate` everywhere.** With a single stack there is no page that has
  to be reached by a full load, so navigation stays on the SPA path.
- **Two message channels.** The layout renders session flashes (used after a
  redirect) and Livewire `toast` events (used for in-place actions).

During the migration the seam was the route file: a migrated module replaced
its own route definitions and was marked `'migrated' => true` in
`config/navigation.php` so `wire:navigate` skipped the pages still on React.
The flag is now true everywhere and could be dropped.

## The shell

| File | Role |
|---|---|
| `resources/views/components/layouts/app.blade.php` | Page shell: drawer, sidebar, header, toasts |
| `config/navigation.php` | The menu — labels, routes, icons, permissions |
| `app/Support/Navigation.php` | Filters the menu by permission, resolves active state |
| `app/Support/PermissionCatalog.php` | Groups the flat permission list into the role matrix |
| `app/Support/Branding.php` | App name and logo from preferences |
| `resources/views/components/` | `icon`, `breadcrumbs`, `user-menu`, `toasts`, `page-header`, `card`, `modal`, `pagination`, `entity-log`, `settings-nav`, `password-requirements`, `form/field`, `table/*` |
| `resources/js/address-cascade.js` | Country → state → city selects (Alpine), dataset loaded on demand |
| `resources/js/chart.js` | Chart.js as an Alpine component, theme-aware, library loaded on demand |
| `resources/views/components/layouts/auth.blade.php` | Guest layout for the authentication screens |
| `resources/views/auth/*.blade.php` | Fortify's views, registered in FortifyServiceProvider |
| `app/Livewire/Support/MaterialHistoryScreen.php` | Base for the per-material purchase/sales history screens |

Theme: DaisyUI `corporate` (light) and `business` (dark), chosen from the
`appearance` cookie (`HandleAppearance` shares it with every view) and applied
by an inline script before first paint, so a dark-mode reload never flashes
white. There is no toggle in the header — appearance lives in personal
settings, and the accent colour in Preferences, which writes `color_theme` and
is applied as `<html data-accent>` over DaisyUI's `--color-primary`.

## Module pattern

Simple master-data modules extend two base classes, so a screen only declares
what differs from every other screen:

- `app/Livewire/Support/MasterIndex.php` — search, sort, paginate, delete
  behind a confirmation modal, permission guards on the actions, and a
  `deleteBlockedReason()` hook for records still in use.
- `app/Livewire/Support/MasterForm.php` — one component for create **and**
  edit, live per-field validation, flash + redirect, "save and add another".

A concrete module is then three small classes plus two views — copy Brands:

1. `app/Livewire/<Module>/Index.php` — extends `MasterIndex`; declares its
   model, permission prefix, label, view and its searchable/sortable columns.
2. `app/Livewire/Forms/<Model>Form.php` — Livewire form object owning fields,
   rules, messages and the write. Replaces the FormRequest for that module.
3. `app/Livewire/<Module>/Form.php` — extends `MasterForm`; declares the typed
   form property, binds the record in `mount(?Model $model = null)`, and names
   its index route, label and view.

Views live in `resources/views/livewire/<module>/`. Permissions go on the
routes (`->middleware('permission:uom-view')`) plus a guard inside any action
reachable without a page load.

As each module landed its React pages, controller and FormRequests were
deleted, it was marked `'migrated' => true` in `config/navigation.php`, and
every component was smoke-rendered (`Livewire::mount(...)` in tinker) before
being handed over for a manual pass.

Delete guards worth keeping: a brand, category or UOM attached to materials
cannot be deleted; a location holding stock or referenced by a receipt/issue
cannot; a charge already applied to an order has to be set inactive instead.

## Progress

- [x] Shell — layout, navigation, theme, toasts, shared components
- [x] Shared table (`app/Livewire/Concerns/WithDataTable.php`)
- [x] Shared master-data bases (`app/Livewire/Support/MasterIndex.php`, `MasterForm.php`)
- [x] **UOM** — `app/Livewire/Uoms/`
- [x] **Brands, Categories, Locations, Charges**
- [x] **Customers** — list, create/edit, show (trading summary + change log)
- [x] **Vendors** — same, with a purchasing summary and the materials they supply
- [x] **Materials** — list, create/edit, show (stock + costing), purchase history, sales history
- [x] All master and configuration data is now on Livewire
- [x] **Inventory** — balances, stock record + ledger, manual adjustment (initial / adjust / transfer)
- [x] **Activity logs** — transaction trail and stock ledger, filtered and paginated in SQL
- [x] **Users, Roles, Preferences, Currencies** — accounts, the permission matrix, app settings
- [x] **Auth pages** — login, forgot/reset password, verify email, confirm password (Blade, Fortify-backed)
- [x] **Personal settings** — profile, password, appearance, forced password change
- [x] **Dashboard** — stats, trend, category and top-material charts (Chart.js via Alpine)
- [x] **Welcome page** — public landing page in Blade
- [x] **Purchase orders + goods receipts** — draft/post/revert/cancel, receipts against outstanding quantities
- [x] **Sales orders + goods issues** — same lifecycle outwards, with availability checked before shipping
- [x] **Inertia, React and Wayfinder removed** — packages uninstalled, `resources/js/{actions,components,hooks,layouts,lib,pages,routes,types,wayfinder}`, `app.tsx`, `ssr.tsx`, the old `app.css`, `app.blade.php`, `HandleInertiaRequests`, `app/Http/Requests/`, `eslint.config.js`, `tsconfig.json` and `components.json` deleted; `livewire.css`/`livewire.js` renamed to `app.css`/`app.js`

The migration is complete: 47 Livewire screens, one asset entry point, and the
only remaining controller is `FileController`.

## Notes

- Records with a change log (customers, and later vendors and materials) render
  it through `<x-entity-log :logs="$record->logs" />`. The diff itself is
  written by the `HasEntityLogs` concern, and the form object passes the user's
  "reason for the change" through to the log entry.
- The address selects import their dataset in three pieces: countries (~95 KB)
  and states (~550 KB) load with the form, while the 8 MB city list is fetched
  only after a state is chosen. Vite splits each into its own chunk.
- `config/blade-icons.php` releases the `icon` component name
  (`components.default => null`) so `<x-icon>` is the application's own
  wrapper, which falls back to a neutral glyph for unknown names instead of
  throwing.
- Aggregating by a status column must run on the query builder, not the model:
  an Eloquent row casts `status` to its enum, so comparing it against
  `PurchaseOrderStatus::liveValues()` (raw strings) silently matches nothing.
  Both show screens use `DB::table(...)` for their summaries because of this.
- Change logs are ordered by `id`, not `created_at`: creating and immediately
  editing a record puts two entries in the same second.
- A screen that both lists rows and totals them needs two queries, not one:
  reusing a query that already carries `select(items.*, …)` and adding `SUM()`
  fails under MySQL's `only_full_group_by`. `MaterialHistoryScreen::aggregateQuery()`
  strips the column list and the ordering before aggregating.
- The stock ledger is treated as the source of truth: the inventory show screen
  sums `inventory_logs.quantity_change` and compares it with the stored balance,
  so a quantity written outside InventoryService shows up as a "Mismatch" card
  instead of hiding.
- Searching a polymorphic trail needs `orWhereHasMorph`, and its closure gets
  the concrete type as its second argument - the receipts and issues have no
  `reference_no` column, so the condition has to branch on the type rather than
  apply one closure to all four documents.
- Accounts that must never be changed from the user list: the seeded
  administrator (id 1) and whoever is signed in. Editing your own account
  redirects to profile settings instead, where the session is handled properly.
- `abort()` inside a Livewire `mount()` is swallowed by `Livewire::test()`, which
  renders an empty component instead of raising. Guard behaviour has to be
  verified through the route (`GET /roles/1/edit` -> 403), not the component test.
- The authentication screens are plain Blade, not Livewire: Fortify owns every
  endpoint they post to, so a form with the field names it expects is both
  simpler and less to go wrong. Only the forced password change is a Livewire
  component, because it has logic of its own.
- `EnsurePasswordChanged` allows the `password.change` route through. Livewire
  updates post to their own endpoint, which this middleware is not applied to,
  so the form on that screen submits without needing to be allow-listed - and
  it must not be added to Livewire's persistent middleware, or saving there
  would redirect to itself.
- Only `resetPasswords` and `emailVerification` are enabled in
  `config/fortify.php`: there is no registration screen and no two-factor flow
  to migrate.
- Charts are Chart.js driven by an Alpine component (`resources/js/chart.js`).
  The library is a dynamic import, so only pages with a chart download its
  207 KB chunk, colours are read from the DaisyUI theme tokens, and the chart is
  rebuilt when `data-theme` changes so it stays legible in both themes.
- Index screens now paginate in SQL instead of shipping whole tables to the
  browser, which is why `WithDataTable` keeps its state in the query string.
- Document forms compute their live totals server-side through the same
  `LineCalculator` and `DocumentTotalsCalculator` the services save with, so the
  figure on screen and the figure in the database cannot disagree.
- Cross-line business rules belong in `rules()` as closure rules, not in
  `addError()` before `save()`: a Livewire form object writes into the
  component's error bag, so a guard written that way does not stop the save and
  the service throws instead. Closure rules are prefixed and short-circuit
  correctly.
- `App\Data\*` are the payload contract between the form objects and the
  services. They outlived the controllers that first built them and are still
  what the Livewire forms hand to `PurchaseOrderService` and friends.
