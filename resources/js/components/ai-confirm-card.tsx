import { useEffect, useState, type FormEvent } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Check, ExternalLink, Loader2, Pencil, Undo2, X } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import { type AttachmentChip } from '@/components/ai-attachments';
import { ImportTable, type ImportChanges, type ImportView } from '@/components/ai-import-card';
import { PlanList, type PlanChanges, type PlanView } from '@/components/ai-plan-card';

declare const route: any;

/**
 * The AI Assistant's confirm cards and reply text, shared by the assistant
 * page and the voice panel. A card changes nothing until Confirm; every
 * button calls the server, which checks everything again.
 */
export type CardStatus = 'pending' | 'done' | 'failed' | 'cancelled' | 'expired' | 'undone';

export interface ToolCard {
    id: number;
    tool: string;
    summary: string;
    status: CardStatus;
    details: Record<string, string>;
    /** Every record a bulk action touches, listed in full. */
    items: string[];
    /** Risky actions: the user must type this to confirm. */
    confirm_phrase: string | null;
    link: string | null;
    can_undo: boolean;
    undo_until: string | null;
    error: string | null;
    /** Drafts: "question" while a must-know value is missing, then "review". */
    stage: 'question' | 'review';
    /** Missing must-know fields, in order; the first is asked. */
    ask: string[];
    question: string | null;
    /** The draft's values: shown as choice buttons, a summary, or the Edit form. */
    fields: FormFieldState[];
    form_error: string | null;
    /** A richer card: the import table (sheet rows → bugs or tasks). */
    view?: CardView | null;
}

type CardView = ImportView | PlanView;

export interface FormFieldState {
    name: string;
    label: string;
    type: 'select' | 'multi' | 'date' | 'number' | 'text';
    required: boolean;
    value: string | string[] | null;
    options: { value: string; label: string }[];
    /** Why the field was left for the user, e.g. "Several match Ravi". */
    note: string | null;
    error: string | null;
}

export interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    cards: ToolCard[];
    /** Files sent with this message. */
    attachments?: AttachmentChip[];
    /** Shown in the chat only, never saved: the provider refused or failed. */
    error?: boolean;
    /** What the reply put on screen (open_page, show_list, open_record). */
    actions?: UiAction[];
}

/** A Sundal page the server built for a UI tool: always a relative URL. */
export interface UiAction {
    url: string;
    label: string;
}

/** "/tasks/12" yes; "//evil.com", "/\evil.com" and "https://…" (other sites) no. */
export const isSundalPath = (url: string): boolean => /^\/(?![/\\])/.test(url);

export const errorMessage = (error: any, fallback: string): string =>
    error?.response?.data?.error
    ?? error?.response?.data?.message
    ?? (Object.values(error?.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
    ?? fallback;

/**
 * The model's replies use a little Markdown: **bold**, [links](/tasks/12) and
 * "- " bullets. Rendered as React elements, never as HTML, so a reply cannot
 * inject markup. Only links inside Sundal (relative paths, as the tools
 * return) are clickable: a reply could repeat a link planted in a record
 * (prompt injection), so other addresses are shown as plain text instead.
 */
export function FormattedText({ text }: { text: string }) {
    const inline = (line: string, key: string) =>
        line.split(/(\*\*[^*]+\*\*|\[[^\]]+\]\([^)\s]+\))/g).map((part, i) => {
            const bold = part.match(/^\*\*([^*]+)\*\*$/);
            if (bold) return <strong key={`${key}-${i}`}>{bold[1]}</strong>;
            const link = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
            if (link && isSundalPath(link[2])) {
                return <a key={`${key}-${i}`} href={link[2]} className="text-primary underline">{link[1]}</a>;
            }
            if (link) {
                return <span key={`${key}-${i}`}>{link[1]} ({link[2]})</span>;
            }
            return <span key={`${key}-${i}`}>{part}</span>;
        });

    return (
        <>
            {text.split('\n').map((line, i) => {
                const bullet = line.match(/^\s*[-*]\s+(.*)$/);
                return bullet
                    ? <div key={i} className="flex gap-2"><span>•</span><span>{inline(bullet[1], `l${i}`)}</span></div>
                    : <div key={i}>{line.trim() === '' ? ' ' : inline(line, `l${i}`)}</div>;
            })}
        </>
    );
}

