import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { useState } from 'react';
import { Loader2, X } from 'lucide-react';
import { NewYork, Toronto, Rio, London, Istanbul, Mumbai, HongKong, Tokyo, Sydney, Paris } from './invoice-templates';
import MediaLibraryModal from '@/components/MediaLibraryModal';
import { useBrand } from '@/contexts/BrandContext';

interface InvoiceSettingsProps {
  settings?: Record<string, any>;
}

const INVOICE_TEMPLATES = [
  { value: 'new_york', label: 'New York' },
  { value: 'toronto', label: 'Toronto' },
  { value: 'rio', label: 'Rio' },
  { value: 'london', label: 'London' },
  { value: 'istanbul', label: 'Istanbul' },
  { value: 'mumbai', label: 'Mumbai' },
  { value: 'hong_kong', label: 'Hong Kong' },
  { value: 'tokyo', label: 'Tokyo' },
  { value: 'sydney', label: 'Sydney' },
  { value: 'paris', label: 'Paris' },
];

export default function InvoiceSettings({ settings = {} }: InvoiceSettingsProps) {
  const { t } = useTranslation();
  const { logoDark } = useBrand();
  const [selectedTemplate, setSelectedTemplate] = useState(settings?.invoice_template || 'new_york');
  const [showQr, setShowQr] = useState(settings?.invoice_qr_display === 'true' || settings?.invoice_qr_display === true);
  const [color, setColor] = useState(settings?.invoice_color || '#ffffff');
  const [footerTitle, setFooterTitle] = useState(settings?.invoice_footer_title || '');
  const [footerNotes, setFooterNotes] = useState(settings?.invoice_footer_notes || '');
  const [invoiceLogo, setInvoiceLogo] = useState(settings?.invoice_logo || '');
  const [showMediaModal, setShowMediaModal] = useState(false);
  const [processing, setProcessing] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post(route('settings.invoice.store'), {
      invoice_template: selectedTemplate,
      invoice_qr_display: showQr,
      invoice_color: color,
      invoice_footer_title: footerTitle,
      invoice_footer_notes: footerNotes,
      invoice_logo: invoiceLogo,
    }, {
        preserveScroll: true,
      onSuccess: (page) => {
            // Flash messages handled by useEffect
            setProcessing(false);
            toast.dismiss();
            const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
        },
      onError: () => {
        toast.error(t('Failed to update invoice settings'));
        setProcessing(false);
      },
    });
  };

  return (
    <div className="mb-2 mt-2">
      <Card className="p-6">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle className='text-lg font-medium'>{t('Invoice Settings')}</CardTitle>
                <CardDescription>{t('Customize your invoice appearance')}</CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleSubmit} className="space-y-6">
                  <div className="space-y-2">
                    <Label htmlFor="invoice_template">{t('Invoice Template')}</Label>
                    <Select value={selectedTemplate} onValueChange={setSelectedTemplate}>
                      <SelectTrigger id="invoice_template">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {INVOICE_TEMPLATES.map((template) => (
                          <SelectItem key={template.value} value={template.value}>
                            {template.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label htmlFor="invoice_qr_display">{t('Display QR Code')}</Label>
                      <p className="text-sm text-muted-foreground">{t('Show QR code on invoices')}</p>
                    </div>
                    <Switch checked={showQr} onCheckedChange={setShowQr} />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="invoice_color">{t('Primary Color')}</Label>
                    <div className="flex items-center gap-3">
                      <Input
                        id="invoice_color"
                        type="color"
                        value={color}
                        onChange={(e) => setColor(e.target.value)}
                        className="h-10 w-20 cursor-pointer"
                      />
                      <Input
                        type="text"
                        value={color}
                        onChange={(e) => setColor(e.target.value)}
                        placeholder={t('#ffffff')}
                        className="flex-1"
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="invoice_logo">{t('Invoice Logo')}</Label>
                    <div className="flex items-center gap-2">
                      <Input
                        id="invoice_logo"
                        type="text"
                        value={invoiceLogo}
                        placeholder={t('Select invoice logo...')}
                        readOnly
                      />
                      <Button type="button" variant="outline" onClick={() => setShowMediaModal(true)}>
                        {t('Browse')}
                      </Button>
                      {invoiceLogo && (
                        <Button type="button" variant="outline" size="icon" onClick={() => setInvoiceLogo('')}>
                          <X className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                    {invoiceLogo && (
                      <div className="mt-2">
                        <img src={invoiceLogo} alt="Logo Preview" className="max-w-[150px] max-h-[150px] object-contain" />
                      </div>
                    )}
                    <p className="text-sm text-muted-foreground">{t('Select a logo for invoices')}</p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="footer_title">{t('Footer Title')}</Label>
                    <Input
                      id="footer_title"
                      type="text"
                      value={footerTitle}
                      onChange={(e) => setFooterTitle(e.target.value)}
                      placeholder={t('Additional title for invoice footer')}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="footer_notes">{t('Footer Notes')}</Label>
                    <Textarea
                      id="footer_notes"
                      value={footerNotes}
                      onChange={(e) => setFooterNotes(e.target.value)}
                      placeholder={t('Additional notes for invoice footer')}
                      className="min-h-20"
                    />
                  </div>

                  <Button type="submit" disabled={processing} className="w-full">
                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {t('Save Changes')}
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>

          <div className="lg:col-span-2">
            <Card className="h-full">
              <CardHeader>
                <CardTitle className='text-lg font-medium'>{t('Invoice Preview')}</CardTitle>
              </CardHeader>
              <CardContent className={`overflow-y-auto ${invoiceLogo ? 'max-h-[800px]' : 'max-h-[600px]'}`}>
                <InvoicePreview template={selectedTemplate} color={color} showQr={showQr} footerTitle={footerTitle} footerNotes={footerNotes} invoiceLogo={invoiceLogo} companyLogo={logoDark} />
              </CardContent>
            </Card>
          </div>
        </div>
      </Card>

      <MediaLibraryModal
        isOpen={showMediaModal}
        onClose={() => setShowMediaModal(false)}
        onSelect={(url) => {
          setInvoiceLogo(url);
          setShowMediaModal(false);
        }}
        multiple={false}
      />
    </div>
  );
}

function InvoicePreview({ template, color, showQr, footerTitle, footerNotes, invoiceLogo, companyLogo }: { template: string; color: string; showQr: boolean; footerTitle?: string; footerNotes?: string; invoiceLogo?: string; companyLogo?: string }) {
  const invoiceUrl = typeof window !== 'undefined' ? window.location.href : 'https://example.com/invoice';
  const templateMap: Record<string, React.ComponentType<any>> = {
    new_york: NewYork,
    toronto: Toronto,
    rio: Rio,
    london: London,
    istanbul: Istanbul,
    mumbai: Mumbai,
    hong_kong: HongKong,
    tokyo: Tokyo,
    sydney: Sydney,
    paris: Paris,
  };

  const TemplateComponent = templateMap[template];
  return TemplateComponent ? <TemplateComponent color={color} showQr={showQr} invoiceUrl={invoiceUrl} footerTitle={footerTitle} footerNotes={footerNotes} invoiceLogo={invoiceLogo} companyLogo={companyLogo} /> : null;
}
