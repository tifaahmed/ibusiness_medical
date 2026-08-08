# Performance Optimization Plan - Home Page
## Target: Achieve 90+ Lighthouse Performance Score

## Current State Analysis

### Bundle Sizes
- **Main App JS**: 285KB (needs optimization)
- **Main CSS**: 170KB (can be reduced)
- **HomePage Component**: 27KB
- **Total Images**: 90 files (3.8MB directory size)

### Key Performance Issues Identified

#### 1. External CDN Dependencies (Critical)
**Current Issue**: Swiper.js loaded from CDN at runtime
```javascript
// HomePage.vue lines 903-920
loadSwiperScripts() - loads Swiper from CDN dynamically
- CSS: https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css
- JS: https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js
```

**Impact**:
- Blocks rendering waiting for external scripts
- Additional DNS lookup + connection time
- ~50-100KB of unoptimized external resources
- No tree-shaking benefits

#### 2. Large JavaScript Bundle
**Issues**:
- 285KB main app bundle (target: <200KB)
- All pages loaded via import.meta.glob (lazy loading is good but can be optimized)
- Multiple heavy dependencies (Pinia, Inertia, etc.)

#### 3. Image Optimization (Partially Complete)
**Good**: Image optimization script exists
**Issues**:
- 90 images totaling 3.8MB
- Many partner images loaded on initial page load
- No responsive image sizes (srcset)
- Missing lazy loading for below-fold images
- Loading from CDN (pravatar.cc) for avatars

#### 4. CSS Bundle Size
**Issue**: 170KB CSS file
- Likely includes unused Tailwind classes
- No CSS purging/minification optimization

#### 5. Multiple Component Imports
HomePage loads 10+ components synchronously:
- FloatingLogos, FloatingPartners, FloatingContact
- HeroSlider, FeatureCard, StatCard
- TestimonialCard, PricingCard
- GuestNavigation, GuestFooter

## Optimization Recommendations (Priority Order)

### Priority 1: Critical Performance Wins

#### A. Move Swiper to NPM Package (Est. Score Gain: +10-15 points)
**Action**:
```bash
npm install swiper@11
```

**Changes Required**:
1. Update `HomePage.vue` to import Swiper from npm instead of CDN
2. Remove dynamic script loading function
3. Import only required Swiper modules (tree-shaking)

**Before** (lines 903-920):
```javascript
const loadSwiperScripts = () => {
  // Dynamic CDN loading
}
```

**After**:
```javascript
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, Keyboard } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
```

**Benefits**:
- Eliminates render-blocking external requests
- Enables tree-shaking (only load needed modules)
- Better caching with versioned assets
- Reduces bundle by ~30-40KB

#### B. Optimize Vite Build Configuration (Est. Score Gain: +5-10 points)
**Update `vite.config.js`**:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': [
                        'vue',
                        'pinia',
                        '@inertiajs/vue3'
                    ],
                    'swiper': ['swiper']
                }
            }
        },
        chunkSizeWarningLimit: 600,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true
            }
        }
    }
});
```

**Benefits**:
- Code splitting for better caching
- Removes console.logs in production
- Better minification

#### C. Implement Image Lazy Loading & Optimization (Est. Score Gain: +8-12 points)

**1. Update Partner Images Array**:
Create a structured partners data file instead of inline array:

**Create `resources/js/data/partners.js`**:
```javascript
export const partners = [
  {
    src: '/images/partners/WhatsApp Image 2026-01-03 at 11.04.09 PM.jpeg',
    alt: 'Partner 1',
    loading: 'lazy'
  },
  // ... rest of partners
];
```

**2. Optimize HeroSlider Component**:
Add lazy loading and proper image sizing

**3. Convert External Avatar URLs**:
- Replace `pravatar.cc` with local optimized avatars
- Reduces external DNS lookups

**4. Add Responsive Images**:
Update optimization script to create multiple sizes:
```bash
npm run optimize-images-responsive
```

### Priority 2: CSS & Font Optimization (Est. Score Gain: +5-8 points)

#### A. Purge Unused CSS
**Update `tailwind.config.js`**:
```javascript
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
  // Add purge options for production
  safelist: [
    // Add any dynamic classes that might be purged incorrectly
  ]
}
```

#### B. Optimize Font Loading
Add font preload to main layout:
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="dns-prefetch" href="https://fonts.bunny.net">
```

