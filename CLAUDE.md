# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Sundal (package name `taskly-saas-react`) is a multi-tenant SaaS project-management platform: Laravel 12 (PHP 8.2+) backend, Inertia.js v2 + React 19 + TypeScript frontend, Tailwind v4. Started from the `laravel/react-starter-kit` skeleton but heavily extended with projects/tasks/bugs/sprints, timesheets/budgets/expenses/invoices, contracts with e-signature, a chat module, AI agent modules, Zoom/Google Meet integrations, and ~20 payment gateway integrations for plan billing.

## Commands

**Backend**
- `composer dev` — runs the full local stack concurrently: `php artisan serve` + `queue:listen` + `pail` (log tailing) + `npm run dev`. This is the normal way to develop.
- `php artisan test` — full PHPUnit suite (class-based, not Pest syntax, despite Pest being a dependency).
- `php artisan test --filter=TestName` — run a single test class or method.
- `php artisan test --filter=RoleAccessTest` — the permission regression suite; **must be re-run after any change to `RoleSeeder.php`, `routes/web.php`, or permission middleware**, since it asserts every named authenticated route is covered by its access matrix.
- Tests run against a real **MySQL** database named `sundal_test` (see `phpunit.xml`), not SQLite — that database must exist locally. `tests/TestCase.php` seeds roles/permissions (`PermissionRoleSeeder`) once for the whole run via `$seed`/`$seeder`; per-test isolation comes from `RefreshDatabase` transactions on top of that baseline, not from re-seeding every test.
- `php artisan migrate` / `php artisan db:seed --class=RoleSeeder --force` — apply schema changes / re-sync role permissions on a running dev DB (needed after editing `RoleSeeder.php`, since role permissions are computed and cached in the `roles`/`permissions` tables, not read from the seeder file at runtime).

**Frontend**
- `npm run dev` — Vite dev server (normally run via `composer dev`, not standalone).
- `npm run build` — production build.
- `npm run types` — `tsc --noEmit`. Note: this repo currently has a number of pre-existing type errors unrelated to any given change (e.g. a `pageActions`/`PageAction[]` variant-typing issue that recurs across several `*/Index.tsx` and `*/Show.tsx` pages, plus assorted `any`/missing-property errors). The Vite build does not type-check, so these do not block `npm run build`. Don't try to fix unrelated pre-existing errors as a side effect of a feature change; do avoid introducing new ones.
- `npm run lint` — ESLint with `--fix`.
- `npm run format` / `npm run format:check` — Prettier (with Tailwind + import-organizing plugins) over `resources/`.

## Architecture

### Multi-tenancy: workspaces, not just companies

The core tenant boundary is the **Workspace**, not the User. A `company`-type user owns one or more workspaces; `manager`/`member`/`client` users belong to workspaces via `WorkspaceMember` (which stores a `role` string). A user's active tenant context is `$user->current_workspace_id`.

Workspace-scoped models use the `App\Models\Concerns\BelongsToWorkspace` trait, which adds a global scope filtering every query to `auth()->user()->current_workspace_id`, and auto-stamps `workspace_id` on create. Console/queue contexts (no authenticated user) are left unscoped. A logged-in user with no current workspace sees nothing from these models by default (`whereRaw('1 = 0')`) — this is intentional, not a bug. Cross-workspace queries need an explicit `withoutGlobalScope('workspace')` with a comment explaining why.

Because this global scope also governs Eloquent route-model binding, a route like `Route::get('chat/conversations/{conversation}', ...)` will 404 if the bound record's `workspace_id` doesn't match the current session's workspace — worth checking first when a "not found" bug doesn't match the actual DB state.

Some models instead (or additionally) implement their own hand-rolled visibility logic (e.g. `Project::scopeVisibleTo()` — owners see everything, others see projects they created/are a member or client of/or that are workspace-visible). When a page/controller reimplements this logic ad hoc instead of reusing the model scope, it tends to drift out of sync with real access rules — check for a shared scope before writing a fresh visibility query.

### Two parallel permission systems — know which one a given check uses

1. **Spatie `laravel-permission`** (`spatie/laravel-permission`), the source of truth for route/UI gating. Roles (`owner`, `manager`, `member`, `client`, plus `superadmin`/`company`) and their permission sets are defined in `database/seeders/RoleSeeder.php` and applied to the DB via `->syncPermissions()`. Route-level checks use `->middleware('permission:some_permission')` or `permission:a|b` (OR-logic) — see `routes/web.php`. Frontend gating mirrors this: `HandleInertiaRequests::getUserPermissions()` computes the flat permission list shared as `auth.permissions`, and `resources/js/utils/authorization.ts`'s `hasPermission(userPermissions, permission)` checks membership in that array client-side (a UX convenience only — the real enforcement is server-side).
2. **`App\Traits\HasPermissionChecks`**, used by some controllers via `$this->authorizePermission('some_permission')`. Its `checkPermission()` **bypasses Spatie entirely for `type === 'company'` users, granting them blanket access** (`if ($user->type === 'company') return true;`), and only falls through to `$user->hasPermissionTo()` for other user types.

These two systems can disagree, especially for edge-case roles. When changing what a role can/can't do, check both the Spatie route middleware/RoleSeeder path and any controller using `HasPermissionChecks`.

`isSaasMode()` (`app/Helpers/helper.php`, backed by `config('app.is_saas')` / env `IS_SAAS`) toggles between two different permission-module sets inside `RoleSeeder.php` for the `company`/`superadmin` roles — self-hosted vs SaaS-platform mode grant different module lists. `config/features.php` is a separate, simpler concern: boolean feature flags that hide/show sidebar nav entries without touching permissions or routes (routes stay live either way).

### "Unified shell" routes that redirect server-side

Several top-level nav sections (`finance`, `reports`, `meetings`, `docs`) aren't real pages — they're named routes in `routes/web.php` that use a shared `$redirectToFirstPermittedTab` closure to 302-redirect to the first sub-route the user has permission for (e.g. `meetings` → `zoom-meetings.index` or `google-meetings.index`). This exists specifically to avoid an Inertia page rendering once and then client-side-redirecting via `useEffect` (a visible page flash). When adding a new tab to one of these groups, add it to the relevant tabs array in `routes/web.php`, not as a new Inertia page — and note the array order is redirect priority, so a permission listed first will always win when a user has more than one.

### Plan/billing gating

`CheckPlanAccess` middleware (aliased `plan.access`) enforces subscription status per-workspace in SaaS mode only (`isSaasMode()` short-circuits it off otherwise). It checks the workspace **owner's** plan, not the acting user's — a member/manager/client's access rides on their workspace owner's plan status, with fallback logic to switch a user to another workspace with an active plan if their current one's owner has expired.

### Frontend structure

- `resources/js/pages/**` — Inertia page components, one per route, generally matching controller names (mixed casing across the tree: some directories are PascalCase like `Workspaces/`, most are kebab/lowercase like `zoom-meetings/`).
- `resources/js/components/page-template.tsx` — the standard page chrome (title, breadcrumbs, header action buttons via a `PageAction[]` prop) used by most index/show pages.
- Routes are called from the frontend via Ziggy's `route('name', params)`, not hardcoded URLs.
- i18next (`react-i18next`) wraps all user-facing strings in `t('...')`.

### Custom Artisan commands

`app/Console/Commands/` includes `CreateTestUsers` (`users:create-test`) — idempotent, creates one account per role (superadmin/owner/manager/member/client) sharing a workspace, for manual QA — plus `AssignDefaultPlanToUsers`, `EnableCookieBanner`, and `SyncNotificationTemplates`.
