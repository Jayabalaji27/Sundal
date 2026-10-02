import { QRCodeGenerator } from '@/components/QRCodeGenerator';
import { useBrand } from '@/contexts/BrandContext';

export function Mumbai({ invoice, color, showQr, invoiceUrl, footerTitle, footerNotes, remainingAmount, formatAmount, t, invoiceLogo, companyLogo }: any) {
  const { logoDark } = useBrand();
  const colorWithoutHash = color.replace('#', '').toLowerCase();
  const fontColor = ['ffffff', 'fbdd03', 'c1d82f', '46de98', '40c7d0', 'fac168'].includes(colorWithoutHash) ? '#000000' : '#ffffff';
  const borderColor = colorWithoutHash === 'ffffff' ? '#000000' : color;
  
  const isPreview = !invoice;
  const invoiceData = isPreview ? {
    invoice_number: '<Invoice Number>',
    invoice_date: '2026-12-10',
    due_date: '2026-12-10',
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
        <div style={{ borderTop: `15px solid ${color}`, position: 'relative' }}>
          {(invoiceLogo || companyLogo || logoDark) && (
            <img
              src={invoiceLogo || companyLogo || logoDark}
              alt="Logo"
              style={{
                position: 'absolute',
                top: '30px',
                right: '0',
                maxWidth: '150px',
                maxHeight: '150px',
                objectFit: 'contain'
              }}
            />
          )}
          <div style={{ padding: '15px 0 15px 30px' }}>
            <h3 style={{ textTransform: 'uppercase', fontSize: '40px', fontWeight: 'bold', margin: '0' }}>{translate('INVOICE')}</h3>
          </div>
        </div>

        <div style={{ padding: '30px 25px 30px 25px' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <tbody>
              <tr>
                <td style={{ fontSize: '13px', verticalAlign: 'top' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('From')}:</strong>
                  <p style={{ margin: '0', lineHeight: '1.5' }}>
                    {invoiceData.creator?.name && <>{invoiceData.creator.name}<br /></>}
                    {invoiceData.creator?.email && <>{invoiceData.creator.email}</>}
                  </p>
                </td>
              </tr>
              <tr>
                <td style={{ fontSize: '13px', verticalAlign: 'top' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Bill To')}:</strong>
                  <p style={{ margin: '0', lineHeight: '1.5' }}>
                    {invoiceData.client?.name}<br />
                    {invoiceData.client?.email}
                  </p>
                </td>
                <td style={{ fontSize: '13px', verticalAlign: 'top', textAlign: 'right' }}>
                  <strong style={{ marginBottom: '10px', display: 'block' }}>{translate('Ship To')}:</strong>
                  <p style={{ margin: '0', lineHeight: '1.5' }}>
                    {invoiceData.creator?.name}<br />
                    {invoiceData.creator?.email}
                  </p>
                </td>
              </tr>
              <tr style={{ borderBottom: `1px solid ${color}` }}>
                <td colSpan={3}>
                  {showQr && (
                    <div style={{ maxWidth: '114px', maxHeight: '114px', marginLeft: 'auto', marginTop: '0', background: '#ffffff' }}>
                      <QRCodeGenerator value={invoiceUrl || 'https://example.com/invoice'} size={114} />
                    </div>
                  )}
                </td>
              </tr>
            </tbody>
          </table>

          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <tbody>
              <tr>
                <td>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <tbody>
                      <tr>
                        <td style={{ padding: '0' }}></td>
                        <td style={{ padding: '0', textAlign: 'right' }}>{translate('Number')}: {invoiceData.invoice_number}</td>
                      </tr>
                      <tr>
                        <td style={{ padding: '0' }}></td>
                        <td style={{ padding: '0', textAlign: 'right' }}>{translate('Invoice Date')}: {window.appSettings.formatDateTime(new Date(invoiceData.invoice_date),false)}<br />{translate('Due Date')}: {window.appSettings.formatDateTime(new Date(invoiceData.due_date),false)}</td>
                      </tr>
                    </tbody>
                  </table>
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