### Priority 3: Component & Code Optimization (Est. Score Gain: +3-5 points)

#### A. Defer Non-Critical Components
Components like `FloatingPartners` and `FloatingLogos` can be lazy-loaded:

```javascript
// Instead of:
import FloatingLogos from './_components/FloatingLogos.vue';

// Use:
const FloatingLogos = defineAsyncComponent(() =>
  import('./_components/FloatingLogos.vue')
);
```

#### B. Reduce JavaScript Execution
**HomePage.vue optimizations**:
1. Remove unused `pricingPlans` computed property (commented out section)
2. Debounce scroll handler
3. Use `requestAnimationFrame` for animations

**Before**:
```javascript
const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
};
```

**After**:
```javascript
const handleScroll = () => {
  window.requestAnimationFrame(() => {
    isScrolled.value = window.scrollY > 50;
  });
};
```

### Priority 4: Server & Caching (Est. Score Gain: +5-7 points)

#### A. Enable HTTP/2
Ensure your server supports HTTP/2 for multiplexing

#### B. Implement Resource Hints
Add to HomePage.vue `<Head>` component:
```javascript
const linkTags = computed(() => {
  return [
    // Existing tags...

    // Add preload for critical resources
    { rel: 'preload', href: '/build/assets/app-*.js', as: 'script' },
    { rel: 'preload', href: '/build/assets/app-*.css', as: 'style' },

    // Prefetch for likely navigation
    { rel: 'prefetch', href: route('contact') },
  ];
});
```

#### C. Optimize Server Response
1. Enable Brotli compression (better than Gzip)
2. Set proper cache headers for static assets
3. Use CDN for static assets

### Priority 5: Remove Render-Blocking Resources (Est. Score Gain: +3-5 points)

#### A. Inline Critical CSS
Extract above-the-fold CSS and inline it in the layout

#### B. Defer Non-Critical JavaScript
Ensure Vite builds with proper defer/async attributes

## Implementation Checklist

### Week 1: Critical Optimizations
- [ ] Install Swiper via NPM
- [ ] Update HomePage.vue to use NPM Swiper
- [ ] Configure Vite for code splitting and optimization
- [ ] Run image optimization script
- [ ] Test and measure performance

### Week 2: CSS & Asset Optimization
- [ ] Configure Tailwind CSS purging
- [ ] Optimize font loading
- [ ] Add resource hints (preload, prefetch, preconnect)
- [ ] Implement lazy loading for below-fold images
- [ ] Test and measure performance

### Week 3: Code & Component Optimization
- [ ] Lazy load non-critical components
- [ ] Optimize scroll handlers
- [ ] Remove unused code (pricing section)
- [ ] Minimize JavaScript execution
- [ ] Test and measure performance

### Week 4: Server & Final Tuning
- [ ] Enable HTTP/2 and Brotli compression
- [ ] Set optimal cache headers
- [ ] Consider CDN for static assets
- [ ] Final performance audit
- [ ] A/B testing

## Expected Results

### Current Estimated Scores
- **Performance**: 60-70 (estimated based on issues)
- **First Contentful Paint (FCP)**: 2.5-3.5s
- **Largest Contentful Paint (LCP)**: 4-5s
- **Total Blocking Time (TBT)**: 500-800ms
- **Cumulative Layout Shift (CLS)**: 0.1-0.25

### Target Scores (After Optimization)
- **Performance**: 90+ ✓
- **FCP**: <1.8s ✓
- **LCP**: <2.5s ✓
- **TBT**: <200ms ✓
- **CLS**: <0.1 ✓

## Quick Wins (Can implement today for +15-20 points)

1. **Install Swiper from NPM** (10-15 points)
2. **Enable CSS purging in Tailwind** (3-5 points)
3. **Add image lazy loading** (2-3 points)
4. **Remove console.logs** (1-2 points)

## Monitoring

After each optimization:
1. Run Lighthouse audit
2. Test on mobile device
3. Check PageSpeed Insights
4. Monitor real user metrics (RUM)

## Testing Commands

```bash
# Build for production
npm run build

# Run image optimization
npm run optimize-images-responsive

# Clear caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# Test locally with production build
php artisan serve
```

## Notes

- Always test on actual mobile devices, not just Chrome DevTools
- Target mobile performance first (mobile-first approach)
- Monitor bundle sizes after each change
- Use Lighthouse CI for continuous monitoring
- Consider implementing a performance budget
