import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import TaskModal from './TaskModal';
import TaskFormModal from '@/components/tasks/TaskFormModal';
import TaskPriority from '@/components/tasks/TaskPriority';
import TaskStageChanger from '@/components/tasks/TaskStageChanger';
import { Button, IconButton } from '@/components/ui/button';
import { SearchInput } from '@/components/ui/search-input';
import { StatusBadge } from '@/components/ui/status-badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/EmptyState';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, MoreHorizontal, Eye, Edit, Copy, Trash2, LayoutGrid, List, User as UserIcon, CheckSquare, Columns, AlertTriangle } from 'lucide-react';
import { PageTemplate, type PageAction } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Task, Project, TaskStage, User, PaginatedData } from '@/types';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { consumeCreateIntent } from '@/utils/create-intent';
import { AgileTask, SEVERITY_OPTIONS, SeverityBadge, TypeIcon } from '@/components/tasks/agile-bits';
import { KanbanBoard } from '@/components/tasks/kanban-board';
import { BOARD_VIEWS, BoardView, BoardViewMenu } from '@/components/tasks/board-view-menu';
import { hasPermission } from '@/utils/authorization';
import { ArrowDown, ArrowUp, ChevronsUpDown, ChevronDown, FileDown, FileUp } from 'lucide-react';
import { ImportModal } from '@/components/ImportModal';
import { cn } from '@/lib/utils';

/** Remembers the view picked here, so the menu link opens on it (read by TaskController). */
const VIEW_COOKIE = 'sundal_tasks_view';

declare global {
    interface Window {
        searchTimeout: any;
    }
}

interface Props {
    /** The Sheet view's page of rows; null in the Detail and Plain views. */
    tasks: PaginatedData<Task> | null;
    /** The Detail and Plain views' cards (at most `limit` of `total`); null in the Sheet view. */
    board?: { cards: AgileTask[]; total: number; limit: number } | null;
    projects: Project[];
    stages: TaskStage[];
    members: User[];
    filters: {
        project_id?: string;
        stage_id?: string;
        priority?: string;
        assigned_to?: string;
        /** 'task' or 'bug'; bugs are tasks of type bug. */
        type?: string;
        severity?: string;
        search?: string;
        view?: string;
        sort_field?: string;
        sort_direction?: string;
    };
    project_name?: string;
    userWorkspaceRole?: string;
    permissions?: any;
}

