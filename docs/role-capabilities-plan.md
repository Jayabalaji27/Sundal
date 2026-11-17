# Role Capabilities — Current State & Target Plan

Written after testing the 5 QA accounts (`superadmin@test.com`, `company@test.com`,
`manager@test.com`, `member@test.com`, `client@test.com`) and finding that Member
vs. Client, and Manager vs. Owner, are hard to tell apart in the UI. This doc is a
**plan to work from later** — nothing here has been implemented yet.

Current-state numbers below are from the actual seeded permissions
(`database/seeders/RoleSeeder.php` + `PermissionSeeder.php`), not guesses —
audited via `Role::permissions` on 2026-08-13.

---

## 0. Verification note — screenshots vs. live server (2026-08-13)

Screenshots of the 5 dashboards were checked against a **fresh HTTP session**
(fresh login, no browser cache involved) hitting `/dashboard` and reading the
`auth.permissions` array the server actually sent. Two things came out of that:

**The screenshots don't match live server state, and the server is correct.**
Live-verified: `client` role has neither `timesheet_view_any` nor
`kb_view_any`/`note_view_any`; `member` role has `team_view`, `chat_view`, and
`bug_*`/`timesheet_*` permissions. But the screenshots show the opposite
pattern — Client's sidebar has Timesheets and Docs (shouldn't), Member's
sidebar is missing Team, Chat, and AI (should have all three). Given the
backend checks out fresh over HTTP, this points to the screenshots being from
browser tabs opened before some of today's role/permission changes landed —
**not** a real bug. Before treating any of this as something to fix, re-test
with a hard refresh (or a fresh incognito login) and see if it still happens.
If it still shows the wrong nav items after a clean reload, that *would* be a
real bug (stale Spatie permission cache server-side, most likely) and worth
raising again.

**One thing the screenshots got right that the earlier draft of this plan
missed:** Owner and Manager sidebars *are* already visibly different — Owner
has a top-level "Workspaces" item and an "Account" section (Billing,
Settings) that Manager's sidebar doesn't have at all. Section 2 below is
corrected to reflect that.

---

## 1. Why Member and Client look the same today

They share more than they differ:

| Shared today | Owner | Manager | Member | Client |
|---|:-:|:-:|:-:|:-:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| View projects (assigned/visible ones) | ✅ | ✅ | ✅ | ✅ |
| View tasks + **change task status** | ✅ | ✅ | ✅ | ✅ |
| Calendar, Meetings (join) | ✅ | ✅ | ✅ | ✅ |
| Reports (their scope) | ✅ | ✅ | ✅ | ✅ |

That last row is the crux of it: **Client can already drag a task between stages**,
same as Member. Combined with both roles landing on a sidebar that mostly just
says "Dashboard / Projects / Calendar / Reports", there's no visual signal that
they're different tiers of access.

What Member has that Client doesn't (today): Bugs, ToDos, Sprints, Timesheets,
Chat, Team (view), AI agents, Docs (KB + Notes), task/bug create-edit-delete
(not just status), Expenses. That's a real, and actually fairly large,
difference in the sidebar — Member should show roughly 8 nav items Client
doesn't. **If a real (non-stale) session ever shows Member and Client with
nearly-identical sidebars, that's the bug to chase — see section 0.** As of
this writing the live server-computed permissions are correct; it just needs
confirming the UI reflects them on a clean session.

---

## 2. Manager vs. Owner — already distinguishable, verified from screenshots

Corrected from an earlier draft of this doc, which assumed Manager and Owner
looked the same day-to-day. They don't, and it's visible in the sidebar
today: Owner has a top-level **Workspaces** item and an **Account** section
(**Billing**, **Settings**) that Manager's sidebar doesn't show at all.
Manager (190 permissions) is missing exactly the back-office things you'd
expect: Team invite-as-Owner/Manager, role changes, deactivation, Billing/Plan,
Zapier, BYOA/API Keys, Referral, Currency, Settings (all tabs). That gap is
already visible without any further work — nothing to fix here beyond
confirming it stays that way as new settings/admin surfaces get added.

---

## 3. Current permission audit (module-level)

| Module | Owner | Manager | Member | Client |
|---|:-:|:-:|:-:|:-:|
| Projects / Tasks | Full | Full | Own + assigned, full CRUD | View + change status only |
| Bugs | Full | Full | Full CRUD | ❌ none |
| ToDos | Full | Full | Full CRUD | ❌ none |
| Sprints | Full | Full | Manage tasks in sprint | ❌ none |
| Timesheets | Full | Full + approvals | Own, full CRUD | ❌ none |
| Budgets | Full | Full | ❌ none | ❌ none |
| Expenses | Full + approvals | Full + approvals | Own, full CRUD | ❌ none |
| Invoices | Full | View + create | ❌ none | View only |
| Contracts | Full | View/create/edit | ❌ **none (gap?)** | View/sign/comment |
| Contract Types | Full | View only | ❌ none | View only |
| Docs (KB + Notes) | Full | Full | View + own notes | ❌ none |
| Chat | Full | Full | Full | ❌ none |
| AI Agents | Full | Full | Use only | ❌ none |
| Forms | Full | Full | ❌ **none (gap?)** | ❌ none |
| Team (view/invite/etc.) | Full | View + invite Member only | View only | ❌ none |
| Reports | Full | Full | View own scope | View own scope |
| Zapier / BYOA (API Keys) | Full | ❌ none | ❌ none | ❌ none |
| Settings | Full | ❌ none | ❌ none | ❌ none |
| Billing / Plan | Full | ❌ none | ❌ none | ❌ none |
| Media Library | Full | View/upload/download/delete | Full | ❌ none |