/** The pages a reply put on screen, as links (the chat page never navigates by itself). */
export function ActionLinks({ actions, newTab = false }: { actions: UiAction[]; newTab?: boolean }) {
    const shown = actions.filter(action => isSundalPath(action.url));
    if (shown.length === 0) return null;

    return (
        <div className="flex flex-wrap gap-1.5">
            {shown.map((action, i) => (
                newTab ? (
                    <a key={i} href={action.url} target="_blank" rel="noopener" className={actionClass}>
                        {action.label} <ExternalLink className="h-3 w-3" />
                    </a>
                ) : (
                    <Link key={i} href={action.url} className={actionClass}>
                        {action.label} <ExternalLink className="h-3 w-3" />
                    </Link>
                )
            ))}
        </div>
    );
}

const actionClass = 'inline-flex items-center gap-1 rounded-full border bg-background px-2.5 py-1 text-xs text-primary hover:bg-muted';

/** "Title: Login page · Priority: Medium · Assignee: Unassigned" for a draft. */
function knownValues(fields: FormFieldState[]): string {
    return fields
        .filter(f => !isEmpty(f.value))
        .map(f => {
            const values = Array.isArray(f.value) ? f.value : [f.value as string];
            const labels = values.map(v => f.options.find(o => o.value === v)?.label ?? v);
            return `${f.label}: ${labels.join(', ')}`;
        })
        .join(' · ');
}

