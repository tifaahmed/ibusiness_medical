<template>
  <div
    class="offers-carousel-container"
    @mouseenter="isPaused = true"
    @mouseleave="isPaused = false"
  >
    <link
      href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Raleway:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />

    <!-- Subtle pattern overlay -->
    <div class="pattern-overlay" />

    <!-- Decorative gold line -->
    <div class="decorative-line-top" />

 

 

    <!-- Main Carousel Card -->
    <div class="carousel-wrapper">
      <div class="carousel-card" :class="{ 'is-animating': isAnimating }" :style="cardStyles">
        <div class="card-content">
          <!-- Image Side -->
          <div class="image-section">
            <img :src="currentOffer.image" :alt="currentOffer.title" class="offer-image" />
            <div class="image-overlay" />

            <!-- Badge -->
            <div class="offer-badge">{{ currentOffer.badge }}</div>

            <!-- Discount Circle -->
            <div class="discount-circle">
              <span class="discount-value">{{ currentOffer.discount }}</span>
              <span class="discount-label">OFF</span>
            </div>

            <!-- Logo watermark -->
            <div class="watermark-logo">
              <img :src="$page.props.appLogo" alt="ASH Health Care" />
            </div>
          </div>

          <!-- Content Side -->
          <div class="content-section">
            <span class="offer-subtitle">{{ currentOffer.subtitle }}</span>
            <h3 class="offer-title">{{ currentOffer.title }}</h3>
            <div class="offer-description" v-html="currentOffer.description"></div>

            <!-- Price -->
            <div class="price-container">
              <span class="sale-price">{{ currentOffer.salePrice }}</span>
              <span class="original-price">{{ currentOffer.originalPrice }}</span>
            </div>

            <!-- CTA Button -->
            <button class="cta-button" @click="openModal" @mouseenter="hoveredButton = true" @mouseleave="hoveredButton = false">
              {{ translations.book_now || 'Book Now' }} →
            </button>
          </div>
        </div>
      </div>

      <!-- Navigation Arrows -->
      <button
        class="nav-button nav-prev"
        @click="prev"
        @mouseenter="hoveredPrev = true"
        @mouseleave="hoveredPrev = false"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          style="transform: rotate(180deg)"
        >
          <path d="M5 12h14M12 5l7 7-7 7" />
        </svg>
      </button>
      <button
        class="nav-button nav-next"
        @click="next"
        @mouseenter="hoveredNext = true"
        @mouseleave="hoveredNext = false"
      >
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M5 12h14M12 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Dots -->
    <div class="dots-container">
      <button
        v-for="(o, i) in offers"
        :key="o.id"
        class="dot"
        :class="{ active: currentIndex === i }"
        @click="goToSlide(i, i > currentIndex ? 'right' : 'left')"
      />
    </div>

    <!-- Partners Section -->
    <div v-if="partnerSlides && partnerSlides.length > 0" class="partners-section">
      <h3 class="partners-title">{{ partnersTitle }}</h3>

      <!-- Partners Slider -->
      <div class="partners-slider-wrapper">
        <div class="swiper partners-swiper" ref="partnersSwiper">
          <div class="swiper-wrapper">
            <div
              v-for="(slide, index) in partnerSlides"
              :key="index"
              class="swiper-slide"
            >
              <div class="partner-slide" @click="openPartnerModal(slide)">
                <img
                  :src="slide.image"
                  :alt="slide.name || `Partner ${index + 1}`"
                  class="partner-image"
                  loading="lazy"
                />
              </div>
            </div>
          </div>
          <!-- Navigation arrows -->
          <div class="swiper-button-next partners-nav-next"></div>
          <div class="swiper-button-prev partners-nav-prev"></div>
        </div>
      </div>
    </div>

    <!-- Bottom decorative line -->
    <div class="decorative-line-bottom" />

    <!-- Offer Details Modal -->
    <Transition name="modal-fade">
      <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
        <div class="modal-container">
          <!-- Close Button -->
          <button class="modal-close" @click="closeModal">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>

          <!-- Modal Content -->
          <div class="modal-content">
            <!-- Image -->
            <div class="modal-image-wrapper">
              <img :src="currentOffer.image" :alt="currentOffer.title" class="modal-image" />
              <div class="modal-badge">{{ currentOffer.badge }}</div>
              <div class="modal-discount">
                <span class="modal-discount-value">{{ currentOffer.discount }}</span>
                <span class="modal-discount-label">OFF</span>
              </div>
            </div>

            <!-- Details -->
            <div class="modal-details">
              <span class="modal-subtitle">{{ currentOffer.subtitle }}</span>
              <h2 class="modal-title">{{ currentOffer.title }}</h2>
              <div class="modal-description" v-html="currentOffer.fullDescription"></div>

              <!-- Contact Information -->
              <div class="modal-contact">
                <div class="modal-contact-item">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <div class="modal-contact-info">
                    <span class="modal-contact-label">{{ translations.call_to_book || 'Call us to book:' }}</span>
                    <a :href="`tel:${currentOffer.phone}`" class="modal-contact-value">{{ currentOffer.phone }}</a>
                  </div>
                </div>
              </div>

              <!-- Pricing -->
              <div class="modal-pricing">
                <div class="modal-price-row">
                  <span class="modal-price-label">{{ translations.original_price || 'Original Price:' }}</span>
                  <span class="modal-price-original">{{ currentOffer.originalPrice }}</span>
                </div>
                <div class="modal-price-row modal-price-highlight">
                  <span class="modal-price-label">{{ translations.special_price || 'Special Price:' }}</span>
                  <span class="modal-price-sale">{{ currentOffer.salePrice }}</span>
                </div>
                <div class="modal-savings">
                  {{ translations.you_save || 'You Save:' }} <strong>{{ calculateSavings(currentOffer) }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Partner Image Modal -->
    <Transition name="modal-fade">
      <div v-if="partnerModalOpen" class="partner-modal-overlay" @click.self="closePartnerModal">
        <div class="partner-modal-container">
          <!-- Close Button -->
          <button class="partner-modal-close" @click="closePartnerModal">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>

          <!-- Partner Image -->
          <div class="partner-modal-image-wrapper">
            <img :src="selectedPartner.image" :alt="selectedPartner.name || 'Partner Logo'" class="partner-modal-image" />
          </div>

          <!-- Partner Details -->
          <div v-if="selectedPartner.name || selectedPartner.description || (selectedPartner.phones && selectedPartner.phones.length)" class="partner-modal-details">
            <h3 v-if="selectedPartner.name" class="partner-modal-title">{{ selectedPartner.name }}</h3>
            <div v-if="selectedPartner.description" class="partner-modal-description" v-html="selectedPartner.description"></div>

            <!-- Phones -->
            <div v-if="selectedPartner.phones && selectedPartner.phones.length" class="partner-modal-phones">
              <div v-for="(phone, idx) in selectedPartner.phones" :key="idx" class="partner-phone-item">
                <a
                  v-if="phone.type === 'whatsapp'"
                  :href="`https://wa.me/${phone.number.replace(/[^0-9]/g, '')}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="partner-phone-link partner-phone-whatsapp"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                  {{ phone.number }}
                </a>
                <a
                  v-else
                  :href="`tel:${phone.number}`"
                  class="partner-phone-link partner-phone-call"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  {{ phone.number }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Swiper from 'swiper';
import { Navigation, Autoplay, Keyboard } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';

const props = defineProps({
  offersData: {
    type: Array,
    default: () => []
  },
  translations: {
    type: Object,
    default: () => ({})
  },
  partners: {
    type: Array,
    default: () => []
  },
  partnersTitle: {
    type: String,
    default: 'Contracts'
  },
  contracts: {
    type: Array,
    default: () => []
  }
});

const COLORS = {
  gold: '#B89B6A',
  goldLight: '#C6A76C',
  teal: '#1E3943',
  tealDark: '#13292F',
  beige: '#E6D3B1',
  ivory: '#F9F6F1',
  gray: '#A0A0A0',
  white: '#FFFFFF',
};

// Transform database offers to carousel format
const offers = computed(() => {
  if (!props.offersData || props.offersData.length === 0) {
    return [];
  }

  return props.offersData.map(offer => {
    // Get translated title and description based on current locale
    const getTranslation = (field) => {
      if (typeof field === 'object') {
        return field.en || field.ar || Object.values(field)[0] || '';
      }
      return field || '';
    };

    return {
      id: offer.id,
      title: getTranslation(offer.title),
      subtitle: offer.has_discount ? (props.translations.special_offer || 'Special Offer') : (props.translations.available_now || 'Available Now'),
      discount: offer.discount_percentage ? `${Math.round(offer.discount_percentage)}%` : '0%',
      description: getTranslation(offer.short_description) || getTranslation(offer.full_description)?.substring(0, 150) + '...' || (props.translations.default_description || 'Special medical test offer'),
      fullDescription: getTranslation(offer.full_description) || getTranslation(offer.short_description) || (props.translations.default_description || 'Special medical test offer'),
      phone: offer.phone || '01156385251',
      originalPrice: offer.old_price ? `${offer.old_price} EGP` : `${offer.price} EGP`,
      salePrice: `${offer.price} EGP`,
      badge: offer.has_discount ? (props.translations.sale_badge || 'SALE') : (props.translations.new_badge || 'NEW'),
      image: offer.image || 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
      thumbnail: offer.thumbnail || offer.image || 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
    };
  });
});

const currentIndex = ref(0);
const isAnimating = ref(false);
const direction = ref(null);
const isPaused = ref(false);
const countdown = ref({ h: 23, m: 59, s: 42 });
const hoveredButton = ref(false);
const hoveredPrev = ref(false);
const hoveredNext = ref(false);
const modalOpen = ref(false);
const partnersSwiper = ref(null);
const partnerModalOpen = ref(false);
const selectedPartner = ref({ image: '', name: '', description: '', phones: [] });

// Build partner slides from contracts data or fallback to partners image array
const partnerSlides = computed(() => {
  if (props.contracts && props.contracts.length > 0) {
    return props.contracts
      .filter(c => c.image)
      .map(c => ({
        image: c.image,
        name: typeof c.name === 'string' ? c.name : (c.name?.ar || c.name?.en || ''),
        description: typeof c.description === 'string' ? c.description : (c.description?.ar || c.description?.en || ''),
        phones: Array.isArray(c.phones) ? c.phones : [],
      }));
  }
  if (props.partners && props.partners.length > 0) {
    return props.partners.map(p => ({
      image: typeof p === 'string' ? p : (p.image || ''),
      name: typeof p === 'object' ? (p.name || '') : '',
      description: typeof p === 'object' ? (p.description || '') : '',
    }));
  }
  return [];
});

let countdownInterval = null;
let autoplayInterval = null;
let partnersSwiperInstance = null;

const currentOffer = computed(() => offers.value[currentIndex.value]);

const cardStyles = computed(() => {
  const styles = {};
  if (isAnimating.value) {
    styles.opacity = 0;
    styles.transform = `translateX(${direction.value === 'right' ? '-30px' : '30px'}) scale(0.98)`;
  } else {
    styles.opacity = 1;
    styles.transform = 'translateX(0) scale(1)';
  }
  return styles;
});

const pad = (n) => String(n).padStart(2, '0');

const goToSlide = (index, dir) => {
  if (isAnimating.value) return;
  direction.value = dir;
  isAnimating.value = true;
  setTimeout(() => {
    currentIndex.value = index;
    setTimeout(() => (isAnimating.value = false), 50);
  }, 300);
};

const next = () => goToSlide((currentIndex.value + 1) % offers.value.length, 'right');
const prev = () => goToSlide((currentIndex.value - 1 + offers.value.length) % offers.value.length, 'left');

const openModal = () => {
  modalOpen.value = true;
  document.body.style.overflow = 'hidden';
};

const closeModal = () => {
  modalOpen.value = false;
  document.body.style.overflow = '';
};

const calculateSavings = (offer) => {
  const original = parseFloat(offer.originalPrice.replace(/[^\d.]/g, ''));
  const sale = parseFloat(offer.salePrice.replace(/[^\d.]/g, ''));
  const savings = original - sale;
  return `${savings} EGP`;
};

const openPartnerModal = (slide) => {
  selectedPartner.value = slide;
  partnerModalOpen.value = true;
  document.body.style.overflow = 'hidden';
};

const closePartnerModal = () => {
  partnerModalOpen.value = false;
  selectedPartner.value = { image: '', name: '', description: '', phones: [] };
  document.body.style.overflow = '';
};

const initPartnersSwiper = async () => {
  try {
    await nextTick();

    if (!partnersSwiper.value) return;

    partnersSwiperInstance = new Swiper(partnersSwiper.value, {
      modules: [Navigation, Autoplay, Keyboard],
      slidesPerView: 3,
      spaceBetween: 16,
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      },
      breakpoints: {
        480: {
          slidesPerView: 4,
          spaceBetween: 18,
        },
        640: {
          slidesPerView: 5,
          spaceBetween: 20,
        },
        768: {
          slidesPerView: 6,
          spaceBetween: 22,
        },
        1024: {
          slidesPerView: 8,
          spaceBetween: 24,
        },
        1280: {
          slidesPerView: 10,
          spaceBetween: 24,
        },
      },
      navigation: {
        nextEl: '.partners-nav-next',
        prevEl: '.partners-nav-prev',
      },
      keyboard: {
        enabled: true,
      },
      speed: 600,
    });
  } catch (error) {
    console.error('[PartnersSlider] Error initializing:', error);
  }
};

onMounted(() => {
  // Countdown timer
  countdownInterval = setInterval(() => {
    countdown.value = ((prev) => {
      if (prev.s > 0) return { ...prev, s: prev.s - 1 };
      if (prev.m > 0) return { ...prev, m: prev.m - 1, s: 59 };
      if (prev.h > 0) return { ...prev, h: prev.h - 1, m: 59, s: 59 };
      return { h: 23, m: 59, s: 59 };
    })(countdown.value);
  }, 1000);

  // Autoplay
  const startAutoplay = () => {
    autoplayInterval = setInterval(() => {
      if (!isPaused.value) {
        next();
      }
    }, 5000);
  };
  startAutoplay();

  // Initialize partners swiper
  if (partnerSlides.value && partnerSlides.value.length > 0) {
    initPartnersSwiper();
  }
});

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval);
  if (autoplayInterval) clearInterval(autoplayInterval);
  if (partnersSwiperInstance) {
    partnersSwiperInstance.destroy(true, true);
  }
});
</script>

