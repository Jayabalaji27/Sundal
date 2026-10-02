# Swatle PM — Development Log

> Tracks development progress, restore points, and session summaries.

---

## Restore Points

### RP-1 · 2026-07-15 · Initial Commit (Base)
**Tag/Commit:** `a995dd46` — "Initial commit: Swatle PM - full feature set"  
**State:** Original Taskly SaaS base with 6 custom modules already built (Chat, Agents, KB, Forms, Sprints, Portal). All vendor files committed for zero-build shared hosting deployment.  
**Working:** Dashboard, all 6 custom modules, basic project management  
**Not Working:** Many features from newer Taskly version missing (Gantt, Todos, Contracts, Google Meet, Calendar, Notes, Taxes, etc.)

---

### RP-2 · 2026-07-14 · Shared Hosting Fixes
**Commits:** `4bbbd801` → `72bcdcf1`  
**Changes:**
- Fixed subdirectory routing at `codecartz.com/sundal/` (LiteSpeed/cPanel)
- Suppressed PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation warnings
- Fixed Ziggy URL for subdirectory deployment
- Fixed i18n translation URL for subdirectory
- Root `index.php` rewritten as proper subdirectory entry point

---

### RP-3 · 2026-07-15 · Feature Sync from Upstream (Modules 1–6)
**Session Date:** 2026-07-15  
**State:** Synced with latest CodeCanyon Taskly version. All missing features ported.

#### Module 1 — Database Migrations
- Ran 2 pending migrations: `drop_slack_telegram_settings_tables`, `update_invoices_status_enum_partialpaid`

#### Module 2 — Backend Methods + Routes
- **ProjectController** added: `gantt()`, `ganttUpdate()`, `publicView()`, `updateSharedSettings()`, `generateShareLink()`
- **InvoiceController** added: `preview()`, `approvePayment()`, `rejectPayment()`
- **TaskController** added: `getCalendarTasks()`
- **CompanyController** added: `export()`, `import()`, `downloadSample()`
- **UserController** added: `destroyLoginHistory()`, `updateLayoutDirection()`
- **Routes added:** Gantt, public-view, invoice preview/approve/reject, task calendar API, company export/import, referral routes
- **HandleInertiaRequests:** Added `avatar`, `workspace_role`, `storageSettings`, `allowed_file_types`, `max_file_size`, `flash.status/statusType`, `userLanguage`

#### Module 3 — Models + Shared Props
- **Invoice model:** `getTaxRateAttribute()`, `setTaxRateAttribute()` (multi-tax support)
- **Task model:** `assignedUser()` relationship alias
- **User model:** `planOrders()`, `getCurrentWorkspaceRole()`, `findWorkspaceWithActivePlan()`
- **Project model:** `shared_settings` + `password` in `$fillable`, cast to `array`

#### Module 4 — Controllers + Pages + Components
- 54 controllers replaced (all 30 payment controllers updated for invoice payment links)
- TimesheetController, ProjectController, TaskController fully replaced
- SettingsController, SystemSettingsController fully replaced
- 23+ frontend pages replaced/updated
- 22+ components updated

#### Module 5 — Providers, Seeders, Configs, Permissions
- EventServiceProvider, AppServiceProvider replaced
- Config: `dompdf.php`, `excel.php`, `services.php` added
- Seeders replaced and run: PermissionSeeder (+100 permissions), RoleSeeder, EmailTemplateSeeder, NotificationTemplateSeeder
- DatabaseSeeder updated with new demo seeders
- **1,284 permissions auto-assigned to all 6 roles**
- Total: 366 permissions, 673 routes

