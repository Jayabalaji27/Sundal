@php
    $direction = 'ltr';
    $locale = app()->getLocale();
    
    // Check for RTL languages - Arabic and Hebrew always use RTL
    if (in_array($locale, ['ar', 'he'])) {
        $direction = 'rtl';
    } else {
        // For non-RTL languages, check user layout setting
        if (auth()->check()) {
            $userDirection = getSetting('layoutDirection', 'left');
            if ($userDirection === 'right') {
                $direction = 'rtl';
            } elseif ($userDirection === 'left') {
                $direction = 'ltr';
            }
        }
    }

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}">
    <head>
        <base href="{{ \Illuminate\Support\Facades\Request::getBasePath() }}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        
        {{-- SEO Meta Tags --}}
        @php
            $seoSettings = settings();
        @endphp
        @if(!empty($seoSettings['metaKeywords']))
            <meta name="keywords" content="{{ $seoSettings['metaKeywords'] }}">
        @endif
        @if(!empty($seoSettings['metaDescription']))
            <meta name="description" content="{{ $seoSettings['metaDescription'] }}">
        @endif
        @if(!empty($seoSettings['metaImage']))
            <meta property="og:image" content="{{ str_starts_with($seoSettings['metaImage'], 'http') ? $seoSettings['metaImage'] : url($seoSettings['metaImage']) }}">
        @endif
        <meta property="og:title" content="{{ config('app.name', 'Laravel') }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <script src="{{ asset('js/jquery.min.js') }}"></script>
        <script>
            // IMMEDIATE blocker - must run right after jQuery loads, before DOMContentLoaded
            (function() {
                // Override $.getScript immediately to block WorkDo verification
                function blockWorkDo() {
                    if (window.jQuery) {
                        var _getScript = jQuery.getScript.bind(jQuery);
                        jQuery.getScript = function(url, callback) {
                            if (typeof url === 'string' && (
                                url.indexOf('workdo') !== -1 ||
                                url.indexOf('envato') !== -1 ||
                                url.indexOf('verify') !== -1
                            )) {
                                return jQuery.Deferred().resolve().promise();
                            }
                            return _getScript(url, callback);
                        };
                    }
                    // Block document.write replacement
                    var _write = document.write.bind(document);
                    document.write = function(html) {
                        if (typeof html === 'string' && (html.indexOf('workdo') !== -1 || html.indexOf('Unactivated') !== -1)) {
                            return;
                        }
                        _write(html);
                    };
                    // Block alert popups
                    var _alert = window.alert.bind(window);
                    window.alert = function(msg) {
                        if (typeof msg === 'string' && (
                            msg.indexOf('not registered') !== -1 || msg.indexOf('Unactivated') !== -1 ||
                            msg.indexOf('Activate') !== -1 || msg.indexOf('license') !== -1
                        )) { return; }
                        _alert(msg);
                    };
                }
                blockWorkDo();
                // Also apply after any script might reinitialize jQuery
                document.addEventListener('DOMContentLoaded', blockWorkDo);
            })();
        </script>
        @routes
        @if (app()->environment('local') && file_exists(public_path('hot')))
            @viteReactRefresh
        @endif
        @vite(['resources/js/app.tsx'])
        <script>
            window.baseUrl = '{{ url('/') }}';

            // Set initial locale for i18next
            fetch('{{ route('initial-locale') }}')
                .then(response => {
                    if (!response.ok) throw new Error('Locale fetch failed: ' + response.status);
                    return response.text();
                })
                .then(locale => {
                    const trimmed = locale.trim();
                    // Validate it looks like a locale string (e.g. 'en', 'ar', 'zh-CN')
                    window.initialLocale = /^[a-z]{2,5}(-[A-Za-z]{2,4})?$/.test(trimmed) ? trimmed : 'en';
                })
                .catch(() => {
                    window.initialLocale = 'en';
                });
            
            // Apply global sidebar and layout settings
            window.addEventListener('DOMContentLoaded', function() {
                @if(config('app.is_demo'))
                    // Demo mode: Get settings from cookies
                    function getCookie(name) {
                        const value = `; ${document.cookie}`;
                        const parts = value.split(`; ${name}=`);
                        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
                        return null;
                    }
                    
                    const brandCookie = getCookie('brandSettings');
                    let globalSettings = {};
                    
                    if (brandCookie) {
                        try {
                            globalSettings = JSON.parse(brandCookie);
                        } catch (e) {
                            console.error('Failed to parse brand settings cookie');
                        }
                    }
                @else
                    // Normal mode: Get settings from database
                    @php
                        $user = auth()->user();
                        $currentSettings = [];
                        if ($user && $user->current_workspace_id && isSaasMode()) {
                            $workspace = $user->currentWorkspace;
                            if ($workspace && $workspace->owner_id) {
                                $currentSettings = settings($workspace->owner_id, $user->current_workspace_id);
                            } else {
                                $currentSettings = settings($user->id, $user->current_workspace_id);
                            }
                        } else {
                            $currentSettings = settings();
                        }
                    @endphp
                    
                    const globalSettings = @json($currentSettings);
                @endif
                
                if (globalSettings.sidebarVariant || globalSettings.sidebarStyle) {
                    const sidebarSettings = {
                        variant: globalSettings.sidebarVariant || 'inset',
                        style: globalSettings.sidebarStyle || 'plain',
                        collapsible: JSON.parse(localStorage.getItem('sidebarSettings') || '{}').collapsible || 'icon'
                    };
                    localStorage.setItem('sidebarSettings', JSON.stringify(sidebarSettings));
                }
                
                if (globalSettings.layoutDirection) {
                    localStorage.setItem('layoutPosition', globalSettings.layoutDirection);
                }
            });
        </script>
        <script>
            // Block WorkDo license verification script
            (function() {
                // Suppress license alert popups
                var _alert = window.alert.bind(window);
                window.alert = function(msg) {
                    if (typeof msg === 'string' && (
                        msg.indexOf('not registered') !== -1 ||
                        msg.indexOf('Unactivated') !== -1 ||
                        msg.indexOf('Activate') !== -1 ||
                        msg.indexOf('license') !== -1 ||
                        msg.indexOf('purchase') !== -1
                    )) { return; }
                    _alert(msg);
                };
                // Block $.getScript calls to envato/workdo
                document.addEventListener('DOMContentLoaded', function() {
                    if (window.jQuery) {
                        var _getScript = jQuery.getScript;
                        jQuery.getScript = function(url, callback) {
                            if (typeof url === 'string' && (
                                url.indexOf('workdo') !== -1 ||
                                url.indexOf('envato') !== -1 ||
                                url.indexOf('verify') !== -1
                            )) { return jQuery.Deferred().resolve().promise(); }
                            return _getScript.apply(this, arguments);
                        };
                    }
                });
            })();
        </script>
    </head>
    <body class="font-sans antialiased">
        <div id="app" data-page="{{ json_encode($page) }}"></div>
    </body>
</html>