<style scoped>
.offers-carousel-container {
  min-height: 100vh;
  background: linear-gradient(165deg, #13292F 0%, #1E3943 40%, #264f5e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-family: 'Libre Baskerville', 'Playfair Display', Georgia, serif;
  padding: 50px 20px;
  overflow: hidden;
  position: relative;
}

@media (max-width: 768px) {
  .offers-carousel-container {
    padding: 30px 15px;
    min-height: auto;
  }
}

.pattern-overlay {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 20% 80%, #B89B6A08 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, #B89B6A06 0%, transparent 50%);
  pointer-events: none;
}

.decorative-line-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #B89B6A, transparent);
}

.decorative-line-bottom {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #B89B6A40, transparent);
}

/* Header Section */
.header-section {
  text-align: center;
  margin-bottom: 40px;
  position: relative;
  z-index: 2;
}

@media (max-width: 768px) {
  .header-section {
    margin-bottom: 24px;
  }
}

.logo-image {
  width: 100px;
  height: auto;
  margin-bottom: 20px;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
  border-radius: 12px;
  background: white;
  padding: 8px;
}

@media (max-width: 768px) {
  .logo-image {
    width: 70px;
    margin-bottom: 15px;
  }
}

.badge-container {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #B89B6A18;
  border-radius: 100px;
  padding: 8px 22px;
  margin-bottom: 18px;
  border: 1px solid #B89B6A30;
}

