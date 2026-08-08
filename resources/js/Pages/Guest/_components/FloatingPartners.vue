<template>
  <div class="floating-partners-container" ref="container"></div>

  <!-- Left-Side Details Panel -->
  <Transition name="slide-panel">
    <div
      v-if="showPanel"
      class="details-overlay"
      @click.self="closePanel"
    >
      <div class="details-panel" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <button class="panel-close" @click="closePanel">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div v-if="selectedContract" class="panel-content">
          <!-- Logo Image -->
          <div class="panel-image-wrapper">
            <img
              v-if="selectedContract.image"
              :src="selectedContract.image"
              :alt="getContractName(selectedContract)"
              class="panel-image"
            />
            <div v-else class="panel-image-placeholder">
              <svg class="w-16 h-16 text-[#B89B6A]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
          </div>

          <!-- Title -->
          <h3 class="panel-title">{{ getContractName(selectedContract) }}</h3>

          <!-- Description -->
          <div v-if="getContractDescription(selectedContract)" class="panel-description" v-html="getContractDescription(selectedContract)"></div>

          <!-- Phones -->
          <div v-if="selectedContract.phones && selectedContract.phones.length" class="panel-phones">
            <div v-for="(phone, idx) in selectedContract.phones" :key="idx" class="panel-phone-item">
              <a
                v-if="phone.type === 'whatsapp'"
                :href="`https://wa.me/${phone.number.replace(/[^0-9]/g, '')}`"
                target="_blank"
                rel="noopener noreferrer"
                class="panel-phone-link panel-phone-whatsapp"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                {{ phone.number }}
              </a>
              <a
                v-else
                :href="`tel:${phone.number}`"
                class="panel-phone-link panel-phone-call"
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
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  contracts: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');

const container = ref(null);
const logos = ref([]);
let animationId = null;

const showPanel = ref(false);
const selectedContract = ref(null);

