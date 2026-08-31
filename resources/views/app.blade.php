<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', session('locale', app()->getLocale())) }}" dir="{{ session('locale') === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset(config('app.logo')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="152x152" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="144x144" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="120x120" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="114x114" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="76x76" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="72x72" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="60x60" href="{{ asset(config('app.logo')) }}">
        <link rel="apple-touch-icon" sizes="57x57" href="{{ asset(config('app.logo')) }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset(config('app.logo')) }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset(config('app.logo')) }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset(config('app.logo')) }}">
        
        <!-- Default Meta Tags (can be overridden by Inertia Head) -->
        <meta name="generator" content="Laravel {{ app()->version() }}">
        <meta name="referrer" content="no-referrer-when-downgrade">
        <meta name="author" content="ASH Health Care">
        <meta name="copyright" content="© {{ date('Y') }} ASH Health Care. All rights reserved.">
        <meta name="coverage" content="Worldwide">
        <meta name="distribution" content="Global">
        <meta name="rating" content="General">
        <meta name="revisit-after" content="7 days">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="slurp" content="index, follow">
        <meta name="duckduckbot" content="index, follow">
        
        <!-- Default Description and Keywords (can be overridden by Inertia Head) -->
        <meta name="description" content="@if(session('locale') === 'ar') كارت خصومات طبي شامل لجميع الجهات الطبية مع خصومات تصل إلى 80%. نقدم أفضل خطط التأمين الطبي وخصومات حصرية على التحاليل والأشعة. متاح في جميع محافظات مصر. @else Comprehensive medical discount card for all medical facilities with discounts up to 80%. We offer the best medical insurance plans and exclusive discounts on lab tests and radiology. Available in all governorates of Egypt. @endif">
        <meta name="keywords" content="@if(session('locale') === 'ar') كارت خصومات طبي، تأمين طبي، خصومات تحاليل، خصومات أشعة، رعاية صحية، ASH Health Care، كارت عضوية طبي، خصومات صحية، تأمين صحي شامل @else medical discount card, health insurance, lab test discounts, radiology discounts, healthcare, ASH Health Care, medical membership card, health discounts, comprehensive health insurance @endif">
        
        <!-- Language and Locale -->
        <meta name="language" content="@if(session('locale') === 'ar') Arabic @else English @endif">
        <meta name="geo.region" content="EG">
        <meta name="geo.placename" content="Cairo, Egypt">
        
        <!-- Default Open Graph Meta Tags (can be overridden by Inertia Head) -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="ASH Health Care">
        <meta property="og:image" content="{{ url('/' . ltrim(config('app.logo'), '/')) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="ASH Health Care - Medical Discount Card">
        <meta property="og:locale" content="@if(session('locale') === 'ar') ar_EG @else en_US @endif">
        <meta property="og:locale:alternate" content="@if(session('locale') === 'ar') en_US @else ar_EG @endif">
        
        <!-- Default Twitter Card Meta Tags (can be overridden by Inertia Head) -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@ashhealthcare">
        <meta name="twitter:creator" content="@ashhealthcare">
        <meta name="twitter:image" content="{{ url('/' . ltrim(config('app.logo'), '/')) }}">
        <meta name="twitter:image:alt" content="ASH Health Care - Medical Discount Card">
        
        <!-- Mobile Meta Tags -->
        <meta name="theme-color" content="#1E3943">
        <meta name="msapplication-TileColor" content="#1E3943">
        <meta name="msapplication-TileImage" content="{{ asset(config('app.logo')) }}">
        <meta name="msapplication-navbutton-color" content="#1E3943">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="ASH Health Care">
        <meta name="application-name" content="ASH Health Care">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="format-detection" content="telephone=no, address=no, email=no">
        
        <!-- PWA and Web App Meta Tags -->
        <meta name="apple-itunes-app" content="app-id=YOUR_APP_ID">
        
        <!-- Security Meta Tags -->
        @production
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        @endproduction
        <meta http-equiv="X-Content-Type-Options" content="nosniff">
        <!-- X-Frame-Options should be set via HTTP headers, not meta tags -->
        <meta http-equiv="X-XSS-Protection" content="1; mode=block">
        <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
        
        <!-- Verification Meta Tags (Add your verification codes when available) -->
        <!-- <meta name="google-site-verification" content="YOUR_GOOGLE_VERIFICATION_CODE"> -->
        <!-- <meta name="facebook-domain-verification" content="YOUR_FACEBOOK_VERIFICATION_CODE"> -->
        <!-- <meta name="msvalidate.01" content="YOUR_BING_VERIFICATION_CODE"> -->
        <!-- <meta name="yandex-verification" content="YOUR_YANDEX_VERIFICATION_CODE"> -->
        <!-- <meta name="pinterest-site-verification" content="YOUR_PINTEREST_VERIFICATION_CODE"> -->
        
        <!-- Additional Meta Tags -->
        <meta name="HandheldFriendly" content="True">
        <meta name="MobileOptimized" content="320">
        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE">
        <meta name="audience" content="all">
        <meta name="target" content="all">
        
        <!-- DNS Prefetch and Preconnect for Performance -->
        <link rel="dns-prefetch" href="https://fonts.bunny.net">
        <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
        <link rel="dns-prefetch" href="https://unicons.iconscout.com">
        <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
        <link rel="preconnect" href="https://unicons.iconscout.com" crossorigin>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        
        <!-- Preload Critical Images -->
        <link rel="preload" as="image" href="{{ asset(config('app.logo')) }}" type="image/png">
        
        <!-- Default Title (will be overridden by Inertia) -->
        <title inertia>{{ config('app.name', 'ASH Health Care') }} - @if(session('locale') === 'ar') كارت خصومات طبي شامل @else Comprehensive Medical Discount Card @endif</title>

        <!-- Fonts - Load asynchronously with font-display: swap -->
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /></noscript>
        <style>
          /* Font display optimization */
          @font-face {
            font-family: 'Figtree';
            font-display: swap;
          }
          
          /* Critical CSS - Inline for faster rendering */
          *{box-sizing:border-box}html{overflow-x:hidden!important;max-width:100vw;width:100%;height:100%}body{overflow-x:hidden!important;max-width:100vw!important;width:100%;height:100%;margin:0;padding:0;font-family:'Figtree',system-ui,sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}img{max-width:100%;height:auto}
        </style>
        
        <!-- Unicons CSS - Load asynchronously -->
        <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css" media="print" onload="this.media='all'" />
        <noscript><link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css" /></noscript>
        
        <!-- Font Awesome - Load asynchronously -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'" />
        <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" /></noscript>
        
        <!-- Performance optimization script -->
        <script>
          // Fallback for browsers that don't support onload with media trick
          if (!document.querySelector('link[onload]')) {
            // Force load async styles immediately if onload didn't work
            document.addEventListener('DOMContentLoaded', function() {
              const asyncLinks = document.querySelectorAll('link[media="print"]');
              asyncLinks.forEach(link => {
                if (link.media === 'print') {
                  link.media = 'all';
                }
              });
            });
          }
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        
        <!-- Service Worker Registration -->
        <script>
          if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
              navigator.serviceWorker.register('/sw.js')
                .then((registration) => {
                  console.log('Service Worker registered:', registration.scope);
                })
                .catch((error) => {
                  console.log('Service Worker registration failed:', error);
                });
            });
          }
        </script>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
