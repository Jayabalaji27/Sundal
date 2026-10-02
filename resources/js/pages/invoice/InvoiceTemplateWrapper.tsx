import { Badge } from '@/components/ui/badge';
import { QRCodeGenerator } from '@/components/QRCodeGenerator';

interface InvoiceTemplateWrapperProps {
  invoice: any;
  color: string;
  showQr: boolean;
  invoiceUrl: string;
  footerTitle: string;
  footerNotes: string;
  remainingAmount: number;
  formatAmount: (amount: number) => string;
  t: (key: string) => string;
  templateName: string;
}

export function RioTemplate({
  invoice,
  color,
  showQr,
  invoiceUrl,
  footerTitle,
  footerNotes,
  remainingAmount,
  formatAmount,
  t,
}: Omit<InvoiceTemplateWrapperProps, 'templateName'>) {
  const template = { primary: color, secondary: color };

  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        <div className="p-8 space-y-8">
          <div className="pb-8 text-center" style={{ borderBottom: `3px solid ${template.primary}` }}>
            <h1 className="text-4xl font-bold mb-2" style={{ color: template.secondary }}>INVOICE</h1>
            <div className="text-sm space-y-1"><p>{invoice.invoice_number}</p></div>

            <div className="flex justify-center gap-12 mt-6">
              <div>
                <div className="mb-2">
                  <Badge style={{ backgroundColor: `${template.primary}20`, color: template.primary }}>
                    {invoice.status === 'paid' ? t('Paid') :
                      invoice.status === 'partial_paid' ? t('Partial Paid') : t('Sent')}
                  </Badge>
                </div>
                <div className="space-y-1 text-left">
                  <div className="p-1 rounded" style={{ backgroundColor: `${template.primary}08` }}>
                    <div className="text-xs text-gray-500">{t('Invoice Date')}</div>
                    <div className="font-semibold">{new Date(invoice.invoice_date).toLocaleDateString()}</div>
                  </div>
                  <div className="p-1 rounded" style={{ backgroundColor: `${template.primary}08` }}>
                    <div className="text-xs text-gray-500">{t('Due Date')}</div>
                    <div className="font-semibold">{new Date(invoice.due_date).toLocaleDateString()}</div>
                  </div>
                </div>
              </div>

              {showQr && (
                <div className="text-center">
                  <div className="p-4 rounded-lg border-2 border-dashed flex flex-col items-center" style={{ borderColor: `${template.primary}40`, backgroundColor: `${template.primary}05` }}>
                    <div className="text-xs text-gray-500 uppercase tracking-wide mb-3">{t('Scan to Share')}</div>
                    <QRCodeGenerator value={invoiceUrl} size={100} />
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-lg font-bold mb-3" style={{ color: template.primary }}>TO:</h3>
              <div className="text-sm space-y-1">
                <p>{invoice.client?.name}</p>
                <p>{invoice.client?.email}</p>
              </div>
            </div>
            {invoice.project && (
              <div>
                <h3 className="text-lg font-bold mb-3" style={{ color: template.primary }}>PROJECT:</h3>
                <div className="p-4 rounded" style={{ backgroundColor: `${template.primary}10` }}>
                  <div className="font-bold">{invoice.project.title}</div>
                </div>
              </div>
            )}
          </div>

          <div className="overflow-x-auto">
            <table className="w-full border-collapse rounded-lg overflow-hidden shadow-sm">
              <thead>
                <tr style={{ background: template.primary }}>
                  <th className="text-left py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Description')}</th>
                  <th className="text-center py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Qty')}</th>
                  <th className="text-right py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Unit Price')}</th>
                  <th className="text-right py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Total')}</th>
                </tr>
              </thead>
              <tbody className="bg-white">
                {invoice.items?.map((item: any, index: number) => (
                  <tr key={item.id} className={`border-b border-gray-200 ${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'}`}>
                    <td className="py-4 px-6 text-gray-900"><div className="font-medium">{item.description}</div></td>
                    <td className="text-center py-4 px-6 text-gray-700">1</td>
                    <td className="text-right py-4 px-6 text-gray-700">${parseFloat(item.rate).toFixed(2)}</td>
                    <td className="text-right py-4 px-6 font-semibold text-gray-900">${parseFloat(item.amount).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              {invoice.notes && (
                <div>
                  <h4 className="font-bold mb-1" style={{ color: template.primary }}>Notes:</h4>
                  <p className="text-sm text-gray-600">{invoice.notes}</p>
                </div>
              )}
              {invoice.terms && (
                <div>
                  <h4 className="font-bold mb-1" style={{ color: template.primary }}>Terms & Conditions:</h4>
                  <p className="text-sm text-gray-600">{invoice.terms}</p>
                </div>
              )}
            </div>
            <div>
              <div className="bg-white rounded-lg border shadow-sm overflow-hidden">
                <div className="p-4 space-y-2">
                  <div className="flex justify-between items-center py-1">
                    <span className="text-gray-600 font-medium text-sm">{t('Subtotal')}:</span>
                    <span className="text-gray-900 font-semibold text-sm">{formatAmount(invoice.subtotal || invoice.total_amount)}</span>
                  </div>
                  {invoice.tax_rate && Array.isArray(invoice.tax_rate) && invoice.tax_rate.length > 0 && (
                    invoice.tax_rate.map((tax: any, index: number) => {
                      const taxAmount = (invoice.subtotal * tax.rate) / 100;
                      return (
                        <div key={index} className="flex justify-between items-center py-1">
                          <span className="text-gray-600 font-medium text-sm">{tax.name} ({tax.rate}%):</span>
                          <span className="text-gray-900 font-semibold text-sm">{formatAmount(taxAmount)}</span>
                        </div>
                      );
                    })
                  )}
                  <div className="flex justify-between items-center py-1 border-t border-b border-gray-100">
                    <span className="text-gray-600 font-medium text-sm">{t('Total')}:</span>
                    <span className="text-gray-900 font-bold text-sm">{formatAmount(invoice.total_amount)}</span>
                  </div>
                  {(invoice.total_amount - remainingAmount) > 0 && (
                    <div className="flex justify-between items-center py-1">
                      <span className="font-medium text-sm" style={{ color: template.primary }}>{t('Paid')}:</span>
                      <span className="font-semibold text-sm" style={{ color: template.primary }}>{formatAmount(invoice.total_amount - remainingAmount)}</span>
                    </div>
                  )}
                </div>
                <div className="px-4 py-2" style={{ backgroundColor: `${template.primary}15` }}>
                  <div className="flex justify-between items-center">
                    <span className="font-bold text-sm" style={{ color: template.primary }}>{t('BALANCE DUE')}:</span>
                    <span className="font-bold text-lg" style={{ color: template.primary }}>{formatAmount(remainingAmount)}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="pt-8" style={{ borderTop: `3px solid ${template.primary}` }}>
            <p className="text-lg font-semibold mb-2" style={{ color: template.primary }}>{footerTitle || 'Thank you for your business!'}</p>
            {footerNotes && <p className="text-sm text-gray-600 mb-4">{footerNotes}</p>}
          </div>
        </div>
      </div>
    </div>
  );
}

export function DefaultTemplate({
  invoice,
  color,
  showQr,
  invoiceUrl,
  footerTitle,
  footerNotes,
  remainingAmount,
  formatAmount,
  t,
}: Omit<InvoiceTemplateWrapperProps, 'templateName'>) {
  const primaryColor = color;

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4">
        <div className="bg-white rounded-lg shadow-sm">
          <div className="p-8 space-y-8">
            <div className="pb-8" style={{ borderBottom: `3px solid ${primaryColor}` }}>
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-1">
                  <h1 className="text-4xl font-bold mb-3" style={{ color: primaryColor }}>INVOICE</h1>
                  <p className="font-semibold text-lg">{invoice.invoice_number}</p>
                </div>

                <div className="text-center lg:col-span-1">
                  <Badge className={`mb-4 ${invoice.status === 'paid' ? 'bg-green-100 text-green-800' :
                    invoice.status === 'partial_paid' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                    {invoice.status === 'paid' ? t('Paid') :
                      invoice.status === 'partial_paid' ? t('Partial Paid') : t('Unpaid')}
                  </Badge>
                  <div className="space-y-2 text-sm">
                    <div>
                      <span className="text-gray-500">{t('Invoice Date')}:</span>
                      <p className="font-semibold">{new Date(invoice.invoice_date).toLocaleDateString()}</p>
                    </div>
                    <div>
                      <span className="text-gray-500">{t('Due Date')}:</span>
                      <p className="font-semibold">{new Date(invoice.due_date).toLocaleDateString()}</p>
                    </div>
                  </div>
                </div>

                <div className="text-center lg:col-span-1">
                  {showQr && (
                    <div className="p-4 rounded-lg border-2 border-dashed flex flex-col items-center" style={{ borderColor: `${primaryColor}40`, backgroundColor: `${primaryColor}05` }}>
                      <div className="text-xs text-gray-500 uppercase tracking-wide mb-3">{t('Scan to Share')}</div>
                      <QRCodeGenerator value={invoiceUrl} size={100} />
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div>
                <h3 className="text-lg font-bold mb-3" style={{ color: primaryColor }}>{t('BILL TO')}:</h3>
                <div className="p-4 rounded" style={{ backgroundColor: `${primaryColor}10` }}>
                  <div className="font-bold text-lg">{invoice.client?.name}</div>
                  <div className="text-sm mt-2 space-y-1">
                    {invoice.client?.email && <p>{invoice.client.email}</p>}
                  </div>
                </div>
              </div>
              {invoice.project && (
                <div>
                  <h3 className="text-lg font-bold mb-3" style={{ color: primaryColor }}>{t('PROJECT')}:</h3>
                  <div className="p-4 rounded" style={{ backgroundColor: `${primaryColor}10` }}>
                    <div className="font-bold">{invoice.project.title}</div>
                  </div>
                </div>
              )}
            </div>

            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr style={{ background: primaryColor }}>
                    <th className="text-left py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Description')}</th>
                    <th className="text-center py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Qty')}</th>
                    <th className="text-right py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Unit Price')}</th>
                    <th className="text-right py-4 px-6 text-white font-bold text-sm uppercase tracking-wide">{t('Total')}</th>
                  </tr>
                </thead>
                <tbody className="bg-white">
                  {invoice.items?.map((item: any, index: number) => (
                    <tr key={item.id} className={`border-b border-gray-200 ${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'}`}>
                      <td className="py-4 px-6 text-gray-900">
                        <div className="font-medium">{item.description}</div>
                      </td>
                      <td className="text-center py-4 px-6 text-gray-700">1</td>
                      <td className="text-right py-4 px-6 text-gray-700">${parseFloat(item.rate).toFixed(2)}</td>
                      <td className="text-right py-4 px-6 font-semibold text-gray-900">${parseFloat(item.amount).toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div className="space-y-4">
                {invoice.notes && (
                  <>
                    <h4 className="font-bold mb-2" style={{ color: primaryColor }}>{t('Notes')}:</h4>
                    <div className="p-4 rounded" style={{ backgroundColor: `${primaryColor}10` }}>
                      <p className="text-sm">{invoice.notes}</p>
                    </div>
                  </>
                )}
                {invoice.terms && (
                  <>
                    <h4 className="font-bold mb-2" style={{ color: primaryColor }}>{t('Terms & Conditions')}:</h4>
                    <div className="p-4 rounded" style={{ backgroundColor: `${primaryColor}10` }}>
                      <p className="text-sm">{invoice.terms}</p>
                    </div>
                  </>
                )}
              </div>
              <div>
                <div className="bg-white rounded-lg border shadow-sm overflow-hidden">
                  <div className="p-6 space-y-4">
                    <div className="flex justify-between items-center py-2 border-b border-gray-100">
                      <span className="text-gray-600 font-medium">{t('Subtotal')}:</span>
                      <span className="text-gray-900 font-semibold">{formatAmount(invoice.subtotal || invoice.total_amount)}</span>
                    </div>
                    {invoice.tax_rate && Array.isArray(invoice.tax_rate) && invoice.tax_rate.length > 0 && (
                      invoice.tax_rate.map((tax: any, index: number) => {
                        const taxAmount = (invoice.subtotal * tax.rate) / 100;
                        return (
                          <div key={index} className="flex justify-between items-center py-2 border-b border-gray-100">
                            <span className="text-gray-600 font-medium">{tax.name} ({tax.rate}%):</span>
                            <span className="text-gray-900 font-semibold">{formatAmount(taxAmount)}</span>
                          </div>
                        );
                      })
                    )}
                    <div className="flex justify-between items-center py-2 border-b border-gray-100">
                      <span className="text-gray-600 font-medium">{t('Total')}:</span>
                      <span className="text-gray-900 font-bold text-lg">{formatAmount(invoice.total_amount)}</span>
                    </div>
                    {(invoice.total_amount - remainingAmount) > 0 && (
                      <div className="flex justify-between items-center py-2 border-b border-gray-100">
                        <span className="text-gray-600 font-medium">{t('Paid')}:</span>
                        <span className="font-semibold text-lg" style={{ color: primaryColor }}>{formatAmount(invoice.total_amount - remainingAmount)}</span>
                      </div>
                    )}
                  </div>
                  <div className="px-6 py-4" style={{ backgroundColor: `${primaryColor}15` }}>
                    <div className="flex justify-between items-center">
                      <span className="font-bold text-lg" style={{ color: primaryColor }}>{t('BALANCE DUE')}:</span>
                      <span className="font-bold text-2xl" style={{ color: primaryColor }}>{formatAmount(remainingAmount)}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-8" style={{ borderTop: `3px solid ${primaryColor}` }}>
              <p className="text-lg font-semibold mb-4" style={{ color: primaryColor }}>{footerTitle}</p>
              {footerNotes && <p className="text-sm text-gray-600 mb-4">{footerNotes}</p>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