#### Module 6 — Final UI + Helpers + Referral Fix
- 10 more pages replaced (landing page, brand settings, notification templates, etc.)
- 12 helper functions added: `isRegistrationEnabled`, `defultnotificationAndsetting`, `getPaymentMethodConfig2`, `emailNotificationEnabled`, `isTelegramEnabled`, `isSlackEnabled`, `getSlackWebhookUrl`, `getTelegramConfig`, `isNotificationTypeEnabled`, `setNotificationTypeStatus`, `createDefaultNotificationTemplates`, `createDefaultNotificationTemplateSettings`
- **Referral routes** uncommented (were causing blank dashboard)
- `bootstrap/autoload-packages.php` created for permanent vendor package registration

---

### RP-4 · 2026-07-17 · Crash Fix — Excel Config Breaking Boot
**Issue:** `config/excel.php` copied in Module 5 caused app to crash at boot with `Class "Maatwebsite\Excel\Excel" not found`. The vendor autoloader mappings had been reverted by a `git checkout` command.

**Root Cause:** `vendor/composer/autoload_psr4.php` and `autoload_static.php` are git-tracked with the original sundal autoloader (doesn't include Excel/DomPDF/Google). Any `git checkout HEAD -- vendor/composer/` reverts the manually-added mappings.

**Fix Applied:**
1. Removed `config/excel.php` and `config/dompdf.php` (not needed at boot — auto-discovered by Laravel service providers)
2. Created `bootstrap/autoload-packages.php` — git-tracked custom PSR-4 autoloader for all extra vendor packages
3. Loaded in `public/index.php` and `artisan` after the composer autoloader
4. This is permanent — never affected by `git checkout`

**Status:** App working. All 6 custom modules active.

---

---

### RP-5 · 2026-07-22 · Tester Bug Fixes (Round 1)

**Bugs received:** 6 from tester. Assessment done before coding — 4 fixed in code, 1 by design, 1 requires server-side migration.

#### Bug 1 — Dashboard: "Manage Companies" buried at bottom ✅ Fixed
- Added `Manage Companies` as a primary action button at the top of the Super Admin dashboard (next to Refresh).
- File: `resources/js/pages/dashboard.tsx`

#### Bug 2 — Dashboard: Move Plan/Orders stats inside company ⛔ By Design
- Super Admin seeing global totals (all companies, all plans, all orders, total revenue) is the correct and expected behavior for a platform admin.
- Per-company drilldown is accessible via the Companies list → company profile modal.
- No code change. Documented for tester.

#### Bug 3 — Dashboard: No "i" info icons on cards ✅ Fixed
- Added `Info` icon with `Tooltip` to the Total Companies, Total Plans, and Plan Orders cards explaining what each metric represents.
- File: `resources/js/pages/dashboard.tsx`

#### Bug 4 — Companies: Can't directly access company profile ✅ Fixed
- Company name was plain text in both list view and grid/card view.
- Made company name a clickable button in both views — opens the company info modal on click.
- File: `resources/js/pages/companies/index.tsx`

#### Bug 5 — Forms: 500 error on form creation ✅ Root cause identified — server fix required
- Root cause: Custom migrations for Forms module have never been run on the production server.
- Tables missing: `forms`, `form_fields`, `form_submissions`
- Code is correct (Form model auto-generates token via `boot()` hook).
- **Server fix:** `php artisan migrate --force`

#### Bug 6 — Integrations: Submenus → 500 error ✅ Same root cause as Bug 5
- Root cause: Custom module migrations not run on production server.
- Tables missing: `kb_categories`, `kb_articles`, `kb_attachments`, `api_keys`, `zapier_hooks`, `agents`
- **Server fix:** `php artisan migrate --force` (same command as Bug 5)

**After this RP:**
- Upload `public/build/` to server
- Run `php artisan migrate --force` on server (fixes both Bug 5 and Bug 6)
- Run `php artisan optimize:clear`

---

## Health Metrics (as of RP-4)

| Metric | Value |
|---|---|
| Routes | 673 |
| Permissions | 366 |
| Roles | 6 |
| Custom Modules | 6 (Chat, Agents, KB, Forms, Sprints, Portal) |
| Payment Gateways | 32 |
| Helper Functions | ~45 |
| Google_Client | ✅ |
| Maatwebsite Excel | ✅ |
| DomPDF | ✅ |

---

### RP-6 · 2026-08-17 · Member Role Bug Fixes (Round 2)

**Bugs received:** 4 from tester, testing as Member role.

#### Bug 1 — Dashboard: "Active Projects" card always showed 0 ✅ Fixed
- Root cause: `DashboardController::getActiveProjects()`/`getProjectStats()` re-implemented
  project visibility ad hoc (checked only the `project_members` table), instead of reusing
  `Project::scopeVisibleTo()` — the same scope `ProjectController@index` uses. That scope also
  counts projects a member created or that are marked workspace-wide visible, so a member
  could see projects on the Projects page that the Dashboard card excluded, showing 0.
- Fix: both methods now filter with `->visibleTo($user)` instead of the ad hoc `whereHas('members', ...)`/`whereHas('clients', ...)` branching.
- File: `app/Http/Controllers/DashboardController.php`

#### Bug 2 — Contracts/Budgets missing for Member role ✅ Fixed
- Contracts: `contract_view_any` was already granted to Member in `RoleSeeder.php`, but the
  dev DB's `role_has_permissions` table hadn't been re-synced since that was added — ran
  `php artisan db:seed --class=RoleSeeder --force` to pick it up.
- Budgets: Member had no budget permission at all. Added `budget_view_any` + `budget_view`
  (view-only, mirroring how Contracts is view-only for Member — create/update/delete stay
  Manager/Owner-only).
- Also added `member` to the `manager`/`client` project-scoping checks in
  `ProjectBudgetController` (index/show), so a Member with the new `budget_view_any`
  permission only sees budgets on projects they're actually assigned to, not every budget
  in the workspace.
- Files: `database/seeders/RoleSeeder.php`, `app/Http/Controllers/ProjectBudgetController.php`

#### Bug 3 — Standup Bot removed from AI Tools page ✅ Fixed
- Removed the "Standup Bot" card from `PRESET_TOOLS` in `resources/js/pages/ai/index.tsx`
  (per tester: not required). Backend route/controller (`standup.index`/`standup.api`) left
  in place — only the UI entry point was removed.
- File: `resources/js/pages/ai/index.tsx`, comment updated in `resources/js/components/app-sidebar.tsx`

#### Bug 4 — Chat page: sidebar/main layout alignment & wrapping ✅ Fixed
- Reproduced live (logged in as Member, Chrome devtools): opening a conversation made
  `document.documentElement.scrollTop` jump to 16px on its own. The desktop sidebar nav is
  `position: fixed` (pinned to the viewport), but the header + chat panel scroll with the
  normal document flow — so that 16px page-level scroll shifted the header/message panel
  relative to the sidebar without moving the sidebar, which read as a misaligned/"wrapped"
  layout.
- Root cause: the "scroll to latest message" effect called
  `messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })` with no `block` option.
  Without `block: 'nearest'`, `scrollIntoView()` walks every scrollable ancestor — including
  the outer document — not just the nearest one (the messages `ScrollArea`), so it nudged
  the whole page along with the message list. Verified in-browser: default call → outer
  scroll jumps to 16px; same call with `block: 'nearest'` → outer scroll stays at 0.
- Fix: added `block: 'nearest'` to that `scrollIntoView()` call.
- File: `resources/js/pages/chat/index.tsx`
- Side effect noticed while fixing Bug 3: removing Standup Bot left the AI Tools page's
  "Built-in Analysis" section header showing with no cards underneath for Member (Risk
  Radar/Resource Conflicts need `agent_advanced_insights`, which Member doesn't have) —
  hid that section entirely when there are no visible tools. File: `resources/js/pages/ai/index.tsx`

### RP-7 · 2026-08-17 · Chat Follow-ups (Round 3)

**Bugs received:** 2 from tester, follow-up on the Chat page (still testing as Member).

#### Bug 1 — Chat sidebar too narrow, names/messages cut off ✅ Fixed
- The conversation list + compose panel was a fixed `sm:w-80` (320px). With avatar + gap +
  timestamp eating into that, long conversation names and message previews truncated hard
  (confirmed live: `<script>alert('XSS')</script> Test message input.` and manager replies
  were both clipped mid-string).
- Also found the compose panel's "Project Channels" list had no `truncate`/`min-w-0` at all,
  so long project titles wrapped across two lines instead of ellipsizing.
- Fix: widened the panel to `sm:w-96` (384px) and added `min-w-0 flex-1 truncate` to the
  People/Project Channel name spans in compose (the main conversation list already had
  `truncate` + `min-w-0` — only compose was missing it).
- File: `resources/js/pages/chat/index.tsx`

#### Bug 2 — Starting a project channel failed ✅ Fixed
- Reproduced live: compose → pick a project only (no people) → "Open Project Chat" →
  toast "Failed to create conversation".
- Root cause: the frontend lets you submit with just a project selected (`composeCTA()`
  shows "Open Project Chat" as soon as a project is picked, no participants required), but
  `ChatController::store()` validated `participant_ids` as `required|array|min:1`
  unconditionally — so a project-only submission always failed server-side validation.
- Fix: for `type === 'project'`, `participant_ids` is now optional and the channel's
  participants are derived from the project's own team (`project->users()` +
  `project->clients()`) instead of requiring manual picks. Also made repeat clicks reuse the
  project's existing channel instead of creating a duplicate each time (same pattern already
  used for direct-message dedup), and scoped the compose panel's project list to
  `Project::visibleTo($user)` — it was previously listing every project in the workspace
  regardless of whether the user was actually on it.
