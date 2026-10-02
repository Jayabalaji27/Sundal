import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useState, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { Copy, FileText, FileSpreadsheet, FileArchive, FileCode, File, Image, MessageSquare, Paperclip, User, Calendar, DollarSign, Plus, Pin, Trash2, Eye, Upload, Search, Clock, CheckCircle, AlertTriangle, Send, Download, Edit, ArrowLeft, PinOff, Save } from 'lucide-react';

import { formatCurrency } from '@/utils/currency';
import { toast } from 'sonner';

interface Contract {
    id: number;
    contract_id: string;
    subject: string;
    description: string;
    contract_value: number;
    currency: string;
    start_date: string;
    end_date: string;
    status: string;
    contract_type: {
        id: number;
        name: string;
        color: string;
    };
    client: {
        id: number;
        name: string;
        email: string;
        avatar: string;
    };
    creator: {
        id: number;
        name: string;
    };
    notes: any[];
    comments: any[];
    attachments: any[];
    created_at: string;
}

const statusOptions = [
    { value: 'pending', label: 'Pending', color: '#ffc107' },
    { value: 'sent', label: 'Sent', color: '#007bff' },
    { value: 'accept', label: 'Accepted', color: '#28a745' },
    { value: 'decline', label: 'Declined', color: '#dc3545' },
    { value: 'expired', label: 'Expired', color: '#fd7e14' },
];

