<template>
  <GovernorateLayout>
    <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.governorate?.edit || 'Edit Governorate'"
        :breadcrumbs="[
          { label: t.common?.governorates || 'Governorates', link: route('admin.governorate.list'), active: false },
          { label: t.governorate?.edit || 'Edit Governorate', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto space-y-2 sm:space-y-3 md:space-y-4">
        <!-- One-click English cleanup for this governorate's name. -->
        <div class="flex flex-wrap items-center justify-end gap-2">
          <p v-if="englishFixMessage" class="mr-auto text-xs text-muted-foreground">{{ englishFixMessage }}</p>
          <button
            type="button"
            :disabled="!englishFixEnabled || englishFixRunning"
            :title="englishFixEnabled ? 'Translate / fix the empty or Arabic English name' : 'Set GEMINI_API_KEY in your .env file to enable this'"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            @click="fixEnglish"
          >
            <svg v-if="englishFixRunning" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
            </svg>
            {{ englishFixRunning ? 'Fixing English…' : 'Fix English with AI' }}
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-2 sm:space-y-3 md:space-y-4">
          <div class="grid grid-cols-1 gap-2 sm:gap-3 md:gap-4">
            <div class="space-y-2 sm:space-y-3 md:space-y-4">
              <GovernorateForm />
              <CitiesCard v-model="cities" />
            </div>
          </div>

          <!-- Sticky Form Actions -->
          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row p-2 sm:p-3 md:p-4 gap-2 sm:gap-3">
              <div class="flex-1"></div>
              <div class="flex gap-2 sm:gap-3 justify-end">
              <Link
                :href="route('admin.governorate.list')"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              >
                {{ t.common?.cancel || 'Cancel' }}
              </Link>
              <div class="relative inline-flex">
                <button
                  type="submit"
                  :disabled="governorateStore.form.processing"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                  </svg>
                  {{ t.governorate?.update || 'Update Governorate' }}
                </button>
                <ErrorTrackButton :errors="governorateStore.validationErrors || {}" :debug-log="governorateStore.debugLog" />
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
    </div>
  </GovernorateLayout>
</template>

<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { watch, computed, ref } from "vue";
import GovernorateLayout from "../GovernorateLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useGovernorateStore } from "../Stores/GovernorateStore";
import { GovernorateForm, CitiesCard } from "../_components/Form";
import ErrorTrackButton from "@/Components/ui/ErrorTrackButton.vue";
import { useNotification } from "@/composables/useNotification";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  governorate: {
    type: Object,
    required: true,
  },
  englishFixEnabled: {
    type: Boolean,
    default: false,
  },
});

const governorateStore = useGovernorateStore();

const englishFixRunning = ref(false);
const englishFixMessage = ref('');

const fixEnglish = async () => {
  if (!props.englishFixEnabled || englishFixRunning.value) return;

  englishFixRunning.value = true;
  englishFixMessage.value = '';
  try {
    const { data } = await axios.post(route('admin.governorate.english.fix', props.governorate.slug));
    const applied = data?.applied?.length || 0;

    if (applied === 0) {
      useNotification().info('No English fix needed.');
    } else {
      useNotification().success(`Fixed the English name. Reloading…`);
      router.reload({ only: ['governorate'] });
    }

    if (data?.errors?.length) {
      englishFixMessage.value = data.errors.join(' ');
    }
  } catch (error) {
    useNotification().error(error?.response?.data?.message || 'Could not fix the English name. Please try again.');
  } finally {
    englishFixRunning.value = false;
  }
};

const cities = computed({
  get: () => governorateStore.form.cities || [],
  set: (value) => { governorateStore.form.cities = value; }
});

// Set governorate data when props are received
// Use watch with immediate to ensure it runs on mount and when props change
watch(() => props.governorate, (newGovernorate) => {
  if (newGovernorate && newGovernorate.id) {
    governorateStore.setGovernorate(newGovernorate);
  }
}, { immediate: true, deep: true });

const handleSubmit = () => {
  governorateStore.updateGovernorate();
};
</script>

<style lang="scss" scoped></style>