- File: `app/Http/Controllers/ChatController.php`
- Verified live: selecting a project with zero participants now opens a 2-member project
  channel instead of erroring.

### RP-8 · 2026-09-27 · New Tester Bug Batch (Round 4) — Session Start

**Status:** Session started, local server up via `php artisan serve` (http://127.0.0.1:8000).

**Bugs received (10 from tester, not yet triaged/fixed):**

1. Contracts — attachments not viewable in Contract Details
2. Dashboard — project count shows 1 instead of 3, false "plan limit reached" error blocks new project creation
3. Invoices — Tax property missing on invoice creation
4. Invoice Details — clicking Download also opens a preview instead of downloading directly
5. Budgets — Project dropdown values don't populate when creating a budget
6. Knowledge Base — no document upload/attachment support
7. Forms — saving a field triggers two toast messages instead of one
8. Reports — Generate Report action fails
9. Expenses — no export option (enhancement request: Excel/CSV/PDF)
10. Navigation/Sidebar — clicking any sidebar module reloads/refreshes the whole sidebar

**Quick categorisation (from tester):**
- Functional bugs (blocking): #1, #2, #3, #5, #8, #10
- UX/behaviour fixes: #4, #7, #10
- Missing features/enhancements: #6, #9

**Next Steps:**
- [ ] Triage each bug — reproduce locally, identify root cause
- [ ] Start with blocking bugs: #2 (Dashboard project count/plan limit), #10 (sidebar refresh — likely a full page nav instead of Inertia link), #8 (Generate Report), #5 (Budget project dropdown), #3 (Invoice tax), #1 (Contract attachments)
- [ ] Then UX fixes: #4 (download-triggers-preview), #7 (duplicate toast)
- [ ] Then enhancements: #6 (KB documents), #9 (Expenses export)
- [ ] Update this log with root cause + fix + file per bug as each is resolved, following the RP-5/6/7 format above

---

### RP-9 · 2026-09-27 · JB Tester Reports (Round 5) — Nav dead-ends, Budgets, Bugs, Contracts, Invoices

**Source:** `jb bug/taskly_manager_role_test_report.md`, `taskly_member_role_test_report.md`, `taskly_client_role_test_report.md` — three real end-to-end test passes (Manager/Member/Client) against a deployed instance.

#### Bug 1 — Chat/Meetings/Knowledge Base/AI nav links → 403 on `/plans` (all 3 roles) ✅ Fixed
- Root cause: `CheckModuleAccess` middleware correctly gates these modules behind the
  workspace owner's Pro Add-on plan, but when a non-owner (manager/member/client) is
  blocked it redirected everyone to `plans.index` — a route that itself requires the
  `plan_view_any` permission, which `RoleSeeder.php` only grants to `owner`/`company`.
  So non-owners traded one block for another: a real 403 dead end.
- Fix: `CheckModuleAccess` now checks `$user->can('plan_view_any')` before redirecting —
  owners still land on `/plans`, everyone else lands on `dashboard` with a flash message
  telling them to ask the workspace owner to upgrade.
- File: `app/Http/Middleware/CheckModuleAccess.php`; updated expectation in
  `tests/Feature/CheckModuleAccessTest.php`. Verified: `CheckModuleAccessTest` passes.

#### Bug 2 — Contracts: Create with no client → full-page 500 (Manager) ✅ Fixed
- Root cause: form/validation already treats `client_id` as optional (fixed in an earlier
  session, see `/memories/repo/taskly-bugs-fixed.md` #23), but the `contracts` table
  migration never made the column nullable — `SQLSTATE[23000] Column 'client_id' cannot
  be null` at the DB layer.
- Fix: new migration makes `contracts.client_id` nullable, `ON DELETE SET NULL` instead of
  `CASCADE` (used raw `ALTER TABLE ... MODIFY` since `doctrine/dbal` isn't installed for
  `Blueprint::change()`).
- File: `database/migrations/2026_09_27_000001_make_contracts_client_id_nullable.php`.
  Migrated locally. **Still needs `php artisan migrate --force` on production.**
- Separately flagged (not fixed here — ops/env, not code): APP_DEBUG appears to be `true`
  in production, exposing raw Laravel/SQL stack traces to end users.

#### Bug 3 — Contracts: status badge shows "Accept"/"Decline" instead of "Accepted"/"Declined" (Client) ✅ Fixed
- The `statusOptions` badge-label list reused the action-verb labels from the Accept/Decline
  dropdown for the status *display* badge too. Left the action dropdown as-is (still says
  "Accept"/"Decline" as a verb, correctly) and changed only the badge display labels to
  past tense.
- Files: `resources/js/pages/contracts/Index.tsx`, `resources/js/pages/contracts/Show.tsx`

#### Bug 4 — Invoices: project picker shows projects a Manager/Member/Client can't otherwise see ✅ Fixed
- Root cause: `InvoiceController::index/create/edit` listed projects with an ad hoc,
  incomplete visibility check (or no check at all in `create()`) instead of reusing
  `Project::scopeVisibleTo()` — the same drift-prone pattern called out in
  `CLAUDE.md`. This is what let Manager see "Demo project" and "zz" in the Invoice
  project dropdown while the Projects page (which does use `visibleTo`) only showed 1.
- Fix: all three methods now filter with `->visibleTo($user)` for manager/member/client,
  consistent with `ProjectController` and `ProjectBudgetController`.
- File: `app/Http/Controllers/InvoiceController.php`. Verified:
  `InvoiceCreationTest` still passes.
- Note: the Projects-page-vs-plan-limit mismatch itself (bug #2 from the earlier tester
  batch, RP-8) is separate and still open — the plan limit intentionally counts every
  project in the workspace (not just what one role can see), which is correct multi-tenant
  behavior, but is confusing without messaging. Left as-is pending a product decision;
  the actual data-leak side of it (Invoice picker) is now fixed.

#### Bug 5 — Budgets: Edit → "End date cannot be in the past" blocks every save (Manager + Member) ✅ Fixed
- Root cause: `BudgetFormModal`'s End Date input is `disabled` in edit mode (and the
  server's `update()` never reads/writes `end_date` at all — it's immutable post-creation
  by design), but the client-side validation still ran the "not in the past" check
  unconditionally. Any budget with an end date already in the past could never be saved
  again, even with zero other changes.
- Fix: that past-date check now only runs in `create` mode.
- File: `resources/js/components/budgets/BudgetFormModal.tsx`

#### Bug 6 — Budgets: Create → Project dropdown empty (Manager + Member) ⛔ By design, UX improved
- Root cause: the dropdown intentionally excludes projects that already have a budget
  (one budget per project) and is already scoped to `visibleTo($user)` in
  `ProjectBudgetController::index`. For Manager/Member test accounts, their one visible
  project already had a budget, so the list was correctly empty, not broken.
- Fix (UX only): the Select placeholder now says "No projects available for a budget"
  instead of "Select project" when the list is empty, instead of silently showing nothing.
- File: `resources/js/components/budgets/BudgetFormModal.tsx`

#### Bug 7 — Bugs: Edit → Update doesn't save, dialog stays open, `Update error: Object` (Manager + Member) ✅ Fixed
- Root cause: `BugModal.tsx`'s create path converts the `'none'` sentinel values
  (`milestone_id`/`assigned_to`) back to `null` before submitting, but the update path
  called `put()` directly without that conversion. Submitting the literal string
  `"none"` for `milestone_id` fails `BugController::update`'s
  `nullable|exists:project_milestones,id` validation, so the whole update 422s and
  nothing is saved — matching the reported "description reverts to empty" symptom (the
  entire request was rejected, not just partially applied).
- Fix: apply the same `setData` sentinel-to-null conversion before `put()` as the create
  branch already does before `post()`.
- File: `resources/js/pages/bugs/BugModal.tsx`

#### Not yet investigated (carried over)
- Contract Type dropdown lists "Full Time" twice — not reproducible in any seeder
  (`ContractSeeder`/`ContractTypeSeeder` don't create a type by that name); looks like
  test data duplication rather than a code bug. Needs a live repro to confirm.
- Meetings dead end for the Client role specifically leads to `/plans` too (Bug 1 above
  should now also fix this for Client, since the same middleware/redirect applies).
- Settings → Branding tab empty; Billing → Tax Settings "No results found" vs Taxes page.
- Timesheet/Expense: Manager/Member can edit/delete their own **Approved** timesheets and
  expenses — permissions review needed.
- Dashboard "My Tasks" shows a task in a project ("Demo project") that isn't in the
  user's own Projects list — visibility inconsistency to investigate (same family as
  Bug 4 above, may already be helped by it, needs a live check).
- Round 4 batch (RP-8, from `bug-issue-descriptions.md`) — Invoice tax property missing on
  creation, Invoice download-triggers-preview, KB no document support, Forms duplicate
  toast, Reports "Generate Report" fails, Expenses no export, Sidebar refresh on nav click
  — still open.

#### Contracts — attachments "not viewable" (RP-8 #1) — investigated, code looks correct
- `ContractController::show()` already sets `$attachment->url = get_file($attachment->files)`
  for every attachment before rendering, and `get_file()` (in `app/Helpers/helper.php`)
  correctly builds a working URL for local/S3/Wasabi storage. The frontend's Eye/Download
  buttons in `contracts/Show.tsx` use `attachment.url` first, only falling back to a guessed
  `/storage/media/{path}` if that's missing — which shouldn't happen given the controller
  logic above.
- No code defect found. Likely either: (a) already fixed by this `get_file()` call in an
  earlier session and the original tester report predates that fix, or (b) a live-only
  issue (e.g. missing `storage:link` symlink on the tested deployment, or a specific
  attachment's underlying file missing from disk). **Needs a live repro against the actual
  deployed instance** — check `php artisan storage:link` has been run and the physical file
  exists at `storage/app/public/media/contracts/attachments/...` before further code changes.

**Regression check:** `CheckModuleAccessTest`, `InvoiceCreationTest`, `ContractUploadTest`
all pass. `RoleAccessTest` has one pre-existing failure (`manager` expected 403 on
`taxes.index`, got 200) — confirmed unrelated to this session's changes (no Tax
routes/permissions/seeder touched); needs its own follow-up.

**Next steps:**
- [ ] Deploy this batch: `php artisan migrate --force` (contracts.client_id), then
      `php artisan optimize:clear`
- [ ] Live-verify Bug 6/7 fixes against the actual tester accounts (not just unit tests)
- [ ] Live-verify Contract attachments actually render (check `storage:link` + physical file)
- [ ] Investigate the pre-existing `taxes.index` RoleAccessTest failure separately
- [ ] Continue down the "Not yet investigated" list above, slice by slice

#### Invoices — "Tax property missing on creation" (RP-8 #3) — likely stale, needs re-check
- `InvoiceController::create()`/`edit()` already pass `taxes` (workspace-scoped via
  `Tax`'s `BelongsToWorkspace` trait) and `invoices/Form.tsx` has a full Tax select +
  per-tax breakdown UI (`selected_taxes`, `calculateTax()`). If the workspace has zero
  `Tax` rows the picker correctly shows "No taxes configured" (same empty-until-seeded
  pattern as Contract Types), which is a data/seeding state, not a missing feature.
- The newer RP-9 Manager test report (a full pass on the same "Test Workspace") explicitly
  lists Invoices as fully "Working" (create/view/edit) and Taxes as "Working" with 4 taxes
  present — so this earlier RP-8 report looks stale/already resolved in that workspace.
  **No code change made** — re-verify live before spending more time here; if still empty,
  check the specific test workspace actually has `Tax` rows and `current_workspace_id` is
  set correctly for the account under test.

---

## Known Pending Items

- [ ] Commit and deploy to `codecartz.com/sundal/` (shared hosting)
- [ ] Run `php artisan migrate` on production after deploy
- [ ] Test all 6 custom modules end-to-end
- [ ] Configure OpenAI API key for Agents/Chatbot
- [ ] Configure Google OAuth for Google Meet/Calendar
- [ ] Set up real-time (polling/WebSocket) for Chat module
- [ ] Form Builder — email notification on submission
- [ ] Client Portal — mobile-friendly styling
- [x] Round 4 bug batch (see RP-8) — Contract attachments (no defect found, needs live
      repro), Invoice tax on creation (stale, already working), Invoice
      download-triggers-preview (already fixed, server-side PDF), KB documents (already
      implemented), Forms duplicate toast (not found — no repro), Reports "Generate
      Report" fails (already fixed, after_or_equal validation), Expenses export (already
      implemented), Sidebar refresh on nav click (already fixed, merges expandedItems)
- [x] Round 5 bug batch (see RP-9) — Contract Type duplicate (fixed: ContractSeeder now
      guards against workspaces that already have types), Settings Branding/Tax mismatch
      (fixed: settings/index.tsx passed the wrong prop to TaxSettings), Approved
      timesheet/expense edit permissions (by design per role-capabilities-plan.md, no
      change), Dashboard/Projects task visibility mismatch (fixed: getMyTasks() now uses
      Project::scopeVisibleTo)
- [x] Pre-existing `RoleAccessTest` failure: manager expected 403 on `taxes.index`, got 200
      — fixed: RoleSeeder.php had wrongly granted manager tax_view_any (unnecessary, since
      InvoiceController fetches taxes without a permission check). Removed the grant.

Full regression: `php artisan test` → 536 tests / 754 assertions, 0 failed (2026-09-27).

---

## Dev Commands Reference

```bash
# Start local server
php artisan serve

# Clear all caches
php artisan optimize:clear

# Clear permission cache
php artisan permission:cache-reset

# Run migrations
php artisan migrate --force

# Re-seed permissions + roles
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force

# Build frontend
npm run build

# List all routes
php artisan route:list

# Check route count
php artisan route:list | Measure-Object
```
