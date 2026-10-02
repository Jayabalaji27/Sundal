# Sundal — QA Test Summary

Target: http://localhost:8000 (pre-running Laravel/Vite dev server). Roles: superadmin, owner (`company`),
manager, member, client — via `php artisan users:create-test` seeded accounts sharing "Test Workspace".
Full scope and per-role expected-nav matrix: `TEST_PLAN.md`. Defect details: `BUG_REPORT.md`.

This file covers **two passes done the same day**: an initial shallow nav/permission sweep across all 5
roles (checklist-based), and a follow-up **zone-based depth-first deep-dive** on the Owner role only, using
an updated `/qa-analyst` methodology (persistent `storage/qa-reports/coverage.json` ledger, per-screen zone
decomposition, widget-vs-DB validation, and full create→verify→edit→delete journeys with a
`[QA-<run_id>]`-marked test data). The deep-dive found 3 new defects (1 Major, 2 Minor), corrected the
severity of one earlier finding, and closed the "no second workspace to test cross-tenant isolation" gap
flagged at the end of the first pass. Live ledger: `storage/qa-reports/coverage.json`.

## Fix pass (2026-08-23/24)

All 10 bugs found across this file (BUG-01 through BUG-10, see `BUG_REPORT.md`) were fixed
directly in application source per explicit user sign-off, then `npm run build` was run and
**all 10 fixes were re-verified live** against the rebuilt production assets — console clean,
correct values/behavior confirmed, several cross-checked directly against the DB (e.g. BUG-09's
overdue count, BUG-05's activity-log increment). BUG-03/04/06/07 were initially only
code-reviewed, then the user asked for full live verification: BUG-03 confirmed by logging in
as superadmin and checking Media Library is gone from the sidebar; BUG-04 by logging out and
watching the console stay error-free for 11s (past both the timer widgets' 2s/3s polling
intervals); BUG-06 by re-running the project-deep-link → Create Task flow and confirming the
project pre-selects; BUG-07 by creating a milestone with no due date and confirming the card
reads "Due: No due date" instead of the Unix epoch. See `BUG_REPORT.md`'s "Fix status" table.
One process note from this pass: the app was found running against a **stale pre-fix production
build** (no `public/hot` file, i.e. not in Vite dev/HMR mode) partway through — the first live
retest of BUG-01 still failed against old compiled JS. Re-running `npm run build` and retesting
resolved this; worth checking `public/hot` first in any future session before trusting "the
browser shows X" as evidence about current source.

## Coverage by feature

| Feature | Owner | Manager | Member | Client | Superadmin |
|---|---|---|---|---|---|
| Login | ✅ Pass | ✅ Pass | ✅ Pass | ⚠️ Blocked (bad seeded password, fixed) → ✅ Pass | ✅ Pass |
| Nav rendering matches expected permission matrix | ✅ Pass | ✅ Pass | ✅ Pass | ✅ Pass | ✅ Pass (own tree) |
| Dashboard load, no console/network errors | ✅ Pass | ✅ Pass | ✅ Pass | ✅ Pass | ✅ Pass |
| Projects / Tasks / Bugs pages load | ✅ Pass | not directly re-checked (nav confirmed) | ✅ Pass | Tasks correctly 403s (no `task_view_any`) | n/a |
| Task CRUD (create → verify → delete) | not run | not run | ✅ Pass (after finding BUG-02) | n/a | n/a |
| Calendar | ✅ Pass | ✅ Pass (resolves initial doubt — Calendar IS visible) | not re-checked | ✅ Pass | n/a |
| Timesheets / Approvals | ✅ Loads, not exercised | ✅ Loads, not exercised | not run | n/a (no access) | n/a |
| Contracts (list/view) | ✅ Pass | ✅ Pass, delete correctly hidden+blocked | not re-checked | ✅ Pass | n/a |
| Contract Accept/Decline sign flow | not run | not run | n/a | ✅ Pass, end-to-end incl. reload persistence | n/a |
| Contract create-form validation | ✅ Pass (all required fields correctly asterisked) | not run | n/a | n/a | n/a |
| Chat, Meetings entry points | ✅ Loads | not re-checked | not re-checked | not re-checked (nav present) | n/a |
| Finance → Invoices | ✅ Pass | ✅ Pass | ✅ Correctly 403s | ✅ Pass | n/a |
| Finance → Budgets | ✅ Pass | ✅ Pass | ✅ Loads (own-scope) | ✅ Correctly 403s | n/a |
| Finance → Expenses | ✅ Pass | not re-checked | ✅ Loads | n/a (no access) | n/a |
| Finance → Taxes | 🔴 **Crashes (BUG-01)** | 🔴 **Crashes (BUG-01)** | n/a (no access) | n/a (no access) | n/a |
| Docs (KB / Notes) | ✅ Pass | not re-checked | not re-checked | n/a (no access) | n/a |
| Forms, AI, Reports | ✅ Pass (AI + Risk Radar interaction tested) | not re-checked | not re-checked | n/a mostly | n/a |
| Settings, Billing | ✅ Pass | n/a (no Billing, correctly) | n/a | n/a | n/a |
| Permission-boundary probes (forbidden direct routes) | n/a | ✅ `/workspaces`, `/plans` correctly 403 | ✅ `/invoices` correctly 403 | ✅ `/tasks`, `/budgets` correctly 403 | 🔴 **Media Library always 403s even for own nav item (BUG-03)** |
| `HasPermissionChecks` company-type bypass — checked for leakage into non-company roles | ✅ Confirmed NOT bypassed for Manager (contract delete correctly blocked both UI and server-side) | — | — | — | — |
| Logout flow | ✅ Pass (redirects correctly) but 🟡 **leaves timer polling running (BUG-04)** | ✅ Pass | ✅ Pass | ✅ Pass | ✅ Pass |
| Superadmin: Companies / Plans / Coupons / Landing Page | — | — | — | — | ✅ Pass, no console errors |

## Pass / Fail / Blocked counts

- **Confirmed defects: 10 total** (1 Blocker, 5 Major, 4 Minor) — see `BUG_REPORT.md`. BUG-02's severity was
  corrected from Major→Minor during the deep-dive after screenshot evidence showed the native browser
  validation tooltip is actually visible to users (not captured by the accessibility-tree snapshots used in
  the first pass).
- **Passing flows verified (pass 1)**: nav rendering + permission gating for all 5 roles (13+ destinations
  each for the 4 workspace roles, 6 for superadmin), full Task CRUD cycle, Contract sign flow, Contract
  create-form validation, multiple direct-route 403 probes, one code-level bypass check (Manager vs.
  `HasPermissionChecks`).
- **Passing flows verified (pass 2 — deep-dive)**: Dashboard (7 widgets cross-checked against direct DB
  queries, all correct), Workspaces list (search, cross-workspace switch), Team (full invite→verify→cancel
  cycle), Projects (Export produces a real valid .xlsx, empty-submit validation, full
  create→task→member→milestone→edit→delete journey on a single project with widget re-validation after each
  step), standalone Tasks/Bugs lists (widget-vs-DB validation + full create→delete cycles), Calendar (month
  nav, investigated and ruled out an apparent event-clustering anomaly as real seeded data), Chat (message
  send, unread-badge clear), Zoom/Google Meetings (correct not-configured states). **Cross-workspace data
  isolation confirmed correct** (see BUG_REPORT.md "Verified correct" section) — this closes the biggest gap
  flagged at the end of pass 1.
- **Light pass (console/network check only, pass 2)**: Expenses, Expense Approvals, Knowledge Base, Notes,
  Forms, Project Reports, Timesheet Reports, Billing (Plans), Resource Conflicts, Settings→Roles tab — all
  load cleanly with zero console errors.
- **Blocked→resolved**: Client-role login (bad seeded test password, fixed locally, not an app bug).

## Exploratory phase (Azure "QA Brain")

Ran successfully — `qa_brain.py` returned 15 ranked adversarial scenarios (saved in `.qa-tmp/brain_scenarios.json`,
raw app map + tested log in `context.json`). Most brain-proposed scenarios were cross-tenant/cross-workspace
data-leak checks (e.g. "can a user see another workspace's tasks/budgets/contracts via direct ID access") —
**these could not be executed** because this test database has only one workspace ("Test Workspace", id 4)
shared by all 5 seeded accounts; there is no second workspace/tenant to attempt a cross-boundary access against.
This is a real coverage gap worth closing in a follow-up pass (seed a second workspace + a user who does NOT
belong to it, then retry the brain's cross-tenant scenario batch).

Of the remaining, executable scenarios, two were run directly:
- **Contract creation validation** (owner, empty-form submit) — **Pass**, all required fields clearly
  asterisked, no crash.
- **AI module / Risk Radar** (owner, real page load + interaction) — **Pass**, no console/network errors.

Not executed (time/scope): timesheet race-condition, meeting-scheduling race, file-upload-type validation,
timesheet-approval-edge-case scenarios — these require either two concurrent sessions or file upload flows
not otherwise exercised in this pass.

## Zone-based deep-dive coverage (Owner role, pass 2)

Full zone decomposition (header actions → stat widgets validated against DB → controls → data rows →
pagination), executed depth-first with a persistent ledger:

| Screen | Zones covered | Result |
|---|---|---|
| Dashboard | header action (Refresh), all 7 stat-widget groups validated against direct DB queries, activity feed | ✅ All pass — every number matches its DB source, including the "Pending 5 / Overdue 1" invoice widget which looked inconsistent (5≠0+5+1) until confirmed Overdue is an intentional *subset* of Pending, not a separate bucket |
| Workspaces | Create Workspace (not exercised), stat widget, search, workspace switch | ✅ Pass. Discovered 2 additional owner-owned workspaces (`sf`, and one XSS-payload-named one from an unrelated earlier test — confirmed not exploitable, React escapes it) |
| Cross-workspace isolation | Dashboard + Projects list re-checked after switching workspace | ✅ **Pass** — zero data leakage between workspaces, closes the pass-1 coverage gap |
| Team | Invite-someone create→verify→cancel cycle, member list widget | ✅ Pass, full cycle clean |
| Projects (list) | Export (real file, verified), AI Generate (opened, not submitted — external LLM cost), Add Project (empty-submit validation, full create) | ✅ Pass, with one screenshot-based correction to BUG-02's severity |
| Projects → project detail (recursion) | Overview widgets, Tasks tab (create), Team tab (add member), Milestones tab (add milestone), Edit Project, Delete Project | 🔴 2 new bugs found (BUG-05 Major, BUG-07 Minor) + 1 more (BUG-06 Minor) on the Tasks-tab deep link |

Timesheets got a full journey including one action test (Submit on an empty timesheet, which surfaced
BUG-08). Invoices and Budgets each got a full widget-vs-DB validation pass, which is what surfaced BUG-09
and BUG-10. Contracts was not re-tested this pass (already covered thoroughly in pass 1). Not reached at
full zone-based depth: AI (only Risk Radar + Resource Conflicts spot-checked, Support Bot custom agent and
Health/Standup/Scope-Creep modules untested), Settings (only General + Roles tabs opened; Integrations/
Branding/Billing tabs untested), full Reports drill-down (only top-level load checked, not the actual report
content/filters). Manager/Member/Client/Superadmin were not re-run with the zone-based method this session
(pass 1's shallower sweep is what covers them).

## Top risks

1. **BUG-01 (Blocker)** — Taxes page is completely broken for both roles that can reach it (Owner, Manager).
   Trivial one-line fix (missing import), highest priority to ship.
2. **BUG-10 (Major, new this pass)** — Budget page throws a 500 error on *every* load (a background request,
   not a user action), confirmed reproducing across two separate sessions/users. One-line fix (add an `id`
   key to the default-categories seeder array) but currently pollutes every Budget page load with a server
   error.
3. **BUG-05 (Major, new this pass)** — Editing *any* project silently discards the entire edit whenever
   Estimated Hours is 0 (the default for most projects), with zero error feedback. One-line validation fix
   (`min:1` → `min:0`) but currently makes project editing broken for a large fraction of real projects.
4. **BUG-08 (Major, new this pass)** — Submitting an empty timesheet silently fails with no toast, despite
   both the backend rejection and the frontend's toast-on-error handling looking individually correct —
   needs a framework-level trace to find where the two sides disconnect. Same silent-failure *symptom* as
   BUG-05, worth investigating together as they may share a root cause in how this app's Inertia error
   responses are being generated/transported.
5. **BUG-09 (Major, new this pass)** — Invoices' "Overdue" widget/filter never works (always 0), because it
   checks a DB status field nothing ever sets, while the correct due-date-based computation already exists
   elsewhere in the same codebase (`DashboardController` and `Invoice::scopeOverdue()`) — a one-line fix to
   reuse the existing correct logic.
6. **BUG-03 (Major)** — Superadmin's own nav sends them into a dead-end 403; either drop the link or exempt
   the route from the workspace-block middleware.
7. **BUG-02 (downgraded to Minor)** and **BUG-06/BUG-07 (Minor, new)** — a cluster of small-but-real
   required-field and null-date-formatting polish gaps across Task/Project/Milestone forms; cheap to fix as
   a batch since they share the same root pattern (missing visual required-cue, unguarded date formatting).
8. **Pattern worth a dedicated audit**: three of this pass's Major bugs (BUG-05, BUG-08, and arguably BUG-10)
   are all "backend correctly rejects/errors, frontend shows the user nothing" — worth a systematic sweep of
   every `router.post/put/delete(...)` call in `resources/js` for missing or non-firing `onError` handling,
   rather than fixing each instance as it's independently discovered.
9. Manager/Member/Client/Superadmin have NOT had the zone-based method applied at all — recommend that as the
   next pass, prioritizing Manager for the `HasPermissionChecks` bypass-class risk per `CLAUDE.md`.

## Regression specs

Not generated this pass (time-boxed). Best candidates for `/tests/e2e/` specs going forward:
- Task CRUD cycle (Member, pass 1) and Contract Accept flow (Client, pass 1).
- Project full journey (Owner, pass 2): create → add task/member/milestone → edit → delete, since it's the
  most thoroughly re-verified flow and would also catch a BUG-05 regression if `min:1` is fixed incorrectly.
