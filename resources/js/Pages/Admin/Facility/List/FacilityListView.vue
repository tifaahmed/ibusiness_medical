<template>
  <FacilityLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                  <path d="M9 22v-4h6v4"></path>
                  <path d="M8 6h.01"></path>
                  <path d="M16 6h.01"></path>
                  <path d="M12 6h.01"></path>
                  <path d="M12 10h.01"></path>
                  <path d="M12 14h.01"></path>
                  <path d="M16 10h.01"></path>
                  <path d="M16 14h.01"></path>
                  <path d="M8 10h.01"></path>
                  <path d="M8 14h.01"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.facility?.management || 'Facilities Management' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
              <a
                :href="exportUrl"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
                title="Export facilities to XLSX"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span class="hidden sm:inline">Export</span>
              </a>

              <Link
                :href="route('admin.facility.migration.page')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
                title="Move facilities, branches and images to another site"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v16a2 2 0 0 0 2 2h12"/><path d="m16 6-4-4-4 4"/><path d="M12 2v10"/><path d="M20 14v6a2 2 0 0 1-2 2"/></svg>
                <span class="hidden sm:inline">Migration</span>
              </Link>
              <Link
                :href="route('admin.facility.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">{{ t.facility?.add_new || 'Add New Facility' }}</span>
                <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
              </Link>
            </div>
          </div>
          
          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <FacilityListFilterContent :initial-filters="filters" :facility-types="facilityTypes" :sales-options="salesOptions" :governorates="governorates" :cities="cities" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>
      
      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <FacilityListTable :facilities="facilities" @delete="handleDelete" />
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import FacilityLayout from "../FacilityLayout.vue";
import FacilityListFilterContent from "./FacilityListFilterContent.vue";
import FacilityListTable from "./FacilityListTable.vue";
import { useFacilityStore } from "../Stores/FacilityStore";
import { Link, router, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed, onMounted, onUnmounted, watch } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facilities: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      facility_type_id: '',
      sales_id: '',
      governorate_id: '',
      city_id: '',
    })
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  salesOptions: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  }
});

const facilityStore = useFacilityStore();
const { facilities: storeFacilities } = storeToRefs(facilityStore);

facilityStore.setFacilities(props.facilities);

const facilities = computed(() => props.facilities);

const filters = ref(props.filters || { search: '', facility_type_id: '', sales_id: '' });

