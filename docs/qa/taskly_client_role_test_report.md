# Taskly: Client Role Test Report

- **Environment:** https://168-231-102-225.sslip.io/
- **Account:** client@test.com (Test Client, "Test Workspace")
- **Date tested:** 26 Sep 2026
- **Scope:** Every section in the Client's nav. In each one: does the page load, and do the main client actions work (view, and accept or decline where offered).

## Summary

| Result | Count |
|---|---|
| Working | 5 |
| Partially Working | 0 |
| Broken | 1 |

**Verdict:** Mostly usable. The Client can view projects, the calendar, invoices and reports, and can accept contracts. But the Meetings section (Zoom and Google) leads to a 403 dead end.

## Results by section

| # | Section | Status | Details |
|---|---|---|---|
| 1 | Projects | Working | List shows 2 projects (xx, zz), view only. Both detail pages (`/projects/2`, `/projects/5`) load. Minor: project zz shows **Team Members 0** on its detail page but **Total Members 1** in Project Reports. |
| 2 | Calendar | Working | Month view and the task event popup load. |
| 3 | Contracts | Working | List and detail (`/contracts/4`) load. **Accept** from the status dropdown (Pending → Accept) works: "Contract status updated successfully". The Attachments tab lists the file. Cosmetic: after accepting, the status badge reads "Accept" instead of "Accepted". |
| 4 | Meetings (Zoom / Google) | **Broken** | **Zoom Meetings** and **Google Meetings** both send you to `/plans`, which shows **403 Forbidden** ("You don't have permission to access this page"). |
| 5 | Invoices | Working | List (INV-2026-0002, Paid) and detail (`/invoices/2`) load. **Print** and **Download PDF** are there but weren't clicked (downloads not tested). No pay option (the invoice is already Paid). |
| 6 | Project Reports | Working | List and both detail pages (`/project-reports/5`, `/project-reports/2`) load. |

## Priority fixes

1. **Meetings dead end:** Zoom and Google Meetings in the Client nav lead to a 403 on `/plans`. Hide them or grant access.
2. **Minor:** the contract status label shows "Accept" rather than "Accepted", and project zz's member count differs between the project page and its report.

## Test data changed

Contract **"Back" (CON-2026-9198)** was **accepted** by the Client (status changed from Pending to Accept). No other records were changed.
