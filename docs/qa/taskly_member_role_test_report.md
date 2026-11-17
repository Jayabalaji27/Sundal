# Taskly: Member Role Test Report

- **Environment:** https://168-231-102-225.sslip.io/
- **Account:** member@test.com (Test Member, "Test Workspace")
- **Date tested:** 26 Sep 2026
- **Scope:** Every section in the Member's nav. In each one: does the page load, and do view, create and edit work where offered. Delete actions were not tested.

## Summary

| Result | Count |
|---|---|
| Working | 10 |
| Partially Working | 0 |
| Broken | 6 |

**Verdict:** Not release-ready. The Member's day-to-day work (tasks, timesheets, expenses, notes) works, but 5 nav sections lead to a 403 dead end, bug edits don't save, and budgets can't be created or edited. Members also get finance buttons they probably shouldn't have.

## Results by section

| # | Section | Status | Details |
|---|---|---|---|
| 1 | Team | Working | Member list (4 members) loads read-only; no invite form for this role. |
| 2 | Projects | Working | List and project detail (`/projects/2`) load. No Add or Edit (view only). Note: the Dashboard's "My Tasks" shows a task in **Demo project**, but the Member's Projects list shows only "xx". |
| 3 | Tasks | Working | Create, edit and view (detail panel) all work. |
| 4 | Bugs | **Broken** (edit) | Report Bug (create) works. **Edit Bug → Update**: the dialog stays open with no message, and the console logs `Update error: Object` (BugModal.js). `PUT /bugs/8` returns 200, but the description isn't saved (still empty after reopening). Same defect as the Manager role. |
| 5 | Calendar | Working | Month view and the event popup load. |
| 6 | Timesheets (My / Weekly) | Working | Create (with an entry), edit and weekly view all work. Possible permissions issue: the Member gets edit and delete on their own **Approved** timesheet (week of 17 Aug). |
| 7 | Contracts | Working | View only (no Add or Edit, as expected). Contract detail (`/contracts/4`) loads. |
| 8 | Chat | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 9 | Meetings (Zoom / Google) | **Broken** | Both links send you to `/plans`, which shows **403 Forbidden**. |
| 10 | Budgets | **Broken** (create + edit) | View and detail (`/budgets/2`) work. **Create Budget**: the Project dropdown is empty. **Edit → Update Budget** fails with "End date cannot be in the past", but End Date is read-only, so it can never save. Also, the Member gets Create, Edit and Delete on budgets, which is likely too much access for this role. |
| 11 | Expenses | Working | Create and edit work. Possible permissions issue: the Member gets delete on their own **Approved** $9,000 expense. The expense date defaults to 25 Sep while local time is 26 Sep (probably using the UTC date). |
| 12 | Knowledge Base | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 13 | Notes | Working | Create and edit work. |
| 14 | Forms | Working | View only (copy link and open form). The public form (`/f/…`) opens and renders correctly. No create or edit for this role. |
| 15 | AI | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 16 | Project Reports | Working | List and detail (`/project-reports/2`) load. |

## Priority fixes

1. **Nav dead ends:** Chat, Zoom and Google Meetings, Knowledge Base and AI all lead to a 403 on `/plans`. Hide them for this role or grant access.
2. **Bug edit doesn't save:** the client treats a 200 response as an error, and the update isn't saved.
3. **Budgets:** the new-budget project list is empty, and a budget whose end date has passed can't be edited.
4. **Permissions to review:** a Member can create, edit and delete budgets, delete an Approved expense, and edit or delete an Approved timesheet.
5. **Project visibility:** a task assigned to the Member in "Demo project" shows on the Dashboard, but the project itself isn't in their Projects list.

## Test data left in the workspace

QA Member Task · QA Member Bug · Timesheet for the week of 21 Sep 2026 (Draft, note "QA member edit") · QA Member Expense ($1, Pending) · Note "QA Member Note edited". Budget "xx" was opened for editing; no field was changed.
