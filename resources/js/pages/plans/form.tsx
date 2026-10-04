import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';


interface Plan {
  id: number;
  name: string;
  plan_type?: 'base' | 'addon';
  price: number;
  yearly_price: number | null;
  duration: string;
  description: string | null;
  business: number;
  max_users: number;
  workspace_limit: number;
  member_limit: number;
  clients_limit: number;
  manager_limit: number;
  max_projects_per_workspace: number;
  storage_limit: number;
  enable_chatgpt: string;
  themes: string[] | null;
  is_trial: string | null;
  trial_day: number;
  is_plan_enable: string;
  is_default: boolean;
}

interface Props {
  plan?: Plan;
  hasDefaultPlan?: boolean;
  otherDefaultPlanExists?: boolean;
}

export default function PlanForm({ plan, hasDefaultPlan = false, otherDefaultPlanExists = false }: Props) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const isEdit = !!plan;

  const [formData, setFormData] = useState({
    name: plan?.name || '',
    plan_type: plan?.plan_type || 'base',
    price: plan?.price || 0,
    yearly_price: plan?.yearly_price || undefined,
    duration: plan?.duration || 'monthly',
    description: plan?.description || '',
    max_users_per_workspace: plan?.max_users_per_workspace || 10,
    max_clients_per_workspace: plan?.max_clients_per_workspace || 5,
    max_managers_per_workspace: plan?.max_managers_per_workspace || 2,
    max_projects_per_workspace: plan?.max_projects_per_workspace || 10,
    workspace_limit: plan?.workspace_limit || 1,
    storage_limit: plan?.storage_limit || 0,
    enable_chatgpt: plan?.enable_chatgpt || 'off',
    is_trial: plan?.is_trial || null,
    trial_day: plan?.trial_day || 0,
    is_plan_enable: plan?.is_plan_enable || 'on',
    is_default: plan?.is_default || false,
  });

  const isAddon = formData.plan_type === 'addon';

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSwitchChange = (name: string, checked: boolean) => {
    setFormData(prev => ({ ...prev, [name]: checked ? 'on' : 'off' }));
  };

  const handleDefaultChange = (checked: boolean) => {
    setFormData(prev => ({ ...prev, is_default: checked }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    if (isEdit) {
      router.put(route('plans.update', plan.id), formData, {
        onFinish: () => setProcessing(false),
        onError: (errors) => {
          setProcessing(false);
          setErrors(errors);
        }
      });
    } else {
      router.post(route('plans.store'), formData, {
        onFinish: () => setProcessing(false),
        onError: (errors) => {
          setProcessing(false);
          setErrors(errors);
        }
      });
    }
  };

  return (
    <PageTemplate
      title={t(isEdit ? "Edit Plan" : "Create Plan")}
      description={t(isEdit ? "Update subscription plan details" : "Add a new subscription plan")}
      url={isEdit ? route('plans.update', plan.id) : "/plans/create"}
      actions={[
        {
          label: t('Back'),
          icon: <ArrowLeft className="h-4 w-4" />,
          variant: 'outline',
          onClick: () => router.get(route('plans.index')),
        }
      ]}
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Plans'), href: route('plans.index') },
        { title: t(isEdit ? 'Edit Plan' : 'Create Plan') }
      ]}
    >
      <div className="bg-white rounded-lg shadow p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <div>
                <Label htmlFor="name">{t("Plan Name")} <span className="text-red-500">*</span></Label>
                <Input
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  placeholder={t("e.g. Basic, Pro, Enterprise")}
                  className={errors.name ? 'border-red-500' : ''}
                />
                {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
              </div>

              <div>
                <Label htmlFor="plan_type">{t("Plan Type")} <span className="text-red-500">*</span></Label>
                {isEdit ? (
                  <p className="text-sm mt-1">
                    {formData.plan_type === 'addon' ? t('Add-on') : t('Base Plan')}
                    <span className="text-xs text-muted-foreground ml-2">{t("(cannot be changed after creation)")}</span>
                  </p>
                ) : (
                  <select
                    id="plan_type"
                    name="plan_type"
                    value={formData.plan_type}
                    onChange={(e) => setFormData(prev => ({ ...prev, plan_type: e.target.value as 'base' | 'addon' }))}
                    className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                  >
                    <option value="base">{t('Base Plan')}</option>
                    <option value="addon">{t('Add-on (Pro bundle)')}</option>
                  </select>
                )}
                {isAddon && (
                  <p className="text-xs text-muted-foreground mt-1">
                    {t("Add-ons are purchased on top of a base plan and unlock AI, Knowledge Base, Chat & Meetings.")}
                  </p>
                )}
              </div>

              <div>
                <Label htmlFor="price">{t("Monthly Price")} <span className="text-red-500">*</span></Label>
                <Input
                  id="price"
                  name="price"
                  type="number"
                  step="0.01"
                  placeholder='e.g. 99'
                  value={formData.price}
                  onChange={handleChange}
                  className={errors.price ? 'border-red-500' : ''}
                />
                {errors.price && <p className="text-sm text-red-600 mt-1">{errors.price}</p>}
              </div>

              <div>
                <Label htmlFor="yearly_price">{t("Yearly Price")} <span className="text-sm text-muted-foreground">({t("Optional")})</span></Label>
                <Input
                  id="yearly_price"
                  name="yearly_price"
                  type="number"
                  step="0.01"
                  value={formData.yearly_price || ''}
                  onChange={handleChange}
                  placeholder={t("Leave empty for 20% discount")}
                />
                <p className="text-xs text-muted-foreground mt-1">
                  {t("If left empty, yearly price will be calculated as 80% of monthly price × 12")}
                </p>
              </div>

              <div>
                <Label htmlFor="trial_day">{t("Trial Days")}</Label>
                <Input
                  id="trial_day"
                  name="trial_day"
                  type="number"
                  placeholder='e.g. 14'
                  value={formData.trial_day}
                  onChange={handleChange}
                  disabled={formData.is_trial !== 'on'}
                />
                {formData.is_trial !== 'on' && (
                  <p className="text-xs text-muted-foreground mt-1">{t("Turn on \"Enable Trial\" to set trial days.")}</p>
                )}
              </div>

              <div>
                <Label htmlFor="description">{t("Description")}</Label>
                <Textarea
                  id="description"
                  name="description"
                  value={formData.description}
                  onChange={handleChange}
                  rows={3}
                  placeholder={t("Enter plan description...")}
                />
              </div>
            </div>

            <div className="space-y-4">
              {isAddon ? (
                <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                  {t("Add-on plans don't have per-workspace limits — they grant a fixed module bundle:")}
                  <div className="font-medium text-foreground mt-1">{t("AI, Knowledge Base, Chat & Meetings")}</div>
                </div>
              ) : (
                <>
                  <div>
                    <Label htmlFor="max_users_per_workspace">{t("Maximum Users Per Workspace")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="max_users_per_workspace"
                      name="max_users_per_workspace"
                      type="number"
                      min="0"
                      placeholder='e.g. 10'
                      value={formData.max_users_per_workspace}
                      onChange={handleChange}
                      className={errors.max_users_per_workspace ? 'border-red-500' : ''}
                    />
                    <p className="text-xs text-muted-foreground mt-1">{t("0 = unlimited")}</p>
                    {errors.max_users_per_workspace && <p className="text-sm text-red-600 mt-1">{errors.max_users_per_workspace}</p>}
                  </div>

                  <div>
                    <Label htmlFor="max_clients_per_workspace">{t("Maximum Clients Per Workspace")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="max_clients_per_workspace"
                      name="max_clients_per_workspace"
                      type="number"
                      min="0"
                      placeholder='e.g. 5'
                      value={formData.max_clients_per_workspace}
                      onChange={handleChange}
                      className={errors.max_clients_per_workspace ? 'border-red-500' : ''}
                    />
                    <p className="text-xs text-muted-foreground mt-1">{t("0 = unlimited")}</p>
                    {errors.max_clients_per_workspace && <p className="text-sm text-red-600 mt-1">{errors.max_clients_per_workspace}</p>}
                  </div>

                  <div>
                    <Label htmlFor="max_managers_per_workspace">{t("Maximum Managers Per Workspace")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="max_managers_per_workspace"
                      name="max_managers_per_workspace"
                      type="number"
                      min="1"
                      placeholder='e.g. 2'
                      value={formData.max_managers_per_workspace}
                      onChange={handleChange}
                      className={errors.max_managers_per_workspace ? 'border-red-500' : ''}
                    />
                    {errors.max_managers_per_workspace && <p className="text-sm text-red-600 mt-1">{errors.max_managers_per_workspace}</p>}
                  </div>

                  <div>
                    <Label htmlFor="max_projects_per_workspace">{t("Maximum Projects Per Workspace")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="max_projects_per_workspace"
                      name="max_projects_per_workspace"
                      type="number"
                      min="1"
                      placeholder='e.g. 10'
                      value={formData.max_projects_per_workspace}
                      onChange={handleChange}
                      className={errors.max_projects_per_workspace ? 'border-red-500' : ''}
                    />
                    {errors.max_projects_per_workspace && <p className="text-sm text-red-600 mt-1">{errors.max_projects_per_workspace}</p>}
                  </div>

                  <div>
                    <Label htmlFor="workspace_limit">{t("Workspace Limit")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="workspace_limit"
                      name="workspace_limit"
                      type="number"
                      min="1"
                      placeholder='e.g. 1'
                      value={formData.workspace_limit}
                      onChange={handleChange}
                      className={errors.workspace_limit ? 'border-red-500' : ''}
                    />
                    {errors.workspace_limit && <p className="text-sm text-red-600 mt-1">{errors.workspace_limit}</p>}
                  </div>

                  <div>
                    <Label htmlFor="storage_limit">{t("Storage Limit (GB)")} <span className="text-red-500">*</span></Label>
                    <Input
                      id="storage_limit"
                      name="storage_limit"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder='e.g. 10'
                      value={formData.storage_limit}
                      onChange={handleChange}
                      className={errors.storage_limit ? 'border-red-500' : ''}
                    />
                    <p className="text-xs text-muted-foreground mt-1">{t("0 = unlimited")}</p>
                    {errors.storage_limit && <p className="text-sm text-red-600 mt-1">{errors.storage_limit}</p>}
                  </div>
                </>
              )}
            </div>
          </div>

          <div className="border rounded-lg p-4 space-y-4">
            <h3 className="font-medium">{t("Features")}</h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="flex items-center justify-between">
                <Label htmlFor="enable_chatgpt">{t("AI Integration")}</Label>
                <Switch
                  id="enable_chatgpt"
                  checked={formData.enable_chatgpt === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('enable_chatgpt', checked)}
                />
              </div>

              {!isAddon && (
                <div className="flex items-center justify-between">
                  <Label htmlFor="is_trial">{t("Enable Trial")}</Label>
                  <Switch
                    id="is_trial"
                    checked={formData.is_trial === 'on'}
                    onCheckedChange={(checked) => handleSwitchChange('is_trial', checked)}
                  />
                </div>
              )}
            </div>
          </div>

          <div className="border rounded-lg p-4 space-y-4">
            <h3 className="font-medium">{t("Settings")}</h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="flex items-center justify-between">
                <Label htmlFor="is_plan_enable">{t("Active")}</Label>
                <Switch
                  id="is_plan_enable"
                  checked={formData.is_plan_enable === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('is_plan_enable', checked)}
                />
              </div>

              {!isAddon && (
                <div className="flex items-center justify-between">
                  <div>
                    <Label htmlFor="is_default">{t("Default Plan")}</Label>
                    {(isEdit ? !plan?.is_default : hasDefaultPlan) && (
                      <p className="text-xs text-amber-600 mt-1">
                        {t("Setting this as default will remove default status from the current default plan.")}
                      </p>
                    )}
                  </div>
                  <Switch
                    id="is_default"
                    checked={formData.is_default}
                    onCheckedChange={handleDefaultChange}
                  />
                </div>
              )}
            </div>
          </div>

          <div className="flex justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.get(route('plans.index'))}
            >
              {t("Cancel")}
            </Button>
            <Button
              type="submit"
            >
              {t(isEdit ? "Update" : "Create")}
            </Button>
          </div>
        </form>
      </div>
    </PageTemplate>
  );
}