@media (max-width: 768px) {
  .badge-container {
    padding: 6px 16px;
    margin-bottom: 12px;
  }
}

.badge-container svg {
  width: 16px;
  height: 16px;
  color: #B89B6A;
}

.badge-text {
  color: #B89B6A;
  font-size: 12px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.main-title {
  font-family: 'Libre Baskerville', serif;
  font-size: clamp(28px, 4.5vw, 48px);
  font-weight: 700;
  color: #F9F6F1;
  margin: 0 0 12px;
  line-height: 1.2;
  letter-spacing: -0.01em;
}

@media (max-width: 768px) {
  .main-title {
    font-size: clamp(20px, 5vw, 28px);
    margin: 0 0 8px;
  }
}

.title-highlight {
  color: #B89B6A;
  font-style: italic;
}

.subtitle {
  color: #E6D3B1;
  font-size: 15px;
  margin: 0;
  max-width: 500px;
  font-family: 'Raleway', sans-serif;
  font-weight: 400;
  opacity: 0.8;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .subtitle {
    font-size: 13px;
    line-height: 1.5;
  }
}

/* Countdown Timer */
.countdown-timer {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 36px;
  background: #13292Fcc;
  border-radius: 14px;
  padding: 12px 26px;
  border: 1px solid #B89B6A25;
  position: relative;
  z-index: 2;
  backdrop-filter: blur(10px);
}

@media (max-width: 768px) {
  .countdown-timer {
    margin-bottom: 24px;
    padding: 8px 16px;
    gap: 6px;
  }
}

.countdown-timer svg {
  width: 15px;
  height: 15px;
  color: #E6D3B1;
}

.countdown-label {
  color: #E6D3B1;
  font-size: 13px;
  font-family: 'Raleway', sans-serif;
  font-weight: 500;
  opacity: 0.7;
}

@media (max-width: 768px) {
  .countdown-label {
    font-size: 11px;
  }
}

.countdown-value {
  background: #B89B6A15;
  border: 1px solid #B89B6A25;
  border-radius: 8px;
  padding: 6px 10px;
  font-family: 'Raleway', monospace;
  font-size: 17px;
  font-weight: 700;
  color: #B89B6A;
  min-width: 40px;
  text-align: center;
  letter-spacing: 0.05em;
}

@media (max-width: 768px) {
  .countdown-value {
    padding: 4px 8px;
    font-size: 14px;
    min-width: 32px;
  }
}

.countdown-separator {
  color: #B89B6A50;
  font-weight: 700;
  font-size: 16px;
}

/* Carousel */
.carousel-wrapper {
  position: relative;
  width: 100%;
  max-width: 920px;
  z-index: 2;
}

.carousel-card {
  background: linear-gradient(135deg, #13292Fee, #1E3943dd);
  border-radius: 24px;
  border: 1px solid #B89B6A20;
  overflow: hidden;
  backdrop-filter: blur(20px);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35), inset 0 1px 0 #B89B6A10;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.carousel-card.is-animating {
  opacity: 0;
}

.card-content {
  display: flex;
  flex-wrap: wrap;
}

/* Image Section */
.image-section {
  flex: 1 1 380px;
  min-height: 340px;
  position: relative;
  overflow: hidden;
}

@media (max-width: 768px) {
  .image-section {
    min-height: 200px;
    max-height: 200px;
  }
}

.offer-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
}

