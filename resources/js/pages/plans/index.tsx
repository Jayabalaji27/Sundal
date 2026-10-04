import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardFooter } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { router, usePage } from '@inertiajs/react';
import {
  CheckCircle2,
  X,
  Pencil,
  Trash2,
  Globe,
  FileText,
  Bot,
  BarChart2,
  Mail,
  Box,
  Store,
  Users,
  HardDrive,
  Plus,
  Sparkles,
  Info,
  Crown,
  Zap,
  Clock,
  Banknote,
  CreditCard,
  IndianRupee,
  Wallet,
  Coins,
  Edit
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useForm } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { PlanSubscriptionModal } from '@/components/plan-subscription-modal';

interface Plan {
  id: number;
  name: string;
  plan_type?: 'base' | 'addon';
  price: string | number;
  yearly_price?: string | number;
  formatted_price?: string;
  duration: string;
  description: string;
  trial_days: number;
  features: string[];
  limits: {
    workspaces: number;
    users_per_workspace: number;
    clients_per_workspace: number;
    managers_per_workspace: number;
    projects_per_workspace: number;
    storage: string;
  };
  status: boolean;
  recommended?: boolean;
  is_default?: boolean;
  is_current?: boolean;
  is_trial_available?: boolean;
  has_pending_request?: boolean;
  is_ai_enabled?: boolean;
  sort_order?: number;
  yearly_savings_percent?: number;
}

interface AddonPlan {
  id: number;
  name: string;
  price: string | number;
  yearly_price?: string | number;
  formatted_price?: string;
  duration: string;
  description: string;
  is_current?: boolean;
  has_pending_request?: boolean;
}

interface Props {
  plans: Plan[];
  billingCycle: 'monthly' | 'yearly';
  hasDefaultPlan?: boolean;
  isAdmin?: boolean;
  currentPlan?: any;
  userTrialUsed?: boolean;
  paymentMethods?: any[];
  trialExpired?: boolean;
  addonPlans?: AddonPlan[];
  currentAddon?: any;
  hasActiveAddon?: boolean;
}

// Stable default: a fresh `[]` each render re-ran the effect that copies addon
// plans into state, so admin pages (no addonPlans prop) re-rendered forever.
const NO_ADDON_PLANS: AddonPlan[] = [];

