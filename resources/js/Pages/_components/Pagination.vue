<template>
  <nav v-if="links && links.length > 0" role="navigation" aria-label="pagination" data-slot="pagination" class="mx-auto flex w-full justify-end">
    <ul data-slot="pagination-content" class="flex flex-row items-center gap-0.5 sm:gap-1">
      <template v-for="(link, index) in visibleLinks" :key="index">
        <li v-if="link.url" data-slot="pagination-item">
          <Link
            :href="link.url"
            :class="[
              'inline-flex items-center justify-center whitespace-nowrap text-xs sm:text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-3 sm:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive shadow-xs rounded-md gap-1 sm:gap-1.5 has-[>svg]:px-1.5 sm:has-[>svg]:px-2.5 h-7 sm:h-9',
              link.active
                ? 'cursor-default pointer-events-none font-semibold bg-golden-yellow text-golden-yellow-foreground hover:bg-golden-yellow border-golden-yellow min-w-[28px] sm:min-w-[36px] px-2 sm:px-3'
                : isPreviousOrNext(link.label)
                  ? 'cursor-pointer font-medium border bg-background hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 w-7 sm:w-9 p-0'
                  : 'cursor-pointer font-medium border bg-background hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 min-w-[28px] sm:min-w-[36px] px-2 sm:px-3'
            ]"
            :preserveScroll="false"
            @click="handlePaginationClick"
            :disabled="link.active || !link.url"
          >
            <template v-if="isPreviousOrNext(link.label) && !link.active">
              <!-- Previous: chevron-left in LTR, chevron-right in RTL -->
              <svg v-if="isPrevious(link.label) && !isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left h-4 w-4">
                <path d="m15 18-6-6 6-6"></path>
              </svg>
              <svg v-if="isPrevious(link.label) && isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right h-4 w-4">
                <path d="m9 18 6-6-6-6"></path>
              </svg>
              <span v-if="isPrevious(link.label)" class="sr-only">Previous page</span>
              <!-- Next: chevron-right in LTR, chevron-left in RTL -->
              <svg v-if="isNext(link.label) && !isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right h-4 w-4">
                <path d="m9 18 6-6-6-6"></path>
              </svg>
              <svg v-if="isNext(link.label) && isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left h-4 w-4">
                <path d="m15 18-6-6 6-6"></path>
              </svg>
              <span v-if="isNext(link.label)" class="sr-only">Next page</span>
            </template>
            <span v-else v-html="link.label"></span>
          </Link>
        </li>
        <li v-else-if="link.isEllipsis" data-slot="pagination-item">
          <span class="inline-flex items-center justify-center w-7 sm:w-9 h-7 sm:h-9 text-xs sm:text-sm text-muted-foreground">...</span>
        </li>
        <li v-else data-slot="pagination-item">
          <button
            data-slot="button"
            :class="[
              'inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-3 sm:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1 sm:gap-1.5 has-[>svg]:px-1.5 sm:has-[>svg]:px-2.5 h-7 sm:h-9 w-7 sm:w-9 p-0',
              'disabled cursor-not-allowed opacity-50'
            ]"
            disabled
          >
            <svg v-if="isPrevious(link.label) && !isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left h-4 w-4">
              <path d="m15 18-6-6 6-6"></path>
            </svg>
            <svg v-if="isPrevious(link.label) && isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right h-4 w-4">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
            <span v-if="isPrevious(link.label)" class="sr-only">Previous page</span>
            <svg v-if="isNext(link.label) && !isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right h-4 w-4">
              <path d="m9 18 6-6-6-6"></path>
            </svg>
            <svg v-if="isNext(link.label) && isRtl" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left h-4 w-4">
              <path d="m15 18-6-6 6-6"></path>
            </svg>
            <span v-if="isNext(link.label)" class="sr-only">Next page</span>
            <span v-if="!isPreviousOrNext(link.label)" v-html="link.label"></span>
          </button>
        </li>
      </template>
    </ul>
  </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const page = usePage();
