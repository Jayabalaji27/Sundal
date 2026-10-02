import React, { useEffect } from 'react';
import { router, usePage, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Edit, DollarSign, Printer, Send, Link, CreditCard, ArrowLeft, Clock, Check, X, ExternalLink, Download, Calendar } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { formatCurrency } from '@/utils/currency';
import { InvoicePaymentModal } from '@/components/invoices/invoice-payment-modal';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { QRCodeGenerator } from '@/components/QRCodeGenerator';
import { NewYork, Toronto, Rio, London, Istanbul, Mumbai, HongKong, Tokyo, Sydney, Paris } from '../settings/components/invoice-templates';
import { useBrand } from '@/contexts/BrandContext';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePdfDownload } from '@/hooks/usePdfDownload';

interface InvoiceItem {
    id: number;
    type: string;
    description: string;
    rate: number;
    amount: number;
    task?: {
        id: number;
        title: string;
    };
    expense?: {
        id: number;
        title: string;
    };
}

interface Invoice {
    id: number;
    invoice_number: string;
    project: {
        id: number;
        title: string;
    };
    client?: {
        id: number;
        name: string;
        avatar?: string;
        email: string;
    };
    creator: {
        id: number;
        name: string;
        email: string;
    };
    title: string;
    description?: string;
    invoice_date: string;
    due_date: string;
    subtotal: number;
    tax_rate: Array<{ id: number, name: string, rate: number }>;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    paid_amount: number;
    payment_method?: string;
    payment_reference?: string;
    payment_details?: any;
    status: string;
    is_overdue: boolean;
    days_overdue: number;
    balance_due: number;
    notes?: string;
    terms?: string;
    payment_token: string;
    items: InvoiceItem[];
    created_at: string;
}

interface PendingPayment {
    id: number;
    amount: number;
    payment_method: string;
    transaction_id?: string;
    payment_date: string;
    status: string;
    receipt_path?: string;
    receipt_url?: string;
    creator?: { id: number; name: string; email: string; };
    created_at: string;
}