const handleDelete = (facilitySlug) => {
  facilityStore.confirmDelete(facilitySlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const exportUrl = computed(() => {
  const params = new URLSearchParams();
  const f = filters.value || {};
  if (f.search) params.set('search', f.search);
  if (f.facility_type_id) params.set('facility_type_id', f.facility_type_id);
  if (f.sales_id) params.set('sales_id', f.sales_id);
  if (f.governorate_id) params.set('governorate_id', f.governorate_id);
  if (f.city_id) params.set('city_id', f.city_id);
  params.set('include_branches', '1');
  const qs = params.toString();
  return route('admin.facility.export') + (qs ? '?' + qs : '');
});

// Listen for Inertia finish event to reset scroll position
let finishCleanup = null;

onMounted(() => {
  console.log('[FacilityListView] Component mounted');
  
  // Check for horizontal overflow
  setTimeout(() => {
    console.log('[FacilityListView] ===== HORIZONTAL SCROLL CHECK =====');
    
    // Check main element and its styles
    const mainElement = document.querySelector('main');
    const parentDiv = mainElement?.parentElement;
    
    if (mainElement) {
      const mainStyles = window.getComputedStyle(mainElement);
      console.log('[FacilityListView] Main element:', {
        scrollWidth: mainElement.scrollWidth,
        clientWidth: mainElement.clientWidth,
        offsetWidth: mainElement.offsetWidth,
        scrollHeight: mainElement.scrollHeight,
        clientHeight: mainElement.clientHeight,
        scrollTop: mainElement.scrollTop,
        scrollLeft: mainElement.scrollLeft,
        overflowY: mainStyles.overflowY,
        overflowX: mainStyles.overflowX,
        width: mainStyles.width,
        maxWidth: mainStyles.maxWidth,
        hasHorizontalScroll: mainElement.scrollWidth > mainElement.clientWidth,
        hasVerticalScroll: mainElement.scrollHeight > mainElement.clientHeight,
      });
      
      if (parentDiv) {
        const parentStyles = window.getComputedStyle(parentDiv);
        console.log('[FacilityListView] Parent div:', {
          scrollWidth: parentDiv.scrollWidth,
          clientWidth: parentDiv.clientWidth,
          width: parentStyles.width,
          maxWidth: parentStyles.maxWidth,
          overflow: parentStyles.overflow,
          hasHorizontalScroll: parentDiv.scrollWidth > parentDiv.clientWidth,
        });
      }
    }
    
    // Check the viewport
    console.log('[FacilityListView] Viewport:', {
      windowInnerWidth: window.innerWidth,
      windowOuterWidth: window.outerWidth,
      documentWidth: document.documentElement.scrollWidth,
      documentClientWidth: document.documentElement.clientWidth,
      documentOffsetWidth: document.documentElement.offsetWidth,
      bodyScrollWidth: document.body.scrollWidth,
      bodyClientWidth: document.body.clientWidth,
      bodyOffsetWidth: document.body.offsetWidth,
      hasHorizontalScroll: document.documentElement.scrollWidth > window.innerWidth,
      htmlOverflowX: window.getComputedStyle(document.documentElement).overflowX,
      bodyOverflowX: window.getComputedStyle(document.body).overflowX,
    });
    
    // Check header card
    const headerCard = document.querySelector('[data-slot="card"]');
    if (headerCard) {
      const cardStyles = window.getComputedStyle(headerCard);
      console.log('[FacilityListView] Header Card:', {
        scrollWidth: headerCard.scrollWidth,
        clientWidth: headerCard.clientWidth,
        offsetWidth: headerCard.offsetWidth,
        width: cardStyles.width,
        maxWidth: cardStyles.maxWidth,
        overflow: cardStyles.overflow,
        overflowX: cardStyles.overflowX,
        paddingLeft: cardStyles.paddingLeft,
        paddingRight: cardStyles.paddingRight,
        hasHorizontalScroll: headerCard.scrollWidth > headerCard.clientWidth,
      });
      
      // Check card header
      const cardHeader = headerCard.querySelector('[data-slot="card-header"]');
      if (cardHeader) {
        const headerStyles = window.getComputedStyle(cardHeader);
        console.log('[FacilityListView] Card Header:', {
          scrollWidth: cardHeader.scrollWidth,
          clientWidth: cardHeader.clientWidth,
          width: headerStyles.width,
          maxWidth: headerStyles.maxWidth,
          overflow: headerStyles.overflow,
          display: headerStyles.display,
          hasHorizontalScroll: cardHeader.scrollWidth > cardHeader.clientWidth,
        });
      }
      
      // Check card content (filter section)
      const cardContent = headerCard.querySelector('[data-slot="card-content"]');
      if (cardContent) {
        const contentStyles = window.getComputedStyle(cardContent);
        console.log('[FacilityListView] Card Content (Filters):', {
          scrollWidth: cardContent.scrollWidth,
          clientWidth: cardContent.clientWidth,
          width: contentStyles.width,
          maxWidth: contentStyles.maxWidth,
          overflow: contentStyles.overflow,
          paddingLeft: contentStyles.paddingLeft,
          paddingRight: contentStyles.paddingRight,
          hasHorizontalScroll: cardContent.scrollWidth > cardContent.clientWidth,
        });
      }
      
      // Check search input
      const searchInput = document.querySelector('#search');
      if (searchInput) {
        const inputStyles = window.getComputedStyle(searchInput);
        console.log('[FacilityListView] Search Input:', {
          scrollWidth: searchInput.scrollWidth,
          clientWidth: searchInput.clientWidth,
          offsetWidth: searchInput.offsetWidth,
          width: inputStyles.width,
          maxWidth: inputStyles.maxWidth,
          paddingLeft: inputStyles.paddingLeft,
          paddingRight: inputStyles.paddingRight,
          boxSizing: inputStyles.boxSizing,
          hasHorizontalScroll: searchInput.scrollWidth > searchInput.clientWidth,
        });
      }
    }
    
    // Find all elements with scroll
    const allElements = document.querySelectorAll('*');
    const elementsWithScroll = [];
    allElements.forEach(el => {
      const styles = window.getComputedStyle(el);
      if (el.scrollWidth > el.clientWidth && el.scrollWidth > window.innerWidth) {
        elementsWithScroll.push({
          element: el,
          tagName: el.tagName,
          className: el.className,
          scrollWidth: el.scrollWidth,
          clientWidth: el.clientWidth,
          offsetWidth: el.offsetWidth,
          width: styles.width,
          maxWidth: styles.maxWidth,
          overflow: styles.overflow,
        });
      }
    });
    
    if (elementsWithScroll.length > 0) {
      console.warn('[FacilityListView] Elements causing horizontal scroll:', elementsWithScroll.slice(0, 10)); // Limit to first 10
      elementsWithScroll.slice(0, 5).forEach((item, index) => {
        console.log(`[FacilityListView] Element ${index + 1}:`, {
          tag: item.tagName,
          classes: item.className.substring(0, 100),
          scrollWidth: item.scrollWidth,
          clientWidth: item.clientWidth,
          width: item.width,
          maxWidth: item.maxWidth,
          element: item.element,
        });
      });
    } else {
      console.log('[FacilityListView] No elements found causing horizontal scroll');
    }
    
    // Check specific problematic elements
    const titleGolden = document.querySelector('.title-golden');
    if (titleGolden) {
      const titleStyles = window.getComputedStyle(titleGolden);
      console.log('[FacilityListView] Title Golden:', {
        scrollWidth: titleGolden.scrollWidth,
        clientWidth: titleGolden.clientWidth,
        offsetWidth: titleGolden.offsetWidth,
        width: titleStyles.width,
        minWidth: titleStyles.minWidth,
        maxWidth: titleStyles.maxWidth,
        overflow: titleStyles.overflow,
        flexShrink: titleStyles.flexShrink,
        flexGrow: titleStyles.flexGrow,
        hasHorizontalScroll: titleGolden.scrollWidth > titleGolden.clientWidth,
      });
    }
    
    const addButton = document.querySelector('[data-slot="button"]');
    if (addButton) {
      const buttonStyles = window.getComputedStyle(addButton);
      console.log('[FacilityListView] Add Button:', {
        scrollWidth: addButton.scrollWidth,
        clientWidth: addButton.clientWidth,
        offsetWidth: addButton.offsetWidth,
        width: buttonStyles.width,
        minWidth: buttonStyles.minWidth,
        maxWidth: buttonStyles.maxWidth,
        paddingLeft: buttonStyles.paddingLeft,
        paddingRight: buttonStyles.paddingRight,
        hasHorizontalScroll: addButton.scrollWidth > addButton.clientWidth,
      });
    }
    
    // Check card-title container
    const cardTitle = document.querySelector('[data-slot="card-title"]');
    if (cardTitle) {
      const titleContainerStyles = window.getComputedStyle(cardTitle);
      console.log('[FacilityListView] Card Title Container:', {
        scrollWidth: cardTitle.scrollWidth,
        clientWidth: cardTitle.clientWidth,
        offsetWidth: cardTitle.offsetWidth,
        width: titleContainerStyles.width,
        minWidth: titleContainerStyles.minWidth,
        maxWidth: titleContainerStyles.maxWidth,
        display: titleContainerStyles.display,
        flexDirection: titleContainerStyles.flexDirection,
        gap: titleContainerStyles.gap,
        hasHorizontalScroll: cardTitle.scrollWidth > cardTitle.clientWidth,
      });
    }
    
    console.log('[FacilityListView] ===== END HORIZONTAL SCROLL CHECK =====');
  }, 500);
  
  // Try to set up router event listener
  // In Inertia v3, router.on() may return a cleanup function
  try {
    if (router && typeof router.on === 'function') {
      console.log('[FacilityListView] Setting up router finish listener');
      finishCleanup = router.on('finish', () => {
        console.log('[FacilityListView] Router finish event triggered');
        
        // Reset scroll to top after navigation (e.g., pagination)
        // Use requestAnimationFrame to ensure DOM is ready
        requestAnimationFrame(() => {
          const mainElement = document.querySelector('main');
          if (mainElement) {
            console.log('[FacilityListView] Resetting scroll - before:', {
              scrollTop: mainElement.scrollTop,
              scrollLeft: mainElement.scrollLeft,
              scrollHeight: mainElement.scrollHeight,
              scrollWidth: mainElement.scrollWidth,
              clientHeight: mainElement.clientHeight,
              clientWidth: mainElement.clientWidth,
            });
            
            mainElement.scrollTop = 0;
            mainElement.scrollLeft = 0;
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
            
            // Check for horizontal scroll after navigation
            setTimeout(() => {
              const headerCard = document.querySelector('[data-slot="card"]');
              if (headerCard && headerCard.scrollWidth > headerCard.clientWidth) {
                console.warn('[FacilityListView] HORIZONTAL SCROLL DETECTED after navigation!', {
                  scrollWidth: headerCard.scrollWidth,
                  clientWidth: headerCard.clientWidth,
                  difference: headerCard.scrollWidth - headerCard.clientWidth,
                });
              }
            }, 100);
            
            console.log('[FacilityListView] Resetting scroll - after:', {
              scrollTop: mainElement.scrollTop,
              scrollLeft: mainElement.scrollLeft,
              overflowY: window.getComputedStyle(mainElement).overflowY,
              overflowX: window.getComputedStyle(mainElement).overflowX,
            });
          } else {
            console.warn('[FacilityListView] Main element not found in finish handler');
          }
        });
      });
      console.log('[FacilityListView] Router listener set up successfully');
    } else {
      console.warn('[FacilityListView] Router or router.on not available:', { router, hasOn: router && typeof router.on });
    }
  } catch (error) {
    console.error('[FacilityListView] Error setting up router finish listener:', error);
  }
  
  // Also check after a delay
  setTimeout(() => {
    const mainElement = document.querySelector('main');
    if (mainElement) {
      console.log('[FacilityListView] Delayed check - Main element styles:', {
        scrollHeight: mainElement.scrollHeight,
        clientHeight: mainElement.clientHeight,
        overflowY: window.getComputedStyle(mainElement).overflowY,
        height: window.getComputedStyle(mainElement).height,
        maxHeight: window.getComputedStyle(mainElement).maxHeight,
      });
    }
  }, 500);
});

onUnmounted(() => {
  console.log('[FacilityListView] Component unmounting');
  
  // Cleanup router event listener if it was set up
  try {
    if (finishCleanup && typeof finishCleanup === 'function') {
      console.log('[FacilityListView] Cleaning up router listener');
      finishCleanup();
    } else {
      console.warn('[FacilityListView] No cleanup function available');
    }
  } catch (error) {
    console.error('[FacilityListView] Error cleaning up router listener:', error);
  }
});

// Watch for URL changes to debug
watch(() => props.facilities, (newFacilities, oldFacilities) => {
  console.log('[FacilityListView] Facilities changed:', {
    oldPage: oldFacilities?.meta?.current_page,
    newPage: newFacilities?.meta?.current_page,
    oldCount: oldFacilities?.data?.length,
    newCount: newFacilities?.data?.length,
  });
  
  // Check scrolling capability after data change
  setTimeout(() => {
    const mainElement = document.querySelector('main');
    if (mainElement) {
      console.log('[FacilityListView] After facilities change - Main element:', {
        scrollHeight: mainElement.scrollHeight,
        clientHeight: mainElement.clientHeight,
        scrollTop: mainElement.scrollTop,
        overflowY: window.getComputedStyle(mainElement).overflowY,
        canScroll: mainElement.scrollHeight > mainElement.clientHeight,
      });
    }
  }, 100);
}, { deep: true });
</script>

