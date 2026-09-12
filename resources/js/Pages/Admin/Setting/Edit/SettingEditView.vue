<template>
  <SettingLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <form class="space-y-3" @submit.prevent="handleSubmit">
          <SettingForm :setting="setting" :value-types="valueTypes" :max-image-size="maxImageSize" />

          <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
            <div class="flex flex-col sm:flex-row p-4">
              <div class="flex-1"></div>
              <div class="flex gap-3 justify-end">
                <Link
                  :href="route('admin.setting.list')"
                  data-slot="button"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 order-2 sm:order-1"
                  type="button"
                >
                  {{ t.common?.cancel || 'Cancel' }}
                </Link>
                <div class="relative inline-flex order-1 sm:order-2">
                  <button
                    type="submit"
                    :disabled="settingStore.form.processing"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px] btn-golden"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ t.setting?.update || 'Update Setting' }}
                  </button>
                  <ErrorTrackButton :errors="settingStore.validationErrors || {}" :debug-log="settingStore.debugLog" />
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </SettingLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import SettingLayout from "../SettingLayout.vue";
import { useSettingStore } from "../Stores/SettingStore";
import ErrorTrackButton from "@/Components/ui/ErrorTrackButton.vue";
import SettingForm from "../Form/SettingForm.vue";
import { onMounted, computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  setting: {
    type: Object,
    required: true
  },
  valueTypes: {
    type: Array,
    default: () => []
  },
  maxImageSize: {
    type: Number,
    default: 5
  }
});

const settingStore = useSettingStore();

onMounted(() => {
  settingStore.setSetting(props.setting);
});

const handleSubmit = () => {
  settingStore.updateSetting();
};
</script>

<style lang="scss" scoped></style>
