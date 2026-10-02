import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';

interface PageFormProps {
  data: {
    title: string;
    content: string;
    meta_title: string;
    meta_description: string;
    is_active: boolean;
    sort_order: number;
  };
  setData: (key: string, value: any) => void;
  errors: Record<string, string>;
  processing: boolean;
  onSubmit: (e: React.FormEvent) => void;
  onCancel: () => void;
  isEditing?: boolean;
  slug?: string;
}

export function PageForm({ 
  data, 
  setData, 
  errors, 
  processing, 
  onSubmit, 
  onCancel, 
  isEditing = false,
  slug
}: PageFormProps) {
  const { t } = useTranslation();

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      {/* Page Information Section */}
      <div className="space-y-4">
        
        <div className="space-y-2">
          <Label htmlFor="title" className="text-sm font-medium">
            {t('Page Title')} <span className="text-red-500">*</span>
          </Label>
          <Input
            id="title"
            value={data.title}
            onChange={(e) => setData('title', e.target.value)}
            placeholder={t('e.g., About Us, Privacy Policy')}
            className={errors.title ? 'border-red-500 w-full' : 'w-full'}
          />
          {errors.title && <p className="text-red-600 text-xs mt-1">{errors.title}</p>}
          {isEditing && slug && (
            <p className="text-xs text-muted-foreground">
              {t('Current slug')}: <span className="font-mono">/page/{slug}</span>
            </p>
          ) || (
            <p className="text-xs text-muted-foreground">
              {t('The title will be used to automatically generate the URL slug')}
            </p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="content" className="text-sm font-medium">
            {t('Content')} <span className="text-red-500">*</span>
          </Label>
          <div className="min-h-[200px]">
            <RichTextEditor
              content={data.content}
              onChange={(content) => setData('content', content)}
              placeholder={t('Write your page content here...')}
              className={errors.content ? 'border-red-500' : ''}
            />
          </div>
          {errors.content && <p className="text-red-600 text-xs mt-1">{errors.content}</p>}
          <p className="text-xs text-muted-foreground mt-3">
            {t('Use the editor toolbar to format your content with headings, lists, links, and more')}
          </p>
        </div>
      </div>

      {/* SEO Section */}
      <div className="space-y-4 pt-4 border-t">
        <h3 className="text-sm font-semibold">{t('SEO Settings')}</h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Meta Title */}
          <div className="space-y-2">
            <Label htmlFor="meta_title" className="text-sm font-medium">
              {t('Meta Title')}
            </Label>
            <Input
              id="meta_title"
              value={data.meta_title}
              onChange={(e) => setData('meta_title', e.target.value)}
              placeholder={t('SEO optimized title')}
              maxLength={60}
            />
            <p className="text-xs text-muted-foreground">
              {t('Recommended: 50-60 characters')} ({data.meta_title?.length || 0}/60)
            </p>
          </div>

          {/* Sort Order */}
          <div className="space-y-2">
            <Label htmlFor="sort_order" className="text-sm font-medium">
              {t('Sort Order')}
            </Label>
            <Input
              id="sort_order"
              type="number"
              value={data.sort_order}
              onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
              placeholder="0"
              min="0"
            />
            <p className="text-xs text-muted-foreground">
              {t('Lower numbers appear first in navigation')}
            </p>
          </div>
        </div>

        {/* Meta Description */}
        <div className="space-y-2">
          <Label htmlFor="meta_description" className="text-sm font-medium">
            {t('Meta Description')}
          </Label>
          <Textarea
            id="meta_description"
            value={data.meta_description}
            onChange={(e) => setData('meta_description', e.target.value)}
            placeholder={t('Brief description for search engines')}
            rows={3}
            maxLength={160}
          />
          <p className="text-xs text-muted-foreground">
            {t('Recommended: 150-160 characters')} ({data.meta_description?.length || 0}/160)
          </p>
        </div>
      </div>

      {/* Publish Settings Section */}
      <div className="space-y-4 pt-4 border-t">
        <h3 className="text-sm font-semibold">{t('Publish Settings')}</h3>
        
        <div className="flex items-start space-x-3 p-4 bg-muted/50 rounded-lg">
          <Switch
            id="is_active"
            checked={data.is_active}
            onCheckedChange={(checked) => setData('is_active', checked)}
          />
          <div className="flex-1">
            <Label htmlFor="is_active" className="text-sm font-medium cursor-pointer">
              {t('Publish Page')}
            </Label>
            <p className="text-xs text-muted-foreground mt-1">
              {data.is_active 
                ? t('This page will be visible to the public immediately') 
                : t('This page will be saved as a draft and hidden from public view')}
            </p>
          </div>
        </div>
      </div>

      {/* Form Actions */}
      <div className="flex justify-end space-x-3 pt-6 border-t">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={processing}>
          {isEditing ? t('Update') : t('Create')}
        </Button>
      </div>
    </form>
  );
}