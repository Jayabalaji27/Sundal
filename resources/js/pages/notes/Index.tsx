import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Trash2, StickyNote, Users, User, LayoutGrid, List } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { CrudTable } from '@/components/CrudTable';
import { useTranslation } from 'react-i18next';
import NoteFormModal from '@/components/notes/NoteFormModal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog } from '@/components/ui/dialog';
import NoteView from './NoteView';

interface Note {
    id: number;
    title: string;
    text: string;
    color: string;
    type: 'personal' | 'shared';
    assign_to: string | null;
    workspace: number;
    created_by: number;
    created_at: string;
    creator: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    };
    assigned_users?: Array<{
        id: number;
        name: string;
        email: string;
    }>;
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

export default function NotesIndex() {
    const { t } = useTranslation();
    const { personal_notes, shared_notes, combined_notes, users, auth, flash, permissions: pagePermissions, filters: pageFilters = {} } = usePage().props as any;
    const notePermissions = pagePermissions;

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    const [activeView, setActiveView] = useState(pageFilters.view_mode || 'grid');
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedType, setSelectedType] = useState(pageFilters.type || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [currentNote, setCurrentNote] = useState<Note | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');

    const getTypeColor = (type: string) => {
        const colors: Record<string, string> = {
            personal: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            shared: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
        };
        return colors[type] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const hasActiveFilters = () => {
        return searchTerm !== '' || selectedType !== 'all';
    };

    const activeFilterCount = () => {
        return (searchTerm ? 1 : 0) + (selectedType !== 'all' ? 1 : 0);
    };

    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; type?: string; view?: string } = {}
    ) => {
        const view   = stateOverrides.view   !== undefined ? stateOverrides.view   : activeView;
        const search = stateOverrides.search !== undefined ? stateOverrides.search : searchTerm;
        const type   = stateOverrides.type   !== undefined ? stateOverrides.type   : selectedType;

        const params: any = { notes_page: 1, view_mode: view };
        if (search) params.search = search;
        if (type !== 'all') params.type = type;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_field) params.sort_field = pageFilters.sort_field;
        if (pageFilters.sort_direction) params.sort_direction = pageFilters.sort_direction;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('notes.index'), buildParams({ notes_page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const applyFilters = () => {
        router.get(route('notes.index'), buildParams({ notes_page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
        router.get(route('notes.index'), buildParams({ sort_field: field, sort_direction: direction, notes_page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedType('all');
        setShowFilters(false);
        const params: any = { notes_page: 1, view_mode: activeView };
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        router.get(route('notes.index'), params, { preserveState: false, preserveScroll: false });
    };

    const handleAction = (action: string, noteOrId: Note | number) => {
        let note: Note;
        
        if (typeof noteOrId === 'number') {
            // Called from CrudTable with ID
            note = (combined_notes?.data || [...(personal_notes?.data || []), ...(shared_notes?.data || [])]).find((n: Note) => n.id === noteOrId);
            if (!note) return;
        } else {
            // Called from grid view with note object
            note = noteOrId;
        }
        
        setCurrentNote(note);
        switch (action) {
            case 'view':
                setIsViewModalOpen(true);
                break;
            case 'edit':
                setModalMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete':
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentNote(null);
        setModalMode('create');
        setIsFormModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (currentNote) {
            toast.loading(t('Deleting note...'));
            router.delete(route('notes.destroy', currentNote.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error(t('Failed to delete note'));
                    setIsDeleteModalOpen(false);
                }
            });
        }
    };

    // CrudTable configuration
    const columns = [
        {
            key: 'title',
            label: t('Title'),
            sortable: true,
            render: (value: string) => (
                <span className="text-sm font-medium text-gray-900">{value}</span>
            )
        },
        {
            key: 'type',
            label: t('Type'),
            sortable: true,
            render: (value: string) => (
                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getTypeColor(value)}`}>
                    {value === 'shared' ? t('Shared') : t('Personal')}
                </span>
            )
        },
        {
            key: 'created_at',
            label: t('Created By'),
            sortable: true,
            render: (value: string, row: Note) => (
                <div className="flex items-center gap-2">
                    <Avatar className="h-7 w-7">
                        <AvatarImage src={row.creator?.avatar ?? undefined} />
                        <AvatarFallback className="text-xs">{row.creator.name.charAt(0).toUpperCase()}</AvatarFallback>
                    </Avatar>
                    <div>
                        <div className="text-sm font-medium text-gray-900">{row.creator.name}</div>
                        <div className="text-xs text-gray-500">{window.appSettings.formatDateTime(new Date(value),false)}</div>
                    </div>
                </div>
            )
        },
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
            condition: () => notePermissions?.update
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500 hover:text-red-700',
            condition: () => notePermissions?.delete
        }
    ];

    const renderNoteCard = (note: Note) => (
        <Card key={note.id} className="overflow-hidden hover:shadow-md transition-shadow">
            <CardHeader className="pb-2">
                <div className="flex justify-between items-start">
                    <CardTitle className="text-base line-clamp-1 flex items-center gap-2">
                        {note.title}
                    </CardTitle>
                    {/* <div className="flex items-center gap-2">
                        {note.type === 'shared' ? (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Users className="h-4 w-4 text-blue-500" />
                                </TooltipTrigger>
                                <TooltipContent>
                                    {note.assigned_users ? note.assigned_users.map((user: any) => user.name).join(', ') : note.creator.name}
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <User className="h-4 w-4 text-gray-500" />
                                </TooltipTrigger>
                                <TooltipContent>{note.creator.name}</TooltipContent>
                            </Tooltip>
                        )}
                    </div> */}
                </div>
                <div className="text-xs text-muted-foreground">
                    {t('By')} {note.creator.name} • {window.appSettings.formatDateTime(new Date(note.created_at),false)}
                </div>
            </CardHeader>

            <CardContent className="py-2">
                <div 
                    className="text-sm text-gray-500 line-clamp-2 break-words overflow-hidden"
                    dangerouslySetInnerHTML={{ __html: note.text }}
                />
            </CardContent>

            <CardFooter className="flex justify-between items-center pt-0 pb-2">
                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getTypeColor(note.type)}`}>
                    {note.type === 'shared' ? t('Shared') : t('Personal')}
                </span>
                <div className="flex gap-1">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => handleAction('view', note)}
                                className="text-blue-500 hover:text-blue-700 h-8 w-8"
                            >
                                <Eye className="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t('View')}</TooltipContent>
                    </Tooltip>
                    {notePermissions?.update && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleAction('edit', note)}
                                    className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                >
                                    <Edit className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t('Edit')}</TooltipContent>
                        </Tooltip>
                    )}
                    {notePermissions?.delete && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-red-500 hover:text-red-700 h-8 w-8"
                                    onClick={() => handleAction('delete', note)}
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
    );

    const pageActions = [];

    if (notePermissions?.create) {
        pageActions.push({
            label: t('Create Note'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Notes') }
    ];

    return (
        <PageTemplate
            title={t('Notes')}
            url="/notes"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters */}
            <div className="bg-white rounded-lg border shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search notes...')}
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
                                    onClick={() => {
                                        setActiveView('list');
                                        router.get(route('notes.index'), buildParams({ notes_page: 1, view_mode: 'list' }, { view: 'list' }), { preserveState: false, preserveScroll: false });
                                    }}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button 
                                    size="sm" 
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => {
                                        setActiveView('grid');
                                        router.get(route('notes.index'), buildParams({ notes_page: 1, view_mode: 'grid' }, { view: 'grid' }), { preserveState: false, preserveScroll: false });
                                    }}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                            
                            <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
                            <Select 
                                value={pageFilters.per_page?.toString() || '12'} 
                                onValueChange={(value) => {
                                    router.get(route('notes.index'), buildParams({ notes_page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
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
                                    <Label>{t('Type')}</Label>
                                    <Select value={selectedType} onValueChange={(value) => {
                                        setSelectedType(value);
                                        router.get(route('notes.index'), buildParams({ notes_page: 1 }, { type: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Types')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Types')}</SelectItem>
                                            <SelectItem value="personal">{t('Personal')}</SelectItem>
                                            <SelectItem value="shared">{t('Shared')}</SelectItem>
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

            {/* Notes Content */}
            {activeView === 'list' ? (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <CrudTable
                        columns={columns}
                        actions={actions}
                        data={combined_notes?.data || [...(personal_notes?.data || []), ...(shared_notes?.data || [])]}
                        from={(combined_notes?.from || personal_notes?.from || shared_notes?.from) || 1}
                        onAction={handleAction}
                        sortField={pageFilters.sort_field}
                        sortDirection={pageFilters.sort_direction}
                        onSort={handleSort}
                        permissions={auth?.permissions || []}
                    />
                    {/* Pagination - for list view */}
                    {combined_notes?.links && (
                        <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                            <div className="text-sm text-muted-foreground">
                                {t('Showing')} <span className="font-medium">{combined_notes.from || 1}</span> {t('to')} <span className="font-medium">{combined_notes.to || 0}</span> {t('of')} <span className="font-medium">{combined_notes.total}</span> {t('notes')}
                            </div>
                            
                            <div className="flex gap-1">
                                {combined_notes.links.map((link: any, i: number) => {
                                    const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                                    const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");

                                    const handlePageNav = () => {
                                        if (!link.url) return;
                                        const notesPage = new URL(link.url).searchParams.get('notes_page');
                                        router.get(route('notes.index'), buildParams({ notes_page: notesPage ? parseInt(notesPage) : 1 }), { preserveState: false, preserveScroll: false });
                                    };
                                    
                                    return (
                                        <Button
                                            key={i}
                                            variant={link.active ? 'default' : 'outline'}
                                            size={isTextLink ? "sm" : "icon"}
                                            className={isTextLink ? "px-3" : "h-8 w-8"}
                                            disabled={!link.url}
                                            onClick={handlePageNav}
                                        >
                                            {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <>
                    {/* Combined Notes Section */}
                    <div className="mb-8">
                        <div className="flex items-center gap-2 mb-4">
                            <StickyNote className="h-5 w-5 text-gray-600" />
                            <h2 className="text-lg font-semibold text-gray-900">{t('All Notes')}</h2>
                            <Badge variant="secondary" className="ml-2">
                                {combined_notes?.total || 0}
                            </Badge>
                        </div>
                        <p className="text-sm text-gray-500 -mt-2 mb-4">
                            {t("Personal notes are only visible to you. Shared notes are visible only to the people they are shared with, so a teammate's note shows up here once they share it with you.")}
                        </p>

                        {(combined_notes?.data?.length || 0) > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                {combined_notes.data.map((note: Note) => renderNoteCard(note))}
                            </div>
                        ) : (
                            <div className="bg-white rounded-lg shadow p-8 text-center">
                                <StickyNote className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                <p className="text-gray-500 mb-4">{searchTerm ? t('No notes found matching your search') : t('No notes found')}</p>
                                {notePermissions?.create && (
                                    <Button onClick={handleAddNew}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Create your first note')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </>
            )}
            
            {/* Pagination - for grid view */}
            {activeView === 'grid' && combined_notes?.links && (
                <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm text-muted-foreground">
                        {t('Showing')} <span className="font-medium">{combined_notes.from || 1}</span> {t('to')} <span className="font-medium">{combined_notes.to || 0}</span> {t('of')} <span className="font-medium">{combined_notes.total}</span> {t('notes')}
                    </div>

                    <div className="flex flex-wrap gap-1">
                        {combined_notes.links.map((link: any, i: number) => {
                            const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                            const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");

                            const handlePageNav = () => {
                                if (!link.url) return;
                                const notesPage = new URL(link.url).searchParams.get('notes_page');
                                router.get(route('notes.index'), buildParams({ notes_page: notesPage ? parseInt(notesPage) : 1 }), { preserveState: false, preserveScroll: false });
                            };
                            
                            return (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size={isTextLink ? "sm" : "icon"}
                                    className={isTextLink ? "px-3" : "h-8 w-8"}
                                    disabled={!link.url}
                                    onClick={handlePageNav}
                                >
                                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Modals */}
            <NoteFormModal
                isOpen={isFormModalOpen}
                onClose={() => {
                    setIsFormModalOpen(false);
                    setCurrentNote(null);
                }}
                note={currentNote}
                mode={modalMode}
                users={users}
            />

            {/* View Modal */}
            <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
                {currentNote && <NoteView record={currentNote} users={users} />}
            </Dialog>

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentNote?.title || ''}
                entityName="note"
            />
        </PageTemplate>
    );
}