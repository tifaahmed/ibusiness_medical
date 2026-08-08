#!/usr/bin/env node

/**
 * Image Optimization Script
 * Optimizes all images in the public/images directory
 * - Compresses JPEGs and PNGs
 * - Creates WebP versions for modern browsers
 * - Maintains original aspect ratios
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

// Maximum dimensions for different image types
const MAX_DIMENSIONS = {
  slider: { width: 1920, height: 1080 },
  partners: { width: 400, height: 400 },
  logo: { width: 500, height: 500 },
  default: { width: 1200, height: 1200 }
};

async function getAllImages(dir) {
  const files = [];
  const items = await readdir(dir);

  for (const item of items) {
    const fullPath = join(dir, item);
    const stats = await stat(fullPath);

    if (stats.isDirectory()) {
      files.push(...await getAllImages(fullPath));
    } else if (/\.(jpe?g|png)$/i.test(item) && !item.includes('.optimized')) {
      files.push(fullPath);
    }
  }

  return files;
}

function getMaxDimensions(filePath) {
  if (filePath.includes('/slider/')) return MAX_DIMENSIONS.slider;
  if (filePath.includes('/partners/')) return MAX_DIMENSIONS.partners;
  if (filePath.includes('/logo/')) return MAX_DIMENSIONS.logo;
  return MAX_DIMENSIONS.default;
}

async function optimizeImage(filePath) {
  const ext = extname(filePath).toLowerCase();
  const dir = dirname(filePath);
  const name = basename(filePath, ext);
  const maxDims = getMaxDimensions(filePath);

  console.log(`\nOptimizing: ${filePath}`);

  try {
    const image = sharp(filePath);
    const metadata = await image.metadata();

    console.log(`  Original: ${metadata.width}x${metadata.height}, ${(metadata.size / 1024).toFixed(2)}KB`);

    // Resize if needed
    let pipeline = image.clone();
    if (metadata.width > maxDims.width || metadata.height > maxDims.height) {
      pipeline = pipeline.resize(maxDims.width, maxDims.height, {
        fit: 'inside',
        withoutEnlargement: true
      });
      console.log(`  Resizing to max ${maxDims.width}x${maxDims.height}`);
    }

    // Optimize and save in original format
    if (ext === '.jpg' || ext === '.jpeg') {
      await pipeline
        .jpeg({ quality: QUALITY.jpeg, progressive: true })
        .toFile(filePath + '.tmp');
    } else if (ext === '.png') {
      await pipeline
        .png({ quality: QUALITY.png, compressionLevel: 9 })
        .toFile(filePath + '.tmp');
    }

    // Create WebP version
    const webpPath = join(dir, `${name}.webp`);
    await pipeline
      .webp({ quality: QUALITY.webp, effort: 6 })
      .toFile(webpPath);

    // Get new file sizes
    const tmpStats = await stat(filePath + '.tmp');
    const webpStats = await stat(webpPath);

    console.log(`  Optimized ${ext}: ${(tmpStats.size / 1024).toFixed(2)}KB (${((1 - tmpStats.size / metadata.size) * 100).toFixed(1)}% reduction)`);
    console.log(`  WebP: ${(webpStats.size / 1024).toFixed(2)}KB (${((1 - webpStats.size / metadata.size) * 100).toFixed(1)}% reduction)`);

    // Replace original with optimized version
    await unlink(filePath);
    await rename(filePath + '.tmp', filePath);

    return {
      originalSize: metadata.size,
      optimizedSize: tmpStats.size,
      webpSize: webpStats.size
    };

  } catch (error) {
    console.error(`  Error optimizing ${filePath}:`, error.message);
    return null;
  }
}

async function main() {
  console.log('🖼️  Image Optimization Starting...\n');
  console.log('This will:');
  console.log('- Compress all JPEG and PNG images');
  console.log('- Create WebP versions for modern browsers');
  console.log('- Resize large images to reasonable dimensions\n');

  if (!existsSync(PUBLIC_IMAGES_DIR)) {
    console.error(`Error: ${PUBLIC_IMAGES_DIR} directory not found`);
    process.exit(1);
  }

  const images = await getAllImages(PUBLIC_IMAGES_DIR);
  console.log(`Found ${images.length} images to optimize\n`);

  let totalOriginalSize = 0;
  let totalOptimizedSize = 0;

  for (const imagePath of images) {
    const result = await optimizeImage(imagePath);
    if (result) {
      totalOriginalSize += result.originalSize;
      totalOptimizedSize += result.optimizedSize;
    }
  }

  console.log('\n✅ Optimization Complete!');
  console.log(`Total original size: ${(totalOriginalSize / 1024 / 1024).toFixed(2)}MB`);
  console.log(`Total optimized size: ${(totalOptimizedSize / 1024 / 1024).toFixed(2)}MB`);
  console.log(`Total savings: ${((1 - totalOptimizedSize / totalOriginalSize) * 100).toFixed(1)}%`);
}

main().catch(console.error);
