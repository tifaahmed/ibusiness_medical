# Quick Start Performance Optimization Guide
## Achieve 90+ Lighthouse Score in 3 Steps

This guide focuses on the **highest impact optimizations** you can implement today.

## Step 1: Replace CDN Swiper with NPM Package (15-20 minutes)
**Impact**: +10-15 performance points

### 1.1 Install Swiper
```bash
npm install swiper@11
```

### 1.2 Update HomePage.vue

**Find and remove** (lines 903-920):
```javascript
const loadSwiperScripts = () => {
  return new Promise((resolve) => {
    if (window.Swiper) {
      resolve();
      return;
    }

    const cssLink = document.createElement('link');
    cssLink.rel = 'stylesheet';
    cssLink.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
    document.head.appendChild(cssLink);

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
    script.onload = () => resolve();
    document.body.appendChild(script);
  });
};
```

**Add at the top of <script setup>** (after line 417):
```javascript
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, Keyboard } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
```

**Update initPartnersSwiper function** (lines 922-964):
```javascript
const initPartnersSwiper = async () => {
  try {
    // Remove: await loadSwiperScripts();
    await nextTick();

    if (!partnersSwiper.value) return;

    partnersSwiperInstance = new Swiper(partnersSwiper.value, {
      modules: [Navigation, Autoplay, Keyboard], // Add this line
      slidesPerView: 2,
      spaceBetween: 20,
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      },
      breakpoints: {
        640: { slidesPerView: 3, spaceBetween: 24 },
        768: { slidesPerView: 4, spaceBetween: 24 },
        1024: { slidesPerView: 5, spaceBetween: 24 },
      },
      navigation: {
        nextEl: '.partners-nav-next',
        prevEl: '.partners-nav-prev',
      },
      keyboard: { enabled: true },
      speed: 600,
    });
  } catch (error) {
    console.error('[PartnersSlider] Error initializing:', error);
  }
};
```

**Update initGallerySwiper function** (lines 966-1021):
```javascript
const initGallerySwiper = async () => {
  try {
    // Remove: await loadSwiperScripts();
    await nextTick();

    if (!gallerySwiper.value) {
      console.error('[GallerySwiper] Container not available');
      return;
    }

    if (gallerySwiperInstance) {
      gallerySwiperInstance.destroy(true, true);
    }

    gallerySwiperInstance = new Swiper(gallerySwiper.value, {
      modules: [Navigation, Pagination, Keyboard], // Add this line
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      initialSlide: partnerGalleryIndex.value,
      centeredSlides: true,
      touchRatio: 1,
      touchAngle: 45,
      grabCursor: true,
      navigation: {
        nextEl: '.gallery-nav-next',
        prevEl: '.gallery-nav-prev',
      },
      pagination: {
        el: '.gallery-pagination',
        clickable: true,
        dynamicBullets: true,
        type: 'bullets',
      },
      keyboard: {
        enabled: true,
        onlyInViewport: true,
      },
      speed: 400,
      on: {
        slideChange: function() {
          const realIndex = this.realIndex;
          console.log('[GallerySwiper] Slide changed to:', realIndex + 1);
        },
        init: function() {
          console.log('[GallerySwiper] Initialized with', this.slides.length, 'slides');
        }
      }
    });

    console.log('[GallerySwiper] ✅ Gallery swiper initialized successfully');
  } catch (error) {
    console.error('[GallerySwiper] ❌ Error initializing:', error);
  }
};
```

## Step 2: Optimize Vite Build Configuration (5 minutes)
**Impact**: +5-10 performance points

### 2.1 Backup current config
```bash
cp vite.config.js vite.config.backup.js
```

### 2.2 Replace with optimized config
```bash
cp vite.config.optimized.js vite.config.js
```

### 2.3 Rebuild assets
```bash
npm run build
```

## Step 3: Optimize Tailwind CSS (10 minutes)
**Impact**: +3-5 performance points

### 3.1 Update tailwind.config.js

**Replace the entire file with**:
```javascript
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#F9F6F1',
                    100: '#E6D3B1',
                    500: '#B89B6A',
                    600: '#C6A76C',
                    700: '#1E3943',
                    800: '#13292F',
                }
            }
        },
    },

    plugins: [forms, typography],

    // Production optimizations
    ...(process.env.NODE_ENV === 'production' && {
        purge: {
            enabled: true,
            content: [
                './resources/views/**/*.blade.php',
                './resources/js/**/*.vue',
                './resources/js/**/*.js',
            ],
            options: {
                safelist: [
                    // Swiper classes
                    /^swiper/,
                    // Animation classes that might be added dynamically
                    'animate-fade-in',
                    'animate-float',
                    // Any other dynamic classes
                ],
            }
        }
    })
};
```

