<template>
  <MembershipUsageLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <div class="space-y-3">
          <form class="space-y-3" @submit.prevent="handleSubmit">
            <div class="space-y-3">
              <MembershipUsageForm
                :usage="usage"
                :memberships="memberships"
                :facilities="facilities"
                :facility-branches="facilityBranches"
                :facility-types="facilityTypes"
              />
            </div>

            <!-- Sticky Form Actions -->
            <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
              <div class="flex flex-col sm:flex-row p-4">
                <div class="flex-1"></div>
                <div class="flex gap-3 justify-end">
                  <Link
                    :href="route('admin.membership-usage.list')"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                    type="button"
                  >
                    {{ t.cancel || 'Cancel' }}
                  </Link>
                  <button
                    type="submit"
                    :disabled="usageStore.form.processing"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ isEditMode ? (t.update_button || 'Update Usage') : (t.create_button || 'Create Usage') }}
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </MembershipUsageLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MembershipUsageLayout from "../MembershipUsageLayout.vue";
import { useMembershipUsageStore } from "../Stores/MembershipUsageStore";
import MembershipUsageForm from "./MembershipUsageForm.vue";
import { computed, watch } from "vue";

const props = defineProps({
  usage: { type: Object, default: null },
  memberships: { type: Array, default: () => [] },
  facilities: { type: Array, default: () => [] },
  facilityBranches: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.membership_usage || {});

const usageStore = useMembershipUsageStore();
const isEditMode = computed(() => !!props.usage?.id);

// Initialize synchronously so selects receive correct values before first render
if (props.usage?.id) {
  usageStore.setUsage(props.usage);
} else {
  usageStore.initializeForm();
}

watch(() => props.usage, (newUsage) => {
  if (newUsage?.id) usageStore.setUsage(newUsage);
}, { deep: true });

const handleSubmit = () => {
  if (isEditMode.value) {
    usageStore.updateUsage();
  } else {
    usageStore.submitForm();
  }
};
</script>
