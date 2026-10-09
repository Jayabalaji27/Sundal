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

### RP-10 · 2026-10-05 · Manager QA Report (Production-Readiness Pass, BUG-01…21)

**Source:** `taskly-manager-qa-report.md` (manager@test.com, workspace 2). Regression tests:
`tests/Feature/QaManagerReportFixesTest.php`.

**P1**
- BUG-01 Checklist progress — `TaskChecklistController::store` never recalculated
  progress (only toggle/destroy did). New `Task::syncProgressFromChecklists()` used by
  store/destroy/toggle. `ProjectHealthService` "done" now = task in a completed stage
  (`task_stages.is_completed`), not `progress = 100`.
- BUG-02 Submitted timesheets editable — `Timesheet::isLocked()` (submitted/approved).
  `TimesheetController` update/destroy throw a validation error (422 for JSON); the
  `TimesheetEntryController` store/update/destroy/bulk routes refuse entries of a locked
  timesheet too. Card/table Edit+Delete hidden unless draft/rejected. Rejecting reopens it.
  (Supersedes the RP-9 "by design" note on editing approved timesheets.)
- BUG-03 AI plan gate — `chatbot.index`, `chatbot.ask`, `chatgpt.generate` now carry
  `module.access`; `FloatingChatGpt` hidden when `auth.modulesLocked`.

**P2**
- BUG-04 Own items in approval queues: approve/reject hidden on own submissions (owner
  excepted), `review_own` flag added to expense approval permissions.
- BUG-05 Expense approval list + both stats endpoints share `reviewableExpenses()` —
  managers scoped to assigned/created projects like `/expenses` and timesheet approvals.
- BUG-06 "Delete undefined": callers passed `title`/`description` to
  `EnhancedDeleteModal`, which takes `entityName`/`warningMessage`. Fixed Forms plus the
  same bug in Agents, Knowledge Base, Portfolios, Sprints.
- BUG-07 Public form errors use field labels (`:attribute is required.`) + native `required`.
- BUG-08 Forms toggle: optimistic badge update, rolled back on error (server side was fine).

**P3** — 09 notes "Showing 0 to 0"; 10 Critical card counts severity critical/blocker;
11 report labels the overview ring "Project Progress" and shows "No milestones";
12 "1 item added"; 13 "Client added to project" toast; 14 a task on a sent/paid invoice
can't be billed again (`InvoiceItem::billedTaskIds`, validated on store/update, excluded
from the task picker; drafts/cancelled don't count); 15 `DialogDescription asChild` in
`EnhancedDeleteModal`; 16 Form Builder null placeholder → `''`; 17 header breadcrumbs
truncate, intermediate crumbs hidden below `lg`; 18 initial `<title>` uses
`globalSettings.titleText`; 20 aria-labels on CrudTable row actions + timesheet/expense
card icons; 21 dashboard task stats use new shared `Task::visibleTo()` (also used by
`TaskController::index`).
- BUG-19 Contract Types slow — **not reproduced**: warm requests 87–122ms (38 queries,
  ~35ms DB); Settings' background XHRs all <140ms. One 23s cold-start spike had only
  109ms DB time → environment (single-threaded `php artisan serve`, OneDrive-synced
  project dir), not a query. No code change; re-measure on the deployed instance.

Tests: `QaManagerReportFixesTest` 14/14 pass; `QaRoleReportFixesTest` still passes.
Full suite 2026-10-05: 176 passed, 35 failed, 1 skipped (212 total, not the 536 recorded
on 09-27; `RoleAccessTest`/`CheckModuleAccessTest` no longer exist in `tests/`). The 35
failures are in AiModulesTest, DashboardTest, FunctionalTest (health 403), PlanAccessTest,
RazorpaySettingsTest, Settings/Password+ProfileUpdateTest. None of them reaches code changed here: most are
302/404s from middleware or moved routes (e.g. the profile page is `/profile`, tests still
hit `/settings/profile`), and AiModulesTest "overdue tasks reduce score" expects 3 overdue
tasks (−20 → 80, "healthy") to be at_risk. There's no git baseline here to prove they
failed before this change, so triage them separately.

