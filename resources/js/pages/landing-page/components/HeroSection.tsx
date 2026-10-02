import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Play, CheckCircle, Users, Zap, Shield } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface HeroSectionProps {
  brandColor?: string;
  settings: any;
  sectionData: {
    title?: string;
    subtitle?: string;
    announcement_text?: string;
    primary_button_text?: string;
    secondary_button_text?: string;
    image?: string;
    stats?: Array<{value: string; label: string}>;
    card?: {
      name: string;
      title: string;
      company: string;
      initials: string;
    };
  };
}

export default function HeroSection({ settings, sectionData, brandColor = '#3b82f6' }: HeroSectionProps) {
  const { t } = useTranslation();
  // Helper to get full URL for images
  const getImageUrl = (path: string) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `${window.appSettings.imageUrl}${path}`;
  };

  const heroImage = getImageUrl(sectionData.image);

  return (
    <section id="hero" className="relative pt-16 bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen flex items-center overflow-hidden">
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-5">
        <div className="absolute top-20 left-10 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl animate-pulse"></div>
        <div className="absolute top-40 right-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl animate-pulse delay-1000"></div>
        <div className="absolute bottom-20 left-1/2 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl animate-pulse delay-2000"></div>
      </div>
      
      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
        <div className="grid lg:grid-cols-2 gap-8 sm:gap-12 lg:gap-16 items-center">
          {/* Left Content */}
          <div className="text-center lg:text-left space-y-6 sm:space-y-8">
            {sectionData.announcement_text && (
              <div className="inline-flex items-center px-4 py-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-gray-700 dark:text-gray-300 rounded-full text-sm font-medium border border-gray-200 dark:border-gray-700 shadow-sm">
                <Zap size={16} className="mr-2 text-yellow-500" />
                {sectionData.announcement_text}
              </div>
            )}
            
            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 dark:text-white leading-tight" role="banner" aria-label="Main heading">
              {sectionData.title || (
                <>
                  Streamline Your <span className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Team's Workflow</span> with Taskly
                </>
              )}
            </h1>
            
            <p className="text-lg md:text-xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl font-medium">
              {sectionData.subtitle || t('The all-in-one SaaS platform that transforms how teams collaborate, manage projects, and deliver results. Boost productivity by 40% with intelligent task management.')}
            </p>
            
            {/* Key Features */}
            <div className="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-300">
              <div className="flex items-center gap-2">
                <CheckCircle size={16} className="text-green-500" />
                <span>{t('Real-time Collaboration')}</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle size={16} className="text-green-500" />
                <span>{t('Advanced Analytics')}</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle size={16} className="text-green-500" />
                <span>{t('Enterprise Security')}</span>
              </div>
            </div>
            
            <div className="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center lg:justify-start">
              {((usePage().props as any).isSaas) && (
                <a
                  href={route('register')}
                  className="text-white px-8 py-4 rounded-xl transition-all duration-300 font-semibold text-base flex items-center justify-center gap-2 hover:scale-105 hover:shadow-lg transform"
                  style={{ backgroundColor: brandColor, boxShadow: `0 10px 25px ${brandColor}20` }}
                  aria-label="Start free trial - Register"
                >
                  {sectionData.primary_button_text || t('Start Free Trial - 14 Days')}
                  <ArrowRight size={18} />
                </a>
              )}
              <a
                href={route('login')}
                className="bg-white dark:bg-gray-800 border-2 px-8 py-4 rounded-xl transition-all duration-300 font-semibold text-base flex items-center justify-center gap-2 hover:bg-gray-50 dark:hover:bg-gray-700 hover:scale-105 transform shadow-sm"
                style={{ borderColor: brandColor, color: brandColor }}
                aria-label="Login to your account"
              >
                <Play size={18} />
                {sectionData.secondary_button_text || t('Sign In')}
              </a>
            </div>

            {sectionData.stats && sectionData.stats.length > 0 ? (
              <div className="grid grid-cols-3 gap-4 sm:gap-6 lg:gap-8 pt-8 sm:pt-12">
                {sectionData.stats.map((stat, index) => (
                  <div key={index} className="text-center">
                    <div className="text-3xl md:text-4xl font-bold text-gray-900">
                      {stat.value}
                    </div>
                    <div className="text-gray-600 font-medium text-sm">{stat.label}</div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-3 gap-4 sm:gap-6 lg:gap-8 pt-8 sm:pt-12">
                <div className="text-center">
                  <div className="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    50K+
                  </div>
                  <div className="text-gray-600 font-medium text-sm">{t('Active Users')}</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    99.9%
                  </div>
                  <div className="text-gray-600 font-medium text-sm">{t('Uptime')}</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    40%
                  </div>
                  <div className="text-gray-600 font-medium text-sm">{t('Productivity Boost')}</div>
                </div>
              </div>
            )}
          </div>

          {/* Right Content - Hero Image or Dashboard Preview */}
          <div className="relative">
            {heroImage ? (
              <div className="relative">
                <img 
                  src={heroImage} 
                  alt="Taskly Dashboard Preview" 
                  className="w-full h-auto rounded-2xl shadow-2xl border border-gray-200"
                />
                {/* Floating Elements */}
                <div className="absolute -top-6 -left-6 bg-white rounded-xl shadow-lg p-4 border border-gray-100">
                  <div className="flex items-center gap-3">
                    <Users size={20} style={{ color: brandColor }} />
                    <div>
                      <div className="text-sm font-semibold text-gray-900">{t('Team Active')}</div>
                      <div className="text-xs text-gray-500">{t('12 members online')}</div>
                    </div>
                  </div>
                </div>
                <div className="absolute -bottom-6 -right-6 bg-white rounded-xl shadow-lg p-4 border border-gray-100">
                  <div className="flex items-center gap-3">
                    <Shield size={20} className="text-green-500" />
                    <div>
                      <div className="text-sm font-semibold text-gray-900">{t('Secure & Reliable')}</div>
                      <div className="text-xs text-gray-500">{t('Enterprise-grade security')}</div>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="relative">
                {/* Modern Dashboard Mockup */}
                <div className="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                  {/* Header */}
                  <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: brandColor }}>
                          <span className="text-white text-sm font-bold">T</span>
                        </div>
                        <span className="font-semibold text-gray-900">{t('Taskly Dashboard')}</span>
                      </div>
                      <div className="flex gap-2">
                        <div className="w-3 h-3 bg-red-400 rounded-full"></div>
                        <div className="w-3 h-3 bg-yellow-400 rounded-full"></div>
                        <div className="w-3 h-3 bg-green-400 rounded-full"></div>
                      </div>
                    </div>
                  </div>
                  
                  {/* Content */}
                  <div className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div className="bg-blue-50 rounded-lg p-4">
                        <div className="text-2xl font-bold" style={{ color: brandColor }}>24</div>
                        <div className="text-sm text-gray-600">{t('Active Tasks')}</div>
                      </div>
                      <div className="bg-green-50 rounded-lg p-4">
                        <div className="text-2xl font-bold text-green-600">8</div>
                        <div className="text-sm text-gray-600">{t('Completed')}</div>
                      </div>
                    </div>
                    
                    <div className="space-y-3">
                      <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div className="w-2 h-2 rounded-full" style={{ backgroundColor: brandColor }}></div>
                        <span className="text-sm text-gray-700">{t('Design System Update')}</span>
                        <div className="ml-auto text-xs text-gray-500">{t('Due Today')}</div>
                      </div>
                      <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span className="text-sm text-gray-700">{t('API Integration')}</span>
                        <div className="ml-auto text-xs text-gray-500">{t('Completed')}</div>
                      </div>
                      <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div className="w-2 h-2 bg-orange-500 rounded-full"></div>
                        <span className="text-sm text-gray-700">{t('User Testing')}</span>
                        <div className="ml-auto text-xs text-gray-500">{t('In Progress')}</div>
                      </div>
                    </div>
                  </div>
                </div>
                
                {/* Floating Notification */}
                <div className="absolute -top-4 -right-4 bg-white rounded-xl shadow-lg p-3 border border-gray-100 animate-bounce">
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span className="text-xs text-gray-700">{t('New task assigned')}</span>
                  </div>
                </div>
              </div>
            )}
            
            {/* Enhanced Decorative Elements */}
            <div className="absolute -top-8 -right-8 w-20 h-20 bg-gradient-to-br from-blue-200 to-purple-200 rounded-full opacity-60 animate-pulse"></div>
            <div className="absolute -bottom-8 -left-8 w-16 h-16 bg-gradient-to-br from-pink-200 to-yellow-200 rounded-full opacity-50 animate-pulse delay-1000"></div>
          </div>
        </div>
      </div>
    </section>
  );
}