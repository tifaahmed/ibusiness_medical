<template>
  <Head 
    :title="pageTitle"
    :meta="metaTags"
    :link="linkTags"
  />
  
  <div class="min-h-screen bg-[#F9F6F1] pb-20 md:pb-0 relative">
    <!-- Floating Partners Background (All Sections) -->
    <FloatingPartners :contracts="contracts" />
    
    <!-- Floating Contact Buttons -->
    <FloatingContact />
    
    <!-- Navigation Component -->
    <GuestNavigation current-page="partners" />
    
    <!-- News Ticker -->
    <NewsTicker />
    
    <!-- Hero Section -->
    <section class="pt-24 pb-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center">
          <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold mb-6 text-white" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            {{ $page.props.translations?.partners?.hero?.title || 'Our Partners' }}
          </h1>
          <p class="text-base md:text-xl text-white/80 max-w-3xl mx-auto" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            {{ $page.props.translations?.partners?.hero?.description || 'Collaborating with leading healthcare organizations to deliver excellence' }}
          </p>
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

    <!-- Facilities Grid Section -->
    <section class="py-12 px-2 md:px-6 overflow-visible">
      <div class="container mx-auto max-w-7xl overflow-visible">
        <div class="text-center mb-6 md:mb-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl font-bold mb-4 text-[#1E3943]">
            {{ $page.props.translations?.partners?.section?.title || 'Trusted Healthcare Partners' }}
          </h2>
          <p class="text-base md:text-xl text-gray-600 max-w-3xl mx-auto">
            {{ $page.props.translations?.partners?.section?.description || 'We work with the best healthcare providers, medical institutions, and insurance companies to ensure comprehensive coverage for our members' }}
          </p>
        </div>

        <!-- Filters Section -->
        <div class="mb-4 md:mb-6 bg-white rounded-xl md:rounded-2xl p-3 md:p-4 shadow-md overflow-visible">
          <div class="flex flex-col md:flex-row gap-2 md:gap-3 items-end overflow-visible">
            <!-- Search Filter -->
            <div class="flex-1 w-full md:w-auto min-w-0">
              <label for="search_filter" class="block text-xs md:text-sm font-medium text-[#1E3943] mb-1" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                {{ $page.props.translations?.partners?.filters?.search || 'Search by Name' }}
              </label>
              <div ref="searchContainer" class="relative">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  :class="['absolute top-1/2 -translate-y-1/2 text-gray-400 z-10 pointer-events-none', locale === 'ar' ? 'right-2.5' : 'left-2.5']"
                >
                  <circle cx="11" cy="11" r="8"></circle>
                  <path d="m21 21-4.3-4.3"></path>
                </svg>
                <input
                  id="search_filter"
                  v-model="selectedFilters.search"
                  @input="handleSearch"
                  @focus="showSuggestions = true"
                  @keydown.escape="showSuggestions = false"
                  @keydown.down.prevent="moveSuggestion(1)"
                  @keydown.up.prevent="moveSuggestion(-1)"
                  @keydown.enter.prevent="confirmHighlighted"
                  autocomplete="off"
                  type="text"
                  :dir="locale === 'ar' ? 'rtl' : 'ltr'"
                  :placeholder="$page.props.translations?.partners?.filters?.search_placeholder || 'Search facilities...'"
                  :class="['w-full py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent outline-none transition-all bg-white', locale === 'ar' ? 'pr-8 pl-3' : 'pl-8 pr-3']"
                  style="color: #000000;"
                />

                <!-- Suggestions dropdown -->
                <ul
                  v-if="showSuggestions && suggestions.length > 0"
                  :dir="locale === 'ar' ? 'rtl' : 'ltr'"
                  class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-64 overflow-y-auto"
                >
                  <li
                    v-for="(item, index) in suggestions"
                    :key="item.slug"
                    @mousedown.prevent="selectSuggestion(item)"
                    :class="[
                      'px-3 py-2 cursor-pointer text-sm flex items-center gap-2 transition-colors',
                      locale === 'ar' ? 'flex-row-reverse' : '',
                      highlightedIndex === index
                        ? 'bg-[#1E3943] text-white'
                        : 'text-[#1E3943] hover:bg-[#F9F6F1]'
                    ]"
                  >
                    <svg class="w-3.5 h-3.5 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                    <span class="flex-1" :class="locale === 'ar' ? 'text-right' : 'text-left'">{{ item.name }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Governorate Filter -->
            <div class="flex-1 w-full md:w-auto relative z-10 min-w-0">
              <label for="governorate_filter" class="block text-xs md:text-sm font-medium text-[#1E3943] mb-1" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                {{ $page.props.translations?.partners?.filters?.governorate || 'Governorate' }}
              </label>
              <div class="relative">
                <GuestSearchableSelect
                  id="governorate_filter"
                  v-model="selectedFilters.governorate_id"
                  @change="handleGovernorateChange"
                  :options="governorateOptions"
                  :placeholder="$page.props.translations?.partners?.filters?.all_governorates || 'All Governorates'"
                  :search-placeholder="$page.props.translations?.partners?.filters?.filter_search || 'Search...'"
                  :no-results-text="locale === 'ar' ? 'لا توجد نتائج' : 'No results found'"
                  :locale="locale"
                />
              </div>
            </div>

            <!-- Facility Type Filter -->
            <div class="flex-1 w-full md:w-auto relative z-10 min-w-0">
              <label for="facility_type_filter" class="block text-xs md:text-sm font-medium text-[#1E3943] mb-1" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                {{ $page.props.translations?.partners?.filters?.facility_type || 'Facility Type' }}
              </label>
              <div class="relative">
                <GuestSearchableSelect
                  id="facility_type_filter"
                  v-model="selectedFilters.facility_type_id"
                  @change="applyFilters"
                  :disabled="!selectedFilters.governorate_id"
                  :options="facilityTypeOptions"
                  :placeholder="selectedFilters.governorate_id ? ($page.props.translations?.partners?.filters?.all_facility_types || 'All Facility Types') : ($page.props.translations?.partners?.filters?.select_governorate_first || 'Select Governorate First')"
                  :search-placeholder="$page.props.translations?.partners?.filters?.filter_search || 'Search...'"
                  :no-results-text="locale === 'ar' ? 'لا توجد نتائج' : 'No results found'"
                  :locale="locale"
                />
              </div>
            </div>

            <!-- Clear Filters Button -->
            <div v-if="hasActiveFilters" class="w-full md:w-auto shrink-0">
              <button
                @click="clearFilters"
                class="w-full md:w-auto px-3 md:px-4 py-2 text-sm bg-gray-200 text-[#1E3943] rounded-lg hover:bg-gray-300 transition-all font-medium flex items-center justify-center gap-1.5"
                :dir="locale === 'ar' ? 'rtl' : 'ltr'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path>
                  <path d="m6 6 12 12"></path>
                </svg>
                {{ $page.props.translations?.partners?.filters?.clear_filters || 'Clear Filters' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Facilities Grid -->
        <div v-if="facilities && facilities.data && facilities.data.length > 0" class="space-y-8">
          <div class="grid grid-cols-2 lg:grid-cols-3 gap-2 md:gap-6" :dir="dir">
            <div
              v-for="facility in facilities.data"
              :key="facility.id"
              class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden flex flex-col"
            >
              <!-- Cover Image (clickable) -->
              <a :href="route('partners.show', facility.slug)" @click.prevent="navigateWithScroll(route('partners.show', facility.slug))">
                <div v-if="facility.image" class="w-full h-24 md:h-40 overflow-hidden">
                  <img
                    :src="facility.image"
                    :alt="getTranslatedName(facility.name)"
                    class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                  />
                </div>
              </a>

              <!-- Card Body -->
              <div class="p-2 md:p-6 flex flex-col flex-1">
                <!-- Facility Header (clickable) -->
                <a :href="route('partners.show', facility.slug)" class="group block mb-0 md:mb-4" @click.prevent="navigateWithScroll(route('partners.show', facility.slug))">
                  <div class="flex items-center gap-2 md:gap-3 mb-1 md:mb-2">
                    <img
                      v-if="facility.logo"
                      :src="facility.logo"
                      :alt="getTranslatedName(facility.name)"
                      class="w-8 h-8 md:w-10 md:h-10 object-contain rounded-lg border border-gray-100 shrink-0"
                    />
                    <h3 class="text-base md:text-xl font-bold text-[#1E3943] group-hover:text-[#B89B6A] transition-colors">
                      {{ getTranslatedName(facility.name) }}
                    </h3>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <span v-if="facility.facility_type" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#1E3943]/10 text-[#1E3943]">
                      {{ getTranslatedName(facility.facility_type.name) }}
                    </span>
                  </div>
                </a>

                <!-- Branches -->
                <div v-if="facility.branches && facility.branches.length > 0" class="space-y-3 flex-1">
                  <h4 class="text-sm font-semibold text-gray-700 hidden md:block">
                    {{ tp('branches') }}
                  </h4>

                  <!-- First branch always visible -->
                  <div :class="locale === 'ar' ? 'border-r-4 border-[#B89B6A] pr-2 md:pr-3 py-1 md:py-2 bg-gray-50 rounded-l' : 'border-l-4 border-[#B89B6A] pl-2 md:pl-3 py-1 md:py-2 bg-gray-50 rounded-r'">
                    <p class="font-medium text-[10px] md:text-sm text-[#1E3943]">{{ getTranslatedName(facility.branches[0].name) }}</p>
                    <p v-if="facility.branches[0].address" class="text-[10px] md:text-xs text-gray-600 mt-1">{{ getTranslatedName(facility.branches[0].address) }}</p>
                    <p v-if="facility.branches[0].governorate || facility.branches[0].city" class="text-xs text-[#B89B6A] mt-0.5">
                      <span v-if="facility.branches[0].city">{{ getTranslatedName(facility.branches[0].city.name) }}</span>
                      <span v-if="facility.branches[0].city && facility.branches[0].governorate">، </span>
                      <span v-if="facility.branches[0].governorate">{{ getTranslatedName(facility.branches[0].governorate.name) }}</span>
                    </p>
                    <GuestPhoneLinks
                      :phone="facility.branches[0].phone"
                      :more-label-template="tp('more_phones')"
                      :less-label="tp('show_less')"
                    />
                  </div>

                  <!-- Extra branches (expanded) -->
                  <template v-if="expandedFacilities.has(facility.id)">
                    <div
                      v-for="branch in facility.branches.slice(1)"
                      :key="branch.id"
                      :class="locale === 'ar' ? 'border-r-4 border-[#B89B6A] pr-2 md:pr-3 py-1 md:py-2 bg-gray-50 rounded-l' : 'border-l-4 border-[#B89B6A] pl-2 md:pl-3 py-1 md:py-2 bg-gray-50 rounded-r'"
                    >
                      <p class="font-medium text-[10px] md:text-sm text-[#1E3943]">{{ getTranslatedName(branch.name) }}</p>
                      <p v-if="branch.address" class="text-[10px] md:text-xs text-gray-600 mt-1">{{ getTranslatedName(branch.address) }}</p>
                      <p v-if="branch.governorate || branch.city" class="text-xs text-[#B89B6A] mt-0.5">
                        <span v-if="branch.city">{{ getTranslatedName(branch.city.name) }}</span>
                        <span v-if="branch.city && branch.governorate">، </span>
                        <span v-if="branch.governorate">{{ getTranslatedName(branch.governorate.name) }}</span>
                      </p>
                      <GuestPhoneLinks
                        :phone="branch.phone"
                        :more-label-template="tp('more_phones')"
                        :less-label="tp('show_less')"
                      />
                    </div>
                  </template>

                  <!-- Read More / Show Less -->
                  <button
                    v-if="facility.branches.length > 1"
                    type="button"
                    @click="toggleExpanded(facility.id)"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#B89B6A] hover:text-[#8B7355] transition-colors mt-1"
                  >
                    <template v-if="!expandedFacilities.has(facility.id)">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                      {{ tp('read_more') }} ({{ facility.branches.length - 1 }} {{ tp('more') }})
                    </template>
                    <template v-else>
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                      {{ tp('show_less') }}
                    </template>
                  </button>
                </div>

                <div v-else class="text-sm text-gray-500 italic flex-1">
                  {{ tp('no_branches') }}
                </div>

                <!-- View Details Button -->
                <div class="mt-4 pt-4 border-t border-gray-100 hidden md:block">
                  <a
                    :href="route('partners.show', facility.slug)"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1E3943] text-white text-sm font-semibold hover:bg-[#B89B6A] transition-colors"
                    @click.prevent="navigateWithScroll(route('partners.show', facility.slug))"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    {{ tp('view_details') }}
                  </a>
                </div>
              </div><!-- end card body -->
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="facilities.meta && facilities.meta.links && facilities.meta.links.length > 0" class="flex justify-center mt-8">
            <Pagination :links="facilities.meta.links" />
          </div>
        </div>

        <!-- No Facilities Message -->
        <div v-else class="text-center py-20" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h3 class="text-lg md:text-2xl font-semibold text-gray-700 mb-2">
            {{ $page.props.translations?.partners?.facility?.no_facilities || 'No Facilities Yet' }}
          </h3>
          <p class="text-sm md:text-base text-gray-500">
            {{ $page.props.translations?.partners?.facility?.no_facilities_description || 'We\'re actively building partnerships with leading healthcare providers' }}
          </p>
        </div>
      </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-12 px-6 bg-white">
      <div class="container mx-auto max-w-6xl">
        <div class="text-center mb-10" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="text-2xl md:text-4xl font-bold mb-4 text-[#1E3943]">
            {{ $page.props.translations?.partners?.benefits?.title || 'Partnership Benefits' }}
          </h2>
          <p class="text-base md:text-xl text-gray-600">
            {{ $page.props.translations?.partners?.benefits?.description || 'What our partnerships mean for you' }}
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <div class="text-center p-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#1E3943] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.partners?.benefits?.wide_coverage?.title || 'Wide Coverage' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.partners?.benefits?.wide_coverage?.description || 'Access to an extensive network of healthcare facilities and services' }}
            </p>
          </div>

          <div class="text-center p-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#B89B6A] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.partners?.benefits?.best_rates?.title || 'Best Rates' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.partners?.benefits?.best_rates?.description || 'Negotiated rates ensuring affordable healthcare for all members' }}
            </p>
          </div>

          <div class="text-center p-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <div class="w-20 h-20 bg-[#1E3943] rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
              </svg>
            </div>
            <h3 class="text-lg md:text-2xl font-bold mb-3 text-[#1E3943]">
              {{ $page.props.translations?.partners?.benefits?.quality_assured?.title || 'Quality Assured' }}
            </h3>
            <p class="text-sm md:text-base text-gray-600">
              {{ $page.props.translations?.partners?.benefits?.quality_assured?.description || 'All partners meet our rigorous standards for quality healthcare' }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 px-6 bg-gradient-to-br from-[#1E3943] to-[#13292F]">
      <div class="container mx-auto max-w-4xl text-center" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <h2 class="text-2xl md:text-4xl font-bold mb-6 text-white">
          {{ $page.props.translations?.partners?.cta?.title || 'Become a Partner' }}
        </h2>
        <p class="text-base md:text-xl text-white/80 mb-8">
          {{ $page.props.translations?.partners?.cta?.description || 'Interested in partnering with ASH Health Care? Let\'s discuss how we can work together' }}
        </p>
        <a 
          href="/contact-us"
          class="inline-block bg-white text-[#1E3943] px-8 py-4 rounded-full font-semibold hover:bg-[#B89B6A] hover:text-white transition-all shadow-lg hover:shadow-xl"
        >
          {{ $page.props.translations?.partners?.cta?.button || 'Contact Us' }}
        </a>
      </div>
    </section>

    <!-- Footer -->
    <GuestFooter />
  </div>
</template>

<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { onClickOutside } from '@vueuse/core';
import Fuse from 'fuse.js';
import GuestNavigation from './_components/GuestNavigation.vue';
import NewsTicker from './_components/NewsTicker.vue';
import GuestFooter from './_components/GuestFooter.vue';
import FloatingPartners from './_components/FloatingPartners.vue';
import FloatingContact from './_components/FloatingContact.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import OffersCarousel from './_components/OffersCarousel.vue';
import GuestSearchableSelect from './_components/GuestSearchableSelect.vue';
import GuestPhoneLinks from './_components/GuestPhoneLinks.vue';

const props = defineProps({
  facilities: {
    type: Object,
    default: () => ({
      data: [],
      meta: {}
    })
  },
  filters: {
    type: Object,
    default: () => ({
      facility_type_id: '',
      governorate_id: ''
    })
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  offers: {
    type: Array,
    default: () => []
  },
  contracts: {
    type: Array,
    default: () => []
  },
  facilityNames: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
const locale = page.props.locale || 'ar';
const dir = locale === 'ar' ? 'rtl' : 'ltr';

// Shorthand for partners.facility translations
const tpDefaults = {
  more_phones: '+:count more',
  show_less: 'Show less',
};
const tp = (key) => page.props.translations?.partners?.facility?.[key] ?? tpDefaults[key] ?? key;

// Track which facility cards are expanded
const expandedFacilities = ref(new Set());
const toggleExpanded = (id) => {
  const next = new Set(expandedFacilities.value);
  next.has(id) ? next.delete(id) : next.add(id);
  expandedFacilities.value = next;
};

// Searchable options
const governorateOptions = computed(() => {
  return props.governorates.map(g => ({
    value: g.id,
    label: getTranslatedName(g.name)
  }));
});

const facilityTypeOptions = computed(() => {
  return props.facilityTypes.map(f => ({
    value: f.id,
    label: getTranslatedName(f.name)
  }));
});

// Partners data
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

// SEO Setup
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
  return `${appUrl.value}/partners`;
});
const baseUrl = computed(() => appUrl.value);

// SEO Data
const pageTitle = computed(() => {
  return locale === 'ar' 
    ? 'شركاؤنا - ASH Health Care | كارت خصومات طبي'
    : 'Our Partners - ASH Health Care | Medical Discount Card';
});

const metaDescription = computed(() => {
  return locale === 'ar'
    ? 'تعرف على شركاء ASH Health Care. نتعاون مع أفضل مقدمي الرعاية الصحية والمؤسسات الطبية وشركات التأمين لضمان تغطية شاملة مع خصومات تصل إلى 80%.'
    : 'Meet ASH Health Care partners. We collaborate with leading healthcare providers, medical institutions, and insurance companies to ensure comprehensive coverage with discounts up to 80%.';
});

const metaKeywords = computed(() => {
  return locale === 'ar'
    ? 'شركاء ASH Health Care، مقدمي الرعاية الصحية، منشآت طبية، تأمين طبي، كارت خصومات طبي، شبكة طبية'
    : 'ASH Health Care partners, healthcare providers, medical facilities, health insurance, medical discount card, medical network';
});

const ogImage = computed(() => {
  return `${baseUrl.value}/images/logo/ash-health-care.png`;
});

// Meta Tags
const metaTags = computed(() => {
  const tags = [
    { name: 'description', content: metaDescription.value },
    { name: 'keywords', content: metaKeywords.value },
    { name: 'author', content: 'ASH Health Care' },
    { name: 'robots', content: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' },
    { name: 'googlebot', content: 'index, follow' },
    { name: 'language', content: locale === 'ar' ? 'Arabic' : 'English' },
    
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: currentUrl.value },
    { property: 'og:title', content: pageTitle.value },
    { property: 'og:description', content: metaDescription.value },
    { property: 'og:image', content: ogImage.value },
    { property: 'og:image:width', content: '1200' },
    { property: 'og:image:height', content: '630' },
    { property: 'og:image:alt', content: 'ASH Health Care - Our Partners' },
    { property: 'og:site_name', content: 'ASH Health Care' },
    { property: 'og:locale', content: locale === 'ar' ? 'ar_EG' : 'en_US' },
    { property: 'og:locale:alternate', content: locale === 'ar' ? 'en_US' : 'ar_EG' },
    
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:url', content: currentUrl.value },
    { name: 'twitter:title', content: pageTitle.value },
    { name: 'twitter:description', content: metaDescription.value },
    { name: 'twitter:image', content: ogImage.value },
    { name: 'twitter:image:alt', content: 'ASH Health Care - Our Partners' },
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
    { rel: 'alternate', hreflang: 'en', href: `${baseUrl.value}/partners` },
    { rel: 'alternate', hreflang: 'ar', href: `${baseUrl.value}/partners` },
    { rel: 'alternate', hreflang: 'x-default', href: `${baseUrl.value}/partners` },
  ];
  
  return links;
});

// Structured Data (JSON-LD) - Inject into head
const injectStructuredData = () => {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  const partnersPageData = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    'name': pageTitle.value,
    'description': metaDescription.value,
    'url': currentUrl.value,
    'mainEntity': {
      '@type': 'ItemList',
      'itemListElement': props.facilities?.data?.map((facility, index) => ({
        '@type': 'ListItem',
        'position': index + 1,
        'item': {
          '@type': 'MedicalBusiness',
          'name': getTranslatedName(facility.name),
          'description': locale === 'ar' ? 'شريك ASH Health Care' : 'ASH Health Care Partner',
          'url': `${baseUrl.value}/partners?facility=${facility.id}`
        }
      })) || []
    },
    'about': {
      '@type': 'Organization',
      'name': 'ASH Health Care',
      'description': locale === 'ar' ? 'كارت خصومات طبي شامل' : 'Comprehensive Medical Discount Card',
      'url': baseUrl.value
    }
  };

  const structuredData = JSON.stringify(partnersPageData);
  
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

const selectedFilters = ref({
  search: props.filters.search || '',
  facility_type_id: props.filters.facility_type_id || '',
  governorate_id: props.filters.governorate_id || ''
});

const hasActiveFilters = computed(() => {
  return !!(selectedFilters.value.search || selectedFilters.value.facility_type_id || selectedFilters.value.governorate_id);
});

// ── Autocomplete suggestions ──────────────────────────────────────────────────
const searchContainer  = ref(null);
const showSuggestions  = ref(false);
const highlightedIndex = ref(-1);

const fuse = computed(() => new Fuse(props.facilityNames, {
  keys: ['name'],
  threshold: 0.45,
  minMatchCharLength: 1,
  includeScore: true,
}));

const suggestions = computed(() => {
  const q = selectedFilters.value.search?.trim();
  if (!q) return [];
  return fuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(suggestions, () => { highlightedIndex.value = -1; });

const selectSuggestion = (item) => {
  selectedFilters.value.search = item.name;
  showSuggestions.value = false;
  highlightedIndex.value = -1;
  applyFilters();
};

const moveSuggestion = (dir) => {
  if (!suggestions.value.length) return;
  highlightedIndex.value = Math.max(-1, Math.min(suggestions.value.length - 1, highlightedIndex.value + dir));
};

const confirmHighlighted = () => {
  const item = suggestions.value[highlightedIndex.value];
  if (item) selectSuggestion(item);
};

onClickOutside(searchContainer, () => { showSuggestions.value = false; });

const navigateWithScroll = (href) => {
  router.visit(href, {
    onSuccess: () => {
      document.body.scrollTo({ top: 0, behavior: 'smooth' });
    },
  });
};

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

let searchTimeout = null;

const handleSearch = (event) => {
  selectedFilters.value.search = event.target.value;
  
  // Debounce search to avoid too many requests
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 300);
};

const handleGovernorateChange = () => {
  // Reset facility type when governorate changes
  selectedFilters.value.facility_type_id = '';
  applyFilters();
};

const applyFilters = () => {
  const params = {};
  if (selectedFilters.value.search && selectedFilters.value.search.trim()) {
    params.search = selectedFilters.value.search.trim();
  }
  if (selectedFilters.value.governorate_id) {
    params.governorate_id = selectedFilters.value.governorate_id;
  }
  if (selectedFilters.value.facility_type_id) {
    params.facility_type_id = selectedFilters.value.facility_type_id;
  }
  
  router.get(route('partners'), params, {
    preserveState: true,
    preserveScroll: false,
    replace: true
  });
};

const clearFilters = () => {
  selectedFilters.value = {
    search: '',
    facility_type_id: '',
    governorate_id: ''
  };
  router.get(route('partners'), {}, {
    preserveState: true,
    preserveScroll: false,
    replace: true
  });
};

// Watch for filter changes from props (when navigating back/forward)
watch(() => props.filters, (newFilters) => {
  if (newFilters) {
    selectedFilters.value = {
      search: newFilters.search || '',
      facility_type_id: newFilters.facility_type_id || '',
      governorate_id: newFilters.governorate_id || ''
    };
    
    // If facility type is selected but not in the filtered list, reset it
    if (selectedFilters.value.facility_type_id && props.facilityTypes.length > 0) {
      const facilityTypeExists = props.facilityTypes.some(
        type => type.id == selectedFilters.value.facility_type_id
      );
      if (!facilityTypeExists) {
        selectedFilters.value.facility_type_id = '';
      }
    }
  }
}, { deep: true, immediate: true });

onMounted(() => {
  injectStructuredData();
});

// Re-inject structured data when facilities change
watch(() => props.facilities, () => {
  injectStructuredData();
}, { deep: true });
</script>