---

### RP-11 · 2026-10-08 · AI Assistant + BYOA, Phase 1 start (branch `feature/ai-assistant-byoa`)

**Plan:** "Sundal AI Assistant & BYOA — Implementation Plan" (claude.ai doc). Scope: company
owner + manager only, separate AI Assistant page, BYOA only (no Sundal-managed or local
models), gated by the existing Pro Add-on (`module.access`). Tests: `tests/Feature/AiAssistantTest.php`.

- **Provider layer** — `App\Services\Ai\AiProvider` interface. `PrismProvider`
  (`prism-php/prism` v0.100.1, new dependency) for Anthropic, OpenAI, Gemini;
  `AzureOpenAiProvider` over the existing `openai-php/client` (Prism has no Azure driver);
  `FakeProvider` for tests. Config: `config/ai_assistant.php` (tested model list, caps, TTLs).
- **Tables** — `ai_provider_settings` (encrypted key, last 4 shown), `ai_conversations`,
  `ai_messages`, `ai_tool_calls` (audit, kept when a chat is deleted; prompt text removed),
  `ai_usage` (monthly token cap).
- **Access** — `AiAccess` + `ai.assistant` middleware: owner/manager only (others 403),
  Pro Add-on via `module.access`, owner can switch managers off. Shared as `auth.aiAssistant`.
- **Tools (8)** — read: list_projects, list_tasks, list_bugs, list_team_members; write:
  create_task, assign_task, change_task_status, assign_bug. Writes only create a pending
  confirm card; they run on Confirm, re-checking access, permission and every record.
  `RecordResolver` scopes every lookup to the workspace + `Project::scopeVisibleTo` and asks
  instead of guessing when a name matches several records.
- **Action classes** (`app/Actions`) — `CreateTask`, `ChangeTaskStage` (now also used by
  `TaskController::store` / `changeStage`), `AssignTask`, `AssignBug`. Acting user is passed in.
- **Audit** — `LogsActivity` adds `via: ai_assistant` + `ai_tool_call_id` to project
  activity metadata for confirmed AI actions (`AiActionContext`).
- **Retention** — `ai:prune-conversations`, scheduled daily 02:30 (per-workspace retention
  days; also expires stale confirm cards).
- **Fix** — `TaskStageSeeder::createDefaultStagesForWorkspace` now sets `is_completed` on
  Done; workspaces created after the 2026-10-02 migration counted Done tasks as open.

Not in this step yet: streaming replies / queue worker (calls run in the request), undo,
spatie activitylog for all modules, `RecordsCreator` trait, manager-workflow tools (Phase 2).

Tests: `AiAssistantTest` 34/34 pass. Full suite 2026-10-08: 35 failures, all in the same 7
files listed under RP-10 (pre-existing). Note: `php artisan test` needs
`-d memory_limit=1G` locally, or Collision runs out of memory printing those failures.

---

### RP-12 · 2026-10-09 · AI Assistant — Phases 2 and 3 complete (branch `feature/ai-assistant-byoa`)

Follows RP-11. All tools are limited by the user's own workspace permissions; owners get all 27.

- **Providers** — OpenRouter added (Prism driver). An empty company key never falls back to a
  server key from `.env`. "No credits left" shown as such.
- **Phase 2 tools** — create bug, change bug status, list sprints, add tasks to a sprint,
  list/decide timesheet approvals, list/decide expenses, budget status, project report.
- **Phase 3 tools** — create project, add project members, list invoices, send invoice, list
  contracts, invite user, revenue summary, Knowledge Base search, record history.
