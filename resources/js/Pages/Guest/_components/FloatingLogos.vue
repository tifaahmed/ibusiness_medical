<template>
  <div id="floating-logos-container" ref="container"></div>
  
  <!-- Gallery Modal -->
  <Transition name="fade">
    <div 
      v-if="showGallery" 
      class="gallery-modal"
      @click.self="closeGallery"
    >
      <div class="gallery-content">
        <button class="gallery-close" @click="closeGallery">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        
        <button 
          class="gallery-nav gallery-prev" 
          @click="previousImage"
          :disabled="currentImageIndex === 0"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        
        <button 
          class="gallery-nav gallery-next" 
          @click="nextImage"
          :disabled="currentImageIndex === logoImages.length - 1"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        
        <OptimizedImage
          :src="logoImages[currentImageIndex]"
          :alt="`Partner ${currentImageIndex + 1}`"
          img-class="gallery-image"
          loading="lazy"
          @error="handleImageError"
        />
        
        <div class="gallery-info">
          <p class="gallery-counter">{{ currentImageIndex + 1 }} / {{ logoImages.length }}</p>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import OptimizedImage from '@/Components/OptimizedImage.vue';

const container = ref(null);
const logos = ref([]);
let animationId = null;
let centerX = 0;
let centerY = 0;
let radius = 300;

const showGallery = ref(false);
const currentImageIndex = ref(0);

const logoImages = [
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.09 PM.jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.10 PM (3).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.10 PM (2).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.10 PM (1).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.10 PM.jpeg',
  '/images/partners/addbf5f5-9add-48dd-86ab-2ddd3324c56e.jpg',
  '/images/partners/f5e7c8e5-db5a-4e3d-b6a9-decba44638e1.jpg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.11 PM (3).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.11 PM (2).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.11 PM (1).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.11 PM.jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.12 PM (2).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.12 PM (1).jpeg',
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.12 PM.jpeg'
];

class OrbitingLogo {
  constructor(imageSrc, containerEl, index, totalLogos, imageIndex) {
    this.containerEl = containerEl;
    this.index = index;
    this.imageIndex = imageIndex; // Index in logoImages array
    
    this.element = document.createElement('div');
    this.element.className = 'orbiting-logo';
    
    const img = document.createElement('img');
    img.src = imageSrc;
    img.alt = 'Orbiting Logo';
    img.loading = 'lazy';
    img.decoding = 'async';
    img.onerror = () => {
      this.element.style.display = 'none';
    };
    this.element.appendChild(img);
    
    // Circular motion parameters
    this.angle = (index / totalLogos) * Math.PI * 2; // Distribute evenly around circle
    this.angularSpeed = 0.0005 + Math.random() * 0.001; // Different speeds for each logo
    this.radius = radius + (Math.random() - 0.5) * 20; // Smaller variation
    
    // Size - small circles
    this.size = 30 + Math.random() * 20; // 30-50px
    this.baseSize = this.size;
    this.isHovered = false;
    this.isPaused = false;
    this.fixedX = 0;
    this.fixedY = 0;
    
    // Add hover and click event listeners
    this.element.addEventListener('mouseenter', () => this.onHover());
    this.element.addEventListener('mouseleave', () => this.onLeave());
    this.element.addEventListener('click', () => this.onClick());
    
    this.updateElementStyle();
    this.containerEl.appendChild(this.element);
  }
  
  onHover() {
    this.isHovered = true;
    this.isPaused = true;
    
    // Lock position to prevent shaking
    const x = centerX + Math.cos(this.angle) * this.radius;
    const y = centerY + Math.sin(this.angle) * this.radius;
    this.fixedX = x - this.size / 2;
    this.fixedY = y - this.size / 2;
    
    // Use transform scale for smoother animation
    this.element.style.transform = 'scale(2)';
    this.element.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    this.element.style.zIndex = '100';
  }
  
  onLeave() {
    this.isHovered = false;
    this.isPaused = false;
    
    // Return to normal size with smooth transition
    this.element.style.transform = 'scale(1)';
    this.element.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    this.element.style.zIndex = '1';
  }
  
  onClick() {
    // Emit event or use a callback to open gallery
    const event = new CustomEvent('openGallery', { 
      detail: { imageIndex: this.imageIndex } 
    });
    window.dispatchEvent(event);
  }
  
  updateElementStyle() {
    // Calculate position on circle (relative to hero section)
    const x = centerX + Math.cos(this.angle) * this.radius;
    const y = centerY + Math.sin(this.angle) * this.radius;
    
    // If hovered, use fixed position to prevent shaking
    if (this.isHovered) {
      this.element.style.left = `${this.fixedX}px`;
      this.element.style.top = `${this.fixedY}px`;
    } else {
      // Position relative to container (hero section)
      this.element.style.left = `${x - this.size / 2}px`;
      this.element.style.top = `${y - this.size / 2}px`;
    }
    
    this.element.style.width = `${this.size}px`;
    this.element.style.height = `${this.size}px`;
  }
  
  update() {
    // Only update if not paused (hovered)
    if (!this.isPaused) {
      // Update angle for circular motion
      this.angle += this.angularSpeed;
      if (this.angle > Math.PI * 2) {
        this.angle -= Math.PI * 2;
      }
      
      this.updateElementStyle();
    }
  }
  
  updateCenter(newCenterX, newCenterY, newRadius) {
    centerX = newCenterX;
    centerY = newCenterY;
    radius = newRadius;
    
    // Update fixed position if hovered
    if (this.isHovered) {
      const x = centerX + Math.cos(this.angle) * this.radius;
      const y = centerY + Math.sin(this.angle) * this.radius;
      this.fixedX = x - this.size / 2;
      this.fixedY = y - this.size / 2;
      this.updateElementStyle();
    }
  }
}

