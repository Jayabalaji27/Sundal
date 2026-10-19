# Swatle PM — Custom Modules & Features

> Base: Taskly SaaS (CodeCanyon) — significantly extended with new modules and enhancements.

---

## Custom Modules (Built on top of base Taskly)

### 1. Chat
**Purpose:** Real-time team messaging between workspace members  
**Files:**
- `app/Http/Controllers/ChatController.php`
- `app/Models/ChatConversation.php`, `ChatMessage.php`
- `resources/js/pages/chat/index.tsx`

**Routes:**
```
GET  /chat                          → chat.index
POST /chat/conversations            → chat.store
GET  /chat/conversations/{id}/messages → chat.messages
POST /chat/conversations/{id}/messages → chat.send
```

---

### 2. AI Agents / Chatbot
**Purpose:** AI assistant powered by OpenAI + Knowledge Base, configurable per agent  
**Files:**
- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/ChatbotController.php`
- `app/Models/Agent.php`
- `resources/js/pages/agents/Index.tsx`, `agents/Chat.tsx`
- `resources/js/pages/chatbot/Index.tsx`

**Routes:**
```
GET    /agents              → agents.index
POST   /agents              → agents.store
PUT    /agents/{agent}      → agents.update
DELETE /agents/{agent}      → agents.destroy
GET    /agents/{agent}/chat → agents.chat
POST   /agents/{agent}/ask  → agents.ask
GET    /chatbot             → chatbot.index
POST   /chatbot/ask         → chatbot.ask
```

---

### 3. Knowledge Base
**Purpose:** Internal documentation — articles, categories, file attachments  
**Files:**
- `app/Http/Controllers/KnowledgeBaseController.php`
- `app/Models/KbArticle.php`, `KbCategory.php`, `KbAttachment.php`
- `resources/js/pages/knowledge-base/Index.tsx`, `knowledge-base/Article.tsx`

**Routes:**
```
GET    /knowledge-base                           → kb.index
POST   /knowledge-base/categories               → kb.categories.store
PUT    /knowledge-base/categories/{id}           → kb.categories.update
DELETE /knowledge-base/categories/{id}           → kb.categories.destroy
GET    /knowledge-base/articles/{id}             → kb.articles.show
POST   /knowledge-base/articles                  → kb.articles.store
PUT    /knowledge-base/articles/{id}             → kb.articles.update
DELETE /knowledge-base/articles/{id}             → kb.articles.destroy
POST   /knowledge-base/articles/{id}/attachments → kb.attachments.upload
GET    /knowledge-base/attachments/{id}/download → kb.attachments.download
DELETE /knowledge-base/attachments/{id}          → kb.attachments.destroy
```

---

### 4. Form Builder
**Purpose:** Drag-and-drop form creator with public submission pages and thank-you flows  
**Files:**
- `app/Http/Controllers/FormBuilderController.php`
- `app/Http/Controllers/PublicFormController.php`
- `app/Models/Form.php`, `FormField.php`, `FormSubmission.php`
- `resources/js/pages/forms/Index.tsx`, `forms/Builder.tsx`, `forms/Public.tsx`
- `resources/js/pages/forms/Submissions.tsx`, `forms/ThankYou.tsx`, `forms/Closed.tsx`

**Routes:**
```
GET    /forms                          → forms.index
POST   /forms                          → forms.store
GET    /forms/{form}/builder           → forms.builder
PUT    /forms/{form}                   → forms.update
POST   /forms/{form}/fields            → forms.saveFields
DELETE /forms/{form}                   → forms.destroy
GET    /forms/{form}/submissions       → forms.submissions
DELETE /forms/{form}/submissions/{id}  → forms.submissions.destroy

