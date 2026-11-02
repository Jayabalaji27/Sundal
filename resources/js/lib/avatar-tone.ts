// Soft tinted backgrounds with a stronger matching text colour, for avatar
// initials. Picked from a hash of the user's name (or initials), so the same
// person always gets the same colour.
const AVATAR_TONES = [
    'bg-brand-soft text-brand-soft-fg',
    'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
    'bg-pink-50 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300',
    'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
    'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
];

export function avatarTone(seed: string | null | undefined): string {
    const text = (seed ?? '').trim().toLowerCase();
    let hash = 0;
    for (let i = 0; i < text.length; i++) {
        hash = (hash * 31 + text.charCodeAt(i)) | 0;
    }
    return AVATAR_TONES[Math.abs(hash) % AVATAR_TONES.length];
}

export function initialsOf(name: string | null | undefined): string {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}