// Fallback hardcoded logos when no contracts data
const fallbackLogos = [
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

// Build the logo sources array from contracts or fallback
const getLogoSources = () => {
  if (props.contracts && props.contracts.length > 0) {
    const sources = props.contracts.map(c => ({
      image: c.image || '',
      contract: c,
    })).filter(c => c.image);
    if (sources.length > 0) return sources;
  }
  return fallbackLogos.map(src => ({
    image: src,
    contract: null,
  }));
};

const getContractName = (contract) => {
  if (!contract || !contract.name) return '';
  const name = contract.name;
  if (typeof name === 'string') return name;
  if (typeof name === 'object') {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getContractDescription = (contract) => {
  if (!contract || !contract.description) return '';
  const desc = contract.description;
  if (typeof desc === 'string') return desc;
  if (typeof desc === 'object') {
    return desc[locale.value] || desc['ar'] || desc['en'] || Object.values(desc)[0] || '';
  }
  return '';
};

class FloatingPartner {
  constructor(imageSrc, containerEl, sourceIndex, heroHeight, contractData) {
    this.containerEl = containerEl;
    this.sourceIndex = sourceIndex;
    this.heroHeight = heroHeight;
    this.contractData = contractData;

    this.element = document.createElement('div');
    this.element.className = 'floating-partner';

    const img = document.createElement('img');
    img.src = imageSrc;
    img.alt = 'Partner Logo';
    img.loading = 'lazy';
    img.decoding = 'async';
    img.onerror = () => {
      this.element.style.display = 'none';
    };
    this.element.appendChild(img);

    this.x = Math.random() * (window.innerWidth - 100);

    const pageHeight = Math.max(
      document.documentElement.scrollHeight,
      document.documentElement.offsetHeight,
      document.body.scrollHeight,
      document.body.offsetHeight,
      window.innerHeight
    );
    const availableHeight = pageHeight - heroHeight - 200;

    this.y = heroHeight + 100 + (Math.random() * Math.max(availableHeight, window.innerHeight));

    this.vx = (Math.random() - 0.5) * 0.8;
    this.vy = (Math.random() - 0.5) * 0.8;

    if (Math.abs(this.vx) < 0.1) {
      this.vx = Math.random() > 0.5 ? 0.2 : -0.2;
    }
    if (Math.abs(this.vy) < 0.1) {
      this.vy = Math.random() > 0.5 ? 0.2 : -0.2;
    }

    this.size = 40 + Math.random() * 30;
    this.baseSize = this.size;
    this.opacity = 0.3 + Math.random() * 0.4;
    this.isHovered = false;

    this.element.addEventListener('mouseenter', () => this.onHover());
    this.element.addEventListener('mouseleave', () => this.onLeave());
    this.element.addEventListener('click', () => this.onClick());

    this.updateElementStyle();
    this.containerEl.appendChild(this.element);
  }

  onHover() {
    this.isHovered = true;
    this.element.style.transform = 'scale(2.5)';
    this.element.style.opacity = '1';
    this.element.style.zIndex = '100';
    this.element.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
  }

  onLeave() {
    this.isHovered = false;
    this.element.style.transform = 'scale(1)';
    this.element.style.opacity = this.opacity.toString();
    this.element.style.zIndex = '1';
  }

  onClick() {
    const event = new CustomEvent('openPartnerDetails', {
      detail: { sourceIndex: this.sourceIndex, contract: this.contractData }
    });
    window.dispatchEvent(event);
  }

  updateElementStyle() {
    this.element.style.left = `${this.x}px`;
    this.element.style.top = `${this.y}px`;
    this.element.style.width = `${this.size}px`;
    this.element.style.height = `${this.size}px`;

    if (!this.isHovered) {
      this.element.style.opacity = this.opacity.toString();
    }
  }

  update() {
    if (this.isHovered) return;

    this.x += this.vx;
    this.y += this.vy;

    const viewportWidth = window.innerWidth;
    const pageHeight = Math.max(
      document.documentElement.scrollHeight,
      document.documentElement.offsetHeight,
      document.body.scrollHeight,
      document.body.offsetHeight,
      window.innerHeight
    );

    if (this.x <= 0 || this.x >= viewportWidth - this.size) {
      this.vx *= -1;
      this.x = Math.max(0, Math.min(this.x, viewportWidth - this.size));
    }

    const minY = this.heroHeight + 100;
    const maxY = pageHeight - this.size - 20;

    if (this.y <= minY || this.y >= maxY) {
      this.vy *= -1;
      this.y = Math.max(minY, Math.min(this.y, maxY));
    }

    if (this.y < this.heroHeight + 100) {
      this.y = this.heroHeight + 100;
    }

    this.updateElementStyle();
  }

  destroy() {
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
    }
  }
}

const openPanel = (contract) => {
  if (contract) {
    selectedContract.value = contract;
    showPanel.value = true;
    document.body.style.overflow = 'hidden';
  }
};

const closePanel = () => {
  showPanel.value = false;
  document.body.style.overflow = '';
};

const updateContainerHeight = () => {
  if (container.value) {
    const footer = document.querySelector('footer');
    let contentHeight = 0;

    if (footer) {
      const footerRect = footer.getBoundingClientRect();
      contentHeight = footerRect.top + footerRect.height + window.scrollY;
    } else {
      contentHeight = document.body.scrollHeight;
    }

    container.value.style.height = `${contentHeight}px`;
    container.value.style.maxHeight = `${contentHeight}px`;

    if (contentHeight > 0) {
      container.value.style.overflow = 'hidden';
    }
  }
};

const initPartners = () => {
  if (!container.value) return;

  updateContainerHeight();

  const nav = document.querySelector('nav');
  let heroSection = document.querySelector('.hero-section-container');

  if (!heroSection && nav) {
    const firstSection = nav.nextElementSibling;
    if (firstSection && firstSection.tagName === 'SECTION') {
      heroSection = firstSection;
    }
  }

  let heroHeight = 0;
  if (heroSection) {
    const rect = heroSection.getBoundingClientRect();
    heroHeight = rect.top + rect.height;
  } else {
    const firstSection = document.querySelector('section');
    if (firstSection) {
      const rect = firstSection.getBoundingClientRect();
      heroHeight = rect.top + rect.height;
    } else {
      heroHeight = window.innerHeight * 0.6;
    }
  }

  let numberOfLogos;
  const screenWidth = window.innerWidth;

  if (screenWidth < 640) numberOfLogos = 15;
  else if (screenWidth < 1024) numberOfLogos = 25;
  else if (screenWidth < 1280) numberOfLogos = 35;
  else numberOfLogos = 45;

  logos.value = [];

  const logoSources = getLogoSources();

  const pageHeight = Math.max(
    document.documentElement.scrollHeight,
    document.documentElement.offsetHeight,
    document.body.scrollHeight,
    document.body.offsetHeight,
    window.innerHeight
  );
  const availableWidth = window.innerWidth - 100;
  const availableHeight = pageHeight - heroHeight - 200;

  for (let i = 0; i < numberOfLogos; i++) {
    const sourceIndex = i % logoSources.length;
    const source = logoSources[sourceIndex];

    const logo = new FloatingPartner(source.image, container.value, sourceIndex, heroHeight, source.contract);

    const gridCols = Math.ceil(Math.sqrt(numberOfLogos));
    const gridRow = Math.floor(i / gridCols);
    const gridCol = i % gridCols;

    const randomOffsetX = (Math.random() - 0.5) * (availableWidth / gridCols * 0.4);
    const randomOffsetY = (Math.random() - 0.5) * (availableHeight / gridCols * 0.4);

    const yPosition = heroHeight + 100 + (gridRow / (gridCols - 1)) * availableHeight + randomOffsetY;

    logo.x = 50 + (gridCol / (gridCols - 1)) * availableWidth + randomOffsetX;
    logo.y = Math.max(heroHeight + 100, Math.min(yPosition, pageHeight - logo.size - 50));

    logo.x = Math.max(50, Math.min(logo.x, window.innerWidth - logo.size - 50));
    logo.y = Math.max(heroHeight + 100, Math.min(logo.y, pageHeight - logo.size - 50));

    logo.updateElementStyle();
    logos.value.push(logo);
  }

  const animate = () => {
    logos.value.forEach(logo => logo.update());
    animationId = requestAnimationFrame(animate);
  };

  animate();
};

const handleResize = () => {
  updateContainerHeight();

  logos.value.forEach(logo => {
    if (logo.x > window.innerWidth - logo.size) {
      logo.x = window.innerWidth - logo.size;
    }
  });
};

let observer = null;

onMounted(() => {
  if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
      initPartners();
    }, { timeout: 2000 });
  } else {
    setTimeout(() => {
      initPartners();
    }, 1500);
  }

  window.addEventListener('resize', handleResize);

  let resizeTimeout;
  observer = new MutationObserver(() => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      updateContainerHeight();
    }, 100);
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['style', 'class']
  });

  window._floatingPartnersObserver = observer;

  // Listen for partner details open event
  window.addEventListener('openPartnerDetails', (event) => {
    const { contract } = event.detail;
    if (contract) {
      openPanel(contract);
    }
  });

  // Keyboard navigation
  const handleKeyPress = (e) => {
    if (!showPanel.value) return;
    if (e.key === 'Escape') {
      closePanel();
    }
  };

  window.addEventListener('keydown', handleKeyPress);

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyPress);
  });
});