# Public (no auth)
GET    /f/{token}  → forms.public
POST   /f/{token}  → forms.submit
```

---

### 5. Sprints, Backlog and Board
**Purpose:** Agile planning inside a project, Jira/Zoho style. Every project page shares one header
with tabs (`components/projects/project-shell.tsx`): Summary · **Backlog** · **Sprints** · **Board** · Timeline ·
Bugs · Milestones · More — the agile flow left to right (what we could do → what we committed to → doing it).
The **Backlog** (projects.backlog) holds only unplanned work, most urgent first, with the open sprints beside
it as drop targets. **Sprints** (sprints.index) lists the active, planned and completed sprints; managers
create, start and complete them there, and tasks can move between sprints or back to the backlog. The
**Board** shows the active sprint's tasks by stage. One sprint per project is active
at a time; a task sits in one open sprint at a time. Completing a sprint moves its unfinished tasks to the
backlog, a planned sprint or a new sprint, and freezes its committed/completed totals (sprint report and
velocity). Tasks carry optional **story points** (`tasks.story_points`).

**Who does what:** `sprint_create` (Owner, Manager) creates; `sprint_update` edits, starts and completes;
`sprint_delete` deletes; `sprint_manage_tasks` (also Members) moves tasks. Clients have no Backlog/Board.

**Files:**
- `app/Http/Controllers/SprintController.php`, `app/Http/Controllers/ProjectBoardController.php`
- `app/Traits/PresentsAgileBoard.php` (task card / sprint summary shapes), `app/Actions/Sprints/AddTasksToSprint.php`
- `app/Models/Sprint.php`
- `resources/js/pages/projects/Backlog.tsx`, `sprints/Index.tsx` (Sprints), `sprints/Show.tsx` (sprint report), `projects/Board.tsx`
- `resources/js/components/sprints/` (sprint form, movable task row)
- `resources/js/components/tasks/kanban-board.tsx`, `agile-bits.tsx`, `use-task-detail.tsx`

**Routes:**
```
GET    /projects/{project}/backlog → projects.backlog  (sprint_view_any)  Backlog (unplanned work)
GET    /projects/{project}/sprints → sprints.index     (sprint_view_any)  Sprints
POST   /projects/{project}/sprints → sprints.store     (sprint_create)
GET    /projects/{project}/board   → projects.board    (task_view_any)    Board
GET    /sprints/{sprint}           → sprints.show      (sprint_view)      Sprint report
PUT    /sprints/{sprint}           → sprints.update    (sprint_update)
DELETE /sprints/{sprint}           → sprints.destroy   (sprint_delete)
POST   /sprints/{sprint}/tasks     → sprints.add-task  (sprint_manage_tasks)
DELETE /sprints/{sprint}/tasks     → sprints.remove-task (sprint_manage_tasks)
POST   /sprints/{sprint}/start     → sprints.start     (sprint_update)
POST   /sprints/{sprint}/complete  → sprints.complete  (sprint_update; move_to = backlog | new | sprint id)
```

---

### 6. Client Portal
**Purpose:** Token-based public project view — clients access project status without logging in  
**Files:**
- `app/Http/Controllers/ClientPortalController.php`
- `resources/js/pages/portal/Show.tsx`

**Routes:**
```
# Public (no auth)
GET  /portal/{token}      → portal.show
POST /portal/{token}/bug  → portal.submit-bug

# Admin controls (auth required)
POST /projects/{project}/portal/toggle      → portal.toggle
POST /projects/{project}/portal/regenerate  → portal.regenerate
```

---

## Additional Features (Smaller additions)

### Portfolios
**Purpose:** Public project showcase pages  
- `app/Http/Controllers/PortfolioController.php`
- `app/Models/Portfolio.php`
- `resources/js/pages/portfolios/Index.tsx`, `portfolios/Show.tsx`

### API Keys (BYOA)
**Purpose:** Users can manage their own API keys for integrations  
- `app/Http/Controllers/ApiKeyController.php`
- `app/Models/ApiKey.php`
- `resources/js/pages/api-keys/Index.tsx`

### Zapier Webhooks
**Purpose:** Outbound webhook triggers for Zapier automation  
- `app/Http/Controllers/ZapierController.php`
- `app/Models/ZapierHook.php`
- `resources/js/pages/zapier/Index.tsx`

### New Payment Gateways
- **PaymentWall** — `app/Http/Controllers/PaymentWallPaymentController.php`
- **SSPay** — `app/Http/Controllers/SSPayPaymentController.php`

### Slack & Telegram Settings (Custom)
- `app/Http/Controllers/SlackSettingsController.php`
- `app/Http/Controllers/TelegramSettingsController.php`
- `app/Models/SlackSetting.php`, `TelegramSetting.php`

---

## Sidebar Navigation Structure

```
Overview
├── Dashboard
└── Calendar

Project Management
├── Workspaces
├── Portfolios          ← Custom
├── Projects
├── Forms               ← Custom
├── Tasks
├── Bugs
├── ToDos
└── Project Reports

Time Tracking
└── Timesheets

Finance
├── Invoices
└── Budget & Expenses

Communications & Content
├── Notes
├── Zoom Meetings
├── Google Meetings
├── Contracts
├── Chat                ← Custom
├── Integrations        ← Custom
│   ├── Knowledge Base
│   ├── Agents
│   ├── BYOA (API Keys)
│   └── Zapier
└── Media Library
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.5 |
| Frontend | React + Inertia.js + TypeScript |
| UI | Tailwind CSS + shadcn/ui |
| DB | MySQL |
| Build | Vite |
| Auth | Spatie Permission |
| Payments | 30+ gateways |
| AI | OpenAI (via openai-php/client) |
| Storage | Local / AWS S3 / Wasabi |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
