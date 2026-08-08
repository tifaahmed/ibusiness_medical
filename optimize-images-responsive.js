#!/usr/bin/env node

/**
 * Responsive Image Optimization Script
 * Creates multiple sizes for each image:
 * - Thumbnail: For small displays (icons, thumbnails)
 * - Medium: For medium displays
 * - Full: Optimized full-size version
 * All with WebP versions
 */

import sharp from 'sharp';
import { readdir, stat, mkdir, rename, unlink } from 'fs/promises';
import { join, extname, dirname, basename } from 'path';
import { existsSync } from 'fs';

const PUBLIC_IMAGES_DIR = './public/images';
const QUALITY = {
  jpeg: 85,
  png: 90,
  webp: 85
};

// Image size configurations
const SIZES = {
  slider: {
    thumbnail: { width: 600, height: 500, suffix: '-thumb' },
    medium: { width: 1200, height: 1000, suffix: '-medium' },
    full: { width: 1920, height: 1080, suffix: '' }
  },
  partners: {
    thumbnail: { width: 150, height: 150, suffix: '-thumb' },
    medium: { width: 300, height: 300, suffix: '-medium' },
    full: { width: 400, height: 400, suffix: '' }
  },
  logo: {
    thumbnail: { width: 100, height: 100, suffix: '-thumb' },
    medium: { width: 250, height: 250, suffix: '-medium' },
    full: { width: 500, height: 500, suffix: '' }
  },
  default: {
    thumbnail: { width: 400, height: 400, suffix: '-thumb' },
    medium: { width: 800, height: 800, suffix: '-medium' },
    full: { width: 1200, height: 1200, suffix: '' }
  }
};

async function getAllImages(dir) {
  const files = [];
  const items = await readdir(dir);

  for (const item of items) {
    const fullPath = join(dir, item);
    const stats = await stat(fullPath);

    if (stats.isDirectory()) {
      files.push(...await getAllImages(fullPath));
    } else if (/\.(jpe?g|png)$/i.test(item) && !item.includes('-thumb') && !item.includes('-medium')) {
      files.push(fullPath);
    }
  }

  return files;
}

function getSizeConfig(filePath) {
  if (filePath.includes('/slider/')) return SIZES.slider;
  if (filePath.includes('/partners/')) return SIZES.partners;
  if (filePath.includes('/logo/')) return SIZES.logo;
  return SIZES.default;
}

async function optimizeImageWithSizes(filePath) {
  const ext = extname(filePath).toLowerCase();
  const dir = dirname(filePath);
  const name = basename(filePath, ext);
  const sizeConfig = getSizeConfig(filePath);

  console.log(`\n📸 Optimizing: ${filePath}`);

  try {
    const image = sharp(filePath);
    const metadata = await image.metadata();

    console.log(`  Original: ${metadata.width}x${metadata.height}, ${(metadata.size / 1024).toFixed(2)}KB`);

    let totalOriginalSize = metadata.size;
    let totalOptimizedSize = 0;

    // Create each size variant
    for (const [sizeName, config] of Object.entries(sizeConfig)) {
      const suffix = config.suffix;
      const outputPath = join(dir, `${name}${suffix}${ext}`);
      const webpPath = join(dir, `${name}${suffix}.webp`);

      // Resize and optimize
      let pipeline = image.clone();

      if (metadata.width > config.width || metadata.height > config.height) {
        pipeline = pipeline.resize(config.width, config.height, {
          fit: 'inside',
          withoutEnlargement: true
        });
      }

      // Save optimized original format
      if (ext === '.jpg' || ext === '.jpeg') {
        await pipeline
          .jpeg({ quality: QUALITY.jpeg, progressive: true })
          .toFile(outputPath + '.tmp');
      } else if (ext === '.png') {
        await pipeline
          .png({ quality: QUALITY.png, compressionLevel: 9 })
          .toFile(outputPath + '.tmp');
      }

      // Create WebP version
      await pipeline
        .webp({ quality: QUALITY.webp, effort: 6 })
        .toFile(webpPath);

      // Get sizes
      const optimizedStats = await stat(outputPath + '.tmp');
      const webpStats = await stat(webpPath);

      totalOptimizedSize += optimizedStats.size;

      console.log(`  ${sizeName.toUpperCase()}: ${(optimizedStats.size / 1024).toFixed(2)}KB ${ext}, ${(webpStats.size / 1024).toFixed(2)}KB WebP`);

      // Replace original with optimized
      if (existsSync(outputPath)) {
        await unlink(outputPath);
      }
      await rename(outputPath + '.tmp', outputPath);
    }

    console.log(`  ✅ Saved ${((1 - totalOptimizedSize / totalOriginalSize) * 100).toFixed(1)}%`);

    return {
      originalSize: totalOriginalSize,
      optimizedSize: totalOptimizedSize
    };

  } catch (error) {
    console.error(`  ❌ Error: ${error.message}`);
    return null;
  }
}

async function main() {
  console.log('🖼️  Responsive Image Optimization Starting...\n');
  console.log('This will create:');
  console.log('- Thumbnail versions for icons/small displays');
  console.log('- Medium versions for tablets');
  console.log('- Full optimized versions');
  console.log('- WebP versions for all sizes\n');

  if (!existsSync(PUBLIC_IMAGES_DIR)) {
    console.error(`Error: ${PUBLIC_IMAGES_DIR} directory not found`);
    process.exit(1);
  }

  const images = await getAllImages(PUBLIC_IMAGES_DIR);
  console.log(`Found ${images.length} images to optimize\n`);

  let totalOriginalSize = 0;
  let totalOptimizedSize = 0;
  let processedCount = 0;

  for (const imagePath of images) {
    const result = await optimizeImageWithSizes(imagePath);
    if (result) {
      totalOriginalSize += result.originalSize;
      totalOptimizedSize += result.optimizedSize;
      processedCount++;
    }
  }

  console.log('\n✅ Optimization Complete!');
  console.log(`Processed: ${processedCount} images`);
  console.log(`Total original size: ${(totalOriginalSize / 1024 / 1024).toFixed(2)}MB`);
  console.log(`Total optimized size: ${(totalOptimizedSize / 1024 / 1024).toFixed(2)}MB`);
  console.log(`Total savings: ${((1 - totalOptimizedSize / totalOriginalSize) * 100).toFixed(1)}%`);
  console.log('\n💡 Tip: Images now have -thumb and -medium versions for responsive loading');
}

main().catch(console.error);
