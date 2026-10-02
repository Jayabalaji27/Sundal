import { QRCodeGenerator } from '@/components/QRCodeGenerator';
import { useBrand } from '@/contexts/BrandContext';

export function Istanbul({ invoice, color, showQr, invoiceUrl, footerTitle, footerNotes, remainingAmount, formatAmount, t, invoiceLogo, companyLogo }: any) {
  const { logoDark } = useBrand();
  const colorWithoutHash = color.replace('#', '').toLowerCase();
  const fontColor = ['ffffff', 'fbdd03', 'c1d82f', '46de98', '40c7d0', 'fac168'].includes(colorWithoutHash) ? '#000000' : '#ffffff';
  const borderColor = colorWithoutHash === 'ffffff' ? '#000000' : color;
  
  const isPreview = !invoice;
  const invoiceData = isPreview ? {
    invoice_number: '<Invoice Number>',
    invoice_date: '2026-12-01',
    due_date: '2026-12-01',
    client: { name: '<Client Name>', email: '<Email>' },
    creator: { name: '<Creator Name>', email: '<Creator Email>' },
    project: { title: 'UI Design Project' },
    items: [1,2,3].map(i => ({ id: i, description: `Task ${i}`, rate: 100, amount: 100 })),
    notes: '<Notes>',
    terms: '<Terms & Conditions>',
    subtotal: 300,
    total_amount: 351,
    tax_rate: [{ name: '<Tax Name>', rate: 17 }]
  } : invoice;
  
  const displayAmount = (amount) => isPreview ? `$${amount.toFixed(2)}` : formatAmount(amount);
  const translate = (key) => isPreview ? key : t(key);

  return (
    <div style={{ fontFamily: 'inherit', margin: 0, padding: 0, boxSizing: 'border-box' }}>
      <div style={{ width: '100%', margin: '0 auto', background: '#ffffff', boxShadow: '0 0 10px #ddd' }}>
        <div style={{ position: 'relative', padding: '30px 30px 0 30px' }}>
          {(invoiceLogo || companyLogo || logoDark) && (
            <img
              src={invoiceLogo || companyLogo || logoDark}
              alt="Logo"
              style={{
                position: 'absolute',
                top: '0',
                right: '0',
                maxWidth: '150px',
                maxHeight: '150px',
                objectFit: 'contain'
              }}
            />
          )}
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <tbody>
              <tr>
                <td style={{ verticalAlign: 'top', paddingRight: '160px' }}>
                  <h3 style={{ display: 'block', textTransform: 'uppercase', fontSize: '30px', fontWeight: 'bold', padding: '15px', background: color, color: fontColor, margin: 0 }}>{translate('INVOICE')}</h3>
                  {showQr && (
                    <div style={{ marginTop: '15px', marginBottom: '15px' }}>
                      <QRCodeGenerator value={invoiceUrl || 'https://example.com/invoice'} size={114} />
                    </div>
                  )}
                  <div>{translate('Number')}: {invoiceData.invoice_number}</div>
                  <div>{translate('Invoice Date')}: {window.appSettings.formatDateTime(new Date(invoiceData.invoice_date),false)}</div>
                  <div>{translate('Due Date')}: {window.appSettings.formatDateTime(new Date(invoiceData.due_date),false)}</div>
                </td>
                <td style={{ verticalAlign: 'top', textAlign: 'right', paddingTop: '60px' }}>
                  <strong>{translate('From')}:</strong>
                  <p style={{ margin: 0, lineHeight: '1.5' }}>
                    {invoiceData.creator?.name}<br />
                    {invoiceData.creator?.email}
                  </p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style={{ padding: '30px 25px 30px 25px' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <tbody>
              <tr>
                <td style={{ verticalAlign: 'top' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Bill To')}:</strong>
                  <p style={{ margin: '0', lineHeight: '1.5' }}>
                    {invoiceData.client?.name}<br />
                    {invoiceData.client?.email}
                  </p>
                </td>
                <td style={{ verticalAlign: 'top', textAlign: 'right' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Ship To')}:</strong>
                  <p style={{ margin: '0', lineHeight: '1.5' }}>
                    {invoiceData.creator?.name}<br />
                    {invoiceData.creator?.email}
                  </p>
                </td>
              </tr>
            </tbody>
          </table>

          <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '30px' }}>
            <tbody>
              <tr style={{ background: color, color: fontColor }}>
                <th style={{ padding: '0.75rem', textAlign: 'left', fontSize: '13px', fontWeight: '600' }}>{translate('Task')}</th>
                <th style={{ padding: '0.75rem', textAlign: 'left', fontSize: '13px', fontWeight: '600' }}>{translate('Amount')}</th>
                <th style={{ padding: '0.75rem', textAlign: 'left', fontSize: '13px', fontWeight: '600' }}>{translate('Total')}</th>
              </tr>
              {invoiceData.items?.map((item: any) => (
                <tr key={item.id}>
                  <td style={{ padding: '0.75rem', textAlign: 'left', borderTop: `1px solid ${borderColor}` }}>{item.description}</td>
                  <td style={{ padding: '0.75rem', textAlign: 'left', borderTop: `1px solid ${borderColor}` }}>{displayAmount(item.rate)}</td>
                  <td style={{ padding: '0.75rem', textAlign: 'left', borderTop: `1px solid ${borderColor}` }}>{displayAmount(item.amount)}</td>
                </tr>
              ))}
              <tr>
                <td colSpan={3} style={{ border: 'none', padding: '0' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <tbody>
                      <tr>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right' }}>{translate('Subtotal')}:</td>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{displayAmount(invoiceData.subtotal || invoiceData.total_amount)}</td>
                      </tr>
                      {invoiceData.tax_rate && Array.isArray(invoiceData.tax_rate) && invoiceData.tax_rate.length > 0 && (
                        invoiceData.tax_rate.map((tax: any, index: number) => {
                          const taxAmount = (invoiceData.subtotal * tax.rate) / 100;
                          return (
                            <tr key={index}>
                              <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right' }}>{tax.name} ({tax.rate}%):</td>
                              <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{displayAmount(taxAmount)}</td>
                            </tr>
                          );
                        })
                      )}
                      {!isPreview && (invoiceData.total_amount - remainingAmount) > 0 && (
                        <tr>
                          <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right' }}>{translate('Paid')}:</td>
                          <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{displayAmount(invoiceData.total_amount - remainingAmount)}</td>
                        </tr>
                      )}
                      <tr>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right' }}>{translate('Balance Due')}:</td>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{displayAmount(isPreview ? invoiceData.total_amount : remainingAmount)}</strong></td>
                      </tr>
                      <tr>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right' }}><strong>{translate('Total')}:</strong></td>
                        <td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{displayAmount(invoiceData.total_amount)}</strong></td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>

          <table style={{ width: '100%', marginTop: '30px' }}>
            <tbody>
              <tr>
                <td style={{ verticalAlign: 'top' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Notes')}:</strong>
                  <p style={{ margin: 0, lineHeight: '1.5' }}>{invoiceData.notes}</p>
                </td>
              </tr>
              <tr>
                <td style={{ verticalAlign: 'top', paddingTop: '20px' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Terms & Conditions')}:</strong>
                  <p style={{ margin: 0, lineHeight: '1.5' }}>{invoiceData.terms}</p>
                </td>
              </tr>
            </tbody>
          </table>

          {footerTitle && (
            <div style={{ marginTop: '30px', padding: '15px 20px', borderTop: `1px solid ${borderColor}` }}>
              <strong>{footerTitle}</strong>
              {footerNotes && <p style={{ margin: '5px 0 0 0' }}>{footerNotes}</p>}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
