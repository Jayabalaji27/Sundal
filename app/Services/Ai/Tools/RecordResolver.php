<?php

namespace App\Services\Ai\Tools;

use App\Models\BudgetCategory;
use App\Models\Bug;
use App\Models\BugStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns the names the model passes ("Website Redesign", "Ravi", "login bug")
 * into records the user may see. Every lookup is limited to the user's
 * current workspace and to projects they can see (Project::scopeVisibleTo).
 *
 * One match is returned. None or several throw a ToolInputException whose
 * message tells the model to ask the user, never to guess.
 */
class RecordResolver
{
    private const MAX_LISTED = 5;

    public function projects(User $user): Builder
    {
        return Project::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->visibleTo($user);
    }

    public function tasks(User $user): Builder
    {
        return Task::query()->whereIn('project_id', $this->projects($user)->select('id'));
    }

    public function bugs(User $user): Builder
    {
        return Bug::query()->whereIn('project_id', $this->projects($user)->select('id'));
    }

    public function project(User $user, string $ref): Project
    {
        return $this->one($this->projects($user), $ref, 'title', __('project'));
    }

    public function task(User $user, string $ref, ?string $projectRef = null): Task
    {
        $query = $this->tasks($user);
        if ($projectRef) {
            $query->where('project_id', $this->project($user, $projectRef)->id);
        }

        return $this->one($query, $ref, 'title', __('task'));
    }

    public function bug(User $user, string $ref, ?string $projectRef = null): Bug
    {
        $query = $this->bugs($user);
        if ($projectRef) {
            $query->where('project_id', $this->project($user, $projectRef)->id);
        }

        return $this->one($query, $ref, 'title', __('bug'));
    }

    /** Workspace owner plus active members. "me" is the acting user. */
    public function members(User $user): Builder
    {
        $workspaceId = $user->current_workspace_id;
        $ownerId = Workspace::whereKey($workspaceId)->value('owner_id');

        return User::query()->where(function ($q) use ($workspaceId, $ownerId) {
            $q->whereIn('id', WorkspaceMember::where('workspace_id', $workspaceId)->where('status', 'active')->select('user_id'))
                ->orWhere('id', $ownerId);
        });
    }

    public function member(User $user, string $ref): User
    {
        $ref = trim($ref);
        if (in_array(strtolower($ref), ['me', 'myself', 'i'], true)) {
            return $user;
        }

        $query = $this->members($user);
        if (filter_var($ref, FILTER_VALIDATE_EMAIL)) {
            $match = (clone $query)->where('email', $ref)->first();
            if ($match) {
                return $match;
            }
        }

        return $this->one($query, $ref, 'name', __('person'));
    }

    // ── Finance and time: the lists the screens allow this user to change ────

    public function draftInvoices(User $user): Builder
    {
        return ListInvoices::visible($user)->where('status', 'draft');
    }

    /** Sent, viewed, partly paid or overdue: what can be marked as paid. */
    public function unpaidInvoices(User $user): Builder
    {
        return ListInvoices::visible($user)->whereIn('status', Invoice::OVERDUE_ELIGIBLE_STATUSES);
    }

    /** Expenses not yet approved (approved ones count against the budget). */
    public function openExpenses(User $user): Builder
    {
        return ProjectExpense::visibleTo($user)->whereIn('project_expenses.status', ['pending', 'requires_info']);
    }

    public function budgets(User $user): Builder
    {
        return ProjectBudget::query()->whereIn('project_id', $this->projects($user)->select('id'));
    }

