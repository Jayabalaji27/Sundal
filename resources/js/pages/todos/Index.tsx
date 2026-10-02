import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Plus, CheckSquare, Edit, Trash2, Eye, LayoutGrid, List, AlertTriangle, Search, Filter, Send, MessageSquare, Paperclip, Download } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { useTranslation } from 'react-i18next';
import TodoFormModal from './TodoFormModal';
import TodoView from './TodoView';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { hasPermission } from '@/utils/authorization';
import axios from 'axios';
import { useForm } from '@inertiajs/react';

interface Todo {
    id: number;
    title: string;
    description: string | null;
    priority: 'low' | 'medium' | 'high';
    status: 'pending' | 'in_progress' | 'completed' | 'overdue';
    due_date: string | null;
    completed_at: string | null;
    created_at: string;
    creator: {
        id: number;
        name: string;
    };
    members: Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    }>;
}

export default function TodosIndex() {
    const { t } = useTranslation();
    const { todos, workspaceMembers, flash, auth, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [currentTodo, setCurrentTodo] = useState<Todo | null>(null);
    const [todoData, setTodoData] = useState<any>(null);
    const [activeTab, setActiveTab] = useState('details');
    const [editingComment, setEditingComment] = useState<number | null>(null);
    const [editCommentText, setEditCommentText] = useState('');
    const [selectedFiles, setSelectedFiles] = useState<FileList | null>(null);
    const [activeView, setActiveView] = useState(pageFilters.view || 'grid');
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedPriority, setSelectedPriority] = useState(pageFilters.priority || 'all');
    const [showFilters, setShowFilters] = useState(false);

    const { data: commentData, setData: setCommentData, post: postComment, reset: resetComment } = useForm({
        comment: ''
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.warning) {
            toast.warning(flash.warning);
        }
    }, [flash]);

    const getPriorityColor = (priority: string) => {
        const colors = {
            high: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
        };
        return colors[priority as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getStatusColor = (status: string) => {
        const colors = {
            pending: 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            in_progress: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            completed: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            overdue: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const renderStatusBadge = (status: string) => {
        return (
            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(status)}`}>
                {/* {status === 'overdue' && <AlertTriangle className="h-3 w-3 mr-1" />} */}
                {capitalizeFirst(status)}
            </span>
        );
    };

    const getInitials = (name: string) => {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    };

    const getAvatarColor = (name: string) => {
        const colors = {
            'owner': 'bg-purple-500',
            'manager': 'bg-blue-500', 
            'member': 'bg-green-500',
            'client': 'bg-orange-500'
        };
        // Simple hash based on first character
        const firstChar = name.charAt(0).toUpperCase();
        const colorKeys = Object.keys(colors);
        const index = firstChar.charCodeAt(0) % colorKeys.length;
        return colors[colorKeys[index] as keyof typeof colors] || 'bg-gray-500';
    };

    const capitalizeFirst = (str: string) => {
        return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
    };

    const handleAddNew = () => {
        setCurrentTodo(null);
        setIsFormModalOpen(true);
    };

    const handleView = (todo: Todo) => {
        setCurrentTodo(todo);
        setActiveTab('details');
        if (todo.id) {
            axios.get(route('todos.show', todo.id))
                .then(response => {
                    setTodoData(response.data.todo);
                })
                .catch(console.error);
        }
        setIsViewModalOpen(true);
    };

    const handleEdit = (todo: Todo) => {
        setCurrentTodo(todo);
        setIsFormModalOpen(true);
    };

    const handleDelete = (todo: Todo) => {
        setCurrentTodo(todo);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (currentTodo) {
            router.delete(route('todos.destroy', currentTodo.id), {
                onSuccess: () => {
                    setIsDeleteModalOpen(false);
                    setCurrentTodo(null);
                },
                onError: () => {
                    toast.error(t('Failed to delete todo'));
                }
            });
        }
    };

    const handleComment = (e: React.FormEvent) => {
        e.preventDefault();
        if (todoData?.id) {
            postComment(route('todo-comments.store', todoData.id), {
                onSuccess: () => {
                    resetComment();
                    axios.get(route('todos.show', todoData.id))
                        .then(response => {
                            setTodoData(response.data.todo);
                        })
                        .catch(console.error);
                }
            });
        }
    };

    const handleFileUpload = (e: React.FormEvent) => {
        e.preventDefault();
        if (todoData?.id && selectedFiles) {
            const formData = new FormData();
            Array.from(selectedFiles).forEach((file) => {
                formData.append('files[]', file);
            });

            router.post(route('todo-attachments.store', todoData.id), formData, {
                onSuccess: () => {
                    setSelectedFiles(null);
                    axios.get(route('todos.show', todoData.id))
                        .then(response => setTodoData(response.data.todo))
                        .catch(console.error);
                }
            });
        }
    };

    const handleDeleteAttachment = (attachmentId: number) => {
        if (todoData?.id) {
            router.delete(route('todo-attachments.destroy', attachmentId), {
                onSuccess: () => {
                    axios.get(route('todos.show', todoData.id))
                        .then(response => setTodoData(response.data.todo))
                        .catch(console.error);
                }
            });
        }
    };

    const hasActiveFilters = () => {
        return searchTerm !== '' || selectedStatus !== 'all' || selectedPriority !== 'all';
    };

    const activeFilterCount = () => {
        return (searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedPriority !== 'all' ? 1 : 0);
    };

    // Central param builder
    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; status?: string; priority?: string; view?: string } = {}
    ) => {
        const view     = stateOverrides.view     !== undefined ? stateOverrides.view     : activeView;
        const search   = stateOverrides.search   !== undefined ? stateOverrides.search   : searchTerm;
        const status   = stateOverrides.status   !== undefined ? stateOverrides.status   : selectedStatus;
        const priority = stateOverrides.priority !== undefined ? stateOverrides.priority : selectedPriority;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (priority !== 'all') params.priority = priority;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('todos.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedStatus('all');
        setSelectedPriority('all');
        setShowFilters(false);
        const params: any = { page: 1, view: activeView };
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        router.get(route('todos.index'), params, { preserveState: false, preserveScroll: false });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_by === field && pageFilters.sort_order === 'asc' ? 'desc' : 'asc';
        router.get(route('todos.index'), buildParams({ sort_by: field, sort_order: direction, page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const todosData = todos?.data || [];

    // Handle actions for CrudTable
    const handleAction = (action: string, todo: Todo) => {
        switch (action) {
            case 'view':
                handleView(todo);
                break;
            case 'edit':
                handleEdit(todo);
                break;
            case 'delete':
                handleDelete(todo);
                break;
        }
    };

    // CrudTable configuration
    const columns = [
        {
            key: 'title',
            label: t('Title'),
            sortable: true,
            render: (value: string, row: Todo) => (
                <div>
                    <div 
                        className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors"
                        onClick={() => handleView(row)}
                    >
                        {value}
                    </div>
                    <div className="text-xs text-gray-500">{t('By')} {row.creator.name}</div>
                </div>
            )
        },
        {
            key: 'priority',
            label: t('Priority'),
            sortable: true,
            render: (value: string) => (
                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(value)}`}>
                    {capitalizeFirst(value)}
                </span>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (value: string) => renderStatusBadge(value)
        },
        {
            key: 'due_date',
            label: t('Due Date'),
            sortable: true,
            render: (value: string) => (
                <span className="text-sm text-gray-500">
                    {value ? window.appSettings.formatDateTime(new Date(value),false) : '-'}
                </span>
            )
        },
        {
            key: 'members',
            label: t('Members'),
            render: (value: any[], row: Todo) => (
                row.members.length > 0 ? renderMemberAvatars(row.members) : '-'
            )
        }
    ];

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500 hover:text-blue-700'
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500 hover:text-amber-700',
            condition: (row: Todo) => hasPermission(permissions, 'todo_update') && row.status !== 'completed'
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500 hover:text-red-700',
            condition: () => hasPermission(permissions, 'todo_delete')
        }
    ];

    const renderMemberAvatars = (members: any[]) => {
        if (!members || members.length === 0) return null;
        
        const displayMembers = members.slice(0, 3);
        const remainingCount = members.length - 3;

        return (
            <div className="flex -space-x-2">
                {displayMembers.map((member) => (
                    <Tooltip key={member.id}>
                        <TooltipTrigger asChild>
                            <Avatar className="h-6 w-6 border-2 border-white cursor-pointer">
                                <AvatarImage src={member.avatar} />
                                <AvatarFallback className={`text-[10px] font-semibold text-white ${getAvatarColor(member.name)}`}>
                                    {getInitials(member.name)}
                                </AvatarFallback>
                            </Avatar>
                        </TooltipTrigger>
                        <TooltipContent>{member.name}</TooltipContent>
                    </Tooltip>
                ))}
                {remainingCount > 0 && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="h-6 w-6 rounded-full border-2 border-white bg-gray-500 flex items-center justify-center text-white text-[10px] font-semibold cursor-pointer">
                                +{remainingCount}
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            {members.slice(3).map(m => m.name).join(', ')}
                        </TooltipContent>
                    </Tooltip>
                )}
            </div>
        );
    };

    const pageActions = hasPermission(permissions, 'todo_create') ? [
        {
            label: t('Create ToDo'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: handleAddNew
        }
    ] : [];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('ToDos') }
    ];

    return (
        <PageTemplate
            title={t('ToDos')}
            url="/todos"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >

            <div className="bg-white rounded-lg border shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search todos...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-full pl-9"
                                    />
                                </div>
                                <Button type="submit" size="sm">
                                    <Search className="h-4 w-4 mr-1.5" />
                                    {t('Search')}
                                </Button>
                            </form>
                            
                            <div className="ml-2">
                                <Button 
                                    variant={hasActiveFilters() ? "default" : "outline"}
                                    size="sm" 
                                    className="h-8 px-2 py-1"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-3.5 w-3.5 mr-1.5" />
                                    {showFilters ? t('Hide Filters') : t('Filters')}
                                    {hasActiveFilters() && (
                                        <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {activeFilterCount()}
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </div>
                        
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="border rounded-md p-0.5 mr-2">
                                <Button
                                    size="sm"
                                    variant={activeView === 'list' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('list'); router.get(route('todos.index'), buildParams({ page: 1, view: 'list' }, { view: 'list' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button 
                                    size="sm" 
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('grid'); router.get(route('todos.index'), buildParams({ page: 1, view: 'grid' }, { view: 'grid' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                            
                            <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
                            <Select 
                                value={pageFilters.per_page?.toString() || '12'} 
                                onValueChange={(value) => {
                                    router.get(route('todos.index'), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
                                }}
                            >
                                <SelectTrigger className="w-16 h-8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="12">12</SelectItem>
                                    <SelectItem value="24">24</SelectItem>
                                    <SelectItem value="48">48</SelectItem>
                                    <SelectItem value="100">100</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStatus} onValueChange={(value) => {
                                        setSelectedStatus(value);
                                        router.get(route('todos.index'), buildParams({ page: 1 }, { status: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Status')}</SelectItem>
                                            <SelectItem value="pending">{t('Pending')}</SelectItem>
                                            <SelectItem value="in_progress">{t('In Progress')}</SelectItem>
                                            <SelectItem value="completed">{t('Completed')}</SelectItem>
                                            <SelectItem value="overdue">{t('Overdue')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <div className="space-y-2">
                                    <Label>{t('Priority')}</Label>
                                    <Select value={selectedPriority} onValueChange={(value) => {
                                        setSelectedPriority(value);
                                        router.get(route('todos.index'), buildParams({ page: 1 }, { priority: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Priority')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Priority')}</SelectItem>
                                            <SelectItem value="low">{t('Low')}</SelectItem>
                                            <SelectItem value="medium">{t('Medium')}</SelectItem>
                                            <SelectItem value="high">{t('High')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    className="h-9"
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

            {activeView === 'grid' ? (
                    todosData.length === 0 ? (
                        <div className="bg-white rounded-lg shadow p-8 text-center">
                            <CheckSquare className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                            <p className="text-gray-500 mb-4">{t('No todos found')}</p>
                            {hasPermission(permissions, 'todo_create') && (
                                <Button onClick={handleAddNew}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    {t('Create your first todo')}
                                </Button>
                            )}
                        </div>
                    ):(
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {todosData.map((todo: Todo) => (
                            <Card key={todo.id} className="hover:shadow-md transition-shadow">
                                <CardHeader className="pb-2">
                                    <div className="flex justify-between items-start">
                                        <CardTitle 
                                            className="text-base line-clamp-1 cursor-pointer hover:text-blue-600 transition-colors"
                                            onClick={() => handleView(todo)}
                                        >
                                            {todo.title}
                                        </CardTitle>
                                        {renderStatusBadge(todo.status)}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {t('By')} {todo.creator.name}
                                    </div>
                                </CardHeader>

                                <CardContent className="py-2">
                                    {todo.description && (
                                        <p className="text-sm text-gray-500 line-clamp-2 mb-2">
                                            {todo.description}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-1">
                                            <span className="text-xs text-gray-600 font-semibold">{t('Priority')}:</span>
                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(todo.priority)}`}>
                                                {capitalizeFirst(todo.priority)}
                                            </span>
                                        </div>
                                        {todo.due_date && (
                                            <span className="text-xs text-gray-500">
                                                <span className="font-bold">Due:</span> {window.appSettings.formatDateTime(new Date(todo.due_date),false)}
                                            </span>
                                        )}
                                    </div>
                                </CardContent>

                                <CardFooter className="pt-0 pb-2 flex justify-between items-center">
                                    <div>
                                        {todo.members.length > 0 && renderMemberAvatars(todo.members)}
                                    </div>
                                    <div className="flex gap-1">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleView(todo)}
                                                    className="h-8 w-8 text-blue-500 hover:text-blue-700"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{t('View')}</TooltipContent>
                                        </Tooltip>
                                        {hasPermission(permissions, 'todo_update') && todo.status !== 'completed' && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleEdit(todo)}
                                                        className="h-8 w-8 text-amber-500 hover:text-amber-700"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('Edit')}</TooltipContent>
                                            </Tooltip>
                                        )}
                                        {hasPermission(permissions, 'todo_delete') && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleDelete(todo)}
                                                        className="h-8 w-8 text-red-500 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('Delete')}</TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                    )
                ) : (
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <CrudTable
                            columns={columns}
                            actions={actions}
                            data={todosData}
                            from={todos?.from || 1}
                            onAction={handleAction}
                            sortField={pageFilters.sort_by}
                            sortDirection={pageFilters.sort_order}
                            onSort={handleSort}
                            permissions={permissions}
                        />
                        {/* Pagination */}
                        {todos?.links && (
                            <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                                <div className="text-sm text-muted-foreground">
                                    {t('Showing')} <span className="font-medium">{todos?.from || 0}</span> {t('to')} <span className="font-medium">{todos?.to || 0}</span> {t('of')} <span className="font-medium">{todos?.total || 0}</span> {t('todos')}
                                </div>
                                
                                <div className="flex gap-1">
                                    {todos?.links?.map((link: any, i: number) => {
                                        const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                                        const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                                        
                                        return (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'outline'}
                                                size={isTextLink ? "sm" : "icon"}
                                                className={isTextLink ? "px-3" : "h-8 w-8"}
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (!link.url) return;
                                                    const pageNum = new URL(link.url).searchParams.get('page');
                                                    router.get(route('todos.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                                                }}
                                            >
                                                {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}

            <TodoFormModal
                isOpen={isFormModalOpen}
                onClose={() => {
                    setIsFormModalOpen(false);
                    setCurrentTodo(null);
                }}
                todo={currentTodo}
                workspaceMembers={workspaceMembers || []}
            />

            <Dialog open={isViewModalOpen} onOpenChange={() => setIsViewModalOpen(false)}>
                {currentTodo && (
                    <TodoView
                        todo={currentTodo}
                        todoData={todoData}
                        activeTab={activeTab}
                        onTabChange={setActiveTab}
                    />
                )}
            </Dialog>

            {/* Pagination */}
            {activeView === 'grid' && todos?.links && (
                <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm text-muted-foreground">
                        {t('Showing')} <span className="font-medium">{todos?.from || 0}</span> {t('to')} <span className="font-medium">{todos?.to || 0}</span> {t('of')} <span className="font-medium">{todos?.total || 0}</span> {t('todos')}
                    </div>

                    <div className="flex flex-wrap gap-1">
                        {todos?.links?.map((link: any, i: number) => {
                            const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                            const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                            
                            return (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size={isTextLink ? "sm" : "icon"}
                                    className={isTextLink ? "px-3" : "h-8 w-8"}
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (!link.url) return;
                                        const pageNum = new URL(link.url).searchParams.get('page');
                                        router.get(route('todos.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                                    }}
                                >
                                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentTodo?.title || ''}
                entityName="todo"
            />
        </PageTemplate>
    );
}
