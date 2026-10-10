import { useEffect, useRef, useState, type ReactNode } from 'react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { AlertCircle, File as FileIcon, FileSpreadsheet, FileText, FolderOpen, HardDrive, Loader2, Monitor, Paperclip, Plus, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

declare const route: any;

/** What the server says about a read file (App\Models\AiAttachment::toChip). */
export interface AttachmentChip {
    id: number;
    name: string;
    extension: string;
    size: number;
    kind: 'spreadsheet' | 'document' | 'text';
    status: 'ready' | 'failed' | 'needs_ocr';
    source: 'upload' | 'sundal' | 'google_drive';
    summary: string;
    tokens: number;
}

/** A file in the message box: uploading, read, or refused. */
export interface PendingFile {
    key: string;
    name: string;
    size: number;
    state: 'uploading' | 'ready' | 'failed';
    progress: number;
    chip?: AttachmentChip;
    error?: string;
}

export const ACCEPTED_EXTENSIONS = ['pdf', 'xlsx', 'xls', 'csv', 'docx', 'txt', 'md'];
export const MAX_FILES = 5;
export const MAX_MB = 10;

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function fileIconFor(name: string): typeof FileIcon {
    const extension = name.split('.').pop()?.toLowerCase() ?? '';
    if (['xlsx', 'xls', 'csv'].includes(extension)) return FileSpreadsheet;
    if (['pdf', 'docx', 'txt', 'md'].includes(extension)) return FileText;
    return FileIcon;
}

const errorText = (error: any, fallback: string): string =>
    error?.response?.data?.errors?.file?.[0] ?? error?.response?.data?.error ?? error?.response?.data?.message ?? fallback;

/**
 * The files waiting in the message box. Each one is uploaded and read by
 * Sundal straight away; the message is sent with the ids of the read ones.
 */
export function useAttachments() {
    const { t } = useTranslation();
    const [files, setFiles] = useState<PendingFile[]>([]);
    const filesRef = useRef(files);
    filesRef.current = files;

    const update = (key: string, values: Partial<PendingFile>) =>
        setFiles(prev => prev.map(f => (f.key === key ? { ...f, ...values } : f)));

    const room = () => MAX_FILES - filesRef.current.filter(f => f.state !== 'failed').length;

    const track = async (key: string, request: () => Promise<{ data: { attachment: AttachmentChip } }>) => {
        try {
            const { data } = await request();
            update(key, { state: 'ready', chip: data.attachment, progress: 100 });
        } catch (error) {
            update(key, { state: 'failed', error: errorText(error, t('This file could not be read.')) });
        }
    };

    const add = (list: FileList | File[]) => {
        const picked = Array.from(list);
        const free = room();
        if (picked.length > free) {
            picked.splice(free);
        }
        for (const file of picked) {
            const key = `${Date.now()}-${Math.random()}`;
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            // Checked again on the server; this only saves a pointless upload.
            const problem = !ACCEPTED_EXTENSIONS.includes(extension)
                ? t('Only PDF, Excel (.xlsx, .xls, .csv), Word (.docx) and text (.txt, .md) files can be attached.')
                : file.size > MAX_MB * 1024 * 1024 ? t('Files can be at most {{mb}} MB.', { mb: MAX_MB }) : null;

            setFiles(prev => [...prev, { key, name: file.name, size: file.size, state: problem ? 'failed' : 'uploading', progress: 0, error: problem ?? undefined }]);
            if (problem) continue;

            const form = new FormData();
            form.append('file', file);
            track(key, () => axios.post(route('ai-assistant.attachments.store'), form, {
                onUploadProgress: e => e.total && update(key, { progress: Math.round((e.loaded / e.total) * 100) }),
            }));
        }
    };

    const addFromSundal = (mediaId: number, name: string, size: number) => {
        if (room() <= 0) return;
        const key = `sundal-${mediaId}-${Date.now()}`;
        setFiles(prev => [...prev, { key, name, size, state: 'uploading', progress: 50 }]);
        track(key, () => axios.post(route('ai-assistant.attachments.from-sundal'), { media_id: mediaId }));
    };

    /** A file Sundal already holds as a chip (Google Drive). */
    const addChip = (key: string, name: string, request: () => Promise<{ data: { attachment: AttachmentChip } }>) => {
        if (room() <= 0) return;
        setFiles(prev => [...prev, { key, name, size: 0, state: 'uploading', progress: 50 }]);
        track(key, request);
    };

    const remove = (key: string) => {
        const file = filesRef.current.find(f => f.key === key);
        setFiles(prev => prev.filter(f => f.key !== key));
        if (file?.chip) {
            axios.delete(route('ai-assistant.attachments.destroy', file.chip.id)).catch(() => undefined);
        }
    };

    return {
        files,
        add,
        addFromSundal,
        addChip,
        remove,
        /** After sending: the files now belong to the message. */
        clear: () => setFiles([]),
        ready: files.filter(f => f.state === 'ready' && f.chip).map(f => f.chip as AttachmentChip),
        uploading: files.some(f => f.state === 'uploading'),
        full: files.filter(f => f.state !== 'failed').length >= MAX_FILES,
    };
}

