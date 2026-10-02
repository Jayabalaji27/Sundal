import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { Users, Calendar, DollarSign, Clock, Pin, Paperclip, Bug, ChevronRight, CheckSquare, Timer, Receipt, BarChart3, AlertTriangle, CheckCircle, Eye, User, File, FileText, FileSpreadsheet, FileArchive, FileCode, Image } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { useState } from 'react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useTranslation } from 'react-i18next';
import { isTimesheetOverdue, getDaysOverdue } from '@/utils/timesheetUtils';
import { BrandProvider } from '@/contexts/BrandContext';
import { useBrandTheme } from '@/hooks/use-brand-theme';
import { Head, usePage } from '@inertiajs/react';

interface projectlinkProps {
    project: any;
    encryptedId: string;
    globalSettings?: any;
}

function ProjectLinkContent({ project, encryptedId }: { project: any; encryptedId: string }) {
    useBrandTheme();

    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('overview');

    // Use only dynamic project data
    const currentProject = project;
    const sharedSettings = currentProject?.shared_settings || {};

    const formatText = (text: string) => {
        if (!text) return '';
        return text.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    };

    // Filter tabs based on shared settings permissions and data availability
    const allTabs = [
        { id: 'overview', label: t('Overview'), icon: Pin, permission: 'overview' },
        { id: 'member', label: t('Team Members'), icon: Users, permission: 'member' },
        { id: 'milestone', label: t('Milestones'), icon: Calendar, permission: 'milestone' },
        { id: 'notes', label: t('Notes'), icon: Pin, permission: 'notes' },
        { id: 'budget', label: t('Budget'), icon: DollarSign, permission: 'budget' },
        { id: 'expenses', label: t('Expenses'), icon: Receipt, permission: 'expenses' },
        { id: 'task', label: t('Tasks'), icon: CheckSquare, permission: 'task' },
        { id: 'recent_bugs', label: t('Recent Bugs'), icon: Bug, permission: 'recent_bugs' },
        { id: 'timesheet', label: t('Timesheets'), icon: Timer, permission: 'timesheet' },
        { id: 'files', label: t('Files'), icon: Paperclip, permission: 'files' },
        { id: 'activity', label: t('Activity'), icon: Clock, permission: 'activity' }
    ];

    // Filter tabs based on shared settings permissions and data availability
    const tabs = allTabs.filter(tab => {
        // Check if permission is disabled in shared settings
        if (sharedSettings[tab.permission] === false) return false;

        // Check if data exists for the section
        switch (tab.id) {
            case 'member':
                return (currentProject?.members && currentProject.members.length > 0) || (currentProject?.clients && currentProject.clients.length > 0);
            case 'milestone':
                return currentProject?.milestones && currentProject.milestones.length > 0;
            case 'notes':
                return currentProject?.notes && (currentProject.notes.data?.length > 0 || currentProject.notes.length > 0);
            case 'budget':
                return currentProject?.budget;
            case 'expenses':
                return currentProject?.expenses && currentProject.expenses.length > 0;
            case 'task':
                return currentProject?.tasks && currentProject.tasks.length > 0;
            case 'recent_bugs':
                return currentProject?.bugs && currentProject.bugs.length > 0;
            case 'timesheet':
                return currentProject?.timesheets && currentProject.timesheets.length > 0;
            case 'files':
                return currentProject?.attachments && (currentProject.attachments.data?.length > 0 || currentProject.attachments.length > 0);
            case 'activity':
                return currentProject?.activities && (currentProject.activities.data?.length > 0 || currentProject.activities.length > 0);
            default:
                return true;
        }
    });

    const getStatusColor = (status: string) => {
        const colors = {
            planning: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            active: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            on_hold: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            completed: 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getMilestoneStatusColor = (status: string) => {
        const colors = {
            pending: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            in_progress: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            completed: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            on_hold: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            high: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            urgent: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            critical: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[priority as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const scrollToSection = (sectionId: string) => {
        setActiveTab(sectionId);
        const element = document.getElementById(sectionId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    };

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-950">
            <Head title={`Project Details - ${(usePage().props as any).globalSettings?.titleText || 'Taskly'}`} />
            {/* Language Switcher - Top Right */}
            <div className="absolute top-7 right-4 z-50 mr-75">
                <LanguageSwitcher />
            </div>
            <div className="max-w-7xl mx-auto px-6 py-8 flex gap-6">
                {/* Left Sidebar */}
                <div className="w-72 shrink-0 sticky top-8 h-fit">
                    <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div className="px-4 py-4 border-b border-gray-100 dark:border-gray-800">
                            <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{t('Navigation')}</h2>
                        </div>
                        <div className="p-2">
                            {tabs.map(tab => {
                                const Icon = tab.icon;
                                return (
                                    <button
                                        key={tab.id}
                                        onClick={() => scrollToSection(tab.id)}
                                        className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg mb-0.5 text-sm font-medium transition-colors cursor-pointer ${
                                            activeTab === tab.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                                        }`}
                                    >
                                        <span className="flex items-center gap-2.5">
                                            <Icon className="h-4 w-4 shrink-0" />
                                            {tab.label}
                                        </span>
                                        <ChevronRight className="h-3.5 w-3.5 opacity-50" />
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Main Content */}
                <div className="flex-1 space-y-6">
                    {/* Overview Section */}
                    {sharedSettings.overview !== false && (
                    <section id="overview">
                        <div className="mb-4">
                            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Project Overview')}</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Complete project details and statistics')}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                            {/* Project Title Card */}
                            <div className="flex items-start gap-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <div className="p-2.5 bg-blue-100 dark:bg-blue-900 rounded-lg shrink-0">
                                    <Pin className="h-5 w-5 text-blue-600 dark:text-blue-100" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">{currentProject?.title || t('Project Title')}</h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">{currentProject?.description || t('No description available')}</p>
                                    <div className="flex gap-2 mt-3">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(currentProject?.status || 'unknown')}`}>
                                            {formatText(currentProject?.status || 'unknown')}
                                        </span>
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(currentProject?.priority || 'low')}`}>
                                            {formatText(currentProject?.priority || 'low')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {/* Stats Grid */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                {[
                                    { label: t('Team Members'), value: (currentProject?.members?.length || 0) + (currentProject?.clients?.length || 0), icon: Users, iconBg: 'bg-purple-100 dark:bg-purple-900', iconColor: 'text-purple-600 dark:text-purple-100' },
                                    { label: t('Deadline'), value: currentProject?.deadline ? new Date(currentProject.deadline).toLocaleDateString() : t('Not set'), icon: Calendar, iconBg: 'bg-orange-100 dark:bg-orange-900', iconColor: 'text-orange-600 dark:text-orange-100' },
                                    { label: t('Budget'), value: formatCurrency(currentProject?.budget?.total_budget || 0), icon: DollarSign, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100' },
                                    { label: t('Est. Hours'), value: `${currentProject?.estimated_hours || 0}h`, icon: Clock, iconBg: 'bg-blue-100 dark:bg-blue-900', iconColor: 'text-blue-600 dark:text-blue-100' }
                                ].map((item, i) => (
                                    <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div className="flex items-center gap-2 mb-2">
                                            <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                            </div>
                                            <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                        </div>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{item.value}</p>
                                    </div>
                                ))}
                            </div>
                            {/* Project Progress */}
                            {currentProject?.progress !== undefined && (
                                <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                    <div className="flex items-center justify-between mb-3">
                                        <div className="flex items-center gap-2">
                                            <div className="p-1.5 bg-indigo-100 dark:bg-indigo-900 rounded-md">
                                                <BarChart3 className="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-100" />
                                            </div>
                                            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{t('Project Progress')}</span>
                                        </div>
                                        <span className="text-sm font-bold text-primary">{currentProject.progress}%</span>
                                    </div>
                                    <Progress value={currentProject.progress} className="h-1.5" />
                                </div>
                            )}
                        </div>
                    </section>
                    )}

                    {/* Team Members Section */}
                    {sharedSettings.member !== false && ((currentProject?.members && currentProject.members.length > 0) || (currentProject?.clients && currentProject.clients.length > 0)) && (
                        <section id="member">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Team Members')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project team members and clients')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                                <div className={`grid md:grid-cols-2 gap-3 ${((currentProject?.members?.length || 0) + (currentProject?.clients?.length || 0)) >= 6 ? 'max-h-80 overflow-y-auto pr-1' : ''}`}>
                                    {currentProject?.members?.map((member: any) => (
                                        <div key={`member-${member.id}`} className="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                            <Avatar className="h-10 w-10 shrink-0">
                                                {member.user.avatar && <AvatarImage src={member.user.avatar} />}
                                                <AvatarFallback className="bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-sm font-semibold">
                                                    {member.user.name.split(' ').map((n: string) => n[0]).join('')}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{member.user.name}</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{member.user.email}</p>
                                            </div>
                                            <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-primary/10 text-primary ring-1 ring-inset ring-primary/20 shrink-0">
                                                {formatText(member.role)}
                                            </span>
                                        </div>
                                    ))}
                                    {currentProject?.clients?.map((client: any) => (
                                        <div key={`client-${client.id}`} className="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                            <Avatar className="h-10 w-10 shrink-0">
                                                {client.avatar && <AvatarImage src={client.avatar} />}
                                                <AvatarFallback className="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 text-sm font-semibold">
                                                    {client.name.split(' ').map((n: string) => n[0]).join('')}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{client.name}</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{client.email}</p>
                                            </div>
                                            <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 shrink-0">
                                                {t('Client')}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Milestones Section */}
                    {sharedSettings.milestone !== false && currentProject?.milestones && currentProject.milestones.length > 0 && (
                        <section id="milestone">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Milestones')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project milestones and progress')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                                <div className={`space-y-3 ${currentProject.milestones.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                    {currentProject.milestones.map((milestone: any) => (
                                        <div key={milestone.id} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-orange-100 dark:bg-orange-900 rounded-md shrink-0 mt-0.5">
                                                    <Calendar className="h-4 w-4 text-orange-600 dark:text-orange-100" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-start justify-between gap-2 mb-1">
                                                        <div className="min-w-0">
                                                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{milestone.title}</h3>
                                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{milestone.description}</p>
                                                        </div>
                                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium shrink-0 ${getMilestoneStatusColor(milestone.status)}`}>
                                                            {formatText(milestone.status)}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                        <Calendar className="h-3 w-3" />
                                                        {t('Due')}: {new Date(milestone.due_date).toLocaleDateString()}
                                                    </div>
                                                    <Progress value={milestone.progress} className="h-1.5" />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Notes Section */}
                    {sharedSettings.notes !== false && currentProject?.notes && (currentProject.notes.data?.length > 0 || currentProject.notes.length > 0) && (
                        <section id="notes">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Notes')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project notes and documentation')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                                <div className={`space-y-3 ${currentProject.notes.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                    {currentProject.notes.map((note: any) => (
                                        <div key={note.id} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-md shrink-0 mt-0.5">
                                                    <Pin className="h-4 w-4 text-yellow-600 dark:text-yellow-100" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{note.title}</h3>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">{note.content}</p>
                                                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                        <div className="flex items-center gap-1.5">
                                                            <Avatar className="h-5 w-5">
                                                                {note.creator?.avatar && <AvatarImage src={note.creator.avatar} />}
                                                                <AvatarFallback className="text-[10px] bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300">
                                                                    {note.creator?.name?.charAt(0)}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <span>{note.creator?.name}</span>
                                                        </div>
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="h-3 w-3" />
                                                            {new Date(note.created_at).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Budget Section */}
                    {sharedSettings.budget !== false && currentProject?.budget && (
                        <section id="budget">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Budget Overview')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project budget and financial summary')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    {[
                                        { label: t('Total Budget'), value: formatCurrency(currentProject.budget?.total_budget || 0), icon: DollarSign, iconBg: 'bg-blue-100 dark:bg-blue-900', iconColor: 'text-blue-600 dark:text-blue-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Total Spent'), value: formatCurrency(currentProject.budget?.total_spent || 0), icon: Receipt, iconBg: 'bg-red-100 dark:bg-red-900', iconColor: 'text-red-600 dark:text-red-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Remaining'), value: formatCurrency(currentProject.budget?.remaining_budget || 0), icon: DollarSign, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                    ].map((item, i) => (
                                        <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                    <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                                </div>
                                                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                            </div>
                                            <p className={`text-lg font-bold ${item.valueColor}`}>{item.value}</p>
                                        </div>
                                    ))}
                                </div>
                                <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                    <div className="flex items-center gap-2 mb-3">
                                        <div className="p-1.5 bg-blue-100 dark:bg-blue-900 rounded-md">
                                            <BarChart3 className="h-3.5 w-3.5 text-blue-600 dark:text-blue-100" />
                                        </div>
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{t('Budget Progress')}</span>
                                        <span className="ml-auto text-sm font-bold text-gray-900 dark:text-gray-100">{(() => {
                                            const totalBudget = currentProject.budget?.total_budget || 0;
                                            const totalSpent = currentProject.budget?.total_spent || 0;
                                            return totalBudget > 0 ? ((totalSpent / totalBudget) * 100).toFixed(1) : '0.0';
                                        })()}%</span>
                                    </div>
                                    {(() => {
                                        const totalBudget = currentProject.budget?.total_budget || 0;
                                        const totalSpent = currentProject.budget?.total_spent || 0;
                                        const percentage = totalBudget > 0 ? Math.round((totalSpent / totalBudget) * 100) : 0;
                                        return <Progress value={percentage} className="h-1.5" />;
                                    })()}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Expenses Section */}
                    {sharedSettings.expenses !== false && currentProject?.expenses && currentProject.expenses.length > 0 && (
                        <section id="expenses">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Project Expenses')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project expenses and financial records')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {[
                                        { label: t('Total Expenses'), value: currentProject.expenses.length, icon: Receipt, iconBg: 'bg-blue-100 dark:bg-blue-900', iconColor: 'text-blue-600 dark:text-blue-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Approved'), value: currentProject.expenses.filter((e: any) => e.status === 'approved').length, icon: CheckSquare, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Pending'), value: currentProject.expenses.filter((e: any) => e.status === 'pending').length, icon: Clock, iconBg: 'bg-yellow-100 dark:bg-yellow-900', iconColor: 'text-yellow-600 dark:text-yellow-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Total Amount'), value: formatCurrency(currentProject.approved_expenses_total || 0), icon: DollarSign, iconBg: 'bg-purple-100 dark:bg-purple-900', iconColor: 'text-purple-600 dark:text-purple-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                    ].map((item, i) => (
                                        <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                    <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                                </div>
                                                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                            </div>
                                            <p className={`text-xl font-bold ${item.valueColor}`}>{item.value}</p>
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{t('Recent Expenses')}</h3>
                                    <div className={`space-y-3 ${currentProject.expenses.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                        {currentProject.expenses.map((expense: any) => (
                                            <div key={expense.id} className="flex items-start gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <div className="p-2 bg-green-100 dark:bg-green-900 rounded-md shrink-0 mt-0.5">
                                                    <DollarSign className="h-4 w-4 text-green-600 dark:text-green-100" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-start justify-between gap-2 mb-1">
                                                        <div className="min-w-0">
                                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{expense.title}</h4>
                                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{expense.description || t('No description')}</p>
                                                        </div>
                                                        <div className="text-right shrink-0">
                                                            <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{formatCurrency(expense.amount)}</p>
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium mt-1 ${
                                                                expense.status === 'approved' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                                expense.status === 'pending' ? 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20' :
                                                                expense.status === 'rejected' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                                'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
                                                            }`}>
                                                                {formatText(expense.status)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                                        <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{new Date(expense.created_at).toLocaleDateString()}</span>
                                                        {expense.submitter?.name && (
                                                            <span className="flex items-center gap-1.5">
                                                                <Avatar className="h-5 w-5">
                                                                    {expense.submitter?.avatar && <AvatarImage src={expense.submitter.avatar} />}
                                                                    <AvatarFallback className="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                                        {expense.submitter.name.charAt(0)}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                {expense.submitter.name}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Tasks Section */}
                    {sharedSettings.task !== false && currentProject?.tasks && currentProject.tasks.length > 0 && (
                        <section id="task">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Project Tasks')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Current project tasks and assignments')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {[
                                        { label: t('Total Tasks'), value: currentProject.tasks.length, icon: CheckSquare, iconBg: 'bg-blue-100 dark:bg-blue-900', iconColor: 'text-blue-600 dark:text-blue-100', valueColor: 'text-gray-900 dark:text-gray-100', sub: null },
                                        { label: t('Completed'), value: currentProject.tasks.filter((t: any) => ['Completed','Done'].includes(t.task_stage?.name)).length, icon: CheckSquare, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100', valueColor: 'text-gray-900 dark:text-gray-100', sub: `${currentProject.tasks.length > 0 ? ((currentProject.tasks.filter((t: any) => ['Completed','Done'].includes(t.task_stage?.name)).length / currentProject.tasks.length) * 100).toFixed(1) : 0}%` },
                                        { label: t('In Progress'), value: currentProject.tasks.filter((t: any) => ['In Progress','Working'].includes(t.task_stage?.name)).length, icon: Clock, iconBg: 'bg-orange-100 dark:bg-orange-900', iconColor: 'text-orange-600 dark:text-orange-100', valueColor: 'text-gray-900 dark:text-gray-100', sub: `${currentProject.tasks.length > 0 ? ((currentProject.tasks.filter((t: any) => ['In Progress','Working'].includes(t.task_stage?.name)).length / currentProject.tasks.length) * 100).toFixed(1) : 0}%` },
                                        { label: t('Pending'), value: currentProject.tasks.filter((t: any) => !['Completed','Done','In Progress','Working'].includes(t.task_stage?.name)).length, icon: Timer, iconBg: 'bg-purple-100 dark:bg-purple-900', iconColor: 'text-purple-600 dark:text-purple-100', valueColor: 'text-gray-900 dark:text-gray-100', sub: `${currentProject.tasks.length > 0 ? ((currentProject.tasks.filter((t: any) => !['Completed','Done','In Progress','Working'].includes(t.task_stage?.name)).length / currentProject.tasks.length) * 100).toFixed(1) : 0}%` },
                                    ].map((item, i) => (
                                        <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                    <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                                </div>
                                                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                            </div>
                                            <p className={`text-xl font-bold ${item.valueColor}`}>{item.value}</p>
                                            {item.sub && <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{item.sub} {t('of total')}</p>}
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{t('Recent Tasks')}</h3>
                                    <div className={`space-y-3 ${currentProject.tasks.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                        {currentProject.tasks.map((task: any) => (
                                            <div key={task.id} className="flex items-start gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <div className="p-2 bg-blue-100 dark:bg-blue-900 rounded-md shrink-0 mt-0.5">
                                                    <CheckSquare className="h-4 w-4 text-blue-600 dark:text-blue-100" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-start justify-between gap-2 mb-1">
                                                        <div className="min-w-0">
                                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{task.title}</h4>
                                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{task.description || t('No description')}</p>
                                                        </div>
                                                        <div className="flex gap-1.5 shrink-0 flex-wrap justify-end">
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(task.priority)}`}>{formatText(task.priority)}</span>
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                                ['Completed','Done'].includes(task.task_stage?.name) ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                                ['In Progress','Working'].includes(task.task_stage?.name) ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20' :
                                                                'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
                                                            }`}>{task.task_stage?.name || 'To Do'}</span>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                            <Avatar className="h-5 w-5">
                                                                {task.assigned_to?.avatar && <AvatarImage src={task.assigned_to.avatar} />}
                                                                <AvatarFallback className="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                                    {task.assigned_to?.name?.charAt(0) || <User className="h-3 w-3" />}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            {task.assigned_to?.name || t('Unassigned')}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            <div className="flex items-center gap-1">
                                                                <div className="w-12 bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                                                    <div className="bg-primary h-1 rounded-full" style={{ width: `${task.progress || 0}%` }} />
                                                                </div>
                                                                <span className="text-xs text-gray-500 dark:text-gray-400">{task.progress || 0}%</span>
                                                            </div>
                                                            <span className="text-xs text-gray-400 dark:text-gray-500">{task.end_date ? new Date(task.end_date).toLocaleDateString() : t('No due date')}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Bugs Section */}
                    {sharedSettings.recent_bugs !== false && currentProject?.bugs && currentProject.bugs.length > 0 && (
                        <section id="recent_bugs">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Project Bugs')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Current issues and bug reports')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {[
                                        { label: t('Total Bugs'), value: currentProject.bugs.length, icon: Bug, iconBg: 'bg-red-100 dark:bg-red-900', iconColor: 'text-red-600 dark:text-red-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Open Issues'), value: currentProject.bugs.filter((b: any) => !['Resolved','Closed'].includes(b.bug_status?.name)).length, icon: CheckSquare, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Critical'), value: currentProject.bugs.filter((b: any) => b.priority === 'critical').length, icon: AlertTriangle, iconBg: 'bg-orange-100 dark:bg-orange-900', iconColor: 'text-orange-600 dark:text-orange-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Unassigned'), value: currentProject.bugs.filter((b: any) => !b.assigned_to).length, icon: Users, iconBg: 'bg-purple-100 dark:bg-purple-900', iconColor: 'text-purple-600 dark:text-purple-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                    ].map((item, i) => (
                                        <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                    <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                                </div>
                                                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                            </div>
                                            <p className={`text-xl font-bold ${item.valueColor}`}>{item.value}</p>
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{t('Recent Bugs')}</h3>
                                    <div className={`space-y-3 ${currentProject.bugs.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                        {currentProject.bugs.map((bug: any) => (
                                            <div key={bug.id} className="flex items-start gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <div className="p-2 bg-red-100 dark:bg-red-900 rounded-md shrink-0 mt-0.5">
                                                    <Bug className="h-4 w-4 text-red-600 dark:text-red-100" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-start justify-between gap-2 mb-1">
                                                        <div className="min-w-0">
                                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{bug.title}</h4>
                                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{bug.description || t('No description')}</p>
                                                        </div>
                                                        <div className="flex gap-1.5 shrink-0 flex-wrap justify-end">
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(bug.priority)}`}>{formatText(bug.priority)}</span>
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                                bug.severity === 'blocker' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                                bug.severity === 'critical' ? 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20' :
                                                                bug.severity === 'major' ? 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20' :
                                                                'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
                                                            }`}>{formatText(bug.severity || 'minor')}</span>
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                                bug.bug_status?.name === 'New' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                                bug.bug_status?.name === 'In Progress' ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20' :
                                                                bug.bug_status?.name === 'Resolved' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                                'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
                                                            }`}>{bug.bug_status?.name || 'New'}</span>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                        <span className="flex items-center gap-1.5">
                                                            <Avatar className="h-5 w-5">
                                                                {bug.assigned_to?.avatar && <AvatarImage src={bug.assigned_to.avatar} />}
                                                                <AvatarFallback className="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                                    {bug.assigned_to?.name?.charAt(0) || <User className="h-3 w-3" />}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            {bug.assigned_to?.name || t('Unassigned')}
                                                        </span>
                                                        <span className="flex items-center gap-1.5">
                                                            <Avatar className="h-5 w-5">
                                                                {bug.reported_by?.avatar && <AvatarImage src={bug.reported_by.avatar} />}
                                                                <AvatarFallback className="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                                    {bug.reported_by?.name?.charAt(0) || <User className="h-3 w-3" />}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            {t('Reported by')} {bug.reported_by?.name}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Timesheets Section */}
                    {sharedSettings.timesheet !== false && currentProject?.timesheets && currentProject.timesheets.length > 0 && (
                        <section id="timesheet">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Time Tracking')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Team time tracking and hours logged')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {[
                                        { label: t('Total Hours'), value: `${currentProject.total_project_hours?.toFixed(1) || '0.0'}h`, icon: Clock, iconBg: 'bg-blue-100 dark:bg-blue-900', iconColor: 'text-blue-600 dark:text-blue-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Billable Hours'), value: `${currentProject.total_billable_hours?.toFixed(1) || '0.0'}h`, icon: DollarSign, iconBg: 'bg-green-100 dark:bg-green-900', iconColor: 'text-green-600 dark:text-green-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Team Members'), value: currentProject.total_team_members || 0, icon: Users, iconBg: 'bg-purple-100 dark:bg-purple-900', iconColor: 'text-purple-600 dark:text-purple-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                        { label: t('Approved'), value: currentProject.approved_timesheets_count || 0, icon: CheckSquare, iconBg: 'bg-orange-100 dark:bg-orange-900', iconColor: 'text-orange-600 dark:text-orange-100', valueColor: 'text-gray-900 dark:text-gray-100' },
                                    ].map((item, i) => (
                                        <div key={i} className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <div className={`p-1.5 ${item.iconBg} rounded-md`}>
                                                    <item.icon className={`h-3.5 w-3.5 ${item.iconColor}`} />
                                                </div>
                                                <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{item.label}</span>
                                            </div>
                                            <p className={`text-xl font-bold ${item.valueColor}`}>{item.value}</p>
                                        </div>
                                    ))}
                                </div>
                                {/* Hours Status */}
                                {(() => {
                                    const totalHours = currentProject.total_project_hours || 0;
                                    const billableHours = currentProject.total_billable_hours || 0;
                                    const overdueCount = currentProject.timesheets.filter((ts: any) => isTimesheetOverdue(ts.end_date, ts.status)).length;
                                    return totalHours > 0 ? (
                                        <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                            <div className="flex items-center justify-between mb-3">
                                                <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{t('Hours Status')}</span>
                                                <div className="flex items-center gap-2">
                                                    {billableHours === totalHours ? (
                                                        <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-primary/10 text-primary ring-1 ring-inset ring-primary/20"><CheckCircle className="h-3 w-3 mr-1" />{t('All Hours Billable')}</span>
                                                    ) : (
                                                        <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20"><AlertTriangle className="h-3 w-3 mr-1" />{t('Partial Billable')}</span>
                                                    )}
                                                    {overdueCount > 0 && (
                                                        <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20"><AlertTriangle className="h-3 w-3 mr-1" />{overdueCount} {t('Overdue')}</span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-xs text-gray-500 dark:text-gray-400">{t('Submitted Progress')}</span>
                                                <span className="text-sm font-bold text-gray-900 dark:text-gray-100">{currentProject.submitted_timesheets_percentage}%</span>
                                            </div>
                                            <Progress value={currentProject.submitted_timesheets_percentage} className="h-1.5" />
                                        </div>
                                    ) : null;
                                })()}
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{t('Recent Timesheets')}</h3>
                                    <div className={`space-y-3 ${currentProject.timesheets.length > 5 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                        {currentProject.timesheets.map((timesheet: any) => {
                                            const isOverdue = isTimesheetOverdue(timesheet.end_date, timesheet.status);
                                            return (
                                                <div key={timesheet.id} className="flex items-start gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                    <div className="p-2 bg-purple-100 dark:bg-purple-900 rounded-md shrink-0 mt-0.5">
                                                        <Timer className="h-4 w-4 text-purple-600 dark:text-purple-100" />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-start justify-between gap-2 mb-1">
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-2">
                                                                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{t('Week of')} {new Date(timesheet.start_date).toLocaleDateString()}</h4>
                                                                    {isOverdue && (
                                                                        <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">{getDaysOverdue(timesheet.end_date)}d {t('overdue')}</span>
                                                                    )}
                                                                </div>
                                                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{new Date(timesheet.start_date).toLocaleDateString()} – {new Date(timesheet.end_date).toLocaleDateString()}</p>
                                                            </div>
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium shrink-0 ${
                                                                timesheet.status === 'approved' ? 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20' :
                                                                timesheet.status === 'pending' ? 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20' :
                                                                timesheet.status === 'draft' ? 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20' :
                                                                isOverdue ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                                'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20'
                                                            }`}>
                                                                {isOverdue ? formatText('overdue') : formatText(timesheet.status)}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                            <span className="flex items-center gap-1.5">
                                                                <Avatar className="h-5 w-5">
                                                                    {timesheet.user?.avatar && <AvatarImage src={timesheet.user.avatar} />}
                                                                    <AvatarFallback className="text-[10px] bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300">
                                                                        {timesheet.user?.name?.charAt(0)}
                                                                    </AvatarFallback>
                                                                </Avatar>
                                                                {timesheet.user?.name}
                                                            </span>
                                                            <div className="flex items-center gap-3">
                                                                <span className="text-primary font-medium">{timesheet.total_hours || 0}h total</span>
                                                                <span className="text-primary font-medium">{timesheet.billable_hours || 0}h billable</span>
                                                                <span>{timesheet.entries_count || 0} {t('entries')}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Files Section */}
                    {sharedSettings.files !== false && currentProject?.attachments && (currentProject.attachments.data?.length > 0 || currentProject.attachments.length > 0) && (
                        <section id="files">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Files & Attachments')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Project documents and media files')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                                <div className={`grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 ${currentProject.attachments.length > 8 ? 'max-h-96 overflow-y-auto pr-1' : ''}`}>
                                    {currentProject.attachments.map((attachment: any) => {
                                        const name = attachment.media_item?.name || '';
                                        const ext = name.split('.').pop()?.toLowerCase() || '';
                                        const mime = attachment.media_item?.mime_type || '';
                                        const isImage = mime.startsWith('image/') || ['jpg','jpeg','png','gif','webp','svg','bmp','ico'].includes(ext);
                                        const fileUrl = attachment.media_item?.url || '';

                                        const getFileConfig = () => {
                                            if (isImage) return { icon: Image, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-400' };
                                            if (ext === 'pdf' || mime.includes('pdf')) return { icon: FileText, bg: 'bg-red-50 dark:bg-red-900/20', color: 'text-red-500' };
                                            if (['doc','docx'].includes(ext) || mime.includes('word')) return { icon: FileText, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-500' };
                                            if (['xls','xlsx','csv'].includes(ext) || mime.includes('sheet') || mime.includes('excel')) return { icon: FileSpreadsheet, bg: 'bg-green-50 dark:bg-green-900/20', color: 'text-green-500' };
                                            if (['zip','rar','7z','tar','gz'].includes(ext) || mime.includes('zip') || mime.includes('archive')) return { icon: FileArchive, bg: 'bg-yellow-50 dark:bg-yellow-900/20', color: 'text-yellow-500' };
                                            if (['js','ts','html','css','php','py','json'].includes(ext)) return { icon: FileCode, bg: 'bg-purple-50 dark:bg-purple-900/20', color: 'text-purple-500' };
                                            return { icon: File, bg: 'bg-gray-100 dark:bg-gray-700', color: 'text-gray-400 dark:text-gray-500' };
                                        };
                                        const { icon: FileIcon, bg, color } = getFileConfig();

                                        return (
                                        <div key={attachment.id} className="group rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md transition-all duration-200">
                                            <div className="relative aspect-square overflow-hidden">
                                                {isImage && fileUrl ? (
                                                    <>
                                                        <img
                                                            src={fileUrl}
                                                            alt={name}
                                                            className="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                            onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                                        />
                                                        <div
                                                            className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                            onClick={() => window.open(fileUrl, '_blank')}
                                                        >
                                                            <div className="bg-white/25 border border-white/40 rounded-full p-1.5">
                                                                <Eye className="h-3.5 w-3.5 text-white" />
                                                            </div>
                                                        </div>
                                                    </>
                                                ) : (
                                                    <div className={`relative w-full h-full ${bg} flex flex-col items-center justify-center gap-1`}>
                                                        <FileIcon className={`h-8 w-8 ${color}`} />
                                                        <span className="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">{ext}</span>
                                                        <div
                                                            className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                            onClick={() => window.open(fileUrl, '_blank')}
                                                        >
                                                            <div className="bg-white/25 border border-white/40 rounded-full p-1.5">
                                                                <Eye className="h-3.5 w-3.5 text-white" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="p-2.5 space-y-1">
                                                <p className="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" title={name}>{name}</p>
                                                <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                    <Avatar className="h-4 w-4">
                                                        {attachment.uploaded_by?.avatar && <AvatarImage src={attachment.uploaded_by.avatar} />}
                                                        <AvatarFallback className="text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                            {attachment.uploaded_by?.name?.charAt(0) || '?'}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="truncate">{attachment.uploaded_by?.name || 'Unknown'}</span>
                                                </div>
                                                <p className="text-xs text-gray-400 dark:text-gray-500">{new Date(attachment.created_at).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Activity Section */}
                    {sharedSettings.activity !== false && currentProject?.activities && (currentProject.activities.data?.length > 0 || currentProject.activities.length > 0) && (
                        <section id="activity">
                            <div className="mb-4">
                                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">{t('Recent Activity')}</h2>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t('Latest project activities and updates')}</p>
                            </div>
                            <div className="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                                {(() => {
                                    const activities = currentProject.activities.data ?? currentProject.activities;
                                    return activities.length > 0 ? (
                                        <div className="overflow-y-auto panel-scrollbar" style={{ maxHeight: '520px' }}>
                                            <div className="relative">
                                                <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700 -translate-x-1/2" />
                                                {activities.map((activity: any, index: number) => {
                                                    const isLeft = index % 2 === 0;
                                                    return (
                                                        <div key={activity.id} className={`relative flex items-start mb-2 ${isLeft ? 'flex-row' : 'flex-row-reverse'}`}>
                                                            <div className={`w-[calc(50%-20px)] ${isLeft ? 'pr-1' : 'pl-1'}`}>
                                                                <div className={`w-fit max-w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm ${isLeft ? 'text-right ml-auto' : 'text-left'}`}>
                                                                    <div className={`flex items-center gap-3 mt-1.5 text-xs text-gray-500 dark:text-gray-400 ${isLeft ? 'flex-row-reverse' : 'flex-row'}`}>
                                                                        <Avatar className="h-9 w-9">
                                                                            <AvatarImage src={activity.user.avatar} />
                                                                            <AvatarFallback className="text-sm">{activity.user.name.charAt(0)}</AvatarFallback>
                                                                        </Avatar>
                                                                        <div className="flex-1 min-w-0">
                                                                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{activity.user.name}</p>
                                                                            <p className="text-xs text-gray-500 dark:text-gray-400">{new Date(activity.created_at).toLocaleString()}</p>
                                                                        </div>
                                                                    </div>
                                                                    <p className="text-sm text-gray-700 dark:text-gray-300 mt-3 break-words">{activity.description}</p>
                                                                </div>
                                                            </div>
                                                            <div className="absolute left-1/2 -translate-x-1/2 top-1/2 -translate-y-1/2 w-3 h-3 rounded-full bg-primary border-2 border-white dark:border-gray-900 shadow z-10" />
                                                            <div className="w-[calc(50%-20px)]" />
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                            <h3 className="text-lg font-semibold text-gray-900 mb-2">{t('No activity recorded yet')}</h3>
                                            <p className="text-gray-500">{t('Project activities will appear here as they happen.')}</p>
                                        </div>
                                    );
                                })()}
                            </div>
                        </section>
                    )}

                </div>
            </div>
        </div>
    );
}

export default function projectlink({ project, encryptedId, globalSettings }: projectlinkProps) {
    return (
        <BrandProvider globalSettings={globalSettings}>
            <ProjectLinkContent project={project} encryptedId={encryptedId} />
        </BrandProvider>
    );
}