import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

interface RecaptchaProps {
  onVerify: (token: string) => void;
  onExpired?: () => void;
  onError?: () => void;
}

declare global {
  interface Window {
    grecaptcha: any;
  }
}

export default function Recaptcha({ onVerify, onExpired, onError }: RecaptchaProps) {
  const { settings = {} } = usePage().props as any;
  const recaptchaRef = useRef<HTMLDivElement>(null);
  const [isLoaded, setIsLoaded] = useState(false);


  const recaptchaEnabled = settings.recaptchaEnabled === 'true' || settings.recaptchaEnabled === true || settings.recaptchaEnabled === 1 || settings.recaptchaEnabled === '1';
  const recaptchaVersion = settings.recaptchaVersion || 'v2';
  const recaptchaSiteKey = settings.recaptchaSiteKey || '';

  useEffect(() => {
    if (!recaptchaEnabled || !recaptchaSiteKey) return;

    const renderRecaptcha = () => {
      if (window.grecaptcha && recaptchaRef.current && !isLoaded) {
        window.grecaptcha.ready(() => {
          try {
            if (recaptchaVersion === 'v2') {
              window.grecaptcha.render(recaptchaRef.current, {
                sitekey: recaptchaSiteKey,
                callback: onVerify,
                'expired-callback': onExpired,
                'error-callback': onError,
              });
            } else {
              window.grecaptcha.execute(recaptchaSiteKey, { action: 'submit' })
                .then(onVerify)
                .catch(onError);
            }
            setIsLoaded(true);
          } catch (error) {
            console.error('ReCaptcha render error:', error);
            onError?.();
          }
        });
      }
    };

    if (window.grecaptcha) {
      renderRecaptcha();
    } else {
      const script = document.createElement('script');
      script.src = `https://www.google.com/recaptcha/api.js?render=${recaptchaVersion === 'v3' ? recaptchaSiteKey : 'explicit'}`;
      script.onload = renderRecaptcha;
      script.onerror = () => onError?.();
      document.head.appendChild(script);
    }
  }, [recaptchaEnabled, recaptchaSiteKey, recaptchaVersion, isLoaded]);

  if (!recaptchaEnabled || !recaptchaSiteKey) {
    return null;
  }

  return recaptchaVersion === 'v2' ? <div ref={recaptchaRef}></div> : null;
}