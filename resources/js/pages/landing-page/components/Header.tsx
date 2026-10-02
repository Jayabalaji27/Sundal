import React, { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { getImagePath, isRegistrationEnabled, usePageProps } from '@/utils/helpers';

// Sections on the home page that the header nav scroll-links to and
// highlights via scroll-spy. Keys must match the `id` given to each
// section wrapper in landing-page/index.tsx.
const SECTION_LINKS = [
  { key: 'features', label: 'Features' },
  { key: 'plans', label: 'Pricing' },
  { key: 'faq', label: 'FAQ' },
  { key: 'contact', label: 'Contact' },
];

interface CustomPage {
  id: number;
  title: string;
  slug: string;
}

interface HeaderProps {
  brandColor?: string;
  settings: {
    company_name: string;
  };
  sectionData?: any;
  customPages?: CustomPage[];
  logoLight?: string;
  logoDark?: string;
}

export default function Header({ settings, sectionData, customPages = [], brandColor = '#3b82f6', logoLight, logoDark }: HeaderProps) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isDarkMode, setIsDarkMode] = useState(false);
  const [activeSection, setActiveSection] = useState<string | null>(null);
  const { t } = useTranslation();
  const pageProps = usePageProps();
  const isHomePage = typeof window !== 'undefined' && window.location.pathname === '/';

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };

    // Check dark mode
    const checkDarkMode = () => {
      setIsDarkMode(document.documentElement.classList.contains('dark'));
    };

    // Initial check
    checkDarkMode();

    // Watch for dark mode changes
    const observer = new MutationObserver(checkDarkMode);
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class']
    });

    window.addEventListener('scroll', handleScroll);

    return () => {
      window.removeEventListener('scroll', handleScroll);
      observer.disconnect();
    };
  }, []);

  // Scroll-spy: highlight the nav link for whichever landing-page section is
  // currently in view. Only the home page renders these sections, so this
  // is a no-op (nothing to observe) on custom pages.
  useEffect(() => {
    if (!isHomePage) return;

    const sectionElements = SECTION_LINKS
      .map(link => document.getElementById(link.key))
      .filter((el): el is HTMLElement => el !== null);

    if (sectionElements.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            setActiveSection(entry.target.id);
          }
        });
      },
      { rootMargin: '-40% 0px -55% 0px', threshold: 0 }
    );

    sectionElements.forEach(el => observer.observe(el));

    // If we arrived with a #hash (e.g. navigated from a custom page), scroll
    // to that section once on mount.
    if (window.location.hash) {
      const target = document.getElementById(window.location.hash.slice(1));
      target?.scrollIntoView({ behavior: 'smooth' });
    }

    return () => observer.disconnect();
  }, [isHomePage]);

  const scrollToSection = (e: React.MouseEvent, key: string) => {
    if (!isHomePage) {
      // Not on the home page - let Inertia navigate there with the hash;
      // the scroll-spy effect above picks it up on mount.
      return;
    }
    const target = document.getElementById(key);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
      setIsMenuOpen(false);
    }
  };

  const menuItems = customPages.map(page => ({
    name: page.title,
    href: route('custom-page.show', page.slug)
  }));

  const isTransparent = sectionData?.transparent;
  const backgroundColor = sectionData?.background_color || '#ffffff';
  
  const getHeaderClasses = () => {
    if (isTransparent) {
      return isScrolled 
        ? 'bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl shadow-lg border-b border-gray-200/50 dark:border-gray-700/50'
        : 'bg-transparent';
    }
    return isScrolled 
      ? 'shadow-lg border-b border-gray-200/50 dark:border-gray-700/50 bg-white dark:bg-gray-900'
      : 'bg-white dark:bg-gray-900';
  };

  const getHeaderStyle = () => {
    if (isTransparent) return {};
    return { backgroundColor };
  };

  return (
    <header 
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${getHeaderClasses()}`}
      style={getHeaderStyle()}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <div className="flex-shrink-0">
            <Link 
              href={route("home")} 
              className="flex items-center gap-2"
            >
              {/* Priority: 1. Section data logo, 2. Super Admin/Owner logo, 3. Company name */}
              {(sectionData?.logo_light || sectionData?.logo_dark) ? (
                <img 
                  src={getImagePath(sectionData?.logo_light || sectionData?.logo_dark)} 
                  alt={settings.company_name}
                  className="h-8 w-auto"
                />
              ) : (logoLight || logoDark) ? (
                <img 
                  src={isDarkMode ? logoLight : logoDark} 
                  alt={settings.company_name}
                  className="h-8 w-auto"
                />
              ) : (
                <span 
                  className="text-2xl font-bold text-gray-900 dark:text-white transition-colors"
                  onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
                  onMouseLeave={(e) => e.currentTarget.style.color = ''}
                >
                  {settings.company_name || 'Taskly'}
                </span>
              )}
            </Link>
          </div>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center space-x-8" role="navigation" aria-label="Main navigation">
            <Link
              href={route('home')}
              className="text-gray-600 dark:text-gray-300 text-sm font-medium transition-colors relative group"
              style={{ '--hover-color': brandColor } as React.CSSProperties}
              onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
              onMouseLeave={(e) => e.currentTarget.style.color = ''}
            >
                {t('Home')}
              <span
                className="absolute -bottom-1 left-0 w-0 h-0.5 transition-all group-hover:w-full"
                style={{ backgroundColor: brandColor }}
                aria-hidden="true"
              ></span>
            </Link>
            {SECTION_LINKS.map((link) => (
              <Link
                key={link.key}
                href={`${route('home')}#${link.key}`}
                onClick={(e) => scrollToSection(e, link.key)}
                className="text-sm font-medium transition-colors relative group"
                style={{ color: activeSection === link.key ? brandColor : undefined }}
                onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
                onMouseLeave={(e) => e.currentTarget.style.color = activeSection === link.key ? brandColor : ''}
              >
                <span className={activeSection === link.key ? '' : 'text-gray-600 dark:text-gray-300'}>
                  {t(link.label)}
                </span>
                <span
                  className={`absolute -bottom-1 left-0 h-0.5 transition-all group-hover:w-full ${activeSection === link.key ? 'w-full' : 'w-0'}`}
                  style={{ backgroundColor: brandColor }}
                  aria-hidden="true"
                ></span>
              </Link>
            ))}
            {menuItems.map((item) => (
              <Link
                key={item.name}
                href={item.href}
                className="text-gray-600 dark:text-gray-300 text-sm font-medium transition-colors relative group"
                style={{ '--hover-color': brandColor } as React.CSSProperties}
                onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
                onMouseLeave={(e) => e.currentTarget.style.color = ''}
              >
                {item.name}
                <span 
                  className="absolute -bottom-1 left-0 w-0 h-0.5 transition-all group-hover:w-full" 
                  style={{ backgroundColor: brandColor }}
                  aria-hidden="true"
                ></span>
              </Link>
            ))}
          </nav>

          {/* Auth Buttons */}
          <div className="hidden md:flex items-center gap-4">
            {pageProps.auth?.user ? (
              <Link
                href={route('dashboard')}
                className="px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors border"
                style={{ 
                  backgroundColor: brandColor, 
                  color: 'white',
                  borderColor: brandColor
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = 'white';
                  e.currentTarget.style.color = brandColor;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = brandColor;
                  e.currentTarget.style.color = 'white';
                }}
              >
                {t('Dashboard')}
              </Link>
            ) : (
              <>
                <Link
                  href={route('login')}
                  className="text-gray-600 dark:text-gray-300 text-sm font-medium transition-colors"
                  onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
                  onMouseLeave={(e) => e.currentTarget.style.color = ''}
                >
                  {t('Login')}
                </Link>
                {(pageProps.isSaas && isRegistrationEnabled()) && (
                  <Link
                    href={route('register')}
                    className="px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors border"
                    style={{ 
                      backgroundColor: brandColor, 
                      color: 'white',
                      borderColor: brandColor
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = 'white';
                      e.currentTarget.style.color = brandColor;
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = brandColor;
                      e.currentTarget.style.color = 'white';
                    }}
                  >
                  {t('Get Started')}
                  </Link>
                )}
              </>
            )}
          </div>

          {/* Mobile menu button */}
          <div className="md:hidden">
            <button
              onClick={() => setIsMenuOpen(!isMenuOpen)}
              className="p-2 text-gray-600 dark:text-gray-300 cursor-pointer hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
              aria-label={isMenuOpen ? 'Close navigation menu' : 'Open navigation menu'}
              aria-expanded={isMenuOpen}
              aria-controls="mobile-menu"
            >
              {isMenuOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>

        {/* Mobile Navigation */}
        {isMenuOpen && (
          <div className="md:hidden border-t border-gray-200 dark:border-gray-700" id="mobile-menu">
            <div 
              className="px-4 py-6 space-y-4 bg-white dark:bg-gray-900"
              style={isTransparent ? {} : { backgroundColor }}
            >
              <Link
                href={route('home')}
                className="block text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white text-base font-medium transition-colors"
                onClick={() => setIsMenuOpen(false)}
              >
                {t('Home')}
              </Link>
              {SECTION_LINKS.map((link) => (
                <Link
                  key={link.key}
                  href={`${route('home')}#${link.key}`}
                  onClick={(e) => scrollToSection(e, link.key)}
                  className="block text-base font-medium transition-colors"
                  style={{ color: activeSection === link.key ? brandColor : undefined }}
                >
                  <span className={activeSection === link.key ? '' : 'text-gray-600 dark:text-gray-300'}>
                    {t(link.label)}
                  </span>
                </Link>
              ))}
              {menuItems.map((item) => (
                <Link
                  key={item.name}
                  href={item.href}
                  className="block text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white text-base font-medium transition-colors"
                  onClick={() => setIsMenuOpen(false)}
                >
                  {item.name}
                </Link>
              ))}
              <div className="pt-4 space-y-3 border-t border-gray-200 dark:border-gray-700">
                {pageProps.auth?.user ? (
                  <Link
                    href={route('dashboard')}
                    className="block w-full text-center py-2.5 rounded-lg text-sm font-semibold transition-colors border"
                    style={{ 
                      backgroundColor: brandColor, 
                      color: 'white',
                      borderColor: brandColor
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = 'white';
                      e.currentTarget.style.color = brandColor;
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = brandColor;
                      e.currentTarget.style.color = 'white';
                    }}
                  >
                    {t('Dashboard')}
                  </Link>
                ) : (
                  <>
                    <Link
                      href={route('login')}
                      className="block w-full text-center text-gray-600 dark:text-gray-300 py-2.5 text-sm font-medium transition-colors"
                      onMouseEnter={(e) => e.currentTarget.style.color = brandColor}
                      onMouseLeave={(e) => e.currentTarget.style.color = ''}
                    >
                      {t('Login')}
                    </Link>
                    {(pageProps.isSaas && isRegistrationEnabled()) && (
                      <Link
                        href={route('register')}
                        className="block w-full text-center py-2.5 rounded-lg text-sm font-semibold transition-colors border"
                        style={{ 
                          backgroundColor: brandColor, 
                          color: 'white',
                          borderColor: brandColor
                        }}
                        onMouseEnter={(e) => {
                          e.currentTarget.style.backgroundColor = 'white';
                          e.currentTarget.style.color = brandColor;
                        }}
                        onMouseLeave={(e) => {
                          e.currentTarget.style.backgroundColor = brandColor;
                          e.currentTarget.style.color = 'white';
                        }}
                      >
                        {t('Get Started')}
                      </Link>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </header>
  );
}