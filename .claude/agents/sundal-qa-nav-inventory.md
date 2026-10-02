# Sundal Nav / Route / Permission Inventory

Reference table for `sundal-qa-tester`. Pre-derived from `resources/js/components/app-sidebar.tsx`, `routes/web.php`, `database/seeders/RoleSeeder.php`, `database/seeders/PermissionSeeder.php`, and controller source, so the tester doesn't have to re-derive it from scratch every run.

**If live behavior ever disagrees with this table** (a route renamed, a nav item moved, a controller's permission trait changed), the tester should report the drift itself as a `Nav/Route inventory drift` finding (Medium severity), treat what it actually observed as ground truth for that run, and regenerate the relevant row(s) here (re-grep the four source files above) — this table is a starting checklist, never a substitute for the live check.

## Top-level nav items (common to SaaS/non-SaaS company-type roles)

| Nav Item | Route Name | Controller | Uses `HasPermissionChecks`? | Parent Group | Feature-flagged? | Representative interaction |
|---|---|---|---|---|---|---|
| Dashboard | `dashboard` | DashboardController | yes | — | no | Confirm widgets/stat tiles render with data, not skeleton/blank |
| Workspaces | `workspaces.index` | WorkspaceController | yes | — | no | Open a workspace, confirm switch updates `current_workspace_id` context |
| Team | `team.index` | TeamController | yes | — | no (conditional on active workspace) | Open a team member's detail/role editor |
| Projects | `projects.index` | ProjectController | yes | Projects | no | Open "Create Project" modal |
| Tasks | `tasks.index` | TaskController | yes | Projects | no | Open a task, add a comment or change status |
| Bugs | `bugs.index` | BugController | yes | Projects | no | Open "Report Bug" modal |
| Todos | `todos.index` | TodoController | yes | Projects | no | Add a todo item |
| Portfolios | `portfolios.index` | PortfolioController | no | Projects | **yes** (`config/features.php: portfolios`) | Direct-nav only (no nav link expected); confirm route still responds |
| Calendar | `task-calendar.index` | CalendarController | yes | — | no | Change month/week view |
| Timesheets (My) | `timesheets.index` | TimesheetController | yes | Timesheets | no | Add a timesheet entry |
| Timesheets (Weekly) | `timesheets.weekly-view` | TimesheetController | yes | Timesheets | no | Switch week, confirm totals recalc |
| Timesheet Approvals | `timesheet-approvals.index` | TimesheetController (or dedicated approvals controller — verify) | yes | Timesheets | no | Approve/reject a pending entry (manager+ only — client/member should not see this) |
| Finance | `finance.index` (unified-shell redirect → first permitted of invoices/budgets/expenses) | n/a (redirect closure) | n/a | — | no | Confirm redirect lands on correct first-permitted sub-tab for the role |
| Contracts | `contracts.index` | ContractController | yes | — | no | Open a contract, confirm signature/comment UI matches role rights |
| Docs | `docs.index` (unified-shell redirect → kb/notes) | n/a (redirect closure) | n/a | — | no | Confirm redirect lands on correct first-permitted sub-tab |
| Chat | `chat.index` | ChatController | no | — | no | Open a conversation, send a message |
| Meetings | `meetings.index` (unified-shell redirect → zoom/google meetings) | n/a (redirect closure) | n/a | — | no | Confirm redirect lands on correct first-permitted sub-tab |
| Forms | `forms.index` | PublicFormController / FormController | no (PublicFormController) | — | no | Open a form; member role should be `form_view_any` (fill only), not builder |
| AI | `ai.index` | AI module controllers (Health, Standup, Risk Radar, Scope Creep, Resource Conflicts, AI Project Gen) | yes (AiProjectController confirmed; others unverified) | — | no | Trigger one AI module action |
| Reports | `reports.index` (unified-shell redirect → project-reports/timesheet-reports/budgets.dashboard) | n/a (redirect closure) | n/a | — | no | Confirm redirect lands on correct first-permitted sub-tab |
| Billing | `plans.index` | PlanController | yes | — | no (SaaS/non-SaaS only) | View current plan, open upgrade flow |
| Settings | `settings` | Settings\* controllers | yes (SettingsController confirmed; sub-controllers unverified individually) | — | no | Open one settings sub-tab relevant to the role |

## Superadmin-only nav items

| Nav Item | Route Name | Feature-flagged? |
|---|---|---|
| Companies | `companies.index` | no |
| Media Library | `media-library` | no |
| Plans / Plan Requests / Plan Orders | `plans.index` + related | no |
| Coupons | `coupons.index` | no |
| Currency | `currencies.index` | **yes** (`config/features.php: currency_mgmt`) |
| Referral | `referral.index` | **yes** (`config/features.php: referral`) |
| Landing Page (group) | various | no |
| Settings | `settings` | no |

## Known high-value bug class to probe

`App\Traits\HasPermissionChecks::checkPermission()` returns `true` unconditionally for any user with `users.type === 'company'`, regardless of their actual Spatie role. `php artisan users:create-test` creates **all 5 test accounts with `type='company'`** — only the Spatie `role` differs. So on any "Uses HasPermissionChecks? yes" row above, the manager/member/client test accounts are at risk of silently getting access RoleSeeder.php says they shouldn't have. Verify the "check" rows too — confirming whether a controller uses the trait is itself part of the tester's job when the answer isn't already pinned down here.

## Test accounts (all password `password123`, one shared workspace)

| Role | Email | Note |
|---|---|---|
| superadmin | `superadmin@test.com` | standalone, not workspace-scoped |
| owner (Spatie role `company`) | `company@test.com` | **not** `owner@test.com` — workspace owner |
| manager | `manager@test.com` | |
| member | `member@test.com` | |
| client | `client@test.com` | |

## Unified-shell redirect tab order (priority = first match wins)

Cross-check against `routes/web.php`'s `$redirectToFirstPermittedTab` calls for exact current tab arrays/permissions before relying on this — it's a summary, not a substitute:

- `finance` → invoices, budgets, expenses (in registration order)
- `reports` → project-reports, timesheet-reports, budgets.dashboard
- `meetings` → zoom-meetings, google-meetings
- `docs` → kb (knowledge-base), notes