onUnmounted(() => {
  if (animationId) {
    cancelAnimationFrame(animationId);
  }
  window.removeEventListener('resize', handleResize);
  window.removeEventListener('openPartnerDetails', () => {});
  if (window._floatingPartnersObserver) {
    window._floatingPartnersObserver.disconnect();
    delete window._floatingPartnersObserver;
  }

  logos.value.forEach(logo => logo.destroy());
  logos.value = [];

  if (container.value) {
    container.value.innerHTML = '';
  }
  document.body.style.overflow = '';
});
</script>

<style scoped>
.floating-partners-container {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: auto;
  z-index: 5;
  pointer-events: none;
  overflow: hidden;
}

:deep(.floating-partner) {
  position: absolute;
  pointer-events: auto;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  will-change: transform, opacity;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.15));
  border-radius: 50%;
  overflow: hidden;
  background: white;
  border: 2px solid rgba(184, 155, 106, 0.3);
  cursor: pointer;
  z-index: 1;
}

:deep(.floating-partner:hover) {
  z-index: 100;
  filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.3));
  border-color: rgba(184, 155, 106, 0.9);
}

:deep(.floating-partner img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

/* Details Panel Overlay */
.details-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  backdrop-filter: blur(4px);
}

/* Left-Side Slide-In Panel */
.details-panel {
  position: fixed;
  top: 0;
  left: 0;
  width: 380px;
  max-width: 90vw;
  height: 100%;
  background: linear-gradient(135deg, #1E3943 0%, #13292F 100%);
  box-shadow: 4px 0 30px rgba(0, 0, 0, 0.3);
  z-index: 10000;
  overflow-y: auto;
  padding: 24px;
  display: flex;
  flex-direction: column;
}

[dir="rtl"] .details-panel {
  left: auto;
  right: 0;
  box-shadow: -4px 0 30px rgba(0, 0, 0, 0.3);
}

.panel-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  z-index: 10;
}