export function ConfirmCard({ card, onChange }: { card: ToolCard; onChange: (card: ToolCard, note: Message | null) => void }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState<'confirm' | 'cancel' | 'undo' | 'update' | 'edit' | null>(null);
    const [phrase, setPhrase] = useState('');
    const [editing, setEditing] = useState(false);
    // Hide the Undo button once its 10 minutes are over, without a reload.
    const [undoOpen, setUndoOpen] = useState(card.can_undo);
    useEffect(() => {
        setUndoOpen(card.can_undo);
        if (!card.can_undo || !card.undo_until) return;
        const ms = new Date(card.undo_until).getTime() - Date.now();
        const timer = setTimeout(() => setUndoOpen(false), Math.max(0, ms));
        return () => clearTimeout(timer);
    }, [card.can_undo, card.undo_until]);

    const fields = card.fields ?? [];
    const pending = card.status === 'pending';
    // A draft still missing a must-know value: one question, answered with buttons.
    const asking = pending && card.stage === 'question' && (card.ask?.length ?? 0) > 0;
    const askField = asking ? fields.find(f => f.name === card.ask[0]) : undefined;

    // Edit form values, reset whenever the server sends the card back.
    const [values, setValues] = useState<Record<string, string | string[]>>(() => formValues(fields));
    useEffect(() => setValues(formValues(fields)), [card.fields]);

    const phraseOk = !card.confirm_phrase || phrase.trim().toLowerCase() === card.confirm_phrase.toLowerCase();

    const post = async (action: 'confirm' | 'cancel' | 'undo' | 'update' | 'edit', body: object = {}) => {
        setBusy(action);
        try {
            const { data } = await axios.post(route(`ai-assistant.tool-calls.${action}`, card.id), body);
            onChange(data.card, data.message);
            if (data.card.status === 'failed') toast.error(data.card.error || t('The action failed.'));
            return data.card as ToolCard;
        } catch (error) {
            toast.error(errorMessage(error, t('Could not update this card.')));
            return null;
        } finally {
            setBusy(null);
        }
    };

    /** A clicked choice, a typed answer or the Edit form: no AI call. */
    const update = async (fieldValues: Record<string, string | string[]>) => {
        const updated = await post('update', { fields: fieldValues });
        if (updated && !(updated.fields ?? []).some(f => f.error)) setEditing(false);
    };

    const statusLabel: Record<CardStatus, string> = {
        pending: asking ? t('Needs an answer') : t('Waiting for you'),
        done: t('Done'),
        failed: t('Failed'),
        cancelled: t('Cancelled'),
        expired: t('Expired'),
        undone: t('Undone'),
    };

    const known = knownValues(fields);
    const view = card.view ?? null;
    // An editable table (sheet import): changes go straight to the server, no AI call.
    const editView = (changes: ImportChanges | PlanChanges) => post('edit', { changes });
    const confirmLabel = view?.type === 'import' && view.kind === 'bugs'
        ? t('Create {{count}} bugs', { count: view.included })
        : view ? t('Create {{count}} tasks', { count: view.included }) : t('Confirm');

    return (
        <Card className={pending ? 'border-violet-300 dark:border-violet-700' : ''}>
            <CardContent className="space-y-3 p-3">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium">{card.summary}</p>
                    <Badge variant={card.status === 'done' ? 'default' : card.status === 'failed' ? 'destructive' : 'secondary'}>
                        {statusLabel[card.status]}
                    </Badge>
                </div>

                {pending && view?.type === 'import' ? (
                    <ImportTable view={view} busy={busy !== null} onEdit={editView} />
                ) : pending && view?.type === 'plan' ? (
                    <PlanList view={view} busy={busy !== null} onEdit={editView} />
                ) : pending && editing ? (
                    <>
                        <CardForm cardId={card.id} fields={fields} values={values} onChange={(name, value) => setValues(prev => ({ ...prev, [name]: value }))} />
                        <div className="flex gap-2">
                            <Button size="sm" onClick={() => update(values)} disabled={busy !== null}>
                                {busy === 'update' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                                {t('Save')}
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => { setEditing(false); setValues(formValues(fields)); }} disabled={busy !== null}>
                                {t('Back')}
                            </Button>
                        </div>
                    </>
                ) : asking && askField ? (
                    <>
                        <ChoiceButtons field={askField} question={card.question ?? askField.label} disabled={busy !== null} onPick={value => update({ [askField.name]: value })} />
                        {known && <p className="text-xs text-muted-foreground">{known}</p>}
                    </>
                ) : (
                    <>
                        {Object.keys(card.details).length > 0 ? (
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                {Object.entries(card.details).map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : known && <p className="text-xs text-muted-foreground">{known}</p>}
                        {card.items.length > 0 && (
                            <ul className="max-h-48 list-disc space-y-0.5 overflow-y-auto rounded-md bg-muted/50 py-2 pl-6 pr-2 text-xs">
                                {card.items.map((item, i) => <li key={i}>{item}</li>)}
                            </ul>
                        )}
                    </>
                )}

                {pending && card.form_error && <p className="text-xs text-destructive">{card.form_error}</p>}
                {card.error && <p className="text-xs text-destructive">{card.error}</p>}

                {pending && !editing && !asking && card.confirm_phrase && (
                    <div className="space-y-1">
                        <Label htmlFor={`phrase-${card.id}`} className="text-xs">
                            {t('This cannot be undone. Type {{phrase}} to confirm.', { phrase: card.confirm_phrase })}
                        </Label>
                        <Input id={`phrase-${card.id}`} value={phrase} onChange={e => setPhrase(e.target.value)} className="h-8 text-xs" autoComplete="off" />
                    </div>
                )}

                {pending && !editing && (
                    <div className="flex flex-wrap gap-2">
                        {!asking && (
                            <Button size="sm" onClick={() => post('confirm', card.confirm_phrase ? { phrase } : {})} disabled={busy !== null || !phraseOk || !!card.form_error || !!view?.problem}>
                                {busy === 'confirm' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                                {confirmLabel}
                            </Button>
                        )}
                        {fields.length > 0 && (
                            <Button size="sm" variant="outline" onClick={() => setEditing(true)} disabled={busy !== null}>
                                <Pencil className="mr-1 h-3 w-3" />
                                {t('Edit')}
                            </Button>
                        )}
                        <Button size="sm" variant="ghost" onClick={() => post('cancel')} disabled={busy !== null}>
                            <X className="mr-1 h-3 w-3" />
                            {t('Cancel')}
                        </Button>
                    </div>
                )}

                {card.status === 'done' && (card.link || undoOpen) && (
                    <div className="flex flex-wrap items-center gap-3">
                        {card.link && (
                            <Link href={card.link} className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                {t('Open record')} <ExternalLink className="h-3 w-3" />
                            </Link>
                        )}
                        {undoOpen && (
                            <Button size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => post('undo')} disabled={busy !== null}>
                                {busy === 'undo' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Undo2 className="mr-1 h-3 w-3" />}
                                {t('Undo')}
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

const MAX_CHOICE_BUTTONS = 8;

/**
 * The answer to one draft question: the choices as buttons (the rest in a
 * short list), or a small box for a name, email or date. One click updates
 * the draft on the server; no AI call.
 */
function ChoiceButtons({ field, question, disabled, onPick }: {
    field: FormFieldState;
    question: string;
    disabled: boolean;
    onPick: (value: string | string[]) => void;
}) {
    const { t } = useTranslation();
    const [text, setText] = useState('');
    const [picked, setPicked] = useState<string[]>([]);
    const shown = field.options.slice(0, MAX_CHOICE_BUTTONS);
    const rest = field.options.slice(MAX_CHOICE_BUTTONS);

    return (
        <div className="space-y-2">
            <p className="text-sm">{question}</p>
            {field.note && <p className="text-xs text-amber-600 dark:text-amber-500">{field.note}</p>}

            {field.type === 'select' && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {shown.map(option => (
                        <Button key={option.value} type="button" size="sm" variant="outline" className="h-7 px-2.5 text-xs" disabled={disabled} onClick={() => onPick(option.value)}>
                            {option.label}
                        </Button>
                    ))}
                    {rest.length > 0 && (
                        <select className={`${fieldClass} h-7 w-auto`} value="" disabled={disabled} onChange={e => e.target.value && onPick(e.target.value)} aria-label={t('More choices')}>
                            <option value="">{t('More…')}</option>
                            {rest.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    )}
                </div>
            )}

            {field.type === 'multi' && (
                <div className="space-y-2">
                    <div className="flex flex-wrap gap-1.5">
                        {field.options.slice(0, 20).map(option => {
                            const on = picked.includes(option.value);
                            return (
                                <Button
                                    key={option.value}
                                    type="button"
                                    size="sm"
                                    variant={on ? 'default' : 'outline'}
                                    className="h-7 px-2.5 text-xs"
                                    aria-pressed={on}
                                    disabled={disabled}
                                    onClick={() => setPicked(prev => (on ? prev.filter(v => v !== option.value) : [...prev, option.value]))}
                                >
                                    {option.label}
                                </Button>
                            );
                        })}
                    </div>
                    <Button type="button" size="sm" className="h-7 text-xs" disabled={disabled || picked.length === 0} onClick={() => onPick(picked)}>
                        {t('Done')}
                    </Button>
                </div>
            )}

            {(field.type === 'text' || field.type === 'date' || field.type === 'number') && (
                <form className="flex gap-2" onSubmit={(e: FormEvent) => { e.preventDefault(); if (text.trim()) onPick(text.trim()); }}>
                    <Input type={field.type} min={field.type === 'number' ? 0 : undefined} step={field.type === 'number' ? 'any' : undefined} value={text} onChange={e => setText(e.target.value)} className="h-8 text-xs" autoFocus />
                    <Button type="submit" size="sm" className="h-8" disabled={disabled || !text.trim()}>{t('OK')}</Button>
                </form>
            )}
        </div>
    );
}

const isEmpty = (value: string | string[] | null | undefined) =>
    value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);

function formValues(fields: FormFieldState[]): Record<string, string | string[]> {
    return Object.fromEntries(fields.map(f => [f.name, f.type === 'multi' ? (Array.isArray(f.value) ? f.value : []) : (f.value as string | null) ?? '']));
}

const fieldClass = 'h-8 w-full rounded-md border border-input bg-background px-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

/**
 * The editable part of a form card. Values the user named are pre-filled;
 * the rest are picked here, from lists the server built for this user.
 */
function CardForm({ cardId, fields, values, onChange }: {
    cardId: number;
    fields: FormFieldState[];
    values: Record<string, string | string[]>;
    onChange: (name: string, value: string | string[]) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2.5">
            {fields.map(field => {
                const id = `card-${cardId}-${field.name}`;
                const value = values[field.name];
                const invalid = !!field.error;

                return (
                    <div key={field.name} className="space-y-1">
                        <Label htmlFor={id} className="text-xs">
                            {field.label}
                            {field.required && <span className="text-destructive"> *</span>}
                        </Label>

                        {field.type === 'select' && (
                            <select
                                id={id}
                                className={`${fieldClass} ${invalid ? 'border-destructive' : ''}`}
                                value={(value as string) ?? ''}
                                onChange={e => onChange(field.name, e.target.value)}
                                aria-invalid={invalid}
                            >
                                <option value="">{t('Choose…')}</option>
                                {field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                        )}

                        {field.type === 'multi' && (
                            <div id={id} className={`max-h-36 space-y-1 overflow-y-auto rounded-md border p-2 ${invalid ? 'border-destructive' : ''}`}>
                                {field.options.length === 0 && <p className="text-xs text-muted-foreground">{t('Nobody to choose from.')}</p>}
                                {field.options.map(option => {
                                    const selected = Array.isArray(value) && value.includes(option.value);
                                    return (
                                        <label key={option.value} className="flex cursor-pointer items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={selected}
                                                onChange={() => {
                                                    const current = Array.isArray(value) ? value : [];
                                                    onChange(field.name, selected ? current.filter(v => v !== option.value) : [...current, option.value]);
                                                }}
                                            />
                                            {option.label}
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        {(field.type === 'date' || field.type === 'text' || field.type === 'number') && (
                            <Input
                                id={id}
                                type={field.type}
                                min={field.type === 'number' ? 0 : undefined}
                                step={field.type === 'number' ? 'any' : undefined}
                                className={`h-8 text-xs ${invalid ? 'border-destructive' : ''}`}
                                value={(value as string) ?? ''}
                                onChange={e => onChange(field.name, e.target.value)}
                                aria-invalid={invalid}
                            />
                        )}

                        {field.error
                            ? <p className="text-xs text-destructive">{field.error}</p>
                            : field.note && <p className="text-xs text-amber-600 dark:text-amber-500">{field.note}</p>}
                    </div>
                );
            })}
        </div>
    );
}