export default function ContractShow() {
    const { t } = useTranslation();
    const { contract, auth, assignedUsers, emailTemplateEnabled } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const [activeTab, setActiveTab] = useState('overview');
    const [newNote, setNewNote] = useState('');
    const [newComment, setNewComment] = useState('');
    const [isAddingComment, setIsAddingComment] = useState(false);
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [isUploadingAttachment, setIsUploadingAttachment] = useState(false);
    const [searchAttachments, setSearchAttachments] = useState('');
    const [attachmentsPerPage, setAttachmentsPerPage] = useState(10);
    const [currentAttachmentsPage, setCurrentAttachmentsPage] = useState(1);
    const isClient = auth?.user?.id === contract.client?.id;
    const [isAddingNote, setIsAddingNote] = useState(false);
    const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
    const [editingCommentId, setEditingCommentId] = useState<number | null>(null);
    const [editNoteText, setEditNoteText] = useState('');
    const [editCommentText, setEditCommentText] = useState('');
    const [viewNote, setViewNote] = useState<any>(null);

    const getStatusBadge = (status: string) => {
        const statusOption = statusOptions.find(s => s.value === status);
        const label = statusOption?.label || status.charAt(0).toUpperCase() + status.slice(1);
        const color = statusOption?.color || '#6b7280';
        return (
            <span
                className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                style={{
                    backgroundColor: color + '20',
                    color: color,
                    boxShadow: `inset 0 0 0 1px ${color}33`,
                }}
            >
                {label}
            </span>
        );
    };

    const handleAddNote = () => {
        if (!newNote.trim()) return;
        router.post(route('contract-notes.store', contract.id), {
            note: newNote
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewNote('');
                setIsAddingNote(false);
                toast.success('Note added successfully');
            }
        });
    };

    const handleAddComment = () => {
        if (!newComment.trim()) return;
        router.post(route('contract-comments.store', contract.id), {
            comment: newComment
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewComment('');
                setIsAddingComment(false);
                toast.success('Comment added successfully');
            }
        });
    };

    const handlePinNote = (noteId: number, isPinned: boolean) => {
        router.put(route('contract-notes.update', [contract.id, noteId]), {
            is_pinned: !isPinned,
        }, {
            preserveScroll: true,
            onSuccess: () => {}
        });
    };

    const [noteToDelete, setNoteToDelete] = useState<any>(null);
    const [commentToDelete, setCommentToDelete] = useState<any>(null);

    const handleDeleteNote = (note: any) => {
        setNoteToDelete(note);
    };

    const confirmDeleteNote = () => {
        router.delete(route('contract-notes.destroy', [contract.id, noteToDelete.id]), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Note deleted successfully');
                setNoteToDelete(null);
            }
        });
    };

    const handleEditNote = (note: any) => {
        setEditingNoteId(note.id);
        setEditNoteText(note.note);
    };

    const handleUpdateNote = (noteId: number) => {
        if (!editNoteText.trim()) return;
        router.put(route('contract-notes.update', [contract.id, noteId]), {
            note: editNoteText
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingNoteId(null);
                setEditNoteText('');
                toast.success('Note updated successfully');
            }
        });
    };

    const handleDeleteComment = (comment: any) => {
        setCommentToDelete(comment);
    };

    const confirmDeleteComment = () => {
        router.delete(route('contract-comments.destroy', commentToDelete.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Comment deleted successfully');
                setCommentToDelete(null);
            }
        });
    };

    const handleEditComment = (comment: any) => {
        setEditingCommentId(comment.id);
        setEditCommentText(comment.comment);
    };

    const handleUpdateComment = (commentId: number) => {
        if (!editCommentText.trim()) return;
        router.put(route('contract-comments.update', commentId), {
            comment: editCommentText
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingCommentId(null);
                setEditCommentText('');
                toast.success('Comment updated successfully');
            }
        });
    };

    const getContractProgress = () => {
        const statusProgress = {
            'pending': 10,
            'sent': 30,
            'signed': 100,
            'declined': 0,
            'expired': 0,
            'cancelled': 0
        };
        return statusProgress[contract.status as keyof typeof statusProgress] || 0;
    };

    const getSortedNotes = () =>
        [...(contract.notes || [])].sort((a: any, b: any) => (b.is_pinned ? 1 : 0) - (a.is_pinned ? 1 : 0));

    const getFilteredAttachments = () => {
        const filtered = contract.attachments?.filter(attachment =>
            attachment.files?.toLowerCase().includes(searchAttachments.toLowerCase())
        ) || [];
        const startIndex = (currentAttachmentsPage - 1) * attachmentsPerPage;
        return {
            items: filtered.slice(startIndex, startIndex + attachmentsPerPage),
            total: filtered.length,
            totalPages: Math.ceil(filtered.length / attachmentsPerPage)
        };
    };

    const handleStatusChange = (newStatus: string) => {
        router.put(route('contracts.change-status', contract.id), {
            status: newStatus
        }, {
            onSuccess: () => {
                toast.success('Contract status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update contract status');
            }
        });
    };


    const pageActions = [
        {
            label: t('Back'),
            icon: <ArrowLeft className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('contracts.index'))
        }
    ];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Contracts'), href: route('contracts.index') },
        { title: t('Contract details') }
    ];

    return (
        <PageTemplate
            title={contract.subject}
            url={`/contracts/${contract.id}`}
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Contract Header */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-6">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <div className="flex gap-2">
                                {getStatusBadge(contract.status)}
                                <span
                                    className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                    style={{
                                        backgroundColor: (contract.contract_type?.color || '#007bff') + '20',
                                        color: contract.contract_type?.color || '#007bff',
                                        boxShadow: `inset 0 0 0 1px ${(contract.contract_type?.color || '#007bff')}33`,
                                    }}
                                >
                                    {contract.contract_type?.name}
                                </span>
                            </div>
                        </div>
                        {isClient && contract.status !== 'accept' && contract.status !== 'decline' && (
                            <Select value={contract.status} onValueChange={handleStatusChange}>
                                <SelectTrigger className="w-[160px] h-9 bg-white border-gray-300">
                                    <SelectValue placeholder="Pending" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-yellow-600" />
                                            Pending
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="accept">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle className="h-4 w-4 text-green-600" />
                                            Accept
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="decline">
                                        <div className="flex items-center gap-2">
                                            <AlertTriangle className="h-4 w-4 text-red-600" />
                                            Decline
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    </div>

                    {/* Contract Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                                <FileText className="h-5 w-5 text-blue-600 dark:text-blue-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-gray-500 dark:text-gray-400">{t('Contract ID')}</p>
                                <p className="text-lg font-bold text-gray-900 dark:text-white leading-tight">{contract.contract_id}</p>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{t('Reference')}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center shrink-0">
                                <DollarSign className="h-5 w-5 text-emerald-600 dark:text-emerald-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-gray-500 dark:text-gray-400">{t('Contract Value')}</p>
                                <p className="text-lg font-bold text-gray-900 dark:text-white leading-tight">{formatCurrency(contract.contract_value || 0)}</p>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{t('Total Amount')}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg bg-violet-100 dark:bg-violet-900 flex items-center justify-center shrink-0">
                                <User className="h-5 w-5 text-violet-600 dark:text-violet-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-gray-500 dark:text-gray-400">{t('Client')}</p>
                                <p className="text-lg font-bold text-gray-900 dark:text-white leading-tight truncate">{contract.client?.name}</p>
                                <p className="text-xs text-gray-400 dark:text-gray-500 truncate">{contract.client?.email}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg bg-amber-100 dark:bg-amber-900 flex items-center justify-center shrink-0">
                                <Calendar className="h-5 w-5 text-amber-600 dark:text-amber-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs text-gray-500 dark:text-gray-400">{t('Duration')}</p>
                                <p className="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                                    {Math.ceil((new Date(contract.end_date).getTime() - new Date(contract.start_date).getTime()) / (1000 * 60 * 60 * 24))}d
                                </p>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{t('Total Days')}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content Tabs */}
            <div className="bg-white rounded-lg shadow">
                <Tabs value={activeTab} onValueChange={setActiveTab} className="relative">
                    <div className="border-b bg-gradient-to-r from-gray-50 to-blue-50 relative z-10">
                        <TabsList className="h-12 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border-0 rounded-none p-0 shadow-none relative z-20 flex-wrap w-full justify-center">
                            <TabsTrigger value="overview" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary dark:text-gray-400 dark:hover:text-primary dark:data-[state=active]:text-white">
                                <Eye className="h-4 w-4 mr-2" />
                                Overview
                            </TabsTrigger>
                            <TabsTrigger value="notes" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary  dark:text-gray-400 dark:hover:text-primary dark:data-[state=active]:text-white">
                                <Pin className="h-4 w-4 mr-2" />
                                Notes ({contract.notes?.length || 0})
                            </TabsTrigger>
                            <TabsTrigger value="comments" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary  dark:text-gray-400 dark:hover:text-primary dark:data-[state=active]:text-white">
                                <MessageSquare className="h-4 w-4 mr-2" />
                                Comments ({contract.comments?.length || 0})
                            </TabsTrigger>
                            <TabsTrigger value="attachments" className="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg data-[state=active]:scale-105 hover:bg-primary/10 hover:text-primary  dark:text-gray-400 dark:hover:text-primary dark:data-[state=active]:text-white">
                                <Paperclip className="h-4 w-4 mr-2" />
                                Attachments ({contract.attachments?.length || 0})
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div className="p-4 relative overflow-visible z-0">
                        <TabsContent value="overview" className="space-y-6 mt-0">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <FileText className="h-5 w-5 text-blue-500" />
                                            Contract Details
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Subject</label>
                                                <p className="mt-1 font-medium text-sm">{contract.subject}</p>
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Contract ID</label>
                                                <p className="mt-1 font-mono text-sm rounded">{contract.contract_id}</p>
                                            </div>
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Description</label>
                                            <p className="mt-1 text-gray-700 leading-relaxed">{contract.description || 'No description provided'}</p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Type</label>
                                                <div className="mt-1">
                                                    <span
                                                        className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                                        style={{
                                                            backgroundColor: (contract.contract_type?.color || '#007bff') + '20',
                                                            color: contract.contract_type?.color || '#007bff',
                                                            boxShadow: `inset 0 0 0 1px ${(contract.contract_type?.color || '#007bff')}33`,
                                                        }}
                                                    >
                                                        {contract.contract_type?.name}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Status</label>
                                                <div className="mt-1">{getStatusBadge(contract.status)}</div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Contract Value</label>
                                                <p className="mt-1 text-2xl font-medium text-sm">{formatCurrency(contract.contract_value || 0)}</p>
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Currency</label>
                                                <p className="mt-1 font-medium text-sm">{contract.currency}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <User className="h-5 w-5 text-purple-500" />
                                            Client & Timeline
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Client</label>
                                            <div className="mt-2 flex items-center gap-3">
                                                <Avatar>
                                                    <AvatarImage src={contract.client?.avatar} />
                                                    <AvatarFallback>{contract.client?.name?.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="text-sm font-semibold">{contract.client?.name}</p>
                                                    <p className="text-sm text-gray-500">{contract.client?.email}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Start Date</label>
                                                <p className="mt-1 font-medium text-sm">{window.appSettings.formatDateTime(new Date(contract.start_date),false)}</p>
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">End Date</label>
                                                <p className="mt-1 font-medium text-sm">{window.appSettings.formatDateTime(new Date(contract.end_date),false)}</p>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Created</label>
                                                <p className="mt-1 font-medium text-sm">{window.appSettings.formatDateTime(new Date(contract.created_at),false)}</p>
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Created By</label>
                                                <div className="mt-2 flex items-center gap-3">
                                                <Avatar>
                                                    <AvatarImage src={contract.creator?.avatar} />
                                                    <AvatarFallback>{contract.creator?.name?.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="mt-1 font-medium text-sm">{contract.creator?.name}</p>
                                                </div>
                                            </div>
                                                
                                            </div>
                                        </div>
                                        {contract.sent_at && (
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Sent At</label>
                                                <p className="mt-1">{window.appSettings.formatDateTime(new Date(contract.sent_at),false)}</p>
                                            </div>
                                        )}
                                        {contract.signed_at && (
                                            <div>
                                                <label className="text-sm font-medium text-gray-600">Signed At</label>
                                                <p className="mt-1 text-green-600 font-semibold">{window.appSettings.formatDateTime(new Date(contract.signed_at),false)}</p>
                                            </div>
                                        )}
                                        {(contract.company_signature || contract.client_signature) && (
                                            <div className="grid grid-cols-2 gap-4">
                                                {contract.company_signature && (
                                                    <div>
                                                        <label className="text-sm font-medium text-gray-600">Company Signature</label>
                                                        <div className="mt-2 p-2 border rounded">
                                                            <img src={contract.company_signature} alt="Company Signature" className="max-w-full h-auto max-h-20" />
                                                        </div>
                                                    </div>
                                                )}
                                                {contract.client_signature && (
                                                    <div>
                                                        <label className="text-sm font-medium text-gray-600">Client Signature</label>
                                                        <div className="mt-2 p-2 border rounded">
                                                            <img src={contract.client_signature} alt="Client Signature" className="max-w-full h-auto max-h-20" />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>

                            {contract.terms_conditions && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <FileText className="h-5 w-5 text-orange-500" />
                                            Terms & Conditions
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="prose max-w-none">
                                            <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">{contract.terms_conditions}</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {assignedUsers && assignedUsers.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <User className="h-5 w-5 text-blue-500" />
                                            Assigned Users
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex flex-wrap gap-3">
                                            {assignedUsers.map((user: any) => (
                                                <div key={user.id} className="flex items-center gap-2 bg-gray-50 rounded-lg p-2">
                                                    <Avatar className="h-8 w-8">
                                                        <AvatarImage src={user.avatar} />
                                                        <AvatarFallback>{user.name?.charAt(0)}</AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="text-sm font-medium">{user.name}</p>
                                                        <p className="text-xs text-gray-500">{user.email}</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent value="attachments" className="space-y-6 mt-0 relative">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <div className="relative w-full sm:w-64">
                                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Search attachments..."
                                            value={searchAttachments}
                                            onChange={(e) => { setSearchAttachments(e.target.value); setCurrentAttachmentsPage(1); }}
                                            className="w-full pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {getFilteredAttachments().total > 0 && (
                                        <>
                                            <Label className="text-xs text-muted-foreground">Per Page:</Label>
                                            <Select
                                                value={attachmentsPerPage?.toString() || "10"}
                                                onValueChange={(value) => {
                                                    setAttachmentsPerPage(parseInt(value));
                                                    setCurrentAttachmentsPage(1);
                                                }}
                                            >
                                                <SelectTrigger className="w-16 h-8">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="10">10</SelectItem>
                                                    <SelectItem value="25">25</SelectItem>
                                                    <SelectItem value="50">50</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </>
                                    )}
                                    {auth?.permissions?.includes('contract_attachment_create') && (
                                    <Button size="sm" onClick={() => setIsUploadModalOpen(true)}>
                                        <Upload className="h-4 w-4 mr-2" />
                                        Upload Files
                                    </Button>
                                    )}
                                </div>
                            </div>
                            {getFilteredAttachments().items.length > 0 ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                                    {getFilteredAttachments().items.map((attachment: any) => {
                                        const name = attachment.files || '';
                                        const ext = name.split('.').pop()?.toLowerCase() || '';
                                        const isImage = ['jpg','jpeg','png','gif','webp','svg','bmp','ico'].includes(ext);
                                        const fileUrl = attachment.url || `/storage/media/${name}`;

                                        const getFileConfig = () => {
                                            if (isImage) return { icon: Image, bg: 'bg-blue-50', color: 'text-blue-400' };
                                            if (ext === 'pdf') return { icon: FileText, bg: 'bg-red-50', color: 'text-red-500' };
                                            if (['doc','docx'].includes(ext)) return { icon: FileText, bg: 'bg-blue-50', color: 'text-blue-500' };
                                            if (['xls','xlsx','csv'].includes(ext)) return { icon: FileSpreadsheet, bg: 'bg-green-50', color: 'text-green-500' };
                                            if (['zip','rar','7z','tar','gz'].includes(ext)) return { icon: FileArchive, bg: 'bg-yellow-50', color: 'text-yellow-500' };
                                            if (['js','ts','html','css','php','py','json'].includes(ext)) return { icon: FileCode, bg: 'bg-purple-50', color: 'text-purple-500' };
                                            return { icon: File, bg: 'bg-gray-100', color: 'text-gray-400' };
                                        };
                                        const { icon: FileIcon, bg, color } = getFileConfig();

                                        return (
                                        <Card key={attachment.id} className="group hover:shadow-lg transition-all duration-200 border-0 shadow-md hover:scale-[1.02] bg-gradient-to-br from-white to-gray-50">
                                            <CardContent className="p-0">
                                                <div className="relative overflow-hidden rounded-t-lg">
                                                    {isImage ? (
                                                        <div className="relative">
                                                            <img
                                                                src={fileUrl}
                                                                alt={name}
                                                                className="w-full h-24 object-cover transition-transform duration-200 group-hover:scale-105"
                                                                onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                                            />
                                                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                                onClick={() => window.open(fileUrl, '_blank')}>
                                                                <div className="bg-white/25 border border-white/40 rounded-full p-1.5"><Eye className="h-3.5 w-3.5 text-white" /></div>
                                                            </div>
                                                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200" onClick={(e) => e.stopPropagation()}>
                                                                {auth?.permissions?.includes('contract_attachment_view') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-white/90 hover:bg-white shadow-md dark:bg-gray-900/90 dark:hover:bg-gray-900" onClick={() => window.open(fileUrl, '_blank', 'noopener,noreferrer')}>
                                                                            <Eye className="h-4 w-4 text-gray-700" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>View</TooltipContent></Tooltip>
                                                                )}
                                                                {auth?.permissions?.includes('contract_attachment_download') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-white/90 hover:bg-white shadow-md dark:bg-gray-900/90 dark:hover:bg-gray-900" onClick={() => { window.location.href = route('contract-attachments.download', attachment.id); }}>
                                                                            <Download className="h-4 w-4 text-gray-700" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>Download</TooltipContent></Tooltip>
                                                                )}
                                                                {auth?.permissions?.includes('contract_attachment_delete') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-red-500/90 hover:bg-red-600 shadow-md" onClick={() => router.delete(route('contract-attachments.destroy', attachment.id), { onSuccess: () => toast.success('Attachment deleted successfully') })}>
                                                                            <Trash2 className="h-4 w-4 text-white" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>Delete</TooltipContent></Tooltip>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className={`relative w-full h-24 ${bg} flex flex-col items-center justify-center gap-1`}>
                                                            <FileIcon className={`h-8 w-8 ${color}`} />
                                                            <span className="text-xs font-semibold text-gray-500 uppercase">{ext}</span>
                                                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                                onClick={() => window.open(fileUrl, '_blank', 'noopener,noreferrer')}>
                                                                <div className="bg-white/25 border border-white/40 rounded-full p-1.5"><Eye className="h-3.5 w-3.5 text-white" /></div>
                                                            </div>
                                                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200" onClick={(e) => e.stopPropagation()}>
                                                                {auth?.permissions?.includes('contract_attachment_view') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-white/90 hover:bg-white shadow-md" onClick={() => window.open(fileUrl, '_blank', 'noopener,noreferrer')}>
                                                                            <Eye className="h-4 w-4 text-gray-700" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>View</TooltipContent></Tooltip>
                                                                )}
                                                                {auth?.permissions?.includes('contract_attachment_download') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-white/90 hover:bg-white shadow-md" onClick={() => { window.location.href = route('contract-attachments.download', attachment.id); }}>
                                                                            <Download className="h-4 w-4 text-gray-700" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>Download</TooltipContent></Tooltip>
                                                                )}
                                                                {auth?.permissions?.includes('contract_attachment_delete') && (
                                                                    <Tooltip><TooltipTrigger asChild>
                                                                        <Button variant="secondary" size="icon" className="h-8 w-8 bg-red-500/90 hover:bg-red-600 shadow-md" onClick={() => router.delete(route('contract-attachments.destroy', attachment.id), { onSuccess: () => toast.success('Attachment deleted successfully') })}>
                                                                            <Trash2 className="h-4 w-4 text-white" />
                                                                        </Button>
                                                                    </TooltipTrigger><TooltipContent>Delete</TooltipContent></Tooltip>
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="p-2 dark:bg-gray-900 rounded-b-lg">
                                                    <h4 className="font-medium text-xs text-gray-900 truncate mb-1" title={name}>{name || 'Unnamed file'}</h4>
                                                    <div className="text-xs text-gray-500">{window.appSettings.formatDateTime(new Date(attachment.created_at),true)}</div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="text-center py-16">
                                    <Paperclip className="h-10 w-10 text-primary mx-auto mb-4" />
                                    <p className="text-gray-500 mb-4">No attachments yet</p>
                                    {auth?.permissions?.includes('contract_attachment_create') && (
                                        <Button size="sm" onClick={() => setIsUploadModalOpen(true)}>
                                            <Upload className="h-4 w-4 mr-2" />
                                            Add first attachment
                                        </Button>
                                    )}
                                </div>
                            )}
                            {getFilteredAttachments().totalPages > 1 && (
                                <div className="flex items-center justify-between mt-6">
                                    <div className="text-sm text-gray-500">
                                        Showing {(currentAttachmentsPage - 1) * attachmentsPerPage + 1} to {Math.min(currentAttachmentsPage * attachmentsPerPage, getFilteredAttachments().total)} of {getFilteredAttachments().total} attachments
                                    </div>
                                    <div className="flex gap-1">
                                        <Button variant="outline" size="sm" className="px-3" onClick={() => setCurrentAttachmentsPage(p => Math.max(1, p - 1))} disabled={currentAttachmentsPage === 1}>
                                            Previous
                                        </Button>
                                        {Array.from({ length: getFilteredAttachments().totalPages }, (_, i) => i + 1).map(page => (
                                            <Button
                                                key={page}
                                                variant={page === currentAttachmentsPage ? 'default' : 'outline'}
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() => setCurrentAttachmentsPage(page)}
                                            >
                                                {page}
                                            </Button>
                                        ))}
                                        <Button variant="outline" size="sm" className="px-3" onClick={() => setCurrentAttachmentsPage(p => Math.min(getFilteredAttachments().totalPages, p + 1))} disabled={currentAttachmentsPage === getFilteredAttachments().totalPages}>
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="comments" className="mt-0">
                            <Card className="flex flex-col" style={{ height: '600px' }}>
                                <CardHeader className="pb-3 shrink-0">
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <MessageSquare className="h-4 w-4" />
                                        {t('Comments')}
                                    </CardTitle>
                                </CardHeader>
                                {/* Scrollable comments list */}
                                <CardContent className="flex-1 overflow-y-auto p-4 pt-0">
                                    {(contract.comments?.length ?? 0) > 0 ? (
                                        <div className="space-y-3">
                                            {contract.comments.map((comment: any) => (
                                                <div key={comment.id} className="flex items-start gap-3 w-full">
                                                    <Avatar className="h-8 w-8 shrink-0">
                                                        <AvatarImage src={comment.creator?.avatar} alt={comment.creator?.name} />
                                                        <AvatarFallback className="text-xs">
                                                            {comment.creator?.name?.charAt(0)?.toUpperCase() || 'U'}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center justify-between gap-2 mb-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium">{comment.creator?.name}</span>
                                                                <span className="text-xs text-gray-400">{window.appSettings.formatDateTime(new Date(comment.created_at),true)}</span>
                                                            </div>
                                                            <div className="flex gap-1 shrink-0">
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-blue-500 hover:text-blue-700" onClick={() => handleEditComment(comment)}>
                                                                            <Edit className="h-3.5 w-3.5" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>{t('Edit')}</TooltipContent>
                                                                </Tooltip>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500 hover:text-red-700" onClick={() => handleDeleteComment(comment)}>
                                                                            <Trash2 className="h-3.5 w-3.5" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>{t('Delete')}</TooltipContent>
                                                                </Tooltip>
                                                            </div>
                                                        </div>
                                                        <div className="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{comment.comment}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center h-full">
                                            <MessageSquare className="h-10 w-10 text-gray-300 mb-3" />
                                            <p className="text-gray-500 text-sm">{t('No comments yet')}</p>
                                            <p className="text-gray-400 text-xs mt-1">{t('Be the first to add a comment')}</p>
                                        </div>
                                    )}
                                </CardContent>
                                {/* Pinned input at bottom */}
                                <div className="border-t p-4 shrink-0">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-8 w-8 shrink-0">
                                            <AvatarImage src={auth?.user?.avatar} alt={auth?.user?.name} />
                                            <AvatarFallback className="text-xs">
                                                {auth?.user?.name?.charAt(0)?.toUpperCase() || 'U'}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex-1 relative">
                                            <Input
                                                value={newComment}
                                                onChange={(e) => setNewComment(e.target.value)}
                                                onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleAddComment(); } }}
                                                placeholder={t('Write your comment...')}
                                                className="pr-10"
                                            />
                                            <Button
                                                onClick={handleAddComment}
                                                disabled={!newComment.trim()}
                                                size="icon"
                                                className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
                                            >
                                                <Send className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </Card>

                            {/* Edit Comment Dialog */}
                            <Dialog open={!!editingCommentId} onOpenChange={(open) => { if (!open) { setEditingCommentId(null); setEditCommentText(''); } }}>
                                <DialogContent className="max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>{t('Edit Comment')}</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-3">
                                        <Textarea
                                            value={editCommentText}
                                            onChange={(e) => setEditCommentText(e.target.value)}
                                            rows={4}
                                            className="resize-none"
                                        />
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" onClick={() => { setEditingCommentId(null); setEditCommentText(''); }}>
                                                {t('Cancel')}
                                            </Button>
                                            <Button onClick={() => handleUpdateComment(editingCommentId!)} disabled={!editCommentText.trim()}>
                                                {t('Update')}
                                            </Button>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </TabsContent>

                        <TabsContent value="notes" className="space-y-4 mt-0">
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-base flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <Pin className="h-4 w-4" />
                                            {t('Notes')}
                                        </div>
                                        {auth?.permissions?.includes('contract_note_create') && (
                                            <Button size="sm" onClick={() => setIsAddingNote(!isAddingNote)}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                {t('Create Note')}
                                            </Button>
                                        )}
                                    </CardTitle>
                                    {isAddingNote && (
                                        <div className="border-t pt-4 mt-2">
                                            <div className="flex items-start gap-3">
                                                <Avatar className="h-8 w-8 shrink-0">
                                                    <AvatarImage src={auth?.user?.avatar} alt={auth?.user?.name} />
                                                    <AvatarFallback className="text-xs">
                                                        {auth?.user?.name?.charAt(0)?.toUpperCase() || 'U'}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1">
                                                    <Textarea
                                                        value={newNote}
                                                        onChange={(e) => setNewNote(e.target.value)}
                                                        placeholder={t('Write your note...')}
                                                        rows={3}
                                                        className="resize-none mb-2"
                                                        autoFocus
                                                    />
                                                    <div className="flex justify-end gap-2">
                                                        <Button variant="outline" size="sm" onClick={() => { setIsAddingNote(false); setNewNote(''); }}>
                                                            {t('Cancel')}
                                                        </Button>
                                                        <Button onClick={handleAddNote} disabled={!newNote.trim()} size="sm">
                                                            {t('Create')}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardContent className="p-4 pt-0">
                                    {/* Notes list */}
                                    {getSortedNotes().length > 0 ? (
                                        <div className="space-y-3 mb-4">
                                            {getSortedNotes().map((note: any) => (
                                                <div key={note.id} className="flex items-start gap-3 w-full">
                                                    <Avatar className="h-8 w-8 shrink-0">
                                                        <AvatarImage src={note.creator?.avatar} alt={note.creator?.name} />
                                                        <AvatarFallback className="text-xs">
                                                            {note.creator?.name?.charAt(0)?.toUpperCase() || 'U'}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center justify-between gap-2 mb-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-medium">{note.creator?.name}</span>
                                                                {note.is_pinned && (
                                                                    <span className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20">
                                                                        <Pin className="h-3 w-3" />
                                                                        {t('Pinned')}
                                                                    </span>
                                                                )}
                                                                <span className="text-xs text-gray-400">{window.appSettings.formatDateTime(new Date(note.created_at),true)}</span>
                                                            </div>
                                                            <div className="flex gap-1 shrink-0">
                                                                {auth?.permissions?.includes('contract_note_update') && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button variant="ghost" size="icon" className={`h-7 w-7 ${note.is_pinned ? 'text-yellow-500 hover:text-yellow-700' : 'text-gray-400 hover:text-yellow-500'}`} onClick={() => handlePinNote(note.id, note.is_pinned)}>
                                                                                {note.is_pinned ? <PinOff className="h-3.5 w-3.5" /> : <Pin className="h-3.5 w-3.5" />}
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>{note.is_pinned ? t('Unpin') : t('Pin')}</TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button variant="ghost" size="icon" className="h-7 w-7 text-blue-500 hover:text-blue-700" onClick={() => setViewNote(note)}>
                                                                            <Eye className="h-3.5 w-3.5" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>{t('View')}</TooltipContent>
                                                                </Tooltip>
                                                                {auth?.permissions?.includes('contract_note_update') && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button variant="ghost" size="icon" className="h-7 w-7 text-amber-500 hover:text-amber-700" onClick={() => handleEditNote(note)}>
                                                                                <Edit className="h-3.5 w-3.5" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>{t('Edit')}</TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                                {auth?.permissions?.includes('contract_note_delete') && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500 hover:text-red-700" onClick={() => handleDeleteNote(note)}>
                                                                                <Trash2 className="h-3.5 w-3.5" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>{t('Delete')}</TooltipContent>
                                                                    </Tooltip>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{note.note}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-10">
                                            <Pin className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                                            <p className="text-gray-500 text-sm">{t('No notes yet')}</p>
                                            <p className="text-gray-400 text-xs mt-1">{t('Add notes to keep track of important information')}</p>
                                        </div>
                                    )}

                                </CardContent>
                            </Card>

                            {/* Edit Note Dialog */}
                            <Dialog open={!!editingNoteId} onOpenChange={(open) => { if (!open) { setEditingNoteId(null); setEditNoteText(''); } }}>
                                <DialogContent className="max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>{t('Edit Note')}</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-3">
                                        <Textarea
                                            value={editNoteText}
                                            onChange={(e) => setEditNoteText(e.target.value)}
                                            rows={4}
                                            className="resize-none"
                                        />
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" onClick={() => { setEditingNoteId(null); setEditNoteText(''); }}>
                                                {t('Cancel')}
                                            </Button>
                                            <Button onClick={() => handleUpdateNote(editingNoteId!)} disabled={!editNoteText.trim()}>
                                                {t('Update')}
                                            </Button>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </TabsContent>
                    </div>
                </Tabs>
            </div>

            <Dialog open={isUploadModalOpen} onOpenChange={setIsUploadModalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Upload className="h-5 w-5" />
                            {t('Upload Files')}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-6">
                        <div
                            className="relative border-2 border-dashed rounded-xl p-12 text-center transition-all duration-200 border-gray-300 hover:border-gray-400 hover:bg-gray-50"
                        >
                            <div>
                                <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <Upload className="h-8 w-8 text-gray-400" />
                                </div>
                                <h3 className="text-lg font-medium mb-2">
                                    {t('Upload your files')}
                                </h3>
                                <p className="text-sm text-muted-foreground mb-6">
                                    {t('Drag and drop your files here, or click to browse')}
                                </p>

                                <Input
                                    type="file"
                                    multiple
                                    onChange={(e) => setSelectedFiles(Array.from(e.target.files || []))}
                                    className="hidden"
                                    id="file-upload-contract"
                                />

                                <Button
                                    type="button"
                                    onClick={() => document.getElementById('file-upload-contract')?.click()}
                                    disabled={isUploadingAttachment}
                                    size="lg"
                                >
                                    {isUploadingAttachment ? (
                                        <>
                                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                            {t('Uploading...')}
                                        </>
                                    ) : (
                                        <>
                                            <Plus className="h-4 w-4 mr-2" />
                                            {t('Choose Files')}
                                        </>
                                    )}
                                </Button>

                                {selectedFiles.length > 0 && (
                                    <div className="mt-4 text-sm text-gray-600">
                                        {selectedFiles.length} file(s) selected
                                    </div>
                                )}
                            </div>
                        </div>

                        {selectedFiles.length > 0 && (
                            <div className="flex gap-2 justify-end">
                                <Button variant="outline" onClick={() => { setIsUploadModalOpen(false); setSelectedFiles([]); }} disabled={isUploadingAttachment}>
                                    {t('Cancel')}
                                </Button>
                                <Button onClick={() => {
                                    setIsUploadingAttachment(true);
                                    const formData = new FormData();
                                    selectedFiles.forEach(file => formData.append('files[]', file));
                                    router.post(route('contract-attachments.store', contract.id), formData, {
                                        onSuccess: () => {
                                            setIsUploadModalOpen(false);
                                            setSelectedFiles([]);
                                            setIsUploadingAttachment(false);
                                            toast.success('Attachments uploaded successfully');
                                        },
                                        onError: (errors) => {
                                            setIsUploadingAttachment(false);
                                            toast.error(`Failed: ${Object.values(errors).join(', ')}`);
                                        }
                                    });
                                }} disabled={isUploadingAttachment}>
                                    {isUploadingAttachment ? (
                                        <>
                                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                            {t('Uploading...')}
                                        </>
                                    ) : (
                                        <>
                                            <Upload className="h-4 w-4 mr-2" />
                                            {t('Upload Files')}
                                        </>
                                    )}
                                </Button>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* View Note Modal */}
            <Dialog open={!!viewNote} onOpenChange={() => setViewNote(null)}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
                    <DialogHeader className="px-6 pt-6 pb-4 border-b">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-primary/10 rounded-lg">
                                <Eye className="h-5 w-5 text-primary" />
                            </div>
                            <DialogTitle className="text-xl font-semibold">{t('Note Details')}</DialogTitle>
                        </div>
                    </DialogHeader>
                    <div className="px-6 py-4 pb-6 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    {t('Created By')}
                                </label>
                                <div className="mt-2 flex items-center gap-2">
                                    <Avatar className="h-7 w-7">
                                        <AvatarImage src={viewNote?.creator?.avatar} />
                                        <AvatarFallback>{viewNote?.creator?.name?.charAt(0)}</AvatarFallback>
                                    </Avatar>
                                    <p className="text-sm font-medium text-gray-900">{viewNote?.creator?.name || '-'}</p>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <Pin className="h-4 w-4" />
                                    {t('Pinned')}
                                </label>
                                <p className="mt-1">
                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${viewNote?.is_pinned ? 'bg-yellow-50 text-yellow-700 ring-yellow-600/20' : 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
                                        {viewNote?.is_pinned ? t('Pinned') : t('Not Pinned')}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    {t('Created At')}
                                </label>
                                <p className="mt-1 text-sm font-medium text-gray-900">
                                    {viewNote?.created_at ? window.appSettings.formatDateTime(new Date(viewNote.created_at),true) : '-'}
                                </p>
                            </div>
                        </div>
                        {viewNote?.note && (
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <MessageSquare className="h-4 w-4" />
                                    {t('Note')}
                                </label>
                                <p className="mt-1 text-sm font-medium text-gray-900 whitespace-pre-wrap">{viewNote.note}</p>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <CrudDeleteModal
                isOpen={!!noteToDelete}
                onClose={() => setNoteToDelete(null)}
                onConfirm={confirmDeleteNote}
                itemName={noteToDelete?.note?.substring(0, 50) + (noteToDelete?.note?.length > 50 ? '...' : '')}
                entityName="Note"
            />

            <CrudDeleteModal
                isOpen={!!commentToDelete}
                onClose={() => setCommentToDelete(null)}
                onConfirm={confirmDeleteComment}
                itemName={commentToDelete?.comment?.substring(0, 50) + (commentToDelete?.comment?.length > 50 ? '...' : '')}
                entityName="Comment"
            />

        </PageTemplate>
    );
}
