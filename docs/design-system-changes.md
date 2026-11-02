# New design system: what changed

**In one line:** Sundal now has one shared set of colours, text styles, borders and shadows (the "design tokens"). The basic building blocks (buttons, inputs, cards, dialogs and so on) and about 50 pages were switched over to it, so the whole app looks the same everywhere and follows the workspace's brand colour.

## Status

- Branch `feature/modern-ui`, edited on 25 Oct 2026.
- **Not committed yet:** 121 changed files and 8 new files, all uncommitted in the project folder.
- **Not in the production build** (`public/build`) or in `sundal-reports-uploads-import-update.zip`. You only see it while the dev server (`npm run dev`) is running.

## 1. The foundation: two new files

| File | What it does |
|---|---|
| `resources/css/design-system/tokens.css` | The colour list. Each colour has a job: page background, card surface, main text, muted text, borders, success, warning, danger, info. Light and dark versions are both here. The brand shades (50 to 800) are worked out from the workspace's brand colour, so hover and selected states always match it. |
| `resources/css/design-system/theme.css` | Turns those tokens into Tailwind classes such as `bg-ds-surface`, `text-ds-fg-muted`, `border-ds-border` and `bg-brand-600`. Adds 3 shadows (`shadow-ds-xs`, `-sm`, `-md`) and 5 text sizes (page title, section title, card title, body, meta). Also retunes Tailwind's own grey, red, amber and green colours so older pages that weren't changed still match. |

`app.css` now loads these two files. The standard shadcn/ui variables every component reads (`--background`, `--primary`, `--border` and so on) now point at the tokens instead of fixed values.

## 2. What people will notice

- **Font:** Inter instead of Instrument Sans.
- **Default brand colour:** indigo `#4f46e5` instead of green `#10b981`.
  - Workspaces that already saved a brand colour keep it. The demo workspace is still green.
  - The **"Purple"** preset is now `#4f46e5` (it was `#8b5cf6`).
  - The PDF report colours follow the same new default.
- **Page background:** light grey (`#f8fafc`), with white cards on top.
- **Cards:** rounder corners (12px), a thin border and a lighter shadow.
- **Dialogs:** rounder corners (16px), 480px wide by default, a softer dark backdrop, and a bigger close button.
- **Inputs:**
  - The border darkens on hover, and a soft brand-coloured ring appears on focus.
  - They show a clear disabled look and a red error state.
  - Text is 16px on phones, so iPhones don't zoom in.
- **Buttons:**
  - Labels are bold.
  - A new **"danger"** style (outlined red) is for everyday delete actions; solid red is only for the final confirmation.
  - Buttons can show a loading spinner.
- **Badges:** softer, tinted tags.
- **Scrollbars:** thin, round and quiet.
- **Selected text:** highlighted in a light brand tint.
- **Sidebar default style:** "sidebar" instead of "inset", only where no style was saved.
- **Dark mode:** uses the same tokens, so it stays consistent with light mode.
- **Reduced motion:** animations are turned off for people who ask their system for less motion.

## 3. New reusable pieces

| Component | Use it for | Used in |
|---|---|---|
| `StatusBadge` | Workflow states (Active, Pending, Failed). Always shows a dot or icon, so the state never depends on colour alone. | Projects, Tasks, Invoices, Team, Weekly timesheet, dashboard, project cards |
| `RoleBadge` | Owner / Manager / Member / Client tags, each with its own icon. | Team page, profile menu |
| `ConfirmDialog` | "Are you sure?" steps for delete, deactivate, remove access. Red confirm button; Escape cancels. | AI assistant, API keys, form submissions, knowledge base article, Team, Zapier |
| `SearchInput` | Search box with an icon and a clear (×) button. | Projects, Tasks, Invoices, Team |
| `FormField` | A label, hint and error message wired to one input. | **Not used anywhere yet** |
| `avatarTone()` | Gives each person a fixed soft colour for their initials avatar. | Avatar component, project cards |

## 4. Existing code switched to the tokens

- **27 base UI components** in `components/ui`, including button, input, textarea, select, checkbox, switch, card, dialog, sheet, popover, dropdown menu, tooltip, tabs, table, badge, avatar, alert, pagination, progress, skeleton, toast and sidebar.
- **32 other components:** sidebar and header, page template, tables (`CrudTable`), empty state, delete dialogs, flash messages, filters, notifications, profile and workspace menus, the kanban board, timesheet parts, and a few auth and settings pieces.
- **50 pages across about 30 areas:** most in Settings, Auth, Timesheets and Invoices, and one or two each in Projects, Tasks, Contracts, Team and others. The change is mostly the same everywhere: fixed colours such as `text-gray-500`, `border-gray-300` and `text-red-700` were replaced by the matching tokens (`text-ds-fg-muted`, `border-ds-border`, `text-ds-danger-fg` ...).
- **Layouts and other front-end files:** app sidebar layout, auth layout, appearance hook, sidebar context, `lib/utils.ts`, and `app.blade.php` (the Inter font link, and a background colour so the page doesn't flash white before it loads).
- **Backend, 4 files, defaults only:** `helper.php`, `SystemSettingsController.php`, `ProjectReportController.php` and `ReportRenderer.php` now default to indigo and the "sidebar" style instead of green and "inset".

## 5. How to use it in new code

```
Page background   bg-ds-canvas        Card / panel      bg-ds-surface border-ds-border rounded-xl shadow-ds-xs
Main text         text-ds-fg          Secondary text    text-ds-fg-secondary      Muted text   text-ds-fg-muted
Brand colour      bg-brand-600        Soft brand tint   bg-brand-soft text-brand-soft-fg
Success / warning / danger / info     text-ds-success-fg  bg-ds-warning-bg  text-ds-danger-fg  bg-ds-info-bg
Page title        text-ds-page-title  Section title     text-ds-section-title     Small meta   text-ds-meta
Corners           rounded-md buttons/inputs · rounded-xl cards · rounded-2xl dialogs · rounded-full avatars
```

Use `StatusBadge` for states, `ConfirmDialog` for destructive confirmations and `Button variant="danger"` for delete buttons. Avoid fixed colours like `text-gray-500` in new code.

## 6. Before shipping

1. **Commit and push** `feature/modern-ui`. Right now the work exists only in this folder.
2. **Rebuild** with `npm run build`, so `public/build` includes it.
3. **Decide on the colour defaults:**
   - Workspaces with no saved brand colour turn from green to indigo.
   - Anyone who picked "Purple" gets the new indigo shade.
4. **Check the pages that weren't converted.** They rely on the retuned Tailwind colours, so look them over in light and dark mode.
5. **`FormField` is unused.** Either start using it in forms or drop it.
