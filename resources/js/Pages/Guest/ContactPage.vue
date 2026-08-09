<template>
  <Head 
    :title="pageTitle"
    :meta="metaTags"
    :link="linkTags"
  />
  
  <div class="min-h-screen bg-[#F9F6F1] pb-20 md:pb-0 relative">
    <!-- Floating Partners Background (All Sections) -->
    <FloatingPartners :contracts="$page.props.contracts" />
    
    <!-- Floating Contact Buttons -->
    <FloatingContact />
    
    <!-- Navigation Component -->
    <GuestNavigation current-page="contact" />
    
    <!-- News Ticker -->
    <NewsTicker />
    
    <!-- Hero Section -->
    <section class="pt-24 pb-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold mb-6 text-white">
            {{ $page.props.translations?.contact?.hero?.title || 'Get In Touch' }}
          </h1>
          <p class="text-base md:text-xl text-white/80 max-w-3xl mx-auto">
            {{ $page.props.translations?.contact?.hero?.description || 'We\'re here to help. Reach out to us through any of the channels below' }}
          </p>
        </div>
      </div>
    </section>

    <!-- Contact Information & Form Section -->
    <section class="py-12 px-6">
      <div class="container mx-auto max-w-6xl">
        <div class="grid md:grid-cols-2 gap-12" :class="$page.props.locale === 'ar' ? 'md:grid-flow-col-dense' : ''">
          <!-- Contact Information -->
          <div :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'" :class="$page.props.locale === 'ar' ? 'md:col-start-2' : ''">
            <h2 class="text-2xl md:text-4xl font-bold mb-8 text-[#1E3943]">
              {{ $page.props.translations?.contact?.contact_info?.title || 'Contact Information' }}
            </h2>
            
            <!-- Phone -->
            <div class="flex items-start mb-6 group" :class="$page.props.locale === 'ar' ? 'flex-row-reverse' : ''">
              <div class="w-12 h-12 bg-[#1E3943] rounded-full flex items-center justify-center flex-shrink-0 group-hover:bg-[#B89B6A] transition-colors" :class="$page.props.locale === 'ar' ? 'ml-4' : 'mr-4'">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base md:text-xl font-semibold mb-1 text-[#1E3943]">
                  {{ $page.props.translations?.contact?.contact_info?.phone || 'Phone' }}
                </h3>
                <a :href="`tel:+20${phoneNumber.replace(/^0/, '')}`" class="text-gray-600 hover:text-[#B89B6A] transition-colors">
                  {{ phoneNumber }}
                </a>
              </div>
            </div>

            <!-- WhatsApp -->
            <div class="flex items-start mb-6 group" :class="$page.props.locale === 'ar' ? 'flex-row-reverse' : ''">
              <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center flex-shrink-0 group-hover:bg-green-700 transition-colors" :class="$page.props.locale === 'ar' ? 'ml-4' : 'mr-4'">
                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-base md:text-xl font-semibold mb-1 text-[#1E3943]">
                  {{ $page.props.translations?.contact?.contact_info?.whatsapp || 'WhatsApp' }}
                </h3>
                <a :href="`https://wa.me/20${whatsappNumber.replace(/^0/, '')}`" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-green-600 transition-colors">
                  {{ whatsappNumber }}
                </a>
              </div>
            </div>

            <!-- Facebook -->
            <div class="flex items-start mb-6 group" :class="$page.props.locale === 'ar' ? 'flex-row-reverse' : ''">
              <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0 group-hover:bg-blue-700 transition-colors" :class="$page.props.locale === 'ar' ? 'ml-4' : 'mr-4'">
                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-base md:text-xl font-semibold mb-1 text-[#1E3943]">
                  {{ $page.props.translations?.contact?.contact_info?.facebook || 'Facebook' }}
                </h3>
                <a :href="$page.props.translations?.home?.footer?.facebook || 'https://www.facebook.com/share/19nhbGft4e/'" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-blue-600 transition-colors">
                  {{ $page.props.translations?.contact?.contact_info?.facebook_link || 'Visit our Facebook page' }}
                </a>
              </div>
            </div>

            <!-- Email -->
            <div class="flex items-start mb-6 group" :class="$page.props.locale === 'ar' ? 'flex-row-reverse' : ''">
              <div class="w-12 h-12 bg-[#1E3943] rounded-full flex items-center justify-center flex-shrink-0 group-hover:bg-[#B89B6A] transition-colors" :class="$page.props.locale === 'ar' ? 'ml-4' : 'mr-4'">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base md:text-xl font-semibold mb-1 text-[#1E3943]">
                  {{ $page.props.translations?.contact?.contact_info?.email || 'Email' }}
                </h3>
                <a href="mailto:info@ashhealthcare.com" class="text-gray-600 hover:text-[#B89B6A] transition-colors">
                  info@ashhealthcare.com
                </a>
              </div>
            </div>

            <!-- Address -->
            <div class="flex items-start group" :class="$page.props.locale === 'ar' ? 'flex-row-reverse' : ''">
              <div class="w-12 h-12 bg-[#1E3943] rounded-full flex items-center justify-center flex-shrink-0 group-hover:bg-[#B89B6A] transition-colors" :class="$page.props.locale === 'ar' ? 'ml-4' : 'mr-4'">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base md:text-xl font-semibold mb-1 text-[#1E3943]">
                  {{ $page.props.translations?.contact?.contact_info?.address || 'Address' }}
                </h3>
                <p class="text-gray-600">
                  {{ $page.props.translations?.home?.footer?.address || 'Cairo, Egypt' }}<br>
                  <a href="https://www.google.com/maps/search/?api=1&query=Cairo,+Egypt" target="_blank" rel="noopener noreferrer" class="text-[#B89B6A] hover:underline">
                    {{ $page.props.translations?.contact?.contact_info?.view_on_maps || 'View on Google Maps' }}
                  </a>
                </p>
              </div>
            </div>
          </div>

          <!-- Contact Form -->
          <div class="bg-white rounded-3xl p-8 shadow-xl" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'" :class="$page.props.locale === 'ar' ? 'md:col-start-1' : ''">
            <h2 class="text-2xl md:text-3xl font-bold mb-6 text-[#1E3943]">
              {{ $page.props.translations?.contact?.form?.title || 'Send us a Message' }}
            </h2>
            
            <form @submit.prevent="submitForm" class="space-y-6">
              <!-- Phone -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  {{ $page.props.translations?.contact?.form?.phone || 'Phone *' }}
                </label>
                <input
                  v-model="form.phone"
                  type="tel"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent transition-all"
                  :placeholder="$page.props.translations?.contact?.form?.phone_placeholder || '+20 XXX XXX XXXX'"
                />
                <span v-if="errors.phone" class="text-red-500 text-sm mt-1">{{ errors.phone }}</span>
              </div>

              <!-- Message -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  {{ $page.props.translations?.contact?.form?.message || 'Message *' }}
                </label>
                <textarea
                  v-model="form.message"
                  required
                  rows="5"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent transition-all resize-none"
                  :placeholder="$page.props.translations?.contact?.form?.message_placeholder || 'Tell us more about your inquiry...'"
                ></textarea>
                <span v-if="errors.message" class="text-red-500 text-sm mt-1">{{ errors.message }}</span>
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="isSubmitting"
                class="w-full bg-gradient-to-r from-[#1E3943] to-[#13292F] text-white py-4 rounded-lg font-semibold hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="!isSubmitting">
                  {{ $page.props.translations?.contact?.form?.send_message || 'Send Message' }}
                </span>
                <span v-else>
                  {{ $page.props.translations?.contact?.form?.sending || 'Sending...' }}
                </span>
              </button>

              <!-- Success Message -->
              <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                {{ successMessage }}
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>

    <!-- Map Section -->
    <section class="py-12 px-6 bg-white">
      <div class="container mx-auto max-w-6xl">
        <h2 class="text-2xl md:text-4xl font-bold mb-8 text-center text-[#1E3943]" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          {{ $page.props.translations?.contact?.map?.title || 'Find Us' }}
        </h2>
        <div class="rounded-3xl overflow-hidden shadow-xl h-96 bg-gray-200">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d110502.60389588467!2d31.1884235!3d30.0444196!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14583fa60b21beeb%3A0x79dfb296e8423bba!2sCairo%2C%20Egypt!5e0!3m2!1sen!2sus!4v1234567890123!5m2!1sen!2sus"
            width="100%"
            height="100%"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            class="w-full h-full"
          ></iframe>
        </div>
        <div class="mt-6 text-center" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <p class="text-gray-600 mb-4">
            {{ $page.props.translations?.home?.footer?.address || 'Cairo, Egypt' }}
          </p>
          <a
            href="https://www.google.com/maps/search/?api=1&query=Cairo,+Egypt"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-[#B89B6A] hover:text-[#8B7355] transition-colors font-medium"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            {{ $page.props.translations?.contact?.map?.open_in_maps || 'Open in Google Maps' }}
          </a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <GuestFooter />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import GuestNavigation from './_components/GuestNavigation.vue';
import NewsTicker from './_components/NewsTicker.vue';
import GuestFooter from './_components/GuestFooter.vue';
import FloatingPartners from './_components/FloatingPartners.vue';
import FloatingContact from './_components/FloatingContact.vue';

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
  return `${appUrl.value}/contact-us`;
});
const baseUrl = computed(() => appUrl.value);

// SEO Data
const pageTitle = computed(() => {
  return locale.value === 'ar' 
    ? 'تواصل معنا - ASH Health Care | كارت خصومات طبي'
    : 'Contact Us - ASH Health Care | Medical Discount Card';
});

const metaDescription = computed(() => {
  return locale.value === 'ar'
    ? 'تواصل مع ASH Health Care. نحن هنا للمساعدة. يمكنك الوصول إلينا عبر الهاتف، واتساب، البريد الإلكتروني، أو زيارة صفحتنا على فيسبوك. اطلب معلومات حول كارت الخصومات الطبي الشامل.'
    : 'Contact ASH Health Care. We are here to help. Reach out to us via phone, WhatsApp, email, or visit our Facebook page. Get information about our comprehensive medical discount card.';
});

const metaKeywords = computed(() => {
  return locale.value === 'ar'
    ? 'تواصل معنا، ASH Health Care، كارت خصومات طبي، رعاية صحية، اتصل بنا، معلومات الاتصال، تأمين طبي'
    : 'contact us, ASH Health Care, medical discount card, healthcare, call us, contact information, health insurance';
});

const ogImage = computed(() => {
  return page.props.appLogo;
});

// Meta Tags
const metaTags = computed(() => {
  const tags = [
    { name: 'description', content: metaDescription.value },
    { name: 'keywords', content: metaKeywords.value },
    { name: 'author', content: 'ASH Health Care' },
    { name: 'robots', content: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' },
    { name: 'googlebot', content: 'index, follow' },
    { name: 'language', content: locale.value === 'ar' ? 'Arabic' : 'English' },
    
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: currentUrl.value },
    { property: 'og:title', content: pageTitle.value },
    { property: 'og:description', content: metaDescription.value },
    { property: 'og:image', content: ogImage.value },
    { property: 'og:image:width', content: '1200' },
    { property: 'og:image:height', content: '630' },
    { property: 'og:image:alt', content: 'ASH Health Care - Contact Us' },
    { property: 'og:site_name', content: 'ASH Health Care' },
    { property: 'og:locale', content: locale.value === 'ar' ? 'ar_EG' : 'en_US' },
    { property: 'og:locale:alternate', content: locale.value === 'ar' ? 'en_US' : 'ar_EG' },
    
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:url', content: currentUrl.value },
    { name: 'twitter:title', content: pageTitle.value },
    { name: 'twitter:description', content: metaDescription.value },
    { name: 'twitter:image', content: ogImage.value },
    { name: 'twitter:image:alt', content: 'ASH Health Care - Contact Us' },
    { name: 'twitter:site', content: '@ashhealthcare' },
    { name: 'twitter:creator', content: '@ashhealthcare' },
    
    { name: 'theme-color', content: '#1E3943' },
  ];
  
  return tags;
});

// Link Tags
const linkTags = computed(() => {
  const canonicalUrl = currentUrl.value.split('?')[0];
  
  const links = [
    { rel: 'canonical', href: canonicalUrl },
    { rel: 'alternate', hreflang: 'en', href: `${baseUrl.value}/contact-us` },
    { rel: 'alternate', hreflang: 'ar', href: `${baseUrl.value}/contact-us` },
    { rel: 'alternate', hreflang: 'x-default', href: `${baseUrl.value}/contact-us` },
  ];
  
  return links;
});

// Structured Data (JSON-LD) - Inject into head
const injectStructuredData = () => {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  const contactPageData = {
    '@context': 'https://schema.org',
    '@type': 'ContactPage',
    'name': pageTitle.value,
    'description': metaDescription.value,
    'url': currentUrl.value,
    'mainEntity': {
      '@type': 'Organization',
      'name': 'ASH Health Care',
      'url': baseUrl.value,
      'contactPoint': {
        '@type': 'ContactPoint',
        'telephone': '+20-1156385251',
        'contactType': 'customer service',
        'email': 'info@ashhealthcare.com',
        'availableLanguage': ['English', 'Arabic'],
        'areaServed': {
          '@type': 'Country',
          'name': 'Egypt'
        }
      },
      'address': {
        '@type': 'PostalAddress',
        'addressLocality': locale.value === 'ar' ? 'القاهرة' : 'Cairo',
        'addressCountry': 'EG'
      }
    }
  };

  const structuredData = JSON.stringify(contactPageData);
  
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

// Get contact information from translations
const whatsappNumber = page.props.translations?.home?.footer?.whatsapp?.replace(/[^0-9]/g, '') || '01156385251';
const phoneNumber = whatsappNumber; // Use same number for phone

const form = ref({
  phone: '',
  message: ''
});

const errors = ref({});
const isSubmitting = ref(false);
const successMessage = ref('');

const submitForm = async () => {
  isSubmitting.value = true;
  errors.value = {};
  successMessage.value = '';

  try {
    await router.post('/api/contact-messages', form.value, {
      onSuccess: () => {
        successMessage.value = page.props.translations?.contact?.form?.success || 'Thank you for your message! We will get back to you soon.';
        form.value = {
          phone: '',
          message: ''
        };
      },
      onError: (err) => {
        errors.value = err;
      },
      onFinish: () => {
        isSubmitting.value = false;
      }
    });
  } catch (error) {
    console.error('Form submission error:', error);
    isSubmitting.value = false;
  }
};

onMounted(() => {
  injectStructuredData();
});
</script>