export default function TasksIndex({ tasks, board = null, projects, stages, members, filters, project_name, userWorkspaceRole, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, permissions: pagePermissions, auth } = usePage().props as any;
    const canMove = hasPermission(auth?.permissions || [], 'task_change_status');
    const taskPermissions = permissions || pagePermissions;
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedProject, setSelectedProject] = useState(filters.project_id || 'all');
    const [selectedStage, setSelectedStage] = useState(filters.stage_id || 'all');
    const [selectedPriority, setSelectedPriority] = useState(filters.priority || 'all');
    const [selectedAssignee, setSelectedAssignee] = useState(filters.assigned_to || 'all');
    const [selectedType, setSelectedType] = useState(filters.type || 'all');
    const [selectedSeverity, setSelectedSeverity] = useState(filters.severity || 'all');
    const showingBugs = selectedType === 'bug';
    const [showFilters, setShowFilters] = useState(false);
    const [selectedTask, setSelectedTask] = useState<Task | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isImportOpen, setIsImportOpen] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const [editingTask, setEditingTask] = useState<Task | null>(null);
    // The Board's three views: Detail and Plain cards by stage, or the Sheet (this table).
    const view: BoardView = BOARD_VIEWS.includes(filters.view as BoardView) ? (filters.view as BoardView) : 'sheet';
    const chooseView = (next: BoardView) => {
        document.cookie = `${VIEW_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;
        reload({ view: next });
    };
    // Cards move at once; the server's answer (or a refusal) resets them from props.
    const [cards, setCards] = useState<AgileTask[]>(board?.cards || []);
    useEffect(() => setCards(board?.cards || []), [board]);
    const changeStage = (taskId: number, stageId: number) =>
        router.put(route('tasks.change-stage', taskId), { task_stage_id: stageId }, { preserveScroll: true, preserveState: true });
    const moveCard = (taskId: number, stageId: number) => {
        const stage = stages.find(item => item.id === stageId);
        setCards(prev => prev.map(card => card.id === taskId
            ? { ...card, task_stage_id: stageId, stage: stage ? { id: stage.id, name: stage.name, color: stage.color } : card.stage, is_completed: !!stage?.is_completed }
            : card));
        changeStage(taskId, stageId);
    };
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [taskToDelete, setTaskToDelete] = useState<Task | null>(null);

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    /** The list's filters as query params, with changes applied ('all' or empty drops one). */
    const listParams = (changes: Record<string, any> = {}) => {
        const values: Record<string, any> = {
            search: searchTerm,
            project_id: selectedProject,
            stage_id: selectedStage,
            priority: selectedPriority,
            assigned_to: selectedAssignee,
            type: selectedType,
            severity: selectedSeverity,
            sort_field: filters.sort_field,
            sort_direction: filters.sort_direction,
            view,
            ...changes,
        };
        // Severity only narrows bugs.
        if (values.type !== 'bug') values.severity = 'all';
        const params: any = { page: 1 };
        Object.entries(values).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '' && value !== 'all') params[key] = value;
        });
        if (project_name) params.project_name = project_name;
        return params;
    };

    const reload = (changes: Record<string, any> = {}) =>
        router.get(route('tasks.index'), listParams(changes), { preserveState: true, preserveScroll: true });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        reload();
    };

    const handleFilter = (key: string, value: string) => {
        if (key === 'project_id') setSelectedProject(value);
        if (key === 'stage_id') setSelectedStage(value);
        if (key === 'priority') setSelectedPriority(value);
        if (key === 'assigned_to') setSelectedAssignee(value);
        if (key === 'type') setSelectedType(value);
        if (key === 'severity') setSelectedSeverity(value);
        if (key === 'type' && value !== 'bug') setSelectedSeverity('all');
        reload({ [key]: value });
    };

    const handleAction = (action: string, taskId: number) => {
        switch (action) {
            case 'view':
                handleViewTask(taskId);
                break;
            case 'edit':
                handleEditTask(taskId);
                break;
            case 'duplicate':
                toast.loading('Duplicating task...');
                router.post(route('tasks.duplicate', taskId), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error('Failed to duplicate task');
                    }
                });
                break;
            case 'delete':
                const task = (tasks?.data || []).find((item: Task) => item.id === taskId);
                if (task) {
                    setTaskToDelete(task);
                    setIsDeleteModalOpen(true);
                }
                break;
        }
    };

    const handleViewTask = async (taskId: number) => {
        try {
            const response = await fetch(route('tasks.show', taskId));
            const data = await response.json();
            setSelectedTask(data.task);
            setIsModalOpen(true);
        } catch (error) {
            console.error('Failed to load task:', error);
        }
    };

    // Opened from a "Create > Task" link (with ?project_id= the project is preselected).
    useEffect(() => {
        if (consumeCreateIntent() && userWorkspaceRole !== 'client') {
            setEditingTask(null);
            setIsFormModalOpen(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Deep-link support: /tasks?task_id=123 (e.g. from the dashboard's "My Tasks"
    // widget) opens that task's detail modal automatically on load.
    useEffect(() => {
        const taskId = new URLSearchParams(window.location.search).get('task_id');
        if (taskId) {
            handleViewTask(Number(taskId));
        }
    }, []);

    const handleEditTask = async (taskId: number) => {
        try {
            const response = await fetch(route('tasks.show', taskId));
            const data = await response.json();

            const taskWithProject = {
                ...data.task,
                project: projects.find(p => p.id === data.task.project_id) || data.task.project
            };

            setEditingTask(taskWithProject);
            setIsFormModalOpen(true);
        } catch (error) {
            console.error('Failed to load task:', error);
        }
    };

    // The Type switch sits outside the panel, so it isn't counted as a panel filter.
    const activeFilterCount = () => {
        return (selectedProject !== 'all' ? 1 : 0) + (selectedStage !== 'all' ? 1 : 0) + (selectedPriority !== 'all' ? 1 : 0) + (selectedAssignee !== 'all' ? 1 : 0)
            + (showingBugs && selectedSeverity !== 'all' ? 1 : 0) + (searchTerm ? 1 : 0);
    };

    const hasActiveFilters = () => activeFilterCount() > 0;

    const handleResetFilters = () => {
        setSelectedProject('all');
        setSelectedStage('all');
        setSelectedPriority('all');
        setSelectedAssignee('all');
        setSelectedSeverity('all');
        setSearchTerm('');
        setShowFilters(false);
        router.get(route('tasks.index'), { page: 1, view, ...(selectedType !== 'all' ? { type: selectedType } : {}) }, { preserveState: true, preserveScroll: true });
    };

    const handleDeleteConfirm = () => {
        if (taskToDelete) {
            toast.loading('Deleting task...');
            router.delete(route('tasks.destroy', taskToDelete.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                    setTaskToDelete(null);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to delete task');
                    setIsDeleteModalOpen(false);
                    setTaskToDelete(null);
                }
            });
        }
    };

    // Tasks in a completed stage (e.g. "Done") or at 100% are never overdue.
    // Compares calendar days, so a task due today isn't overdue yet.
    const isTaskOverdue = (task: any) => {
        if (!task?.end_date) return false;
        if (task.task_stage?.is_completed || (task.progress ?? 0) >= 100) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const dueDate = new Date(task.end_date);
        dueDate.setHours(0, 0, 0, 0);
        return dueDate < today;
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            low: 'bg-ds-neutral-bg text-ds-neutral-fg',
            medium: 'bg-ds-info-bg text-ds-info-fg',
            high: 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
            critical: 'bg-ds-danger-bg text-ds-danger-fg'
        };
        return colors[priority as keyof typeof colors] || 'bg-ds-neutral-bg text-ds-neutral-fg';
    };
    const priorityLabel = (priority: string) => t((priority || '').charAt(0).toUpperCase() + (priority || '').slice(1));

    // Sorting runs on the server, so it covers every page, not just this one.
    const sortHeader = (field: string, label: string) => {
        const active = filters.sort_field === field;
        const direction = filters.sort_direction === 'asc' ? 'asc' : 'desc';
        const Icon = !active ? ChevronsUpDown : direction === 'asc' ? ArrowUp : ArrowDown;
        return (
            <TableHead scope="col" aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}>
                <button
                    type="button"
                    className={cn('-mx-1 inline-flex cursor-pointer items-center gap-1 whitespace-nowrap rounded-sm px-1 hover:text-ds-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus', active && 'text-ds-fg')}
                    onClick={() => reload({ sort_field: field, sort_direction: active && direction === 'asc' ? 'desc' : 'asc' })}
                >
                    {label}
                    <Icon className={cn('h-3 w-3', !active && 'opacity-40')} aria-hidden="true" />
                </button>
            </TableHead>
        );
    };

    const userPermissionList = auth?.permissions || [];
    const canExport = hasPermission(userPermissionList, 'task_view_any');
    const canImport = userWorkspaceRole !== 'client' && hasPermission(userPermissionList, 'task_create');

    // The list as the page shows it now (the applied filters and sort), as Excel.
    const exportTasks = async () => {
        setIsExporting(true);
        try {
            const params: Record<string, string> = {};
            ['project_id', 'stage_id', 'priority', 'assigned_to', 'type', 'severity', 'search', 'sort_field', 'sort_direction'].forEach((key) => {
                const value = (filters as Record<string, any>)[key];
                if (value && value !== 'all') params[key] = String(value);
            });
            const response = await fetch(route('tasks.export', params), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `tasks_${new Date().toLocaleDateString('en-CA')}.xlsx`; // today's local date, YYYY-MM-DD
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            toast.success(t('Tasks exported'));
        } catch (error) {
            console.error('Task export failed:', error);
            toast.error(t('Export failed'));
        } finally {
            setIsExporting(false);
        }
    };

    const importExportMenu = (canExport || canImport) && (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" disabled={isExporting}>
                    <FileDown className="h-4 w-4" />
                    {isExporting ? t('Exporting...') : t('Import / Export')}
                    <ChevronDown className="h-4 w-4 text-ds-fg-muted" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-52">
                {canExport && (
                    <DropdownMenuItem onClick={exportTasks} className="gap-2">
                        <FileDown className="h-4 w-4" />
                        {t('Export to Excel')}
                    </DropdownMenuItem>
                )}
                {canImport && (
                    <DropdownMenuItem onClick={() => setIsImportOpen(true)} className="gap-2">
                        <FileUp className="h-4 w-4" />
                        {t('Import from Excel / CSV')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const importProject = filters.project_id && filters.project_id !== 'all'
        ? projects.find((p) => String(p.id) === String(filters.project_id))
        : undefined;

    const pageActions: PageAction[] = [];

    // Only show Create Task button for non-clients; on the bug list it reports a bug.
    if (userWorkspaceRole !== 'client') {
        pageActions.push({
            label: showingBugs ? t('Report Bug') : t('Create Task'),
            icon: <Plus className="h-4 w-4" />,
            variant: 'default',
            onClick: () => {
                setEditingTask(null);
                setIsFormModalOpen(true);
            }
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        ...(project_name ? [{ title: t('Projects'), href: route('projects.index') }] : []),
        { title: project_name ? `${project_name} - ${t('Tasks')}` : showingBugs ? t('Bugs') : t('Tasks') }
    ];

    const typeOptions = [
        { value: 'all', label: t('All work') },
        { value: 'task', label: t('Tasks') },
        { value: 'bug', label: t('Bugs') },
    ];

    return (
        <PageTemplate
            title={project_name ? `${project_name} - ${t('Tasks')}` : showingBugs ? t('Bugs') : (userWorkspaceRole === 'member' ? t('My Tasks') : t('Tasks'))}
            url="/tasks"
            actions={pageActions}
            toolbar={importExportMenu}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <Head title={t('Tasks')} />

            {/* Filters Row */}
            <div className="mb-4 rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Tasks and bugs are one list: switch between them here. */}
                            <div role="radiogroup" aria-label={t('Type')} className="inline-flex rounded-lg bg-ds-neutral-bg p-1">
                                {typeOptions.map(option => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={selectedType === option.value}
                                        onClick={() => handleFilter('type', option.value)}
                                        className={cn(
                                            'inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-md px-3 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                                            selectedType === option.value ? 'bg-ds-surface font-medium text-ds-fg shadow-ds-xs' : 'text-ds-fg-muted hover:text-ds-fg',
                                        )}
                                    >
                                        {option.value !== 'all' && <TypeIcon type={option.value} className="h-3.5 w-3.5" />}
                                        {option.label}
                                    </button>
                                ))}
                            </div>

                            <form onSubmit={handleSearch} className="w-full sm:w-64" role="search">
                                <SearchInput
                                    placeholder={showingBugs ? t('Search bugs...') : t('Search tasks...')}
                                    value={searchTerm}
                                    onChange={(value) => {
                                        setSearchTerm(value);
                                        clearTimeout(window.searchTimeout);
                                        window.searchTimeout = setTimeout(() => reload({ search: value }), 500);
                                    }}
                                />
                            </form>

                            {/* Filters never outrank the page's primary action. */}
                            <Button
                                variant={hasActiveFilters() ? 'secondary' : 'outline'}
                                onClick={() => setShowFilters(!showFilters)}
                                aria-expanded={showFilters}
                                aria-controls="task-filters"
                            >
                                <Filter className="h-4 w-4" />
                                {showFilters ? t('Hide Filters') : t('Filters')}
                                {hasActiveFilters() && (
                                    <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground">
                                        {activeFilterCount()}
                                        <span className="sr-only"> {t('applied')}</span>
                                    </span>
                                )}
                            </Button>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {view === 'sheet' && (
                                <>
                                    <Label className="text-xs font-normal text-ds-fg-muted">{t('Per Page')}:</Label>
                                    <Select
                                        value={tasks?.per_page?.toString() || "20"}
                                        onValueChange={(value) => reload({ per_page: parseInt(value) })}
                                    >
                                        <SelectTrigger className="w-16 h-8">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="20">20</SelectItem>
                                            <SelectItem value="50">50</SelectItem>
                                            <SelectItem value="100">100</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </>
                            )}
                            <BoardViewMenu view={view} onChange={chooseView} />
                        </div>
                    </div>

                    {showFilters && (
                        <div id="task-filters" className="rounded-lg border border-ds-border-subtle bg-ds-surface-subtle p-4">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('Project')}</Label>
                                    <Select value={selectedProject} onValueChange={(value) => handleFilter('project_id', value)}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Projects')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Projects')}</SelectItem>
                                            {projects.map((project) => (
                                                <SelectItem key={project.id} value={project.id.toString()}>
                                                    {project.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStage} onValueChange={(value) => handleFilter('stage_id', value)}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All')}</SelectItem>
                                            {stages.map((stage) => (
                                                <SelectItem key={stage.id} value={stage.id.toString()}>
                                                    {stage.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Priority')}</Label>
                                    <Select value={selectedPriority} onValueChange={(value) => handleFilter('priority', value)}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Priority')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Priority')}</SelectItem>
                                            <SelectItem value="low">{t('Low')}</SelectItem>
                                            <SelectItem value="medium">{t('Medium')}</SelectItem>
                                            <SelectItem value="high">{t('High')}</SelectItem>
                                            <SelectItem value="critical">{t('Critical')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {showingBugs && (
                                    <div className="space-y-2">
                                        <Label>{t('Severity')}</Label>
                                        <Select value={selectedSeverity} onValueChange={(value) => handleFilter('severity', value)}>
                                            <SelectTrigger className="w-40">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">{t('All Severity')}</SelectItem>
                                                {SEVERITY_OPTIONS.map(option => (
                                                    <SelectItem key={option.value} value={option.value}>{t(option.label)}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label>{t('Assignee')}</Label>
                                    <Select value={selectedAssignee} onValueChange={(value) => handleFilter('assigned_to', value)}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Assignees')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Assignees')}</SelectItem>
                                            {members.map((member) => (
                                                <SelectItem key={member.id} value={member.id.toString()}>
                                                    {member.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button
                                    variant="ghost"
                                    onClick={handleResetFilters}
                                    disabled={!hasActiveFilters()}
                                >
                                    {t('Reset Filters')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Detail and Plain views: every matching item by stage, across projects. */}
            {view !== 'sheet' && cards.length > 0 && (
                <div className="space-y-2">
                    {board && board.total > board.cards.length && (
                        <p className="text-sm text-ds-fg-muted">
                            {t('Showing the :shown most recent of :total items. Use the filters to narrow them down.').replace(':shown', String(board.cards.length)).replace(':total', String(board.total))}
                        </p>
                    )}
                    <KanbanBoard
                        stages={stages as any}
                        tasks={cards}
                        canMove={canMove}
                        onMove={moveCard}
                        onOpen={(taskId) => handleAction('view', taskId)}
                        variant={view}
                        showProject={!project_name && selectedProject === 'all'}
                    />
                </div>
            )}

            {/* Sheet view: this table, page by page. */}
            {view === 'sheet' && (tasks?.data?.length ?? 0) > 0 && (
            <div className="overflow-hidden rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
                {(
                        <Table className="min-w-[760px]">
                            <TableHeader>
                                <TableRow>
                                    {sortHeader('title', t('Title'))}
                                    <TableHead scope="col">{t('Stage')}</TableHead>
                                    {sortHeader('priority', t('Priority'))}
                                    <TableHead scope="col">{t('Assignee')}</TableHead>
                                    {sortHeader('end_date', t('Due Date'))}
                                    <TableHead scope="col" className="text-right">{t('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks?.data?.map((task: Task) => (
                                    <TableRow key={`table-${task.id}`}>
                                        <TableCell>
                                            <div className="flex items-start gap-2.5">
                                                <TypeIcon type={task.type} className="mt-0.5" />
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <button
                                                            type="button"
                                                            className="cursor-pointer rounded-sm text-left text-sm font-medium text-ds-fg transition-colors hover:text-ds-fg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
                                                            onClick={() => handleAction('view', task.id)}
                                                        >
                                                            {task.title}
                                                        </button>
                                                        {task.type === 'bug' && <SeverityBadge severity={task.severity} />}
                                                    </div>
                                                    {/* The project sits under the title (one column instead of two). */}
                                                    {!project_name && <div className="text-sm text-ds-fg-muted">{task.project?.title}</div>}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {/* Changed in place, the same move as dragging a card on the board. */}
                                            {canMove && task.task_stage_id ? (
                                                <Select value={String(task.task_stage_id)} onValueChange={(value) => changeStage(task.id, Number(value))}>
                                                    <SelectTrigger className="h-8 w-40 text-xs" aria-label={t('Stage of :title').replace(':title', task.title)}>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {stages.map(stage => (
                                                            <SelectItem key={stage.id} value={String(stage.id)}>
                                                                <span className="inline-flex items-center gap-2">
                                                                    <span className="h-2 w-2 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
                                                                    {stage.name}
                                                                </span>
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    style={{ backgroundColor: task.task_stage?.color + '20', borderColor: task.task_stage?.color }}
                                                >
                                                    {task.task_stage?.name}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            <span className={cn('inline-flex items-center rounded-sm px-2 py-0.5 text-xs font-medium', getPriorityColor(task.priority))}>
                                                {priorityLabel(task.priority)}
                                            </span>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {task.assigned_to ? (
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="h-7 w-7">
                                                        <AvatarImage src={task.assigned_to.avatar} />
                                                        <AvatarFallback className="text-xs">
                                                            {task.assigned_to.name?.charAt(0)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="truncate text-sm text-ds-fg">{task.assigned_to.name}</span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-ds-fg-placeholder">{t('Unassigned')}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            <div className="flex items-center gap-2">
                                                {task.end_date && isTaskOverdue(task) && (
                                                    <StatusBadge tone="danger" icon={<AlertTriangle aria-hidden="true" />}>
                                                        {t('Overdue')}
                                                    </StatusBadge>
                                                )}
                                                {task.end_date
                                                    ? <span className="text-ds-fg-secondary">{new Date(task.end_date).toLocaleDateString()}</span>
                                                    : <span className="text-ds-fg-placeholder">{t('No due date')}</span>}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap py-1.5 text-right">
                                            {/* View is the everyday action; changes sit in a menu, and Delete asks first. */}
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => handleAction('view', task.id)}>{t('View')}</Button>
                                                {userWorkspaceRole !== 'client' && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <IconButton aria-label={t('More actions for {{name}}', { name: task.title })}>
                                                                <MoreHorizontal />
                                                            </IconButton>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="min-w-40">
                                                            <DropdownMenuItem onClick={() => handleAction('edit', task.id)}><Edit />{t('Edit')}</DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleAction('duplicate', task.id)}><Copy />{t('Duplicate')}</DropdownMenuItem>
                                                            <DropdownMenuItem variant="destructive" onClick={() => handleAction('delete', task.id)}><Trash2 />{t('Delete')}</DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                )}
            </div>
            )}

            {/* Empty State */}
            {(view === 'sheet' ? (tasks?.data?.length ?? 0) : cards.length) === 0 && (
                <div className="rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
                    <EmptyState
                        icon={hasActiveFilters() ? Search : CheckSquare}
                        title={showingBugs ? t('No Bugs Found') : t('No Tasks Found')}
                        description={hasActiveFilters()
                            ? (showingBugs ? t('No bugs match your current filters.') : t('No tasks match your current filters.'))
                            : (showingBugs ? t('No bugs have been reported yet.') : t('No tasks have been created yet.'))}
                        secondaryAction={hasActiveFilters() ? { label: t('Clear Filters'), onClick: handleResetFilters } : undefined}
                        action={!hasActiveFilters() && userWorkspaceRole !== 'client'
                            ? { label: showingBugs ? t('Report Bug') : t('Create Task'), icon: <Plus className="h-4 w-4" />, onClick: () => { setEditingTask(null); setIsFormModalOpen(true); } }
                            : undefined}
                    />
                </div>
            )}

            {/* Pagination */}
            {view === 'sheet' && tasks?.links && (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ds-border bg-ds-surface px-4 py-3 shadow-ds-xs">
                    <div className="text-sm text-ds-fg-muted">
                        {t('Showing')} <span className="font-medium">{tasks?.from || 0}</span> {t('to')} <span className="font-medium">{tasks?.to || 0}</span> {t('of')} <span className="font-medium">{tasks?.total || 0}</span> {showingBugs ? t('bugs') : t('items')}
                    </div>

                    <div className="flex flex-wrap gap-1">
                        {tasks?.links?.map((link: any, i: number) => {
                            const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                            const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");

                            return (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size={isTextLink ? "sm" : "icon"}
                                    className={isTextLink ? "px-3" : "size-8"}
                                    aria-current={link.active ? 'page' : undefined}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                >
                                    {isTextLink ? t(label) : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Modals */}
            {selectedTask && (
                <TaskModal
                    task={selectedTask}
                    isOpen={isModalOpen}
                    onClose={() => {
                        setIsModalOpen(false);
                        setSelectedTask(null);
                    }}
                    members={members}
                    stages={stages}
                    milestones={selectedTask.project?.milestones || []}
                    permissions={taskPermissions}
                />
            )}

            <TaskFormModal
                isOpen={isFormModalOpen}
                onClose={() => {
                    setIsFormModalOpen(false);
                    setEditingTask(null);
                }}
                task={editingTask || undefined}
                projects={projects}
                members={members}
                milestones={editingTask?.project?.milestones || []}
                initialProjectId={selectedProject !== 'all' ? selectedProject : undefined}
                initialType={showingBugs ? 'bug' : 'task'}
            />

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setTaskToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={taskToDelete?.title || ''}
                entityName={taskToDelete?.type === 'bug' ? t('bug') : t('task')}
            />

            {canImport && (
                <ImportModal
                    isOpen={isImportOpen}
                    onClose={() => setIsImportOpen(false)}
                    type="tasks"
                    title="Tasks"
                    requiredFields={importProject ? ['title'] : ['title', 'project']}
                    extraData={importProject ? { project_id: importProject.id } : undefined}
                    notes={
                        <ul className="text-xs text-ds-info-fg list-disc pl-4 space-y-0.5">
                            <li>{t('One task per row, from Excel (.xlsx, .xls) or CSV. Columns are matched by name; you can change any match.')}</li>
                            <li>
                                {importProject
                                    ? t('Rows without a Project go into {{project}}.', { project: importProject.title })
                                    : t('Each row needs a Project (its exact name). Filter this page by a project first to put every row into it.')}
                            </li>
                            <li>{t('People are matched by email or full name. Stage, priority and dates are optional.')}</li>
                            <li>{t('A title already in the project is skipped. Nobody is notified about imported tasks.')}</li>
                        </ul>
                    }
                />
            )}
        </PageTemplate>
    );
}