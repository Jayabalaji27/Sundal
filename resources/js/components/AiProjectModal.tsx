import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sparkles, Loader2, ChevronRight, CheckCircle, AlertCircle, Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface AiTask { title: string; description: string; priority: string; estimated_hours: number; }
interface AiPlan { title: string; description: string; priority: string; estimated_hours: number; tasks: AiTask[]; }

const priorityColor: Record<string,string> = {
    critical: 'border-red-300 text-red-700 bg-red-50',
    urgent:   'border-red-300 text-red-700 bg-red-50',
    high:     'border-orange-300 text-orange-700 bg-orange-50',
    medium:   'border-blue-300 text-blue-700 bg-blue-50',
    low:      'border-gray-300 text-gray-600 bg-gray-50',
};

export function AiProjectModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { t } = useTranslation();
    const [step, setStep] = useState<'input'|'review'|'creating'>('input');
    const [requirements, setRequirements] = useState('');
    const [parsing, setParsing] = useState(false);
    const [plan, setPlan] = useState<AiPlan | null>(null);
    const [error, setError] = useState('');

    const handleParse = async () => {
        if (requirements.trim().length < 20) {
            setError(t('Please describe your project in at least 20 characters.'));
            return;
        }
        setError('');
        setParsing(true);
        try {
            const { data } = await axios.post(route('ai.projects.parse'), { requirements });
            if (data.success) {
                setPlan(data.plan);
                setStep('review');
            } else {
                setError(data.message);
            }
        } catch (e: any) {
            setError(e.response?.data?.message ?? t('Failed to parse requirements. Please try again.'));
        } finally {
            setParsing(false);
        }
    };

    const handleCreate = async () => {
        if (!plan) return;
        setStep('creating');
        try {
            const { data } = await axios.post(route('ai.projects.create'), plan);
            if (data.success) {
                toast.success(t('Project created successfully!'));
                onClose();
                router.visit(data.redirect);
            }
        } catch {
            toast.error(t('Failed to create project.'));
            setStep('review');
        }
    };

    const removeTask = (i: number) => {
        if (!plan) return;
        setPlan({ ...plan, tasks: plan.tasks.filter((_, idx) => idx !== i) });
    };

    const reset = () => { setStep('input'); setPlan(null); setRequirements(''); setError(''); };

    return (
        <Dialog open={open} onOpenChange={v => { if (!v) { reset(); onClose(); } }}>
            <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="h-5 w-5 text-primary" />
                        {t('AI Project Generator')}
                    </DialogTitle>
                </DialogHeader>

                {/* Step: Input */}
                {step === 'input' && (
                    <div className="flex flex-col gap-4 flex-1">
                        <p className="text-sm text-muted-foreground">
                            {t('Describe your project in plain English. The AI will generate a structured project plan with tasks instantly.')}
                        </p>
                        <Textarea
                            value={requirements}
                            onChange={e => setRequirements(e.target.value)}
                            placeholder={t("e.g. Build a customer portal for a SaaS app. Users should be able to view invoices, track project progress, submit bugs, and download reports. Need a dashboard, auth, and API integration with our main system.")}
                            className="min-h-[180px] resize-none"
                            autoFocus
                        />
                        {error && <p className="text-sm text-red-500 flex items-center gap-1.5"><AlertCircle className="h-4 w-4" />{error}</p>}
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">{requirements.length} / 3000</span>
                            <Button onClick={handleParse} disabled={parsing || requirements.length < 20} className="gap-2">
                                {parsing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                                {parsing ? t('Generating...') : t('Generate Plan')}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Step: Review */}
                {step === 'review' && plan && (
                    <div className="flex flex-col gap-4 flex-1 overflow-hidden">
                        <div className="space-y-3">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label className="text-xs">{t('Project Title')}</Label>
                                    <Input value={plan.title} onChange={e => setPlan({...plan, title: e.target.value})} className="mt-1" />
                                </div>
                                <div className="flex items-end gap-2">
                                    <div className="flex-1">
                                        <Label className="text-xs">{t('Estimated Hours')}</Label>
                                        <Input type="number" value={plan.estimated_hours} onChange={e => setPlan({...plan, estimated_hours: +e.target.value})} className="mt-1" />
                                    </div>
                                    <Badge variant="outline" className={`mb-0.5 ${priorityColor[plan.priority] ?? ''}`}>{plan.priority}</Badge>
                                </div>
                            </div>
                            <div>
                                <Label className="text-xs">{t('Description')}</Label>
                                <Textarea value={plan.description} onChange={e => setPlan({...plan, description: e.target.value})} className="mt-1 min-h-[60px] resize-none text-sm" />
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <Label className="text-xs font-semibold">{t('Tasks')} ({plan.tasks.length})</Label>
                                <Button size="sm" variant="outline" className="h-7 text-xs gap-1" onClick={() => setPlan({...plan, tasks: [...plan.tasks, {title:'New Task',description:'',priority:'medium',estimated_hours:4}]})}>
                                    <Plus className="h-3 w-3" /> {t('Add')}
                                </Button>
                            </div>
                            <div className="overflow-y-auto max-h-[260px] space-y-1.5 pr-1">
                                {plan.tasks.map((task, i) => (
                                    <div key={i} className="flex items-start gap-2 p-2.5 rounded-lg border bg-muted/30 group">
                                        <ChevronRight className="h-3.5 w-3.5 text-muted-foreground mt-0.5 shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <Input value={task.title} onChange={e => { const t=[...plan.tasks]; t[i]={...t[i],title:e.target.value}; setPlan({...plan,tasks:t}); }}
                                                className="h-7 text-sm border-0 bg-transparent p-0 focus-visible:ring-0 font-medium" />
                                        </div>
                                        <Badge variant="outline" className={`text-[10px] shrink-0 ${priorityColor[task.priority] ?? ''}`}>{task.priority}</Badge>
                                        <span className="text-xs text-muted-foreground shrink-0">{task.estimated_hours}h</span>
                                        <Button size="sm" variant="ghost" className="h-6 w-6 p-0 opacity-0 group-hover:opacity-100 shrink-0" onClick={() => removeTask(i)}>
                                            <Trash2 className="h-3 w-3 text-red-500" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {step === 'creating' && (
                    <div className="flex flex-col items-center justify-center gap-3 py-12">
                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                        <p className="text-sm text-muted-foreground">{t('Creating your project...')}</p>
                    </div>
                )}

                <DialogFooter>
                    {step === 'input' && <Button variant="outline" onClick={() => { reset(); onClose(); }}>{t('Cancel')}</Button>}
                    {step === 'review' && (
                        <>
                            <Button variant="outline" onClick={reset}>{t('Start Over')}</Button>
                            <Button onClick={handleCreate} className="gap-2">
                                <CheckCircle className="h-4 w-4" />
                                {t('Create Project')} ({plan?.tasks.length} {t('tasks')})
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
