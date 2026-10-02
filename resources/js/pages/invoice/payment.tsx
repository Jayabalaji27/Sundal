import { useState, useEffect } from 'react';
import { usePage, Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FileText, Calendar, User, Building2, Clock, Shield, Banknote, CreditCard, IndianRupee, Wallet, Coins } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { InvoicePaymentForm } from '@/components/invoice-payment-form';

export default function InvoicePayment() {
    const { invoice, enabledGateways, remainingAmount, company, favicon, appName } = usePage().props as any;
    
    // Debug: Log invoice data to check project information
    console.log('Invoice data:', invoice);
    console.log('Project data:', invoice?.project);
    
    const [selectedGateway, setSelectedGateway] = useState<string | null>(null);
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [showGatewayModal, setShowGatewayModal] = useState(false);
    const [showCopiedMessage, setShowCopiedMessage] = useState(false);
    const [paymentAmount, setPaymentAmount] = useState(remainingAmount || invoice.total_amount || 0);

    const getPaymentMethodIcon = (gatewayId: string) => {
        const iconMap = {
            bank: <Banknote className="h-5 w-5" />,
            stripe: <CreditCard className="h-5 w-5" />,
            paypal: <CreditCard className="h-5 w-5" />,
            razorpay: <IndianRupee className="h-5 w-5" />,
            paystack: <CreditCard className="h-5 w-5" />,
            flutterwave: <CreditCard className="h-5 w-5" />,
            paytabs: <CreditCard className="h-5 w-5" />,
            skrill: <Wallet className="h-5 w-5" />,
            coingate: <Coins className="h-5 w-5" />,
            payfast: <CreditCard className="h-5 w-5" />,
            tap: <CreditCard className="h-5 w-5" />,
            xendit: <CreditCard className="h-5 w-5" />,
            paytr: <CreditCard className="h-5 w-5" />,
            mollie: <CreditCard className="h-5 w-5" />,
            toyyibpay: <CreditCard className="h-5 w-5" />,
            cashfree: <IndianRupee className="h-5 w-5" />,
            khalti: <CreditCard className="h-5 w-5" />,
            iyzipay: <CreditCard className="h-5 w-5" />,
            benefit: <CreditCard className="h-5 w-5" />,
            ozow: <CreditCard className="h-5 w-5" />,
            easebuzz: <IndianRupee className="h-5 w-5" />,
            authorizenet: <CreditCard className="h-5 w-5" />,
            fedapay: <CreditCard className="h-5 w-5" />,
            payhere: <CreditCard className="h-5 w-5" />,
            cinetpay: <CreditCard className="h-5 w-5" />,
            paiement: <CreditCard className="h-5 w-5" />,
            yookassa: <CreditCard className="h-5 w-5" />,
            aamarpay: <CreditCard className="h-5 w-5" />,
            midtrans: <CreditCard className="h-5 w-5" />,
            paymentwall: <CreditCard className="h-5 w-5" />,
            sspay: <CreditCard className="h-5 w-5" />
        };
        return iconMap[gatewayId] || <CreditCard className="h-5 w-5" />;
    };

    const gatewaysWithIcons = enabledGateways?.map(gateway => ({
        ...gateway,
        icon: getPaymentMethodIcon(gateway.id)
    })) || [];

    const formatAmount = (amount) => {
        const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount);
        return `$${numericAmount.toFixed(2)}`;
    };

    const isOverdue = new Date(invoice.due_date) < new Date();

    const handleGatewaySelect = (gatewayId: string) => {
        setSelectedGateway(gatewayId);
        setShowPaymentModal(true);
    };

    const closeModal = () => {
        setShowPaymentModal(false);
        setSelectedGateway(null);
    };

    const handlePaymentSuccess = () => {
        toast.success('Payment successful');
        closeModal();
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    };

    const renderPaymentForm = () => {
        if (!selectedGateway || !showPaymentModal) return null;

        return (
            <InvoicePaymentForm
                invoiceToken={invoice.payment_token}
                amount={paymentAmount}
                paymentMethod={selectedGateway}
                onSuccess={handlePaymentSuccess}
                onCancel={closeModal}
            />
        );
    };

    return (
        <>
            <Head title={`Invoice - ${company?.name || 'Taskly SaaS'}`}>
                {favicon && (
                    <link rel="icon" type="image/x-icon" href={favicon} />
                )}
            </Head>
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-100">
                {/* Header */}
                <div className="bg-white/80 backdrop-blur-sm border-b border-gray-200/50 sticky top-0 z-10">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="bg-gradient-to-br from-blue-600 to-blue-700 p-3 rounded-xl shadow-lg">
                                    <FileText className="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Invoice #{invoice.invoice_number}</h1>
                                    <p className="text-gray-600 text-sm sm:text-base flex items-center mt-1">
                                        <Shield className="h-4 w-4 mr-1" />
                                        Secure Payment Portal
                                    </p>
                                </div>
                            </div>
                            <Badge variant={isOverdue ? 'destructive' : 'secondary'} className="text-xs sm:text-sm px-3 py-1.5 font-medium">
                                {isOverdue ? 'Overdue' : 'Due'} {new Date(invoice.due_date).toLocaleDateString()}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Header with Title and Buttons */}
                    <div className="flex justify-between items-start mb-8">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Invoice Details</h1>
                            <p className="text-gray-600 mt-1">View and manage your invoice</p>
                        </div>
                        <div className="flex gap-3">
                            <Button
                                variant="outline"
                                className="h-10 px-4 text-sm font-medium relative"
                                onClick={async () => {
                                    try {
                                        await navigator.clipboard.writeText(window.location.href);
                                        setShowCopiedMessage(true);
                                        setTimeout(() => setShowCopiedMessage(false), 2000);
                                        toast.success('Link copied to clipboard!');
                                    } catch (error) {
                                        toast.error('Failed to copy link. Please try again.');
                                    }
                                }}
                            >
                                {showCopiedMessage ? (
                                    <span className="flex items-center text-green-600">
                                        ✓ Copied!
                                    </span>
                                ) : (
                                    "📋 Copy Link"
                                )}
                            </Button>

                            {(invoice.status === 'partial_paid' || (invoice.status !== 'paid' && remainingAmount > 0)) && (
                                <Button
                                    className="h-10 px-4 text-sm font-medium bg-green-600 hover:bg-green-700"
                                    onClick={() => setShowGatewayModal(true)}
                                >
                                    💳 Pay Invoice
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Invoice Header Card */}
                    <Card className="mb-8 border-0 shadow-sm">
                        <CardContent className="p-6">
                            <div className="flex justify-between items-start">
                                <div>
                                    <h2 className="text-xl font-bold text-gray-900">{invoice.invoice_number} {invoice.client?.name}</h2>
                                    <p className="text-gray-600 mt-1">Invoice for professional services and software licenses.</p>
                                </div>
                                <div className="text-right">
                                    <Badge
                                        variant={invoice.status === 'paid' ? 'default' : 'outline'}
                                        className={`mb-2 ${
                                            invoice.status === 'paid' ? 'bg-green-100 text-green-800' :
                                            invoice.status === 'partial_paid' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-red-100 text-red-800'
                                        }`}
                                    >
                                        {invoice.status === 'paid' ? 'Paid' : 
                                         invoice.status === 'partial_paid' ? 'Partial Paid' : 'Unpaid'}
                                    </Badge>
                                    <p className="text-sm text-gray-600">{invoice.invoice_number}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <Card className="border-l-4 border-l-green-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">TOTAL AMOUNT</p>
                                        <p className="text-2xl font-bold text-green-600 mt-1">{formatAmount(invoice.total_amount)}</p>
                                    </div>
                                    <div className="bg-green-100 p-3 rounded-full">
                                        <span className="text-green-600 text-xl">💰</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-l-4 border-l-blue-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">PAID AMOUNT</p>
                                        <p className="text-2xl font-bold text-blue-600 mt-1">{formatAmount((invoice.total_amount - remainingAmount) || 0)}</p>
                                    </div>
                                    <div className="bg-blue-100 p-3 rounded-full">
                                        <span className="text-blue-600 text-xl">💳</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-l-4 border-l-red-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">DUE AMOUNT</p>
                                        <p className="text-2xl font-bold text-red-600 mt-1">{formatAmount(remainingAmount)}</p>
                                    </div>
                                    <div className="bg-red-100 p-3 rounded-full">
                                        <span className="text-red-600 text-xl">⏰</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <Card className="border-l-4 border-l-purple-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">INVOICE ITEMS</p>
                                        <p className="text-2xl font-bold text-purple-600 mt-1">{invoice.items?.length || 1}</p>
                                    </div>
                                    <div className="bg-purple-100 p-3 rounded-full">
                                        <span className="text-purple-600 text-xl">📦</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-l-4 border-l-orange-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">INVOICE DATE</p>
                                        <p className="text-lg font-bold text-orange-600 mt-1">{new Date(invoice.invoice_date).toLocaleDateString()}</p>
                                    </div>
                                    <div className="bg-orange-100 p-3 rounded-full">
                                        <span className="text-orange-600 text-xl">📅</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-l-4 border-l-yellow-500 shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 uppercase tracking-wide">DUE DATE</p>
                                        <p className="text-lg font-bold text-yellow-600 mt-1">{new Date(invoice.due_date).toLocaleDateString()}</p>
                                    </div>
                                    <div className="bg-yellow-100 p-3 rounded-full">
                                        <span className="text-yellow-600 text-xl">📋</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Invoice Details */}
                    <div className="space-y-6">
                        {/* Client & Invoice Info */}
                        <Card className="shadow-xl border-0 overflow-hidden">
                            <CardHeader className="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white">
                                <CardTitle className="flex items-center space-x-2 text-lg">
                                    <Building2 className="h-5 w-5" />
                                    <span>Invoice Information</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div className="space-y-6">
                                        <div className="flex items-start space-x-4">
                                            <div className="bg-blue-100 p-2 rounded-lg">
                                                <User className="h-5 w-5 text-blue-600" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-500 uppercase tracking-wide">Bill To</p>
                                                <p className="text-xl font-bold text-gray-900 mt-1">{invoice.client?.name}</p>
                                                {invoice.client?.email && (
                                                    <p className="text-sm text-gray-600 mt-1">{invoice.client.email}</p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-start space-x-4">
                                            <div className="bg-green-100 p-2 rounded-lg">
                                                <FileText className="h-5 w-5 text-green-600" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-500 uppercase tracking-wide">{invoice.title ? 'Invoice Title' : 'Project'}</p>
                                                <p className="text-lg font-semibold text-gray-900 mt-1">{invoice.title || invoice.project?.name || invoice.project_name || 'No Project'}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-6">
                                        <div className="flex items-start space-x-4">
                                            <div className="bg-purple-100 p-2 rounded-lg">
                                                <Calendar className="h-5 w-5 text-purple-600" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-500 uppercase tracking-wide">Invoice Date</p>
                                                <p className="text-lg font-semibold text-gray-900 mt-1">{new Date(invoice.invoice_date).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start space-x-4">
                                            <div className={`p-2 rounded-lg ${isOverdue ? 'bg-red-100' : 'bg-orange-100'}`}>
                                                <Clock className={`h-5 w-5 ${isOverdue ? 'text-red-600' : 'text-orange-600'}`} />
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-500 uppercase tracking-wide">Due Date</p>
                                                <p className={`text-lg font-semibold mt-1 ${isOverdue ? 'text-red-600' : 'text-gray-900'}`}>
                                                    {new Date(invoice.due_date).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Products */}
                        {invoice.items && invoice.items.length > 0 && (
                            <Card className="shadow-sm border-0 overflow-hidden">
                                <CardHeader className="bg-gray-50 border-b">
                                    <CardTitle className="flex items-center space-x-2 text-lg text-gray-900">
                                        <span>📦</span>
                                        <span>Invoice Items</span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead className="bg-gray-100">
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-sm font-bold text-gray-700">Invoice Items</th>
                                                    <th className="px-6 py-4 text-right text-sm font-bold text-gray-700">Rate</th>
                                                    <th className="px-6 py-4 text-right text-sm font-bold text-gray-700">Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200">
                                                {invoice.items.map((item: any, index: number) => (
                                                    <tr key={index} className="hover:bg-gray-50 transition-colors">
                                                        <td className="px-6 py-4 text-sm font-medium text-gray-900">{item.description}</td>
                                                        <td className="px-6 py-4 text-sm text-gray-700 text-right font-medium">{formatAmount(item.rate)}</td>
                                                        <td className="px-6 py-4 text-sm font-bold text-green-600 text-right">{formatAmount(item.amount)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-6 border-t">
                                        <div className="space-y-3">
                                            <div className="flex justify-between text-sm">
                                                <span className="text-gray-600 font-medium">Subtotal</span>
                                                <span className="font-semibold text-gray-900">{formatAmount(invoice.subtotal || invoice.total_amount)}</span>
                                            </div>
                                            {invoice.discount_amount > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600 font-medium">Discount</span>
                                                    <span className="font-semibold text-red-600">-{formatAmount(invoice.discount_amount)}</span>
                                                </div>
                                            )}
                                            {invoice.tax_amount > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600 font-medium">Tax</span>
                                                    <span className="font-semibold text-gray-900">{formatAmount(invoice.tax_amount)}</span>
                                                </div>
                                            )}
                                            <Separator className="my-3" />
                                            <div className="flex justify-between text-xl font-bold">
                                                <span className="text-gray-900">Total</span>
                                                <span className="text-blue-600">{formatAmount(invoice.total_amount)}</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Additional Information */}
                        <Card className="shadow-sm border-0">
                            <CardHeader className="bg-gray-50 border-b">
                                <CardTitle className="text-lg text-gray-900">Additional Information</CardTitle>
                            </CardHeader>
                            <CardContent className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <h4 className="text-sm font-semibold text-gray-700 mb-2">NOTES</h4>
                                        <p className="text-sm text-gray-600">
                                            {invoice.notes || "Thank you for your business. Please remit payment by due date."}
                                        </p>
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-gray-700 mb-2">TERMS</h4>
                                        <p className="text-sm text-gray-600">
                                            {invoice.terms || "Net 30 days. Late payment fee of 1.5% per month applies."}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Payment Gateway Selection Modal */}
                <Dialog open={showGatewayModal} onOpenChange={setShowGatewayModal}>
                    <DialogContent className="max-w-md max-h-[80vh]">
                        <DialogHeader>
                            <DialogTitle className="text-center">Pay Invoice #{invoice.invoice_number}</DialogTitle>
                        </DialogHeader>
                        
                        <div className="space-y-4">
                            <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-blue-700">Invoice #{invoice.invoice_number}</span>
                                    <span className="font-bold text-blue-900">{formatAmount(invoice.total_amount)}</span>
                                </div>
                                <div className="text-xs text-blue-600 mt-1">{invoice.client?.name}</div>
                                <div className="text-xs text-blue-600 mt-1">Remaining: {formatAmount(remainingAmount)}</div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 mb-2 block">Payment Amount</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={remainingAmount}
                                    value={paymentAmount}
                                    onChange={(e) => setPaymentAmount(Number(e.target.value))}
                                    placeholder="Enter amount to pay"
                                    className="w-full"
                                />
                                <div className="flex gap-2 mt-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setPaymentAmount(remainingAmount / 2)}
                                    >
                                        50%
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setPaymentAmount(remainingAmount)}
                                    >
                                        Full Amount
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 mb-3 block">Select Payment Method</label>
                                <div className="space-y-3 max-h-64 overflow-y-auto">
                                    {(!gatewaysWithIcons || gatewaysWithIcons.length === 0) && (
                                        <div className="text-sm text-gray-500 border border-dashed border-gray-300 rounded-lg p-4 text-center">
                                            No payment methods are configured for this invoice yet. Please contact the sender for other payment arrangements.
                                        </div>
                                    )}
                                    {gatewaysWithIcons.map((gateway) => (
                                        <div
                                            key={gateway.id}
                                            className={`flex items-center p-4 border rounded-lg cursor-pointer transition-all ${selectedGateway === gateway.id
                                                    ? 'border-blue-500 bg-blue-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                                }`}
                                            onClick={() => setSelectedGateway(gateway.id)}
                                        >
                                            <div className="text-primary mr-3">
                                                {gateway.icon}
                                            </div>
                                            <span className="text-sm font-medium text-gray-900">{gateway.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex gap-3 pt-4">
                                <Button
                                    variant="outline"
                                    className="flex-1 h-12 bg-gray-800 text-white border-gray-800 hover:bg-gray-700"
                                    onClick={() => setShowGatewayModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    className="flex-1 h-12 bg-blue-600 hover:bg-blue-700"
                                    onClick={() => {
                                        if (!selectedGateway) {
                                            toast.error('Please select a payment method first.');
                                            return;
                                        }
                                        if (!paymentAmount || paymentAmount <= 0) {
                                            toast.error('Please enter a valid payment amount.');
                                            return;
                                        }
                                        if (paymentAmount > remainingAmount) {
                                            toast.error('Payment amount cannot exceed remaining balance.');
                                            return;
                                        }
                                        setShowGatewayModal(false);
                                        setShowPaymentModal(true);
                                    }}
                                    disabled={!selectedGateway || !paymentAmount || paymentAmount <= 0}
                                >
                                    Pay {formatAmount(paymentAmount)}
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                {/* Payment Form Modal */}
                <Dialog open={showPaymentModal} onOpenChange={setShowPaymentModal}>
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle className="text-center">Complete Payment</DialogTitle>
                        </DialogHeader>
                        {renderPaymentForm()}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}