- **Not offered on purpose** — creating sprints (route removed from the product), creating
  contracts (form needs an uploaded file), and the plan's off-limits list (roles, billing,
  keys, webhooks, removing users, deleting a workspace, hard deletes).
- **Safety** — undo for 10 minutes (assign task/bug, task stage, bug status; refused if the
  record changed again); typed confirmation for sending invoices and bulk actions over 10
  records; bulk cards list every record.
- **New shared Actions** (screens use them too) — CreateBug, ChangeBugStatus,
  DecideTimesheetApproval, DecideExpense, AddTasksToSprint, CreateProject,
  AssignProjectMembers, SendInvoice; InviteToWorkspace over WorkspaceService. Sprint add-task
  now refuses tasks of another project or a completed sprint.
- **History log** — `spatie/laravel-activitylog` 4.x (v5 needs PHP 8.4) with `workspace_id`;
  `HistoryRecorder` logs 22 models with who, old/new values and source (screen /
  ai_assistant / system). `RecordsCreator` fills created_by/reported_by/uploaded_by on 19
  models. `LogsActivity` no longer credits changes with no signed-in user to user 1.
  New columns: sprint_tasks.created_by, contracts_attachments.uploaded_by, contacts.created_by.
- **Page** — upgrade page for owners without the Pro Add-on; 30-day usage chart and cap bar;
  replies render bold, links and bullets; provider errors stay in the chat (`ai_messages.is_error`).
- **Cap alert** — email to the owner once a month at 80% of the token cap (if mail is configured).
- **Writing helper** — the floating ChatGPT button uses the workspace's BYOA provider when one
  is connected (old `chatgptKey` still used otherwise) and counts toward the cap.
- **Queue mode** — `AI_ASSISTANT_QUEUE=true` answers in `ProcessAiMessage` (runs as the sender)
  and the page polls. Off by default: needs a running queue worker.
- **Evaluation** — `tests/AiEval/prompts.json` (52 manager + 50 owner prompts) and
  `php artisan ai:eval --user=<email>`: scores the tool picked first, gate 90%. Uses the
  workspace's real provider (costs tokens); changes nothing.

Tests: `AiAssistantTest` 62/62. Verified in the browser with OpenRouter: "create a project in
the name of sundal" → card → Confirm → project created.

---

### RP-13 · 2026-10-09 · AI Assistant — deterministic form cards and fallbacks

**Problem:** "create a to do task for a login page" created the task in a project the model
picked (the tool schema marked `project` required, so the model filled it), with silent
priority/assignee defaults. Asking in text would cost extra AI calls.

**Fix:** one AI call, then the server decides with fixed rules (`app/Services/Ai/Forms/`):
- 10 write tools implement `HasForm`; the model gets every parameter as optional and is told
  to pass only what the user said.
- `FormBuilder` keeps a value only if it resolves to exactly one visible record **and** the
  user's own words name it (grounding check, no AI). Otherwise the card shows a list,
  matches first. Project is pre-filled when there is only one; priority/severity/status and
  assignee (with "Unassigned") are required picks.
- Confirm sends the picks, the server validates them against the same scoped lists, re-runs
  `prepare()` and executes. Errors stay on the card's fields; no AI call to fix them.
- Fallbacks: quick-action buttons open any form with no AI (`POST ai-assistant/forms`);
  `IntentMatcher` offers the form by keyword when the provider fails or the model shows no
  card. Not on forms: bulk approvals and add-to-sprint (their cards already list every record).
- Sync replies raise PHP's time limit (`AI_ASSISTANT_SYNC_TIME_LIMIT`, 300 s) so a slow
  provider ends as a saved error with the form fallback, not a 30 s fatal.

Tests: `AiAssistantTest` 76/76. Verified in the browser (OpenRouter free model): the card
filled Title "Login page" and the only project, left Priority and Assignee to pick,
Confirm disabled until picked; "New task" quick action opened the form with no AI call.

---