export default function InvoiceShow() {
    const { t } = useTranslation();
    const { invoice, userWorkspaceRole, flash, emailNotificationsEnabled, invoiceSettings, pendingPayments, company } = usePage().props as { invoice: Invoice; userWorkspaceRole: string; flash?: any; emailNotificationsEnabled?: boolean; invoiceSettings?: any; pendingPayments?: PendingPayment[]; };
    const { logoDark } = useBrand();
    const [showPaymentModal, setShowPaymentModal] = React.useState(false);
    const [showMarkPaidModal, setShowMarkPaidModal] = React.useState(false);
    const pdfTemplateRef = React.useRef<HTMLDivElement>(null);
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('payment_status');
        const message = urlParams.get('message');

        if (paymentStatus && message) {
            const decodedMessage = decodeURIComponent(message);
            setTimeout(() => {
                if (paymentStatus === 'success') {
                    toast.success(decodedMessage);
                } else {
                    toast.error(decodedMessage);
                }
            }, 1000);

            const cleanUrl = window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }

        if (flash?.success) {
            const message = flash.success.replace(/&#39;/g, "'").replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            setTimeout(() => {
                toast.success(message);
            }, 1000);
        }
        if (flash?.error) {
            const message = flash.error.replace(/&#39;/g, "'").replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            setTimeout(() => {
                toast.error(message);
            }, 1000);
        }
    }, [flash]);

    const getStatusColor = (status: string) => {
        const colors = {
            draft: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            sent: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            viewed: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            paid: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            partial_paid: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            overdue: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            cancelled: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const handlePrint = () => {
        window.print();
    };

    const handleAction = (action: string) => {
        switch (action) {
            case 'edit':
                router.get(route('invoices.edit', invoice.id));
                break;

            case 'mark-paid':
                setShowMarkPaidModal(true);
                break;

            case 'send':
                toast.loading('Sending invoice...');
                router.post(route('invoices.send', invoice.id), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error('Failed to send invoice');
                    }
                });
                break;

            case 'pay':
                setShowPaymentModal(true);
                break;

            case 'copy-payment-link':
                const paymentUrl = route('invoices.payment', invoice.payment_token);
                navigator.clipboard.writeText(paymentUrl).then(() => {
                    toast.success(t('Payment link copied to clipboard'));
                }).catch(() => {
                    toast.error(t('Failed to copy payment link'));
                });
                break;
        }
    };

    const handlePaymentSuccess = () => {
        router.reload();
    };

    const handleApprovePayment = (paymentId: number) => {
        toast.loading(t('Approving payment...'));
        router.post(route('invoices.payments.approve', { invoice: invoice.id, payment: paymentId }), {}, {
            onSuccess: () => { toast.dismiss(); },
            onError: () => { toast.dismiss(); toast.error(t('Failed to approve payment')); }
        });
    };

    const handleRejectPayment = (paymentId: number) => {
        toast.loading(t('Rejecting payment...'));
        router.post(route('invoices.payments.reject', { invoice: invoice.id, payment: paymentId }), {}, {
            onSuccess: () => { toast.dismiss(); },
            onError: () => { toast.dismiss(); toast.error(t('Failed to reject payment')); }
        });
    };

    const handleMarkPaidConfirm = () => {
        toast.loading('Marking invoice as paid...');
        router.post(route('invoices.mark-paid', invoice.id), {}, {
            onSuccess: () => {
                toast.dismiss();
                setShowMarkPaidModal(false);
            },
            onError: () => {
                toast.dismiss();
                toast.error('Failed to mark invoice as paid');
                setShowMarkPaidModal(false);
            }
        });
    };

    const pageActions = [
        {
            label: t('Print'),
            icon: <Printer className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: handlePrint
        },
        {
            label: t('Download PDF'),
            icon: <Download className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => {
                window.location.href = route('invoices.download-pdf', invoice.id);
            }
        }
    ];

    if (invoice.status === 'draft' && ['owner', 'manager'].includes(userWorkspaceRole)) {
        pageActions.push(
            {
                label: t('Edit'),
                icon: <Edit className="h-4 w-4 mr-2" />,
                variant: 'outline',
                onClick: () => handleAction('edit')
            },
            {
                label: t('Copy Payment Link'),
                icon: <Link className="h-4 w-4 mr-2" />,
                variant: 'outline',
                onClick: () => handleAction('copy-payment-link')
            }
        );

        if (emailNotificationsEnabled) {
            pageActions.push(
                {
                    label: t('Send'),
                    icon: <Send className="h-4 w-4 mr-2" />,
                    variant: 'default',
                    onClick: () => handleAction('send')
                }
            );
        }
    }

    if (invoice.status !== 'paid' && invoice.status !== 'cancelled') {
        if (userWorkspaceRole === 'client') {
            pageActions.push(
                {
                    label: t('Pay Now'),
                    icon: <CreditCard className="h-4 w-4 mr-2" />,
                    variant: 'default',
                    onClick: () => handleAction('pay')
                }
            );
        } else {
            // Only add Copy Payment Link if not already added in draft section
            if (invoice.status !== 'draft') {
                pageActions.push(
                    {
                        label: t('Copy Payment Link'),
                        icon: <Link className="h-4 w-4 mr-2" />,
                        variant: 'outline',
                        onClick: () => handleAction('copy-payment-link')
                    }
                );
            }
            pageActions.push(
                {
                    label: t('Mark as Paid'),
                    icon: <DollarSign className="h-4 w-4 mr-2" />,
                    variant: 'default',
                    onClick: () => handleAction('mark-paid')
                }
            );
        }
    }

    pageActions.push(
        {
            label: t('Back'),
            icon: <ArrowLeft className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('invoices.index'))
        }
    );

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Invoices'), href: route('invoices.index') },
        { title: t('Invoice Detail') }
    ];

    const formatAmount = (amount: number) => {
        return formatCurrency(amount);
    };

    const showQr = invoiceSettings?.invoice_qr_display === 'true' || invoiceSettings?.invoice_qr_display === true;
    const footerTitle = invoiceSettings?.invoice_footer_title || '';
    const footerNotes = invoiceSettings?.invoice_footer_notes || '';
    const templateName = invoiceSettings?.invoice_template || 'london';
    const invoiceColor = invoiceSettings?.invoice_color || '#ffffff';
    const companyLogo = (invoiceSettings?.invoice_logo && invoiceSettings.invoice_logo.trim() !== '') ? invoiceSettings.invoice_logo : logoDark;

    return (
        <>
            <Head>
                <style>{`
                    @media print {
                        @page { margin: 0.5in; }
                        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                        a[href]::after { content: none !important; }
                        .print\:hidden { display: none !important; }
                        .print\:block { display: block !important; }
                    }
                `}</style>
            </Head>
            <div className="print:hidden">
                <PageTemplate
                    title={`${t('Invoice')} #${invoice.invoice_number}`}
                    url={`/invoices/${invoice.id}`}
                    actions={pageActions}
                    breadcrumbs={breadcrumbs}
                >
                    <div className="space-y-6 print:hidden">
                        {/* Customer Details Card */}
                        <Card>
                            <CardContent className="p-4 md:p-12">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="text-xl font-bold tracking-tight">{t('Invoice Details')}</div>
                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-sm font-medium ${getStatusColor(invoice.status)}`}>
                                            {invoice.status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
                                        </span>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {/* Col 1: From */}
                                    <div className="space-y-3">
                                        {invoice.project && (
                                            <div className="mb-5">
                                                <p className="text-sm font-medium mb-1">{t('Project')}</p>
                                                <p className="text-sm text-muted-foreground mb-1">{invoice.project.title}</p>
                                            </div>
                                        )}
                                        <p className="font-medium mb-3">{t('From')}</p>
                                        <div className="flex items-center gap-3">
                                            <div className="min-w-0 space-y-0.5">
                                                <p className="font-bold text-sm leading-snug">{company?.name || invoice.creator?.name || '-'}</p>
                                                {invoice.creator?.email && <p className="text-sm text-muted-foreground">{invoice.creator?.email}</p>}
                                            </div>
                                        </div>
                                        <div className="pt-2">
                                            <div className="inline-flex items-center gap-1.5 text-sm">
                                                <Calendar className="h-3.5 w-3.5 text-muted-foreground flex-shrink-0" />
                                                <span className="font-medium">{t('Invoice Date')} :</span>
                                                <span className="text-muted-foreground">{window.appSettings.formatDateTime(new Date(invoice.invoice_date), false)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Col 2: Bill To */}
                                    <div className="space-y-3">
                                        {invoice.project && <div className="mb-4 invisible"><p className="text-sm mb-1">-</p><p className="text-sm font-medium">-</p></div>}
                                        <p className="font-medium mb-4">{t('Bill To')}</p>
                                        <div className="flex items-center gap-3">
                                            <div className="min-w-0 space-y-0.5">
                                                <p className="font-bold text-sm leading-snug">{invoice.client?.name || '-'}</p>
                                                {invoice.client?.email && <p className="text-sm text-muted-foreground">{invoice.client.email}</p>}
                                            </div>
                                        </div>
                                        <div className="pt-2">
                                            <div className="inline-flex items-center gap-1.5 text-sm">
                                                <Calendar className="h-3.5 w-3.5 text-muted-foreground flex-shrink-0" />
                                                <span className="font-medium">{t('Due Date')} :</span>
                                                <span className="text-muted-foreground text-red-600">{window.appSettings.formatDateTime(new Date(invoice.due_date), false)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Col 3: Status badge + QR */}
                                    <div className="flex flex-col items-start md:items-end">
                                        {showQr && (
                                            <div className="flex flex-col items-center mt-4">
                                                <div className="p-3 rounded-xl border bg-white shadow-sm inline-block">
                                                    <QRCodeGenerator
                                                        value={route('invoices.payment', invoice.payment_token)}
                                                        size={130}
                                                    />
                                                </div>
                                                <p className="text-xs text-muted-foreground mt-2">{t('Scan for invoice details')}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Divider + second row: title/description | notes/terms | empty */}
                                {(invoice.title || invoice.description || invoice.notes || invoice.terms) && (
                                    <div className="mt-4 pt-4 border-t space-y-3">
                                            {invoice.title && (
                                                <div>
                                                    <h3 className="text-sm font-medium mb-1">{t('Title')}</h3>
                                                    <div className="text-sm text-muted-foreground">{invoice.title}</div>
                                                </div>
                                            )}
                                            {invoice.description && (
                                                <div>
                                                    <h3 className="text-sm font-medium mb-1">{t('Description')}</h3>
                                                    <div className="text-sm text-muted-foreground">{invoice.description}</div>
                                                </div>
                                            )}
                                            {invoice.notes && (
                                                <div>
                                                    <h3 className="text-sm font-medium mb-1">{t('Notes')}</h3>
                                                    <div className="text-sm text-muted-foreground">{invoice.notes}</div>
                                                </div>
                                            )}
                                            {invoice.terms && (
                                                <div>
                                                    <h3 className="text-sm font-medium mb-1">{t('Terms & Conditions')}</h3>
                                                    <div className="text-sm text-muted-foreground">{invoice.terms}</div>
                                                </div>
                                            )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Invoice Items */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-xl font-bold tracking-tight">
                                    {t('Invoice Tasks')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr className="bg-[#F0F0F1] dark:bg-gray-900 border-t">
                                                <th className="px-4 py-3 text-start text-sm font-bold tracking-wide">{t('Task')}</th>
                                                <th className="px-4 py-3 text-right text-sm font-bold tracking-wide">{t('Amount')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {invoice.items?.map((item) => (
                                                <tr key={item.id}>
                                                    <td className="px-4 py-4">
                                                        {item.task?.title && (
                                                            <div className="font-bold text-sm">{item.task.title}</div>
                                                        )}
                                                        {item.task?.description && (
                                                            <div className="text-sm text-muted-foreground mt-1">{item.task.description}</div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 text-right font-bold text-base">
                                                        {formatCurrency(item.amount)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="mt-6 flex justify-end">
                                    <div className="w-full max-w-sm space-y-3 bg-muted/50 rounded-lg p-5 shadow-sm border border-muted/50">
                                        <div className="flex justify-between text-base">
                                            <span className=" font-semibold">{t('Subtotal')}</span>
                                            <span className="font-bold">{formatCurrency(invoice.subtotal)}</span>
                                        </div>
                                        {invoice.tax_rate && Array.isArray(invoice.tax_rate) && invoice.tax_rate.length > 0 && (
                                            invoice.tax_rate.map((tax: any, index: number) => {
                                                const taxAmount = (invoice.subtotal * tax.rate) / 100;
                                                return (
                                                    <div key={index} className="flex justify-between text-base">
                                                        <span className="text-muted-foreground font-semibold">{tax.name} ({tax.rate}%)</span>
                                                        <span className="font-bold">{formatCurrency(taxAmount)}</span>
                                                    </div>
                                                );
                                            })
                                        )}
                                        <Separator className="my-2" />
                                        <div className="flex justify-between">
                                            <span className="font-bold text-base">{t('Total Amount')}</span>
                                            <span className="font-bold text-base tracking-tight">{formatCurrency(invoice.total_amount)}</span>
                                        </div>
                                        {invoice.paid_amount > 0 && (
                                            <div className="flex justify-between text-base">
                                                <span className="text-muted-foreground font-semibold">{t('Paid Amount')}</span>
                                                <span className="font-bold text-green-600">{formatCurrency(invoice.paid_amount)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span className="font-bold text-base">{t('Balance Due')}</span>
                                            <span className={`font-bold text-base tracking-tight ${
                                                    invoice.balance_due <= 0 ? 'text-green-600' :
                                                    invoice.status === 'partial_paid' ? 'text-orange-500' :
                                                    'text-red-600'
                                                }`}
                                                >{formatCurrency(invoice.balance_due)}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Pending Payments Section - visible to owner/manager only */}
                        {pendingPayments && pendingPayments.length > 0 && permissions.includes('invoice_manage_payments') && (
                            // {pendingPayments && pendingPayments.length > 0 && ['owner', 'manager'].includes(userWorkspaceRole) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold tracking-tight flex items-center gap-2">
                                        {/* <Clock className="h-5 w-5 text-yellow-500" /> */}
                                        {t('Pending Payments')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="min-w-full text-center divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead>
                                                <tr className="bg-[#F0F0F1] dark:bg-gray-900 border-t">
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Method')}</th>
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Date')}</th>
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Payment ID')}</th>
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Amount')}</th>
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Receipt')}</th>
                                                    <th className="px-3 py-2 text-sm font-bold tracking-wide">{t('Actions')}</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {pendingPayments.map((payment) => (
                                                    <tr key={payment.id}>
                                                        <td className="px-3 py-2">
                                                            {(() => {
                                                                return (
                                                                    <Badge variant="outline" className={`capitalize bg-blue-50 text-blue-700 border-blue-200`}>
                                                                        {payment.payment_method.replace(/_/g, ' ')}
                                                                    </Badge>
                                                                );
                                                            })()}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-muted-foreground">
                                                            {window.appSettings.formatDateTime(new Date(payment.payment_date), false)}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {payment.transaction_id
                                                                ? <code className="text-xs font-mono">{payment.transaction_id}</code>
                                                                : <span className="text-sm text-muted-foreground">-</span>
                                                            }
                                                        </td>
                                                        <td className="px-3 py-2 font-bold text-sm">
                                                            {formatCurrency(payment.amount)}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {payment.receipt_url
                                                                ?
                                                                <a href={payment.receipt_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                                                    <TooltipProvider>
                                                                        <Tooltip>
                                                                            <TooltipTrigger asChild>
                                                                                <Button variant="ghost" size="icon" className="text-blue-600">
                                                                                    <ExternalLink className="h-4 w-4" />
                                                                                </Button>

                                                                            </TooltipTrigger>
                                                                            <TooltipContent>
                                                                                {t('View Receipt')}
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                    </TooltipProvider>
                                                                </a>
                                                                : <span className="text-sm text-muted-foreground">-</span>
                                                            }
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="space-x-2 gap-2">
                                                                <TooltipProvider>
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="text-green-600"
                                                                                onClick={() => handleApprovePayment(payment.id)}
                                                                            >
                                                                                <Check className="h-4 w-4 mr-1" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            {t('Approve')}
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                </TooltipProvider>

                                                                <TooltipProvider>
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="text-red-600"
                                                                                onClick={() => handleRejectPayment(payment.id)}
                                                                            >
                                                                                <X className="h-4 w-4 mr-1" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            {t('Reject')}
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                </TooltipProvider>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                    </div>



                    {/* Payment Modal */}
                    <InvoicePaymentModal
                        invoice={invoice}
                        open={showPaymentModal}
                        onClose={() => setShowPaymentModal(false)}
                        onSuccess={handlePaymentSuccess}
                    />

                    {/* Mark as Paid Confirmation Modal */}
                    <Dialog open={showMarkPaidModal} onOpenChange={setShowMarkPaidModal}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Mark Invoice as Paid')}</DialogTitle>
                            </DialogHeader>
                            <p>{t('Are you sure you want to mark invoice')} {invoice.invoice_number} {t('as paid')}?</p>
                            <div className="flex justify-end gap-2 mt-4">
                                <Button variant="outline" onClick={() => setShowMarkPaidModal(false)}>
                                    {t('Cancel')}
                                </Button>
                                <Button onClick={handleMarkPaidConfirm}>
                                    {t('Mark as Paid')}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </PageTemplate>
            </div>

            {/* Print Template */}
            <div className="hidden print:block">
                {(() => {
                    const templateProps = {
                        invoice,
                        color: invoiceColor,
                        showQr,
                        invoiceUrl: route('invoices.payment', invoice.payment_token),
                        footerTitle,
                        footerNotes,
                        remainingAmount: invoice.balance_due,
                        formatAmount,
                        t,
                        companyLogo
                    };

                    switch (templateName?.toLowerCase()) {
                        case 'new_york': return <NewYork {...templateProps} />;
                        case 'toronto': return <Toronto {...templateProps} />;
                        case 'rio': return <Rio {...templateProps} />;
                        case 'istanbul': return <Istanbul {...templateProps} />;
                        case 'mumbai': return <Mumbai {...templateProps} />;
                        case 'hong_kong': return <HongKong {...templateProps} />;
                        case 'tokyo': return <Tokyo {...templateProps} />;
                        case 'sydney': return <Sydney {...templateProps} />;
                        case 'paris': return <Paris {...templateProps} />;
                        case 'london':
                        default:
                            return <London {...templateProps} />;
                    }
                })()}
            </div>

            {/* PDF Generation Container (rendered offscreen so html2canvas can capture it) */}
            <div ref={pdfTemplateRef} style={{ position: 'absolute', left: '-9999px', top: '0', width: '900px', background: '#ffffff' }}>
                {(() => {
                    const templateProps = {
                        invoice,
                        color: invoiceColor,
                        showQr,
                        invoiceUrl: route('invoices.payment', invoice.payment_token),
                        footerTitle,
                        footerNotes,
                        remainingAmount: invoice.balance_due,
                        formatAmount,
                        t,
                        companyLogo
                    };

                    switch (templateName?.toLowerCase()) {
                        case 'new_york': return <NewYork {...templateProps} />;
                        case 'toronto': return <Toronto {...templateProps} />;
                        case 'rio': return <Rio {...templateProps} />;
                        case 'istanbul': return <Istanbul {...templateProps} />;
                        case 'mumbai': return <Mumbai {...templateProps} />;
                        case 'hong_kong': return <HongKong {...templateProps} />;
                        case 'tokyo': return <Tokyo {...templateProps} />;
                        case 'sydney': return <Sydney {...templateProps} />;
                        case 'paris': return <Paris {...templateProps} />;
                        case 'london':
                        default:
                            return <London {...templateProps} />;
                    }
                })()}
            </div>
        </>
    );
}
