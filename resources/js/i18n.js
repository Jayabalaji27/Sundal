// resources/js/i18n.js
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import Backend from 'i18next-http-backend';
import LanguageDetector from 'i18next-browser-languagedetector';

// Make i18n instance available for direct imports
export { default as i18next } from 'i18next';

// Custom backend to handle the modified response format
const customBackend = {
  type: 'backend',
  init: function(services, backendOptions) {
    this.services = services;
    this.options = backendOptions;
  },
  read: function(language, namespace, callback) {
    // Sanitize language — must be a valid locale code, never HTML or garbage
    const safeLanguage = /^[a-z]{2,8}(-[A-Za-z]{2,8})?$/.test(language) ? language : 'en';
    const baseUrl = (window.baseUrl || '').replace(/\/$/, '');
    const loadPath = window.route
        ? window.route('translations', safeLanguage)
        : `${baseUrl}/translations/${safeLanguage}`;

    fetch(loadPath)
      .then(response => {
        if (!response.ok) throw new Error(`Translation fetch failed: HTTP ${response.status} for ${loadPath}`);
        return response.json();
      })
      .then(data => {
        // Extract translations from the structured response
        const translations = data.translations;
        
        // Set document direction based on layoutDirection
        if (data.layoutDirection) {
          // Force direction change regardless of previous state
          document.documentElement.dir = data.layoutDirection;
          document.documentElement.setAttribute('dir', data.layoutDirection);
          
          // Store direction in localStorage for persistence
          localStorage.setItem('layoutDirection', data.layoutDirection);
          
          // Force re-render of sidebar by adding and removing a class
          document.documentElement.classList.add('direction-changed');
          setTimeout(() => {
            document.documentElement.classList.remove('direction-changed');
          }, 50); // Increased timeout for better rendering
        }
        
        // Store the current locale
        if (data.locale) {
          localStorage.setItem('i18nextLng', data.locale);
          // Also store in a session cookie for server-side awareness
          document.cookie = `app_language=${data.locale}; path=/; max-age=${60 * 60 * 24}`;
        }
        
        callback(null, translations);
      })
      .catch(error => {
        console.error('Translation loading error:', error);
        callback(error, null);
      });
  }
};

// Function to get initial language
const getInitialLanguage = () => {
  // Try to get from server if available
  if (window.initialLocale) {
    return window.initialLocale;
  }
  
  // Otherwise use browser detection with fallback to 'en'
  return null; // null will trigger language detection
};

// Function to reset language cache when switching languages
const resetLanguageCache = (language) => {
  // Clear any cached translations for better switching
  if (window.localStorage) {
    Object.keys(localStorage).forEach(key => {
      if (key.startsWith('i18next_res_')) {
        localStorage.removeItem(key);
      }
    });
  }
};

// Override the changeLanguage method to reset cache
const originalChangeLanguage = i18n.changeLanguage;
i18n.changeLanguage = function(language) {
  resetLanguageCache(language);
  return originalChangeLanguage.apply(this, arguments);
};

// Initialize i18n
i18n
    .use(customBackend)
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        lng: getInitialLanguage(),
        fallbackLng: getInitialLanguage(),
        load: 'currentOnly',
        debug: process.env.NODE_ENV === 'development',
        
        interpolation: {
            escapeValue: false,
        },
        
        detection: {
          order: ['localStorage', 'cookie', 'navigator'],
          lookupCookie: 'app_language',
          caches: ['localStorage', 'cookie'],
        },
        
        ns: ['translation'],
        defaultNS: 'translation',
        
        partialBundledLanguages: true,
        loadOnInitialization: true,
        react: {
            useSuspense: false,
        },
    });

// Export the initialized instance
export default i18n;

// Make sure the i18n instance is available for direct imports
window.i18next = i18n;