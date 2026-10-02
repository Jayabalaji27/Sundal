import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useState, useEffect } from 'react';
import { Save, Search, Upload, X, Lightbulb } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { getImagePath } from '@/utils/helpers';
import { Card, CardContent } from '@/components/ui/card';
interface SeoSettingsProps {
  settings?: Record<string, string>;
}
export default function SeoSettings({ settings = {} }: SeoSettingsProps) {
  const { t } = useTranslation();
  const pageProps = usePage().props as any;
  const isSaas = pageProps.globalSettings.is_saas;
  // Default settings
  const defaultSettings = {
    metaKeywords: '',
    metaDescription: '',
    metaImage: '/storage/seo/seo-banner.jpg'
  };
  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0
    ? settings
    : (pageProps.settings || {});
  // Initialize state with merged settings
  const [seoSettings, setSeoSettings] = useState(() => ({
    metaKeywords: settingsData.metaKeywords || defaultSettings.metaKeywords,
    metaDescription: settingsData.metaDescription || defaultSettings.metaDescription,
    metaImage: settingsData.metaImage || defaultSettings.metaImage
  }));
  // State for image upload
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [imageError, setImageError] = useState(false);
  const [imageFile, setImageFile] = useState<File | null>(null);
  // Update state when settings change
  useEffect(() => {
    if (Object.keys(settingsData).length > 0) {
      const mergedSettings = Object.keys(defaultSettings).reduce((acc, key) => {
        acc[key] = settingsData[key] || defaultSettings[key];
        return acc;
      }, {} as Record<string, string>);
      setSeoSettings(prevSettings => ({
        ...prevSettings,
        ...mergedSettings
      }));
    }
  }, [settingsData]);
  // Handle SEO settings form changes
  const handleSeoSettingsChange = (field: string, value: string) => {
    setSeoSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };
  // Handle file upload for meta image
  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    // Validate file type
    if (!file.type.startsWith('image/')) {
      toast.error('Please select a valid image file');
      return;
    }
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Image size should be less than 5MB');
      return;
    }
    // Create a URL for preview
    const fileUrl = URL.createObjectURL(file);
    // Reset error state
    setImageError(false);
    setImagePreview(fileUrl);
    setImageFile(file);
  };
  // Remove uploaded image
  const removeImage = () => {
    setImagePreview(null);
    setImageError(false);
    setImageFile(null);
    setSeoSettings(prev => ({
      ...prev,
      metaImage: defaultSettings.metaImage
    }));
  };
  // Handle SEO settings form submission
  const submitSeoSettings = (e: React.FormEvent) => {
    e.preventDefault();
    // Client-side validation
    if (!seoSettings.metaKeywords.trim()) {
      toast.error(t('Meta Keywords is required'));
      return;
    }
    if (!seoSettings.metaDescription.trim()) {
      toast.error(t('Meta Description is required'));
      return;
    }
    if (!seoSettings.metaImage.trim() && !imageFile) {
      toast.error(t('Meta Image is required'));
      return;
    }
    // Prepare form data
    const formData = {
      metaKeywords: seoSettings.metaKeywords,
      metaDescription: seoSettings.metaDescription,
      metaImage: imageFile || seoSettings.metaImage
    };
    // Submit to backend using Inertia
    router.post(route('settings.seo.update'), formData, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: (page) => {
            setImageFile(null);
            setImagePreview(null);
            toast.dismiss();
            const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
        },
      onError: (errors) => {
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update SEO settings');
        toast.error(errorMessage);
      }
    });
  };
  // Get current preview image
  const getPreviewImage = () => {
    if (imagePreview) return imagePreview;

    const baseUrl = pageProps.globalSettings?.base_url || window.location.origin;

    if (seoSettings.metaImage) {
      // If path starts with /storage, use it with base URL
      if (seoSettings.metaImage.startsWith('/storage')) {
        return `${baseUrl}${seoSettings.metaImage}`;
      }
      return getImagePath(seoSettings.metaImage);
    }
    return `${baseUrl}${defaultSettings.metaImage}`;
  };
  return (
    <SettingsSection
      title={t("SEO Settings")}
      description={t("Configure SEO settings to improve your website's search engine visibility")}
      action={
        <Button type="submit" form="seo-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <Card>
        <CardContent className='p-6 space-y-4'>
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-8">
        {/* Form Section */}
        <div className="lg:col-span-3">
          <form id="seo-settings-form" onSubmit={submitSeoSettings} className="space-y-6">
            <div className="grid grid-cols-1 gap-6">
          <div className="grid gap-2">
            <Label htmlFor="metaKeywords">{t("Meta Keywords")} <span className="text-red-500">*</span></Label>
            <Input
              id="metaKeywords"
              type="text"
              value={seoSettings.metaKeywords}
              onChange={(e) => handleSeoSettingsChange('metaKeywords', e.target.value)}
              placeholder={t("Enter keywords separated by commas")}
              required
            />
            <p className="text-xs text-muted-foreground">
              {t("Use relevant keywords that describe your content. Separate multiple keywords with commas.")}
            </p>
          </div>
          <div className="grid gap-2">
            <Label htmlFor="metaDescription">{t("Meta Description")} <span className="text-red-500">*</span></Label>
            <Textarea
              id="metaDescription"
              value={seoSettings.metaDescription}
              onChange={(e) => handleSeoSettingsChange('metaDescription', e.target.value)}
              placeholder={t("Enter a brief description for search engines (max 160 characters)")}
              maxLength={160}
              rows={4}
              required
            />
            <div className="text-sm text-muted-foreground text-right">
              {seoSettings.metaDescription.length}/160
            </div>
            <p className="text-xs text-muted-foreground">
              {t("Write a compelling description that summarizes your page content and encourages clicks from search results.")}
            </p>
          </div>
          <div className="grid gap-2">
            <Label htmlFor="metaImage">{t("Meta Image")} <span className="text-red-500">*</span></Label>
            <div className="space-y-4">
              {/* File Upload */}
              <div className="flex gap-3">
                <div className="flex-1">
                  <div className="relative">
                    <Input
                      id="metaImageUpload"
                      type="file"
                      accept="image/*"
                      className="absolute inset-0 opacity-0 cursor-pointer"
                      onChange={handleImageUpload}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      className="w-full justify-start"
                    >
                      <Upload className="h-4 w-4 mr-2" />
                      {seoSettings.metaImage || imagePreview ? t("Change Image") : t("Upload Image")}
                    </Button>
                  </div>
                </div>
                {(seoSettings.metaImage || imagePreview) && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={removeImage}
                    className="px-3"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                )}
              </div>
              <p className="text-xs text-muted-foreground">
                {t("Recommended size: 1200x630px for optimal social media sharing.")}
              </p>
            </div>
          </div>
        </div>
      </form>
        </div>
        {/* SEO Preview Section */}
        <div className="lg:col-span-2 space-y-4">
          <div className="border rounded-lg p-6 bg-muted/30 h-fit">
            <h3 className="text-base font-semibold mb-4 flex items-center gap-2">
              <Search className="h-4 w-4" />
              {t("SEO Preview")}
            </h3>
            {/* Google Search Result Preview */}
            <div className="bg-white border rounded-lg p-4 space-y-3">
              <div className="space-y-3">
                <p className="text-sm font-medium text-gray-700">{t("Social Media Preview")}</p>
                {getPreviewImage() && (
                  <div className="w-full h-32 bg-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                    <img
                      src={getPreviewImage()}
                      alt="Preview"
                      className="w-full h-full object-contain"
                      onError={(e) => {
                        e.currentTarget.style.display = 'none';
                      }}
                    />
                  </div>
                )}
                <div className="space-y-2">
                  <div className="text-gray-600 text-sm leading-relaxed">
                    {seoSettings.metaDescription || t('Your meta description will appear here.')}
                  </div>
                </div>
              </div>
            </div>
            {/* SEO Tips */}
            <div className="mt-4 border rounded-lg p-3 bg-blue-50/50">
              <h4 className="text-xs font-semibold mb-2 flex items-center gap-2 text-blue-700">
                <Lightbulb className="h-3 w-3" />
                {t("SEO Tips")}
              </h4>
              <div className="space-y-1 text-xs text-blue-600">
                {/* <div className="flex items-start gap-2">
                  <span className="text-blue-400 mt-0.5">•</span>
                  <span><strong>{t('Title')}:</strong> {t('50-60 characters optimal')}</span>
                </div> */}
                <div className="flex items-start gap-2">
                  <span className="text-blue-400 mt-0.5">•</span>
                  <span><strong>{t('Keywords')}:</strong> {t('Use 3-5 relevant keywords')}</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-blue-400 mt-0.5">•</span>
                  <span><strong>{t('Description')}:</strong> {t('150-160 characters')}</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-blue-400 mt-0.5">•</span>
                  <span><strong>{t('Image')}:</strong> {t('1200x630px works well')}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      </CardContent>
      </Card>
    </SettingsSection>
  );
}
