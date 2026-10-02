import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { getBrandSettings, type BrandSettings } from '@/pages/settings/components/brand-settings';
import { initializeTheme } from '@/hooks/use-appearance';

interface BrandContextType extends BrandSettings {
  updateBrandSettings: (settings: Partial<BrandSettings>) => void;
}

const BrandContext = createContext<BrandContextType | undefined>(undefined);

export function BrandProvider({ children, globalSettings, user }: { children: ReactNode; globalSettings?: any; user?: any }) {
  // Check if in demo mode
  const isDemoMode = globalSettings?.is_demo || 
                     (window as any).page?.props?.is_demo || 
                     (window as any).appSettings?.isDemoMode || 
                     (window as any).isDemo || false;
  
  // Determine which settings to use based on user role and route
  const getEffectiveSettings = () => {
    const isPublicRoute = window.location.pathname.includes('/public/') || 
                         window.location.pathname === '/' || 
                         window.location.pathname.includes('/auth/');
    
    // For public routes (landing page, auth pages), always use superadmin settings
    if (isPublicRoute) {
      return globalSettings;
    }
    
    // For authenticated routes, use user's own settings if company role
    if (user?.role === 'company' && user?.globalSettings) {
      return user.globalSettings;
    }
    
    // Default to global settings (superadmin)
    return globalSettings;
  };
  
  const [brandSettings, setBrandSettings] = useState<BrandSettings>(() => {
    const effectiveSettings = getEffectiveSettings();
    const settings = getBrandSettings(effectiveSettings, isDemoMode);
    return settings;
  });



  // Listen for changes in settings
  useEffect(() => {
    const effectiveSettings = getEffectiveSettings();
    const updatedSettings = getBrandSettings(effectiveSettings, isDemoMode);
    setBrandSettings(updatedSettings);
  }, [globalSettings, user, isDemoMode]);

  const updateBrandSettings = (newSettings: Partial<BrandSettings>) => {
    setBrandSettings(prev => ({ ...prev, ...newSettings }));
  };

  return (
    <BrandContext.Provider value={{ ...brandSettings, updateBrandSettings }}>
      {children}
    </BrandContext.Provider>
  );
}

export function useBrand() {
  const context = useContext(BrandContext);
  if (context === undefined) {
    throw new Error('useBrand must be used within a BrandProvider');
  }
  return context;
}