### RP-14 · 2026-10-09 · AI Assistant — ask in the chat, topic buttons

Feedback on RP-13: a form for every incomplete request makes the assistant a slower copy
of the normal screens, and quick actions should set the subject, not open forms.

- **Three tiers per field** (`FormField`): must know (asked), default (priority → medium,
  severity → major, status → planning, assignee → unassigned, project role → member; never
  asked, shown on the card), optional (never asked). An AI-supplied enum the user did not say
  falls back to the default.
- **Drafts instead of forms:** a missing must-know value makes the card a single question
  (`stage: question`) with the choices as buttons. A click (`POST tool-calls/{id}/update`) or
  a short typed answer (`ReplyMatcher`, e.g. "mobile app, make it high", "assign it to me")
  completes it with **no AI call**. Then a short confirm card (`stage: review`) with
  Confirm / Edit / Cancel; the form only opens on Edit.
- `ReplyMatcher` only answers plain short replies on the latest draft: questions, commands,
  long messages or unexplained words go to the AI, which updates the same draft in place.
- **Topic buttons** (`Topics`): Tasks, Bugs, Projects, Approvals, Finance, Team, Help, under
  the message box, kept on `ai_conversations.topic` until removed. The model gets only that
  topic's tools (+ shared look-ups) and a prompt line; a message clearly about another topic
  gets all tools in the same call. Never adds tools beyond permissions.
- Removed the quick-action buttons and `POST ai-assistant/forms`. With the AI down, a bare
  message under a topic still starts that topic's draft.
- Fixed while testing: "me" matched any option whose id equalled the user's id (project #1);
  "me"/"unassigned" now apply to people fields only.

Tests: `AiAssistantTest` 89/89. Browser (OpenRouter free model), Tasks topic: "login page
for sundal, high priority" → confirm card with High + Unassigned (1 AI call); "assign it to
me" → card updated, server reply, still 1 AI call for 2 messages.

---

### RP-15 · 2026-10-10 · AI mode: the assistant in its own tab

Like Outlook's "New Outlook" switch: an **AI mode** switch in the header (owners and
managers; owners without the add-on are sent to the upgrade page) opens the assistant
full screen in a new tab at `/ai-mode`. On = that tab is open; off closes it. Phones open it
in the same tab. The sidebar "AI Assistant" item now shows on phones only; owners reach the
AI settings from the AI mode tab.

- **Authentication:** same login and checks (auth, plan, `ai.assistant`, `module.access`),
  plus Laravel's `password.confirm` with a 30-minute grace when opening AI mode.
- **Workspace lock:** the tab is bound to the workspace it was opened for; after a switch in
  Sundal its requests get 409 `workspace_changed` (nothing runs in the wrong workspace).
- **Idle timeout:** owner picks 15 / 30 / 60 min (`ai_provider_settings.idle_timeout_minutes`,
  default 30). Only the user's POST actions count, never polling. 2-minute warning with
  "Stay signed in"; then the tab locks (chat kept) and asks for the password.
- **Only while Sundal is open:** Sundal tabs check in (`POST ai-mode/heartbeat`, 30 s);
  no check-in for 120 s → 423 `locked_app_closed`. The tabs also talk over BroadcastChannel,
  so closing Sundal locks AI mode within ~7 s (5 s grace for reloads) and reopening it
  unlocks automatically. A password does not lift this lock.
- **Sign-out** in Sundal tells the AI tab; any 401/419 shows "You are signed out".
- `App\Services\Ai\AiMode` holds the rules; `EnsureAiModeSession` (`ai.mode`) applies them
  to requests carrying `X-AI-Mode`; the normal AI Assistant page is unaffected.

Note: the heartbeat and the AI tab's polling keep the normal 120-minute Laravel session
alive while both tabs are open; the AI mode idle lock is what ends an unattended session.

