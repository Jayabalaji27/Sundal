import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** The upload limits from Settings > Storage (shared as `storageSettings`). */
export interface FileLimits {
    allowedTypes: string;
    maxSizeKb: number;
}

/** Identifies a picked file, so picking the same file twice doesn't add it twice. */
export const fileKey = (file: File) => `${file.name}:${file.size}:${file.lastModified}`;

const extensionOf = (name: string) => (name.includes('.') ? name.split('.').pop()!.toLowerCase() : '');

export const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${+(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

/** Why the server would refuse this file, or null when it's fine. */
export function fileProblem(file: File, limits: FileLimits, t: (key: string, options?: any) => string): string | null {
    const allowed = limits.allowedTypes.split(',').map((type) => type.trim().toLowerCase()).filter(Boolean);
    const extension = extensionOf(file.name);
    if (allowed.length && !allowed.includes(extension)) {
        return t('{{type}} files are not allowed', { type: (extension || t('This type')).toUpperCase() });
    }
    if (file.size > limits.maxSizeKb * 1024) {
        return t('Too large, max {{size}}', { size: formatFileSize(limits.maxSizeKb * 1024) });
    }
    return null;
}

const badgeColor = (extension: string) => {
    if (extension === 'pdf') return 'bg-red-500';
    if (['doc', 'docx'].includes(extension)) return 'bg-blue-500';
    if (['xls', 'xlsx', 'csv'].includes(extension)) return 'bg-green-600';
    if (['ppt', 'pptx'].includes(extension)) return 'bg-orange-500';
    return 'bg-gray-500';
};

interface Props {
    files: File[];
    limits: FileLimits;
    onRemove: (file: File) => void;
    onClear?: () => void;
    disabled?: boolean;
}

/** Files picked for upload: a thumbnail or type badge, name, size, any problem, and a remove button. */
export function SelectedFilesList({ files, limits, onRemove, onClear, disabled = false }: Props) {
    const { t } = useTranslation();

    const previews = useMemo(
        () => new Map(files.filter((file) => file.type.startsWith('image/')).map((file) => [fileKey(file), URL.createObjectURL(file)])),
        [files],
    );
    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    if (files.length === 0) return null;

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
                <span className="font-medium">
                    {files.length === 1 ? t('1 file selected') : t('{{count}} files selected', { count: files.length })}
                </span>
                {onClear && files.length > 1 && (
                    <Button type="button" variant="link" size="sm" className="h-auto p-0" onClick={onClear} disabled={disabled}>
                        {t('Remove all')}
                    </Button>
                )}
            </div>
            <ul className="max-h-60 divide-y overflow-y-auto rounded-lg border" aria-label={t('Selected files')}>
                {files.map((file) => {
                    const key = fileKey(file);
                    const extension = extensionOf(file.name);
                    const problem = fileProblem(file, limits, t);
                    const preview = previews.get(key);
                    return (
                        <li key={key} className="flex items-center gap-3 px-3 py-2">
                            {preview ? (
                                <img src={preview} alt="" className="h-10 w-10 shrink-0 rounded border object-cover" />
                            ) : (
                                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded text-[10px] font-bold text-white ${badgeColor(extension)}`}>
                                    {(extension || 'file').slice(0, 4).toUpperCase()}
                                </span>
                            )}
                            <div className="min-w-0 flex-1 text-left">
                                <p className="truncate text-sm font-medium" title={file.name}>{file.name}</p>
                                <p className={`text-xs ${problem ? 'text-destructive' : 'text-muted-foreground'}`}>
                                    {problem ?? formatFileSize(file.size)}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 shrink-0"
                                onClick={() => onRemove(file)}
                                disabled={disabled}
                                aria-label={t('Remove {{name}}', { name: file.name })}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
