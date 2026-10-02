# Permissions disabled/curated per role — reference for re-enabling later

This is a running log of permissions that were deliberately **removed or withheld**
from a role (as opposed to never existing). Kept so a future version can decide to
re-enable any of these without having to archaeology through `RoleSeeder.php` git
blame. Source of truth for current state is always `database/seeders/RoleSeeder.php`
+ `database/seeders/PermissionSeeder.php` — this file is a index/explanation on top
of that, not a replacement.

To re-enable something listed here: add the permission name(s) back to the
relevant role's list in `database/seeders/RoleSeeder.php`, then run
`php artisan db:seed --class=RoleSeeder --force` on any environment that needs
the change applied (permissions are synced into the `roles`/`permissions` tables,
not read live from the seeder file).

---

## Super Admin — **RESTORED to full access (2026-08-14)**

Super Admin previously had a curated module whitelist (dashboards, company, plan,
coupon, currency, landing_page, custom_page, newsletter, contact, language,
email_template, settings_cookie, referral, plus a filtered subset of `settings`
permissions) and explicitly excluded the `media` module and several workspace-level
settings permissions (`settings_slack`, `settings_telegram`,
`settings_email_notification`, `settings_webhook`, `settings_tax`,
`settings_invoice`, `settings_google_calendar`, `settings_google_meet`).

**As of 2026-08-14, this was changed to blanket access** — Super Admin is the
platform owner and now gets `Permission::all()` synced to the role, full stop.
Noted here in case a future version wants to go back to a curated Super Admin
(e.g. if multiple platform-staff accounts are introduced and a narrower
"support staff" role is needed) — the pre-change permission list above is what
it was scoped to.

Note: `tests/Feature/Permissions/RoleAccessTest.php` still has ~60 rows expecting
`superadmin` to be `DENY`'d on workspace-scoped routes (tasks, projects, bugs,
etc.) from the old curated era. Those are now stale relative to this change and
need updating to `ALLOW` — not yet done as of this writing.

## Manager

- `user_view_logs` — removed: managers do not need access to other users' login history.

Otherwise manager gets a broad module-level grant (projects, tasks, bugs, timesheet,
budget, expense, invoice, media, report, notes, calendar, contracts, knowledge_base,
chat, agent, sprint, form, etc.) plus a curated list of `project_*`/`todo_*`/contract
permissions. See `RoleSeeder.php` for the exact list — it's large enough that "what's
missing" is more useful to track than "what's present."

## Member

- `task_manage_stages` — removed: members can view task stages, not manage/reorder them.
- `bug_manage_statuses` — removed: members can view bug statuses, not manage them.
- **Finance (Budget/Expense/Invoice) — disabled entirely** for members (no
  `budget_*`, `expense_*`, `invoice_*` permissions at all).
- **Reports — disabled** (no `project_report_view_any`/`project_report_view`, no
  `report_timesheet`; this is why `reports.index` and `project-reports.index`
  403 for members).
- **Contracts — disabled entirely** for members (no `contract_*` permissions).
- `user_view_logs` — removed: members must not see other users' login history.
- `agent_advanced_insights` (Risk Radar / Resource Conflicts) — **gap, not an
  intentional removal**: this permission was added to `PermissionSeeder.php`
  after member's permission list was written, and member was never granted it.
  Member has base agent permissions (`agent_view_any`, `agent_view`, `agent_use`)
  but not the advanced-insights one. Currently causes `risk-radar.index` and
  `resource-conflicts.index` to 403 for members — worth deciding whether that's
  intended or should be granted.

## Client

- `task_manage_stages` — removed per product instructions (clients are read-only
  on workflow management).
- `timesheet_*` — removed entirely per product instructions.
- `budget_*` — removed entirely per product instructions.
- `user_view_logs` — removed per product instructions.
- `note_*` — removed entirely per product instructions.
- Knowledge Base, Chat, Agents, BYOA (API keys), Bug Statuses — removed entirely
  per product instructions (clients have no access to any of these modules).
- **`task_view_any` / `task_view` / `task_change_status` — removed 2026-08-14**:
  clients should not see the standalone Tasks page/list in the sidebar or via
  direct URL. Clients can still see task details surfaced *inside the calendar*
  view (`task_calendar_view_tasks` was kept, and the calendar API routes
  `api/tasks/calendar` / `api/task-calendar/task/{task}` were updated to accept
  either the full task permission or the calendar-only one, so this didn't
  regress the calendar feature). Bugs (`bug_*`) were already fully disabled for
  clients before this change.

## Structural note: `owner` Spatie role removed

At some point before 2026-08-14, the dedicated `owner` Spatie role (which used
to `syncPermissions($companyPermissions)`, identical to `company`) was removed
from `RoleSeeder.php` entirely. Workspace owners are now just assigned the
`company` Spatie role directly (see `CreateTestUsers::handle()` —
`$owner->assignRole('company')`). The `workspace_members.role` column still
uses the string `'owner'` for the owning member's row (that's a separate,
workspace-scoped concept from the Spatie role) — this is not a permissions
change, just a role-model consolidation, noted here so it isn't mistaken for
one.

## Related bug (found 2026-08-14, not yet fixed): Companies page / dashboard stats

`users.type` defaults to `'company'` for **every** non-superadmin user
(`WorkspaceService.php`: `'type' => 'company', // All users are company type`)
— it does not distinguish a workspace owner from a manager/member/client. The
actual role distinction lives in `workspace_members.role` and the user's Spatie
role, not `users.type`.

`CompanyController::index()` (superadmin's Companies list) filters with
`User::where('type', 'company')`, which sweeps in every manager/member/client
account alongside real tenant owners — that's why non-owner accounts show up
on the superadmin Companies page. The same bug affects `DashboardController`'s
"Total Companies" / "Active Companies" / recent-companies-widget stats
(`app/Http/Controllers/DashboardController.php` around lines 853, 899-900,
1062, 1131 — all `User::where('type', 'company')`). Correct fix is to filter by
the Spatie `company` role (`whereHas('roles', fn($q) => $q->where('name',
'company'))`) instead of `type`. Not fixed as part of this pass — flagged for
follow-up.
