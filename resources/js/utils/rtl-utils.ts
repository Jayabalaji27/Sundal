// Global RTL utility for consistent RTL handling across all pages
export const applyRTLFromCookies = (globalSettings?: { is_demo?: boolean; layoutDirection?: string }) => {
  if (typeof document === 'undefined') return;
  
  // Check if current language is RTL (Arabic or Hebrew)
  const currentLang = (window as any).i18next?.language || document.documentElement.lang || 'en';
  const isLanguageRTL = ['ar', 'he'].includes(currentLang);
  
  // If language is RTL, always use RTL direction regardless of layout settings
  if (isLanguageRTL) {
    const applyDir = () => {
      document.documentElement.dir = 'rtl';
      document.documentElement.setAttribute('dir', 'rtl');
      document.body.dir = 'rtl';
      document.body.setAttribute('dir', 'rtl');
      document.documentElement.classList.add('rtl');
      document.body.classList.add('rtl');
    };
    
    applyDir();
    setTimeout(applyDir, 0);
    setTimeout(applyDir, 10);
    setTimeout(applyDir, 100);
    return;
  }
  
  const isDemo = globalSettings?.is_demo || 
                 (window as any).page?.props?.globalSettings?.is_demo || 
                 (window as any).page?.props?.is_demo || 
                 (window as any).appSettings?.isDemoMode || 
                 (window as any).isDemo || false;
  
  let storedPosition = 'left';
  
  if (isDemo) {
    // In demo mode, use cookies
    const getCookie = (name: string): string | null => {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      if (parts.length === 2) {
        const cookieValue = parts.pop()?.split(';').shift();
        return cookieValue ? decodeURIComponent(cookieValue) : null;
      }
      return null;
    };
    const stored = getCookie('layoutPosition');
    if (stored === 'left' || stored === 'right') {
      storedPosition = stored;
    }
  } else {
    // In normal mode, get from database via globalSettings
    const stored = globalSettings?.layoutDirection;
    if (stored === 'left' || stored === 'right') {
      storedPosition = stored;
    }
  }
  
  const dir = storedPosition === 'right' ? 'rtl' : 'ltr';
  
  // Apply RTL immediately and forcefully
  const applyDir = () => {
    document.documentElement.dir = dir;
    document.documentElement.setAttribute('dir', dir);
    document.body.dir = dir;
    document.body.setAttribute('dir', dir);
    
    // Add RTL class for additional styling support
    if (dir === 'rtl') {
      document.documentElement.classList.add('rtl');
      document.body.classList.add('rtl');
    } else {
      document.documentElement.classList.remove('rtl');
      document.body.classList.remove('rtl');
    }
  };
  
  // Apply immediately
  applyDir();
  
  // Apply again after a short delay to ensure it sticks
  setTimeout(applyDir, 0);
  setTimeout(applyDir, 10);
  setTimeout(applyDir, 100);
};

// Initialize RTL on page load
export const initializeRTL = () => {
  if (typeof document === 'undefined') return;
  
  // Check if current language is RTL (Arabic or Hebrew)
  const currentLang = document.documentElement.lang || 'en';
  const isLanguageRTL = ['ar', 'he'].includes(currentLang);
  
  // If language is RTL, always use RTL direction
  if (isLanguageRTL) {
    document.documentElement.dir = 'rtl';
    document.documentElement.setAttribute('dir', 'rtl');
    document.body.dir = 'rtl';
    document.body.setAttribute('dir', 'rtl');
    document.documentElement.classList.add('rtl');
    document.body.classList.add('rtl');
    return;
  }
  
  const isDemo = (window as any).page?.props?.globalSettings?.is_demo || 
                 (window as any).page?.props?.is_demo || 
                 (window as any).appSettings?.isDemoMode || 
                 (window as any).isDemo || false;
  
  if (isDemo) {
    const getCookie = (name: string): string | null => {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      if (parts.length === 2) {
        const cookieValue = parts.pop()?.split(';').shift();
        return cookieValue ? decodeURIComponent(cookieValue) : null;
      }
      return null;
    };
    
    const stored = getCookie('layoutPosition');
    if (stored === 'right') {
      const dir = 'rtl';
      document.documentElement.dir = dir;
      document.documentElement.setAttribute('dir', dir);
      document.body.dir = dir;
      document.body.setAttribute('dir', dir);
      document.documentElement.classList.add('rtl');
      document.body.classList.add('rtl');
    }
  }
};

// Auto-initialize when script loads
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRTL);
  } else {
    initializeRTL();
  }
}