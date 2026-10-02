import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent } from '@/components/ui/card';
import { ColorPicker } from '@/components/ui/color-picker';
import { X, Users, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';

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
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface NoteFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    note: Note | null;
    mode: 'create' | 'edit';
    users: User[];
}

export default function NoteFormModal({ isOpen, onClose, note, mode, users }: NoteFormModalProps) {
    const { t } = useTranslation();
    const [formData, setFormData] = useState({
        title: '',
        text: '',
        color: '#3B82F6',
        type: 'personal' as 'personal' | 'shared',
        assign_to: [] as number[]
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<{ [key: string]: string }>({});

    useEffect(() => {
        if (isOpen) {
            if (mode === 'edit' && note) {
                const assignedUsers = note.assign_to ? note.assign_to.split(',').map(id => parseInt(id)) : [];
                setFormData({
                    title: note.title,
                    text: note.text,
                    color: note.color,
                    type: note.type,
                    assign_to: assignedUsers
                });
            } else {
                setFormData({
                    title: '',
                    text: '',
                    color: '#3B82F6',
                    type: 'personal',
                    assign_to: []
                });
            }
            setErrors({});
        }
    }, [isOpen, mode, note]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Ignore double clicks / repeated Enter while a request is in flight -
        // that's how identical duplicate notes were being created.
        if (isSubmitting) return;
        setIsSubmitting(true);

        const newErrors: { [key: string]: string } = {};

        if (!formData.title.trim()) {
            newErrors.title = 'The title field is required.';
        }

        if (!formData.text.trim()) {
            newErrors.text = 'The text field is required.';
        }

        if (formData.type === 'shared' && formData.assign_to.length === 0) {
            newErrors.assign_to = 'Please select at least one user to share with.';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            setIsSubmitting(false);
            return;
        }

        setErrors({});

        const submitData = {
            ...formData,
            assign_to: formData.type === 'shared' ? formData.assign_to : []
        };

        const url = mode === 'edit' && note
            ? route('notes.update', note.id)
            : route('notes.store');

        const method = mode === 'edit' ? 'put' : 'post';

        router[method](url, submitData, {
            onSuccess: () => {
                onClose();
            },
            onError: (errors) => {
                setErrors(errors);
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    const handleUserToggle = (userId: number) => {
        setFormData(prev => ({
            ...prev,
            assign_to: prev.assign_to.includes(userId)
                ? prev.assign_to.filter(id => id !== userId)
                : [...prev.assign_to, userId]
        }));
    };

    const handleTypeChange = (type: 'personal' | 'shared') => {
        setFormData(prev => ({
            ...prev,
            type,
            assign_to: type === 'personal' ? [] : prev.assign_to
        }));
    };

    const getTypeColor = (type: string) => {
        const colors: Record<string, string> = {
            personal: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            shared: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
        };
        return colors[type] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'edit' ? t('Edit Note') : t('Create Note')}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Title */}
                    <div className="space-y-2">
                        <Label htmlFor="title">{t('Title')} <span className="text-red-500">*</span></Label>
                        <Input
                            id="title"
                            value={formData.title}
                            onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
                            placeholder={t('Enter note title')}
                            className={errors.title ? 'border-red-500' : ''}
                        />
                        {errors.title && <p className="text-sm text-red-500">{errors.title}</p>}
                    </div>

                    {/* Content */}
                    <div className="space-y-2">
                        <Label htmlFor="text">{t('Content')} <span className="text-red-500">*</span></Label>
                        <RichTextEditor
                            content={formData.text}
                            onChange={(content) => setFormData(prev => ({ ...prev, text: content }))}
                            placeholder={t('Enter note content')}
                            className={errors.text ? 'border-red-500' : ''}
                        />
                        {errors.text && <p className="text-sm text-red-500">{errors.text}</p>}
                    </div>

                    {/* Color Selection */}
                    <div className="space-y-2">
                        <Label>{t('Color')}</Label>
                        <ColorPicker
                            value={formData.color}
                            onChange={(color) => setFormData(prev => ({ ...prev, color }))}
                            className="w-40"
                        />
                    </div>

                    {/* Note Type */}
                    <div className="space-y-3">
                        <Label>{t('Note Type')}</Label>
                        <RadioGroup
                            value={formData.type}
                            onValueChange={(value) => handleTypeChange(value as 'personal' | 'shared')}
                            className="flex gap-6"
                        >
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="personal" id="personal" />
                                <Label htmlFor="personal" className="flex items-center gap-2 cursor-pointer">
                                    <User className="h-4 w-4" />
                                    {t('Personal')}
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="shared" id="shared" />
                                <Label htmlFor="shared" className="flex items-center gap-2 cursor-pointer">
                                    <Users className="h-4 w-4" />
                                    {t('Shared')}
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* User Selection for Shared Notes */}
                    {formData.type === 'shared' && (
                        <div className="space-y-3">
                            <Label>{t('Select users to share with')} <span className="text-red-500">*</span></Label>
                            <Select
                                value=""
                                onValueChange={(value) => {
                                    if (value && !formData.assign_to.includes(parseInt(value))) {
                                        handleUserToggle(parseInt(value));
                                    }
                                }}
                            >
                                <SelectTrigger className={`bg-white ${errors.assign_to ? 'border-red-500' : ''}`}>
                                    <SelectValue placeholder={t('Select users...')} />
                                </SelectTrigger>
                                <SelectContent searchable className="bg-white border shadow-lg z-[9999]">
                                    {users.map((user) => (
                                        <SelectItem
                                            key={user.id}
                                            value={user.id.toString()}
                                            className="bg-white hover:bg-gray-100"
                                            disabled={formData.assign_to.includes(user.id)}
                                        >
                                            {user.name} ({user.email}) - {user.role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.assign_to && <p className="text-sm text-red-500">{errors.assign_to}</p>}

                            {formData.assign_to.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {formData.assign_to.map((userId) => {
                                        const user = users.find(u => u.id === userId);
                                        return user ? (
                                            <span key={userId} className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                                {user.name}
                                                <button
                                                    type="button"
                                                    onClick={() => handleUserToggle(userId)}
                                                    className="ml-1 cursor-pointer hover:text-red-600"
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </span>
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
                        <Button type="submit" disabled={isSubmitting}>
                            {(mode === 'edit' ? t('Update') : t('Create'))}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
