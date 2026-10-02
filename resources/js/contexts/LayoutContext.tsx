import { createContext, ReactNode, useContext, useEffect, useState } from 'react';

export type LayoutPosition = 'left' | 'right';

type LayoutContextType = {
    position: LayoutPosition;
    effectivePosition: LayoutPosition;
    updatePosition: (val: LayoutPosition) => void;
    saveLayoutPosition: () => void;
    isRtl: boolean;
};

const LayoutContext = createContext<LayoutContextType | undefined>(undefined);



const getCookieValue = (name: string): string | null => {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) {
    const cookieValue = parts.pop()?.split(';').shift();
    return cookieValue ? decodeURIComponent(cookieValue) : null;
  }
  return null;
};

const setCookieValue = (name: string, value: string, days = 365) => {
  if (typeof document === 'undefined') return;
  const maxAge = days * 24 * 60 * 60;
  document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

export const LayoutProvider = ({ children }: { children: ReactNode }) => {
    const [position, setPosition] = useState<LayoutPosition>('left');
    const [isRtl, setIsRtl] = useState<boolean>(false);

    useEffect(() => {
        // Check if current language is RTL (Arabic or Hebrew)
        const currentLang = (window as any).i18next?.language || document.documentElement.lang || 'en';
        const isLanguageRTL = ['ar', 'he'].includes(currentLang);
        
        // If language is RTL, force RTL layout regardless of user settings
        if (isLanguageRTL) {
            setPosition('right'); // Force right position for RTL languages
        } else {
            const isDemo = (window as any).page?.props?.globalSettings?.is_demo || 
                           (window as any).page?.props?.is_demo || 
                           (window as any).appSettings?.isDemoMode || 
                           (window as any).isDemo || false;
            let storedPosition: LayoutPosition | null = null;
            
            if (isDemo) {
                // In demo mode, use cookies
                storedPosition = getCookieValue('layoutPosition') as LayoutPosition;
            } else {
                // In normal mode, get from database via globalSettings
                const globalSettings = (window as any).page?.props?.globalSettings;
                storedPosition = globalSettings?.layoutDirection as LayoutPosition;
            }

            if (storedPosition === 'left' || storedPosition === 'right') {
                setPosition(storedPosition);
            }
        }
        
        // Check if the document is in RTL mode
        const checkRtl = () => {
            const rtl = document.documentElement.dir === 'rtl';
            setIsRtl(rtl);
        };
        
        // Initial check
        checkRtl();
        
        // Set up a mutation observer to detect changes to the dir attribute
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'dir') {
                    checkRtl();
                }
            });
        });
        
        observer.observe(document.documentElement, { attributes: true });
        
        return () => observer.disconnect();
    }, []);
    


    const updatePosition = (val: LayoutPosition) => {
        setPosition(val);
    };
    
    const saveLayoutPosition = () => {
        const isDemo = (window as any).page?.props?.globalSettings?.is_demo || 
                       (window as any).page?.props?.is_demo || 
                       (window as any).appSettings?.isDemoMode || 
                       (window as any).isDemo || false;
        
        if (isDemo) {
            setCookieValue('layoutPosition', position);
        }
    };
    
    // Calculate effective position based on RTL mode
    // For language-based RTL (Arabic/Hebrew), always use right position
    const currentLang = (window as any).i18next?.language || document.documentElement.lang || 'en';
    const isLanguageRTL = ['ar', 'he'].includes(currentLang);
    
    const effectivePosition: LayoutPosition = isLanguageRTL ? 'right' : position;

    return <LayoutContext.Provider value={{ position, effectivePosition, updatePosition, saveLayoutPosition, isRtl }}>{children}</LayoutContext.Provider>;
};

export const useLayout = () => {
    const context = useContext(LayoutContext);
    if (!context) throw new Error('useLayout must be used within LayoutProvider');
    return context;
};