/** One file: icon, name, what Sundal read, and remove. Read-only in sent messages. */
export function FileChip({ file, onRemove }: { file: PendingFile | AttachmentChip; onRemove?: () => void }) {
    const { t } = useTranslation();
    const pending = 'state' in file ? file : null;
    const chip = pending ? pending.chip : (file as AttachmentChip);
    const failed = pending?.state === 'failed';
    const Icon = fileIconFor(file.name);
    const detail = failed
        ? pending?.error
        : pending?.state === 'uploading'
            ? t('Reading… {{progress}}%', { progress: pending.progress })
            : [chip?.summary, chip ? formatBytes(chip.size) : null].filter(Boolean).join(' · ');

    return (
        <div
            className={`flex max-w-full items-center gap-2 rounded-xl border px-2.5 py-1.5 text-left sm:max-w-[280px] ${
                failed ? 'border-destructive/40 bg-destructive/5' : 'bg-background'
            }`}
            title={`${file.name}${detail ? ` — ${detail}` : ''}`}
        >
            <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                failed ? 'bg-destructive/10 text-destructive' : chip?.kind === 'spreadsheet' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
            }`}>
                {pending?.state === 'uploading' ? <Loader2 className="h-4 w-4 animate-spin" /> : failed ? <AlertCircle className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-medium">{file.name}</span>
                <span className={`block truncate text-[11px] ${failed ? 'text-destructive' : 'text-muted-foreground'}`}>{detail}</span>
            </span>
            {onRemove && (
                <button type="button" onClick={onRemove} className="shrink-0 rounded-md p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={t('Remove {{name}}', { name: file.name })}>
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}

/** The + button: from the computer, from Sundal, and Google Drive (or how to set it up). */
export function AttachMenu({ onFiles, onSundal, onDrive, disabled }: {
    onFiles: (files: FileList) => void;
    onSundal: () => void;
    /** Opens the Drive picker; absent while Drive is not set up on this server. */
    onDrive?: () => void;
    disabled: boolean;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [driveHelp, setDriveHelp] = useState(false);

    return (
        <>
            <input
                ref={input}
                type="file"
                multiple
                hidden
                accept={ACCEPTED_EXTENSIONS.map(e => `.${e}`).join(',')}
                onChange={e => { if (e.target.files?.length) onFiles(e.target.files); e.target.value = ''; }}
            />
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="outline" size="icon" className="h-8 w-8 shrink-0 rounded-full" disabled={disabled} aria-label={t('Attach files')} title={disabled ? t('At most {{count}} files per message', { count: MAX_FILES }) : t('Attach files')}>
                        <Plus className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" side="top" className="w-72">
                    <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">{t('Attach a file')}</DropdownMenuLabel>
                    <DropdownMenuItem onSelect={() => input.current?.click()}>
                        <Monitor className="mr-2 h-4 w-4" />
                        {t('Upload from your computer')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={onSundal}>
                        <FolderOpen className="mr-2 h-4 w-4" />
                        {t('From Sundal files')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={onDrive ?? (() => setDriveHelp(true))}>
                        <HardDrive className={`mr-2 h-4 w-4 ${onDrive ? '' : 'text-muted-foreground'}`} />
                        <span className="flex flex-col">
                            <span className={onDrive ? '' : 'text-muted-foreground'}>{t('From Google Drive')}</span>
                            {!onDrive && <span className="text-[11px] text-muted-foreground">{t('Not set up yet')}</span>}
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <p className="px-2 py-1.5 text-[11px] leading-4 text-muted-foreground">
                        {t('PDF, Excel, CSV, Word or text, up to {{mb}} MB. Sundal reads the file and sends only the parts the assistant needs to your company\'s AI provider.', { mb: MAX_MB })}
                    </p>
                </DropdownMenuContent>
            </DropdownMenu>
            <Dialog open={driveHelp} onOpenChange={setDriveHelp}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Google Drive is not set up yet')}</DialogTitle>
                        <DialogDescription>
                            {t('Whoever hosts Sundal connects it to Google once; then everyone can attach files from their own Drive. Until then, download the file and use Upload from your computer.')}
                        </DialogDescription>
                    </DialogHeader>
                    <ol className="list-decimal space-y-1.5 pl-5 text-sm">
                        <li>{t('In Google Cloud Console, create a project and turn on the Google Drive API and the Google Picker API.')}</li>
                        <li>{t('Create an OAuth client ID of type Web application, with this site\'s address as an authorised JavaScript origin.')}</li>
                        <li>{t('Create an API key, limited to the Google Picker API.')}</li>
                        <li>{t('Add them to the server\'s .env file:')} <code className="block whitespace-pre rounded bg-muted p-2 text-xs">{'AI_ASSISTANT_GOOGLE_CLIENT_ID=…\nAI_ASSISTANT_GOOGLE_API_KEY=…\nAI_ASSISTANT_GOOGLE_APP_ID=… (project number)'}</code></li>
                    </ol>
                    <p className="text-xs text-muted-foreground">{t('Sundal asks Google for read-only access, only when someone picks a file, and never stores the access.')}</p>
                </DialogContent>
            </Dialog>
        </>
    );
}

interface SundalFile {
    id: number;
    name: string;
    size: number;
    extension: string;
    where: string | null;
    created_at: string | null;
}

/** Pick a document already in Sundal (media library, task, bug or project files). */
export function SundalFilesDialog({ open, onOpenChange, onPick }: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (file: SundalFile) => void;
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [files, setFiles] = useState<SundalFile[] | null>(null);

    useEffect(() => {
        if (!open) return;
        let stale = false;
        const timer = window.setTimeout(() => {
            axios.get(route('ai-assistant.attachments.sundal'), { params: { search } })
                .then(({ data }) => { if (!stale) setFiles(data.files); })
                .catch(() => { if (!stale) setFiles([]); });
        }, search ? 250 : 0);
        return () => { stale = true; window.clearTimeout(timer); };
    }, [open, search]);

    return (
        <Dialog open={open} onOpenChange={value => { onOpenChange(value); if (!value) { setSearch(''); setFiles(null); } }}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Attach a file from Sundal')}</DialogTitle>
                    <DialogDescription>{t('Documents in your media library and on tasks, bugs and projects you can see.')}</DialogDescription>
                </DialogHeader>
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input value={search} onChange={e => setSearch(e.target.value)} placeholder={t('Search files')} className="pl-8" aria-label={t('Search files')} autoFocus />
                </div>
                <div className="max-h-80 space-y-1 overflow-y-auto">
                    {files === null && <Loader2 className="mx-auto my-6 h-5 w-5 animate-spin text-muted-foreground" />}
                    {files?.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">{t('No PDF, Excel, Word or text files found.')}</p>}
                    {files?.map(file => {
                        const Icon = fileIconFor(file.name);
                        return (
                            <button
                                key={file.id}
                                type="button"
                                onClick={() => { onPick(file); onOpenChange(false); }}
                                className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm">{file.name}</span>
                                    <span className="block truncate text-xs text-muted-foreground">{[file.where, formatBytes(file.size)].filter(Boolean).join(' · ')}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Starter requests for what was attached. They fill the box; nothing is sent until the user does. */
export function FileSuggestions({ files, onPick }: { files: AttachmentChip[]; onPick: (text: string) => void }) {
    const { t } = useTranslation();
    if (files.length === 0) return null;
    const sheet = files.some(f => f.kind === 'spreadsheet');
    const document = files.some(f => f.kind !== 'spreadsheet');
    const suggestions = [
        ...(sheet ? ['Create bugs from this sheet', 'Create tasks from this sheet'] : []),
        ...(document ? ['Turn this document into tasks', 'Summarise this document', 'List the open questions in this document'] : []),
    ];

    return (
        <div className="flex flex-wrap gap-1.5 px-4 pb-1">
            {suggestions.map(text => (
                <button key={text} type="button" onClick={() => onPick(t(text))} className="rounded-full border bg-background px-2.5 py-1 text-[11px] text-muted-foreground transition-colors hover:border-violet-300 hover:text-foreground">
                    <Paperclip className="mr-1 inline h-3 w-3" />
                    {t(text)}
                </button>
            ))}
        </div>
    );
}

/** Drop files anywhere on the chat. */
export function DropZone({ onFiles, children, className = '' }: { onFiles: (files: FileList) => void; children: ReactNode; className?: string }) {
    const { t } = useTranslation();
    const [over, setOver] = useState(false);
    const depth = useRef(0);
    const hasFiles = (e: React.DragEvent) => Array.from(e.dataTransfer?.types ?? []).includes('Files');

    return (
        <div
            className={`relative ${className}`}
            onDragEnter={e => { if (!hasFiles(e)) return; e.preventDefault(); depth.current++; setOver(true); }}
            onDragOver={e => { if (hasFiles(e)) e.preventDefault(); }}
            onDragLeave={() => { depth.current = Math.max(0, depth.current - 1); if (depth.current === 0) setOver(false); }}
            onDrop={e => { if (!hasFiles(e)) return; e.preventDefault(); depth.current = 0; setOver(false); if (e.dataTransfer.files.length) onFiles(e.dataTransfer.files); }}
        >
            {children}
            {over && (
                <div className="pointer-events-none absolute inset-2 z-40 flex items-center justify-center rounded-2xl border-2 border-dashed border-violet-400 bg-violet-50/80 dark:bg-violet-950/60">
                    <div className="text-center">
                        <Paperclip className="mx-auto h-6 w-6 text-violet-600" />
                        <p className="mt-2 text-sm font-medium">{t('Drop files to attach them')}</p>
                        <p className="text-xs text-muted-foreground">{t('PDF, Excel, CSV, Word or text, up to {{mb}} MB', { mb: MAX_MB })}</p>
                    </div>
                </div>
            )}
        </div>
    );
}

/** The install's Google Cloud project, for the Drive picker (null when not set up). */
export interface GoogleDriveConfig {
    clientId: string;
    apiKey: string;
    appId: string | null;
}

const loadScript = (src: string) => new Promise<void>((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error(src));
    document.head.appendChild(script);
});

const DRIVE_MIME_TYPES = [
    'application/pdf', 'text/plain', 'text/markdown', 'text/csv',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.google-apps.document', 'application/vnd.google-apps.spreadsheet',
].join(',');

/**
 * Google's own sign-in and file picker, loaded only when used. The user
 * grants read access to Drive in a Google pop-up; the short-lived token is
 * sent once with each picked file and never stored by Sundal.
 */
export async function openDrivePicker(config: GoogleDriveConfig, onPick: (fileId: string, name: string, token: string) => void): Promise<void> {
    await Promise.all([loadScript('https://accounts.google.com/gsi/client'), loadScript('https://apis.google.com/js/api.js')]);
    const w = window as any;
    await new Promise<void>(resolve => w.gapi.load('picker', () => resolve()));

    const tokenClient = w.google.accounts.oauth2.initTokenClient({
        client_id: config.clientId,
        scope: 'https://www.googleapis.com/auth/drive.readonly',
        callback: (response: { access_token?: string; error?: string }) => {
            if (!response.access_token) return;
            const token = response.access_token;
            const picker = w.google.picker;
            const view = new picker.DocsView(picker.ViewId.DOCS).setMimeTypes(DRIVE_MIME_TYPES).setIncludeFolders(true);
            const builder = new picker.PickerBuilder()
                .addView(view)
                .enableFeature(picker.Feature.MULTISELECT_ENABLED)
                .setOAuthToken(token)
                .setDeveloperKey(config.apiKey)
                .setCallback((data: any) => {
                    if (data[picker.Response.ACTION] !== picker.Action.PICKED) return;
                    for (const doc of data[picker.Response.DOCUMENTS] ?? []) {
                        onPick(doc[picker.Document.ID], doc[picker.Document.NAME], token);
                    }
                });
            if (config.appId) builder.setAppId(config.appId);
            builder.build().setVisible(true);
        },
    });
    tokenClient.requestAccessToken({ prompt: '' });
}
