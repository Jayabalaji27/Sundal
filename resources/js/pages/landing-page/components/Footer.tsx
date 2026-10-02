import React, { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Facebook, Twitter, Linkedin, Instagram, Mail, Phone, MapPin, CheckCircle } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface FooterProps {
  brandColor?: string;
  flash?: {
    success?: string;
    error?: string;
    info?: string;
  };
  settings: {
    company_name: string;
    contact_email: string;
    contact_phone: string;
    contact_address: string;
  };
  sectionData?: {
    description?: string;
    newsletter_title?: string;
    newsletter_subtitle?: string;
    privacy_text?: string;
    links?: any;
    social_links?: Array<{
      name: string;
      icon: string;
      href: string;
    }>;
    section_titles?: {
      product: string;
      company: string;
      support: string;
      legal: string;
    };
  };
}

export default function Footer({ flash, settings, sectionData = {}, brandColor = '#3b82f6' }: FooterProps) {
  const currentYear = new Date().getFullYear();
  const { t } = useTranslation();
  const [isSubmitted, setIsSubmitted] = useState(false);
  
  const { data, setData, post, processing, errors, reset } = useForm({
    email: ''
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('landing-page.subscribe'), {
      onSuccess: (page) => {
        reset();
        if (page.props.flash?.success) {
          toast.success(page.props.flash.success);
        }
      },
      onError: (errors) => {
        console.log('Footer - Error occurred:', errors);
        const errorMessage = Object.values(errors).join(', ');
        toast.error(errorMessage || 'Failed to subscribe. Please try again.');
      }
    });
  };

  const defaultLinks = {
    product: [
      { name: 'Features', href: '#features' },
      { name: 'Pricing', href: '#pricing' },
      { name: 'Templates', href: '#' },
      { name: 'Integrations', href: '#' }
    ],
    company: [
      { name: 'About Us', href: '#about' },
      { name: 'Careers', href: '#' },
      { name: 'Press', href: '#' },
      { name: 'Contact', href: '#contact' }
    ],
    support: [
      { name: 'Help Center', href: '#' },
      { name: 'Terms of Service', href: '#' }
    ],
    legal: [
      { name: 'Privacy Policy', href: '#' },
      { name: 'Terms of Service', href: '#' }
    ]
  };

  const footerLinks = {
    product: sectionData.links?.product || defaultLinks.product,
    company: sectionData.links?.company || defaultLinks.company,
    support: sectionData.links?.support || defaultLinks.support,
    legal: sectionData.links?.legal || defaultLinks.legal
  };

  const iconMap: Record<string, any> = {
    Facebook,
    Twitter,
    Linkedin,
    Instagram
  };
  
  const socialLinks = sectionData.social_links || [
    { name: 'Facebook', icon: 'Facebook', href: '#' },
    { name: 'Twitter', icon: 'Twitter', href: '#' },
    { name: 'LinkedIn', icon: 'Linkedin', href: '#' },
    { name: 'Instagram', icon: 'Instagram', href: '#' }
  ];

  return (
    <footer className="bg-gray-900 text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Main Footer Content */}
        <div className="py-12 sm:py-16">
          <div className="grid lg:grid-cols-6 gap-8 sm:gap-12">
            {/* Company Info */}
            <div className="lg:col-span-2">
              <Link href="/" className="text-2xl font-bold text-white mb-6 block hover:text-gray-300 transition-colors">
                {settings.company_name}
              </Link>
              <p className="text-gray-400 mb-8 leading-relaxed">
                {sectionData.description || t('Transforming professional networking...')}
              </p>
              
              {/* Contact Info */}
              <div className="space-y-3">
                <div className="flex items-center gap-3">
                  <Mail className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-400 text-sm">{settings.contact_email}</span>
                </div>
                <div className="flex items-center gap-3">
                  <Phone className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-400 text-sm">{settings.contact_phone}</span>
                </div>
                <div className="flex items-center gap-3">
                  <MapPin className="w-4 h-4 text-gray-400" />
                  <span className="text-gray-400 text-sm">{settings.contact_address}</span>
                </div>
              </div>
            </div>

            {/* Product Links */}
            <div>
              <h3 className="text-white font-semibold mb-4">{sectionData.section_titles?.product || t('Product')}</h3>
              <ul className="space-y-3">
                {(footerLinks.product || []).map((link) => (
                  <li key={link.name}>
                    <a
                      href={link.href}
                      className="text-gray-400 hover:text-white transition-colors text-sm"
                    >
                      {link.name}
                    </a>
                  </li>
                ))}
              </ul>
            </div>

            {/* Company Links */}
            <div>
              <h3 className="text-white font-semibold mb-4">{sectionData.section_titles?.company || t('Company')}</h3>
              <ul className="space-y-3">
                {(footerLinks.company || []).map((link) => (
                  <li key={link.name}>
                    <a
                      href={link.href}
                      className="text-gray-400 hover:text-white transition-colors text-sm"
                    >
                      {link.name}
                    </a>
                  </li>
                ))}
              </ul>
            </div>

            {/* Support Links */}
            <div>
              <h3 className="text-white font-semibold mb-4">{sectionData.section_titles?.support || t('Support')}</h3>
              <ul className="space-y-3">
                {(footerLinks.support || []).map((link) => (
                  <li key={link.name}>
                    <a
                      href={link.href}
                      className="text-gray-400 hover:text-white transition-colors text-sm"
                    >
                      {link.name}
                    </a>
                  </li>
                ))}
              </ul>
            </div>

            {/* Legal Links */}
            <div>
              <h3 className="text-white font-semibold mb-4">{sectionData.section_titles?.legal || t('Legal')}</h3>
              <ul className="space-y-3">
                {(footerLinks.legal || []).map((link) => (
                  <li key={link.name}>
                    <a
                      href={link.href}
                      className="text-gray-400 hover:text-white transition-colors text-sm"
                    >
                      {link.name}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>

        {/* Newsletter Section */}
        {(sectionData.newsletter_title || sectionData.newsletter_subtitle) && (
          <div className="border-t border-gray-800 py-8 sm:py-12">
            <div className="text-center max-w-2xl mx-auto">
              <h3 className="text-xl font-bold text-white mb-4">
                {sectionData.newsletter_title || t('Stay Updated with Our Latest Features')}
              </h3>
              <p className="text-gray-400 mb-6">
                {sectionData.newsletter_subtitle || t('Join our newsletter for product updates and networking tips')}
              </p>
              <form onSubmit={handleSubmit} className="max-w-md mx-auto">
                <div className="flex flex-col sm:flex-row gap-4">
                  <div className="flex-1">
                    <input
                      type="email"
                      value={data.email}
                      onChange={(e) => setData('email', e.target.value)}
                      placeholder={t('Enter your email')}
                      className="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-gray-600 focus:border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                      required
                      disabled={processing}
                      aria-label="Email address for newsletter subscription"
                    />
                    {errors.email && (
                      <p className="text-red-400 text-sm mt-1">{errors.email}</p>
                    )}
                  </div>
                  <button
                    type="submit"
                    disabled={processing}
                    className="text-white px-6 py-3 rounded-lg transition-colors font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 min-w-[120px]"
                    style={{ backgroundColor: brandColor }}
                    aria-label={processing ? 'Subscribing to newsletter' : 'Subscribe to newsletter'}
                  >
                    {processing && (
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    )}
                    {processing ? t('Subscribing...') : t('Subscribe')}
                  </button>
                </div>
              </form>

              <p className="text-gray-500 text-sm mt-4">
                {sectionData?.privacy_text || t('No spam, unsubscribe at any time.')}
              </p>
            </div>
          </div>
        )}

        {/* Bottom Footer */}
        <div className="border-t border-gray-800 py-4 sm:py-6">
          <div className="flex flex-col md:flex-row justify-between items-center gap-3 sm:gap-4">
            {/* Copyright */}
            <div className="text-gray-400 text-sm">
              © {currentYear} {settings.company_name}. {t('All rights reserved.')}
            </div>

            {/* Social Links */}
            {socialLinks.length > 0 && (
              <div className="flex items-center gap-4">
                <span className="text-gray-400 text-sm">{t('Follow us:')}</span>
                <div className="flex gap-3">
                  {socialLinks.map((social) => {
                    const IconComponent = iconMap[social.icon] || Facebook;
                    return (
                      <a
                        key={social.name}
                        href={social.href}
                        className="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition-colors"
                        aria-label={social.name}
                      >
                        <IconComponent className="w-4 h-4 text-gray-400" />
                      </a>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </footer>
  );
}