[dir="rtl"] .panel-close {
  right: auto;
  left: 16px;
}

.panel-close:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.4);
  transform: scale(1.1);
}

.panel-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 40px;
  gap: 24px;
}

.panel-image-wrapper {
  width: 180px;
  height: 180px;
  border-radius: 50%;
  overflow: hidden;
  background: white;
  border: 3px solid rgba(184, 155, 106, 0.6);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  flex-shrink: 0;
}

.panel-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.panel-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(184, 155, 106, 0.1);
}

.panel-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
  text-align: center;
  line-height: 1.3;
}

.panel-description {
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.8);
  text-align: center;
  line-height: 1.7;
  max-width: 320px;
}

/* Panel Phones */
.panel-phones {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  max-width: 280px;
}

.panel-phone-item {
  width: 100%;
}

.panel-phone-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  width: 100%;
  justify-content: center;
}

.panel-phone-call {
  color: #B89B6A;
  background: rgba(184, 155, 106, 0.1);
  border: 1px solid rgba(184, 155, 106, 0.3);
}

.panel-phone-call:hover {
  background: rgba(184, 155, 106, 0.2);
  border-color: rgba(184, 155, 106, 0.5);
}

.panel-phone-whatsapp {
  color: #25D366;
  background: rgba(37, 211, 102, 0.1);
  border: 1px solid rgba(37, 211, 102, 0.3);
}

.panel-phone-whatsapp:hover {
  background: rgba(37, 211, 102, 0.2);
  border-color: rgba(37, 211, 102, 0.5);
}

/* Slide Panel Transition */
.slide-panel-enter-active {
  transition: opacity 0.3s ease;
}

.slide-panel-enter-active .details-panel {
  animation: slideInLeft 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.slide-panel-leave-active {
  transition: opacity 0.3s ease;
}

.slide-panel-leave-active .details-panel {
  animation: slideOutLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.slide-panel-enter-from,
.slide-panel-leave-to {
  opacity: 0;
}

@keyframes slideInLeft {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(0);
  }
}

@keyframes slideOutLeft {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-100%);
  }
}

/* RTL slide direction */
[dir="rtl"] .slide-panel-enter-active .details-panel {
  animation: slideInRight 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

[dir="rtl"] .slide-panel-leave-active .details-panel {
  animation: slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

@keyframes slideOutRight {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(100%);
  }
}

@media (max-width: 640px) {
  .details-panel {
    width: 100%;
    max-width: 100vw;
  }

  .panel-image-wrapper {
    width: 140px;
    height: 140px;
  }

  .panel-title {
    font-size: 1.25rem;
  }

  .panel-description {
    font-size: 0.875rem;
  }
}
</style>
