import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
interface CreateLanguageModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess?: () => void;
}
export function CreateLanguageModal({ open, onOpenChange, onSuccess }: CreateLanguageModalProps) {
    const { t } = useTranslation();
    const [formData, setFormData] = useState({
        code: '',
        name: '',
        countryCode: ''
    });
    const [isLoading, setIsLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const newErrors: Record<string, string> = {};
        if (!formData.code.trim()) newErrors.code = t('Language code is required');
        if (!formData.name.trim()) newErrors.name = t('Language name is required');
        if (!formData.countryCode.trim()) newErrors.countryCode = t('Country code is required');
        if (Object.keys(newErrors).length > 0) { setErrors(newErrors); return; }
        setErrors({});
        setIsLoading(true);
        try {
            const response = await fetch(route('languages.create'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(formData)
            });
            const data = await response.json();
            if (response.ok && data.success) {
                toast.success(data.message || t('Language created successfully'));
                setFormData({ code: '', name: '', countryCode: '' });
                setErrors({});
                onOpenChange(false);
                onSuccess?.();
                window.location.reload();
            } else {
                toast.error(data.message || data.error || t('Failed to create language'));
            }
        } catch (error) {
            toast.error(t('Failed to create language'));
        } finally {
            setIsLoading(false);
        }
    };
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Create New Language')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="code" required>{t('Language Code')}</Label>
                        <Input
                            id="code"
                            placeholder="e.g., fr, de, es"
                            value={formData.code}
                            onChange={(e) => { setFormData({ ...formData, code: e.target.value }); setErrors(p => ({ ...p, code: '' })); }}
                            className={errors.code ? 'border-red-500' : ''}
                        />
                        {errors.code && <p className="text-xs text-red-500 mt-1">{errors.code}</p>}
                    </div>
                    <div>
                        <Label htmlFor="name" required>{t('Language Name')}</Label>
                        <Input
                            id="name"
                            placeholder="e.g., French, German, Spanish"
                            value={formData.name}
                            onChange={(e) => { setFormData({ ...formData, name: e.target.value }); setErrors(p => ({ ...p, name: '' })); }}
                            className={errors.name ? 'border-red-500' : ''}
                        />
                        {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
                    </div>
                    <div>
                        <Label htmlFor="countryCode" required>{t('Country Code')}</Label>
                        <Input
                            id="countryCode"
                            placeholder="e.g., FR, DE, ES"
                            maxLength={2}
                            value={formData.countryCode}
                            onChange={(e) => { setFormData({ ...formData, countryCode: e.target.value.toUpperCase() }); setErrors(p => ({ ...p, countryCode: '' })); }}
                            className={errors.countryCode ? 'border-red-500' : ''}
                        />
                        {errors.countryCode && <p className="text-xs text-red-500 mt-1">{errors.countryCode}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => { onOpenChange(false); setErrors({}); }}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? (
                                <>
                                    <RefreshCw className="h-4 w-4 animate-spin mr-2" />
                                    {t('Creating...')}
                                </>
                            ) : (
                                t('Create')
                            )}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}




