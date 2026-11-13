import React, { useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MessageSquare, MoreHorizontal, Edit, Trash2, Send } from 'lucide-react';
import { Task, TaskComment, User } from '@/types';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

type Mentionable = { id: number; name: string; avatar?: string | null };

interface Props {
    task: Task;
    comments: TaskComment[];
    currentUser: User;
    onUpdate?: () => void;
    canAddComments?: boolean;
    /** People who can be @mentioned (the task's project members). */
    members?: Mentionable[];
}

const escapeRegExp = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** Ids of the picked people whose "@Name" is still in the text. */
const mentionsInText = (text: string, picked: number[], people: Mentionable[]) =>
    picked.filter((id) => {
        const person = people.find((p) => p.id === id);
        return person && text.includes(`@${person.name}`);
    });

function Avatar({ person, size = 'sm' }: { person?: { name?: string; avatar?: string | null }; size?: 'sm' | 'md' }) {
    const [failed, setFailed] = useState(false);
    const initials = (person?.name ?? '').substring(0, 2);
    return (
        <div className={cn(
            'flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-ds-brand-soft font-semibold uppercase text-ds-brand-soft-fg',
            size === 'md' ? 'h-8 w-8 text-xs' : 'h-6 w-6 text-[10px]',
        )}>
            {person?.avatar && !failed ? (
                <img src={person.avatar} alt={person.name} className="h-full w-full object-cover" onError={() => setFailed(true)} />
            ) : initials}
        </div>
    );
}

/**
 * Textarea with an @mention picker: typing "@" and part of a name lists matching
 * people; Enter, Tab or a click inserts "@Name". Arrow keys move, Escape closes.
 */
