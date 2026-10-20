import { useState, type FormEvent } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Download, FileSpreadsheet, FileText, FileType, Loader2, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from '@/components/custom-toast';

export type ReportFormat = 'pdf' | 'xlsx' | 'docx';

type ReportParams = Record<string, string | number | null | undefined>;

interface Props {
    /** Download endpoint, e.g. route('timesheet-reports.export'). */
    exportUrl: string;
    /** Email endpoint, e.g. route('timesheet-reports.email'). */
    emailUrl: string;
    /** The filters the report on screen was generated with, sent with both requests. */
    params?: ReportParams;
    disabled?: boolean;
}

const FORMATS: { value: ReportFormat; label: string; icon: typeof FileText }[] = [
    { value: 'pdf', label: 'PDF', icon: FileText },
    { value: 'xlsx', label: 'Excel', icon: FileSpreadsheet },
    { value: 'docx', label: 'Word', icon: FileType },
];

const jsonHeaders = () => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
});

/** First validation error or message from a failed JSON response. */
async function errorMessage(response: Response, fallback: string): Promise<string> {
    try {
        const data = await response.json();
        const firstError = data?.errors ? (Object.values(data.errors)[0] as string[])?.[0] : null;
        return firstError || data?.message || fallback;
    } catch {
        return fallback;
    }
}

function filenameFrom(response: Response, fallback: string): string {
    const header = response.headers.get('Content-Disposition') || '';
    const encoded = header.match(/filename\*=UTF-8''([^;]+)/i);
    if (encoded) return decodeURIComponent(encoded[1]);
    return header.match(/filename="?([^";]+)"?/i)?.[1] || fallback;
}

/** "Download" (PDF / Excel / Word) and "Email" buttons for a generated report. */
export function ReportExportActions({ exportUrl, emailUrl, params = {}, disabled = false }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const myEmail: string = auth?.user?.email || '';

    const [downloading, setDownloading] = useState<ReportFormat | null>(null);
    const [emailOpen, setEmailOpen] = useState(false);
    const [format, setFormat] = useState<ReportFormat>('pdf');
    const [recipient, setRecipient] = useState<'me' | 'other'>('me');
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [emailError, setEmailError] = useState<string | null>(null);

    const openEmail = (preset?: ReportFormat) => {
        if (preset) setFormat(preset);
        setEmailError(null);
        setEmailOpen(true);
    };

    const download = async (selected: ReportFormat) => {
        setDownloading(selected);
        try {
            const query = new URLSearchParams();
            const all: ReportParams = { ...params, format: selected };
            Object.entries(all).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') query.set(key, String(value));
            });
            const response = await fetch(`${exportUrl}?${query.toString()}`, { headers: jsonHeaders(), credentials: 'same-origin' });
            if (!response.ok) {
                toast.error(await errorMessage(response, t('Failed to download the report.')));
                return;
            }

            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFrom(response, `report.${selected}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);

            toast.success(t('Report downloaded'), {
                description: t('Want to share it? Email it to yourself or someone else.'),
                duration: 10000,
                action: { label: t('Email it'), onClick: () => openEmail(selected) },
            });
        } catch (error) {
            console.error('Failed to download report:', error);
            toast.error(t('Failed to download the report.'));
        } finally {
            setDownloading(null);
        }
    };

    const send = async (event: FormEvent) => {
        event.preventDefault();
        setSending(true);
        setEmailError(null);
        try {
            const response = await fetch(emailUrl, {
                method: 'POST',
                headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ ...params, format, recipient, email: recipient === 'other' ? email.trim() : null, message: message.trim() || null }),
            });
            if (!response.ok) {
                setEmailError(response.status === 429
                    ? t('Too many emails sent. Please wait a minute and try again.')
                    : await errorMessage(response, t('Failed to send the report.')));
                return;
            }
            const data = await response.json();
            toast.success(data?.message || t('Report sent.'));
            setEmailOpen(false);
            setMessage('');
        } catch (error) {
            console.error('Failed to email report:', error);
            setEmailError(t('Failed to send the report.'));
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button size="sm" disabled={disabled || downloading !== null}>
                        {downloading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                        {downloading ? t('Preparing...') : t('Download')}
                        <ChevronDown className="ml-1 h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-44">
                    {FORMATS.map(({ value, label, icon: Icon }) => (
                        <DropdownMenuItem key={value} onClick={() => download(value)} className="gap-2">
                            <Icon className="h-4 w-4" />
                            {t(label)}
                            <span className="text-muted-foreground ml-auto text-xs">.{value}</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            <Button size="sm" variant="outline" disabled={disabled} onClick={() => openEmail()}>
                <Mail className="mr-2 h-4 w-4" />
                {t('Email')}
            </Button>

            <Dialog open={emailOpen} onOpenChange={(open) => !sending && setEmailOpen(open)}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={send} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>{t('Email report')}</DialogTitle>
                            <DialogDescription>{t('The report is attached to the email in the format you choose.')}</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2">
                            <Label>{t('Format')}</Label>
                            <div className="grid grid-cols-3 gap-2" role="radiogroup" aria-label={t('Format')}>
                                {FORMATS.map(({ value, label, icon: Icon }) => (
                                    <Button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={format === value}
                                        variant={format === value ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => setFormat(value)}
                                    >
                                        <Icon className="mr-1.5 h-4 w-4" />
                                        {t(label)}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>{t('Send to')}</Label>
                            <RadioGroup value={recipient} onValueChange={(value) => setRecipient(value as 'me' | 'other')} className="gap-3">
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem value="me" id="report-recipient-me" />
                                    <Label htmlFor="report-recipient-me" className="font-normal">
                                        {t('My email')} {myEmail && <span className="text-muted-foreground">({myEmail})</span>}
                                    </Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem value="other" id="report-recipient-other" />
                                    <Label htmlFor="report-recipient-other" className="font-normal">{t('Another email address')}</Label>
                                </div>
                            </RadioGroup>
                            {recipient === 'other' && (
                                <Input
                                    type="email"
                                    required
                                    autoFocus
                                    placeholder={t('name@company.com')}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    aria-label={t('Recipient email')}
                                />
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="report-email-message">
                                {t('Message')} <span className="text-muted-foreground font-normal">({t('optional')})</span>
                            </Label>
                            <Textarea
                                id="report-email-message"
                                rows={3}
                                maxLength={1000}
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                placeholder={t('Add a note for the recipient')}
                            />
                        </div>

                        {emailError && <p className="text-destructive text-sm" role="alert">{emailError}</p>}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEmailOpen(false)} disabled={sending}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={sending || (recipient === 'other' && !email.trim())}>
                                {sending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Mail className="mr-2 h-4 w-4" />}
                                {sending ? t('Sending...') : t('Send')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