### 3.2 Rebuild
```bash
npm run build
```

## Step 4: Add Image Lazy Loading (15 minutes)
**Impact**: +8-12 performance points

### 4.1 Update HeroSlider component
Find: `resources/js/Pages/Guest/_components/HeroSlider.vue`

Add `loading="lazy"` to all `<img>` tags that are not in the first slide:
```vue
<img
  :src="slide.image"
  :alt="slide.alt"
  loading="lazy"  <!-- Add this -->
  class="..."
>
```

### 4.2 Update partner images in HomePage.vue
**Replace the partner images section** (lines 742-757) with:
```javascript
const partners = [
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.09 PM.jpeg',
  // ... rest of partners
];

// Update the template to add lazy loading
// In the template section (line 239), update:
<img
  :src="partner"
  :alt="`Partner ${index + 1}`"
  class="max-w-full max-h-20 object-contain"
  loading="lazy"  <!-- Add this attribute -->
  @error="handleImageError"
>
```

### 4.3 Update testimonial avatars
**Replace external avatars** (lines 768, 775, 782) with local placeholders:
```javascript
const testimonials = computed(() => {
  const t = page.props.translations?.home?.testimonials || {};
  const role = t.role || 'Patient';

  return [
    {
      name: t.testimonial_1?.name || 'Ahmed Mohamed',
      role: role,
      text: t.testimonial_1?.text || '...',
      avatar: '/images/avatars/avatar-1.jpg', // Changed from pravatar.cc
      rating: t.testimonial_1?.rating || 5
    },
    {
      name: t.testimonial_2?.name || 'Sara Abdullah',
      role: role,
      text: t.testimonial_2?.text || '...',
      avatar: '/images/avatars/avatar-2.jpg', // Changed from pravatar.cc
      rating: t.testimonial_2?.rating || 5
    },
    {
      name: t.testimonial_3?.name || 'Khaled Ali',
      role: role,
      text: t.testimonial_3?.text || '...',
      avatar: '/images/avatars/avatar-3.jpg', // Changed from pravatar.cc
      rating: t.testimonial_3?.rating || 5
    }
  ];
});
```

Create placeholder avatars:
```bash
mkdir -p public/images/avatars
# Add 3 small avatar images (100x100px) to this directory
```

## Step 5: Build and Test (5 minutes)

### 5.1 Clear all caches
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### 5.2 Rebuild production assets
```bash
npm run build
```

### 5.3 Test the changes
```bash
# If using local development
php artisan serve

# Open browser and test:
# - Swiper functionality works
# - Images load properly with lazy loading
# - No console errors
```

## Measuring Results

### Before optimization:
1. Open Chrome DevTools
2. Go to Lighthouse tab
3. Run Mobile audit
4. Note the Performance score

### After optimization:
1. Rebuild: `npm run build`
2. Clear browser cache (Ctrl+Shift+Delete)
3. Run Lighthouse audit again
4. Compare scores

**Expected improvements**:
- Performance: +18-27 points
- FCP: -0.5 to -1.0s
- LCP: -1.0 to -1.5s
- TBT: -200 to -400ms

## Verification Checklist

After implementation, verify:
- [ ] No CDN requests to jsdelivr.net or cdnjs.com for Swiper
- [ ] Bundle size reduced (check Network tab)
- [ ] All images have `loading="lazy"` except first-viewport images
- [ ] No external avatar requests to pravatar.cc
- [ ] Swiper sliders work correctly on both desktop and mobile
- [ ] No JavaScript errors in console
- [ ] Performance score improved by 15-25 points

## Troubleshooting

### Swiper not working after changes
- Check browser console for errors
- Ensure `modules: [Navigation, Pagination, ...]` is added to Swiper config
- Clear browser cache completely

### Build errors
```bash
# Remove node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run build
```

### CSS not optimized
- Ensure `NODE_ENV=production` when building
- Check that Tailwind purge is enabled
- Verify content paths in tailwind.config.js

## Next Steps

After completing these quick wins:
1. Review the full PERFORMANCE-OPTIMIZATION-PLAN.md
2. Implement Priority 3-5 optimizations
3. Set up continuous performance monitoring
4. Consider implementing a CDN for static assets

## Support

If you encounter issues:
1. Check browser console for errors
2. Review build output for warnings
3. Test in incognito mode (no extensions)
4. Compare bundle sizes before/after in `public/build/manifest.json`
