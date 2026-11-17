# Taskly: Manager Role Test Report

- **Environment:** https://168-231-102-225.sslip.io/
- **Account:** manager@test.com (Test Manager, "Test Workspace")
- **Date tested:** 25 Sep 2026
- **Scope:** Every section in the Manager's nav. In each one: does the page load, and do view, create and edit work where offered. Delete actions were not tested.

## Summary

| Result | Count |
|---|---|
| Working | 12 |
| Partially Working | 2 |
| Broken | 6 |

**Verdict:** Not release-ready. 5 nav sections (Chat, Zoom Meetings, Google Meetings, Knowledge Base, AI) lead to a 403 dead end. Creating a contract, creating or editing a budget, and editing a bug all fail.

## Results by section

| # | Section | Status | Details |
|---|---|---|---|
| 1 | Team | Working | Member list and invite form load. **Send invite** not pressed (it would send a real email). |
| 2 | Projects | Partially Working | View, edit and project detail work. **Add Project → Create** fails: "Project limit reached… Free plan allows 3 projects, you currently have 3", but the Projects list shows only **1** project. The other 2 (Demo project, zz) show up in the Invoice project picker but not on the Projects page. |
| 3 | Tasks | Working | Create, edit and view (detail panel) all work. |
| 4 | Bugs | **Broken** (edit) | Report Bug (create) works. **Edit Bug → Update**: the dialog stays open with no message, and the console logs `Update error: Object` (BugModal.js). `PUT /bugs/7` returns 200, but the change isn't saved (description still empty after reopening). The edit dialog also renders half blank. |
| 5 | Calendar | Working | Month and week views and the event popup work. Minor: a task starting 25 Sep shows on Sat 26 Sep in week view (likely a timezone offset). |
| 6 | Timesheets (My / Weekly / Approvals) | Working | Create (with an entry), edit, weekly view and approvals list all work. Possible permissions issue: the Manager gets edit and delete on another member's **Approved** timesheet. |
| 7 | Contracts | **Broken** (create) | **Add Contract → Create** with no client selected gives a full-page **500 Internal Server Error**: `SQLSTATE[23000] … Column 'client_id' cannot be null`. The form labels Client as optional. The page shows the raw Laravel/SQL debug output, so debug mode appears to be on in production (security risk). Edit and view work. The Contract Type dropdown lists "Full Time" twice. |
| 8 | Chat | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 9 | Meetings (Zoom / Google) | **Broken** | Both links send you to `/plans`, which shows **403 Forbidden**. |
| 10 | Invoices | Working | Create (INV-2026-0004), view detail, and edit → Update all work. |
| 11 | Budgets | **Broken** (create + edit) | **Create Budget**: the Project dropdown is empty, so no budget can be created. **Edit → Update Budget** fails with "End date cannot be in the past", but the End Date field is read-only, so the budget can never be saved. The existing budget shows $9,300 spent of $90 (10333% utilization). |
| 12 | Expenses (+ Expense Approvals) | Working | Create, edit and the approvals page all work. |
| 13 | Taxes | Working | Create and edit work. |
| 14 | Knowledge Base | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 15 | Notes | Working | Create and edit work. |
| 16 | Forms | Working | New Form → builder, add a field and save fields all work. The table is wider than the page, so the **New Form** button is partly cut off. |
| 17 | AI | **Broken** | The nav link sends you to `/plans`, which shows **403 Forbidden**. |
| 18 | Project Reports | Working | List and detail (`/project-reports/2`) load. |
| 19 | Timesheet Reports | Working | Generate Report works. Minor: a project card shows Tasks 2/5 but Progress 0%. |
| 20 | Settings | Partially Working | Integrations, Roles and General → Contract Types load. The **Branding** tab is completely empty. **Billing → Tax Settings** shows "No results found", while the Taxes page lists 4 taxes. |

## Priority fixes

1. **Nav dead ends:** Chat, Meetings, Knowledge Base and AI either shouldn't appear in the Manager's nav or should be reachable. Right now they lead to a 403 on `/plans`.
2. **Contracts create returns a 500:** make `client_id` nullable or require the field in the form, and turn off `APP_DEBUG` in production.
3. **Bug edit doesn't save:** the client treats a 200 response as an error, and the update isn't saved.
4. **Budgets:** the project list for new budgets is empty, and a budget whose end date has passed can't be edited.
5. **Projects:** the plan-limit count doesn't match the projects the Manager can see.
6. **Settings:** the Branding tab is blank, and Billing's tax list doesn't match the Taxes page.

## Test data left in the workspace

QA Test Task · QA Test Bug · Timesheet for the week of 21 Sep 2026 (Draft, note "QA edit") · Invoice INV-2026-0004 · QA Test Expense ($1) · Tax "QA Tax edited" (5%) · Note "QA Test Note edited" · QA Test Form (1 field). Project "xx", contract "Back" and budget "xx" were opened for editing; no field was changed.
