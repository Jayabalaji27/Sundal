import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ProjectHealthWidget } from '@/components/ProjectHealthWidget';
import { ScopeCreepWidget } from '@/components/ScopeCreepWidget';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Edit, Plus, Pin, Trash2, Users, Calendar, DollarSign, Clock, User, Eye, Receipt, CheckSquare, Timer, CheckCircle, AlertTriangle, BarChart3, CreditCard, Columns, Paperclip, Download, Upload, Search, Bug, Play, Copy, Check, Globe, Link as LinkIcon, MoreHorizontal } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageTemplate } from '@/components/page-template';
import { CrudFormModal } from '@/components/CrudFormModal';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { SimplePagination } from '@/components/SimplePagination';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';
import { getTimesheetLabel, formatHoursDisplay, isTimesheetOverdue, getDaysOverdue } from '@/utils/timesheetUtils';
import { formatCurrency } from '@/utils/currency';
import MediaPicker from '@/components/MediaPicker';
import TimesheetFormModal from '@/components/timesheets/TimesheetFormModal';

function ClientPortalPanel({ project }: { project: any }) {
    const { t } = useTranslation();
    const appUrl = (usePage().props as any).globalSettings?.base_url || window.location.origin;
    const portalUrl = `${appUrl}/portal/${project.portal_token}`;
    const [enabled, setEnabled] = useState(!!project.portal_enabled);
    const [message, setMessage] = useState(project.portal_message || '');
    const [copied, setCopied] = useState(false);

    const copyLink = () => {
        navigator.clipboard.writeText(portalUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const save = () => {
        router.post(route('portal.toggle', project.id), { portal_enabled: enabled, portal_message: message }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <div className="space-y-5 max-w-xl">
            <div>
                <h3 className="font-semibold mb-1">{t('Client Portal')}</h3>
                <p className="text-sm text-muted-foreground">
                    {t('Share a read-only link with your client. They can see progress, milestones, invoices, and submit issue reports — no login required.')}
                </p>
            </div>

            <div className="flex items-center gap-3">
                <Switch checked={enabled} onCheckedChange={setEnabled} />
                <Label>{enabled ? t('Portal is enabled') : t('Portal is disabled')}</Label>
            </div>

            {enabled && project.portal_token && (
                <div>
                    <Label className="text-xs text-muted-foreground mb-1 block">{t('SHAREABLE LINK')}</Label>
                    <div className="flex gap-2">
                        <div className="flex-1 rounded-lg border bg-muted/40 px-3 py-2 text-sm font-mono truncate">
                            {portalUrl}
                        </div>
                        <Button size="icon" variant="outline" onClick={copyLink} title={t('Copy')}>
                            {copied ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                        </Button>
                        <Button size="icon" variant="outline" onClick={() => window.open(portalUrl, '_blank')} title={t('Open')}>
                            <LinkIcon className="h-4 w-4" />
                        </Button>
                    </div>
                    <button onClick={() => router.post(route('portal.regenerate', project.id), {}, { preserveScroll: true, preserveState: true })}
                        className="mt-1 text-xs text-muted-foreground hover:text-destructive transition-colors">
                        {t('Regenerate link')} (invalidates current link)
                    </button>
                </div>
            )}

            <div>
                <Label>{t('Welcome Message for Client')}</Label>
                <Textarea value={message} onChange={e => setMessage(e.target.value)} rows={3} className="mt-1"
                    placeholder={t('e.g. Welcome! This is your project portal. You can track progress and report issues here.')} />
            </div>

            <Button onClick={save}>{enabled && !project.portal_token ? t('Enable & Generate Link') : t('Save Settings')}</Button>
        </div>
    );
}

export default function ProjectShow() {
    const { t } = useTranslation();
    const { auth, project, budget = null, members, managers, clients, projectTasks = [], taskStages = [], projectBugs = [], projectTimesheets = [], canDeleteProject, userWorkspaceRole, attachmentFilters = {}, noteFilters = {}, activityFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];
    
    // Permission-based access control
    const canEditProject = hasPermission(permissions, 'project_update');
    const canManageProject = hasPermission(permissions, 'project_assign_members') || hasPermission(permissions, 'project_assign_clients') || hasPermission(permissions, 'project_manage_notes');
    const canManageBudget = hasPermission(permissions, 'project_manage_budget');
    const canManageAttachments = hasPermission(permissions, 'project_manage_attachments');
    const canManageNotes = hasPermission(permissions, 'project_manage_notes');
    const canTrackProgress = hasPermission(permissions, 'project_track_progress');
    
    const [activeTab, setActiveTab] = useState('overview');
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
    const [modalType, setModalType] = useState<'milestone' | 'note' | 'client' | 'member' | 'manager' | 'project' | 'attachment'>('milestone');
    const [isTimesheetModalOpen, setIsTimesheetModalOpen] = useState(false);
    const [selectedAttachments, setSelectedAttachments] = useState('');
    const [isUploadingAttachment, setIsUploadingAttachment] = useState(false);
    
    // Pagination states
    const [milestonesPage, setMilestonesPage] = useState(1);
    const [notesPage, setNotesPage] = useState(1);
    const [activityPage, setActivityPage] = useState(1);
    const [attachmentSearch, setAttachmentSearch] = useState(attachmentFilters?.attachment_search || '');
    const [attachmentsPerPage, setAttachmentsPerPage] = useState(attachmentFilters?.attachments_per_page || 12);
    const [notesSearch, setNotesSearch] = useState(noteFilters?.notes_search || '');
    const [notesPerPage, setNotesPerPage] = useState(noteFilters?.notes_per_page || 5);
    const [activitySearch, setActivitySearch] = useState(activityFilters?.activity_search || '');
    const [activityPerPage, setActivityPerPage] = useState(activityFilters?.activity_per_page || 10);
    const itemsPerPage = 5;
    
    // Reset pagination when switching tabs
    const handleTabChange = (value: string) => {
        setActiveTab(value);
        if (value === 'milestones') setMilestonesPage(1);
        if (value === 'notes') setNotesPage(1);
        if (value === 'activity') setActivityPage(1);
    };
    
    const handleAttachmentSearch = () => {
        const params: any = { attachments_page: 1 };
        if (attachmentSearch) params.attachment_search = attachmentSearch;
        if (attachmentsPerPage !== 12) params.attachments_per_page = attachmentsPerPage;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'attachmentFilters']
        });
    };
    
    const handleAttachmentPerPageChange = (value: string) => {
        const newPerPage = parseInt(value);
        setAttachmentsPerPage(newPerPage);
        
        const params: any = { attachments_page: 1, attachments_per_page: newPerPage };
        if (attachmentSearch) params.attachment_search = attachmentSearch;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'attachmentFilters']
        });
    };
    
    const handleNotesSearch = () => {
        const params: any = { notes_page: 1 };
        if (notesSearch) params.notes_search = notesSearch;
        if (notesPerPage !== 5) params.notes_per_page = notesPerPage;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'noteFilters']
        });
    };
    
    const handleNotesPerPageChange = (value: string) => {
        const newPerPage = parseInt(value);
        setNotesPerPage(newPerPage);
        
        const params: any = { notes_page: 1, notes_per_page: newPerPage };
        if (notesSearch) params.notes_search = notesSearch;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'noteFilters']
        });
    };
    
    const handleActivitySearch = () => {
        const params: any = { activity_page: 1 };
        if (activitySearch) params.activity_search = activitySearch;
        if (activityPerPage !== 10) params.activity_per_page = activityPerPage;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'activityFilters']
        });
    };
    
    const handleActivityPerPageChange = (value: string) => {
        const newPerPage = parseInt(value);
        setActivityPerPage(newPerPage);
        
        const params: any = { activity_page: 1, activity_per_page: newPerPage };
        if (activitySearch) params.activity_search = activitySearch;
        
        router.get(route('projects.show', project.id), params, { 
            preserveState: true, 
            preserveScroll: true,
            only: ['project', 'activityFilters']
        });
    };

    const getStatusColor = (status: string) => {
        const colors = {
            planning: 'bg-blue-100 text-blue-800',
            active: 'bg-green-100 text-green-800',
            on_hold: 'bg-yellow-100 text-yellow-800',
            completed: 'bg-gray-100 text-gray-800',
            cancelled: 'bg-red-100 text-red-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            low: 'bg-green-100 text-green-800',
            medium: 'bg-yellow-100 text-yellow-800',
            high: 'bg-orange-100 text-orange-800',
            urgent: 'bg-red-100 text-red-800'
        };
        return colors[priority as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const handleAction = (action: string, item: any = null, type: string = '') => {
        setCurrentItem(item);
        
        switch (action) {
            case 'add-milestone':
                setModalType('milestone');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'edit-milestone':
                setModalType('milestone');
                setFormMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete-milestone':
                setIsDeleteModalOpen(true);
                break;
            case 'add-note':
                setModalType('note');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'edit-note':
                setModalType('note');
                setFormMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete-note':
                setIsDeleteModalOpen(true);
                break;
            case 'invite-client':
                setModalType('client');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'invite-member':
                setModalType('member');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'invite-manager':
                setModalType('manager');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'toggle-pin':
                router.put(route('project-notes.toggle-pin', [project.id, item.id]));
                break;
            case 'add-attachment':
                setCurrentItem({ media_ids: [] });
                setModalType('attachment');
                setFormMode('create');
                setIsFormModalOpen(true);
                break;
            case 'delete-attachment':
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleFormSubmit = (formData: any) => {
        let routeName = '';
        let routeParams: any[] = [];
        
        switch (modalType) {
            case 'milestone':
                routeName = formMode === 'create' ? 'project-milestones.store' : 'project-milestones.update';
                routeParams = formMode === 'create' ? [project.id] : [project.id, currentItem.id];
                break;
            case 'note':
                routeName = formMode === 'create' ? 'project-notes.store' : 'project-notes.update';
                routeParams = formMode === 'create' ? [project.id] : [project.id, currentItem.id];
                break;
            case 'client':
                routeName = 'projects.assign-clients';
                routeParams = [project.id];
                break;
            case 'member':
                routeName = 'projects.assign-members';
                routeParams = [project.id];
                break;
            case 'manager':
                routeName = 'projects.assign-managers';
                routeParams = [project.id];
                break;
            case 'project':
                routeName = 'projects.update';
                routeParams = [project.id];
                break;
            case 'budget':
                routeName = 'projects.create-budget';
                routeParams = [project.id];
                break;
            case 'attachment':
                // Handle attachment upload - check both formData and currentItem for media_ids
                const mediaIds = formData.media_ids || currentItem?.media_ids || [];
                // Debug: Check available media IDs
                
                if (mediaIds && mediaIds.length > 0) {
                    setIsUploadingAttachment(true);
                    router.post(route('project-attachments.store', project.id), {
                        media_ids: mediaIds
                    }, {
                        onSuccess: () => {
                            setIsFormModalOpen(false);
                            setSelectedAttachments('');
                            setCurrentItem(null);
                            setIsUploadingAttachment(false);
                            toast.success('Attachments uploaded successfully');
                        },
                        onError: (errors) => {
                            setIsUploadingAttachment(false);
                            toast.error(`Failed: ${Object.values(errors).join(', ')}`);
                        }
                    });
                    return;
                }
                
                // Also check if selectedAttachments has URLs that we can extract media IDs from
                if (selectedAttachments) {
                    // Try to extract media IDs from URLs
                    const urlMediaIds = selectedAttachments.split(',').map(url => {
                        const match = url.trim().match(/\/media\/(\d+)/);
                        return match ? parseInt(match[1]) : null;
                    }).filter(Boolean);
                    
                    if (urlMediaIds.length > 0) {
                        setIsUploadingAttachment(true);
                        router.post(route('project-attachments.store', project.id), {
                            media_ids: urlMediaIds
                        }, {
                            onSuccess: () => {
                                setIsFormModalOpen(false);
                                setSelectedAttachments('');
                                setCurrentItem(null);
                                setIsUploadingAttachment(false);
                                toast.success('Attachments uploaded successfully');
                            },
                            onError: (errors) => {
                                setIsUploadingAttachment(false);
                                toast.error(`Failed: ${Object.values(errors).join(', ')}`);
                            }
                        });
                        return;
                    }
                }
                
                toast.error('Please select attachments to upload');
                return;
        }

        // For attachment uploads, handle differently
        if (modalType === 'attachment') {
            // This is handled in the attachment case above
            return;
        }
        
        const method = formMode === 'create' ? 'post' : 'put';
        toast.loading(`${formMode === 'create' ? 'Creating' : 'Updating'}...`);
        
        const routeUrl = routeParams.length === 2 ? 
            route(routeName, modalType === 'milestone' ? 
                { project: routeParams[0], milestone: routeParams[1] } :
                { project: routeParams[0], note: routeParams[1] }
            ) :
            route(routeName, routeParams[0]);
        
        router[method](routeUrl, formData, {
            onSuccess: () => {
                setIsFormModalOpen(false);
                toast.dismiss();
                toast.success(`${modalType} ${formMode === 'create' ? 'created' : 'updated'} successfully`);
            },
            onError: (errors) => {
                toast.dismiss();
                toast.error(`Failed: ${Object.values(errors).join(', ')}`);
            }
        });
    };

    const handleDeleteConfirm = () => {
        let routeName = '';
        let routeParams: any[] = [];
        
        switch (modalType) {
            case 'milestone':
                routeName = 'project-milestones.destroy';
                routeParams = [project.id, currentItem.id];
                break;
            case 'note':
                routeName = 'project-notes.destroy';
                routeParams = [project.id, currentItem.id];
                break;
            case 'attachment':
                routeName = 'project-attachments.destroy';
                routeParams = [currentItem.id];
                break;
            case 'member':
                routeName = 'projects.remove-member';
                routeParams = [project.id, currentItem.user?.id || currentItem.id];
                break;
            case 'client':
                routeName = 'projects.remove-client';
                routeParams = [project.id, currentItem.id];
                break;
            default:
                return;
        }
        
        toast.loading('Removing...');
        
        router.delete(route(routeName, routeParams), {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                toast.dismiss();
                toast.success(`${modalType === 'member' ? 'Team member' : modalType} removed successfully`);
            },
            onError: (errors) => {
                toast.dismiss();
                toast.error(`Failed to remove: ${Object.values(errors).join(', ')}`);
            }
        });
    };

    const getFormConfig = () => {
        switch (modalType) {
            case 'milestone':
                return {
                    fields: [
                        { name: 'title', label: 'Title', type: 'text', required: true },
                        { name: 'description', label: 'Description', type: 'textarea' },
                        { name: 'due_date', label: 'Due Date', type: 'date' },
                        { 
                            name: 'status', 
                            label: 'Status', 
                            type: 'select',
                            options: [
                                { value: 'pending', label: 'Pending' },
                                { value: 'in_progress', label: 'In Progress' },
                                { value: 'completed', label: 'Completed' },
                                { value: 'overdue', label: 'Overdue' }
                            ],
                            required: true
                        }
                    ],
                    modalSize: 'lg'
                };
            case 'note':
                return {
                    fields: [
                        { name: 'title', label: 'Title', type: 'text', required: true },
                        { name: 'content', label: 'Content', type: 'textarea', required: true },
                        { name: 'is_pinned', label: 'Pin this note', type: 'checkbox' }
                    ],
                    modalSize: 'lg'
                };
            case 'client':
                const availableClients = clients ? clients.filter((client: any) => 
                    !project.clients?.some((pc: any) => pc.id === client.id)
                ) : [];
                return {
                    fields: [
                        { 
                            name: 'client_ids', 
                            label: 'Add Clients', 
                            type: 'multi-select',
                            options: availableClients.map((client: any) => ({
                                value: client.id.toString(),
                                label: `${client.name} (${client.email})`
                            })),
                            placeholder: 'Select clients...',
                            required: true
                        }
                    ],
                    modalSize: 'xl'
                };
            case 'member':
                const availableMembers = members ? members.filter((member: any) => {
                    const isProjectMember = project.members?.some((pm: any) => pm.user?.id === member.id);
                    const isProjectClient = project.clients?.some((pc: any) => pc.id === member.id);
                    return !isProjectMember && !isProjectClient;
                }) : [];
                return {
                    fields: [
                        { 
                            name: 'member_ids', 
                            label: 'Add Members', 
                            type: 'multi-select',
                            options: availableMembers.map((member: any) => ({
                                value: member.id.toString(),
                                label: `${member.name} (${member.email})`
                            })),
                            placeholder: 'Select members...',
                            required: true
                        }
                    ],
                    modalSize: 'xl'
                };
            case 'manager':
                const availableManagers = managers ? managers.filter((manager: any) => {
                    const isProjectMember = project.members?.some((pm: any) => pm.user?.id === manager.id);
                    const isProjectClient = project.clients?.some((pc: any) => pc.id === manager.id);
                    return !isProjectMember && !isProjectClient;
                }) : [];
                
                return {
                    fields: [
                        { 
                            name: 'manager_ids', 
                            label: 'Add Managers', 
                            type: 'multi-select',
                            options: availableManagers.map((manager: any) => ({
                                value: manager.id.toString(),
                                label: `${manager.name} (${manager.email})`
                            })),
                            placeholder: 'Select managers...',
                            required: true
                        }
                    ],
                    modalSize: 'xl'
                };
            case 'project':
                return {
                    fields: [
                        { name: 'title', label: 'Project Title', type: 'text', required: true },
                        { name: 'description', label: 'Description', type: 'textarea' },
                        { 
                            name: 'status', 
                            label: 'Status', 
                            type: 'select',
                            options: [
                                { value: 'planning', label: 'Planning' },
                                { value: 'active', label: 'Active' },
                                { value: 'on_hold', label: 'On Hold' },
                                { value: 'completed', label: 'Completed' },
                                { value: 'cancelled', label: 'Cancelled' }
                            ],
                            required: true
                        },
                        { 
                            name: 'priority', 
                            label: 'Priority', 
                            type: 'select',
                            options: [
                                { value: 'low', label: 'Low' },
                                { value: 'medium', label: 'Medium' },
                                { value: 'high', label: 'High' },
                                { value: 'urgent', label: 'Urgent' }
                            ],
                            required: true
                        },
                        { name: 'start_date', label: 'Start Date', type: 'date' },
                        { name: 'deadline', label: 'Deadline', type: 'date' },
                        { name: 'estimated_hours', label: 'Estimated Hours', type: 'number', min: 0 },
                        { name: 'is_public', label: 'Make project public', type: 'checkbox' }
                    ],
                    modalSize: 'xl'
                };
            case 'budget':
                return {
                    fields: [
                        { name: 'total_budget', label: 'Total Budget', type: 'number', required: true, min: 0 },
                        { 
                            name: 'currency', 
                            label: 'Currency', 
                            type: 'select',
                            options: [
                                { value: 'USD', label: 'USD' },
                                { value: 'EUR', label: 'EUR' },
                                { value: 'GBP', label: 'GBP' }
                            ],
                            required: true
                        },
                        { 
                            name: 'period_type', 
                            label: 'Period Type', 
                            type: 'select',
                            options: [
                                { value: 'project', label: 'Project Duration' },
                                { value: 'monthly', label: 'Monthly' },
                                { value: 'quarterly', label: 'Quarterly' }
                            ],
                            required: true
                        },
                        { name: 'start_date', label: 'Start Date', type: 'date', required: true },
                        { name: 'end_date', label: 'End Date', type: 'date' },
                        { name: 'description', label: 'Description', type: 'textarea' },
                        {
                            name: 'categories',
                            label: 'Budget Categories',
                            type: 'dynamic-list',
                            fields: [
                                { name: 'name', label: 'Category Name', type: 'text', required: true },
                                { name: 'allocated_amount', label: 'Allocated Amount', type: 'number', required: true, min: 0 },
                                { name: 'color', label: 'Color', type: 'color', defaultValue: '#3B82F6' },
                                { name: 'description', label: 'Description', type: 'text' }
                            ],
                            required: true,
                            minItems: 1
                        }
                    ],
                    modalSize: 'xl'
                };
            case 'attachment':
                return {
                    fields: [
                        {
                            name: 'media_ids',
                            type: 'custom',
                            component: (
                                <MediaPicker
                                    label="Select Files"
                                    value={selectedAttachments}
                                    onChange={(value, mediaIds) => {
                                        // Store selected attachments
                                        setSelectedAttachments(value);
                                        // Store media IDs for form submission
                                        if (mediaIds && mediaIds.length > 0) {
                                            setCurrentItem(prev => ({ ...prev, media_ids: mediaIds }));
                                        }
                                    }}
                                    multiple={true}
                                    placeholder="Select files to upload..."
                                    showPreview={true}
                                />
                            ),
                            required: true
                        }
                    ],
                    modalSize: 'lg'
                };
            default:
                return { fields: [], modalSize: 'md' };
        }
    };

    const pageActions = [
        ...(hasPermission(permissions, 'task_view_any') ? [{
            label: t('Tasks'),
            icon: <CheckSquare className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('tasks.index', { project_id: project.id, project_name: project.title, view: 'kanban' }))
        }] : []),
        ...(hasPermission(permissions, 'bug_view_any') ? [{
            label: t('Bugs'),
            icon: <Bug className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('bugs.index', { project_id: project.id, project_name: project.title }))
        }] : []),
        ...(hasPermission(permissions, 'expense_view_any') ? [{
            label: t('Expenses'),
            icon: <Receipt className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('expenses.index', { project_id: project.id, project_name: project.title }))
        }] : []),
        ...(canManageBudget ? [{
            label: t('Budget'),
            icon: <DollarSign className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => {
                if (budget) {
                    router.get(route('budgets.show', budget.id));
                } else {
                    router.get(route('budgets.index'));
                }
            }
        }] : [])
    ];
    
    if (canEditProject) {
        pageActions.push({
            label: t('Edit Project'),
            icon: <Edit className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => {
                setCurrentItem(project);
                setModalType('project');
                setFormMode('edit');
                setIsFormModalOpen(true);
            }
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Projects'), href: route('projects.index') },
        { title: project.title }
    ];

    return (
        <PageTemplate 
            title={project.title}
            url={`/projects/${project.id}`}
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Project Header */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-6">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <div className="flex gap-2">
                                <Badge className={getStatusColor(project.status)}>
                                    {project.status.replace('_', ' ')}
                                </Badge>
                                <Badge className={getPriorityColor(project.priority)}>
                                    {project.priority}
                                </Badge>
                                {project.is_public ? (
                                    <Badge variant="outline" className="text-green-600 border-green-600">
                                        {t('Public Project')}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="text-gray-600 border-gray-600">
                                        {t('Private Project')}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Project Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Users className="h-4 w-4 text-blue-500" />
                                    <span className="text-sm font-medium">Team Members</span>
                                </div>
                                <p className="text-2xl font-bold">{project.members?.length || 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-green-500" />
                                    <span className="text-sm font-medium">Deadline</span>
                                </div>
                                <p className="text-lg font-semibold">{new Date(project.deadline).toLocaleDateString()}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <DollarSign className="h-4 w-4 text-yellow-500" />
                                    <span className="text-sm font-medium">Budget</span>
                                </div>
                                <p className="text-2xl font-bold">{formatCurrency(budget?.total_budget || 0)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-purple-500" />
                                    <span className="text-sm font-medium">Est. Hours</span>
                                </div>
                                <p className="text-2xl font-bold">{project.estimated_hours}h</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Progress */}
                    <Card>
                        <CardContent className="p-4">
                            <div className="space-y-2">
                                <div className="flex justify-between items-center">
                                    <span className="font-medium">{t('Project Progress')}</span>
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold">{project.progress}%</span>
                                        {hasPermission(permissions, 'project_track_progress') && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            toast.loading('Recalculating progress...');
                                                            router.post(route('projects.recalculate-progress', project.id), {}, {
                                                                onSuccess: () => {
                                                                    toast.dismiss();
                                                                    toast.success('Progress recalculated successfully');
                                                                },
                                                                onError: () => {
                                                                    toast.dismiss();
                                                                    toast.error('Failed to recalculate progress');
                                                                }
                                                            });
                                                        }}
                                                        className="h-6 px-2 text-xs"
                                                    >
                                                        <BarChart3 className="h-3 w-3" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('Recalculate Progress')}</TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </div>
                                <Progress value={project.progress} className="h-2" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* AI Health Score + Scope Creep are internal delivery metrics - not for clients */}
                    {userWorkspaceRole !== 'client' && (
                        <>
                            <ProjectHealthWidget projectId={project.id} />
                            <ScopeCreepWidget projectId={project.id} />
                        </>
                    )}
                </div>
            </div>

            {/* Tabs Content */}
            <div className="bg-white rounded-lg shadow">
                <Tabs value={activeTab} onValueChange={handleTabChange} className="relative">
                    <div className="border-b bg-gradient-to-r from-gray-50 to-blue-50 relative z-10">
                        <TabsList className="h-auto min-h-12 bg-white/80 backdrop-blur-sm border-0 rounded-none p-2 shadow-none relative z-20 flex-wrap w-full justify-center gap-1">
                            {/* Primary tabs — always visible. Everything else lives under "More" to
                                keep the bar to a single row (avoids the old wrap-and-overlap bug) and
                                cut down on visual clutter from having 14 top-level tabs. */}
                            {hasPermission(permissions, 'project_view') && (
                                <TabsTrigger value="overview" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary text-gray-600">
                                    <User className="h-4 w-4 mr-2" />
                                    Overview
                                </TabsTrigger>
                            )}
                            {hasPermission(permissions, 'task_view_any') && (
                                <TabsTrigger value="tasks" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary text-gray-600">
                                    <CheckSquare className="h-4 w-4 mr-2" />
                                    Tasks
                                </TabsTrigger>
                            )}
                            {hasPermission(permissions, 'project_assign_members') && (
                                <TabsTrigger value="team" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary text-gray-600">
                                    <Users className="h-4 w-4 mr-2" />
                                    Team
                                </TabsTrigger>
                            )}
                            {hasPermission(permissions, 'project_manage_milestones') && (
                                <TabsTrigger value="milestones" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary text-gray-600">
                                    <Calendar className="h-4 w-4 mr-2" />
                                    Milestones
                                </TabsTrigger>
                            )}
                            {hasPermission(permissions, 'project_view') && (
                                <TabsTrigger value="activity" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary text-gray-600">
                                    <Clock className="h-4 w-4 mr-2" />
                                    Activity
                                </TabsTrigger>
                            )}

                            {(() => {
                                const secondaryTabs = [
                                    { value: 'notes', label: 'Notes', icon: Pin, show: hasPermission(permissions, 'project_manage_notes') },
                                    { value: 'budget', label: 'Budget', icon: DollarSign, show: hasPermission(permissions, 'project_manage_budget') },
                                    { value: 'expense', label: 'Expense', icon: CreditCard, show: hasPermission(permissions, 'expense_view_any') },
                                    { value: 'scrum', label: 'Scrum', icon: Columns, show: hasPermission(permissions, 'task_view_any') },
                                    { value: 'gantt', label: 'Gantt', icon: BarChart3, show: hasPermission(permissions, 'task_view_any') },
                                    { value: 'sprints', label: 'Sprints', icon: Play, show: hasPermission(permissions, 'sprint_view_any') },
                                    { value: 'portal', label: 'Client Portal', icon: Globe, show: false },
                                    { value: 'bugs', label: 'Bugs', icon: Bug, show: hasPermission(permissions, 'bug_view_any') },
                                    { value: 'timesheet', label: 'Timesheet', icon: Timer, show: hasPermission(permissions, 'timesheet_view_any') },
                                    { value: 'attachments', label: 'Attachments', icon: Paperclip, show: hasPermission(permissions, 'project_manage_attachments') },
                                ].filter(tab => tab.show);
                                const activeSecondary = secondaryTabs.find(tab => tab.value === activeTab);

                                if (secondaryTabs.length === 0) return null;

                                return (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                type="button"
                                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 hover:bg-primary/10 hover:text-primary flex items-center ${activeSecondary ? 'bg-primary text-primary-foreground shadow-lg' : 'text-gray-600'}`}
                                            >
                                                {activeSecondary ? <activeSecondary.icon className="h-4 w-4 mr-2" /> : <MoreHorizontal className="h-4 w-4 mr-2" />}
                                                {activeSecondary ? activeSecondary.label : 'More'}
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="center">
                                            {secondaryTabs.map(tab => (
                                                <DropdownMenuItem key={tab.value} onClick={() => handleTabChange(tab.value)}>
                                                    <tab.icon className="h-4 w-4 mr-2" />
                                                    {tab.label}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                );
                            })()}
                        </TabsList>
                    </div>

                    <div className="p-4 relative overflow-visible z-0">
                        <TabsContent value="overview" className="space-y-6 mt-0">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Project Description</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-gray-700 leading-relaxed">{project.description || 'No description provided.'}</p>
                                </CardContent>
                            </Card>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Calendar className="h-5 w-5 text-blue-500" />
                                            Project Timeline
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Start Date:</span>
                                            <span className="font-medium">{project.start_date ? new Date(project.start_date).toLocaleDateString() : 'Not set'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Deadline:</span>
                                            <span className="font-medium">{project.deadline ? new Date(project.deadline).toLocaleDateString() : 'Not set'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Estimated Hours:</span>
                                            <span className="font-medium">{project.estimated_hours || 0}h</span>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Users className="h-5 w-5 text-green-500" />
                                            Team Summary
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Managers:</span>
                                            <span className="font-medium">{project.members?.filter((member: any) => member.role === 'manager').length || 0}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Team Members:</span>
                                            <span className="font-medium">{project.members?.filter((member: any) => member.role === 'member').length || 0}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Clients:</span>
                                            <span className="font-medium">{project.clients?.length || 0}</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Project Reports */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Timer className="h-5 w-5 text-blue-500" />
                                            Time Tracking Summary
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Total Hours Logged:</span>
                                                <span className="font-semibold">
                                                    {Number(project.total_project_hours || 0).toFixed(1)}h
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Billable Hours:</span>
                                                <span className="font-semibold text-green-600">
                                                    {Number(project.total_billable_hours || 0).toFixed(1)}h
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Team Members Active:</span>
                                                <span className="font-semibold">
                                                    {project.total_team_members || 0}
                                                </span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <BarChart3 className="h-5 w-5 text-purple-500" />
                                            Project Reports
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Total Milestones:</span>
                                                <span className="font-semibold">{project.milestones?.length || 0}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Notes Created:</span>
                                                <span className="font-semibold text-blue-600">
                                                    {project.notes?.total || 0}
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Attachments:</span>
                                                <span className="font-semibold text-green-600">
                                                    {project.attachments?.total || 0}
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Activities:</span>
                                                <span className="font-semibold text-orange-600">
                                                    {project.activities?.total || 0}
                                                </span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5 text-yellow-500" />
                                            Budget Utilization
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Total Budget:</span>
                                                <span className="font-semibold">{formatCurrency(budget?.total_budget || 0)}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Amount Spent:</span>
                                                <span className="font-semibold text-red-600">{formatCurrency(budget?.total_spent || 0)}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Remaining:</span>
                                                <span className="font-semibold text-green-600">{formatCurrency(budget?.remaining_budget || 0)}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Utilization:</span>
                                                <span className="font-semibold">{budget?.utilization_percentage?.toFixed(1) || '0.0'}%</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Users className="h-5 w-5 text-purple-500" />
                                            Team Performance
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Team Size:</span>
                                                <span className="font-semibold">{project.team_size ?? project.members?.length ?? 0}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Active Members:</span>
                                                <span className="font-semibold text-green-600">
                                                    {project.total_team_members || 0}
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Avg Hours/Member:</span>
                                                <span className="font-semibold">
                                                    {Number(project.avg_hours_per_member || 0).toFixed(1)}h
                                                </span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-gray-600">Project Progress:</span>
                                                <span className="font-semibold">{project.progress || 0}%</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        <TabsContent value="team" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Team & Clients</h3>
                                <div className="flex gap-2">
                                    {hasPermission(permissions, 'project_assign_clients') && (
                                        <Button size="sm" onClick={() => handleAction('invite-client')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Client
                                        </Button>
                                    )}
                                    {hasPermission(permissions, 'project_assign_members') && (
                                        <Button size="sm" onClick={() => handleAction('invite-member')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Member
                                        </Button>
                                    )}
                                    {hasPermission(permissions, 'project_assign_members') && (
                                        <Button size="sm" onClick={() => handleAction('invite-manager')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Manager
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {/* Project Managers */}
                            {project.members?.filter((member: any) => member.role === 'manager').length > 0 && (
                                <div>
                                    <h4 className="font-medium mb-3">Managers ({project.members.filter((member: any) => member.role === 'manager').length})</h4>
                                    <div className="grid gap-3">
                                        {project.members.filter((member: any) => member.role === 'manager').map((member: any) => (
                                            <Card key={member.user.id}>
                                                <CardContent className="p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar>
                                                                <AvatarImage src={member.user.avatar} />
                                                                <AvatarFallback>{member.user.name.charAt(0)}</AvatarFallback>
                                                            </Avatar>
                                                            <div>
                                                                <p className="font-medium">{member.user.name}</p>
                                                                <p className="text-sm text-gray-500">{member.user.email}</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="secondary" className="bg-blue-100 text-blue-800">{member.role}</Badge>
                                                            {canManageProject && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        setCurrentItem({ ...member, type: 'manager' });
                                                                        setModalType('member');
                                                                        setIsDeleteModalOpen(true);
                                                                    }}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Project Members */}
                            {project.members?.filter((member: any) => member.role === 'member').length > 0 && (
                                <div>
                                    <h4 className="font-medium mb-3">Team Members ({project.members.filter((member: any) => member.role === 'member').length})</h4>
                                    <div className="grid gap-3">
                                        {project.members.filter((member: any) => member.role === 'member').map((member: any) => (
                                            <Card key={member.user.id}>
                                                <CardContent className="p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar>
                                                                <AvatarImage src={member.user.avatar} />
                                                                <AvatarFallback>{member.user.name.charAt(0)}</AvatarFallback>
                                                            </Avatar>
                                                            <div>
                                                                <p className="font-medium">{member.user.name}</p>
                                                                <p className="text-sm text-gray-500">{member.user.email}</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="secondary">{member.role}</Badge>
                                                            {canManageProject && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        setCurrentItem({ ...member, type: 'member' });
                                                                        setModalType('member');
                                                                        setIsDeleteModalOpen(true);
                                                                    }}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Project Clients */}
                            {project.clients?.length > 0 && (
                                <div>
                                    <h4 className="font-medium mb-3">Clients ({project.clients.length})</h4>
                                    <div className="grid gap-3">
                                        {project.clients.map((client: any) => (
                                            <Card key={client.id}>
                                                <CardContent className="p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar>
                                                                <AvatarImage src={client.avatar} />
                                                                <AvatarFallback>{client.name.charAt(0)}</AvatarFallback>
                                                            </Avatar>
                                                            <div>
                                                                <p className="font-medium">{client.name}</p>
                                                                <p className="text-sm text-gray-500">{client.email}</p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="outline">client</Badge>
                                                            {canManageProject && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        setCurrentItem({ ...client, type: 'client' });
                                                                        setModalType('client');
                                                                        setIsDeleteModalOpen(true);
                                                                    }}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Empty State */}
                            {(!project.members?.length && !project.clients?.length) && (
                                <div className="text-center py-8 text-gray-500">
                                    <Users className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <p>No managers, team members or clients assigned yet.</p>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="milestones" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Milestones</h3>
                                {hasPermission(permissions, 'project_manage_milestones') && (
                                    <Button size="sm" onClick={() => handleAction('add-milestone')}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        Add Milestone
                                    </Button>
                                )}
                            </div>

                            {project.milestones && project.milestones.length > 0 ? (
                                <div className="space-y-4">
                                    {project.milestones.slice((milestonesPage - 1) * itemsPerPage, milestonesPage * itemsPerPage).map((milestone: any) => (
                                        <Card key={milestone.id}>
                                            <CardContent className="p-4">
                                                <div className="flex justify-between items-start">
                                                    <div className="flex-1">
                                                        <h4 className="font-medium">{milestone.title}</h4>
                                                        <p className="text-sm text-gray-600 mt-1">{milestone.description}</p>
                                                        <div className="flex items-center gap-4 mt-2">
                                                            <Badge variant="outline">{milestone.status}</Badge>
                                                            <span className="text-sm text-gray-500">
                                                                Due: {milestone.due_date ? new Date(milestone.due_date).toLocaleDateString() : t('No due date')}
                                                            </span>
                                                        </div>
                                                        <div className="mt-2">
                                                            <Progress value={milestone.progress} className="h-1" />
                                                        </div>
                                                    </div>
                                                    {canManageProject && (
                                                        <div className="flex gap-1">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button 
                                                                        variant="ghost" 
                                                                        size="icon"
                                                                        onClick={() => handleAction('edit-milestone', milestone)}
                                                                    >
                                                                        <Edit className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Edit</TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button 
                                                                        variant="ghost" 
                                                                        size="icon"
                                                                        onClick={() => {
                                                                            setModalType('milestone');
                                                                            handleAction('delete-milestone', milestone);
                                                                        }}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Delete</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <Calendar className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <p>No milestones created yet.</p>
                                    {canManageProject && (
                                        <Button className="mt-4" onClick={() => handleAction('add-milestone')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add First Milestone
                                        </Button>
                                    )}
                                </div>
                            )}
                            
                            {project.milestones && project.milestones.length > itemsPerPage && (
                                <SimplePagination
                                    currentPage={milestonesPage}
                                    totalPages={Math.ceil(project.milestones.length / itemsPerPage)}
                                    onPageChange={setMilestonesPage}
                                />
                            )}
                        </TabsContent>

                        <TabsContent value="notes" className="space-y-6 mt-0">
                            <div className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-semibold">Notes</h3>
                                    {hasPermission(permissions, 'project_manage_notes') && (
                                        <Button size="sm" onClick={() => handleAction('add-note')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Note
                                        </Button>
                                    )}
                                </div>
                                
                                {/* Search and Filter Bar */}
                                <div className="bg-gray-50 rounded-lg p-4 border">
                                    <div className="flex items-center justify-between gap-4">
                                        <form onSubmit={(e) => { e.preventDefault(); handleNotesSearch(); }} className="flex gap-2 flex-1">
                                            <div className="relative flex-1 max-w-md">
                                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                                <Input
                                                    placeholder="Search by title or content..."
                                                    value={notesSearch}
                                                    onChange={(e) => setNotesSearch(e.target.value)}
                                                    className="pl-10 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                                                />
                                            </div>
                                            <Button type="submit" size="sm" className="bg-blue-600 hover:bg-blue-700">
                                                <Search className="h-4 w-4 mr-1.5" />
                                                Search
                                            </Button>
                                            {notesSearch && (
                                                <Button 
                                                    type="button" 
                                                    variant="outline" 
                                                    size="sm"
                                                    onClick={() => {
                                                        setNotesSearch('');
                                                        router.get(route('projects.show', project.id), {}, { 
                                                            preserveState: true, 
                                                            preserveScroll: true,
                                                            only: ['project', 'noteFilters']
                                                        });
                                                    }}
                                                >
                                                    Clear
                                                </Button>
                                            )}
                                        </form>
                                        
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-600 whitespace-nowrap">Per Page:</span>
                                            <Select 
                                                value={notesPerPage.toString()} 
                                                onValueChange={handleNotesPerPageChange}
                                            >
                                                <SelectTrigger className="w-20 h-9 bg-white border-gray-200">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="5">5</SelectItem>
                                                    <SelectItem value="10">10</SelectItem>
                                                    <SelectItem value="20">20</SelectItem>
                                                    <SelectItem value="50">50</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {project.notes && project.notes.data?.length > 0 ? (
                                <>
                                    <div className="space-y-4">
                                        {project.notes.data.map((note: any) => (
                                            <Card key={note.id}>
                                                <CardContent className="p-4">
                                                    <div className="flex justify-between items-start">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <h4 className="font-medium">{note.title}</h4>
                                                                {note.is_pinned && <Pin className="h-4 w-4 text-yellow-500" />}
                                                            </div>
                                                            <p className="text-sm text-gray-600 mt-2">{note.content}</p>
                                                            <div className="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                                                <span>By {note.creator.name}</span>
                                                                <span>•</span>
                                                                <span>{new Date(note.created_at).toLocaleDateString()}</span>
                                                            </div>
                                                        </div>
                                                        <div className="flex gap-1">
                                                            {canManageProject && (
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() => handleAction('toggle-pin', note)}
                                                                        >
                                                                            <Pin className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Toggle Pin</TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                            {canManageProject && (
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button 
                                                                            variant="ghost" 
                                                                            size="icon"
                                                                            onClick={() => handleAction('edit-note', note)}
                                                                        >
                                                                            <Edit className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Edit</TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                            {canManageProject && (
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button 
                                                                            variant="ghost" 
                                                                            size="icon"
                                                                            onClick={() => {
                                                                                setModalType('note');
                                                                                handleAction('delete-note', note);
                                                                            }}
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Delete</TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                    
                                    {/* Pagination */}
                                    {project.notes.links && (
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="text-sm text-gray-500">
                                                Showing {project.notes.from || 0} to {project.notes.to || 0} of {project.notes.total || 0} notes
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                {project.notes.links.map((link: any, i: number) => {
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
                                                                if (link.url) {
                                                                    const url = new URL(link.url);
                                                                    const params: any = {};
                                                                    url.searchParams.forEach((value, key) => {
                                                                        params[key] = value;
                                                                    });
                                                                    router.get(route('projects.show', project.id), params, { 
                                                                        preserveState: true, 
                                                                        preserveScroll: true,
                                                                        only: ['project', 'noteFilters']
                                                                    });
                                                                }
                                                            }}
                                                        >
                                                            {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <Pin className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                        {notesSearch ? 'No notes found' : 'No notes created yet'}
                                    </h3>
                                    <p className="text-gray-500 mb-6 max-w-sm mx-auto">
                                        {notesSearch ? 'Try adjusting your search terms or ' : 'Create notes to keep track of important information.'}
                                        {notesSearch && (
                                            <Button variant="link" className="p-0 h-auto" onClick={() => {
                                                setNotesSearch('');
                                                router.get(route('projects.show', project.id), {}, { 
                                                    preserveState: true, 
                                                    preserveScroll: true,
                                                    only: ['project', 'noteFilters']
                                                });
                                            }}>
                                                clear search
                                            </Button>
                                        )}
                                    </p>
                                    {!notesSearch && canManageProject && (
                                        <Button className="mt-4" onClick={() => handleAction('add-note')}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add First Note
                                        </Button>
                                    )}
                                </div>
                            )}
                        </TabsContent>

                        {canManageBudget && (
                        <TabsContent value="budget" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Budget Management</h3>
                                {budget && (
                                    <Button size="sm" onClick={() => router.get(route('budgets.show', budget.id))}>
                                        <Eye className="h-4 w-4 mr-2" />
                                        Show Budget
                                    </Button>
                                )}
                            </div>

                            {budget ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <DollarSign className="h-4 w-4 text-green-500" />
                                                    <span className="text-sm font-medium">Total Budget</span>
                                                </div>
                                                <p className="text-2xl font-bold">{formatCurrency(budget.total_budget || 0)}</p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <Receipt className="h-4 w-4 text-blue-500" />
                                                    <span className="text-sm font-medium">Total Spent</span>
                                                </div>
                                                <p className="text-2xl font-bold">{formatCurrency(budget.total_spent || 0)}</p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <Clock className="h-4 w-4 text-orange-500" />
                                                    <span className="text-sm font-medium">Remaining</span>
                                                </div>
                                                <p className="text-2xl font-bold">{formatCurrency(budget.remaining_budget || 0)}</p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                    
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Budget Progress</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-2">
                                                <div className="flex justify-between">
                                                    <span>Utilization</span>
                                                    <span>{budget.utilization_percentage?.toFixed(1) || 0}%</span>
                                                </div>
                                                <Progress value={budget.utilization_percentage || 0} className="h-2" />
                                            </div>
                                        </CardContent>
                                    </Card>
                                    
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Recent Expenses</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {budget.expenses?.length > 0 ? (
                                                <div className="space-y-3">
                                                    {budget.expenses.slice(0, 3).map((expense: any) => (
                                                        <div key={expense.id} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                                                            <div>
                                                                <p className="font-medium">{expense.title}</p>
                                                                <p className="text-sm text-gray-500">{expense.submitter?.name}</p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-bold">{formatCurrency(expense.amount)}</p>
                                                                <Badge variant="outline">{expense.status}</Badge>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-gray-500 text-center py-4">No expenses recorded yet</p>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="p-8 text-center">
                                        <DollarSign className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                        <h3 className="text-lg font-semibold mb-2">No Budget Set</h3>
                                        <p className="text-gray-500 mb-4">Create a budget to track project expenses and spending.</p>
                                        {canManageBudget && (
                                            <Button onClick={() => router.get(route('budgets.index'))}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Create Budget
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                        )}

                        <TabsContent value="expense" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Project Expenses</h3>
                                <Button size="sm" onClick={() => router.get(route('expenses.index', { project_id: project.id, project_name: project.title }))}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    Manage Expenses
                                </Button>
                            </div>

                            {budget?.expenses?.length > 0 ? (
                                <div className="space-y-4">
                                    {/* Expense Stats */}
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <CreditCard className="h-4 w-4 text-blue-500" />
                                                    <span className="text-sm font-medium">Total Expenses</span>
                                                </div>
                                                <p className="text-2xl font-bold">{budget.expenses.length}</p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                                    <span className="text-sm font-medium">Approved</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {budget.expenses.filter((expense: any) => expense.status === 'approved').length}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <Clock className="h-4 w-4 text-yellow-500" />
                                                    <span className="text-sm font-medium">Pending</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {budget.expenses.filter((expense: any) => expense.status === 'pending').length}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <DollarSign className="h-4 w-4 text-purple-500" />
                                                    <span className="text-sm font-medium">Total Amount</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {formatCurrency(budget.expenses.reduce((total: number, expense: any) => total + parseFloat(expense.amount || 0), 0))}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Recent Expenses */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Recent Expenses</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-3">
                                                {budget.expenses.slice(0, 5).map((expense: any) => (
                                                    <div key={expense.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex-1">
                                                                    <h4 className="font-medium text-sm">{expense.title}</h4>
                                                                    <p className="text-xs text-gray-500 mt-1">
                                                                        {expense.description || 'No description'}
                                                                    </p>
                                                                    <div className="flex items-center gap-2 mt-1">
                                                                        <span className="text-xs text-gray-400">By {expense.submitter?.name}</span>
                                                                        <span className="text-xs text-gray-400">•</span>
                                                                        <span className="text-xs text-gray-400">{new Date(expense.created_at).toLocaleDateString()}</span>
                                                                        {expense.category && (
                                                                            <>
                                                                                <span className="text-xs text-gray-400">•</span>
                                                                                <Badge variant="outline" className="text-xs">{expense.category}</Badge>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <div className="text-right">
                                                                    <p className="text-lg font-bold">{formatCurrency(expense.amount)}</p>
                                                                    <Badge variant="outline" className={`text-xs ${
                                                                        expense.status === 'approved' ? 'bg-green-100 text-green-800 border-green-200' :
                                                                        expense.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' :
                                                                        expense.status === 'rejected' ? 'bg-red-100 text-red-800 border-red-200' :
                                                                        'bg-gray-100 text-gray-800 border-gray-200'
                                                                    }`}>
                                                                        {expense.status}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {budget.expenses.length > 5 && (
                                                <div className="mt-4 text-center">
                                                    <Button variant="outline" size="sm" onClick={() => router.get(route('expenses.index', { project_id: project.id }))}>
                                                        View All {budget.expenses.length} Expenses
                                                    </Button>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="p-8 text-center">
                                        <CreditCard className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                        <h3 className="text-lg font-semibold mb-2">No Expenses Recorded</h3>
                                        <p className="text-gray-500 mb-4">Track project expenses and spending for better budget management.</p>
                                        <Button onClick={() => router.get(route('expenses.index', { project_id: project.id }))}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Manage Expenses
                                        </Button>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>



                        <TabsContent value="tasks" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Project Tasks</h3>
                                <div className="flex gap-2">
                                    <Button size="sm" onClick={() => router.get(route('tasks.index', { project_id: project.id, project_name: project.title }))}>
                                        <Eye className="h-4 w-4 mr-2" />
                                        Manage Tasks
                                    </Button>
                                </div>
                            </div>

                            {projectTasks && projectTasks.length > 0 ? (
                                <div className="space-y-6">
                                    {/* Task Stats - Always 4 Cards */}
                                    {(() => {
                                        const tasksByStage = projectTasks.reduce((acc: any, task: any) => {
                                            const stageName = task.task_stage?.name || 'No Stage';
                                            acc[stageName] = (acc[stageName] || 0) + 1;
                                            return acc;
                                        }, {});
                                        
                                        const totalTasks = projectTasks.length;
                                        const completedTasks = projectTasks.filter((task: any) => task.task_stage?.name === 'Completed' || task.task_stage?.name === 'Done').length;
                                        const inProgressTasks = projectTasks.filter((task: any) => task.task_stage?.name === 'In Progress' || task.task_stage?.name === 'Working').length;
                                        const pendingTasks = totalTasks - completedTasks - inProgressTasks;
                                        
                                        return (
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                {/* Total Tasks Card */}
                                                <Card>
                                                    <CardContent className="p-4">
                                                        <div className="flex items-center gap-2 mb-2">
                                                            <CheckSquare className="h-4 w-4 text-blue-500" />
                                                            <span className="text-sm font-medium">Total Tasks</span>
                                                        </div>
                                                        <p className="text-2xl font-bold">{totalTasks}</p>
                                                    </CardContent>
                                                </Card>
                                                
                                                {/* Completed Tasks Card */}
                                                <Card>
                                                    <CardContent className="p-4">
                                                        <div className="flex items-center gap-2 mb-2">
                                                            <CheckSquare className="h-4 w-4 text-green-500" />
                                                            <span className="text-sm font-medium">Completed</span>
                                                        </div>
                                                        <p className="text-2xl font-bold">{completedTasks}</p>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {totalTasks > 0 ? ((completedTasks / totalTasks) * 100).toFixed(1) : 0}% of total
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                                
                                                {/* In Progress Tasks Card */}
                                                <Card>
                                                    <CardContent className="p-4">
                                                        <div className="flex items-center gap-2 mb-2">
                                                            <CheckSquare className="h-4 w-4 text-orange-500" />
                                                            <span className="text-sm font-medium">In Progress</span>
                                                        </div>
                                                        <p className="text-2xl font-bold">{inProgressTasks}</p>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {totalTasks > 0 ? ((inProgressTasks / totalTasks) * 100).toFixed(1) : 0}% of total
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                                
                                                {/* Pending Tasks Card */}
                                                <Card>
                                                    <CardContent className="p-4">
                                                        <div className="flex items-center gap-2 mb-2">
                                                            <CheckSquare className="h-4 w-4 text-purple-500" />
                                                            <span className="text-sm font-medium">Pending</span>
                                                        </div>
                                                        <p className="text-2xl font-bold">{pendingTasks}</p>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {totalTasks > 0 ? ((pendingTasks / totalTasks) * 100).toFixed(1) : 0}% of total
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        );
                                    })()}

                                    {/* Recent Tasks */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Recent Tasks</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-3">
                                                {projectTasks.slice(0, 5).map((task: any) => (
                                                    <div key={task.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex-1">
                                                                    <h4 className="font-medium text-sm">{task.title}</h4>
                                                                    <p className="text-xs text-gray-500 mt-1 line-clamp-1">{task.description || 'No description'}</p>
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    <Badge 
                                                                        variant="outline" 
                                                                        className={`text-xs ${
                                                                            task.priority === 'critical' ? 'bg-red-100 text-red-800 border-red-200' :
                                                                            task.priority === 'high' ? 'bg-orange-100 text-orange-800 border-orange-200' :
                                                                            task.priority === 'medium' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' :
                                                                            'bg-green-100 text-green-800 border-green-200'
                                                                        }`}
                                                                    >
                                                                        {task.priority}
                                                                    </Badge>
                                                                    <Badge 
                                                                        variant="outline" 
                                                                        style={{ 
                                                                            backgroundColor: task.task_stage?.color + '20', 
                                                                            borderColor: task.task_stage?.color,
                                                                            color: task.task_stage?.color
                                                                        }}
                                                                        className="text-xs"
                                                                    >
                                                                        {task.task_stage?.name}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center justify-between mt-2">
                                                                <div className="flex items-center gap-2">
                                                                    {task.assigned_to ? (
                                                                        <div className="flex items-center gap-1">
                                                                            <Avatar className="h-5 w-5">
                                                                                <AvatarImage src={task.assigned_to.avatar} />
                                                                                <AvatarFallback className="text-xs">
                                                                                    {task.assigned_to.name?.charAt(0)}
                                                                                </AvatarFallback>
                                                                            </Avatar>
                                                                            <span className="text-xs text-gray-600">{task.assigned_to.name}</span>
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-xs text-gray-400">Unassigned</span>
                                                                    )}
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    <div className="flex items-center gap-1">
                                                                        <div className="w-12 bg-gray-200 rounded-full h-1">
                                                                            <div 
                                                                                className="bg-blue-600 h-1 rounded-full" 
                                                                                style={{width: `${task.progress}%`}}
                                                                            ></div>
                                                                        </div>
                                                                        <span className="text-xs text-gray-600">{task.progress}%</span>
                                                                    </div>
                                                                    <span className="text-xs text-gray-500">
                                                                        {task.end_date ? new Date(task.end_date).toLocaleDateString() : 'No due date'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {projectTasks.length > 5 && (
                                                <div className="mt-4 text-center">
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        onClick={() => router.get(route('tasks.index', { project_id: project.id, project_name: project.title }))}
                                                    >
                                                        View All {projectTasks.length} Tasks
                                                    </Button>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="p-8 text-center">
                                        <CheckSquare className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                        <h3 className="text-lg font-semibold mb-2">No Tasks Yet</h3>
                                        <p className="text-gray-500 mb-4">Create tasks to track progress and assign work for this project.</p>
                                        {canManageProject && (
                                            <Button onClick={() => router.get(route('tasks.index', { project_id: project.id, project_name: project.title }))}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Create First Task
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent value="bugs" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Project Bugs</h3>
                                <div className="flex gap-2">
                                    <Button size="sm" onClick={() => router.get(route('bugs.index', { project_id: project.id, project_name: project.title }))}>
                                        <Eye className="h-4 w-4 mr-2" />
                                        Manage Bugs
                                    </Button>
                                </div>
                            </div>

                            {projectBugs && projectBugs.length > 0 ? (
                                <div className="space-y-4">
                                    {/* Bug Stats */}
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <Bug className="h-4 w-4 text-red-500" />
                                                    <span className="text-sm font-medium">Total Bugs</span>
                                                </div>
                                                <p className="text-2xl font-bold">{projectBugs.length}</p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                                    <span className="text-sm font-medium">Open Issues</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {projectBugs.filter((bug: any) => !['Resolved', 'Closed'].includes(bug.bug_status?.name)).length}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <AlertTriangle className="h-4 w-4 text-orange-500" />
                                                    <span className="text-sm font-medium">Critical</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {projectBugs.filter((bug: any) => bug.priority === 'critical').length}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <User className="h-4 w-4 text-purple-500" />
                                                    <span className="text-sm font-medium">Unassigned</span>
                                                </div>
                                                <p className="text-2xl font-bold">
                                                    {projectBugs.filter((bug: any) => !bug.assigned_to).length}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Recent Bugs */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Recent Bugs</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-3">
                                                {projectBugs.slice(0, 5).map((bug: any) => (
                                                    <div key={bug.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex-1">
                                                                    <h4 className="font-medium text-sm">{bug.title}</h4>
                                                                    <p className="text-xs text-gray-500 mt-1 line-clamp-1">{bug.description || 'No description'}</p>
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    <Badge 
                                                                        variant="outline" 
                                                                        className={`text-xs ${
                                                                            bug.priority === 'critical' ? 'bg-red-100 text-red-800 border-red-200' :
                                                                            bug.priority === 'high' ? 'bg-orange-100 text-orange-800 border-orange-200' :
                                                                            bug.priority === 'medium' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' :
                                                                            'bg-green-100 text-green-800 border-green-200'
                                                                        }`}
                                                                    >
                                                                        {bug.priority}
                                                                    </Badge>
                                                                    <Badge 
                                                                        variant="outline" 
                                                                        className={`text-xs ${
                                                                            bug.severity === 'blocker' ? 'bg-red-100 text-red-800 border-red-200' :
                                                                            bug.severity === 'critical' ? 'bg-orange-100 text-orange-800 border-orange-200' :
                                                                            bug.severity === 'major' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' :
                                                                            'bg-green-100 text-green-800 border-green-200'
                                                                        }`}
                                                                    >
                                                                        {bug.severity}
                                                                    </Badge>
                                                                    <Badge 
                                                                        variant="outline" 
                                                                        style={{ 
                                                                            backgroundColor: bug.bug_status?.color + '20', 
                                                                            borderColor: bug.bug_status?.color,
                                                                            color: bug.bug_status?.color
                                                                        }}
                                                                        className="text-xs"
                                                                    >
                                                                        {bug.bug_status?.name}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center justify-between mt-2">
                                                                <div className="flex items-center gap-2">
                                                                    {bug.assigned_to ? (
                                                                        <div className="flex items-center gap-1">
                                                                            <Avatar className="h-5 w-5">
                                                                                <AvatarImage src={bug.assigned_to.avatar} />
                                                                                <AvatarFallback className="text-xs">
                                                                                    {bug.assigned_to.name?.charAt(0)}
                                                                                </AvatarFallback>
                                                                            </Avatar>
                                                                            <span className="text-xs text-gray-600">{bug.assigned_to.name}</span>
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-xs text-gray-400">Unassigned</span>
                                                                    )}
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-xs text-gray-500">
                                                                        Reported by {bug.reported_by?.name}
                                                                    </span>
                                                                    <span className="text-xs text-gray-500">
                                                                        {bug.end_date ? new Date(bug.end_date).toLocaleDateString() : 'No due date'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {projectBugs.length > 5 && (
                                                <div className="mt-4 text-center">
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        onClick={() => router.get(route('bugs.index', { project_id: project.id, project_name: project.title }))}
                                                    >
                                                        View All {projectBugs.length} Bugs
                                                    </Button>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="p-8 text-center">
                                        <Bug className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                        <h3 className="text-lg font-semibold mb-2">No Bugs Reported</h3>
                                        <p className="text-gray-500 mb-4">Track and manage bugs for better project quality.</p>
                                        {canManageProject && (
                                            <Button onClick={() => router.get(route('bugs.index', { project_id: project.id, project_name: project.title }))}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Report First Bug
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent value="timesheet" className="space-y-6 mt-0">
                            <div className="flex justify-between items-center">
                                <h3 className="text-lg font-semibold">Time Tracking</h3>
                                <div className="flex gap-2">
                                    {canManageProject && (
                                        <Button size="sm" onClick={() => setIsTimesheetModalOpen(true)}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Create Timesheet
                                        </Button>
                                    )}
                                    <Button size="sm" variant="outline" onClick={() => router.get(route('timesheets.index', { project_id: project.id }))}>
                                        <Eye className="h-4 w-4 mr-2" />
                                        Manage Timesheets
                                    </Button>
                                </div>
                            </div>

                            {projectTimesheets && projectTimesheets.length > 0 ? (
                                <div className="space-y-4">
                                    {/* Timesheet Stats */}
                                    {(() => {
                                        const totalHours = projectTimesheets.reduce((total: number, timesheet: any) => total + parseFloat(timesheet.total_hours || 0), 0);
                                        const billableHours = projectTimesheets.reduce((total: number, timesheet: any) => total + parseFloat(timesheet.billable_hours || 0), 0);
                                        const hoursDisplay = formatHoursDisplay(totalHours, billableHours);
                                        const overdueCount = projectTimesheets.filter((timesheet: any) => isTimesheetOverdue(timesheet.end_date, timesheet.status)).length;
                                        
                                        return (
                                            <>
                                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                    <Card>
                                                        <CardContent className="p-4">
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <Timer className="h-4 w-4 text-blue-500" />
                                                                <span className="text-sm font-medium">Total Hours</span>
                                                                {hoursDisplay.match && totalHours > 0 && (
                                                                    <CheckCircle className="h-4 w-4 text-green-500" title="Hours match" />
                                                                )}
                                                            </div>
                                                            <p className="text-2xl font-bold">{totalHours.toFixed(1)}h</p>
                                                        </CardContent>
                                                    </Card>
                                                    <Card>
                                                        <CardContent className="p-4">
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <Clock className="h-4 w-4 text-green-500" />
                                                                <span className="text-sm font-medium">Billable Hours</span>
                                                            </div>
                                                            <p className="text-2xl font-bold text-green-600">
                                                                {billableHours.toFixed(1)}h ({hoursDisplay.percentage}%)
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                    <Card>
                                                        <CardContent className="p-4">
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <Users className="h-4 w-4 text-purple-500" />
                                                                <span className="text-sm font-medium">Team Members</span>
                                                            </div>
                                                            <p className="text-2xl font-bold">
                                                                {new Set(projectTimesheets.map((timesheet: any) => timesheet.user?.id)).size}
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                    <Card>
                                                        <CardContent className="p-4">
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <CheckSquare className="h-4 w-4 text-orange-500" />
                                                                <span className="text-sm font-medium">Approved</span>
                                                            </div>
                                                            <p className="text-2xl font-bold">
                                                                {projectTimesheets.filter((timesheet: any) => timesheet.status === 'approved').length}
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                </div>
                                                
                                                {/* Hours Status Summary */}
                                                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-lg p-4">
                                                    <div className="space-y-4">
                                                        <div className="flex items-center justify-between">
                                                            <h4 className="text-sm font-semibold text-gray-700">Hours Status</h4>
                                                            <div className="flex items-center gap-2">
                                                                {hoursDisplay.match && totalHours > 0 ? (
                                                                    <Badge className="bg-green-100 text-green-800 border-green-300" variant="outline">
                                                                        <CheckCircle className="h-3 w-3 mr-1" />
                                                                        All Hours Billable
                                                                    </Badge>
                                                                ) : totalHours > 0 ? (
                                                                    <Badge className="bg-yellow-100 text-yellow-800 border-yellow-300" variant="outline">
                                                                        <AlertTriangle className="h-3 w-3 mr-1" />
                                                                        Partial Billable
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="outline">No Hours Logged</Badge>
                                                                )}
                                                                {overdueCount > 0 && (
                                                                    <Badge className="bg-red-100 text-red-800 border-red-300" variant="outline">
                                                                        <AlertTriangle className="h-3 w-3 mr-1" />
                                                                        {overdueCount} Overdue
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                        {totalHours > 0 && (
                                                            <div className="bg-white rounded-md p-3 border border-gray-200">
                                                                <div className="flex items-center justify-between mb-2">
                                                                    <span className="text-sm font-medium text-gray-700">Billable Rate</span>
                                                                    <span className="text-lg font-bold text-gray-900">{hoursDisplay.percentage}%</span>
                                                                </div>
                                                                <div className="relative">
                                                                    <Progress value={hoursDisplay.percentage} className="w-full h-3 bg-gray-200" />
                                                                    <div className="absolute inset-0 flex items-center justify-center">
                                                                        <span className="text-xs font-medium text-white drop-shadow">
                                                                            {billableHours.toFixed(1)}h / {totalHours.toFixed(1)}h
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </>
                                        );
                                    })()}

                                    {/* Recent Timesheets */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Recent Timesheets</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-3">
                                                {projectTimesheets.slice(0, 5).map((timesheet: any) => {
                                                    const hoursDisplay = formatHoursDisplay(timesheet.total_hours || 0, timesheet.billable_hours || 0);
                                                    const label = getTimesheetLabel({
                                                        total_hours: timesheet.total_hours || 0,
                                                        billable_hours: timesheet.billable_hours || 0,
                                                        end_date: timesheet.end_date,
                                                        status: timesheet.status
                                                    });
                                                    const isOverdue = isTimesheetOverdue(timesheet.end_date, timesheet.status);
                                                    
                                                    return (
                                                        <div key={timesheet.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                            <div className="flex-1">
                                                                <div className="flex items-center gap-3">
                                                                    <div className="flex-1">
                                                                        <div className="flex items-center gap-2">
                                                                            <h4 className="font-medium text-sm">
                                                                                Week of {new Date(timesheet.start_date).toLocaleDateString()}
                                                                            </h4>
                                                                            {hoursDisplay.match && timesheet.total_hours > 0 && (
                                                                                <CheckCircle className="h-3 w-3 text-green-500" title="Hours match" />
                                                                            )}
                                                                            {isOverdue && (
                                                                                <Badge className="bg-red-100 text-red-800 border-red-200 text-xs" variant="outline">
                                                                                    {getDaysOverdue(timesheet.end_date)}d overdue
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                        <p className="text-xs text-gray-500 mt-1">
                                                                            {new Date(timesheet.start_date).toLocaleDateString()} - {new Date(timesheet.end_date).toLocaleDateString()}
                                                                        </p>
                                                                        {label && (
                                                                            <Badge className={`${label.className} text-xs mt-1`} variant="outline">
                                                                                {label.label}
                                                                            </Badge>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        <Badge 
                                                                            variant="outline" 
                                                                            className={`text-xs ${
                                                                                timesheet.status === 'approved' ? 'bg-green-100 text-green-800 border-green-200' :
                                                                                timesheet.status === 'submitted' ? 'bg-blue-100 text-blue-800 border-blue-200' :
                                                                                timesheet.status === 'rejected' ? 'bg-red-100 text-red-800 border-red-200' :
                                                                                isOverdue ? 'bg-red-100 text-red-800 border-red-200' :
                                                                                'bg-gray-100 text-gray-800 border-gray-200'
                                                                            }`}
                                                                        >
                                                                            {isOverdue ? 'Overdue' : timesheet.status}
                                                                        </Badge>
                                                                    </div>
                                                                </div>
                                                                <div className="flex items-center justify-between mt-2">
                                                                    <div className="flex items-center gap-2">
                                                                        {timesheet.user ? (
                                                                            <div className="flex items-center gap-1">
                                                                                <Avatar className="h-5 w-5">
                                                                                    <AvatarImage src={timesheet.user.avatar} />
                                                                                    <AvatarFallback className="text-xs">
                                                                                        {timesheet.user.name?.charAt(0)}
                                                                                    </AvatarFallback>
                                                                                </Avatar>
                                                                                <span className="text-xs text-gray-600">{timesheet.user.name}</span>
                                                                            </div>
                                                                        ) : (
                                                                            <span className="text-xs text-gray-400">Unknown User</span>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex items-center gap-4">
                                                                        <div className="flex items-center gap-1">
                                                                            <Timer className="h-3 w-3 text-blue-500" />
                                                                            <span className="text-xs text-gray-600">{hoursDisplay.total}</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1">
                                                                            <DollarSign className="h-3 w-3 text-green-500" />
                                                                            <span className="text-xs text-green-600">
                                                                                {hoursDisplay.billable} ({hoursDisplay.percentage}%)
                                                                            </span>
                                                                        </div>
                                                                        <span className="text-xs text-gray-500">
                                                                            {timesheet.entries?.length || 0} entries
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                            {projectTimesheets.length > 5 && (
                                                <div className="mt-4 text-center">
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        onClick={() => router.get(route('timesheets.index', { project_id: project.id }))}
                                                    >
                                                        View All {projectTimesheets.length} Timesheets
                                                    </Button>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="p-8 text-center">
                                        <Timer className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                        <h3 className="text-lg font-semibold mb-2">No Timesheets Yet</h3>
                                        <p className="text-gray-500 mb-4">Start tracking time on project tasks to see timesheet data here.</p>
                                        {canManageProject && (
                                            <Button onClick={() => router.get(route('timesheets.index', { project_id: project.id }))}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Create First Timesheet
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent value="attachments" className="space-y-6 mt-0">
                            <div className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-semibold">Project Attachments</h3>
                                    {hasPermission(permissions, 'project_manage_attachments') && (
                                        <Button size="sm" onClick={() => handleAction('add-attachment')}>
                                            <Upload className="h-4 w-4 mr-2" />
                                            Upload Files
                                        </Button>
                                    )}
                                </div>
                                
                                {/* Search and Filter Bar */}
                                <div className="bg-gray-50 rounded-lg p-4 border">
                                    <div className="flex items-center justify-between gap-4">
                                        <form onSubmit={(e) => { e.preventDefault(); handleAttachmentSearch(); }} className="flex gap-2 flex-1">
                                            <div className="relative flex-1 max-w-md">
                                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                                <Input
                                                    placeholder="Search by filename or uploader..."
                                                    value={attachmentSearch}
                                                    onChange={(e) => setAttachmentSearch(e.target.value)}
                                                    className="pl-10 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                                                />
                                            </div>
                                            <Button type="submit" size="sm" className="bg-blue-600 hover:bg-blue-700">
                                                <Search className="h-4 w-4 mr-1.5" />
                                                Search
                                            </Button>
                                            {attachmentSearch && (
                                                <Button 
                                                    type="button" 
                                                    variant="outline" 
                                                    size="sm"
                                                    onClick={() => {
                                                        setAttachmentSearch('');
                                                        router.get(route('projects.show', project.id), {}, { 
                                                            preserveState: true, 
                                                            preserveScroll: true,
                                                            only: ['project', 'attachmentFilters']
                                                        });
                                                    }}
                                                >
                                                    Clear
                                                </Button>
                                            )}
                                        </form>
                                        
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-600 whitespace-nowrap">Per Page:</span>
                                            <Select 
                                                value={attachmentsPerPage.toString()} 
                                                onValueChange={handleAttachmentPerPageChange}
                                            >
                                                <SelectTrigger className="w-20 h-9 bg-white border-gray-200">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="6">6</SelectItem>
                                                    <SelectItem value="12">12</SelectItem>
                                                    <SelectItem value="24">24</SelectItem>
                                                    <SelectItem value="48">48</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {project.attachments && project.attachments.data?.length > 0 ? (
                                <>
                                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                                        {project.attachments.data.map((attachment: any) => (
                                            <Card key={attachment.id} className="group hover:shadow-lg transition-all duration-200 border-0 shadow-md hover:scale-[1.02] bg-gradient-to-br from-white to-gray-50">
                                                <CardContent className="p-0">
                                                    {/* Image Preview */}
                                                    <div className="relative overflow-hidden rounded-t-lg">
                                                        {attachment.media_item?.url ? (
                                                            <div className="relative">
                                                                <img 
                                                                    src={attachment.media_item.thumb_url || attachment.media_item.url} 
                                                                    alt={attachment.media_item.name}
                                                                    className="w-full h-24 object-cover transition-transform duration-200 group-hover:scale-105"
                                                                    onError={(e) => {
                                                                        e.currentTarget.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDIwMCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTUwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik04NyA2NUw5MyA3MUwxMDcgNTdMMTIzIDczVjEwNUg3N1Y2NUg4N1oiIGZpbGw9IiM5Q0EzQUYiLz4KPGNpcmNsZSBjeD0iOTEiIGN5PSI1NyIgcj0iNCIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K';
                                                                    }}
                                                                />
                                                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200" />
                                                                
                                                                {/* Action Buttons Overlay */}
                                                                <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                                    <Button
                                                                        variant="secondary"
                                                                        size="icon"
                                                                        className="h-8 w-8 bg-white/90 hover:bg-white shadow-md"
                                                                        onClick={() => {
                                                                            if (attachment.media_item?.url) {
                                                                                window.open(route('project-attachments.download', attachment.id), '_blank');
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Download className="h-4 w-4 text-gray-700" />
                                                                    </Button>
                                                                    
                                                                    {canManageProject && (
                                                                        <Button
                                                                            variant="secondary"
                                                                            size="icon"
                                                                            className="h-8 w-8 bg-red-500/90 hover:bg-red-600 shadow-md"
                                                                            onClick={() => {
                                                                                setModalType('attachment');
                                                                                handleAction('delete-attachment', attachment);
                                                                            }}
                                                                        >
                                                                            <Trash2 className="h-4 w-4 text-white" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <div className="w-full h-24 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                                <Paperclip className="h-6 w-6 text-gray-400" />
                                                            </div>
                                                        )}
                                                    </div>
                                                    
                                                    {/* Content */}
                                                    <div className="p-2">
                                                        <h4 className="font-medium text-xs text-gray-900 truncate mb-1" title={attachment.media_item?.name}>
                                                            {attachment.media_item?.name || 'Unnamed file'}
                                                        </h4>
                                                        <div className="flex items-center gap-1 text-xs text-gray-500 mb-1">
                                                            <Avatar className="h-3 w-3">
                                                                <AvatarFallback className="text-xs bg-blue-100 text-blue-600">
                                                                    {attachment.uploaded_by?.name?.charAt(0)}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <span className="truncate">{attachment.uploaded_by?.name}</span>
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            {new Date(attachment.created_at).toLocaleString()}
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                    
                                    {/* Pagination */}
                                    {project.attachments.links && (
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="text-sm text-gray-500">
                                                Showing {project.attachments.from || 0} to {project.attachments.to || 0} of {project.attachments.total || 0} attachments
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                {project.attachments.links.map((link: any, i: number) => {
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
                                                                if (link.url) {
                                                                    const url = new URL(link.url);
                                                                    const params: any = {};
                                                                    url.searchParams.forEach((value, key) => {
                                                                        params[key] = value;
                                                                    });
                                                                    router.get(route('projects.show', project.id), params, { 
                                                                        preserveState: true, 
                                                                        preserveScroll: true,
                                                                        only: ['project', 'attachmentFilters']
                                                                    });
                                                                }
                                                            }}
                                                        >
                                                            {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-16">
                                    <div className="mx-auto w-24 h-24 bg-gradient-to-br from-blue-50 to-indigo-100 rounded-full flex items-center justify-center mb-6">
                                        <Paperclip className="h-10 w-10 text-blue-500" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                        {attachmentSearch ? 'No attachments found' : 'No attachments yet'}
                                    </h3>
                                    <p className="text-gray-500 mb-6 max-w-sm mx-auto">
                                        {attachmentSearch ? 'Try adjusting your search terms or ' : 'Upload files to share documents, images, and other resources with your team.'}
                                    {attachmentSearch && (
                                        <Button variant="link" className="p-0 h-auto" onClick={() => {
                                            setAttachmentSearch('');
                                            router.get(route('projects.show', project.id), {}, { 
                                                preserveState: true, 
                                                preserveScroll: true,
                                                only: ['project', 'attachmentFilters']
                                            });
                                        }}>
                                            clear search
                                        </Button>
                                    )}
                                    </p>
                                    {!attachmentSearch && canManageProject && (
                                        <Button className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700" onClick={() => handleAction('add-attachment')}>
                                            <Upload className="h-4 w-4 mr-2" />
                                            Upload First Attachment
                                        </Button>
                                    )}
                                </div>
                            )}
                        </TabsContent>

                        {/* ── SPRINTS ──────────────────────────────────────────── */}
                        <TabsContent value="sprints" className="mt-0">
                            <div className="flex items-center justify-between mb-4">
                                <p className="text-sm text-muted-foreground">Manage sprints and backlog for this project.</p>
                                <Button size="sm" onClick={() => router.get(route('sprints.index', project.id))}>
                                    <Play className="mr-1.5 h-4 w-4" /> Open Sprint Planner
                                </Button>
                            </div>
                        </TabsContent>

                        {/* ── CLIENT PORTAL ────────────────────────────────────── */}
                        <TabsContent value="portal" className="mt-0">
                            <ClientPortalPanel project={project} />
                        </TabsContent>

                        <TabsContent value="activity" className="space-y-6 mt-0">
                            <div className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <h3 className="text-lg font-semibold">Project Activity</h3>
                                </div>
                                
                                {/* Search and Filter Bar */}
                                <div className="bg-gray-50 rounded-lg p-4 border">
                                    <div className="flex items-center justify-between gap-4">
                                        <form onSubmit={(e) => { e.preventDefault(); handleActivitySearch(); }} className="flex gap-2 flex-1">
                                            <div className="relative flex-1 max-w-md">
                                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                                <Input
                                                    placeholder="Search activity..."
                                                    value={activitySearch}
                                                    onChange={(e) => setActivitySearch(e.target.value)}
                                                    className="pl-10 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                                                />
                                            </div>
                                            <Button type="submit" size="sm" className="bg-blue-600 hover:bg-blue-700">
                                                <Search className="h-4 w-4 mr-1.5" />
                                                Search
                                            </Button>
                                            {activitySearch && (
                                                <Button 
                                                    type="button" 
                                                    variant="outline" 
                                                    size="sm"
                                                    onClick={() => {
                                                        setActivitySearch('');
                                                        router.get(route('projects.show', project.id), {}, { 
                                                            preserveState: true, 
                                                            preserveScroll: true,
                                                            only: ['project', 'activityFilters']
                                                        });
                                                    }}
                                                >
                                                    Clear
                                                </Button>
                                            )}
                                        </form>
                                        
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-600 whitespace-nowrap">Per Page:</span>
                                            <Select 
                                                value={activityPerPage.toString()} 
                                                onValueChange={handleActivityPerPageChange}
                                            >
                                                <SelectTrigger className="w-20 h-9 bg-white border-gray-200">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="5">5</SelectItem>
                                                    <SelectItem value="10">10</SelectItem>
                                                    <SelectItem value="20">20</SelectItem>
                                                    <SelectItem value="50">50</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {project.activities && project.activities.data?.length > 0 ? (
                                <>
                                    <div className="space-y-4">
                                        {project.activities.data.map((activity: any) => (
                                            <Card key={activity.id}>
                                                <CardContent className="p-4">
                                                    <div className="flex items-start gap-3">
                                                        <Avatar className="h-8 w-8">
                                                            <AvatarFallback className="text-xs">
                                                                {activity.user.name.charAt(0)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex-1">
                                                            <p className="text-sm">{activity.description}</p>
                                                            <div className="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                                                <span>{activity.user.name}</span>
                                                                <span>•</span>
                                                                <span>{new Date(activity.created_at).toLocaleString()}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                    
                                    {/* Pagination */}
                                    {project.activities.links && (
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="text-sm text-gray-500">
                                                Showing {project.activities.from || 0} to {project.activities.to || 0} of {project.activities.total || 0} activities
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                {project.activities.links.map((link: any, i: number) => {
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
                                                                if (link.url) {
                                                                    const url = new URL(link.url);
                                                                    const params: any = {};
                                                                    url.searchParams.forEach((value, key) => {
                                                                        params[key] = value;
                                                                    });
                                                                    router.get(route('projects.show', project.id), params, { 
                                                                        preserveState: true, 
                                                                        preserveScroll: true,
                                                                        only: ['project', 'activityFilters']
                                                                    });
                                                                }
                                                            }}
                                                        >
                                                            {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                        {activitySearch ? 'No activity found' : 'No activity recorded yet'}
                                    </h3>
                                    <p className="text-gray-500 mb-6 max-w-sm mx-auto">
                                        {activitySearch ? 'Try adjusting your search terms or ' : 'Project activities will appear here as they happen.'}
                                        {activitySearch && (
                                            <Button variant="link" className="p-0 h-auto" onClick={() => {
                                                setActivitySearch('');
                                                router.get(route('projects.show', project.id), {}, { 
                                                    preserveState: true, 
                                                    preserveScroll: true,
                                                    only: ['project', 'activityFilters']
                                                });
                                            }}>
                                                clear search
                                            </Button>
                                        )}
                                    </p>
                                </div>
                            )}
                        </TabsContent>

                        {/* ── SCRUM BOARD ─────────────────────────────────────── */}
                        <TabsContent value="scrum" className="mt-0">
                            {taskStages.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                                    <Columns className="h-10 w-10 mb-3 opacity-40" />
                                    <p className="font-medium">No task stages found.</p>
                                    <p className="text-sm mt-1">Create task stages first from the Tasks menu.</p>
                                </div>
                            ) : (
                                <div className="flex gap-4 overflow-x-auto pb-4 pt-2">
                                    {taskStages.map((stage: any) => {
                                        const stageTasks = projectTasks.filter((t: any) => t.task_stage_id === stage.id);
                                        const PRIORITY_COLORS: Record<string, string> = {
                                            urgent: 'bg-red-100 text-red-700',
                                            high: 'bg-orange-100 text-orange-700',
                                            medium: 'bg-blue-100 text-blue-700',
                                            low: 'bg-gray-100 text-gray-600',
                                        };
                                        return (
                                            <div key={stage.id} className="flex-shrink-0 w-72">
                                                {/* Column header */}
                                                <div className="flex items-center gap-2 mb-3 px-1">
                                                    <span className="inline-block h-3 w-3 rounded-full flex-shrink-0"
                                                        style={{ backgroundColor: stage.color || '#6366f1' }} />
                                                    <span className="font-semibold text-sm truncate">{stage.name}</span>
                                                    <span className="ml-auto text-xs bg-muted rounded-full px-2 py-0.5 font-medium">
                                                        {stageTasks.length}
                                                    </span>
                                                </div>
                                                {/* Task cards */}
                                                <div className="space-y-2.5 min-h-[120px]">
                                                    {stageTasks.length === 0 && (
                                                        <div className="rounded-lg border-2 border-dashed py-8 text-center text-xs text-gray-400">
                                                            No tasks
                                                        </div>
                                                    )}
                                                    {stageTasks.map((task: any) => (
                                                        <div key={task.id}
                                                            className="rounded-lg border bg-white p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                                                            onClick={() => router.get(route('tasks.index', { task_id: task.id }))}>
                                                            <div className="flex items-start justify-between gap-2 mb-2">
                                                                <p className="text-sm font-medium leading-snug line-clamp-2">{task.title}</p>
                                                                {task.priority && (
                                                                    <span className={`shrink-0 text-xs rounded-full px-2 py-0.5 font-medium ${PRIORITY_COLORS[task.priority] || 'bg-gray-100 text-gray-600'}`}>
                                                                        {task.priority}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {task.end_date && (
                                                                <p className="text-xs text-gray-500 mb-2">
                                                                    Due: {new Date(task.end_date).toLocaleDateString()}
                                                                </p>
                                                            )}
                                                            <div className="flex items-center justify-between mt-2">
                                                                {task.assigned_to ? (
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Avatar className="h-5 w-5">
                                                                            <AvatarImage src={task.assigned_to?.avatar} />
                                                                            <AvatarFallback className="text-[10px]">
                                                                                {task.assigned_to?.name?.charAt(0)}
                                                                            </AvatarFallback>
                                                                        </Avatar>
                                                                        <span className="text-xs text-gray-500 truncate max-w-[80px]">{task.assigned_to?.name}</span>
                                                                    </div>
                                                                ) : <span />}
                                                                {/* Move to stage */}
                                                                <select
                                                                    className="text-xs border rounded px-1.5 py-0.5 bg-white cursor-pointer"
                                                                    value={task.task_stage_id || ''}
                                                                    onClick={e => e.stopPropagation()}
                                                                    onChange={e => {
                                                                        const newStageId = e.target.value;
                                                                        router.put(route('tasks.change-stage', task.id),
                                                                            { task_stage_id: newStageId },
                                                                            { preserveScroll: true }
                                                                        );
                                                                    }}>
                                                                    {taskStages.map((s: any) => (
                                                                        <option key={s.id} value={s.id}>{s.name}</option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </TabsContent>

                        {/* ── GANTT CHART ──────────────────────────────────────── */}
                        <TabsContent value="gantt" className="mt-0">
                            {(() => {
                                const DAY_WIDTH = 28; // px per day
                                const tasksWithDates = projectTasks.filter((t: any) => t.start_date && t.end_date);
                                const tasksNoDates = projectTasks.filter((t: any) => !t.start_date || !t.end_date);

                                if (projectTasks.length === 0) {
                                    return (
                                        <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                                            <BarChart3 className="h-10 w-10 mb-3 opacity-40" />
                                            <p className="font-medium">No tasks yet.</p>
                                        </div>
                                    );
                                }

                                // Compute date range (project dates or task dates, padded)
                                const allDates: number[] = [];
                                if (project.start_date) allDates.push(new Date(project.start_date).getTime());
                                if (project.deadline) allDates.push(new Date(project.deadline).getTime());
                                tasksWithDates.forEach((t: any) => {
                                    allDates.push(new Date(t.start_date).getTime());
                                    allDates.push(new Date(t.end_date).getTime());
                                });
                                const now = Date.now();
                                const rangeStart = allDates.length > 0
                                    ? new Date(Math.min(...allDates) - 2 * 86400000)
                                    : new Date(now - 7 * 86400000);
                                const rangeEnd = allDates.length > 0
                                    ? new Date(Math.max(...allDates) + 5 * 86400000)
                                    : new Date(now + 30 * 86400000);

                                const totalDays = Math.round((rangeEnd.getTime() - rangeStart.getTime()) / 86400000);
                                const totalWidth = totalDays * DAY_WIDTH;

                                // Build week headers
                                const weeks: { label: string; days: number }[] = [];
                                let cursor = new Date(rangeStart);
                                while (cursor < rangeEnd) {
                                    const monday = new Date(cursor);
                                    const sunday = new Date(cursor);
                                    sunday.setDate(sunday.getDate() + 6);
                                    const daysInView = Math.min(7, Math.round((rangeEnd.getTime() - cursor.getTime()) / 86400000));
                                    weeks.push({
                                        label: monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
                                        days: daysInView,
                                    });
                                    cursor.setDate(cursor.getDate() + 7);
                                }

                                const getBar = (task: any) => {
                                    const s = new Date(task.start_date).getTime();
                                    const e = new Date(task.end_date).getTime();
                                    const left = Math.round((s - rangeStart.getTime()) / 86400000) * DAY_WIDTH;
                                    const width = Math.max(DAY_WIDTH, Math.round((e - s) / 86400000) * DAY_WIDTH);
                                    return { left, width };
                                };

                                const todayLeft = Math.round((now - rangeStart.getTime()) / 86400000) * DAY_WIDTH;

                                const PRIORITY_COLORS: Record<string, string> = {
                                    urgent: '#ef4444', high: '#f97316', medium: '#6366f1', low: '#94a3b8',
                                };

                                return (
                                    <div>
                                        <div className="overflow-x-auto rounded-xl border">
                                            <div style={{ minWidth: `${280 + totalWidth}px` }}>
                                                {/* Header row */}
                                                <div className="flex border-b bg-muted/40">
                                                    <div className="w-64 shrink-0 border-r px-3 py-2 text-xs font-semibold text-muted-foreground">
                                                        Task
                                                    </div>
                                                    <div className="flex" style={{ width: totalWidth }}>
                                                        {weeks.map((w, i) => (
                                                            <div key={i}
                                                                className="border-r text-xs font-medium text-muted-foreground px-1 py-2 truncate"
                                                                style={{ width: w.days * DAY_WIDTH }}>
                                                                {w.label}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Task rows */}
                                                {tasksWithDates.map((task: any) => {
                                                    const { left, width } = getBar(task);
                                                    const color = PRIORITY_COLORS[task.priority] || '#6366f1';
                                                    return (
                                                        <div key={task.id} className="flex items-center border-b hover:bg-muted/20 group">
                                                            <div className="w-64 shrink-0 border-r px-3 py-2 flex items-center gap-2">
                                                                <span className="text-sm truncate cursor-pointer hover:text-primary"
                                                                    onClick={() => router.get(route('tasks.index', { task_id: task.id }))}>
                                                                    {task.title}
                                                                </span>
                                                            </div>
                                                            <div className="relative" style={{ width: totalWidth, height: 36 }}>
                                                                {/* Today line */}
                                                                {todayLeft >= 0 && todayLeft <= totalWidth && (
                                                                    <div className="absolute top-0 bottom-0 w-px bg-red-400 z-10"
                                                                        style={{ left: todayLeft }} />
                                                                )}
                                                                {/* Bar */}
                                                                <div
                                                                    className="absolute top-2 rounded-full cursor-pointer opacity-90 hover:opacity-100 flex items-center px-2"
                                                                    style={{ left, width, height: 20, backgroundColor: color + '33', border: `1.5px solid ${color}` }}
                                                                    title={`${task.title}\n${task.start_date} → ${task.end_date}`}
                                                                    onClick={() => router.get(route('tasks.index', { task_id: task.id }))}>
                                                                    <span className="text-[10px] font-medium truncate" style={{ color }}>
                                                                        {task.title}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {/* Tasks without dates */}
                                        {tasksNoDates.length > 0 && (
                                            <div className="mt-4">
                                                <p className="text-xs text-muted-foreground mb-2">
                                                    {tasksNoDates.length} task(s) have no dates and are not shown on the chart:
                                                </p>
                                                <div className="flex flex-wrap gap-2">
                                                    {tasksNoDates.map((t: any) => (
                                                        <button key={t.id}
                                                            onClick={() => router.get(route('tasks.index', { task_id: t.id }))}
                                                            className="text-xs rounded-full border px-3 py-1 hover:bg-muted transition-colors">
                                                            {t.title}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })()}
                        </TabsContent>

                    </div>
                </Tabs>
            </div>

            {/* Form Modal */}
            <CrudFormModal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                onSubmit={handleFormSubmit}
                formConfig={getFormConfig()}
                initialData={currentItem || (modalType === 'milestone' ? { status: 'pending' } : modalType === 'member' ? { role: 'member' } : modalType === 'project' ? { status: 'planning', priority: 'medium', is_public: false } : {})}
                title={modalType === 'project' ? 'Edit Project' : `${formMode === 'create' ? 'Add' : 'Edit'} ${modalType.charAt(0).toUpperCase() + modalType.slice(1)}`}
                mode={formMode}
            />

            {/* Delete Modal */}
            <EnhancedDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.title || currentItem?.name || currentItem?.user?.name || currentItem?.media_item?.name || ''}
                entityName={modalType === 'member' ? 'team member' : modalType}
                warningMessage={
                    modalType === 'member' ? `This will remove ${currentItem?.user?.name || 'this member'} from the project team.` :
                    modalType === 'client' ? `This will remove ${currentItem?.name || 'this client'} from the project.` :
                    modalType === 'attachment' ? 'This file will be permanently deleted from the project.' :
                    'This action cannot be undone.'
                }
                additionalInfo={
                    modalType === 'member' ? [
                        'Access to project resources will be revoked',
                        'Task assignments may need to be reassigned',
                        'Time tracking history will be preserved'
                    ] :
                    modalType === 'client' ? [
                        'Client access to project will be removed',
                        'Project visibility for this client will be revoked'
                    ] :
                    modalType === 'attachment' ? [
                        'File will be removed from all project references',
                        'Download history will be preserved'
                    ] : []
                }
            />

            {/* Timesheet Modal */}
            <TimesheetFormModal
                isOpen={isTimesheetModalOpen}
                onClose={() => setIsTimesheetModalOpen(false)}
                projects={[project]}
            />
        </PageTemplate>
    );
}