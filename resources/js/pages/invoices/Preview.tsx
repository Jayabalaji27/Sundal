import React, { useEffect, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Printer, Download, X } from 'lucide-react';
import { NewYork, Toronto, Rio, London, Istanbul, Mumbai, HongKong, Tokyo, Sydney, Paris } from '../settings/components/invoice-templates';
import { useBrand } from '@/contexts/BrandContext';
import { usePdfDownload } from '@/hooks/usePdfDownload';
import { formatCurrency } from '@/utils/currency';

interface InvoiceItem {
    id: number;
    type: string;
    description: string;
    rate: number;
    amount: number;
    task?: { id: number; title: string };
    expense?: { id: number; title: string };
}

interface Invoice {
    id: number;
    invoice_number: string;
    project: { id: number; title: string };
    client?: { id: number; name: string; avatar?: string };
    creator: { id: number; name: string };
    title: string;
    description?: string;
    invoice_date: string;
    due_date: string;
    subtotal: number;
    tax_rate: Array<{ id: number; name: string; rate: number }>;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    paid_amount: number;
    status: string;
    balance_due: number;
    notes?: string;
    terms?: string;
    payment_token: string;
    items: InvoiceItem[];
}

export default function InvoicePreview() {
    const { t } = useTranslation();
    const { invoice, invoiceSettings } = usePage().props as { invoice: Invoice; invoiceSettings?: any };
    const { logoDark } = useBrand();
    const printAreaRef = useRef<HTMLDivElement>(null);
    const { downloadPDF, isGeneratingPDF } = usePdfDownload();

    const showQr = invoiceSettings?.invoice_qr_display === 'true' || invoiceSettings?.invoice_qr_display === true;
    const footerTitle = invoiceSettings?.invoice_footer_title || '';
    const footerNotes = invoiceSettings?.invoice_footer_notes || '';
    const templateName = invoiceSettings?.invoice_template || 'london';
    const invoiceColor = invoiceSettings?.invoice_color || '#3b82f6';
    const companyLogo = (invoiceSettings?.invoice_logo && invoiceSettings.invoice_logo.trim() !== '') ? invoiceSettings.invoice_logo : logoDark;

    const formatAmount = (amount: number) => {
        return formatCurrency(amount);
    };

    // Auto download on page load
    useEffect(() => {
        const timer = setTimeout(() => {
            handleDownload();
        }, 1500); // Small delay to ensure everything is rendered
        return () => clearTimeout(timer);
    }, []);

    const handleDownload = async () => {
        if (printAreaRef.current) {
            await downloadPDF(printAreaRef.current, `Invoice-${invoice.invoice_number}.pdf`);
            window.close();
        }
    };

    const handlePrint = () => {
        window.print();
    };

    const handleClose = () => {
        window.close();
    };

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

    const renderActiveTemplate = () => {
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
    };

    return (
        <>
            <Head>
                <title>{`${t('Invoice Preview')} - #${invoice.invoice_number}`}</title>
                <style>{`
                    body {
                        background-color: #f3f4f6;
                    }
                    @media print {
                        body {
                            background-color: #ffffff;
                            print-color-adjust: exact;
                            -webkit-print-color-adjust: exact;
                        }
                        .no-print {
                            display: none !important;
                        }
                        .print-area {
                            box-shadow: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            width: 100% !important;
                        }
                    }
                `}</style>
            </Head>

            {/* Preview Sheet Container */}
            <div className="min-h-screen py-10 px-4 flex justify-center bg-gray-100">
                <div 
                    ref={printAreaRef}
                    className="print-area w-full max-w-[900px] bg-white p-10 shadow-lg rounded-xl border border-gray-200 transition-all duration-200"
                >
                    {renderActiveTemplate()}
                </div>
            </div>
        </>
    );
}