    public function budgetCategories(User $user, ?int $projectId = null): Builder
    {
        return BudgetCategory::whereHas('projectBudget', fn ($q) => $q
            ->whereIn('project_id', $this->projects($user)->select('id'))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId)));
    }

    /** The user's own time entries on timesheets they can still change. */
    public function openTimeEntries(User $user): Builder
    {
        return TimesheetEntry::where('user_id', $user->id)->whereHas('timesheet', fn ($q) => $q
            ->where('workspace_id', $user->current_workspace_id)
            ->whereNotIn('status', ['submitted', 'approved']));
    }

    /** The user's own draft or rejected timesheets that have entries: what can be submitted. */
    public function openTimesheets(User $user): Builder
    {
        return Timesheet::where('user_id', $user->id)
            ->where('workspace_id', $user->current_workspace_id)
            ->whereIn('status', ['draft', 'rejected'])
            ->whereHas('entries');
    }

    /** Tasks not already on a sent or paid invoice (BUG-14). */
    public function billableTasks(User $user, ?int $projectId = null): Builder
    {
        return $this->tasks($user)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotIn('id', InvoiceItem::query()->whereNotNull('task_id')
                ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled']))
                ->select('task_id'));
    }

    /**
     * One record from $query by id ("#12") or by $column, with the same
     * rules and messages as project() or task().
     */
    public function find(Builder $query, string $ref, string $column, string $kind): Model
    {
        return $this->one($query, $ref, $column, $kind);
    }

    /** "#3;#5" or "Login page; Signup" → each resolved in $query. @return Collection<int, Model> */
    public function many(Builder $query, string $refs, string $column, string $kind): Collection
    {
        $found = new Collection();
        foreach (array_filter(array_map('trim', explode(';', $refs))) as $ref) {
            $found->push($this->one(clone $query, $ref, $column, $kind));
        }

        return $found->unique(fn (Model $m) => $m->getKey())->values();
    }

    /** A number above 0 (and at most $max), or null when empty. */
    public function number(mixed $value, string $label, ?float $max = null): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $number = str_replace([',', ' '], '', (string) $value);
        if (!is_numeric($number) || (float) $number <= 0 || ($max !== null && (float) $number > $max)) {
            throw new ToolInputException($max !== null
                ? __(':label must be a number above 0 and at most :max.', ['label' => $label, 'max' => $max + 0])
                : __(':label must be a number above 0.', ['label' => $label]));
        }

        return round((float) $number, 2);
    }

    public function taskStage(User $user, string $ref): TaskStage
    {
        return $this->one(TaskStage::forWorkspace($user->current_workspace_id), $ref, 'name', __('task stage'));
    }

    public function bugStatus(User $user, string $ref): BugStatus
    {
        return $this->one(BugStatus::forWorkspace($user->current_workspace_id), $ref, 'name', __('bug status'));
    }

    /** A YYYY-MM-DD date, or null when empty. */
    public function date(?string $value, string $label): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', trim($value));
        } catch (\Throwable) {
            $date = false;
        }

        if (!$date || $date->format('Y-m-d') !== trim($value)) {
            throw new ToolInputException(__(':label must be a date in YYYY-MM-DD format.', ['label' => $label]));
        }

        return $date->format('Y-m-d');
    }

    /**
     * Re-load a record by id when a confirmed card runs. Fails if it was
     * deleted or is no longer visible to the user since the card was shown.
     */
    public function byId(Builder $query, int $id, string $kind): Model
    {
        return (clone $query)->whereKey($id)->first()
            ?? throw new ToolInputException(__('The :kind on this card no longer exists or is no longer visible to you.', ['kind' => $kind]));
    }

    /**
     * Find one record by id ("#12" or "12") or by name: an exact
     * (case-insensitive) match wins over a partial one.
     */
    /**
     * Every record a reference could mean, using the same rules as one():
     * an id ("#12" or "12") wins, then exact (case-insensitive) names, then
     * partial names. At most MAX_LISTED + 1 are returned.
     *
     * @return Collection<int, Model>
     */
    public function candidates(Builder $query, string $ref, string $column): Collection
    {
        $ref = trim($ref);
        if ($ref === '') {
            return new Collection();
        }

        if (preg_match('/^#?(\d+)$/', $ref, $m)) {
            $byId = (clone $query)->whereKey((int) $m[1])->first();
            if ($byId) {
                return new Collection([$byId]);
            }
        }

        $exact = (clone $query)->whereRaw("LOWER({$column}) = ?", [mb_strtolower($ref)])->limit(self::MAX_LISTED + 1)->get();
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return (clone $query)->where($column, 'like', '%' . addcslashes($ref, '%_\\') . '%')->limit(self::MAX_LISTED + 1)->get();
    }

    private function one(Builder $query, string $ref, string $column, string $kind): Model
    {
        $ref = trim($ref);
        if ($ref === '') {
            throw new ToolInputException(__('Which :kind? Ask the user.', ['kind' => $kind]));
        }

        $matches = $this->candidates($query, $ref, $column);

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw new ToolInputException(__('No :kind matches ":ref" that this user can see. Ask the user to check the name.', ['kind' => $kind, 'ref' => $ref]));
        }

        throw new ToolInputException(__('Several :kind records match ":ref": :list. Ask the user which one; do not guess.', [
            'kind' => $kind,
            'ref' => $ref,
            'list' => $this->describe($matches, $column),
        ]));
    }

    private function describe(Collection $matches, string $column): string
    {
        $list = $matches->take(self::MAX_LISTED)
            ->map(fn (Model $m) => "{$m->{$column}} (#{$m->getKey()})")
            ->implode('; ');

        return $matches->count() > self::MAX_LISTED ? $list . '; …' : $list;
    }
}