Tests: `AiAssistantTest` 98/98. Browser (Edge, two tabs): switch → new tab → password →
AI mode; Sundal switch shows on; closing Sundal locked AI mode; reopening unlocked it;
switching off from Sundal closed the AI tab. The idle lock was tested on the server only.

---

### RP-16 · 2026-10-10 · AI Assistant security review and hardening

Already sound: encrypted, never-returned BYOA keys; permission + workspace re-checked on
every tool and on Confirm; fixed vendor endpoints (Azure host pattern, no SSRF); replies
rendered as React elements (no HTML); confirmation for every write; rate-limited unlock.

Fixed:
1. **Session rules could be skipped** by calling the API without the `X-AI-Mode` header or
   using `/ai-assistant`. Now both entry points need the password (30-min grace) and start
   the AI session; every assistant request needs it (workspace lock + idle lock). Only
   "Sundal must be open" stays specific to the AI mode tab.
2. **External links in AI replies** (prompt-injection phishing): only Sundal-internal
   relative links are clickable; others show as plain text.
3. **Saved key sent to a new Azure endpoint**: changing the endpoint now requires the key.
4. **Untyped card values**: `fields.*` must be a value or a short list of values (no nested
   arrays, ≤ 4000 chars); a list where one value is expected is a card error, not a 500.
5. **Rate limits**: card actions 60/min/user, connection tests 10/min/user.
6. **Queued reply after a workspace switch**: does nothing and says so (was silent).

Still to do outside the code: `APP_DEBUG=false` and `APP_ENV=production` in production;
HTTPS so session cookies are secure; rotate the OpenRouter key that was pasted in chat.

Tests: `AiAssistantTest` 104/104 (6 new). Browser two-tab AI mode flow re-checked.

---

### RP-17 · 2026-10-10 · No password for the AI Assistant; fallback card fix

- **No password prompt** (decision): the AI Assistant page and the AI mode tab use the
  current browser login. `password.confirm` removed from both routes and the
  `password_grace_minutes` setting removed. The AI session still starts on opening, so the
  workspace lock, idle timeout and (AI mode) Sundal-open rules stay. The idle pause now ends
  with **Continue** (`POST ai-mode/unlock`, current login) instead of a password.
- **Wrong fallback card:** "create a invoice in sundal project" offered a New project card
  (keyword fallback matched "create … project"). `IntentMatcher` now offers nothing for
  things without a create form (invoice, contract, expense, timesheet, milestone, sprint,
  budget, payment, note, meeting, client, user), and "project" only counts when it is what
  is created ("create a project …"), not where ("… in sundal project").

Tests: `AiAssistantTest` 107/107. Browser: AI mode opens straight to `/ai-mode`.

---

### RP-18 · 2026-10-10 · AI Assistant module tools, phase 1: Finance and Time

Goal: the assistant can do each module's everyday create/update/delete for company owners
and managers (permissions decide per tool). Plan: per-action tools, never a generic CRUD
tool; deletes only for everyday records; typed confirmation for money. Phase 1 = Finance +
Time (17 tools, registry now 44). Phases 2–4 (projects/tasks/sprints/bugs, contracts and
meetings, docs and team) follow.

- **Shared Actions** (screens and assistant run the same rules): `Invoices/{CreateInvoice,
  UpdateInvoice, DeleteInvoice, MarkInvoicePaid}`, `Expenses/{CreateExpense, UpdateExpense,
  DeleteExpense}`, `Budgets/{CreateBudget, UpdateBudget}`, `Timesheets/{LogTime,
  UpdateTimeEntry, DeleteTimeEntry, SubmitTimesheet}`, `Timer/{StartTimer, StopTimer}`.
  `InvoiceController`, `ProjectExpenseController`, `ProjectBudgetController`,
  `TimesheetEntryController`, `TimesheetController::submit` and `TimerController` now call
  them (validation stays in the controllers). Side fixes: the invoice-created event fires
  after the items exist; a budget update can no longer edit another budget's category by id.
