<template>
  <div class="membership-card bg-card text-card-foreground border-2 border-border rounded-xl p-4 sm:p-6 shadow-lg w-full max-w-[500px]" :dir="cardLocale === 'ar' ? 'rtl' : 'ltr'">
    <!-- Card Header -->
    <div class="flex items-center justify-between mb-4 sm:mb-6 gap-2">
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <!-- User Avatar -->
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full overflow-hidden border-2 border-primary flex-shrink-0">
          <OptimizedImage
            v-if="membership.user?.avatar_url"
            :src="membership.user.avatar_url"
            :alt="membership.user?.name"
            img-class="w-full h-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="w-full h-full bg-primary/20 flex items-center justify-center text-sm sm:text-base font-bold text-primary"
          >
            {{ getUserInitials(membership.user?.name) }}
          </div>
        </div>
        <div class="min-w-0">
          <h3 class="text-base sm:text-lg font-bold text-card-foreground truncate">{{ t.membership_card || 'Membership Card' }}</h3>
          <p class="text-xs text-muted-foreground">{{ t.official_membership || 'Official Membership' }}</p>
        </div>
      </div>
      <div v-if="membership.is_active" class="px-2 sm:px-3 py-1 bg-emerald-500/25 border border-emerald-500/50 rounded-full flex-shrink-0">
        <span class="text-[10px] sm:text-xs font-semibold text-emerald-800">{{ t.active || 'ACTIVE' }}</span>
      </div>
    </div>

    <!-- Member Info -->
    <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-6">
      <div>
        <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ t.member_name || 'Member Name' }}</label>
        <p class="text-lg sm:text-xl font-bold text-card-foreground mt-1 break-words">{{ membership.user?.name || 'N/A' }}</p>
      </div>

      <div>
        <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ t.membership_number || 'Membership Number' }}</label>
        <p class="text-base sm:text-lg font-mono font-semibold text-card-foreground mt-1 break-all">{{ membership.membership_number }}</p>
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-2 sm:gap-4 mb-4 sm:mb-6 pt-3 sm:pt-4 border-t border-border">
      <div>
        <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ t.registration_date || 'Registration Date' }}</label>
        <p class="text-xs sm:text-sm font-semibold text-card-foreground mt-1">{{ formatDate(membership.registration_date) }}</p>
      </div>
      <div>
        <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ t.expiration_date || 'Expiration Date' }}</label>
        <p class="text-xs sm:text-sm font-semibold text-card-foreground mt-1">{{ formatDate(membership.expiration_date) }}</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-2 pt-3 sm:pt-4 border-t border-border">
      <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-border flex items-center justify-center flex-shrink-0">
          <OptimizedImage
            v-if="membership.user?.avatar_url"
            :src="membership.user.avatar_url"
            :alt="membership.user?.name"
            img-class="w-full h-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center text-xs font-semibold text-card-foreground"
          >
            {{ getUserInitials(membership.user?.name) }}
          </div>
        </div>
        <div class="min-w-0">
          <p class="text-[10px] sm:text-xs text-muted-foreground">{{ t.issued_to || 'Issued to' }}</p>
          <p class="text-xs sm:text-sm font-semibold text-card-foreground truncate">{{ membership.user?.email || 'N/A' }}</p>
        </div>
      </div>
      <div :class="cardLocale === 'ar' ? 'text-left sm:text-right' : 'text-left sm:text-right'">
        <p class="text-[10px] sm:text-xs text-muted-foreground">{{ t.valid_until || 'Valid Until' }}</p>
        <p class="text-xs sm:text-sm font-semibold text-card-foreground">{{ formatDate(membership.expiration_date) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import OptimizedImage from '@/Components/OptimizedImage.vue';

const props = defineProps({
  membership: {
    type: Object,
    required: true,
  },
  translations: {
    type: Object,
    default: () => ({}),
  },
  locale: {
    type: String,
    default: '',
  },
});

const page = usePage();
const cardLocale = computed(() => props.locale || page.props.locale || 'ar');
const t = computed(() => {
  if (Object.keys(props.translations).length > 0) return props.translations;
  return page.props.translations?.home?.membership_page || {};
});

const getUserInitials = (name) => {
  if (!name) return "??";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString(cardLocale.value === 'ar' ? 'ar-EG' : 'en-US', {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  } catch (error) {
    return dateString;
  }
};
</script>

<style scoped>
.membership-card {
  min-width: 280px;
}
</style>
