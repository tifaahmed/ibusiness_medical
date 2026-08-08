<template>
  <Head
    :title="pageTitle"
    :meta="metaTags"
  />

  <div class="min-h-screen bg-[#F9F6F1] pb-20 md:pb-0 relative">
    <!-- Floating Partners Background -->
    <FloatingPartners :contracts="contracts" />

    <!-- Floating Contact Buttons -->
    <FloatingContact />

    <!-- Navigation -->
    <GuestNavigation current-page="partners" />

    <!-- Hero Section -->
    <section class="pt-24 pb-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto max-w-6xl">
        <!-- Back link -->
        <div class="mb-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <Link
            :href="route('partners')"
            class="inline-flex items-center gap-2 text-white/70 hover:text-white transition-colors text-sm"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              :class="locale === 'ar' ? 'rotate-180' : ''"
            >
              <path d="m15 18-6-6 6-6"/>
            </svg>
            {{ t('back_to_partners') }}
          </Link>
        </div>

        <div :dir="locale === 'ar' ? 'rtl' : 'ltr'" class="flex items-start gap-5">
          <img
            v-if="facility.logo"
            :src="facility.logo"
            :alt="getTranslatedName(facility.name)"
            class="h-20 w-20 object-contain rounded-xl bg-white/10 p-1 shrink-0"
          />
          <div>
          <h1 class="text-3xl md:text-5xl font-bold mb-4 text-white">
            {{ getTranslatedName(facility.name) }}
          </h1>
          <div class="flex flex-wrap gap-2 mt-4">
            <span
              v-if="facility.facility_type"
              class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold bg-[#B89B6A] text-white"
            >
              {{ getTranslatedName(facility.facility_type.name) }}
            </span>
            <span
              v-if="facility.governorate"
              class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold bg-white/20 text-white"
            >
              {{ getTranslatedName(facility.governorate.name) }}
            </span>
          </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Cover Image -->
    <section v-if="facility.image" class="w-full">
      <img
        :src="facility.image"
        :alt="getTranslatedName(facility.name)"
        class="w-full max-h-80 object-cover"
      />
    </section>

    <!-- Gallery Section -->
    <section v-if="facility.gallery && facility.gallery.length" class="py-12 px-6">
      <div class="container mx-auto max-w-6xl">
        <h2 class="text-2xl md:text-3xl font-bold text-[#1E3943] mb-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          {{ t('gallery_title') || 'Gallery' }}
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
          <img
            v-for="item in facility.gallery"
            :key="item.id"
            :src="item.url"
            alt="Gallery"
            class="w-full aspect-square object-cover rounded-xl"
          />
        </div>
      </div>
    </section>

    <!-- Branches Section -->
    <section class="py-12 px-6">
      <div class="container mx-auto max-w-6xl">
        <!-- Heading + count -->
        <div class="flex items-center gap-3 mb-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-3xl font-bold text-[#1E3943]">
            {{ t('branches_title') }}
          </h2>
          <span class="bg-[#1E3943] text-white text-sm font-semibold px-3 py-1 rounded-full">
            {{ branches.length }}
          </span>
        </div>

        <!-- Branch Search -->
        <div class="mb-6">
          <div class="relative max-w-md">
            <svg
              xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.3-4.3"></path>
            </svg>
            <input
              v-model="branchSearchInput"
              @input="handleBranchSearch"
              type="text"
              :dir="locale === 'ar' ? 'rtl' : 'ltr'"
              :placeholder="t('branch_search_placeholder')"
              class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent outline-none transition-all bg-white"
              style="color: #000000;"
            />
          </div>
        </div>

        <!-- Branch Cards -->
        <div v-if="branches.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <div
            v-for="branch in branches"
            :key="branch.id"
            class="bg-white rounded-2xl p-6 shadow-md hover:shadow-lg transition-all duration-300"
          >
            <h3 class="text-lg font-bold text-[#1E3943] mb-3">
              {{ getTranslatedName(branch.name) }}
            </h3>

            <div v-if="branch.address" class="flex gap-2 mb-3 text-sm text-gray-600">
              <span class="font-semibold text-[#1E3943] shrink-0">{{ t('address') }}</span>
              <span>{{ getTranslatedName(branch.address) }}</span>
            </div>

            <div v-if="getTranslatedName(branch.governorate) || getTranslatedName(branch.city)" class="flex gap-2 mb-3 text-sm text-gray-600">
              <span v-if="getTranslatedName(branch.governorate)" class="inline-flex items-center gap-1">
                <span class="font-semibold text-[#1E3943] shrink-0">{{ t('governorate') }}</span>
                <span>{{ getTranslatedName(branch.governorate) }}</span>
              </span>
              <span v-if="getTranslatedName(branch.city)" class="inline-flex items-center gap-1">
                <span class="font-semibold text-[#1E3943] shrink-0">{{ t('city') }}</span>
                <span>{{ getTranslatedName(branch.city) }}</span>
              </span>
            </div>

            <div v-if="branch.phone && getPhonesArray(branch.phone).length > 0">
              <span class="text-sm font-semibold text-[#1E3943] block mb-2">{{ t('phone') }}</span>
              <div class="flex flex-col gap-1.5">
                <a
                  v-for="(phone, index) in getPhonesArray(branch.phone)"
                  :key="index"
                  :href="`tel:${phone}`"
                  class="inline-flex items-center gap-1.5 text-xs text-[#B89B6A] hover:text-[#8B7355] transition-colors bg-[#B89B6A]/5 hover:bg-[#B89B6A]/10 rounded-lg px-2.5 py-1.5 border border-[#B89B6A]/15 hover:border-[#B89B6A]/30 w-fit"
                >
                  <svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <span class="font-medium">{{ phone }}</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty states -->
        <div v-else class="text-center py-16" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <p class="text-gray-500 text-lg">
            {{ branchSearch ? t('no_branches_found') : t('no_branches') }}
          </p>
        </div>
      </div>
    </section>

    <!-- Same-Name Section -->
    <section v-if="sameName.length > 0" class="py-12 px-6 bg-white">
      <div class="container mx-auto max-w-6xl" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <h2 class="text-2xl md:text-3xl font-bold text-[#1E3943] mb-6">
          {{ t('same_name_title') }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <a
            v-for="f in sameName"
            :key="f.id"
            :href="route('partners.show', f.slug)"
            class="block bg-[#E8F0F2] border border-[#1E3943]/20 rounded-xl p-5 hover:shadow-md transition-all duration-300 hover:-translate-y-1 group"
            @click.prevent="navigateWithScroll(route('partners.show', f.slug))"
          >
            <h3 class="font-bold text-[#1E3943] group-hover:text-[#B89B6A] transition-colors mb-2">
              {{ getTranslatedName(f.name) }}
            </h3>
            <div class="flex flex-wrap gap-2">
              <span v-if="f.facility_type" class="text-xs px-2.5 py-1 rounded-full bg-[#1E3943]/10 text-[#1E3943]">
                {{ getTranslatedName(f.facility_type.name) }}
              </span>
              <span v-if="f.governorate" class="text-xs px-2.5 py-1 rounded-full bg-[#B89B6A]/10 text-[#B89B6A]">
                {{ getTranslatedName(f.governorate.name) }}
              </span>
            </div>
          </a>
        </div>
      </div>
    </section>

    <!-- Same-Category Section -->
    <section v-if="sameCategory.length > 0" class="py-12 px-6">
      <div class="container mx-auto max-w-6xl" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <h2 class="text-2xl md:text-3xl font-bold text-[#1E3943] mb-6">
          {{ t('same_category_title') }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <a
            v-for="f in sameCategory"
            :key="f.id"
            :href="route('partners.show', f.slug)"
            class="block bg-[#FDF5E8] border border-[#B89B6A]/30 rounded-xl p-5 hover:shadow-md transition-all duration-300 hover:-translate-y-1 group"
            @click.prevent="navigateWithScroll(route('partners.show', f.slug))"
          >
            <h3 class="font-bold text-[#1E3943] group-hover:text-[#B89B6A] transition-colors mb-2">
              {{ getTranslatedName(f.name) }}
            </h3>
            <div class="flex flex-wrap gap-2">
              <span v-if="f.facility_type" class="text-xs px-2.5 py-1 rounded-full bg-[#1E3943]/10 text-[#1E3943]">
                {{ getTranslatedName(f.facility_type.name) }}
              </span>
              <span v-if="f.governorate" class="text-xs px-2.5 py-1 rounded-full bg-[#B89B6A]/10 text-[#B89B6A]">
                {{ getTranslatedName(f.governorate.name) }}
              </span>
            </div>
          </a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <GuestFooter />
  </div>
</template>

<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import GuestNavigation from './_components/GuestNavigation.vue';
import GuestFooter from './_components/GuestFooter.vue';
import FloatingPartners from './_components/FloatingPartners.vue';
import FloatingContact from './_components/FloatingContact.vue';

const props = defineProps({
  facility: {
    type: Object,
    required: true,
  },
  branches: {
    type: Array,
    default: () => [],
  },
  branchSearch: {
    type: String,
    default: '',
  },
  sameName: {
    type: Array,
    default: () => [],
  },
  sameCategory: {
    type: Array,
    default: () => [],
  },
  contracts: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();

const navigateWithScroll = (href) => {
  router.visit(href, {
    preserveScroll: true,
    onSuccess: () => {
      document.body.scrollTo({ top: 0, behavior: 'smooth' });
    },
  });
};

const locale = page.props.locale || 'ar';

const t = (key) => page.props.translations?.partners?.show?.[key] ?? key;

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getPhonesArray = (phone) => {
  if (!phone) return [];
  if (typeof phone === 'string' && phone.trim() !== '') return [phone.trim()];
  if (Array.isArray(phone)) {
    return phone.filter(p => p && String(p).trim().length > 0).map(p => String(p).trim());
  }
  return [];
};

const branchSearchInput = ref(props.branchSearch);
let searchTimeout = null;

const handleBranchSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      route('partners.show', props.facility.slug),
      { branch_search: branchSearchInput.value },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  }, 350);
};

const pageTitle = getTranslatedName(props.facility.name) + ' - ASH Health Care';

const metaTags = [
  { name: 'robots', content: 'index, follow' },
  { name: 'theme-color', content: '#1E3943' },
];
</script>
