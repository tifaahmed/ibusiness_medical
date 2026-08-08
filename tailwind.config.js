import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    // Optimize CSS purging - safelist for dynamic classes
    safelist: [
        // Keep dynamic classes that might be generated
        /^swiper-/,
        /^animate-/,
        'swiper-wrapper',
        'swiper-slide',
        'swiper-pagination',
    ],

    darkMode: 'class',

    safelist: [
        // Animation classes that might be added dynamically
        'animate-fade-in',
        'animate-float',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                background: 'var(--background)',
                foreground: 'var(--foreground)',
                'dark-foreground': 'var(--dark-foreground)',
                
                card: {
                    DEFAULT: 'var(--card)',
                    foreground: 'var(--card-foreground)',
                },
                popover: {
                    DEFAULT: 'var(--popover)',
                    foreground: 'var(--popover-foreground)',
                },
                primary: {
                    DEFAULT: 'var(--primary)',
                    foreground: 'var(--primary-foreground)',
                    medium: 'var(--primary-medium)',
                    'medium-foreground': 'var(--primary-medium-foreground)',
                },
                secondary: {
                    DEFAULT: 'var(--secondary)',
                    foreground: 'var(--secondary-foreground)',
                },
                muted: {
                    DEFAULT: 'var(--muted)',
                    foreground: 'var(--muted-foreground)',
                },
                accent: {
                    DEFAULT: 'var(--accent)',
                    foreground: 'var(--accent-foreground)',
                },
                destructive: {
                    DEFAULT: 'var(--destructive)',
                    foreground: 'var(--destructive-foreground)',
                },
                border: 'var(--border)',
                input: 'var(--input)',
                ring: 'var(--ring)',
                
                sidebar: {
                    DEFAULT: 'var(--sidebar)',
                    foreground: 'var(--sidebar-foreground)',
                    primary: 'var(--sidebar-primary)',
                    'primary-foreground': 'var(--sidebar-primary-foreground)',
                    accent: 'var(--sidebar-accent)',
                    'accent-foreground': 'var(--sidebar-accent-foreground)',
                    border: 'var(--sidebar-border)',
                    ring: 'var(--sidebar-ring)',
                },
                
                'golden-yellow': {
                    DEFAULT: 'var(--golden-yellow)',
                    foreground: 'var(--golden-yellow-foreground)',
                },
                'fanar-blue': {
                    DEFAULT: 'var(--fanar-blue)',
                    foreground: 'var(--fanar-blue-foreground)',
                },
                'vibrant-pink': 'var(--vibrant-pink)',
                'vibrant-purple': 'var(--vibrant-purple)',
                'vibrant-orange': 'var(--vibrant-orange)',
                
                pink: {
                    DEFAULT: 'var(--pink)',
                    foreground: 'var(--pink-foreground)',
                },
                
                'bright-blue': {
                    DEFAULT: 'var(--color-bright-blue)',
                    foreground: 'var(--color-bright-blue-foreground)',
                },
                'hot-magenta': {
                    DEFAULT: 'var(--color-hot-magenta)',
                    foreground: 'var(--color-hot-magenta-foreground)',
                },
                'emerald-bright': {
                    DEFAULT: 'var(--color-emerald-bright)',
                    foreground: 'var(--color-emerald-bright-foreground)',
                },
                'electric-purple': {
                    DEFAULT: 'var(--color-electric-purple)',
                    foreground: 'var(--color-electric-purple-foreground)',
                },
                'cyan-bright': {
                    DEFAULT: 'var(--color-cyan-bright)',
                    foreground: 'var(--color-cyan-bright-foreground)',
                },
                'coral-red': {
                    DEFAULT: 'var(--color-coral-red)',
                    foreground: 'var(--color-coral-red-foreground)',
                },
                'lime-bright': {
                    DEFAULT: 'var(--color-lime-bright)',
                    foreground: 'var(--color-lime-bright-foreground)',
                },
                'indigo-bright': {
                    DEFAULT: 'var(--color-indigo-bright)',
                    foreground: 'var(--color-indigo-bright-foreground)',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
        },
    },

    plugins: [forms, typography],
};
