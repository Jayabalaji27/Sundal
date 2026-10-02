import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';

import { X } from 'lucide-react';
import { GoogleMeeting, Project, User } from '@/types';
import { useTranslation } from 'react-i18next';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    meeting?: GoogleMeeting;
    projects: Project[];
    members: User[];
    googleCalendarEnabled?: boolean;
}

export default function GoogleMeetingModal({ isOpen, onClose, meeting, projects, members, googleCalendarEnabled = false }: Props) {
       const { t } = useTranslation();
    const [formData, setFormData] = useState({
        title: '',
        description: '',
        start_time: '',
        duration: 60,
        project_id: '',
        status: 'scheduled',
        member_ids: [] as string[]
    });
    
    const [projectMembers, setProjectMembers] = useState<User[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (isOpen) {
            if (meeting) {
                setFormData({
                    title: meeting.title || '',
                    description: meeting.description || '',
                    start_time: meeting.start_time ? new Date(meeting.start_time).toISOString().slice(0, 16) : '',
                    duration: meeting.duration || 60,
                    project_id: meeting.project_id?.toString() || 'none',
                    status: meeting.status || 'scheduled',
                    member_ids: meeting.members?.map(m => m.id.toString()) || []
                });
                if (meeting.project_id) {
                    loadProjectMembers(meeting.project_id.toString());
                }
            } else {
                setFormData({
                    title: '',
                    description: '',
                    start_time: '',
                    duration: 60,
                    project_id: 'none',
                    status: 'scheduled',
                    member_ids: []
                });
                setProjectMembers([]);
            }
            setErrors({});
            setIsSubmitting(false);
        }
    }, [isOpen, meeting]);

    const loadProjectMembers = async (projectId: string) => {
        if (!projectId || projectId === 'none') {
            setProjectMembers([]);
            return;
        }
        
        try {
            const response = await fetch(route('api.projects.members', projectId));
            if (response.ok) {
                const data = await response.json();
                setProjectMembers(data || []);
            } else {
                console.error('Failed to load project members:', response.status);
                setProjectMembers([]);
            }
        } catch (error) {
            console.error('Failed to load project members:', error);
            setProjectMembers([]);
        }
    };

    const handleProjectChange = (projectId: string) => {
        const actualProjectId = projectId === 'none' ? '' : projectId;
        handleChange('project_id', actualProjectId);
        handleChange('member_ids', []); // Reset selected members
        loadProjectMembers(actualProjectId);
    };

    const handleChange = (name: string, value: any) => {
        setFormData(prev => ({ ...prev, [name]: value }));
        if (errors[name]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (isSubmitting) return;

        // Client-side validation
        const newErrors: Record<string, string> = {};
        
        if (!formData.title.trim()) {
            newErrors.title = t('The title field is required.');
        }
        
        if (!formData.start_time) {
            newErrors.start_time = t('The start time field is required.');
        }
        
        if (!formData.project_id || formData.project_id === 'none') {
            newErrors.project_id = t('The project field is required.');
        }
        
        if (formData.project_id && formData.project_id !== 'none' && formData.member_ids.length === 0) {
            newErrors.member_ids = t('Please select at least one member.');
        }
        
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }
        
        setIsSubmitting(true);
        setErrors({});

        const submitData = {
            ...formData,
            project_id: formData.project_id === 'none' ? '' : formData.project_id
        };

        if (meeting) {
            router.put(route('google-meetings.update', meeting.id), submitData, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    onClose();
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    setErrors(errors);
                }
            });
        } else {
            router.post(route('google-meetings.store'), submitData, {
                onSuccess: () => {
                    setIsSubmitting(false);
                    onClose();
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    setErrors(errors);
                }
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {meeting ? t('Edit Google Meeting') : t('Create Google Meeting')}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <Label htmlFor="title">{t('Meeting Title')} <span className="text-red-500">*</span></Label>
                        <Input
                            id="title"
                            value={formData.title}
                            onChange={(e) => handleChange('title', e.target.value)}
                            placeholder={t('Enter meeting title')}
                            className={errors.title ? 'border-red-500' : ''}
                        />
                        {errors.title && <p className="text-sm text-red-600">{errors.title}</p>}
                    </div>

                    <div>
                        <Label htmlFor="description">{t('Description')}</Label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => handleChange('description', e.target.value)}
                            placeholder={t('Meeting agenda or description')}
                            rows={3}
                        />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="start_time">{t('Start Date & Time')} <span className="text-red-500">*</span></Label>
                            <Input
                                id="start_time"
                                type="datetime-local"
                                value={formData.start_time}
                                onChange={(e) => handleChange('start_time', e.target.value)}
                                className={errors.start_time ? 'border-red-500' : ''}
                            />
                            {errors.start_time && <p className="text-sm text-red-600">{errors.start_time}</p>}
                        </div>

                        <div>
                            <Label htmlFor="duration">{t('Duration (minutes)')} <span className="text-red-500">*</span></Label>
                            <Input
                                id="duration"
                                type="number"
                                min="15"
                                max="480"
                                value={formData.duration}
                                onChange={(e) => handleChange('duration', parseInt(e.target.value) || 60)}
                                placeholder={t('Enter duration in minutes')}
                                className={errors.duration ? 'border-red-500' : ''}
                            />
                            {errors.duration && <p className="text-sm text-red-600">{errors.duration}</p>}
                        </div>
                    </div>
                    
                    {meeting && (
                        <div>
                            <Label htmlFor="status">{t('Status')}</Label>
                            <Select value={formData.status} onValueChange={(value) => handleChange('status', value)}>
                                <SelectTrigger className="bg-white">
                                    <SelectValue placeholder={t('Select status')} />
                                </SelectTrigger>
                                <SelectContent className="bg-white border shadow-lg z-[9999]">
                                    <SelectItem value="scheduled" className="bg-white hover:bg-gray-100">{t('Scheduled')}</SelectItem>
                                    <SelectItem value="started" className="bg-white hover:bg-gray-100">{t('Started')}</SelectItem>
                                    <SelectItem value="ended" className="bg-white hover:bg-gray-100">{t('Ended')}</SelectItem>
                                    <SelectItem value="cancelled" className="bg-white hover:bg-gray-100">{t('Cancelled')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div>
                        <Label htmlFor="project_id">{t('Project')} <span className="text-red-500">*</span></Label>
                        <Select value={formData.project_id || 'none'} onValueChange={handleProjectChange}>
                            <SelectTrigger className={`bg-white ${errors.project_id ? 'border-red-500' : ''}`}>
                                <SelectValue placeholder={t('Select project')} />
                            </SelectTrigger>
                            <SelectContent searchable className="bg-white border shadow-lg z-[9999]">
                                <SelectItem value="none" className="bg-white hover:bg-gray-100">{t('Select project')}</SelectItem>
                                {projects.map((project) => (
                                    <SelectItem key={project.id} value={project.id.toString()} className="bg-white hover:bg-gray-100">
                                        {project.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.project_id && <p className="text-sm text-red-600">{errors.project_id}</p>}
                    </div>

                    {formData.project_id && formData.project_id !== 'none' && (
                        <div>
                            <Label htmlFor="member_ids">{t('Members')} <span className="text-red-500">*</span></Label>
                            <Select 
                                value="" 
                                onValueChange={(value) => {
                                    if (value && !formData.member_ids.includes(value)) {
                                        handleChange('member_ids', [...formData.member_ids, value]);
                                    }
                                }}
                            >
                                <SelectTrigger className={`bg-white ${errors.member_ids ? 'border-red-500' : ''}`}>
                                    <SelectValue placeholder={t('Select members...')} />
                                </SelectTrigger>
                                <SelectContent searchable className="bg-white border shadow-lg z-[9999]">
                                    {projectMembers.map((member) => (
                                        <SelectItem 
                                            key={member.id} 
                                            value={member.id.toString()} 
                                            className="bg-white hover:bg-gray-100"
                                            disabled={formData.member_ids.includes(member.id.toString())}
                                        >
                                            {member.name} ({member.email}) - {member.role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.member_ids && <p className="text-sm text-red-600">{errors.member_ids}</p>}
                            
                            {formData.member_ids.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {formData.member_ids.map((memberId) => {
                                        const member = projectMembers.find(m => m.id.toString() === memberId);
                                        return member ? (
                                            <Badge key={memberId} variant="secondary" className="flex items-center space-x-1">
                                                <span>{member.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        handleChange('member_ids', formData.member_ids.filter(id => id !== memberId));
                                                    }}
                                                    className="ml-1 cursor-pointer hover:text-red-600"
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </Badge>
                                        ) : null;
                                    })}
                                </div>
                            )}
                        </div>
                    )}


                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit">
                            {(meeting ? t('Update') : t('Create'))}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}