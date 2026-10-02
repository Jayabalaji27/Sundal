import { usePage, Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, FileText, Calendar, User } from 'lucide-react';

export default function PaymentSuccess() {
    const { invoice, message, company, favicon, appName } = usePage().props as any;

    const formatAmount = (amount: number) => {
        const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount);
        return `$${numericAmount.toFixed(2)}`;
    };

    return (
        <>
            <Head title={`Payment Success - ${company?.name || 'Taskly SaaS'}`}>
                {favicon && (
                    <link rel="icon" type="image/x-icon" href={favicon} />
                )}
            </Head>
            <div className="min-h-screen bg-gradient-to-br from-green-50 via-blue-50 to-indigo-100">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    {/* Success Header */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
                        <p className="text-gray-600">Your payment has been processed successfully.</p>
                        {message && (
                            <p className="text-green-600 mt-2">{message}</p>
                        )}
                    </div>

                    {/* Invoice Summary */}
                    <Card className="mb-8">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div className="flex items-center space-x-3">
                                    <div className="bg-blue-100 p-2 rounded-lg">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">Invoice #{invoice.invoice_number}</h2>
                                        <p className="text-gray-600">Payment completed</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-2xl font-bold text-green-600">{formatAmount(invoice.total_amount)}</p>
                                    <p className="text-sm text-gray-600">Total Amount</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="flex items-start space-x-3">
                                    <div className="bg-purple-100 p-2 rounded-lg">
                                        <User className="h-4 w-4 text-purple-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-500 uppercase tracking-wide">Bill To</p>
                                        <p className="font-semibold text-gray-900">{invoice.client?.name}</p>
                                        {invoice.client?.email && (
                                            <p className="text-sm text-gray-600">{invoice.client.email}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-start space-x-3">
                                    <div className="bg-orange-100 p-2 rounded-lg">
                                        <Calendar className="h-4 w-4 text-orange-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-500 uppercase tracking-wide">Invoice Date</p>
                                        <p className="font-semibold text-gray-900">{new Date(invoice.invoice_date).toLocaleDateString()}</p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-3">
                                    <div className="bg-green-100 p-2 rounded-lg">
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-500 uppercase tracking-wide">Status</p>
                                        <p className="font-semibold text-green-600">Paid</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Payment Details */}
                    {invoice.payments && invoice.payments.length > 0 && (
                        <Card className="mb-8">
                            <CardContent className="p-6">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">Payment Details</h3>
                                <div className="space-y-3">
                                    {invoice.payments.map((payment: any, index: number) => (
                                        <div key={index} className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <p className="font-medium text-gray-900">{formatAmount(payment.amount)}</p>
                                                <p className="text-sm text-gray-600">
                                                    {payment.payment_method} • {new Date(payment.payment_date).toLocaleDateString()}
                                                </p>
                                            </div>
                                            {payment.transaction_id && (
                                                <div className="text-right">
                                                    <p className="text-sm text-gray-600">Transaction ID</p>
                                                    <p className="text-sm font-mono text-gray-900">{payment.transaction_id}</p>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Next Steps */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">What's Next?</h3>
                            <div className="space-y-3 text-sm text-gray-600">
                                <div className="flex items-start space-x-2">
                                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                                    <p>You will receive a payment confirmation email shortly</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                                    <p>Your invoice has been marked as paid in our system</p>
                                </div>
                                <div className="flex items-start space-x-2">
                                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                                    <p>If you have any questions, please contact our support team</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="text-center mt-8">
                        <Button
                            onClick={() => window.print()}
                            variant="outline"
                            className="mr-4"
                        >
                            Print Receipt
                        </Button>
                        <Button
                            onClick={() => window.close()}
                        >
                            Close Window
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}