const calculateHeroCenter = () => {
  // Find the hero section (parent section element)
  const heroSection = container.value?.closest('section');
  if (heroSection && container.value) {
    const rect = heroSection.getBoundingClientRect();
    centerX = rect.width / 2; // Relative to hero section
    centerY = rect.height / 2; // Relative to hero section
    radius = Math.max(rect.width, rect.height) / 3; // Much smaller orbit
    
    // Container is already absolute, positioned relative to hero section
    // Just ensure it fills the hero section
    container.value.style.top = '0';
    container.value.style.left = '0';
    container.value.style.width = '100%';
    container.value.style.height = '100%';
  } else if (container.value) {
    // Fallback: use container's own dimensions
    const rect = container.value.getBoundingClientRect();
    centerX = rect.width / 2;
    centerY = rect.height / 2;
    radius = Math.min(rect.width, rect.height) / 4;
  } else {
    // Final fallback
    centerX = window.innerWidth / 2;
    centerY = window.innerHeight / 3;
    radius = Math.min(window.innerWidth, window.innerHeight) / 6;
  }
};

const openGallery = (imageIndex) => {
  currentImageIndex.value = imageIndex;
  showGallery.value = true;
  document.body.style.overflow = 'hidden';
};

const closeGallery = () => {
  showGallery.value = false;
  document.body.style.overflow = '';
};

const nextImage = () => {
  if (currentImageIndex.value < logoImages.length - 1) {
    currentImageIndex.value++;
  }
};

const previousImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--;
  }
};

const handleImageError = (event) => {
  event.target.style.display = 'none';
};

const initLogos = () => {
  if (!container.value) return;

  calculateHeroCenter();

  let numberOfLogos;
  const screenWidth = window.innerWidth;
  
  if (screenWidth < 640) numberOfLogos = 8;
  else if (screenWidth < 1024) numberOfLogos = 12;
  else if (screenWidth < 1280) numberOfLogos = 16;
  else numberOfLogos = 20;
  
  logos.value = [];
  
  for (let i = 0; i < numberOfLogos; i++) {
    const imageIndex = i % logoImages.length;
    const imageSrc = logoImages[imageIndex];
    logos.value.push(new OrbitingLogo(imageSrc, container.value, i, numberOfLogos, imageIndex));
  }
  
  const animate = () => {
    logos.value.forEach(logo => logo.update());
    animationId = requestAnimationFrame(animate);
  };
  
  animate();
};

const handleResize = () => {
  calculateHeroCenter();
  logos.value.forEach(logo => {
    logo.updateCenter(centerX, centerY, radius);
  });
};

const handleScroll = () => {
  // Update container position on scroll to keep it aligned with hero section
  calculateHeroCenter();
};

onMounted(() => {
  // Use requestIdleCallback to defer initialization until browser is idle
  if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
      initLogos();
    }, { timeout: 1000 });
  } else {
    // Fallback for browsers without requestIdleCallback
    setTimeout(() => {
      initLogos();
    }, 300);
  }
  window.addEventListener('resize', handleResize);
  window.addEventListener('scroll', handleScroll);
  
  // Listen for gallery open event
  window.addEventListener('openGallery', (event) => {
    openGallery(event.detail.imageIndex);
  });
  
  // Keyboard navigation
  const handleKeyPress = (e) => {
    if (!showGallery.value) return;
    
    if (e.key === 'Escape') {
      closeGallery();
    } else if (e.key === 'ArrowRight') {
      nextImage();
    } else if (e.key === 'ArrowLeft') {
      previousImage();
    }
  };
  
  window.addEventListener('keydown', handleKeyPress);
  
  // Cleanup on unmount
  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyPress);
  });
});

onUnmounted(() => {
  if (animationId) {
    cancelAnimationFrame(animationId);
  }
  window.removeEventListener('resize', handleResize);
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('openGallery', () => {});
  // Clean up DOM elements
  if (container.value) {
    container.value.innerHTML = '';
  }
  document.body.style.overflow = '';
});
</script>

<style scoped>
#floating-logos-container {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 150;
  pointer-events: none;
  overflow: hidden;
}

  :deep(.orbiting-logo) {
  position: absolute;
  opacity: 1;
  pointer-events: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: transform, left, top;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
  border-radius: 50%;
  overflow: hidden;
  background: transparent;
  border: 2px solid rgba(184, 155, 106, 0.5);
  cursor: pointer;
  z-index: 151;
}

:deep(.orbiting-logo:hover) {
  z-index: 200;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
  border-color: rgba(184, 155, 106, 0.8);
}

:deep(.orbiting-logo img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

/* Gallery Modal Styles */
.gallery-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.95);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.gallery-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gallery-image {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.gallery-close {
  position: absolute;
  top: -50px;
  right: 0;
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: white;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

.gallery-close:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.5);
  transform: scale(1.1);
}

.gallery-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.3);
  color: white;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  z-index: 10;
}

.gallery-nav:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-50%) scale(1.1);
}

.gallery-nav:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.gallery-prev {
  left: -70px;
}

.gallery-next {
  right: -70px;
}

.gallery-info {
  position: absolute;
  bottom: -50px;
  left: 50%;
  transform: translateX(-50%);
  color: white;
  text-align: center;
}

.gallery-counter {
  background: rgba(255, 255, 255, 0.1);
  padding: 8px 16px;
  border-radius: 20px;
  backdrop-filter: blur(10px);
  font-size: 14px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .gallery-prev {
    left: 10px;
  }
  
  .gallery-next {
    right: 10px;
  }
  
  .gallery-close {
    top: 10px;
    right: 10px;
  }
  
  .gallery-info {
    bottom: 10px;
  }
}
</style>