- **Finance tools:** `create_invoice` (draft billing unbilled tasks, one amount per task;
  client = the project's only client; undo deletes the draft), `update_invoice` (title,
  dates, notes; drafts; undo), `delete_invoice` (drafts; invoice number typed),
  `mark_invoice_paid` (sent/overdue; number typed; undo), `list_expenses`,
  `create_expense` / `update_expense` / `delete_expense` (not-yet-approved only; no future
  dates; undo on create/update), `create_budget` (one per project, one "General" category),
  `update_budget` (total, period, status; undo).
- **Time tools (new Time topic):** `list_my_time`, `log_time`, `update_time_entry`,
  `delete_time_entry` (own entries on unlocked timesheets only), `submit_timesheet` (the only
  open one is picked without asking), `start_timer`, `stop_timer`.
- **Forms:** new field kinds `number`, `unpaid_invoice`, `expense`, `budget_category`,
  `time_entry`, `timesheet`, `billable_tasks` (several); date and text defaults; a list
  narrowed by an earlier project field follows it (pick a project → that project's tasks).
  `ReplyMatcher` reads a typed number ("750", "2.5 hours") when one is asked.
- **Topics:** new "Time" button; Finance covers invoices, expenses and budgets. Under a
  topic button, a draft still waiting in the chat keeps its tool. Tried and dropped:
  narrowing the tools by keywords when no button is on. It broke real requests ("assign the
  login bug fix to Ravi" named "bug" and lost the task tools), so without a button the
  model still gets every tool (44). Revisit with prompt caching if token cost grows.
- **Keyword fallback** offers the new cards ("create an invoice", "add an expense for…",
  "log 3 hours…", "mark INV-104 as paid", "submit my timesheet", "start the timer"); a
  message about a task or bug stays a task or bug.

Tests: `AiAssistantTest` 130/130 (14 new, incl. the screens still working through the
shared actions). QA role suites + `FunctionalTest` after the controller refactor: 148
passed; the 2 project-health 403/404 failures in `FunctionalTest` also fail without these
changes (pre-existing).

### RP-19 · 2026-10-10 · AI Assistant: new chat interface

Redesign of `ai-assistant/index.tsx` from three references (ChatGPT-style welcome screen,
a chat app with a conversation sidebar, a prompt-card start page); only what fits Sundal.

- **Welcome screen:** assistant orb, "Good morning, {name} — What can I do for you
  today?", the message box in the middle, and four example cards with topic icons
  (topic-specific examples when a topic is on).
- **Conversation sidebar:** search, chats grouped Today / Yesterday / Previous 7 days /
  Older, topic icon and time ago per chat, delete on hover. Slides over the chat on phones.
- **Chat header:** chat title, connected model pill (new `model` prop: provider and model
  names only), Settings (owners) and New chat. Owner settings moved from tabs to this
  button, with "Back to the assistant".
- **Messages:** avatar, name and time; assistant replies in cards with Copy; typing dots
  while waiting; the confirm cards unchanged.
- **Message box:** grows with the text, topic picker as a dropdown with the chosen topic
  as a removable pill, character count, round send button; disclaimer underneath.
- Left out on purpose: attachments, voice, likes, regenerate, folders (no backend for them).

Checked in Edge: light, dark, 390px phone, owner settings. `AiAssistantTest` 130/130.

---

## Known Pending Items

- [ ] Commit and deploy to `codecartz.com/sundal/` (shared hosting)
- [ ] Run `php artisan migrate` on production after deploy
- [ ] AI Assistant: `npm run build` (new page) + `php artisan migrate` + scheduler running for `ai:prune-conversations`
- [ ] AI Assistant: run `php artisan ai:eval` per role on each provider before release (plan gate: 90%)
- [ ] AI Assistant: set `AI_ASSISTANT_QUEUE=true` once a queue worker runs in production
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