.image-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #13292F30 0%, #1E394370 100%);
}

.offer-badge {
  position: absolute;
  top: 20px;
  left: 20px;
  background: #B89B6A;
  color: #13292F;
  padding: 6px 16px;
  border-radius: 8px;
  font-size: 11px;
  font-family: 'Raleway', sans-serif;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  box-shadow: 0 4px 16px #B89B6A50;
}

.discount-circle {
  position: absolute;
  bottom: 24px;
  right: 24px;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: #13292Fcc;
  backdrop-filter: blur(12px);
  border: 2px solid #B89B6A60;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.discount-value {
  font-family: 'Libre Baskerville', serif;
  font-size: 26px;
  font-weight: 700;
  color: #B89B6A;
  line-height: 1;
}

.discount-label {
  font-size: 9px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  color: #E6D3B1;
  letter-spacing: 0.15em;
  opacity: 0.8;
}

.watermark-logo {
  position: absolute;
  top: 20px;
  right: 20px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 8px;
  padding: 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.watermark-logo img {
  width: 36px;
  height: auto;
  display: block;
  border-radius: 4px;
}

/* Content Section */
.content-section {
  flex: 1 1 360px;
  padding: 44px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.offer-subtitle {
  color: #B89B6A;
  font-size: 11px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 10px;
}

.offer-title {
  font-family: 'Libre Baskerville', serif;
  font-size: clamp(24px, 3.2vw, 34px);
  font-weight: 700;
  color: #F9F6F1;
  margin: 0 0 14px;
  line-height: 1.2;
}

.offer-description {
  color: #E6D3B1;
  font-size: 14px;
  line-height: 1.7;
  margin: 0 0 28px;
  font-family: 'Raleway', sans-serif;
  font-weight: 400;
  opacity: 0.7;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  white-space: normal;
}

.price-container {
  display: flex;
  align-items: baseline;
  gap: 14px;
  margin-bottom: 32px;
}

.sale-price {
  font-family: 'Libre Baskerville', serif;
  font-size: 32px;
  font-weight: 700;
  color: #B89B6A;
}

.original-price {
  font-size: 16px;
  font-family: 'Raleway', sans-serif;
  color: #A0A0A0;
  text-decoration: line-through;
  font-weight: 500;
}

.cta-button {
  background: linear-gradient(135deg, #B89B6A, #C6A76C);
  color: #13292F;
  border: none;
  border-radius: 12px;
  padding: 16px 38px;
  font-size: 14px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  cursor: pointer;
  align-self: flex-start;
  transition: all 0.3s ease;
  box-shadow: 0 8px 28px #B89B6A35;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.cta-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 36px #B89B6A50;
}

/* Navigation Buttons */
.nav-button {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: #13292Fdd;
  border: 1px solid #B89B6A30;
  color: #B89B6A;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.25s ease;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.nav-prev {
  left: -22px;
}

.nav-next {
  right: -22px;
}

.nav-button:hover {
  background: #B89B6A;
  color: #13292F;
  transform: translateY(-50%) scale(1.1);
}

/* Dots */
.dots-container {
  display: flex;
  gap: 10px;
  margin-top: 32px;
  position: relative;
  z-index: 2;
}

@media (max-width: 768px) {
  .dots-container {
    margin-top: 20px;
    gap: 6px;
  }
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 100px;
  background: #E6D3B130;
  border: none;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 0;
}

.dot.active {
  width: 36px;
  background: #B89B6A;
  box-shadow: 0 0 14px #B89B6A50;
}

/* Responsive */
@media (max-width: 768px) {
  .carousel-wrapper {
    max-width: 95%;
  }

  .content-section {
    padding: 24px 20px;
  }

  .offer-title {
    font-size: 20px;
    margin-bottom: 10px;
  }

  .offer-description {
    font-size: 13px;
    margin-bottom: 20px;
    line-height: 1.5;
  }

  .price-container {
    margin-bottom: 20px;
  }

  .sale-price {
    font-size: 24px;
  }

  .original-price {
    font-size: 14px;
  }

  .cta-button {
    padding: 12px 28px;
    font-size: 13px;
  }

  .nav-button {
    width: 40px;
    height: 40px;
  }

  .nav-prev {
    left: -16px;
  }

  .nav-next {
    right: -16px;
  }

  .discount-circle {
    width: 70px;
    height: 70px;
    bottom: 16px;
    right: 16px;
  }

  .discount-value {
    font-size: 22px;
  }

  .discount-label {
    font-size: 8px;
  }

  .offer-badge {
    top: 12px;
    left: 12px;
    padding: 4px 12px;
    font-size: 10px;
  }

  .watermark-logo {
    top: 12px;
    right: 12px;
  }

  .watermark-logo img {
    width: 28px;
  }
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
  overflow-y: auto;
}

.modal-container {
  position: relative;
  width: 100%;
  max-width: 900px;
  max-height: 90vh;
  background: linear-gradient(135deg, #13292F 0%, #1E3943 100%);
  border-radius: 24px;
  border: 1px solid #B89B6A40;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

.modal-close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  z-index: 10;
  backdrop-filter: blur(10px);
}

.modal-close:hover {
  background: #B89B6A;
  transform: rotate(90deg);
}

.modal-content {
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow-y: auto;
}

@media (min-width: 768px) {
  .modal-content {
    flex-direction: row;
  }
}

.modal-image-wrapper {
  position: relative;
  flex: 1;
  min-height: 300px;
  max-height: 400px;
  overflow: hidden;
}

@media (min-width: 768px) {
  .modal-image-wrapper {
    max-height: none;
  }
}

.modal-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.modal-badge {
  position: absolute;
  top: 20px;
  left: 20px;
  background: #B89B6A;
  color: #13292F;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 12px;
  font-family: 'Raleway', sans-serif;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  box-shadow: 0 4px 16px rgba(184, 155, 106, 0.5);
}

.modal-discount {
  position: absolute;
  bottom: 20px;
  right: 20px;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(19, 41, 47, 0.9);
  backdrop-filter: blur(12px);
  border: 2px solid rgba(184, 155, 106, 0.6);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.modal-discount-value {
  font-family: 'Libre Baskerville', serif;
  font-size: 24px;
  font-weight: 700;
  color: #B89B6A;
  line-height: 1;
}

.modal-discount-label {
  font-size: 9px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  color: #E6D3B1;
  letter-spacing: 0.15em;
  opacity: 0.8;
}

.modal-details {
  flex: 1;
  padding: 40px;
  overflow-y: auto;
}

@media (max-width: 767px) {
  .modal-details {
    padding: 30px 24px;
  }
}

.modal-subtitle {
  color: #B89B6A;
  font-size: 12px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  display: block;
  margin-bottom: 12px;
}

.modal-title {
  font-family: 'Libre Baskerville', serif;
  font-size: 32px;
  font-weight: 700;
  color: #F9F6F1;
  margin: 0 0 16px;
  line-height: 1.2;
}

@media (max-width: 767px) {
  .modal-title {
    font-size: 24px;
  }
}

.modal-description {
  color: #E6D3B1;
  font-size: 15px;
  line-height: 1.7;
  margin: 0 0 28px;
  font-family: 'Raleway', sans-serif;
  opacity: 0.9;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  white-space: normal;
}

.modal-features {
  margin-bottom: 28px;
  padding: 20px;
  background: rgba(184, 155, 106, 0.05);
  border-radius: 12px;
  border: 1px solid rgba(184, 155, 106, 0.15);
}

.modal-features-title {
  font-family: 'Raleway', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: #B89B6A;
  margin: 0 0 16px;
}

.modal-features-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-features-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #F9F6F1;
  font-family: 'Raleway', sans-serif;
  font-size: 14px;
}

.modal-features-list li svg {
  color: #B89B6A;
  flex-shrink: 0;
}

.modal-contact {
  margin-bottom: 28px;
  padding: 20px;
  background: linear-gradient(135deg, rgba(184, 155, 106, 0.1), rgba(198, 167, 108, 0.05));
  border-radius: 12px;
  border: 1px solid rgba(184, 155, 106, 0.25);
}

.modal-contact-item {
  display: flex;
  align-items: center;
  gap: 16px;
}

.modal-contact-item svg {
  color: #B89B6A;
  flex-shrink: 0;
  width: 24px;
  height: 24px;
}

.modal-contact-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-contact-label {
  font-family: 'Raleway', sans-serif;
  font-size: 13px;
  color: #E6D3B1;
  opacity: 0.8;
}

.modal-contact-value {
  font-family: 'Raleway', sans-serif;
  font-size: 20px;
  font-weight: 700;
  color: #B89B6A;
  text-decoration: none;
  letter-spacing: 0.05em;
  transition: all 0.3s ease;
}

.modal-contact-value:hover {
  color: #C6A76C;
  text-decoration: underline;
}

.modal-pricing {
  margin-bottom: 28px;
  padding: 24px;
  background: rgba(184, 155, 106, 0.08);
  border-radius: 12px;
  border: 1px solid rgba(184, 155, 106, 0.2);
}

.modal-price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.modal-price-label {
  font-family: 'Raleway', sans-serif;
  font-size: 14px;
  color: #E6D3B1;
  opacity: 0.8;
}

.modal-price-original {
  font-family: 'Raleway', sans-serif;
  font-size: 16px;
  color: #A0A0A0;
  text-decoration: line-through;
}

.modal-price-highlight {
  padding-top: 12px;
  border-top: 1px solid rgba(184, 155, 106, 0.2);
}

.modal-price-sale {
  font-family: 'Libre Baskerville', serif;
  font-size: 32px;
  font-weight: 700;
  color: #B89B6A;
}

.modal-savings {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(184, 155, 106, 0.2);
  text-align: center;
  color: #E6D3B1;
  font-family: 'Raleway', sans-serif;
  font-size: 14px;
}

.modal-savings strong {
  color: #B89B6A;
  font-weight: 700;
  font-size: 18px;
}

.modal-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (min-width: 640px) {
  .modal-actions {
    flex-direction: row;
  }
}

.modal-btn-primary {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: linear-gradient(135deg, #B89B6A, #C6A76C);
  color: #13292F;
  border: none;
  border-radius: 12px;
  padding: 16px 32px;
  font-size: 16px;
  font-family: 'Raleway', sans-serif;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 8px 28px rgba(184, 155, 106, 0.35);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.modal-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 36px rgba(184, 155, 106, 0.5);
}

.modal-btn-secondary {
  flex: 1;
  background: transparent;
  color: #E6D3B1;
  border: 1px solid rgba(230, 211, 177, 0.3);
  border-radius: 12px;
  padding: 16px 32px;
  font-size: 16px;
  font-family: 'Raleway', sans-serif;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.modal-btn-secondary:hover {
  background: rgba(230, 211, 177, 0.1);
  border-color: rgba(230, 211, 177, 0.5);
}

/* Modal Transitions */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-active .modal-container,
.modal-fade-leave-active .modal-container {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-from .modal-container,
.modal-fade-leave-to .modal-container {
  transform: scale(0.95);
  opacity: 0;
}

/* Scrollbar for modal content */
.modal-content::-webkit-scrollbar {
  width: 8px;
}

.modal-content::-webkit-scrollbar-track {
  background: rgba(184, 155, 106, 0.1);
  border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
  background: rgba(184, 155, 106, 0.4);
  border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
  background: rgba(184, 155, 106, 0.6);
}

/* Partners Section */
.partners-section {
  margin-top: 50px;
  position: relative;
  z-index: 2;
}

@media (max-width: 768px) {
  .partners-section {
    margin-top: 30px;
  }
}

.partners-title {
  text-align: center;
  font-family: 'Libre Baskerville', serif;
  font-size: clamp(24px, 4vw, 36px);
  font-weight: 700;
  color: #F9F6F1;
  margin: 0 0 32px;
  letter-spacing: -0.01em;
}

@media (max-width: 768px) {
  .partners-title {
    font-size: clamp(20px, 5vw, 24px);
    margin: 0 0 20px;
  }
}

.partners-slider-wrapper {
  position: relative;
  padding: 0 50px;
  max-width: 920px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .partners-slider-wrapper {
    padding: 0 40px;
  }
}

:deep(.partners-swiper) {
  width: 100%;
  padding-bottom: 20px;
}

:deep(.partners-swiper .swiper-slide) {
  height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.partner-slide {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(249, 246, 241, 0.05);
  border-radius: 12px;
  border: 1px solid rgba(184, 155, 106, 0.2);
  transition: all 0.3s ease;
  cursor: pointer;
  height: 100%;
  min-height: 100px;
}

.partner-slide:hover {
  background: rgba(249, 246, 241, 0.1);
  border-color: rgba(184, 155, 106, 0.4);
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.partner-image {
  max-width: 100%;
  max-height: 80px;
  object-fit: contain;
  display: block;
}

:deep(.partners-nav-next),
:deep(.partners-nav-prev) {
  color: #B89B6A;
  background: rgba(19, 41, 47, 0.9);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid rgba(184, 155, 106, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

:deep(.partners-nav-next:hover),
:deep(.partners-nav-prev:hover) {
  background: #B89B6A;
  color: #13292F;
  transform: scale(1.1);
}

:deep(.partners-nav-next::after),
:deep(.partners-nav-prev::after) {
  font-size: 18px;
  font-weight: bold;
}

@media (max-width: 768px) {
  .partner-slide {
    padding: 12px;
    min-height: 80px;
  }

  .partner-image {
    max-height: 60px;
  }

  :deep(.partners-nav-next),
  :deep(.partners-nav-prev) {
    width: 32px;
    height: 32px;
  }

  :deep(.partners-nav-next::after),
  :deep(.partners-nav-prev::after) {
    font-size: 14px;
  }
}

/* Partner Image Modal */
.partner-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.9);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 20px;
}

.partner-modal-container {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  background: linear-gradient(135deg, #13292F 0%, #1E3943 100%);
  border-radius: 16px;
  border: 1px solid rgba(184, 155, 106, 0.3);
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
  overflow-y: auto;
  padding: 20px;
}

.partner-modal-close {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(184, 155, 106, 0.2);
  border: 1px solid rgba(184, 155, 106, 0.4);
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  z-index: 10;
  backdrop-filter: blur(10px);
}

.partner-modal-close:hover {
  background: #B89B6A;
  transform: rotate(90deg);
}

.partner-modal-image-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 200px;
}

.partner-modal-image {
  max-width: 100%;
  max-height: 50vh;
  object-fit: contain;
  border-radius: 8px;
}

.partner-modal-details {
  padding: 20px 10px 10px;
  text-align: center;
}

.partner-modal-title {
  font-family: 'Libre Baskerville', serif;
  font-size: 22px;
  font-weight: 700;
  color: #F9F6F1;
  margin: 0 0 10px;
  line-height: 1.3;
}

.partner-modal-description {
  font-family: 'Raleway', sans-serif;
  font-size: 15px;
  color: #E6D3B1;
  line-height: 1.7;
  margin: 0;
  opacity: 0.9;
}

/* Partner Phone Items */
.partner-modal-phones {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
}

.partner-phone-item {
  width: 100%;
  max-width: 280px;
}

.partner-phone-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  border-radius: 10px;
  font-family: 'Raleway', sans-serif;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  width: 100%;
  justify-content: center;
}

.partner-phone-call {
  color: #B89B6A;
  background: rgba(184, 155, 106, 0.1);
  border: 1px solid rgba(184, 155, 106, 0.3);
}

.partner-phone-call:hover {
  background: rgba(184, 155, 106, 0.2);
  border-color: rgba(184, 155, 106, 0.5);
}

.partner-phone-whatsapp {
  color: #25D366;
  background: rgba(37, 211, 102, 0.1);
  border: 1px solid rgba(37, 211, 102, 0.3);
}

.partner-phone-whatsapp:hover {
  background: rgba(37, 211, 102, 0.2);
  border-color: rgba(37, 211, 102, 0.5);
}

/* Partner modal scrollbar */
.partner-modal-container::-webkit-scrollbar {
  width: 6px;
}

.partner-modal-container::-webkit-scrollbar-track {
  background: rgba(184, 155, 106, 0.1);
  border-radius: 3px;
}

.partner-modal-container::-webkit-scrollbar-thumb {
  background: rgba(184, 155, 106, 0.4);
  border-radius: 3px;
}

.partner-modal-container::-webkit-scrollbar-thumb:hover {
  background: rgba(184, 155, 106, 0.6);
}

@media (max-width: 768px) {
  .partner-modal-container {
    padding: 15px;
  }

  .partner-modal-image-wrapper {
    min-height: 150px;
  }

  .partner-modal-image {
    max-height: 40vh;
  }

  .partner-modal-title {
    font-size: 18px;
  }

  .partner-modal-description {
    font-size: 13px;
  }

  .partner-phone-link {
    font-size: 14px;
    padding: 8px 16px;
  }
}
</style>