function MentionTextarea({
    value, onChange, people, picked, onPick, placeholder, rows, className, onSubmitShortcut, listAbove = true,
}: {
    value: string;
    onChange: (value: string) => void;
    people: Mentionable[];
    picked: number[];
    onPick: (ids: number[]) => void;
    placeholder?: string;
    rows?: number;
    className?: string;
    onSubmitShortcut?: () => void;
    listAbove?: boolean;
}) {
    const { t } = useTranslation();
    const ref = useRef<HTMLTextAreaElement>(null);
    const [query, setQuery] = useState<string | null>(null);
    const [active, setActive] = useState(0);

    const matches = useMemo(() => {
        if (query === null) return [];
        const q = query.toLowerCase();
        return people.filter((p) => p.name.toLowerCase().includes(q)).slice(0, 6);
    }, [query, people]);

    // The "@word" right before the caret, if any.
    const readQuery = (text: string, caret: number) => {
        const match = /(^|\s)@([^\s@]{0,30})$/.exec(text.slice(0, caret));
        setQuery(match ? match[2] : null);
        setActive(0);
    };

    const insert = (person: Mentionable) => {
        const el = ref.current;
        const caret = el?.selectionStart ?? value.length;
        const before = value.slice(0, caret).replace(/@([^\s@]{0,30})$/, `@${person.name} `);
        const next = before + value.slice(caret);
        onChange(next);
        onPick(Array.from(new Set([...picked, person.id])));
        setQuery(null);
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(before.length, before.length);
        });
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (query !== null && matches.length > 0) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive((i) => (i + 1) % matches.length); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); setActive((i) => (i - 1 + matches.length) % matches.length); return; }
            if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); insert(matches[active]); return; }
        }
        if (e.key === 'Escape' && query !== null) { e.preventDefault(); e.stopPropagation(); setQuery(null); return; }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && onSubmitShortcut) { e.preventDefault(); onSubmitShortcut(); }
    };

    const open = query !== null && matches.length > 0;

    return (
        <div className="relative">
            <Textarea
                ref={ref}
                value={value}
                onChange={(e) => { onChange(e.target.value); readQuery(e.target.value, e.target.selectionStart); }}
                onKeyDown={handleKeyDown}
                onClick={(e) => readQuery(value, e.currentTarget.selectionStart)}
                onBlur={() => setTimeout(() => setQuery(null), 150)}
                placeholder={placeholder}
                rows={rows}
                className={className}
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                aria-controls="mention-list"
            />
            {open && (
                <ul
                    id="mention-list"
                    role="listbox"
                    aria-label={t('People to mention')}
                    className={cn(
                        'absolute left-2 z-50 w-64 overflow-hidden rounded-lg border border-ds-border bg-ds-surface py-1 shadow-lg',
                        listAbove ? 'bottom-full mb-1' : 'top-full mt-1',
                    )}
                >
                    {matches.map((person, i) => (
                        <li
                            key={person.id}
                            role="option"
                            aria-selected={i === active}
                            onMouseDown={(e) => { e.preventDefault(); insert(person); }}
                            onMouseEnter={() => setActive(i)}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm text-ds-fg',
                                i === active && 'bg-ds-surface-hover',
                            )}
                        >
                            <Avatar person={person} />
                            <span className="truncate">{person.name}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** Comment text with each "@Name" of a mentioned person shown as a chip. */
function CommentText({ text, mentionIds, people }: { text: string; mentionIds: number[]; people: Mentionable[] }) {
    const names = mentionIds
        .map((id) => people.find((p) => p.id === id)?.name)
        .filter((n): n is string => !!n)
        .sort((a, b) => b.length - a.length);

    if (names.length === 0) {
        return <p className="whitespace-pre-wrap text-sm leading-relaxed text-ds-fg">{text}</p>;
    }

    const parts = text.split(new RegExp(`(${names.map((n) => `@${escapeRegExp(n)}`).join('|')})`, 'g'));
    return (
        <p className="whitespace-pre-wrap text-sm leading-relaxed text-ds-fg">
            {parts.map((part, i) =>
                names.some((n) => part === `@${n}`) ? (
                    <span key={i} className="rounded bg-ds-brand-soft px-1 py-px font-medium text-ds-brand-soft-fg">{part}</span>
                ) : (
                    <React.Fragment key={i}>{part}</React.Fragment>
                ),
            )}
        </p>
    );
}

export default function TaskComments({ task, comments, onUpdate, canAddComments = true, members = [] }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const myId: number | undefined = auth?.user?.id;

    // Everyone on the project except me; the server drops anyone who can't see the task.
    const people = useMemo<Mentionable[]>(
        () =>
            (members as any[])
                .map((m) => ({ id: Number(m.id ?? m.user_id), name: String(m.name ?? m.user?.name ?? ''), avatar: m.avatar ?? m.user?.avatar ?? null }))
                .filter((m) => m.id && m.name && m.id !== myId),
        [members, myId],
    );
    // Names for highlighting also include the author's own, in case they're mentioned by others.
    const allPeople = useMemo<Mentionable[]>(
        () => [...people, ...(auth?.user ? [{ id: auth.user.id, name: auth.user.name }] : [])],
        [people, auth?.user],
    );

    const [newComment, setNewComment] = useState('');
    const [newMentions, setNewMentions] = useState<number[]>([]);
    const [editingComment, setEditingComment] = useState<number | null>(null);
    const [editText, setEditText] = useState('');
    const [editMentions, setEditMentions] = useState<number[]>([]);
    const [deleteModal, setDeleteModal] = useState<{ isOpen: boolean; commentId: number | null }>({ isOpen: false, commentId: null });

    const submit = () => {
        if (!newComment.trim()) return;

        router.post(route('task-comments.store', task.id), {
            comment: newComment,
            mentions: mentionsInText(newComment, newMentions, people),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewComment('');
                setNewMentions([]);
                onUpdate?.();
            }
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submit();
    };

    const handleEdit = (comment: TaskComment) => {
        setEditingComment(comment.id);
        setEditText(comment.comment);
        setEditMentions(((comment as any).mentions ?? []).map(Number));
    };

    const handleUpdate = (commentId: number) => {
        router.put(route('task-comments.update', commentId), {
            comment: editText,
            mentions: mentionsInText(editText, editMentions, allPeople),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingComment(null);
                setEditText('');
                setEditMentions([]);
                onUpdate?.();
            }
        });
    };

    const handleDelete = (commentId: number) => {
        setDeleteModal({ isOpen: true, commentId });
    };

    const confirmDelete = () => {
        if (deleteModal.commentId) {
            router.delete(route('task-comments.destroy', deleteModal.commentId), {
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteModal({ isOpen: false, commentId: null });
                    onUpdate?.();
                }
            });
        }
    };

    return (
        <>
            <CrudDeleteModal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ isOpen: false, commentId: null })}
                onConfirm={confirmDelete}
                itemName="this comment"
                entityName="comment"
            />
            <div className="flex flex-col h-full overflow-hidden">
                {/* Comments List */}
                <div className="flex-1 overflow-y-auto pr-2 space-y-3 mb-4 pt-4">
                    {comments.map((comment) => (
                        <div key={comment.id} className="flex gap-3 rounded-xl border border-ds-border-subtle bg-ds-surface p-4">
                            <Avatar person={comment.user} size="md" />
                            <div className="min-w-0 flex-1">
                                <div className="mb-1 flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <span className="text-sm font-semibold text-ds-fg">{comment.user?.name}</span>
                                        <span className="ml-2 text-xs text-ds-fg-muted">
                                            {new Date(comment.created_at).toLocaleString()}
                                        </span>
                                    </div>
                                    {(comment.can_update || comment.can_delete) && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="-mt-1 h-7 w-7 p-0" aria-label={t('Comment actions')}>
                                                    <MoreHorizontal className="h-4 w-4 text-ds-fg-muted" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="z-[9999]">
                                                {comment.can_update && (
                                                    <DropdownMenuItem onClick={() => handleEdit(comment)}>
                                                        <Edit className="h-4 w-4 mr-2" />
                                                        {t('Edit')}
                                                    </DropdownMenuItem>
                                                )}
                                                {comment.can_delete && (
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(comment.id)}
                                                        className="text-red-600"
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                        {t('Delete')}
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>

                                {editingComment === comment.id ? (
                                    <div className="mt-2 space-y-2">
                                        <MentionTextarea
                                            value={editText}
                                            onChange={setEditText}
                                            people={people}
                                            picked={editMentions}
                                            onPick={setEditMentions}
                                            rows={2}
                                            listAbove={false}
                                            onSubmitShortcut={() => handleUpdate(comment.id)}
                                            className="resize-none"
                                        />
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => handleUpdate(comment.id)}>
                                                {t('Save')}
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => setEditingComment(null)}>
                                                {t('Cancel')}
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <CommentText text={comment.comment} mentionIds={((comment as any).mentions ?? []).map(Number)} people={allPeople} />
                                )}
                            </div>
                        </div>
                    ))}

                    {comments.length === 0 && (
                        <div className="flex h-full flex-col items-center justify-center py-10 text-center text-ds-fg-muted">
                            <div className="mb-4 rounded-full bg-ds-surface-sunken p-4">
                                <MessageSquare className="h-8 w-8 text-ds-fg-placeholder" />
                            </div>
                            <p className="text-sm font-medium">{t('No comments yet')}</p>
                            <p className="text-xs">{t('Ask a question or share an update. Type @ to mention a teammate.')}</p>
                        </div>
                    )}
                </div>

                {/* Add Comment Form */}
                {canAddComments && (
                    <div className="shrink-0 border-t border-ds-border-subtle bg-ds-surface pt-4">
                        <form onSubmit={handleSubmit} className="relative">
                            <MentionTextarea
                                value={newComment}
                                onChange={setNewComment}
                                people={people}
                                picked={newMentions}
                                onPick={setNewMentions}
                                placeholder={people.length ? t('Write a comment… type @ to mention someone') : t('Write a comment…')}
                                rows={3}
                                onSubmitShortcut={submit}
                                className="resize-none rounded-xl py-3 pr-12"
                            />
                            <div className="absolute bottom-2 right-2">
                                <Button aria-label={t('Send')}
                                    type="submit"
                                    size="icon"
                                    disabled={!newComment.trim()}
                                    className="h-8 w-8 rounded-lg text-white shadow-sm disabled:opacity-50 transition-all"
                                >
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                        <p className="mt-1.5 text-xs text-ds-fg-muted">
                            {t('Mentioned people, the assignee and the task creator are notified. Ctrl+Enter to send.')}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
