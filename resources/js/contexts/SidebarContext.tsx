import { createContext, ReactNode, useContext, useEffect, useState } from 'react';

export interface SidebarSettings {
  variant: 'inset' | 'floating' | 'minimal';
  collapsible: 'icon' | 'offcanvas' | 'none';
}

type SidebarContextType = {
  variant: SidebarSettings['variant'];
  collapsible: SidebarSettings['collapsible'];
  style: string;
  updateVariant: (variant: SidebarSettings['variant']) => void;
  updateCollapsible: (collapsible: SidebarSettings['collapsible']) => void;
  updateStyle: (style: string) => void;
  saveSidebarSettings: () => void;
};

const SidebarContext = createContext<SidebarContextType | undefined>(undefined);

// Extended sidebar settings with style
interface ExtendedSidebarSettings extends SidebarSettings {
  style: string;
}

// Default sidebar settings with style
const DEFAULT_EXTENDED_SETTINGS: ExtendedSidebarSettings = {
  variant: 'inset',
  collapsible: 'icon',
  style: 'plain'
};

// Cookie utility functions
const setCookie = (name: string, value: string, days = 30) => {
  if (typeof document === 'undefined') return;
  const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
  document.cookie = `${name}=${value}; expires=${expires}; path=/`;
};

const getCookie = (name: string): string | null => {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop()?.split(';').shift() || '';
  return null;
};

const isDemoMode = () => {
  return (window as any).page?.props?.globalSettings?.is_demo || 
         (window as any).page?.props?.is_demo || 
         (window as any).appSettings?.isDemoMode || 
         (window as any).isDemo || false;
};

// Get extended sidebar settings from localStorage or cookies
const getExtendedSidebarSettings = (): ExtendedSidebarSettings => {
  // Check for demo mode cookie first
  if (isDemoMode()) {
    // Clear localStorage in demo mode
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem('sidebarSettings');
    }
    
    const cookieData = getCookie('sidebarSettings');
    if (cookieData) {
      try {
        return JSON.parse(cookieData);
      } catch (error) {
        console.error('Failed to parse sidebar settings from cookie', error);
      }
    }
    return DEFAULT_EXTENDED_SETTINGS;
  }
  
  if (typeof localStorage === 'undefined') {
    return DEFAULT_EXTENDED_SETTINGS;
  }
  
  try {
    const savedSettings = localStorage.getItem('sidebarSettings');
    return savedSettings ? JSON.parse(savedSettings) : DEFAULT_EXTENDED_SETTINGS;
  } catch (error) {
    return DEFAULT_EXTENDED_SETTINGS;
  }
};

export const SidebarProvider = ({ children }: { children: ReactNode }) => {
  const [settings, setSettings] = useState<ExtendedSidebarSettings>(getExtendedSidebarSettings());

  // Update variant
  const updateVariant = (variant: SidebarSettings['variant']) => {
    setSettings(prev => {
      const newSettings = { ...prev, variant };
      if (isDemoMode()) {
        // Clear localStorage and store in cookies as JSON for demo mode
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem('sidebarSettings');
        }
        setCookie('sidebarSettings', JSON.stringify(newSettings));
      } else {
        localStorage.setItem('sidebarSettings', JSON.stringify(newSettings));
      }
      return newSettings;
    });
  };

  // Update collapsible
  const updateCollapsible = (collapsible: SidebarSettings['collapsible']) => {
    setSettings(prev => {
      const newSettings = { ...prev, collapsible };
      if (isDemoMode()) {
        // Clear localStorage and store in cookies as JSON for demo mode
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem('sidebarSettings');
        }
        setCookie('sidebarSettings', JSON.stringify(newSettings));
      } else {
        localStorage.setItem('sidebarSettings', JSON.stringify(newSettings));
      }
      return newSettings;
    });
  };

  // Update style
  const updateStyle = (style: string) => {
    setSettings(prev => {
      const newSettings = { ...prev, style };
      if (isDemoMode()) {
        // Clear localStorage and store in cookies as JSON for demo mode
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem('sidebarSettings');
        }
        setCookie('sidebarSettings', JSON.stringify(newSettings));
      } else {
        localStorage.setItem('sidebarSettings', JSON.stringify(newSettings));
      }
      return newSettings;
    });
  };

  // Save sidebar settings to cookies (demo mode only)
  const saveSidebarSettings = () => {
    if (isDemoMode()) {
      setCookie('sidebarSettings', JSON.stringify(settings));
    }
  };



  return (
    <SidebarContext.Provider value={{ 
      variant: settings.variant, 
      collapsible: settings.collapsible,
      style: settings.style,
      updateVariant,
      updateCollapsible,
      updateStyle,
      saveSidebarSettings
    }}>
      {children}
    </SidebarContext.Provider>
  );
};

export const useSidebarSettings = () => {
  const context = useContext(SidebarContext);
  if (!context) throw new Error('useSidebarSettings must be used within SidebarProvider');
  return context;
};