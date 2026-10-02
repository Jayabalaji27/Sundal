import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, CreditCard, Circle, Percent } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

interface Plan {
  id: number;
  name: string;
  price: number;
  duration: string;
  description?: string;
  features?: string[];
  is_active?: boolean;
  is_current?: boolean;
}

interface UpgradePlanModalProps {
  isOpen: boolean;
  onSelectDuration?: boolean;
  onClose: () => void;
  onConfirm: (planId: number, duration: string) => void;
  plans: Plan[];
  currentPlanId?: number;
  companyName: string;
}

export function UpgradePlanModal({
  isOpen,
  onClose,
  onConfirm,
  plans,
  currentPlanId,
  companyName
}: UpgradePlanModalProps) {
  const { t } = useTranslation();
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [billingCycle, setBillingCycle] = useState<string>('monthly');
  
  // Initialize with current plan ID and duration
  useEffect(() => {
    if (isOpen && plans && plans.length > 0) {
      // Find the current plan
      const currentPlan = plans.find(plan => plan.is_current === true);
      
      // If there's a current plan, select it
      if (currentPlan) {
        setSelectedPlanId(currentPlan.id);
        setBillingCycle(currentPlan.duration.toLowerCase());
      } else if (currentPlanId) {
        setSelectedPlanId(currentPlanId);
        setBillingCycle('monthly');
      } else {
        setSelectedPlanId(plans[0].id);
        setBillingCycle('monthly');
      }
    }
  }, [isOpen, plans]);
  
  const handleConfirm = () => {
    if (selectedPlanId) {
      onConfirm(selectedPlanId, billingCycle);
    }
  };
  
  const isYearly = billingCycle === 'yearly';

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <DialogTitle className="text-lg font-semibold text-gray-900">{t("Upgrade Plan for Company")}</DialogTitle>
          <DialogDescription className="text-sm text-gray-600">
            {t("Select a new plan for this company")}
          </DialogDescription>
        </DialogHeader>

        {/* Billing Period Toggle */}
        <div className="flex items-center justify-center gap-3 py-2 px-4 bg-gray-50 rounded-lg">
          <span className={cn(
            "text-sm font-medium transition-colors", 
            !isYearly ? "text-primary" : "text-gray-600"
          )}>
            {t("Monthly")}
          </span>
          <Switch 
            checked={isYearly} 
            onCheckedChange={(checked) => setBillingCycle(checked ? 'yearly' : 'monthly')} 
            className="data-[state=checked]:bg-primary"
          />
          <div className="flex items-center space-x-2">
            <span className={cn(
              "text-sm font-medium transition-colors", 
              isYearly ? "text-primary" : "text-gray-600"
            )}>
              {t("Yearly")}
            </span>
            {isYearly && (
              <Badge variant="secondary" className="ml-2 bg-green-100 text-green-700 border-0 text-xs font-medium">
                {t("Save up to 20%")}
              </Badge>
            )}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto py-2 pr-2">
          <RadioGroup 
            value={selectedPlanId?.toString() || ""} 
            onValueChange={(value) => setSelectedPlanId(parseInt(value))}
            className="space-y-2 pr-2"
          >
            {plans.filter(p => p.duration.toLowerCase() === billingCycle).map((plan) => (
              <div
                key={plan.id}
                className={cn(
                  "relative rounded-lg border-2 p-3 cursor-pointer transition-all",
                  selectedPlanId === plan.id 
                    ? "border-primary bg-primary/5" 
                    : "border-gray-200 hover:border-gray-300 bg-white"
                )}
                onClick={() => setSelectedPlanId(plan.id)}
              >
                <div className="flex items-start gap-3">
                  {/* Radio Button */}
                  <div className="flex items-center pt-0.5">
                    <RadioGroupItem 
                      value={plan.id.toString()} 
                      id={`plan-${plan.id}`} 
                      className="h-4 w-4"
                    />
                  </div>
                  
                  {/* Plan Content */}
                  <div className="flex-1 min-w-0">
                    {/* Plan Header */}
                    <div className="flex items-center justify-between mb-1">
                      <div className="flex items-center gap-2">
                        <h3 className="text-base font-semibold text-gray-900 leading-tight">{plan.name}</h3>
                        {plan.is_current && (
                          <Badge variant="secondary" className="text-xs font-medium bg-blue-100 text-blue-700 border-0 py-0 px-2 leading-tight">
                            {t("Current")}
                          </Badge>
                        )}
                      </div>
                    </div>
                    
                    {/* Price */}
                    <div className="flex items-baseline gap-1 mb-1">
                      <CreditCard className="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />
                      <span className="text-base font-bold text-gray-900 leading-tight">
                        {plan.price}
                      </span>
                      <span className="text-sm text-gray-600 leading-tight">/ {t(plan.duration)}</span>
                    </div>

                    {/* Description */}
                    {plan.description && (
                      <p className="text-sm text-gray-600 mb-1.5 leading-snug">{plan.description}</p>
                    )}
                    
                    {/* Feature Tags */}
                    {plan.features && plan.features.length > 0 && (
                      <div className="flex flex-wrap gap-1.5">
                        {plan.features.slice(0, 3).map((feature, index) => (
                          <Badge 
                            key={`${plan.id}-${index}`} 
                            variant="outline" 
                            className="text-xs font-normal bg-gray-50 text-gray-700 border-gray-200 py-0.5 px-2 leading-tight"
                          >
                            <CheckCircle2 className="mr-1 h-3 w-3 text-green-500 flex-shrink-0" />
                            <span className="truncate">{feature}</span>
                          </Badge>
                        ))}
                        {plan.features.length > 3 && (
                          <Badge 
                            variant="outline" 
                            className="text-xs font-normal bg-gray-50 text-gray-600 border-gray-200 py-0.5 px-2 leading-tight"
                          >
                            +{plan.features.length - 3} {t("more")}
                          </Badge>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </RadioGroup>
        </div>
        
        <DialogFooter className="border-t pt-3">
          <Button variant="outline" onClick={onClose} className="text-sm font-medium">
            {t("Cancel")}
          </Button>
          <Button 
            onClick={handleConfirm} 
            disabled={!selectedPlanId}
            className="bg-primary hover:bg-primary/90 text-sm font-medium"
          >
            {t("Upgrade Plan")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}