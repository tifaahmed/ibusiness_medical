# Image Optimization and Caching Guide

This project includes comprehensive image optimization and caching strategies to improve performance and reduce bandwidth usage.

## Features

### 1. Image Optimization Script

The `optimize-images.js` script automatically:
- Compresses JPEG and PNG images
- Creates WebP versions for modern browsers
- Resizes large images to reasonable dimensions
- Maintains aspect ratios

#### Usage

```bash
npm run optimize-images
```

This will process all images in the `public/images` directory.

#### Optimization Settings

The script uses different maximum dimensions based on image location:
- **Slider images**: 1920x1080px
- **Partner logos**: 400x400px
- **General logos**: 500x500px
- **Other images**: 1200x1200px

#### Quality Settings

- JPEG: 85% quality with progressive encoding
- PNG: 90% quality with maximum compression
- WebP: 85% quality

### 2. Browser Caching

Browser caching is configured via `.htaccess` with the following cache durations:

- **Images** (jpg, png, gif, webp, svg): 1 year
- **CSS and JavaScript**: 1 month
- **Fonts**: 1 year
- **Other static assets**: 1 month

### 3. OptimizedImage Vue Component

A Vue component that automatically serves WebP images with fallback to original formats.

#### Usage

```vue
<script setup>
import OptimizedImage from '@/Components/OptimizedImage.vue';
</script>

<template>
  <OptimizedImage
    src="/images/slider/banner.jpg"
    alt="Banner image"
    img-class="w-full h-auto"
    loading="lazy"
    width="1920"
    height="1080"
  />
</template>
```

#### Props

- **src** (required): Path to the original image
- **alt**: Alt text for accessibility
- **imgClass**: CSS classes to apply to the image
- **loading**: Loading strategy ('lazy' or 'eager', default: 'lazy')
- **width**: Image width attribute
- **height**: Image height attribute

#### How it works

The component:
1. Automatically generates the WebP source path from the original image
2. Uses the `<picture>` element to serve WebP to supporting browsers
3. Falls back to the original format for older browsers
4. Applies lazy loading by default for better performance

### 4. Cache Control Middleware

The `CacheControl` middleware automatically sets appropriate cache headers for different types of responses:

- **Authenticated pages**: No cache (private, no-store)
- **Public pages** (home, about, contact): 1 hour cache
- **API responses**: No cache (must-revalidate)
- **POST/PUT/DELETE requests**: No cache

### 5. Gzip Compression

All text-based assets (HTML, CSS, JS, JSON, XML) are automatically compressed using Gzip to reduce transfer size.

## Best Practices

### When adding new images:

1. **Upload original high-quality images** to `public/images`
2. **Run the optimization script**: `npm run optimize-images`
3. **Use the OptimizedImage component** in your Vue templates
4. **Specify dimensions** when possible for better performance

### Example workflow:

```bash
# 1. Add new images to public/images/slider/
cp new-banner.jpg public/images/slider/

# 2. Optimize all images
npm run optimize-images

# 3. Use in your Vue component
```

```vue
<OptimizedImage
  src="/images/slider/new-banner.jpg"
  alt="New Banner"
  img-class="slider-image"
  width="1920"
  height="1080"
/>
```

## Performance Benefits

### Before Optimization:
- Large image files (500KB - 2MB each)
- No WebP support
- No browser caching
- No compression

### After Optimization:
- Compressed images (50-80% reduction in size)
- WebP versions (additional 25-35% reduction)
- 1-year browser cache for images
- Gzip compression for all text assets
- Lazy loading by default

### Expected Results:
- **70-85% reduction** in image bandwidth usage
- **Faster page load times** due to smaller file sizes
- **Better user experience** with progressive image loading
- **Reduced server bandwidth** costs

## Technical Details

### Cache Headers Applied:

```
# Images
Cache-Control: public, max-age=31536000, immutable

# CSS/JS
Cache-Control: public, max-age=2592000

# HTML Pages (public)
Cache-Control: public, max-age=3600

# Authenticated Content
Cache-Control: private, no-cache, no-store, must-revalidate
```

### Supported Image Formats:

- Input: JPEG, JPG, PNG, GIF
- Output: Original format (optimized) + WebP

## Troubleshooting

### Images not loading after optimization

1. Clear browser cache
2. Check that WebP files were created in the same directory
3. Verify image paths are correct

### Optimization script fails

1. Ensure Node.js dependencies are installed: `npm install`
2. Check that the `public/images` directory exists
3. Verify file permissions for the images directory

### Cache not working

1. Verify Apache modules are enabled: `mod_expires`, `mod_headers`, `mod_deflate`
2. Check `.htaccess` file is being read
3. Clear browser cache and test with developer tools

## Future Enhancements

- Automatic optimization during build process
- CDN integration for static assets
- Responsive image srcset generation
- AVIF format support for even better compression
