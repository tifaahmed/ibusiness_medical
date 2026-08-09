<template>
  <Head 
    :title="pageTitle"
    :meta="metaTags"
    :link="linkTags"
  />
  
  <div class="bg-[#F9F6F1] text-gray-800 scroll-smooth relative pb-20 md:pb-0" style="font-family: 'Inter', system-ui, sans-serif">
    <!-- Floating Partners Background (All Sections) -->
    <FloatingPartners :contracts="contracts" />

    <!-- Floating Contact Buttons -->
    <FloatingContact />

    <!-- Navigation -->
    <GuestNavigation current-page="home" />

    <!-- News Ticker -->
    <NewsTicker />

    <!-- Mobile Menu (Hidden - using GuestNavigation bottom nav instead) -->
    <Transition name="slide">
        <div 
        v-if="false && mobileMenuOpen"
        class="fixed inset-0 bg-[#F9F6F1] z-40 md:hidden hidden"
      >
        <div class="p-6">
          <div class="flex justify-between items-center mb-8">
            <img :src="$page.props.appLogo" alt="ASH Health Care" class="h-8 w-auto" @error="handleImageError">
            <button @click="mobileMenuOpen = false">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            </button>
          </div>
          <nav class="flex flex-col space-y-4">
            <a 
              v-for="link in navLinks" 
              :key="link.href"
              :href="link.href" 
              class="text-lg text-[#1E3943] hover:text-[#B89B6A]"
              @click.prevent="scrollToSection(link.href); mobileMenuOpen = false"
            >
              {{ link.text }}
            </a>
            
            <!-- Language Switcher Mobile - Only show for authenticated users -->
            <div v-if="$page.props.auth?.user" class="flex items-center space-x-2 pt-2 border-t border-gray-200">
              <span class="text-sm text-[#1E3943]">Language:</span>
              <a
                href="#"
                @click.prevent="switchLanguage('en'); mobileMenuOpen = false"
                class="text-sm px-3 py-1 rounded"
                :class="$page.props.locale === 'en' ? 'bg-[#B89B6A] text-white' : 'text-[#1E3943] hover:bg-[#F9F6F1]'"
              >
                English
              </a>
              <a
                href="#"
                @click.prevent="switchLanguage('ar'); mobileMenuOpen = false"
                class="text-sm px-3 py-1 rounded"
                :class="$page.props.locale === 'ar' ? 'bg-[#B89B6A] text-white' : 'text-[#1E3943] hover:bg-[#F9F6F1]'"
              >
                العربية
              </a>
            </div>
            
            <button 
              @click="window.dispatchEvent(new CustomEvent('open-member-card-modal')); mobileMenuOpen = false"
              class="bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-6 py-3 rounded-lg mt-4"
            >
              Member Card
            </button>
          </nav>
        </div>
      </div>
    </Transition>

    <!-- Hero Section -->
    <section class="pt-16 pb-8 md:pt-24 md:pb-12 px-4 md:px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F] relative overflow-hidden">
      <!-- Floating Logos Background (Hero Section Only) -->
      <FloatingLogos />
      
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-10 left-4 md:top-20 md:left-10 w-48 h-48 md:w-72 md:h-72 bg-[#B89B6A] rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-4 md:bottom-20 md:right-10 w-56 h-56 md:w-96 md:h-96 bg-[#C6A76C] rounded-full blur-3xl"></div>
      </div>

      <div class="container mx-auto relative z-10 hero-section-container">
        <div class="grid md:grid-cols-2 gap-6 md:gap-12 items-center">
          <!-- Hero Text -->
          <div class="text-white animate-fade-in order-1">
            <h1 class="text-2xl sm:text-3xl md:text-5xl lg:text-6xl font-bold mb-3 md:mb-4 leading-tight" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
              {{ $page.props.translations?.home?.hero?.title || 'Healthcare Excellence is the Key to' }} <span class="text-[#B89B6A]">{{ $page.props.translations?.home?.hero?.title_highlight || 'Better Living' }}</span>
            </h1>
            <p class="text-sm sm:text-base md:text-xl text-[#E6D3B1] mb-4 md:mb-6 leading-relaxed" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
              {{ $page.props.translations?.home?.hero?.description || 'Providing comprehensive healthcare solutions with cutting-edge technology and compassionate care for your well-being.' }}
            </p>
            <div class="flex flex-col sm:flex-row gap-3 md:gap-4">
              <template v-if="$page.props.auth?.user">
                <Link :href="route('admin.dashboard')" class="bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-4 md:px-6 lg:px-8 py-2.5 md:py-3 lg:py-4 rounded-lg hover:shadow-2xl transition-all text-sm md:text-base lg:text-lg font-semibold text-center">
                  {{ $page.props.translations?.home?.auth?.dashboard || 'Go to Dashboard' }}
                </Link>
              </template>
              <template v-else>
                <Link :href="route('contact')" class="bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-4 md:px-6 lg:px-8 py-2.5 md:py-3 lg:py-4 rounded-lg hover:shadow-2xl transition-all text-sm md:text-base lg:text-lg font-semibold text-center">
                  {{ $page.props.translations?.home?.hero?.cta_primary || 'Get Started for Free' }}
                </Link>
              </template>
              <button class="bg-white/10 text-white px-4 md:px-6 lg:px-8 py-2.5 md:py-3 lg:py-4 rounded-lg hover:bg-white/20 transition-all backdrop-blur-sm border border-white/20 text-sm md:text-base lg:text-lg font-semibold" @click="scrollToSection('#features')">
                {{ $page.props.translations?.home?.hero?.cta_secondary || 'Learn More' }}
              </button>
            </div>
            <p class="mt-3 md:mt-4 text-xs sm:text-sm md:text-base text-[#E6D3B1] flex items-center flex-wrap" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
              <svg class="w-4 h-4 md:w-5 md:h-5 mr-1 md:mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="break-words">{{ $page.props.translations?.home?.hero?.trial_text || 'No credit card needed • Free 14-day trial' }}</span>
            </p>
          </div>

          <!-- Hero Slider -->
          <div class="relative order-2">
            <HeroSlider />
          </div>
        </div>
      </div>
    </section>

    <!-- Offers Carousel Section -->
    <OffersCarousel
      v-if="offers && offers.length > 0"
      :offers-data="offers"
      :translations="$page.props.translations?.home?.offers || {}"
      :partners="partners"
      :contracts="contracts"
      :partners-title="$page.props.translations?.home?.partners?.title || 'Contracts'"
    />

    <!-- Features Section -->
    <section id="features" class="py-12 px-6">
      <div class="container mx-auto">
        <div class="text-center mb-10 animate-fade-in" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl lg:text-5xl font-bold text-[#1E3943] mb-4">
            {{ $page.props.translations?.home?.features?.title || 'Build a Patient-Centric Healthcare Strategy' }}
          </h2>
          <p class="text-base md:text-xl text-[#A0A0A0] max-w-2xl mx-auto">
            {{ $page.props.translations?.home?.features?.subtitle || 'Innovative solutions designed for modern healthcare delivery.' }}
          </p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 md:gap-4 lg:gap-8   ">
          <FeatureCard
            v-for="feature in features"
            :key="feature.title"
            :icon="feature.icon"
            :title="feature.title"
            :description="feature.description"
            @click="() => { window.dispatchEvent(new CustomEvent('open-member-card-modal')); }"
          />
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="py-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center text-white">
          <StatCard
            v-for="stat in stats"
            :key="stat.value"
            :value="stat.value"
            :label="stat.label"
          />
        </div>
      </div>
    </section>

    <!-- Governorates Section -->
    <section class="py-12 px-6 bg-transparent">
      <div class="container mx-auto">
        <div :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h3 class="text-xl md:text-3xl font-bold text-[#1E3943] mb-6 text-center">
            {{ $page.props.translations?.home?.governorates?.title || 'Governorates' }}
          </h3>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 max-w-5xl mx-auto">
            <div
              v-for="(governorate, index) in ($page.props.translations?.home?.governorates?.list || [])"
              :key="index"
              class="bg-white rounded-lg p-4 text-center shadow-md hover:shadow-lg transition-shadow border border-[#E6D3B1]"
            >
              <p class="text-sm md:text-lg font-semibold text-[#1E3943]">{{ governorate }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-12 px-6 bg-transparent">
      <div class="container mx-auto">
        <div class="text-center mb-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl lg:text-5xl font-bold text-[#1E3943] mb-4">
            {{ $page.props.translations?.home?.testimonials?.title || 'What Our Patients Say' }}
          </h2>
          <p class="text-base md:text-xl text-[#A0A0A0] max-w-2xl mx-auto">
            {{ $page.props.translations?.home?.testimonials?.subtitle || 'Real stories from real patients who trust us with their health.' }}
          </p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-8">
          <TestimonialCard
            v-for="testimonial in testimonials"
            :key="testimonial.name"
            :name="testimonial.name"
            :role="testimonial.role"
            :text="testimonial.text"
            :avatar="testimonial.avatar"
            :rating="testimonial.rating"
          />
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <!-- <section id="pricing" class="py-12 px-6 bg-gradient-to-b from-white to-[#F9F6F1]">
      <div class="container mx-auto">
        <div class="text-center mb-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl lg:text-5xl font-bold text-[#1E3943] mb-4">
            {{ $page.props.translations?.home?.pricing?.title || 'Flexible Healthcare Plans' }}
          </h2>
          <p class="text-base md:text-xl text-[#A0A0A0] max-w-2xl mx-auto">
            {{ $page.props.translations?.home?.pricing?.subtitle || 'Choose the plan that best fits your healthcare needs.' }}
          </p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
          <PricingCard
            v-for="plan in pricingPlans"
            :key="plan.name"
            :name="plan.name"
            :price="plan.price"
            :description="plan.description"
            :features="plan.features"
            :popular="plan.popular"
          />
        </div>
      </div>
    </section> -->

    <!-- CTA Section -->
    <section class="py-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F] relative overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-20 left-20 w-96 h-96 bg-[#B89B6A] rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#C6A76C] rounded-full blur-3xl"></div>
      </div>
      
      <div class="container mx-auto text-center relative z-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
        <h2 class="text-2xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
          {{ $page.props.translations?.home?.cta?.title || 'Ready to Experience Better Healthcare?' }}
        </h2>
        <p class="text-base md:text-xl text-[#E6D3B1] mb-8 max-w-2xl mx-auto">
          {{ $page.props.translations?.home?.cta?.description || 'Join thousands of satisfied patients who trust ASH Health Care for their wellness journey.' }}
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <template v-if="$page.props.auth?.user">
            <Link :href="route('admin.dashboard')" class="bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-6 md:px-8 py-3 md:py-4 rounded-lg hover:shadow-2xl transition-all text-base md:text-lg font-semibold">
              {{ $page.props.translations?.home?.cta?.go_to_dashboard || 'Go to Dashboard' }}
            </Link>
          </template>
          <template v-else>
            <Link :href="route('contact')" class="bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-6 md:px-8 py-3 md:py-4 rounded-lg hover:shadow-2xl transition-all text-base md:text-lg font-semibold">
              {{ $page.props.translations?.home?.cta?.secondary || 'Contact Us' }}
            </Link>
          </template>

        </div>

      </div>
    </section>

    <!-- Footer -->
    <GuestFooter />

    <!-- Partner Gallery Modal -->
    <Transition name="fade">
      <div
        v-if="partnerGalleryOpen"
        class="partner-gallery-modal fixed inset-0 z-[200] bg-black/95 backdrop-blur-sm flex items-center justify-center"
        @click.self="closePartnerGallery"
        @touchstart.prevent="handleTouchStart"
        @touchmove.prevent="handleTouchMove"
        @touchend.prevent="handleTouchEnd"
      >
        <!-- Close Button -->
        <button
          @click="closePartnerGallery"
          class="gallery-close-btn absolute top-4 right-4 md:top-6 md:right-6 text-white hover:text-[#B89B6A] transition-colors z-50 p-2 md:p-3 bg-black/50 rounded-full backdrop-blur-sm"
          aria-label="Close gallery"
        >
          <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Gallery Swiper Container -->
        <div class="gallery-container w-full h-full flex items-center justify-center px-4 py-16 md:py-8">
          <div class="swiper gallery-swiper w-full h-full max-w-4xl" ref="gallerySwiper">
            <div class="swiper-wrapper">
              <div
                v-for="(partner, index) in partners"
                :key="index"
                class="swiper-slide"
              >
                <div class="flex items-center justify-center w-full h-full p-2 md:p-8">
                  <img
                    :src="partner"
                    :alt="`Partner ${index + 1}`"
                    class="gallery-image max-w-full max-h-full w-auto h-auto object-contain rounded-lg shadow-2xl"
                    loading="lazy"
                    decoding="async"
                    @error="handleImageError"
                    @load="handleImageLoad"
                  >
                </div>
              </div>
            </div>
            <!-- Navigation Arrows -->
            <div class="swiper-button-next gallery-nav-next"></div>
            <div class="swiper-button-prev gallery-nav-prev"></div>
            <!-- Pagination -->
            <div class="swiper-pagination gallery-pagination"></div>
          </div>
        </div>

        <!-- Slide Counter (Mobile) -->
        <div class="absolute bottom-20 md:bottom-24 left-1/2 -translate-x-1/2 text-white text-sm md:text-base bg-black/50 px-4 py-2 rounded-full backdrop-blur-sm z-40">
          <span v-if="gallerySwiperInstance" class="slide-counter">
            {{ (gallerySwiperInstance.realIndex || 0) + 1 }} / {{ partners.length }}
          </span>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, router, Head, usePage } from '@inertiajs/vue3';
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, Keyboard } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import FloatingLogos from './_components/FloatingLogos.vue';
import FloatingPartners from './_components/FloatingPartners.vue';
import FloatingContact from './_components/FloatingContact.vue';
import HeroSlider from './_components/HeroSlider.vue';
import OffersCarousel from './_components/OffersCarousel.vue';
import FeatureCard from './_components/FeatureCard.vue';
import StatCard from './_components/StatCard.vue';
import TestimonialCard from './_components/TestimonialCard.vue';
import PricingCard from './_components/PricingCard.vue';
import GuestNavigation from './_components/GuestNavigation.vue';
import NewsTicker from './_components/NewsTicker.vue';
import GuestFooter from './_components/GuestFooter.vue';

const props = defineProps({
  offers: {
    type: Array,
    default: () => []
  },
  contracts: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
const locale = computed(() => page.props.locale || 'en');
const appUrl = computed(() => {
  if (typeof window !== 'undefined') {
    return window.location.origin;
  }
  return 'http://127.0.0.1:8000';
});
const currentUrl = computed(() => {
  if (typeof window !== 'undefined') {
    return window.location.href;
  }
  return appUrl.value;
});
const baseUrl = computed(() => appUrl.value);

// SEO Data
const pageTitle = computed(() => {
  return locale.value === 'ar' 
    ? 'ASH Health Care - كارت خصومات طبي شامل | خصومات تصل إلى 80%'
    : 'ASH Health Care - Comprehensive Medical Discount Card | Up to 80% Discounts';
});

const metaDescription = computed(() => {
  return locale.value === 'ar'
    ? 'كارت خصومات طبي شامل لجميع الجهات الطبية مع خصومات تصل إلى 80%. نقدم أفضل خطط التأمين الطبي وخصومات حصرية على التحاليل والأشعة. متاح في جميع محافظات مصر.'
    : 'Comprehensive medical discount card for all medical facilities with discounts up to 80%. We offer the best medical insurance plans and exclusive discounts on lab tests and radiology. Available in all governorates of Egypt.';
});

const metaKeywords = computed(() => {
  return locale.value === 'ar'
    ? 'كارت خصومات طبي، تأمين طبي، خصومات تحاليل، خصومات أشعة، رعاية صحية، ASH Health Care، كارت عضوية طبي، خصومات صحية، تأمين صحي شامل'
    : 'medical discount card, health insurance, lab test discounts, radiology discounts, healthcare, ASH Health Care, medical membership card, health discounts, comprehensive health insurance';
});

const ogImage = computed(() => {
  return page.props.appLogo;
});

// Meta Tags
const metaTags = computed(() => {
  const tags = [
    // Basic Meta Tags
    { name: 'description', content: metaDescription.value },
    { name: 'keywords', content: metaKeywords.value },
    { name: 'author', content: 'ASH Health Care' },
    { name: 'robots', content: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' },
    { name: 'googlebot', content: 'index, follow' },
    { name: 'language', content: locale.value === 'ar' ? 'Arabic' : 'English' },
    { name: 'revisit-after', content: '7 days' },
    { name: 'rating', content: 'general' },
    
    // Open Graph Tags
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: currentUrl.value },
    { property: 'og:title', content: pageTitle.value },
    { property: 'og:description', content: metaDescription.value },
    { property: 'og:image', content: ogImage.value },
    { property: 'og:image:width', content: '1200' },
    { property: 'og:image:height', content: '630' },
    { property: 'og:image:alt', content: 'ASH Health Care - Medical Discount Card' },
    { property: 'og:site_name', content: 'ASH Health Care' },
    { property: 'og:locale', content: locale.value === 'ar' ? 'ar_EG' : 'en_US' },
    { property: 'og:locale:alternate', content: locale.value === 'ar' ? 'en_US' : 'ar_EG' },
    
    // Twitter Card Tags
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:url', content: currentUrl.value },
    { name: 'twitter:title', content: pageTitle.value },
    { name: 'twitter:description', content: metaDescription.value },
    { name: 'twitter:image', content: ogImage.value },
    { name: 'twitter:image:alt', content: 'ASH Health Care - Medical Discount Card' },
    { name: 'twitter:site', content: '@ashhealthcare' },
    { name: 'twitter:creator', content: '@ashhealthcare' },
    
    // Additional Meta Tags
    { name: 'theme-color', content: '#1E3943' },
    { name: 'apple-mobile-web-app-capable', content: 'yes' },
    { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
    { name: 'apple-mobile-web-app-title', content: 'ASH Health Care' },
    { name: 'application-name', content: 'ASH Health Care' },
    { name: 'msapplication-TileColor', content: '#1E3943' },
    { name: 'msapplication-config', content: '/browserconfig.xml' },
  ];
  
  return tags;
});

// Link Tags
const linkTags = computed(() => {
  // Get clean canonical URL (without query params for better SEO)
  const canonicalUrl = currentUrl.value.split('?')[0];
  
  const links = [
    // Canonical URL
    { rel: 'canonical', href: canonicalUrl },
    
    // Language Alternates (point to homepage - language is set via session)
    { rel: 'alternate', hreflang: 'en', href: `${baseUrl.value}/` },
    { rel: 'alternate', hreflang: 'ar', href: `${baseUrl.value}/` },
    { rel: 'alternate', hreflang: 'x-default', href: `${baseUrl.value}/` },
    
    // Preconnect for performance
    { rel: 'dns-prefetch', href: 'https://fonts.bunny.net' },
    { rel: 'preconnect', href: 'https://fonts.bunny.net', crossorigin: true },
  ];
  
  return links;
});

// Structured Data (JSON-LD) - Inject into head
const injectStructuredData = () => {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  const organizationData = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    'name': 'ASH Health Care',
    'alternateName': locale.value === 'ar' ? 'آش للرعاية الصحية' : 'ASH Health Care',
    'url': baseUrl.value,
    'logo': page.props.appLogo,
    'description': metaDescription.value,
    'address': {
      '@type': 'PostalAddress',
      'addressCountry': 'EG',
      'addressLocality': locale.value === 'ar' ? 'القاهرة' : 'Cairo',
      'addressRegion': locale.value === 'ar' ? 'مصر' : 'Egypt'
    },
    'contactPoint': {
      '@type': 'ContactPoint',
      'telephone': '+20-1156385251',
      'contactType': 'customer service',
      'availableLanguage': ['English', 'Arabic']
    },
    'sameAs': [
      'https://www.facebook.com/share/19nhbGft4e/',
    ],
    'aggregateRating': {
      '@type': 'AggregateRating',
      'ratingValue': '4.8',
      'reviewCount': '1250',
      'bestRating': '5',
      'worstRating': '1'
    }
  };

  const websiteData = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    'name': 'ASH Health Care',
    'alternateName': locale.value === 'ar' ? 'آش للرعاية الصحية' : 'ASH Health Care',
    'url': baseUrl.value,
    'description': metaDescription.value,
    'inLanguage': locale.value === 'ar' ? 'ar-EG' : 'en-US',
    'potentialAction': {
      '@type': 'SearchAction',
      'target': {
        '@type': 'EntryPoint',
        'urlTemplate': `${baseUrl.value}/search?q={search_term_string}`
      },
      'query-input': 'required name=search_term_string'
    }
  };

  const medicalBusinessData = {
    '@context': 'https://schema.org',
    '@type': 'MedicalBusiness',
    'name': 'ASH Health Care',
    'image': page.props.appLogo,
    'description': metaDescription.value,
    'url': baseUrl.value,
    'telephone': '+20-1156385251',
    'address': {
      '@type': 'PostalAddress',
      'addressCountry': 'EG',
      'addressLocality': locale.value === 'ar' ? 'القاهرة' : 'Cairo'
    },
    'priceRange': '$$',
    'openingHours': 'Mo-Su 00:00-23:59',
    'areaServed': {
      '@type': 'Country',
      'name': 'Egypt'
    },
    'hasOfferCatalog': {
      '@type': 'OfferCatalog',
      'name': 'Medical Services',
      'itemListElement': [
        {
          '@type': 'Offer',
          'itemOffered': {
            '@type': 'Service',
            'name': locale.value === 'ar' ? 'خصومات التحاليل الطبية' : 'Medical Lab Test Discounts',
            'description': locale.value === 'ar' ? 'خصومات تصل إلى 80% على التحاليل الطبية' : 'Up to 80% discounts on medical lab tests'
          }
        },
        {
          '@type': 'Offer',
          'itemOffered': {
            '@type': 'Service',
            'name': locale.value === 'ar' ? 'خدمات الأشعة' : 'Radiology Services',
            'description': locale.value === 'ar' ? 'تغطية كاملة لخدمات الأشعة' : 'Complete radiology services coverage'
          }
        },
        {
          '@type': 'Offer',
          'itemOffered': {
            '@type': 'Service',
            'name': locale.value === 'ar' ? 'كارت طبي شامل' : 'Comprehensive Medical Card',
            'description': locale.value === 'ar' ? 'تغطية شاملة للعلاجات والفحوصات والاستشارات الطبية' : 'Comprehensive coverage for treatments, examinations, and medical consultations'
          }
        }
      ]
    }
  };

  const structuredData = JSON.stringify([organizationData, websiteData, medicalBusinessData]);
  
  // Remove existing structured data script if any
  const existingScript = document.querySelector('script[type="application/ld+json"][data-seo="true"]');
  if (existingScript) {
    existingScript.remove();
  }
  
  // Inject structured data
  const script = document.createElement('script');
  script.type = 'application/ld+json';
  script.setAttribute('data-seo', 'true');
  script.textContent = structuredData;
  document.head.appendChild(script);
};

const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
const languageMenuOpen = ref(false);
const partnerGalleryOpen = ref(false);
const partnerGalleryIndex = ref(0);
const gallerySwiper = ref(null);
let gallerySwiperInstance = null;

const switchLanguage = (locale) => {
  window.location.href = route('lang.switch', { locale });
};


const navLinks = [
  { text: 'About Us', href: '#about' },
  { text: 'Contact Us', href: '#contact' },
  { text: 'Partners', href: '#partners' }
];

const features = computed(() => {
  const t = page.props.translations?.home?.features || {};
  return [
    {
      icon: 'activity',
      title: t.lab_discounts?.title || 'Lab Test Discounts',
      description: t.lab_discounts?.description || 'Discounts up to 80% at the largest medical laboratories'
    },
    {
      icon: 'heart-pulse',
      title: t.radiology_centers?.title || 'Radiology Centers',
      description: t.radiology_centers?.description || 'Complete coverage of radiology services at competitive prices'
    },
    {
      icon: 'shield-check',
      title: t.comprehensive_card?.title || 'Comprehensive Medical Card',
      description: t.comprehensive_card?.description || 'Comprehensive coverage for treatments, examinations, and medical consultations'
    },
    {
      icon: 'users',
      title: t.contracts?.title || 'Contracts',
      description: t.contracts?.description || 'Wide network of accredited medical service providers'
    }
  ];
});

const stats = computed(() => {
  const t = page.props.translations?.home?.stats || {};
  return [
    { value: '50K+', label: t.happy_patients || 'Happy Clients' },
    { value: '200+', label: t.expert_doctors || 'Accredited Hospitals' },
    { value: '80%', label: t.success_rate || 'Discount Rate' },
    { value: '6+', label: t.years_experience || 'Governorates' }
  ];
});

const partners = [
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
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.12 PM.jpeg',
  // Duplicate set for fuller slider
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
  '/images/partners/WhatsApp Image 2026-01-03 at 11.04.12 PM.jpeg',
  // Third set
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

const testimonials = computed(() => {
  const t = page.props.translations?.home?.testimonials || {};
  const role = t.role || 'Patient';

  return [
    {
      name: t.testimonial_1?.name || 'Ahmed Mohamed',
      role: role,
      text: t.testimonial_1?.text || 'Excellent service and real discounts on lab tests, saved me more than 70% of costs',
      avatar: '/images/avatars/avatar-1.svg',
      rating: t.testimonial_1?.rating || 5
    },
    {
      name: t.testimonial_2?.name || 'Sara Abdullah',
      role: role,
      text: t.testimonial_2?.text || 'Comprehensive medical insurance that covers all my family\'s needs without complications',
      avatar: '/images/avatars/avatar-2.svg',
      rating: t.testimonial_2?.rating || 5
    },
    {
      name: t.testimonial_3?.name || 'Khaled Ali',
      role: role,
      text: t.testimonial_3?.text || 'Fast response and accurate appointments are among the best I found in medical insurance services',
      avatar: '/images/avatars/avatar-3.svg',
      rating: t.testimonial_3?.rating || 5
    }
  ];
});

const pricingPlans = computed(() => {
  const t = page.props.translations?.home?.pricing || {};
  return [
    {
      name: t.basic_care?.name || 'Basic Care',
      price: 49,
      description: t.basic_care?.description || 'Essential healthcare services',
      features: [
        'Primary care consultations',
        'Basic diagnostic tests',
        'Prescription management',
        '24/7 helpline'
      ],
      popular: false
    },
    {
      name: t.premium_care?.name || 'Premium Care',
      price: 99,
      description: t.premium_care?.description || 'Comprehensive healthcare',
      features: [
        'All Basic features',
        'Specialist consultations',
        'Advanced diagnostics',
        'Telemedicine access',
        'Priority scheduling'
      ],
      popular: true
    },
    {
      name: t.family_care?.name || 'Family Care',
      price: 199,
      description: t.family_care?.description || 'Complete family coverage',
      features: [
        'All Premium features',
        'Cover up to 5 members',
        'Home healthcare visits',
        'Dedicated care coordinator',
        'Wellness programs'
      ],
      popular: false
    }
  ];
});


const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
};

const scrollToSection = (href) => {
  const target = document.querySelector(href);
  if (target) {
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

const handleImageError = (event) => {
  event.target.style.display = 'none';
};

const openPartnerGallery = (index) => {
  partnerGalleryIndex.value = index;
  partnerGalleryOpen.value = true;
  // Prevent body scroll
  document.body.style.overflow = 'hidden';
  // Prevent scrolling on iOS
  document.body.style.position = 'fixed';
  document.body.style.width = '100%';
  
  nextTick(() => {
    initGallerySwiper();
  });
};

const closePartnerGallery = () => {
  partnerGalleryOpen.value = false;
  // Restore body scroll
  document.body.style.overflow = '';
  document.body.style.position = '';
  document.body.style.width = '';
  
  if (gallerySwiperInstance) {
    gallerySwiperInstance.destroy(true, true);
    gallerySwiperInstance = null;
  }
};

// Touch handlers for swipe to close on mobile
let touchStartY = 0;
let touchStartX = 0;

const handleTouchStart = (e) => {
  touchStartY = e.touches[0].clientY;
  touchStartX = e.touches[0].clientX;
};

const handleTouchMove = (e) => {
  // Allow swiper to handle horizontal swipes
  const deltaX = Math.abs(e.touches[0].clientX - touchStartX);
  const deltaY = Math.abs(e.touches[0].clientY - touchStartY);
  
  // If vertical swipe is more than horizontal, prevent default to allow swipe-to-close
  if (deltaY > deltaX && deltaY > 50) {
    // Vertical swipe detected - could implement swipe down to close
  }
};

const handleTouchEnd = (e) => {
  const deltaY = touchStartY - e.changedTouches[0].clientY;
  // Swipe down more than 100px to close
  if (deltaY < -100) {
    closePartnerGallery();
  }
};

const initGallerySwiper = async () => {
  try {
    await nextTick();

    if (!gallerySwiper.value) {
      console.error('[GallerySwiper] Container not available');
      return;
    }

    // Destroy existing instance if any
    if (gallerySwiperInstance) {
      gallerySwiperInstance.destroy(true, true);
    }

    gallerySwiperInstance = new Swiper(gallerySwiper.value, {
      modules: [Navigation, Pagination, Keyboard],
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
          // Update slide counter if needed
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

const handleImageLoad = () => {
  // Force swiper to update after image loads
  if (gallerySwiperInstance) {
    gallerySwiperInstance.update();
  }
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  injectStructuredData();

  // Close language menu when clicking outside
  document.addEventListener('click', (e) => {
    if (languageMenuOpen.value && !e.target.closest('.language-switcher')) {
      languageMenuOpen.value = false;
    }
  });
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  if (gallerySwiperInstance) {
    gallerySwiperInstance.destroy(true, true);
  }
  document.body.style.overflow = '';
});
</script>

<style scoped>
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-20px); }
}

.animate-float {
  animation: float 3s ease-in-out infinite;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 0.6s ease-in;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from {
  transform: translateX(-100%);
}

.slide-leave-to {
  transform: translateX(-100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Gallery Modal Styles */
.partner-gallery-modal {
  touch-action: pan-y;
}

.gallery-container {
  position: relative;
  min-height: 0;
}

:deep(.gallery-swiper) {
  width: 100%;
  height: 100%;
  min-height: 300px;
}

:deep(.gallery-swiper .swiper-wrapper) {
  align-items: center;
}

:deep(.gallery-swiper .swiper-slide) {
  display: flex;
  align-items: center;
  justify-content: center;
  height: auto;
  min-height: 200px;
}

.gallery-image {
  max-width: 95vw;
  max-height: 70vh;
}

@media (max-width: 768px) {
  .gallery-image {
    max-width: 90vw;
    max-height: 60vh;
  }
}

.gallery-close-btn {
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

:deep(.gallery-nav-next),
:deep(.gallery-nav-prev) {
  color: white;
  background: rgba(255, 255, 255, 0.15);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  backdrop-filter: blur(10px);
  transition: all 0.3s ease;
  margin-top: 0;
  top: 50%;
  transform: translateY(-50%);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

:deep(.gallery-nav-next:hover),
:deep(.gallery-nav-prev:hover) {
  background: rgba(184, 155, 106, 0.8);
  transform: translateY(-50%) scale(1.1);
}

:deep(.gallery-nav-next::after),
:deep(.gallery-nav-prev::after) {
  font-size: 20px;
  font-weight: bold;
}

:deep(.gallery-nav-next) {
  right: 10px;
}

:deep(.gallery-nav-prev) {
  left: 10px;
}

:deep(.gallery-pagination) {
  bottom: 60px !important;
  display: flex;
  justify-content: center;
  align-items: center;
}

:deep(.gallery-pagination .swiper-pagination-bullet) {
  background: rgba(255, 255, 255, 0.4);
  width: 8px;
  height: 8px;
  margin: 0 4px;
  opacity: 1;
  transition: all 0.3s ease;
}

:deep(.gallery-pagination .swiper-pagination-bullet-active) {
  background: #B89B6A;
  width: 24px;
  border-radius: 4px;
}

@media (max-width: 768px) {
  :deep(.gallery-nav-next),
  :deep(.gallery-nav-prev) {
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.2);
  }

  :deep(.gallery-nav-next::after),
  :deep(.gallery-nav-prev::after) {
    font-size: 16px;
  }

  :deep(.gallery-nav-next) {
    right: 8px;
  }

  :deep(.gallery-nav-prev) {
    left: 8px;
  }

  :deep(.gallery-pagination) {
    bottom: 80px !important;
  }

  :deep(.gallery-pagination .swiper-pagination-bullet) {
    width: 6px;
    height: 6px;
    margin: 0 3px;
  }

  :deep(.gallery-pagination .swiper-pagination-bullet-active) {
    width: 20px;
  }

  .gallery-container {
    padding-top: 60px;
    padding-bottom: 100px;
  }
}

/* Fade transition for modal */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