const isRtl = computed(() => page.props.locale === 'ar');

const props = defineProps({
  links: {
    type: Array,
    required: true,
    default: () => [],
  },
  maxVisible: {
    type: Number,
    default: 5, // Default max visible page numbers (excluding prev/next)
  },
  mobileMaxVisible: {
    type: Number,
    default: 3, // Max visible on mobile
  },
});

// Track screen size
const isMobile = ref(false);

const checkMobile = () => {
  isMobile.value = window.innerWidth < 640; // sm breakpoint
};

onMounted(() => {
  checkMobile();
  window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
});

// Compute visible links with ellipsis
const visibleLinks = computed(() => {
  if (!props.links || props.links.length === 0) return [];
  
  const maxVisible = isMobile.value ? props.mobileMaxVisible : props.maxVisible;
  
  // Separate prev/next from page numbers
  const prevLink = props.links.find(l => isPrevious(l.label));
  const nextLink = props.links.find(l => isNext(l.label));
  const pageLinks = props.links.filter(l => !isPreviousOrNext(l.label));
  
  // If few enough pages, show all
  if (pageLinks.length <= maxVisible) {
    return props.links;
  }
  
  // Find active page index
  const activeIndex = pageLinks.findIndex(l => l.active);
  const totalPages = pageLinks.length;
  
  // Calculate range around active page
  const sideCount = Math.floor((maxVisible - 1) / 2);
  let startIndex = Math.max(0, activeIndex - sideCount);
  let endIndex = Math.min(totalPages - 1, activeIndex + sideCount);
  
  // Adjust if we're near the start or end
  if (activeIndex - sideCount < 0) {
    endIndex = Math.min(totalPages - 1, maxVisible - 1);
  }
  if (activeIndex + sideCount >= totalPages) {
    startIndex = Math.max(0, totalPages - maxVisible);
  }
  
  const result = [];
  
  // Add prev button
  if (prevLink) result.push(prevLink);
  
  // Add first page if not in range
  if (startIndex > 0) {
    result.push(pageLinks[0]);
    if (startIndex > 1) {
      result.push({ isEllipsis: true, label: '...' });
    }
  }
  
  // Add visible page range
  for (let i = startIndex; i <= endIndex; i++) {
    result.push(pageLinks[i]);
  }
  
  // Add last page if not in range
  if (endIndex < totalPages - 1) {
    if (endIndex < totalPages - 2) {
      result.push({ isEllipsis: true, label: '...' });
    }
    result.push(pageLinks[totalPages - 1]);
  }
  
  // Add next button
  if (nextLink) result.push(nextLink);
  
  return result;
});

const isPreviousOrNext = (label) => {
  if (!label) return false;
  const text = label.toString().toLowerCase().trim();
  const htmlText = text.replace(/<[^>]*>/g, '').toLowerCase().trim();
  return htmlText.includes('previous') || htmlText.includes('next') || 
         text === '«' || text === '»' || 
         text.includes('&laquo;') || text.includes('&raquo;') ||
         htmlText.includes('&laquo;') || htmlText.includes('&raquo;');
};

const isPrevious = (label) => {
  if (!label) return false;
  const text = label.toString().toLowerCase().trim();
  const htmlText = text.replace(/<[^>]*>/g, '').toLowerCase().trim();
  return htmlText.includes('previous') || text === '«' || text.includes('&laquo;') || htmlText.includes('&laquo;');
};

const isNext = (label) => {
  if (!label) return false;
  const text = label.toString().toLowerCase().trim();
  const htmlText = text.replace(/<[^>]*>/g, '').toLowerCase().trim();
  return htmlText.includes('next') || text === '»' || text.includes('&raquo;') || htmlText.includes('&raquo;');
};

const handlePaginationClick = () => {
  // Scroll to top when pagination link is clicked
  const mainElement = document.querySelector('main');
  if (mainElement) {
    mainElement.scrollTop = 0;
  }
  window.scrollTo({ top: 0, behavior: 'instant' });
};
</script>