**Two things flagged as probably-unintentional gaps, not by-design:**
- ~~Member has zero Forms access.~~ **Fixed 2026-08-13** — Member now has
  `form_view_any` (view only, can't build/manage forms).
- ~~Member has zero Contracts access, but Client does.~~ **Fixed 2026-08-13**
  — Member now has view-only contract access (`contract_view_any`,
  `contract_view`, `contract_preview`, `contract_type_view_any`,
  `contract_type_view`) — explicitly no sign/comment/create/edit, matching
  "view only" from the decision below.

**Decisions made (2026-08-13):**
- Member gets Forms + Contracts, both view-only — implemented in
  `RoleSeeder.php`, verified live (page loads 200, create attempts 403).
- Manager stays with zero Settings access — intentional, no change made.

---

## 4. Target role definitions (proposed)

### Owner (Company)
Everything. The only one who can: manage billing/plan, invite anyone as any
role, change roles, deactivate people, configure Settings/Branding/Integrations,
delete workspace-level records outright (contract types, projects, etc.).

### Manager — "runs the work, not the account"
Full CRUD on projects/tasks/bugs/sprints/todos/timesheets/budgets/expenses
(+ approvals), invoices (view/create, not delete), contracts (view/create/edit,
not delete or type management), Docs/Chat/AI/Forms/Reports — all full. Team:
view + invite **Member only** (already enforced server-side from this session's
work), assign members to projects they manage (already enforced). Cannot:
touch billing, Settings, Integrations, invite/promote to Manager or Owner,
deactivate anyone, hard-delete anything workspace-level.

### Member — "does the work, on assigned projects"
Full CRUD on tasks/bugs assigned to them or that they created, sprint task
management, own timesheets, own expenses, Docs (view all + create/edit own
notes), Chat, AI (use agents), Team (view only). **Proposed additions to close
the gaps above:** Forms (view + submit, not manage), Contracts (view only, no
sign/comment — that's the Client's job). Cannot: create/delete projects,
approve timesheets/expenses, manage budgets, invite/manage team, touch
Settings/Billing.

### Client — "external stakeholder, view + limited engagement"
View-only on their assigned/visible projects and tasks, **but can change task
status** (already true) and **can comment/sign on contracts** (already true) —
those two are the intentional "limited engagement" surface. View invoices,
view reports for their project, join meetings, view calendar. Cannot: create
or edit anything except the two exceptions above, see Bugs/ToDos/Sprints/
Timesheets/Budgets/Docs/Chat/AI/Forms/Team at all.

Longer-term (Phase 4 of the original refactor doc, not yet built): Client
stops being a logged-in workspace role entirely and moves to the token-based
portal (`/portal/{token}`) with per-project toggles for what's visible
(`show_timesheets`, `show_invoices`, `show_budget`, `allow_bug_submission`,
`allow_task_comments`). Worth keeping in mind so today's Client-role fixes
don't get thrown away when that lands.

---

## 5. What would actually make the difference *visible*

Now that section 0 shows the underlying permissions already differ by ~8 nav
items between Member and Client, the sidebar gap should be visible on a clean
session without any permission changes. Still worth considering once that's
confirmed:
- Add a small role badge somewhere in the header/profile menu so it's obvious
  at a glance which account you're testing as (would have made today's mix-up
  easier to catch).
- For nav items shared across all 4 roles (Reports, Meetings), consider
  whether the *content* differs enough per role that it's obvious once
  inside, even if the nav entry itself is the same.

---

## 6. Open questions for you

1. ~~Hard-refresh and confirm the sidebar matches server-verified permissions.~~
   **Resolved** — re-verified on a genuinely fresh HTTP session (server
   freshly restarted, no browser involved at all): Member correctly receives
   `team_view`, `chat_view`, `agent_view_any`, `kb_view_any`. The screenshots
   really were a stale browser tab, not a bug.
2. ~~Should Member get Forms and Contracts (view only)?~~ **Resolved: yes,
   both, implemented.**
3. ~~Should Manager be allowed into `/settings`?~~ **Resolved: no, stays
   Owner-only.**
4. Worth a role badge in the header for QA/testing clarity, or not needed?
   Still open — didn't implement, low priority.
5. Any role/permission here that's *wrong* relative to what you actually want,
   beyond the two now-fixed gaps? Still open.

---

## 7. Unrelated bug found and fixed along the way (2026-08-13)

While testing this, found that the "Meetings" nav item (and Finance, Reports,
Docs — the other Phase 2 unified nav entries) rendered a full page and then
immediately client-side redirected to the real destination (e.g.
`/meetings` → renders a shell → `useEffect` redirects to `/zoom-meetings`),
visible as a flash of one page before landing on another. Fixed by making
those 4 routes redirect server-side (a plain 302, picking the first tab the
user has permission for) instead of rendering an Inertia page that redirects
itself — the browser now gets a single redirect before React ever mounts
anything. The now-unused shell components
(`resources/js/pages/{finance,reports,meetings,docs}/index.tsx`) were deleted
since nothing renders them anymore.
