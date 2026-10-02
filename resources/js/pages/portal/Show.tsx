import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { CheckCircle2, AlertCircle, Clock, BarChart2, FileText, Bug, MessageSquare } from 'lucide-react';
import { toast } from '@/components/custom-toast';

const STATUS_COLOR: Record<string, string> = {
    planning:  'bg-blue-100 text-blue-700',
    active:    'bg-green-100 text-green-700',
    on_hold:   'bg-yellow-100 text-yellow-700',
    completed: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-700',
};

export default function PortalShow() {
    const { project, taskStats, bugStats, invoices, token, flash } = usePage().props as any;

    const [bugModal, setBugModal] = useState(false);
    const [bugTitle, setBugTitle] = useState('');
    const [bugDesc, setBugDesc] = useState('');
    const [bugPriority, setBugPriority] = useState('medium');

    useEffect(() => {
        if (flash?.success) { toast.success(flash.success); setBugModal(false); setBugTitle(''); setBugDesc(''); }
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const submitBug = () => {
        router.post(route('portal.submit-bug', token), { title: bugTitle, description: bugDesc, priority: bugPriority });
    };

    const totalTasks = taskStats.reduce((sum: number, s: any) => sum + parseInt(s.count), 0);

    return (
        <>
            <Head title={`${project.title} — Client Portal`} />
            <div className="min-h-screen bg-muted/30">
                {/* Header */}
                <div className="border-b bg-background px-6 py-4 shadow-sm">
                    <div className="mx-auto max-w-4xl flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{project.workspace_name}</p>
                            <h1 className="text-xl font-bold">{project.title}</h1>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className={`rounded-full px-3 py-1 text-sm font-medium ${STATUS_COLOR[project.status] || ''}`}>
                                {project.status?.replace('_', ' ')}
                            </span>
                            <Button size="sm" onClick={() => setBugModal(true)}>
                                <Bug className="mr-1.5 h-4 w-4" /> Report Issue
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="mx-auto max-w-4xl p-6 space-y-6">

                    {/* Welcome message */}
                    {project.portal_message && (
                        <div className="rounded-xl border bg-background p-4 text-sm">{project.portal_message}</div>
                    )}

                    {/* Progress */}
                    <div className="rounded-xl border bg-background p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold flex items-center gap-2"><BarChart2 className="h-4 w-4" /> Overall Progress</h2>
                            <span className="text-lg font-bold">{project.progress ?? 0}%</span>
                        </div>
                        <Progress value={project.progress ?? 0} className="h-3" />
                        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {taskStats.map((s: any) => (
                                <div key={s.stage} className="rounded-lg bg-muted/50 p-3 text-center">
                                    <p className="text-xl font-bold">{s.count}</p>
                                    <p className="text-xs text-muted-foreground truncate">{s.stage}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Timeline */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        {project.start_date && (
                            <div className="rounded-xl border bg-background p-4">
                                <div className="flex items-center gap-2 text-muted-foreground mb-1"><Clock className="h-4 w-4" /><span className="text-xs font-medium">START DATE</span></div>
                                <p className="font-semibold">{new Date(project.start_date).toLocaleDateString()}</p>
                            </div>
                        )}
                        {project.deadline && (
                            <div className="rounded-xl border bg-background p-4">
                                <div className="flex items-center gap-2 text-muted-foreground mb-1"><AlertCircle className="h-4 w-4" /><span className="text-xs font-medium">DEADLINE</span></div>
                                <p className="font-semibold">{new Date(project.deadline).toLocaleDateString()}</p>
                            </div>
                        )}
                    </div>

                    {/* Milestones */}
                    {project.milestones?.length > 0 && (
                        <div className="rounded-xl border bg-background p-5">
                            <h2 className="mb-4 font-semibold flex items-center gap-2"><CheckCircle2 className="h-4 w-4" /> Milestones</h2>
                            <div className="space-y-3">
                                {project.milestones.map((m: any) => (
                                    <div key={m.id} className="flex items-center gap-3">
                                        <div className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${m.status === 'completed' ? 'bg-green-100' : 'bg-muted'}`}>
                                            <CheckCircle2 className={`h-3.5 w-3.5 ${m.status === 'completed' ? 'text-green-600' : 'text-muted-foreground'}`} />
                                        </div>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">{m.title || m.name}</p>
                                            {m.due_date && <p className="text-xs text-muted-foreground">Due {new Date(m.due_date).toLocaleDateString()}</p>}
                                        </div>
                                        <Badge variant={m.status === 'completed' ? 'default' : 'secondary'} className="text-xs">{m.status}</Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Issues summary */}
                    {bugStats.total > 0 && (
                        <div className="rounded-xl border bg-background p-4">
                            <h2 className="mb-3 font-semibold flex items-center gap-2"><Bug className="h-4 w-4" /> Issues</h2>
                            <div className="flex gap-6">
                                <div className="text-center"><p className="text-2xl font-bold">{bugStats.total}</p><p className="text-xs text-muted-foreground">Total</p></div>
                                <div className="text-center"><p className="text-2xl font-bold text-orange-500">{bugStats.open}</p><p className="text-xs text-muted-foreground">Open</p></div>
                                <div className="text-center"><p className="text-2xl font-bold text-green-500">{bugStats.total - bugStats.open}</p><p className="text-xs text-muted-foreground">Resolved</p></div>
                            </div>
                        </div>
                    )}

                    {/* Invoices */}
                    {invoices?.length > 0 && (
                        <div className="rounded-xl border bg-background p-5">
                            <h2 className="mb-4 font-semibold flex items-center gap-2"><FileText className="h-4 w-4" /> Invoices</h2>
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr><th className="pb-2">Invoice</th><th className="pb-2">Amount</th><th className="pb-2">Status</th><th className="pb-2">Due</th></tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoices.map((inv: any) => (
                                        <tr key={inv.id}>
                                            <td className="py-2 font-medium">#{inv.invoice_number}</td>
                                            <td className="py-2">{inv.total_amount}</td>
                                            <td className="py-2"><Badge variant={inv.status === 'paid' ? 'default' : 'secondary'} className="text-xs">{inv.status}</Badge></td>
                                            <td className="py-2 text-muted-foreground">{inv.due_date ? new Date(inv.due_date).toLocaleDateString() : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Recent activity */}
                    {project.activities?.length > 0 && (
                        <div className="rounded-xl border bg-background p-5">
                            <h2 className="mb-4 font-semibold flex items-center gap-2"><MessageSquare className="h-4 w-4" /> Recent Activity</h2>
                            <div className="space-y-3">
                                {project.activities.slice(0, 8).map((a: any) => (
                                    <div key={a.id} className="flex items-start gap-3 text-sm">
                                        <div className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                                        <div className="flex-1">
                                            <p>{a.description}</p>
                                            <p className="text-xs text-muted-foreground">{new Date(a.created_at).toLocaleDateString()}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <p className="text-center text-xs text-muted-foreground">
                        This portal is provided by {project.workspace_name} · Powered by Swatle
                    </p>
                </div>
            </div>

            {/* Report bug modal */}
            <Dialog open={bugModal} onOpenChange={setBugModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>Report an Issue</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>Issue Title *</Label><Input value={bugTitle} onChange={e => setBugTitle(e.target.value)} placeholder="Brief description of the issue" className="mt-1" /></div>
                        <div><Label>Details</Label><Textarea value={bugDesc} onChange={e => setBugDesc(e.target.value)} placeholder="Steps to reproduce, expected vs actual..." className="mt-1" rows={4} /></div>
                        <div><Label>Priority</Label>
                            <Select value={bugPriority} onValueChange={setBugPriority}>
                                <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setBugModal(false)}>Cancel</Button>
                        <Button onClick={submitBug} disabled={!bugTitle.trim()}>Submit Report</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