export default function Plans({ plans: initialPlans, billingCycle: initialBillingCycle = 'monthly', hasDefaultPlan, isAdmin = false, currentPlan, userTrialUsed, paymentMethods = [], trialExpired = false, addonPlans: initialAddonPlans = NO_ADDON_PLANS, hasActiveAddon = false }: Props) {
  const { t } = useTranslation();
  // const { flash } = usePage().props as any;
  const { flash, globalSettings } = usePage().props as any;
  const { post, processing } = useForm();
  const [plans, setPlans] = useState<Plan[]>(initialPlans);
  const [addonPlans, setAddonPlans] = useState<AddonPlan[]>(initialAddonPlans);
  const [isAddonSubscriptionModalOpen, setIsAddonSubscriptionModalOpen] = useState(false);
  const [selectedAddon, setSelectedAddon] = useState<AddonPlan | null>(null);
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'yearly'>(initialBillingCycle);
  // Real per-plan yearly savings (server-computed) - no hard-coded percentage
  const maxYearlySavings = Math.max(0, ...(initialPlans || []).map((plan: Plan) => plan.yearly_savings_percent ?? 0));
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [planToDelete, setPlanToDelete] = useState<Plan | null>(null);
  const [isSubscriptionModalOpen, setIsSubscriptionModalOpen] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);

  const formatCurrency = (amount: string | number) => {
    if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
      const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
      return window.appSettings.formatCurrency(numericAmount);
    }
    return amount || 0;
  };

  // Update plans when initialPlans changes
  useEffect(() => {
    setPlans(initialPlans);
  }, [initialPlans]);

  useEffect(() => {
    setAddonPlans(initialAddonPlans);
  }, [initialAddonPlans]);

  // Show flash messages
  useEffect(() => {
    if (flash?.error) {
      toast.error(flash.error);
    }
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (trialExpired) {
      toast.error(t('Your trial has expired. Please subscribe to continue using the service.'));
    }
  }, [flash, trialExpired]);

  // Function to handle billing cycle change
  const handleBillingCycleChange = (value: 'monthly' | 'yearly') => {
    setBillingCycle(value);
    router.get(route('plans.index'), { billing_cycle: value }, { preserveState: true, preserveScroll: true });
  };

  // Company plan actions
  const handlePlanRequest = (planId: number) => {
    router.post(route('plans.request'), {
      plan_id: planId,
      billing_cycle: billingCycle
    }, {
      onSuccess: () => {
        // Update local state to show cancel button
        setPlans(plans.map(plan =>
          plan.id === planId ? { ...plan, has_pending_request: true } : plan
        ));
      },
      onError: (errors) => {
        if (errors.error) {
          toast.error(errors.error);
        }
      }
    });
  };

  const handleCancelRequest = (planId: number) => {
    router.post(route('plans.cancel-request'), {
      plan_id: planId
    }, {
      onSuccess: () => {
        // Update local state to show request button
        setPlans(plans.map(plan =>
          plan.id === planId ? { ...plan, has_pending_request: false } : plan
        ));
      },
      onError: (errors) => {
        if (errors.error) {
          toast.error(errors.error);
        }
      }
    });
  };

  const handleStartTrial = (planId: number) => {
    if (userTrialUsed) {
      toast.error(t('You have already used your free trial. Please subscribe to continue.'));
      return;
    }

    // No local patching on success: the response's plans (synced into state by
    // the initialPlans effect) already mark the trial plan as current. Patching
    // from this closure's stale list left the old plan marked "Current" too.
    router.post(route('plans.trial'), {
      plan_id: planId
    }, {
      onError: (errors) => {
        if (errors.error) {
          toast.error(errors.error);
        }
      }
    });
  };

  const handleAddonRequest = (planId: number) => {
    router.post(route('plans.request'), {
      plan_id: planId,
      billing_cycle: billingCycle
    }, {
      onSuccess: () => {
        setAddonPlans(addonPlans.map(plan =>
          plan.id === planId ? { ...plan, has_pending_request: true } : plan
        ));
      },
      onError: (errors) => {
        if (errors.error) toast.error(errors.error);
      }
    });
  };

  const handleAddonCancelRequest = (planId: number) => {
    router.post(route('plans.cancel-request'), {
      plan_id: planId
    }, {
      onSuccess: () => {
        setAddonPlans(addonPlans.map(plan =>
          plan.id === planId ? { ...plan, has_pending_request: false } : plan
        ));
      },
      onError: (errors) => {
        if (errors.error) toast.error(errors.error);
      }
    });
  };

  const handleAddonSubscribe = (planId: number) => {
    const plan = addonPlans.find(p => p.id === planId);
    if (plan) {
      const originalPlan = initialAddonPlans.find(p => p.id === planId);
      setSelectedAddon({
        ...plan,
        yearly_price: originalPlan?.yearly_price || plan.price,
        paymentMethods
      });
      setIsAddonSubscriptionModalOpen(true);
    }
  };

  const handleSubscribe = async (planId: number) => {
    const plan = plans.find(p => p.id === planId);
    if (plan) {
      // Get the original plan data from initialPlans to ensure we have yearly_price
      const originalPlan = initialPlans.find(p => p.id === planId);
      setSelectedPlan({
        ...plan,
        yearly_price: originalPlan?.yearly_price || plan.price,
        paymentMethods: paymentMethods
      });
      setIsSubscriptionModalOpen(true);
    }
  };

  const formatPaymentMethods = (paymentSettings: any) => {
    const methods = [];

    if (paymentSettings?.is_bank_enabled === true || paymentSettings?.is_bank_enabled === '1') {
      methods.push({
        id: 'bank',
        name: t('Bank Transfer'),
        icon: <Banknote className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_stripe_enabled === true || paymentSettings?.is_stripe_enabled === '1') {
      methods.push({
        id: 'stripe',
        name: t('Stripe'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_paypal_enabled === true || paymentSettings?.is_paypal_enabled === '1') {
      methods.push({
        id: 'paypal',
        name: t('PayPal'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_razorpay_enabled === true || paymentSettings?.is_razorpay_enabled === '1') {
      methods.push({
        id: 'razorpay',
        name: t('Razorpay'),
        icon: <IndianRupee className="h-5 w-5" />,
        enabled: true
      });
    }

    if ((paymentSettings?.is_mercadopago_enabled === true || paymentSettings?.is_mercadopago_enabled === '1') && paymentSettings?.mercadopago_access_token) {
      methods.push({
        id: 'mercadopago',
        name: t('MercadoPago'),
        icon: <Wallet className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_paystack_enabled === true || paymentSettings?.is_paystack_enabled === '1') {
      methods.push({
        id: 'paystack',
        name: t('Paystack'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_flutterwave_enabled === true || paymentSettings?.is_flutterwave_enabled === '1') {
      methods.push({
        id: 'flutterwave',
        name: t('Flutterwave'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_paytabs_enabled === true || paymentSettings?.is_paytabs_enabled === '1') {
      methods.push({
        id: 'paytabs',
        name: t('PayTabs'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_skrill_enabled === true || paymentSettings?.is_skrill_enabled === '1') {
      methods.push({
        id: 'skrill',
        name: t('Skrill'),
        icon: <Wallet className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_coingate_enabled === true || paymentSettings?.is_coingate_enabled === '1') {
      methods.push({
        id: 'coingate',
        name: t('CoinGate'),
        icon: <Coins className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_payfast_enabled === true || paymentSettings?.is_payfast_enabled === '1') {
      methods.push({
        id: 'payfast',
        name: t('Payfast'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_tap_enabled === true || paymentSettings?.is_tap_enabled === '1') {
      methods.push({
        id: 'tap',
        name: t('Tap'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_xendit_enabled === true || paymentSettings?.is_xendit_enabled === '1') {
      methods.push({
        id: 'xendit',
        name: t('Xendit'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_paytr_enabled === true || paymentSettings?.is_paytr_enabled === '1') {
      methods.push({
        id: 'paytr',
        name: t('PayTR'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_mollie_enabled === true || paymentSettings?.is_mollie_enabled === '1') {
      methods.push({
        id: 'mollie',
        name: t('Mollie'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_toyyibpay_enabled === true || paymentSettings?.is_toyyibpay_enabled === '1') {
      methods.push({
        id: 'toyyibpay',
        name: t('toyyibPay'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_cashfree_enabled === true || paymentSettings?.is_cashfree_enabled === '1') {
      methods.push({
        id: 'cashfree',
        name: t('Cashfree'),
        icon: <IndianRupee className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_khalti_enabled === true || paymentSettings?.is_khalti_enabled === '1') {
      methods.push({
        id: 'khalti',
        name: t('Khalti'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_iyzipay_enabled === true || paymentSettings?.is_iyzipay_enabled === '1') {
      methods.push({
        id: 'iyzipay',
        name: t('Iyzipay'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_benefit_enabled === true || paymentSettings?.is_benefit_enabled === '1') {
      methods.push({
        id: 'benefit',
        name: t('Benefit'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_ozow_enabled === true || paymentSettings?.is_ozow_enabled === '1') {
      methods.push({
        id: 'ozow',
        name: t('Ozow'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_easebuzz_enabled === true || paymentSettings?.is_easebuzz_enabled === '1') {
      methods.push({
        id: 'easebuzz',
        name: t('Easebuzz'),
        icon: <IndianRupee className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_authorizenet_enabled === true || paymentSettings?.is_authorizenet_enabled === '1') {
      methods.push({
        id: 'authorizenet',
        name: t('AuthorizeNet'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_fedapay_enabled === true || paymentSettings?.is_fedapay_enabled === '1') {
      methods.push({
        id: 'fedapay',
        name: t('FedaPay'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_payhere_enabled === true || paymentSettings?.is_payhere_enabled === '1') {
      methods.push({
        id: 'payhere',
        name: t('PayHere'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_cinetpay_enabled === true || paymentSettings?.is_cinetpay_enabled === '1') {
      methods.push({
        id: 'cinetpay',
        name: t('CinetPay'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_paiement_enabled === true || paymentSettings?.is_paiement_enabled === '1') {
      methods.push({
        id: 'paiement',
        name: t('Paiement Pro'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_nepalste_enabled === true || paymentSettings?.is_nepalste_enabled === '1') {
      methods.push({
        id: 'nepalste',
        name: t('Nepalste'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_yookassa_enabled === true || paymentSettings?.is_yookassa_enabled === '1') {
      methods.push({
        id: 'yookassa',
        name: t('YooKassa'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_aamarpay_enabled === true || paymentSettings?.is_aamarpay_enabled === '1') {
      methods.push({
        id: 'aamarpay',
        name: t('Aamarpay'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    if (paymentSettings?.is_midtrans_enabled === true || paymentSettings?.is_midtrans_enabled === '1') {
      methods.push({
        id: 'midtrans',
        name: t('Midtrans'),
        icon: <CreditCard className="h-5 w-5" />,
        enabled: true
      });
    }

    return methods;
  };

  const getActionButton = (plan: Plan) => {
    // Check if user has active subscription to this plan
    if (currentPlan && currentPlan.id === plan.id && currentPlan.expires_at && new Date(currentPlan.expires_at) > new Date()) {
      return (
        <Button disabled className="w-full h-12 bg-green-50 text-green-700 border border-green-200 font-medium">
          <Crown className="h-4 w-4 mr-2" />
          {t('Already Subscribed')}
        </Button>
      );
    }

    if (plan.is_current) {
      return (
        <Button disabled className="w-full h-12 bg-primary/10 text-primary border border-primary/20 font-medium">
          <Crown className="h-4 w-4 mr-2" />
          {t('Current Plan')}
        </Button>
      );
    }

    if (plan.is_trial_available && !userTrialUsed && !trialExpired) {
      return (
        <div className="space-y-3">
          <Button
            onClick={() => handleStartTrial(plan.id)}
            disabled={processing}
            variant="outline"
            className="w-full h-12 border-primary text-primary hover:bg-primary/5 font-medium"
          >
            <Zap className="h-4 w-4 mr-2" />
            {t('Start {{days}} Day Trial', { days: plan.trial_days })}
          </Button>
          <Button
            onClick={() => handleSubscribe(plan.id)}
            disabled={processing}
            className="w-full h-12 bg-primary hover:bg-primary/90 text-white font-medium"
          >
            {t('Subscribe Now')}
          </Button>
        </div>
      );
    }

    return (
      <div className="space-y-3">
        {plan.has_pending_request ? (
          <Button
            onClick={() => handleCancelRequest(plan.id)}
            disabled={processing}
            variant="outline"
            className="w-full h-12 border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 font-medium"
          >
            <X className="h-4 w-4 mr-2" />
            {t('Cancel Request')}
          </Button>
        ) : (
          <Button
            onClick={() => handlePlanRequest(plan.id)}
            disabled={processing}
            variant="outline"
            className="w-full h-12 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 font-medium"
          >
            <Clock className="h-4 w-4 mr-2" />
            {t('Request Plan')}
          </Button>
        )}
        <Button
          onClick={() => handleSubscribe(plan.id)}
          disabled={processing || (currentPlan && currentPlan.id === plan.id && currentPlan.expires_at && new Date(currentPlan.expires_at) > new Date())}
          className="w-full h-12 bg-primary hover:bg-primary/90 text-white font-medium"
        >
          {currentPlan && currentPlan.id === plan.id && currentPlan.expires_at && new Date(currentPlan.expires_at) > new Date()
            ? t('Already Subscribed')
            : t('Subscribe Now')
          }
        </Button>
      </div>
    );
  };

  // Function to get the appropriate icon for a feature
  const getFeatureIcon = (feature: string) => {
    switch (feature) {
      case 'Custom Domain':
        return <Globe className="h-4 w-4" />;
      case 'Subdomain':
        return <Globe className="h-4 w-4" />;
      case 'PWA':
        return <FileText className="h-4 w-4" />;
      case 'Blog Module':
        return <FileText className="h-4 w-4" />;
      case 'AI Integration':
        return <Bot className="h-4 w-4" />;
      case 'Analytics':
        return <BarChart2 className="h-4 w-4" />;
      case 'Email Support':
        return <Mail className="h-4 w-4" />;
      case 'API Access':
        return <Box className="h-4 w-4" />;
      case 'Priority Support':
        return <Users className="h-4 w-4" />;
      case 'Storage':
        return <HardDrive className="h-4 w-4" />;
      default:
        return <CheckCircle2 className="h-4 w-4" />;
    }
  };

  // Function to check if a feature is included in the plan
  const isFeatureIncluded = (plan: Plan, feature: string) => {
    return plan.features.includes(feature);
  };

  // Function to toggle plan status
  const togglePlanStatus = (planId: number) => {
    // Send request to toggle plan status
    router.post(route('plans.toggle-status', planId), {}, {
      preserveState: true,
      onSuccess: () => {
        // Update local state
        setPlans(plans.map(plan =>
          plan.id === planId ? { ...plan, status: !plan.status } : plan
        ));
      }
    });
  };

  // Function to handle delete
  const handleDelete = (plan: Plan) => {
    setPlanToDelete(plan);
    setIsDeleteModalOpen(true);
  };

  // Function to handle delete confirmation
  const handleDeleteConfirm = () => {
    if (planToDelete) {
      router.delete(route('plans.destroy', planToDelete.id), {
        onSuccess: () => {
          setIsDeleteModalOpen(false);
          setPlanToDelete(null);
        }
      });
    }
  };

  // Common features to display for all plans
  const commonFeatures = [
    'AI Integration'
  ];

  // Define limit icons


  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Plans') }
  ];

  return (
    <PageTemplate
      title={t("Plans")}
      description={t("Manage subscription plans for your customers")}
      url="/plans"
      breadcrumbs={breadcrumbs}
    >
      <div className="space-y-8">
        {/* Header with controls */}
        <div className="flex flex-col items-center text-center mb-12">
          <div className="max-w-3xl mx-auto mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">
              {isAdmin ? t("Subscription Plans") : t("Choose Your Plan")}
            </h1>
            <p className="text-lg text-gray-600 dark:text-gray-400">
              {isAdmin
                ? t("Create and manage subscription plans to offer different service tiers to your customers.")
                : t("Select the perfect plan for your business needs and start growing today.")
              }
            </p>
          </div>

          <div className="flex flex-col sm:flex-row items-center gap-4">
            {/* Billing Cycle Toggle */}
            <div className="bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
              <Tabs
                value={billingCycle}
                onValueChange={(v) => handleBillingCycleChange(v as 'monthly' | 'yearly')}
                className="w-full"
              >
                <TabsList className="grid w-full grid-cols-2 bg-transparent p-0 h-auto">
                  <TabsTrigger
                    value="monthly"
                    className="px-6 py-2 text-sm font-medium data-[state=active]:bg-white data-[state=active]:dark:bg-gray-900 data-[state=active]:text-gray-900 data-[state=active]:dark:text-gray-100 data-[state=active]:shadow-sm rounded-md"
                  >
                    {t("Monthly")}
                  </TabsTrigger>
                  <TabsTrigger
                    value="yearly"
                    className="px-6 py-2 text-sm font-medium data-[state=active]:bg-white data-[state=active]:dark:bg-gray-900 data-[state=active]:text-gray-900 data-[state=active]:dark:text-gray-100 data-[state=active]:shadow-sm rounded-md relative"
                  >
                    {t("Yearly")}
                    {maxYearlySavings > 0 && (
                      <span className="ml-2 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                        {t("Save up to {{percent}}%", { percent: maxYearlySavings })}
                      </span>
                    )}
                  </TabsTrigger>
                </TabsList>
              </Tabs>
            </div>

            {/* Add Plan Button - Admin only */}
            {isAdmin && (
              <Button
                className="bg-primary hover:bg-primary/90 text-white px-6 py-2 h-10 font-medium"
                onClick={() => router.get(route('plans.create'))}
              >
                <Plus className="h-4 w-4 mr-2" />
                {t("Add Plan")}
              </Button>
            )}
          </div>
        </div>

        {/* Plans grid - grouped so AI-enabled plans form a clearly labeled
            trailing section instead of being interleaved with standard plans
            in whatever order they happen to be stored (backend now orders by
            admin-controlled sort_order, but AI vs. standard grouping still
            needs to be explicit here for it to read as intentional). */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
          {[...plans]
            .sort((a, b) => (a.is_ai_enabled === b.is_ai_enabled ? 0 : a.is_ai_enabled ? 1 : -1))
            .map((plan, index, sortedPlans) => (
          <React.Fragment key={plan.id}>
            {plan.is_ai_enabled && (index === 0 || !sortedPlans[index - 1].is_ai_enabled) && (
              <div className="col-span-full flex items-center gap-2 mt-2 mb-1">
                <Sparkles className="h-5 w-5 text-primary" />
                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{t('AI-Powered Plans')}</h3>
              </div>
            )}
            <div
              className={`relative h-full transition-all duration-200 ${(!isAdmin && plan.recommended) ? 'transform scale-105 z-10' : ''
                }`}
            >
              {/* Main Card */}
              <div className={`
                relative h-full flex flex-col rounded-lg border-2 transition-all duration-200
                ${(!isAdmin && plan.recommended)
                  ? 'border-primary shadow-xl bg-white dark:bg-gray-900'
                  : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-lg'
                }
                ${isAdmin && !plan.status && !globalSettings?.is_demo ? 'grayscale opacity-60' : ''}
              `}>
                {/* Recommended Badge */}
                {!isAdmin && plan.recommended && (
                  <div className="absolute -top-4 left-1/2 transform -translate-x-1/2 z-10">
                    <div className="bg-primary text-white px-4 py-1 rounded-full text-sm font-semibold">
                      {t("Recommended")}
                    </div>
                  </div>
                )}

                {/* Status Indicators */}
                <div className="absolute top-4 right-4 z-10 flex flex-col gap-1">
                  {isAdmin && (
                    <>
                      {plan.plan_type === 'addon' && (
                        <span className="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20">
                          {t("Add-on")}
                        </span>
                      )}
                      {plan.is_default && (
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                          {t("Default")}
                        </span>
                      )}
                      <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${plan.status
                          ? 'bg-green-50 text-green-700 ring-green-600/20'
                          : 'bg-red-50 text-red-700 ring-red-600/20'
                        }`}>
                        {plan.status ? t("Active") : t("Inactive")}
                      </span>
                    </>
                  )}
                  {!isAdmin && plan.is_current && (
                    <span className="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                      <Crown className="h-3 w-3 mr-1" />
                      {t("Current")}
                    </span>
                  )}
                </div>

                {/* Card Header */}
                <div className="p-6 text-center border-b border-gray-100">
                  <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                    {plan.name}
                  </h3>

                  {/* Pricing */}
                  <div className="mb-4">
                    <div className="flex items-center justify-center">
                      <span className="text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {formatCurrency(plan.price)}
                      </span>
                      <span className="text-gray-500 dark:text-gray-400 ml-1">
                        /{t(plan.duration.toLowerCase())}
                      </span>
                    </div>
                    {billingCycle === 'yearly' && (plan.yearly_savings_percent ?? 0) > 0 && (
                      <span className="mt-2 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                        {t("Save {{percent}}%", { percent: plan.yearly_savings_percent })}
                      </span>
                    )}
                  </div>

                  {/* Description */}
                  <p className="text-gray-600 dark:text-gray-400 text-sm mb-4">
                    {plan.description}
                  </p>

                  {/* Trial Badge */}
                  {plan.trial_days > 0 && (
                    <div className="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm font-medium">
                      <Zap className="h-3 w-3" />
                      {t("{{days}} days free trial", { days: plan.trial_days })}
                    </div>
                  )}
                </div>

                {/* Card Content */}
                <div className="flex flex-col flex-1 p-6">
                  {/* Usage limits */}
                  <div className="mb-6">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 uppercase tracking-wide">
                      {t("What's Included")}
                    </h4>
                    <div className="space-y-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Store className="h-4 w-4 text-blue-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Workspaces")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.workspaces}</span>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Users className="h-4 w-4 text-emerald-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Users/Workspace")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.users_per_workspace || t('Unlimited')}</span>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Users className="h-4 w-4 text-amber-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Clients/Workspace")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.clients_per_workspace || t('Unlimited')}</span>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Users className="h-4 w-4 text-purple-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Managers/Workspace")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.managers_per_workspace}</span>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <FileText className="h-4 w-4 text-indigo-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Projects/Workspace")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.projects_per_workspace}</span>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <HardDrive className="h-4 w-4 text-teal-500" />
                          <span className="text-sm text-gray-700 dark:text-gray-300">{t("Storage")}</span>
                        </div>
                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{plan.limits.storage}</span>
                      </div>
                    </div>
                  </div>

                  {/* Features */}
                  <div className="mb-6 flex-1">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 uppercase tracking-wide">
                      {t("Features")}
                    </h4>
                    <ul className="space-y-2">
                      {commonFeatures.map((feature, index) => {
                        const included = isFeatureIncluded(plan, feature);
                        return (
                          <li key={index} className="flex items-center gap-2">
                            <div className={`flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center ${included
                                ? 'bg-green-100 text-green-600'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-400'
                              }`}>
                              {included ? (
                                <CheckCircle2 className="h-3 w-3" />
                              ) : (
                                <X className="h-3 w-3" />
                              )}
                            </div>
                            <span className={`text-sm ${included ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400'}`}>
                              {t(feature)}
                            </span>
                          </li>
                        );
                      })}
                    </ul>
                  </div>

                  {/* Actions */}
                  <div className="mt-auto">
                    {isAdmin ? (
                      <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div className="flex items-center gap-2">
                          <Switch
                            checked={plan.status}
                            onCheckedChange={() => togglePlanStatus(plan.id)}
                            className={plan.status ? "data-[state=checked]:bg-primary" : ""}
                          />
                          <span className="text-sm text-gray-700 dark:text-gray-300">
                            {plan.status ? t("Active") : t("Inactive")}
                          </span>
                        </div>
                        <div className="flex items-center gap-1">
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="text-amber-500 hover:text-amber-700"
                                onClick={() => router.get(route('plans.edit', plan.id))}
                              >
                                <Edit className="h-4 w-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t('Edit')}</TooltipContent>
                          </Tooltip>

                          {!plan.is_default && (
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  className="text-red-500 hover:text-red-700"
                                  onClick={() => handleDelete(plan)}
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </TooltipTrigger>
                              <TooltipContent>{t('Delete')}</TooltipContent>
                            </Tooltip>
                          )}
                        </div>
                      </div>
                    ) : (
                      getActionButton(plan)
                    )}
                  </div>
                </div>
              </div>
            </div>
          </React.Fragment>
            ))}
        </div>

        {/* Add-ons section — Company only */}
        {!isAdmin && addonPlans.length > 0 && (
          <div className="max-w-6xl mx-auto">
            <div className="text-center mb-8">
              <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">{t("Pro Add-on")}</h2>
              <p className="text-gray-600 dark:text-gray-400">
                {t("Unlock AI, Knowledge Base, Chat & Meetings on top of your current plan.")}
              </p>
              {hasActiveAddon && (
                <span className="inline-flex items-center gap-1 mt-3 rounded-md bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                  <Crown className="h-3 w-3" />
                  {t("Add-on Active")}
                </span>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {addonPlans.map((addon) => (
                <div key={addon.id} className="rounded-lg border-2 border-purple-200 dark:border-purple-800/50 bg-white dark:bg-gray-900 p-6 flex flex-col">
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">{addon.name}</h3>
                    {addon.is_current && (
                      <span className="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                        <Crown className="h-3 w-3 mr-1" />
                        {t("Current")}
                      </span>
                    )}
                  </div>
                  <div className="mb-3">
                    <span className="text-3xl font-bold text-gray-900 dark:text-gray-100">{formatCurrency(addon.price)}</span>
                    <span className="text-gray-500 dark:text-gray-400 ml-1">/{t(addon.duration.toLowerCase())}</span>
                  </div>
                  <p className="text-gray-600 dark:text-gray-400 text-sm mb-4 flex-1">{addon.description}</p>
                  {addon.is_current ? (
                    <Button disabled className="w-full h-11 bg-primary/10 text-primary border border-primary/20 font-medium">
                      <Crown className="h-4 w-4 mr-2" />
                      {t('Current Add-on')}
                    </Button>
                  ) : (
                    <div className="space-y-2">
                      {addon.has_pending_request ? (
                        <Button
                          onClick={() => handleAddonCancelRequest(addon.id)}
                          variant="outline"
                          className="w-full h-11 border-red-200 text-red-600 hover:bg-red-50"
                        >
                          <X className="h-4 w-4 mr-2" />
                          {t('Cancel Request')}
                        </Button>
                      ) : (
                        <Button
                          onClick={() => handleAddonRequest(addon.id)}
                          variant="outline"
                          className="w-full h-11 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                          <Clock className="h-4 w-4 mr-2" />
                          {t('Request Add-on')}
                        </Button>
                      )}
                      <Button
                        onClick={() => handleAddonSubscribe(addon.id)}
                        className="w-full h-11 bg-primary hover:bg-primary/90 text-white font-medium"
                      >
                        {t('Subscribe Now')}
                      </Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Add-on Subscription Modal — Company only */}
        {!isAdmin && selectedAddon && (
          <PlanSubscriptionModal
            isOpen={isAddonSubscriptionModalOpen}
            onClose={() => {
              setIsAddonSubscriptionModalOpen(false);
              setSelectedAddon(null);
            }}
            plan={selectedAddon}
            billingCycle={billingCycle}
            paymentMethods={formatPaymentMethods(selectedAddon.paymentMethods)}
          />
        )}

        {/* Delete Modal - Admin only */}
        {isAdmin && (
          <CrudDeleteModal
            isOpen={isDeleteModalOpen}
            onClose={() => setIsDeleteModalOpen(false)}
            onConfirm={handleDeleteConfirm}
            itemName={planToDelete?.name || ''}
            entityName="plan"
          />
        )}

        {/* Subscription Modal - Company only */}
        {!isAdmin && selectedPlan && (
          <PlanSubscriptionModal
            isOpen={isSubscriptionModalOpen}
            onClose={() => {
              setIsSubscriptionModalOpen(false);
              setSelectedPlan(null);
            }}
            plan={selectedPlan}
            billingCycle={billingCycle}
            paymentMethods={formatPaymentMethods(selectedPlan.paymentMethods)}
          />
        )}
      </div>
    </PageTemplate>
  );
}