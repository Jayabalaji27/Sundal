import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, Palette } from 'lucide-react';

interface Project {
    id: number;
    title: string;
}

interface Budget {
    id: number;
    project_id: number;
    total_budget: number;
    currency: string;
    period_type: string;
    start_date: string;
    end_date?: string;
    description?: string;
    status: string;
    categories: Array<{
        id: number;
        name: string;
        allocated_amount: number;
        color: string;
        description?: string;
    }>;
}

interface Category {
    name: string;
    allocated_amount: number;
    color: string;
    description: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    budget?: Budget | null;
    mode: 'create' | 'edit';
    currentProject?: Project | null;
}

export default function BudgetFormModal({ isOpen, onClose, budget, mode, currentProject }: Props) {
    const { projects = [], currencies = [], workspace } = usePage().props as { 
        projects?: Project[], 
        currencies?: Array<{code: string, name: string, symbol: string}>,
        workspace?: any
    };
    
    const [formData, setFormData] = useState({
        project_id: '',
        total_budget: '',
        period_type: 'project',
        start_date: '',
        end_date: '',
        description: '',
        status: 'active'
    });

    const [categories, setCategories] = useState<Category[]>([]);
    const [defaultCategories, setDefaultCategories] = useState<Category[]>([]);
    const [errors, setErrors] = useState<any>({});
    const [isLoadingDefaults, setIsLoadingDefaults] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);


    useEffect(() => {
        if (mode === 'edit' && budget) {
            setFormData({
                project_id: budget.project_id.toString(),
                total_budget: budget.total_budget.toString(),
                period_type: budget.period_type,
                start_date: budget.start_date,
                end_date: budget.end_date || '',
                description: budget.description || '',
                status: budget.status
            });
            setCategories(budget.categories.map(cat => ({
                name: cat.name,
                allocated_amount: cat.allocated_amount,
                color: cat.color,
                description: cat.description || ''
            })));
        } else if (mode === 'create') {
            resetForm();
            if (currentProject) {
                setFormData(prev => ({ ...prev, project_id: currentProject.id.toString() }));
            }
            loadDefaultCategories();
        }
    }, [mode, budget, isOpen, currentProject]);



    const resetForm = () => {
        setFormData({
            project_id: '',
            total_budget: '',
            period_type: 'project',
            start_date: '',
            end_date: '',
            description: '',
            status: 'active'
        });
        setCategories([]);
        setErrors({});
        setIsSubmitting(false);
        setIsLoadingDefaults(false);
    };

    const loadDefaultCategories = async () => {
        try {
            setIsLoadingDefaults(true);
            const response = await fetch(route('budgets.default-categories'));
            if (!response.ok) {
                setDefaultCategories([]);
                return;
            }
            const data = await response.json();
            setDefaultCategories(data.categories || []);
        } catch (error) {
            setDefaultCategories([]);
        } finally {
            setIsLoadingDefaults(false);
        }
    };

    const addDefaultCategories = () => {
        if (defaultCategories.length === 0) {
            loadDefaultCategories().then(() => {
                applyDefaultCategories();
            });
        } else {
            applyDefaultCategories();
        }
    };

    const applyDefaultCategories = () => {
        const totalBudget = parseFloat(formData.total_budget) || 0;
        const categoryCount = defaultCategories.length;
        const amountPerCategory = categoryCount > 0 ? totalBudget / categoryCount : 0;

        const newCategories = defaultCategories.map(cat => ({
            name: cat.name,
            allocated_amount: amountPerCategory,
            color: cat.color,
            description: cat.description || ''
        }));

        setCategories(newCategories);
    };



    const addCategory = () => {
        if (mode === 'create' && !formData.project_id) {
            setErrors({ project_id: 'Please select a project first' });
            return;
        }
        if (!formData.total_budget || parseFloat(formData.total_budget) <= 0) {
            setErrors({ total_budget: 'Please enter total budget amount first' });
            return;
        }
        setCategories([...categories, {
            name: '',
            allocated_amount: 0,
            color: '#3B82F6',
            description: ''
        }]);
    };

    const updateCategory = (index: number, field: keyof Category, value: any) => {
        const updated = [...categories];
        updated[index] = { ...updated[index], [field]: value };
        setCategories(updated);
    };

    const removeCategory = (index: number) => {
        setCategories(categories.filter((_, i) => i !== index));
    };

    const getTotalAllocated = () => {
        return categories.reduce((sum, cat) => sum + (parseFloat(cat.allocated_amount.toString()) || 0), 0);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        
        // Client-side validation
        const newErrors: any = {};
        
        if (!formData.project_id) {
            newErrors.project_id = 'Please select a project';
        }
        
        if (!formData.total_budget || parseFloat(formData.total_budget) <= 0) {
            newErrors.total_budget = 'Please enter a valid budget amount';
        }
        
        if (parseFloat(formData.total_budget) > 999999999.99) {
            newErrors.total_budget = 'Budget amount cannot exceed 999,999,999.99';
        }
        
        if (formData.end_date && formData.start_date && formData.end_date <= formData.start_date) {
            newErrors.end_date = 'End date must be after start date';
        }

        // End Date is read-only in edit mode (not sent to/updated by the
        // server) - only validate it as "not in the past" when it's actually
        // editable, otherwise an already-past end date would block every save.
        if (mode === 'create' && formData.end_date && new Date(formData.end_date) < new Date()) {
            newErrors.end_date = 'End date cannot be in the past';
        }
        
        if (categories.length === 0) {
            newErrors.categories = 'Please add at least one budget category';
        }
        
        // Check if any category has empty name
        const hasEmptyNames = categories.some(cat => !cat.name.trim());
        if (hasEmptyNames) {
            newErrors.categories = 'All categories must have a name';
        }
        
        const totalBudget = parseFloat(formData.total_budget);
        const totalAllocated = getTotalAllocated();
        
        if (totalAllocated > totalBudget) {
            newErrors.categories = 'Total allocated amount cannot exceed budget';
        }
        
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        const submitData = {
            ...formData,
            total_budget: totalBudget,
            categories: categories.map((cat, index) => ({
                ...cat,
                allocated_amount: parseFloat(cat.allocated_amount.toString()) || 0,
                sort_order: index + 1
            }))
        };

        setIsSubmitting(true);
        
        const url = mode === 'create' ? route('budgets.store') : route('budgets.update', budget?.id);
        const method = mode === 'create' ? 'post' : 'put';
        
        router[method](url, submitData, {
            onSuccess: () => {
                onClose();
                if (mode === 'create') resetForm();
            },
            onError: (errors) => setErrors(errors),
            onFinish: () => setIsSubmitting(false)
        });
    };

    const colorOptions = [
        '#3B82F6', // Blue
        '#EF4444', // Red  
        '#10B981', // Green
        '#F59E0B', // Yellow
        '#8B5CF6', // Purple
        '#EC4899', // Pink
        '#6B7280', // Gray
        '#84CC16', // Lime
        '#F97316', // Orange
        '#06B6D4', // Cyan
        '#DC2626', // Red-600
        '#059669', // Green-600
        '#7C3AED', // Violet-600
        '#DB2777', // Pink-600
        '#4F46E5', // Indigo-600
        '#0891B2'  // Cyan-600
    ];

    const handleClose = () => {
        if (!isSubmitting) {
            onClose();
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto z-50">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Create Budget' : 'Edit Budget'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="project_id">Project *</Label>
                            {currentProject || mode === 'edit' ? (
                                <Input
                                    value={currentProject?.title || budget?.project?.title || ''}
                                    disabled
                                    className="bg-gray-50"
                                />
                            ) : (
                                <Select
                                    value={formData.project_id}
                                    onValueChange={(value) => setFormData({...formData, project_id: value})}
                                    disabled={mode === 'edit'}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={projects.length ? 'Select project' : 'No projects available for a budget'} />
                                    </SelectTrigger>
                                    <SelectContent searchable className="z-[60]">
                                        {projects.map((project) => (
                                            <SelectItem key={project.id} value={project.id.toString()}>
                                                {project.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            {errors.project_id && <p className="text-red-500 text-sm mt-1">{errors.project_id}</p>}
                        </div>

                        <div>
                            <Label htmlFor="total_budget">Total Budget *</Label>
                            <Input
                                id="total_budget"
                                type="number"
                                step="0.01"
                                max="999999999.99"
                                value={formData.total_budget}
                                onChange={(e) => setFormData({...formData, total_budget: e.target.value})}
                                placeholder="0.00"
                                required
                            />
                            {errors.total_budget && <p className="text-red-500 text-sm mt-1">{errors.total_budget}</p>}
                        </div>



                        <div>
                            <Label htmlFor="period_type">Period Type *</Label>
                            <Select value={formData.period_type} onValueChange={(value) => setFormData({...formData, period_type: value})}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select period type" />
                                </SelectTrigger>
                                <SelectContent className="z-[60]">
                                    <SelectItem value="project">Project Budget</SelectItem>
                                    <SelectItem value="monthly">Monthly Budget</SelectItem>
                                    <SelectItem value="quarterly">Quarterly Budget</SelectItem>
                                    <SelectItem value="yearly">Yearly Budget</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.period_type && <p className="text-red-500 text-sm mt-1">{errors.period_type}</p>}
                        </div>

                        <div>
                            <Label htmlFor="start_date">Start Date *</Label>
                            <Input
                                id="start_date"
                                type={mode === 'edit' ? 'text' : 'date'}
                                value={mode === 'edit' ? (formData.start_date ? new Date(formData.start_date).toLocaleDateString() : 'Not set') : formData.start_date}
                                onChange={mode === 'edit' ? undefined : (e) => setFormData({...formData, start_date: e.target.value})}
                                disabled={mode === 'edit'}
                                className={mode === 'edit' ? 'bg-gray-50' : ''}
                                required={mode === 'create'}
                            />
                        </div>

                        <div>
                            <Label htmlFor="end_date">End Date</Label>
                            <Input
                                id="end_date"
                                type={mode === 'edit' ? 'text' : 'date'}
                                value={mode === 'edit' ? (formData.end_date ? new Date(formData.end_date).toLocaleDateString() : 'Ongoing') : formData.end_date}
                                onChange={mode === 'edit' ? undefined : (e) => setFormData({...formData, end_date: e.target.value})}
                                disabled={mode === 'edit'}
                                className={mode === 'edit' ? 'bg-gray-50' : ''}
                            />
                            {errors.end_date && <p className="text-red-500 text-sm mt-1">{errors.end_date}</p>}
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => setFormData({...formData, description: e.target.value})}
                            placeholder="Budget description..."
                            rows={3}
                        />
                    </div>

                    {/* Budget Categories */}
                    <div>
                        <div className="flex items-center justify-between mb-4">
                            <Label className="text-base font-medium">Budget Categories *</Label>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addCategory}
                                    disabled={(mode === 'create' && !formData.project_id) || !formData.total_budget || parseFloat(formData.total_budget) <= 0}
                                >
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Category
                                </Button>
                            </div>
                        </div>

                        {categories.length === 0 && (
                            <div className="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                                <Palette className="h-12 w-12 mx-auto mb-3 text-gray-400" />
                                <p className="text-gray-500 font-medium">No categories added yet</p>
                                <p className="text-sm text-gray-400 mb-4">Add categories to organize your budget allocation</p>

                            </div>
                        )}

                        <div className="space-y-4">
                            {categories.map((category, index) => (
                                <div key={index} className="p-4 border rounded-lg bg-gray-50">
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <Label>Category Name *</Label>
                                            <Input
                                                value={category.name}
                                                onChange={(e) => updateCategory(index, 'name', e.target.value)}
                                                placeholder="e.g., Development"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label>Allocated Amount *</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={category.allocated_amount}
                                                onChange={(e) => updateCategory(index, 'allocated_amount', parseFloat(e.target.value) || 0)}
                                                placeholder="0.00"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label>Color</Label>
                                            <div className="flex gap-2 items-center">
                                                <div 
                                                    className="w-8 h-8 rounded border-2 border-gray-300"
                                                    style={{ backgroundColor: category.color }}
                                                />
                                                <input
                                                    type="color"
                                                    value={category.color}
                                                    onChange={(e) => updateCategory(index, 'color', e.target.value)}
                                                    className="w-12 h-8 rounded border cursor-pointer"
                                                />
                                                <div className="grid grid-cols-4 gap-1">
                                                    {colorOptions.slice(0, 8).map((color) => (
                                                        <button
                                                            key={color}
                                                            type="button"
                                                            className="w-4 h-4 rounded border hover:scale-110 transition-transform"
                                                            style={{ backgroundColor: color }}
                                                            onClick={() => updateCategory(index, 'color', color)}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => removeCategory(index)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-2">
                                        <Label>Description</Label>
                                        <Input
                                            value={category.description}
                                            onChange={(e) => updateCategory(index, 'description', e.target.value)}
                                            placeholder="Category description..."
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>

                        {categories.length > 0 && (
                            <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div className="flex justify-between text-sm">
                                    <span>Total Allocated:</span>
                                    <span className={getTotalAllocated() > parseFloat(formData.total_budget) ? 'text-red-600 font-medium' : 'text-green-600'}>
                                        {(() => { if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) { return window.appSettings.formatCurrency(getTotalAllocated(), { showSymbol: true }); } return getTotalAllocated().toFixed(2); })()}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span>Total Budget:</span>
                                    <span>{(() => { if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) { return window.appSettings.formatCurrency(parseFloat(formData.total_budget) || 0, { showSymbol: true }); } return (parseFloat(formData.total_budget) || 0).toFixed(2); })()}</span>
                                </div>
                                <div className="flex justify-between text-sm font-medium">
                                    <span>Remaining:</span>
                                    <span className={parseFloat(formData.total_budget) - getTotalAllocated() < 0 ? 'text-red-600' : 'text-green-600'}>
                                        {(() => { if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) { return window.appSettings.formatCurrency((parseFloat(formData.total_budget) || 0) - getTotalAllocated(), { showSymbol: true }); } return ((parseFloat(formData.total_budget) || 0) - getTotalAllocated()).toFixed(2); })()}
                                    </span>
                                </div>
                            </div>
                        )}

                        {errors.categories && <p className="text-red-500 text-sm mt-1">{errors.categories}</p>}
                    </div>

                    <div className="flex justify-end gap-2 pt-4 border-t">
                        <Button type="button" variant="outline" onClick={handleClose} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            disabled={categories.length === 0 || !formData.project_id || !formData.total_budget || isSubmitting}
                        >
                            {isSubmitting ? 'Saving...' : (mode === 'create' ? 'Create Budget' : 'Update Budget')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}