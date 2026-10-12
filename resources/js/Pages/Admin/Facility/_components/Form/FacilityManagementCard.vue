<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div data-slot="card-header" class="py-2 px-6">
      <div class="leading-none font-semibold title-golden flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        {{ t.facility?.management || 'Management' }}
      </div>
    </div>
    <div data-slot="card-content" class="px-6 space-y-3">
      <FormSearchableSelect
        v-model="facilityStore.form.managed_by"
        :label="t.facility?.managed_by || 'Managed by'"
        :options="options"
        :error="facilityStore.validationErrors?.managed_by"
        :placeholder="t.facility?.managed_by_placeholder || 'Select an admin user (optional)'"
      />
      <p class="text-xs text-muted-foreground">
        {{ t.facility?.managed_by_hint || 'The admin user responsible for this facility. Leave empty if nobody is assigned.' }}
      </p>
      <button
        v-if="facilityStore.form.managed_by"
        type="button"
        @click="facilityStore.form.managed_by = ''"
        class="cursor-pointer text-xs font-medium border border-destructive/40 text-destructive hover:bg-destructive/10 h-7 rounded-md px-2.5"
      >
        {{ t.facility?.managed_by_clear || 'Remove assignment' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { FormSearchableSelect } from "@/Components/form";
import { useFacilityStore } from "../../Stores/FacilityStore";

const props = defineProps({
  managedByOptions: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const facilityStore = useFacilityStore();
const options = computed(() => props.managedByOptions);
</script>
