import { usePage } from '@inertiajs/react';

// Add window type declaration
declare global {
  interface Window {
    location: Location;
  }
}

/**
 * Get page props helper
 */
export const usePageProps = () => {
  const { props } = usePage();
  return props as any;
};

/**
 * Check if user registration is enabled
 */
export const isRegistrationEnabled = (props?: any): boolean => {
  try {
    const pageProps = props || (typeof window !== 'undefined' && (window as any).page?.props);
    const settings = pageProps?.globalSettings || {};
    const registrationEnabled = settings.registrationEnabled;
    if (registrationEnabled === undefined || registrationEnabled === null) {
      return true; // No setting saved yet - default to enabled, matching the PHP-side default
    }
    return registrationEnabled === true || registrationEnabled === '1';
  } catch {
    return true; // Default to enabled
  }
};

/**
 * Get image path with proper base URL
 */
export const getImagePath = (path: string, pageProps?: any): string => {
  if (!path) return '';
  if (path.startsWith('http')) return path;
  
  // Get base URL
  const getBaseUrl = () => {
    try {
      const props = pageProps || usePage().props;
      const baseUrl = (props as any).globalSettings?.base_url;
      if (baseUrl) return baseUrl;
    } catch {}
    return window.location.origin;
  };
  
  const baseUrl = getBaseUrl();
  
  // If path already contains storage/media, just prepend base URL
  if (path.includes('storage/media')) {
    return path.startsWith('/') ? `${baseUrl}${path}` : `${baseUrl}/${path}`;
  }

  try {
    const props = pageProps || usePage().props;
    const dynamicPath = `${baseUrl}/storage/media/`;
    let imageUrlPrefix = (props as any).imageUrlPrefix || dynamicPath;

    if (!imageUrlPrefix.includes('storage/media')) {
      imageUrlPrefix = imageUrlPrefix.endsWith('/') ? imageUrlPrefix + 'storage/media/' : imageUrlPrefix + '/storage/media/';
    }

    // Handle slash concatenation
    const prefixEndsWithSlash = imageUrlPrefix.endsWith('/');
    const pathStartsWithSlash = path.startsWith('/');

    if (prefixEndsWithSlash && pathStartsWithSlash) {
      return imageUrlPrefix + path.substring(1);
    } else if (!prefixEndsWithSlash && !pathStartsWithSlash) {
      return imageUrlPrefix + '/' + path;
    } else {
      return imageUrlPrefix + path;
    }
  }
  catch {
    const fallbackPrefix = `${baseUrl}/storage/media/`;
    return path.startsWith('/') ? fallbackPrefix + path.substring(1) : fallbackPrefix + path;
  }
};
