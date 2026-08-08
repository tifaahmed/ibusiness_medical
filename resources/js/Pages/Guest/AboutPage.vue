<template>
  <Head title="About Us - ASH Health Care" />
  
  <div class="min-h-screen bg-[#F9F6F1] pb-20 md:pb-0 relative">
    <!-- Floating Partners Background (All Sections) -->
    <FloatingPartners :contracts="contracts" />
    
    <!-- Floating Contact Buttons -->
    <FloatingContact />
    
    <!-- Navigation Component -->
    <GuestNavigation current-page="about" />
    
    <!-- News Ticker -->
    <NewsTicker />
    
    <!-- Hero Section -->
    <section class="pt-24 pb-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold mb-6 text-white">
            {{ $page.props.translations?.about?.hero?.title || 'About ASH Health Care' }}
          </h1>
          <p class="text-base md:text-xl text-white/80 max-w-3xl mx-auto">
            {{ $page.props.translations?.about?.hero?.description || 'Providing premium healthcare solutions with excellence and compassion' }}
          </p>
        </div>
      </div>
    </section>

    <!-- Our Story Section -->
    <section class="py-12 px-6">
      <div class="container mx-auto max-w-6xl">
        <div class="grid md:grid-cols-2 gap-12 items-center">
          <div :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <h2 class="text-2xl md:text-4xl font-bold mb-6 text-[#1E3943]">
              {{ $page.props.translations?.about?.story?.title || 'Our Story' }}
            </h2>
            <p class="text-sm md:text-lg text-gray-700 mb-4">
              {{ $page.props.translations?.about?.story?.paragraph_1 || 'ASH Health Care was founded with a vision to revolutionize healthcare delivery by combining cutting-edge medical technology with compassionate patient care.' }}
            </p>
            <p class="text-sm md:text-lg text-gray-700 mb-4">
              {{ $page.props.translations?.about?.story?.paragraph_2 || 'Since our inception, we have been committed to providing accessible, high-quality healthcare services to communities across the region.' }}
            </p>
            <p class="text-sm md:text-lg text-gray-700">
              {{ $page.props.translations?.about?.story?.paragraph_3 || 'Our team of dedicated healthcare professionals works tirelessly to ensure every patient receives personalized care tailored to their unique needs.' }}
            </p>
          </div>
          <div class="relative">
            <div class="bg-[#1E3943]/10 rounded-3xl p-8 backdrop-blur-sm">
              <img 
                src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80" 
                :alt="$page.props.translations?.about?.story?.image_alt || 'Healthcare Team'" 
                class="rounded-2xl shadow-2xl w-full"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Mission & Vision Section -->
    <section class="py-12 px-6 bg-white">
      <div class="container mx-auto max-w-6xl">
        <div class="grid md:grid-cols-2 gap-12">
          <!-- Mission -->
          <div class="bg-gradient-to-br from-[#1E3943] to-[#13292F] rounded-3xl p-8 text-white" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-16 h-16 bg-[#B89B6A] rounded-full flex items-center justify-center mb-6">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-xl md:text-3xl font-bold mb-4">
              {{ $page.props.translations?.about?.mission?.title || 'Our Mission' }}
            </h3>
            <p class="text-white/90 text-sm md:text-lg">
              {{ $page.props.translations?.about?.mission?.description || 'To deliver exceptional healthcare services that improve the quality of life for our patients through innovation, dedication, and compassionate care.' }}
            </p>
          </div>
          
          <!-- Vision -->
          <div class="bg-gradient-to-br from-[#B89B6A] to-[#8B7355] rounded-3xl p-8 text-white" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-6">
              <svg class="w-8 h-8 text-[#B89B6A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </div>
            <h3 class="text-xl md:text-3xl font-bold mb-4">
              {{ $page.props.translations?.about?.vision?.title || 'Our Vision' }}
            </h3>
            <p class="text-white/90 text-sm md:text-lg">
              {{ $page.props.translations?.about?.vision?.description || 'To be the leading healthcare provider in the region, recognized for excellence in patient care, medical innovation, and community wellness.' }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Values Section -->
    <section class="py-12 px-6">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center mb-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl font-bold mb-4 text-[#1E3943]">
            {{ $page.props.translations?.about?.values?.title || 'Our Core Values' }}
          </h2>
          <p class="text-base md:text-xl text-gray-600">
            {{ $page.props.translations?.about?.values?.subtitle || 'The principles that guide everything we do' }}
          </p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
          <div class="text-center p-8 rounded-2xl bg-white shadow-lg hover:shadow-xl transition-shadow" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#1E3943] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.about?.values?.compassion?.title || 'Compassion' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.about?.values?.compassion?.description || 'We treat every patient with empathy, respect, and genuine care' }}
            </p>
          </div>
          
          <div class="text-center p-8 rounded-2xl bg-white shadow-lg hover:shadow-xl transition-shadow" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#B89B6A] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.about?.values?.excellence?.title || 'Excellence' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.about?.values?.excellence?.description || 'We maintain the highest standards in medical care and service delivery' }}
            </p>
          </div>
          
          <div class="text-center p-8 rounded-2xl bg-white shadow-lg hover:shadow-xl transition-shadow" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#1E3943] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.about?.values?.innovation?.title || 'Innovation' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.about?.values?.innovation?.description || 'We embrace cutting-edge technology to enhance patient outcomes' }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Card Benefits Section -->
    <section class="py-12 px-6 bg-white">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center mb-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl font-bold mb-4 text-[#1E3943]">
            {{ $page.props.translations?.about?.benefits?.title || 'Card Benefits' }}
          </h2>
          <p class="text-base md:text-xl text-gray-600">
            {{ $page.props.translations?.about?.benefits?.subtitle || 'All the advantages you get with the ASH Health Care card' }}
          </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <div
            v-for="(benefit, index) in benefitItems"
            :key="`benefit-${index}`"
            class="relative bg-gradient-to-br from-[#F9F6F1] to-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all border border-[#1E3943]/5"
          >
            <div class="w-12 h-12 bg-[#B89B6A] rounded-xl flex items-center justify-center mb-4 text-white font-bold text-lg">
              {{ index + 1 }}
            </div>
            <h3 class="text-lg md:text-xl font-bold mb-3 text-[#1E3943]">
              {{ benefit.title }}
            </h3>
            <p class="text-sm md:text-base text-gray-700 whitespace-pre-line leading-relaxed">
              {{ benefit.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-12 px-6">
      <div class="container mx-auto max-w-4xl">
        <div class="text-center mb-10" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl font-bold mb-4 text-[#1E3943]">
            {{ $page.props.translations?.about?.faq?.title || 'Frequently Asked Questions' }}
          </h2>
          <p class="text-base md:text-xl text-gray-600">
            {{ $page.props.translations?.about?.faq?.subtitle || 'Everything you need to know about the ASH Health Care card' }}
          </p>
        </div>

        <div class="space-y-4" :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'">
          <div
            v-for="(item, index) in faqItems"
            :key="`faq-${index}`"
            class="bg-white rounded-2xl shadow-md hover:shadow-lg transition-shadow overflow-hidden border border-[#1E3943]/5"
          >
            <button
              type="button"
              class="w-full flex items-center justify-between gap-4 p-5 md:p-6 text-start focus:outline-none focus:ring-2 focus:ring-[#B89B6A]/50"
              :aria-expanded="openFaq === index"
              @click="toggleFaq(index)"
            >
              <span class="text-base md:text-lg font-bold text-[#1E3943] flex-1">
                {{ item.question }}
              </span>
              <span
                class="flex-shrink-0 w-8 h-8 rounded-full bg-[#1E3943] text-white flex items-center justify-center transition-transform duration-300"
                :class="{ 'rotate-180': openFaq === index }"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </button>
            <div
              v-show="openFaq === index"
              class="px-5 md:px-6 pb-5 md:pb-6 -mt-1"
            >
              <div class="border-t border-gray-100 pt-4">
                <p class="text-sm md:text-base text-gray-700 whitespace-pre-line leading-relaxed">
                  {{ item.answer }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <GuestFooter />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import GuestNavigation from './_components/GuestNavigation.vue';
import NewsTicker from './_components/NewsTicker.vue';
import GuestFooter from './_components/GuestFooter.vue';
import FloatingPartners from './_components/FloatingPartners.vue';
import FloatingContact from './_components/FloatingContact.vue';

const props = defineProps({
  contracts: {
    type: Array,
    default: () => []
  },
  faqs: {
    type: Array,
    default: () => []
  }
});

const page = usePage();

const localized = (value) => {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const locale = page.props.locale || 'ar';
    return value[locale] || value.ar || value.en || Object.values(value)[0] || '';
  }
  return '';
};

const faqItems = computed(() => {
  if (Array.isArray(props.faqs) && props.faqs.length > 0) {
    return props.faqs.map((faq) => ({
      question: localized(faq.question),
      answer: localized(faq.answer),
    }));
  }
  return page.props.translations?.about?.faq?.items || [];
});

const benefitItems = computed(() => page.props.translations?.about?.benefits?.items || []);

const openFaq = ref(0);
const toggleFaq = (index) => {
  openFaq.value = openFaq.value === index ? -1 : index;
};
</script>

