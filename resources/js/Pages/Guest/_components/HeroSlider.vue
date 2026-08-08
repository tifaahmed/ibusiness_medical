<template>
  <div class="hero-slider-container">
    <div class="swiper" ref="swiperContainer">
      <div class="swiper-wrapper">
        <div 
          class="swiper-slide" 
          v-for="(slide, index) in slides" 
          :key="index"
        >
          <div class="slide-content">
            <div class="image-container animate-float">
              <OptimizedImage
                :src="slide.src"
                :alt="slide.alt"
                img-class="slide-image"
                :loading="index === 0 ? 'eager' : 'lazy'"
                width="1920"
                height="1080"
                sizes="(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px"
                @error="handleImageError(index)"
                @load="handleImageLoad(index)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="swiper-pagination"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import Swiper from 'swiper';
import { Pagination, Autoplay, Keyboard } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';
import OptimizedImage from '@/Components/OptimizedImage.vue';

const slides = [
  { src: '/images/slider/1.png', alt: 'Healthcare Partner 1' },
  { src: '/images/slider/2.png', alt: 'Healthcare Partner 2' },
  { src: '/images/slider/3.png', alt: 'Healthcare Partner 3' },
  { src: '/images/slider/4.png', alt: 'Healthcare Partner 4' },
  { src: '/images/slider/5.png', alt: 'Healthcare Partner 5' },
  { src: '/images/slider/6.png', alt: 'Healthcare Partner 6' },
  { src: '/images/slider/7.png', alt: 'Healthcare Partner 7' },
  { src: '/images/slider/8.png', alt: 'Healthcare Partner 8' },
  { src: '/images/slider/9.png', alt: 'Healthcare Partner 9' },
  { src: '/images/slider/10.png', alt: 'Healthcare Partner 10' }
];

const swiperContainer = ref(null);
let swiperInstance = null;

const handleImageError = (index) => {
  console.error(`[HeroSlider] ❌ Image ${index + 1} failed to load:`, slides[index].src);
};

const handleImageLoad = (index) => {
  console.log(`[HeroSlider] ✅ Image ${index + 1} loaded:`, slides[index].src);
};

const initSwiper = async () => {
  if (!swiperContainer.value) {
    return;
  }

  const container = swiperContainer.value;
  const containerWidth = container.offsetWidth || container.clientWidth || window.innerWidth;
  
  console.log('[HeroSlider] Container dimensions:', {
    width: containerWidth,
    offsetWidth: container.offsetWidth,
    clientWidth: container.clientWidth
  });
  
  // Ensure container has proper width
  if (containerWidth === 0 || !containerWidth) {
    console.warn('[HeroSlider] ⚠️ Container width is 0, retrying...');
    return;
  }

  // Destroy existing instance if any
  if (swiperInstance) {
    swiperInstance.destroy(true, true);
    swiperInstance = null;
  }

  swiperInstance = new Swiper(container, {
    modules: [Pagination, Autoplay, Keyboard],
    slidesPerView: 1,
    spaceBetween: 0,
    loop: true,
    autoplay: {
      delay: 2000,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
      dynamicBullets: true,
    },
    keyboard: {
      enabled: true,
    },
    effect: 'slide',
    speed: 300,
    watchOverflow: true,
    observer: true,
    observeParents: true,
    on: {
      slideChange: function() {
        const realIndex = this.realIndex + 1;
        console.log('[HeroSlider] ▶️ Slide changed to:', realIndex);
      },
      init: function() {
        console.log('[HeroSlider] ✅ Swiper initialized');
        // Force update after init
        this.update();
      }
    }
  });

  console.log('[HeroSlider] ✅ Swiper instance created:', swiperInstance);
  console.log('[HeroSlider] Swiper slides:', swiperInstance.slides?.length);
};

onMounted(async () => {
  console.log('[HeroSlider] 🚀 Component mounted');
  console.log('[HeroSlider] Slides data:', slides);

  try {
    console.log('[HeroSlider] Step 1: Waiting for nextTick...');
    await nextTick();

    console.log('[HeroSlider] Step 2: Swiper container:', swiperContainer.value);

    if (!swiperContainer.value) {
      console.error('[HeroSlider] ❌ Swiper container is null!');
      return;
    }

    console.log('[HeroSlider] Step 3: Initializing Swiper instance...');

    // Wait a bit more to ensure container has dimensions
    setTimeout(() => {
      initSwiper();
    }, 100);

  } catch (error) {
    console.error('[HeroSlider] ❌ Error initializing Swiper slider:', error);
    console.error('[HeroSlider] Error stack:', error.stack);
  }
});

onUnmounted(() => {
  console.log('[HeroSlider] 🛑 Component unmounting');
  if (swiperInstance) {
    swiperInstance.destroy(true, true);
    swiperInstance = null;
  }
});
</script>

<style scoped>
.hero-slider-container {
  position: relative;
  width: 100%;
  max-width: 100%;
  height: 100%;
  min-height: 500px;
  overflow: hidden;
  box-sizing: border-box;
}

:deep(.swiper) {
  width: 100% !important;
  max-width: 100%;
  height: 100%;
  min-height: 500px;
  position: relative;
  overflow: hidden;
}

:deep(.swiper-wrapper) {
  width: 100% !important;
  max-width: 100%;
  height: 100%;
  display: flex;
}

:deep(.swiper-slide) {
  width: 100% !important;
  max-width: 100%;
  height: 100%;
  min-height: 500px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-sizing: border-box;
}

.slide-content {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.image-container {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.slide-image {
  max-width: 100%;
  max-height: 500px;
  width: auto;
  height: auto;
  object-fit: contain;
  filter: drop-shadow(0 25px 50px rgba(0, 0, 0, 0.25));
  user-select: none;
  display: block;
}

/* Floating Animation */
@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-20px);
  }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

/* Pagination */
:deep(.swiper-pagination) {
  bottom: 1.5rem !important;
}

:deep(.swiper-pagination-bullet) {
  width: 8px;
  height: 8px;
  background: rgba(255, 255, 255, 0.4);
  opacity: 1;
  transition: all 0.3s ease;
}

:deep(.swiper-pagination-bullet-active) {
  width: 32px;
  border-radius: 4px;
  background: white;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .hero-slider-container {
    min-height: 400px;
  }

  :deep(.swiper) {
    min-height: 400px;
  }

  :deep(.swiper-slide) {
    min-height: 400px;
  }

  .slide-content {
    padding: 1rem;
  }

  .slide-image {
    max-height: 400px;
  }

  :deep(.swiper-pagination) {
    bottom: 1rem !important;
  }

  .animate-float {
    animation: float 4s ease-in-out infinite;
  }
}
</style>
