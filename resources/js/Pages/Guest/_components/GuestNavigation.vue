<template>
  <!-- Desktop Navigation (Top) - Hidden on Mobile -->
  <nav 
    ref="desktopNav"
    class="hidden md:block fixed top-0 w-full z-50 transition-all duration-300"
    :class="[
      isScrolled ? 'bg-gradient-to-br from-[#1E3943] to-[#13292F] backdrop-blur-md shadow-lg' : 'bg-gradient-to-br from-[#1E3943] to-[#13292F]'
    ]"
    :style="{
      height: isScrolled ? '60px' : '80px',
      transform: navHidden ? 'translateY(-100%)' : 'translateY(0)'
    }"
  >
    <div 
      class="container mx-auto transition-all duration-300"
      :class="isScrolled ? 'px-4 py-1' : 'px-6 py-4'"
      :style="isScrolled ? 'height: 60px;' : 'height: 80px;'"
    >
      <div class="flex items-center justify-between h-full">
        <!-- Logo -->
        <Link href="/" class="flex items-center space-x-2">
          <img
            v-show="!isScrolled"
            :src="$page.props.appLogo"
            alt="ASH Health Care"
            class="hidden lg:block h-14 w-auto transition-all duration-300"
            loading="eager"
            @error="handleImageError"
          />
          <span
            v-show="!isScrolled"
            class="hidden lg:block font-bold text-lg tracking-wider animate-text-fizzy"
          >A S H Health Care</span>
          <img
            v-show="isScrolled"
            :src="$page.props.appLogo"
            alt="ASH Health Care"
            class="hidden lg:block h-10 w-auto transition-all duration-300"
            loading="eager"
            @error="handleImageError"
          />
          <span
            v-show="isScrolled"
            class="hidden lg:block font-bold text-sm tracking-wider animate-text-fizzy"
          >A S H Health Care</span>
          <img
            :src="$page.props.appLogo"
            alt="ASH Health Care"
            class="hidden md:block lg:!hidden w-[40px] h-[40px] object-contain"
            loading="eager"
            @error="handleImageError"
          />
          <span class="hidden md:block lg:!hidden font-bold text-xs tracking-wider animate-text-fizzy">A S H Health Care</span>
        </Link>
        
        <!-- Desktop Menu -->
        <div class="hidden md:flex items-center transition-all duration-300" :class="isScrolled ? 'space-x-6' : 'space-x-8'">
          <Link 
            v-for="link in navLinks" 
            :key="link.href"
            :href="link.href" 
            class="transition-all duration-300"
            :class="[
              currentPage === link.page ? 'text-[#B89B6A] font-semibold' : 'text-white hover:text-[#B89B6A]',
              isScrolled ? 'text-sm' : 'text-base'
            ]"
          >
            {{ link.text }}
          </Link>
        </div>
        
        <!-- CTA Buttons -->
        <div class="hidden md:flex items-center space-x-4">
          <!-- Dashboard Icon (if logged in) -->
          <Link
            v-if="page.props.auth?.user"
            :href="route('admin.dashboard')"
            class="text-white hover:text-[#B89B6A] transition-all duration-300 rounded-lg hover:bg-white/10 flex items-center gap-2"
            :class="isScrolled ? 'p-1.5' : 'p-2'"
            title="Dashboard"
          >
            <svg class="transition-all duration-300" :class="isScrolled ? 'w-4 h-4' : 'w-5 h-5'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
          </Link>
          
          <!-- Member Card Button -->
          <button 
            @click="openMemberCard"
            class="bg-[#B89B6A] text-white rounded-full font-medium shadow-lg hover:bg-[#A6895A] transition-all duration-300"
            :class="isScrolled ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm'"
          >
            {{ page.props.translations?.home?.nav?.card || 'Card' }}
          </button>
 
        </div>
      </div>
    </div>
  </nav>

  <!-- Mobile Bottom Navigation Bar - App Style -->
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-[100] bg-white border-t-2 border-gray-100 shadow-2xl safe-area-inset-bottom" style="box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);">
    <div class="flex items-center justify-around px-1 py-2">
      <!-- Home -->
      <Link
        href="/"
        class="flex flex-col items-center justify-center px-3 py-2 rounded-lg transition-all min-w-[60px] relative"
        :class="currentPage === 'home' ? 'text-[#B89B6A] bg-[#B89B6A]/10' : 'text-gray-600'"
        @click="handleMobileNavClick"
      >
        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <div v-if="currentPage === 'home'" class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[#B89B6A] rounded-full"></div>
      </Link>

      <!-- Dashboard (if logged in) -->
      <Link
        v-if="page.props.auth?.user"
        :href="route('admin.dashboard')"
        class="flex flex-col items-center justify-center px-3 py-2 rounded-lg transition-all min-w-[60px] relative"
        :class="isDashboardRoute ? 'text-[#B89B6A] bg-[#B89B6A]/10' : 'text-gray-600'"
        @click="handleMobileNavClick"
      >
        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <div v-if="isDashboardRoute" class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[#B89B6A] rounded-full"></div>
      </Link>

      <!-- About -->
      <Link
        href="/about"
        class="flex flex-col items-center justify-center px-3 py-2 rounded-lg transition-all min-w-[60px] relative"
        :class="currentPage === 'about' ? 'text-[#B89B6A] bg-[#B89B6A]/10' : 'text-gray-600'"
        @click="handleMobileNavClick"
      >
        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div v-if="currentPage === 'about'" class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[#B89B6A] rounded-full"></div>
      </Link>

      <!-- Partners -->
      <Link
        href="/partners"
        class="flex flex-col items-center justify-center px-3 py-2 rounded-lg transition-all min-w-[60px] relative"
        :class="currentPage === 'partners' ? 'text-[#B89B6A] bg-[#B89B6A]/10' : 'text-gray-600'"
        @click="handleMobileNavClick"
      >
        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <div v-if="currentPage === 'partners'" class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[#B89B6A] rounded-full"></div>
      </Link>

      <!-- Contact -->
      <Link
        href="/contact-us"
        class="flex flex-col items-center justify-center px-3 py-2 rounded-lg transition-all min-w-[60px] relative"
        :class="currentPage === 'contact' ? 'text-[#B89B6A] bg-[#B89B6A]/10' : 'text-gray-600'"
        @click="handleMobileNavClick"
      >
        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <div v-if="currentPage === 'contact'" class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[#B89B6A] rounded-full"></div>
      </Link>
    </div>
  </nav>

  <!-- Mobile Top Bar (Minimal) -->
  <div 
    class="md:hidden fixed top-0 left-0 right-0 z-40 bg-gradient-to-br from-[#1E3943] to-[#13292F] safe-area-inset-top transition-transform duration-300"
    :class="mobileHeaderVisible ? 'translate-y-0' : '-translate-y-full'"
  >
    <div class="flex items-center justify-between px-4 py-3">
      <!-- Logo -->
      <Link href="/" class="flex items-center space-x-1 flex-shrink-0">
        <img
          :src="$page.props.appLogo"
          alt="ASH Health Care"
          class="w-[32px] h-[32px] object-contain"
          loading="eager"
          @error="handleImageError"
        />
        <span class="font-bold text-sm tracking-wider animate-text-fizzy">A S H Health Care</span>
      </Link>
      
      <!-- Right Side: Card Button -->
      <div class="flex items-center gap-2">
        <!-- Member Card Button -->
        <button 
          @click="openMemberCard"
          class="bg-[#B89B6A] text-white px-4 py-2 rounded-full text-sm font-medium shadow-lg hover:bg-[#A6895A] transition-colors"
        >
          {{ page.props.translations?.home?.nav?.card || 'Card' }}
        </button>
      </div>
    </div>
  </div>
  
  
  <!-- Member Card Modal -->
  <Transition name="fade">
    <div
      v-if="memberCardModalOpen"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[200] flex items-center justify-center p-4"
      @click.self="memberCardModalOpen = false"
    >
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative animate-fade-in" :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'">
        <button
          @click="memberCardModalOpen = false"
          class="absolute top-4 text-gray-400 hover:text-gray-600 transition-colors"
          :class="currentLocale === 'ar' ? 'left-4' : 'right-4'"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <h2 class="text-2xl md:text-3xl font-bold text-[#1E3943] mb-4">{{ memberCardT.title || 'Member Card' }}</h2>
        <p class="text-gray-600 mb-6">{{ memberCardT.description || 'Enter your member card number to access your benefits' }}</p>

        <form @submit.prevent="handleMemberCardSubmit" class="space-y-4">
          <div>
            <label for="navCardNumber" class="block text-sm font-medium text-gray-700 mb-2">
              {{ memberCardT.card_number || 'Card Number' }}
            </label>
            <input
              id="navCardNumber"
              v-model="memberCardNumber"
              type="text"
              :placeholder="memberCardT.placeholder || 'Enter your card number'"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent outline-none transition-all"
              :class="memberCardError ? 'border-red-400' : 'border-gray-300'"
              required
              @input="memberCardError = ''"
            />
            <p v-if="memberCardError" class="mt-2 text-sm text-red-500">{{ memberCardError }}</p>
          </div>

          <button
            type="submit"
            :disabled="memberCardLoading"
            class="w-full bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ memberCardLoading ? (memberCardT.verifying || 'Verifying...') : (memberCardT.verify || 'Verify Card') }}
          </button>
        </form>

        <p class="mt-4 text-sm text-center text-gray-500">
          {{ memberCardT.no_card || "Don't have a member card?" }} <Link href="/contact-us" class="text-[#B89B6A] hover:underline" @click="memberCardModalOpen = false">{{ memberCardT.contact_us || 'Contact us' }}</Link>
        </p>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const memberCardModalOpen = ref(false);
const memberCardNumber = ref('');
const memberCardError = ref('');
const memberCardLoading = ref(false);

const page = usePage();

defineProps({
  currentPage: {
    type: String,
    default: 'home'
  }
});

const emit = defineEmits(['open-member-card']); // kept for backwards compat

const isScrolled = ref(false);
const languageMenuOpen = ref(false);
const navHidden = ref(false);
const mobileHeaderVisible = ref(true);
const lastScrollY = ref(0);
const scrollThreshold = 10; // Minimum scroll distance to trigger hide/show
const desktopNav = ref(null);

// Language switching
const currentLocale = computed(() => page.props.locale || 'en');
const otherLanguage = computed(() => currentLocale.value === 'en' ? 'ar' : 'en');
const currentLanguageText = computed(() => currentLocale.value === 'en' ? 'AR' : 'EN');

// Member card translations
const memberCardT = computed(() => page.props.translations?.home?.member_card_modal || {});

// Check if current route is dashboard
const isDashboardRoute = computed(() => {
  const url = page.url;
  return url.includes('/admin/dashboard') || url === '/admin';
});

const navLinks = computed(() => {
  const t = page.props.translations?.home?.nav || {};
  return [
    { text: '' || ' ', href: '/', page: ' ' },
    { text: t.about || 'About Us', href: '/about', page: 'about' },
    { text: t.contact || 'Contact Us', href: '/contact-us', page: 'contact' },
    { text: t.partners || 'Partners', href: '/partners', page: 'partners' }
  ];
});

const handleScroll = () => {
  const currentScrollY = window.scrollY;
  const isDesktop = window.innerWidth >= 768;
  
  // Scroll direction tracking for both desktop and mobile
  if (isDesktop) {
    if (currentScrollY > lastScrollY.value && currentScrollY > 100) {
      if (!navHidden.value) console.log('[Nav] HIDE nav at', currentScrollY);
      navHidden.value = true;
    } else if (currentScrollY < lastScrollY.value) {
      if (navHidden.value) console.log('[Nav] SHOW nav at', currentScrollY);
      navHidden.value = false;
    }
    if (currentScrollY < 50) {
      if (navHidden.value) console.log('[Nav] SHOW nav (at top)', currentScrollY);
      navHidden.value = false;
    }
    mobileHeaderVisible.value = true;
  } else {
    if (currentScrollY > lastScrollY.value && currentScrollY > 100) {
      mobileHeaderVisible.value = false;
      navHidden.value = true;
    } else if (currentScrollY < lastScrollY.value) {
      mobileHeaderVisible.value = true;
      navHidden.value = false;
    }
    if (currentScrollY < 50) {
      mobileHeaderVisible.value = true;
      navHidden.value = false;
    }
  }
  
  console.log('[Nav] scroll:', currentScrollY, 'last:', lastScrollY.value, 'dir:', currentScrollY > lastScrollY.value ? 'down' : 'up', 'hidden:', navHidden.value);
  lastScrollY.value = currentScrollY;
  
  // Desktop scroll height change
  if (isDesktop) {
    const newScrolledState = currentScrollY > 50;
    if (isScrolled.value !== newScrolledState) {
      isScrolled.value = newScrolledState;
      
      // Force DOM update using ref
      if (desktopNav.value) {
        desktopNav.value.style.height = isScrolled.value ? '60px' : '80px';
        desktopNav.value.style.setProperty('height', isScrolled.value ? '60px' : '80px', 'important');
        const container = desktopNav.value.querySelector('.container');
        if (container) {
          container.style.paddingTop = isScrolled.value ? '4px' : '16px';
          container.style.paddingBottom = isScrolled.value ? '4px' : '16px';
        }
      }
    } else {
      if (desktopNav.value) {
        desktopNav.value.style.height = isScrolled.value ? '60px' : '80px';
      }
    }
  } else {
    isScrolled.value = false;
  }
};

const switchLanguage = (locale) => {
  window.location.href = `/lang/${locale}`;
};

const handleMobileNavClick = () => {
  // Smooth scroll to top when navigating
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const openMemberCard = () => {
  memberCardError.value = '';
  memberCardNumber.value = '';
  memberCardModalOpen.value = true;
  emit('open-member-card');
};

const handleMemberCardSubmit = () => {
  if (!memberCardNumber.value.trim()) return;

  memberCardError.value = '';
  memberCardLoading.value = true;

  router.post(route('guest.membership.lookup'), {
    membership_number: memberCardNumber.value.trim()
  }, {
    preserveScroll: true,
    onSuccess: () => {
      memberCardModalOpen.value = false;
      memberCardNumber.value = '';
      memberCardError.value = '';
    },
    onError: (errors) => {
      memberCardError.value = errors.membership_number || 'Something went wrong. Please try again.';
    },
    onFinish: () => {
      memberCardLoading.value = false;
    }
  });
};

const handleImageError = (e) => {
  console.error('Logo failed to load');
};

const handleExternalMemberCardOpen = () => {
  memberCardModalOpen.value = true;
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true });
  window.addEventListener('resize', handleScroll, { passive: true });
  window.addEventListener('open-member-card-modal', handleExternalMemberCardOpen);
  handleScroll();
  lastScrollY.value = window.scrollY;
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('resize', handleScroll);
  window.removeEventListener('open-member-card-modal', handleExternalMemberCardOpen);
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 0.3s ease-out;
}

/* Safe area insets for mobile devices with notches */
.safe-area-inset-top {
  padding-top: env(safe-area-inset-top);
}

.safe-area-inset-bottom {
  padding-bottom: env(safe-area-inset-bottom);
}

/* Mobile bottom nav styling */
@media (max-width: 767px) {
  nav.fixed.bottom-0 {
    backdrop-filter: blur(20px);
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
  }
  
  /* Active state styling */
  a[class*="text-[#B89B6A]"] {
    position: relative;
  }
  
  /* Ensure bottom nav is always visible */
  nav.fixed.bottom-0 {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
  }
}
</style>
