import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Trash2, CalendarDays, Package, FileText, ArrowLeft } from 'lucide-react';
import { Separator } from '@/components/ui/separator';
import { formatCurrency } from '@/utils/currency';
import { todayLocalDateString } from '@/utils/formHelpers';

interface InvoiceItem {
    type: 'task';
    description: string;
    rate: number;
    amount: number;
    task_id: number | null;
}

interface Props {
    invoice?: any;
    projects: any[];
    clients: any[];
    currencies: any[];
    taxes: any[];
}

export default function InvoiceForm({ invoice, projects, clients, currencies, taxes }: Props) {
    const { t } = useTranslation();
    const isEdit = !!invoice;

    const [formData, setFormData] = useState({
        project_id: invoice?.project_id?.toString() || '',
        client_id: invoice?.client_id?.toString() || '',
        title: invoice?.title || '',
        description: invoice?.description || '',
        invoice_date: invoice?.invoice_date ? new Date(invoice.invoice_date).toISOString().split('T')[0] : todayLocalDateString(),
        due_date: invoice?.due_date ? new Date(invoice.due_date).toISOString().split('T')[0] : '',

        selected_taxes: invoice?.selected_taxes || [],

        currency: invoice?.currency || 'USD',
        notes: invoice?.notes || '',
        terms: invoice?.terms || '',
    });

    const [items, setItems] = useState<InvoiceItem[]>(
        invoice?.items?.map((item: any) => ({
            type: 'task',
            description: item.description || '',
            rate: item.rate || 0,
            amount: item.amount || 0,
            task_id: item.task_id,
        })) || [{
            type: 'task',
            description: '',
            rate: 0,
            amount: 0,
            task_id: null
        }]
    );

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [projectTasks, setProjectTasks] = useState([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [projectClients, setProjectClients] = useState([]);
    const [availableClients, setAvailableClients] = useState(clients || []);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Invoices'), href: route('invoices.index') },
        { title: isEdit ? `${t('Edit')} ${invoice.invoice_number}` : t('Create Invoice') }
    ];

    useEffect(() => {
        if (formData.project_id) {
            loadProjectData(formData.project_id);
        }
    }, []);

    const handleInputChange = (field: string, value: any) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));

        if (field === 'project_id' && value) {
            loadProjectData(value);
            setItems([{ type: 'task', description: '', rate: 0, amount: 0, task_id: null }]);
        } else if (field === 'project_id' && !value) {
            setProjectTasks([]);
            setProjectClients([]);
            setAvailableClients(clients || []);
            setItems([{ type: 'task', description: '', rate: 0, amount: 0, task_id: null }]);
        }
    };

    const loadProjectData = async (projectId: string) => {
        try {
            const response = await fetch(route('api.projects.invoice-data', projectId), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            setProjectTasks(data.tasks || []);
            setProjectClients(data.clients || []);
            setAvailableClients(data.clients || []);
        } catch (error) {
            console.error('Failed to load project data:', error);
            setProjectTasks([]);
            setProjectClients([]);
            setAvailableClients([]);
        }
    };

    const handleItemChange = (index: number, field: string, value: any) => {
        const updatedItems = [...items];
        updatedItems[index] = {
            ...updatedItems[index],
            [field]: value
        };
        setItems(updatedItems);
    };

    const addItem = () => {
        setItems([...items, {
            type: 'task',
            description: '',
            rate: 0,
            amount: 0,
            task_id: null
        }]);
    };

    const removeItem = (index: number) => {
        if (items.length > 1) {
            setItems(items.filter((_, i) => i !== index));
        }
    };

    const calculateSubtotal = () => {
        return items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    };

    const calculateTax = () => {
        if (!formData.selected_taxes || !formData.selected_taxes.length) return 0;
        const subtotal = calculateSubtotal();
        return formData.selected_taxes.reduce((total: number, taxId: number) => {
            const tax = taxes?.find(t => t.id == taxId);
            return total + (subtotal * (tax?.rate || 0)) / 100;
        }, 0);
    };

    const calculateTotal = () => {
        return calculateSubtotal() + calculateTax();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const submitData = {
            ...formData,
            items: items.filter(item => item.task_id !== null && item.task_id !== 'no-tasks')
        };

        if (isEdit) {
            router.put(route('invoices.update', invoice.id), submitData, {
                onSuccess: () => setIsSubmitting(false),
                onError: (errors) => {
                    setIsSubmitting(false);
                    setErrors(errors);
                }
            });
        } else {
            router.post(route('invoices.store'), submitData, {
                onSuccess: () => setIsSubmitting(false),
                onError: (errors) => {
                    setIsSubmitting(false);
                    setErrors(errors);
                }
            });
        }
    };

    return (
        <PageTemplate
            title={isEdit ? `${t('Edit Invoice')} ${invoice.invoice_number}` : t('Create Invoice')}
            url={isEdit ? `/invoices/${invoice.id}/edit` : "/invoices/create"}
            breadcrumbs={breadcrumbs}
            actions={[
                {
                    label: t('Back'),
                    icon: <ArrowLeft className="h-4 w-4 mr-2" />,
                    variant: 'outline',
                    onClick: () => router.get(route('invoices.index'))
                }
            ]}
        >
            <form onSubmit={handleSubmit} className="space-y-6">

                {/* Invoice Details */}
                <Card className="shadow-sm border">
                    <CardHeader className="pb-4 border-b bg-muted/30">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                            <div className="h-7 w-7 rounded-md bg-primary/10 flex items-center justify-center">
                                <CalendarDays className="h-4 w-4 text-primary" />
                            </div>
                            {t('Invoice Details')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-5">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                            <div className="space-y-1.5">
                                <Label required htmlFor="project_id">{t('Project')}</Label>
                                <Select
                                    value={formData.project_id}
                                    onValueChange={(value) => handleInputChange('project_id', value)}
                                >
                                    <SelectTrigger className={errors.project_id ? 'border-red-500' : ''}>
                                        <SelectValue placeholder={t('Select project')} />
                                    </SelectTrigger>
                                    <SelectContent searchable className="z-[9999]">
                                        {projects?.map((project: any) => (
                                            <SelectItem key={project.id} value={project.id.toString()}>
                                                {project.title}
                                            </SelectItem>
                                        ))}
                                        {(!projects || projects.length === 0) && (
                                            <SelectItem value="no-projects" disabled>
                                                {t('No projects available')}
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.project_id && <p className="text-sm text-red-600">{errors.project_id}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label required htmlFor="client_id">{t('Client')}</Label>
                                <Select
                                    value={formData.client_id}
                                    onValueChange={(value) => handleInputChange('client_id', value)}
                                >
                                    <SelectTrigger className={errors.client_id ? 'border-red-500' : ''}>
                                        <SelectValue placeholder={t('Select client')} />
                                    </SelectTrigger>
                                    <SelectContent searchable className="z-[9999]">
                                        {availableClients?.map((client: any) => (
                                            <SelectItem key={client.id} value={client.id.toString()}>
                                                {client.name}
                                            </SelectItem>
                                        ))}
                                        {(!availableClients || availableClients.length === 0) && (
                                            <SelectItem value="no-clients" disabled>
                                                {formData.project_id ? t('No clients for this project') : t('No clients available')}
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.client_id && <p className="text-sm text-red-600">{errors.client_id}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label required htmlFor="invoice_date">{t('Invoice Date')}</Label>
                                <Input
                                    id="invoice_date"
                                    type="date"
                                    value={formData.invoice_date}
                                    onChange={(e) => handleInputChange('invoice_date', e.target.value)}
                                    className={errors.invoice_date ? 'border-red-500' : ''}
                                />
                                {errors.invoice_date && <p className="text-sm text-red-600">{errors.invoice_date}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label required htmlFor="due_date">{t('Due Date')}</Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={formData.due_date}
                                    onChange={(e) => handleInputChange('due_date', e.target.value)}
                                    className={errors.due_date ? 'border-red-500' : ''}
                                />
                                {errors.due_date && <p className="text-sm text-red-600">{errors.due_date}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                            <div className="space-y-1.5">
                                <Label required htmlFor="title">{t('Invoice Title')}</Label>
                                <Input
                                    id="title"
                                    value={formData.title}
                                    onChange={(e) => handleInputChange('title', e.target.value)}
                                    placeholder={t('Enter invoice title')}
                                    className={errors.title ? 'border-red-500' : ''}
                                />
                                {errors.title && <p className="text-sm text-red-600">{errors.title}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Input
                                    id="description"
                                    value={formData.description}
                                    onChange={(e) => handleInputChange('description', e.target.value)}
                                    placeholder={t('Enter invoice description')}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="notes">{t('Notes')}</Label>
                                <Textarea
                                    id="notes"
                                    value={formData.notes}
                                    onChange={(e) => handleInputChange('notes', e.target.value)}
                                    placeholder={t('Internal notes')}
                                    rows={3}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="terms">{t('Terms & Conditions')}</Label>
                                <Textarea
                                    id="terms"
                                    value={formData.terms}
                                    onChange={(e) => handleInputChange('terms', e.target.value)}
                                    placeholder={t('Payment terms and conditions')}
                                    rows={3}
                                />
                            </div>
                        </div>

                        <div className="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div className="space-y-1.5">
                            <Label>{t('Tax')}</Label>
                            <Select
                                value=""
                                onValueChange={(value) => {
                                    const taxId = parseInt(value);
                                    if (!formData.selected_taxes.includes(taxId)) {
                                        handleInputChange('selected_taxes', [...formData.selected_taxes, taxId]);
                                    }
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('Select tax')} />
                                </SelectTrigger>
                                <SelectContent searchable className="z-[9999]">
                                    {taxes?.filter(tax => !formData.selected_taxes.includes(tax.id)).map((tax: any) => (
                                        <SelectItem key={tax.id} value={tax.id.toString()}>
                                            {tax.name} ({tax.rate}%)
                                        </SelectItem>
                                    ))}
                                    {taxes?.filter(tax => !formData.selected_taxes.includes(tax.id)).length === 0 && (
                                        <SelectItem value="no-taxes" disabled>
                                            {taxes?.length === 0 ? t('No taxes configured') : t('All taxes selected')}
                                        </SelectItem>
                                    )}
                                </SelectContent>
                            </Select>
                            {formData.selected_taxes.length > 0 && (
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {formData.selected_taxes.map((taxId: number) => {
                                        const tax = taxes?.find(t => t.id === taxId);
                                        if (!tax) return null;
                                        const taxAmount = (calculateSubtotal() * tax.rate) / 100;
                                        return (
                                            <div key={taxId} className="flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                                <span>{tax.name} ({tax.rate}%): {formatCurrency(taxAmount)}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        handleInputChange('selected_taxes', formData.selected_taxes.filter((id: number) => id !== taxId));
                                                    }}
                                                    className="ml-1 text-blue-600 cursor-pointer hover:text-blue-800 font-bold"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Invoice Items */}
                <Card className="shadow-sm border">
                    <CardHeader className="py-3 border-b bg-muted/30">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                <div className="h-7 w-7 rounded-md bg-primary/10 flex items-center justify-center">
                                    <Package className="h-4 w-4 text-primary" />
                                </div>
                                {t('Invoice Items')}
                            </CardTitle>
                            <Button type="button" onClick={addItem} variant="default" size="sm">
                                <Plus className="h-4 w-4 mr-1.5" />
                                {t('Add Item')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto mx-4 mt-4 mb-4 border rounded-lg">
                            <table className="min-w-full">
                                <thead>
                                    <tr className="bg-muted/40 border-b">
                                        <th className="w-10 px-3 py-3 text-left text-xs font-semibold text-muted-foreground">#</th>
                                        <th className="px-3 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            <Label required className="text-sm font-bold text-muted-foreground">{t('Task')}</Label>
                                        </th>
                                        <th className="w-48 px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            <Label required className="text-sm font-bold text-muted-foreground">{t('Amount')}</Label>
                                        </th>
                                        <th className="w-12 px-4 py-3 text-center text-sm font-semibold text-muted-foreground">{t('Action')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {items.map((item, index) => {
                                        const taskError = errors[`items.${index}.task_id`] || errors['items'];
                                        const amountError = errors[`items.${index}.amount`];
                                        const selectedInOtherRows = items
                                            .filter((_, i) => i !== index)
                                            .map(i => i.task_id)
                                            .filter(Boolean);
                                        const availableTasks = projectTasks.filter(
                                            (task: any) => !selectedInOtherRows.includes(task.id)
                                        );
                                        return (
                                            <tr key={index} className="hover:bg-muted/20 transition-colors">
                                                <td className="px-4 py-3 text-sm text-muted-foreground font-medium align-middle">
                                                    {index + 1}
                                                </td>
                                                <td className="px-4 py-3 align-middle">
                                                    <Select
                                                        value={item.task_id?.toString() || ''}
                                                        onValueChange={(value) => handleItemChange(index, 'task_id', value ? parseInt(value) : null)}
                                                    >
                                                        <SelectTrigger className={taskError ? 'border-red-500' : ''}>
                                                            <SelectValue placeholder={t('Select task')} />
                                                        </SelectTrigger>
                                                        <SelectContent searchable className="z-[9999]">
                                                            {availableTasks.map((task: any) => (
                                                                <SelectItem key={task.id} value={task.id.toString()}>
                                                                    {task.title}
                                                                </SelectItem>
                                                            ))}
                                                            {availableTasks.length === 0 && (
                                                                <SelectItem value="no-tasks" disabled>
                                                                    {formData.project_id ? t('No tasks available') : t('Select project first')}
                                                                </SelectItem>
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {taskError && <p className="text-sm text-red-600 mt-1">{taskError}</p>}
                                                </td>
                                                <td className="px-4 py-3 align-middle">
                                                    <div className={`flex items-center rounded-md border overflow-hidden ${amountError ? 'border-red-500' : 'border-input'}`}>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={item.amount}
                                                            onChange={(e) => handleItemChange(index, 'amount', parseFloat(e.target.value) || 0)}                                                        />
                                                    </div>
                                                    {amountError && <p className="text-sm text-red-600 mt-1">{amountError}</p>}
                                                </td>
                                                <td className="px-4 py-3 align-middle">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeItem(index)}
                                                        disabled={items.length === 1}
                                                        className="text-red-500 hover:text-red-700 hover:bg-red-50 h-8 w-8"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Summary */}
                        <div className="flex justify-end px-4 pb-4 pt-4 border-t">
                            <div className="w-full sm:w-72 border rounded-lg overflow-hidden">
                                <div className="px-4 py-2.5 bg-muted/50 border-b">
                                    <h3 className="text-sm font-semibold">{t('Invoice Summary')}</h3>
                                </div>
                                <div className="px-4 py-3 space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">{t('Subtotal')}</span>
                                        <span className="font-medium">{formatCurrency(calculateSubtotal())}</span>
                                    </div>
                                    {formData.selected_taxes.length > 0 && formData.selected_taxes.map((taxId: number) => {
                                        const tax = taxes?.find(t => t.id === taxId);
                                        if (!tax) return null;
                                        const taxAmount = (calculateSubtotal() * tax.rate) / 100;
                                        return (
                                            <div key={taxId} className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">{tax.name} ({tax.rate}%)</span>
                                                <span className="font-medium">{formatCurrency(taxAmount)}</span>
                                            </div>
                                        );
                                    })}
                                    <Separator />
                                    <div className="flex justify-between items-center pt-1">
                                        <span className="font-semibold">{t('Total')}</span>
                                        <span className="font-bold text-lg">{formatCurrency(calculateTotal())}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Footer actions */}
                <div className="flex justify-between items-center py-4 border-t">
                    <div className="text-sm text-muted-foreground">
                        {items.length} {t('items added')}
                    </div>
                    <div className="flex gap-3">
                        <Button type="button" variant="outline" onClick={() => router.visit(route('invoices.index'))}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={isSubmitting || items.length === 0}>
                            {isEdit ? t('Update') : t('Create')}
                        </Button>
                    </div>
                </div>
            </form>
        </PageTemplate>
    );
}
