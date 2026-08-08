<template>
  <div class="space-y-3">
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
            <path d="M12 8v4l3 3"></path>
          </svg>
          {{ t.details_title || 'Membership Usage Details' }}
        </div>
      </div>

      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Membership -->
        <div data-slot="form-item" class="grid gap-1">
          <FormSearchableSelect
            v-model="form.membership_id"
            :label="t.membership || 'Membership'"
            :options="membershipOptions"
            :error="usageStore.validationErrors?.membership_id"
            :placeholder="t.membership_placeholder || 'Select membership'"
            required
          />
        </div>

        <!-- Facility -->
        <div data-slot="form-item" class="grid gap-1">
          <FormSearchableSelect
            v-model="form.facility_id"
            :label="t.facility || 'Facility'"
            :options="facilityOptions"
            :error="usageStore.validationErrors?.facility_id"
            :placeholder="t.facility_placeholder || 'Select facility'"
            required
            @change="onFacilityChange"
          />
        </div>

        <!-- Facility Branch -->
        <div data-slot="form-item" class="grid gap-1">
          <FormSearchableSelect
            v-model="form.facility_branch_id"
            :label="t.facility_branch || 'Facility Branch'"
            :options="filteredBranchOptions"
            :error="usageStore.validationErrors?.facility_branch_id"
            :placeholder="t.facility_branch_placeholder || 'Select branch (optional)'"
          />
        </div>

        <!-- Facility Type (auto-filled, read-only) -->
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-1">{{ t.facility_type || 'Facility Type' }}</label>
          <div class="w-full py-2 px-3 border border-border text-foreground dark:bg-input/30 bg-transparent rounded-md h-9 mt-1 opacity-60 cursor-not-allowed select-none flex items-center">
            {{ selectedFacilityTypeName || t.facility_type_auto || 'Auto-filled from facility' }}
          </div>
          <p v-if="usageStore.validationErrors?.facility_type_id" class="mt-1 text-sm text-destructive">
            {{ usageStore.validationErrors.facility_type_id }}
          </p>
        </div>

        <!-- Amount -->
        <div data-slot="form-item" class="grid gap-1">
          <FormInput
            v-model="form.amount"
            :label="t.amount || 'Amount'"
            type="number"
            step="0.01"
            min="0"
            :error="usageStore.validationErrors?.amount"
            :placeholder="t.amount_placeholder || '0.00'"
            required
          />
        </div>

        <!-- Description -->
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-1">
            {{ t.description || 'Description' }} <span class="text-xs text-muted-foreground">({{ t.description_optional || 'optional' }})</span>
          </label>
          <textarea
            v-model="form.description"
            rows="4"
            maxlength="1000"
            :placeholder="t.description_placeholder || 'Enter description...'"
            class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md mt-1 focus:ring-[3px] focus:ring-ring/50 resize-none"
            :class="usageStore.validationErrors?.description ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''"
          ></textarea>
          <p v-if="usageStore.validationErrors?.description" class="mt-1 text-sm text-destructive">
            {{ usageStore.validationErrors.description }}
          </p>
        </div>
      </div>
    </div>
    <!-- Gallery Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="7" height="7" x="3" y="3" rx="1"/>
            <rect width="7" height="7" x="14" y="3" rx="1"/>
            <rect width="7" height="7" x="14" y="14" rx="1"/>
            <rect width="7" height="7" x="3" y="14" rx="1"/>
          </svg>
          {{ t.gallery_title || 'Gallery' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">

        <!-- Existing gallery thumbnails (edit mode) -->
        <div v-if="existingGallery.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div
            v-for="item in existingGallery"
            :key="item.id"
            class="relative group aspect-square"
          >
            <img
              :src="item.url"
              alt="Gallery image"
              class="w-full h-full object-cover rounded-lg border border-border"
            />
            <button
              type="button"
              class="absolute top-1 right-1 bg-destructive text-destructive-foreground rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-sm font-bold leading-none"
              @click="removeExistingGalleryItem(item.id)"
              title="Remove"
            >×</button>
          </div>
        </div>

        <!-- New image previews -->
        <div v-if="galleryNewPreviews.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div
            v-for="(preview, index) in galleryNewPreviews"
            :key="index"
            class="flex flex-col gap-1"
          >
            <div class="relative group aspect-square">
              <img
                :src="preview.url"
                alt="New gallery image"
                class="w-full h-full object-cover rounded-lg border"
                :class="galleryError(index) ? 'border-destructive' : 'border-ring'"
              />
              <button
                type="button"
                class="absolute top-1 right-1 bg-destructive text-destructive-foreground rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-sm font-bold leading-none"
                @click="removeNewGalleryItem(index)"
                title="Remove"
              >×</button>
            </div>
            <!-- Per-image error -->
            <p v-if="galleryError(index)" class="text-xs text-destructive leading-tight">
              {{ galleryError(index) }}
            </p>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="existingGallery.length === 0 && galleryNewPreviews.length === 0" class="text-sm text-muted-foreground">
          {{ t.no_gallery || 'No gallery images yet.' }}
        </div>

        <!-- Add images button -->
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md border border-dashed border-border px-4 py-2 text-sm text-muted-foreground hover:text-foreground hover:border-foreground transition-colors"
          @click="galleryInput.click()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ t.add_images || 'Add Images' }}
        </button>
        <input
          type="file"
          ref="galleryInput"
          multiple
          accept="image/*"
          class="hidden"
          @change="handleGalleryChange"
        />

        <!-- Accepted formats hint -->
        <p class="text-xs text-muted-foreground">
          {{ t.gallery_formats || 'Accepted: JPEG, JPG, PNG, GIF, WEBP, AVIF · Max 5 MB per image' }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput } from "@/Components/form";
import FormSearchableSelect from "@/Components/form/FormSearchableSelect.vue";
import { useMembershipUsageStore } from "../Stores/MembershipUsageStore";
import { computed, ref, markRaw } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  memberships: { type: Array, default: () => [] },
  facilities: { type: Array, default: () => [] },
  facilityBranches: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
  usage: { type: Object, default: null },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.membership_usage || {});

const usageStore = useMembershipUsageStore();
const { form } = storeToRefs(usageStore);



const membershipOptions = computed(() =>
  props.memberships.map(m => ({
    value: String(m.id),
    label: `${m.membership_number}${m.user_name ? ' — ' + m.user_name : ''}`,
  }))
);

const facilityOptions = computed(() =>
  props.facilities.map(f => ({ value: String(f.id), label: f.name }))
);

const filteredBranchOptions = computed(() => {
  if (!form.value.facility_id) return props.facilityBranches.map(b => ({ value: String(b.id), label: b.name }));
  return props.facilityBranches
    .filter(b => String(b.facility_id) === String(form.value.facility_id))
    .map(b => ({ value: String(b.id), label: b.name }));
});

const selectedFacilityTypeName = computed(() => {
  if (!form.value.facility_type_id) return '';
  return props.facilityTypes.find(ty => String(ty.id) === String(form.value.facility_type_id))?.name || '';
});

// Gallery
const galleryInput = ref(null);
const galleryNewPreviews = ref([]);

const existingGallery = computed(() =>
  (props.usage?.gallery || []).filter(item => !form.value.gallery_delete.includes(item.id))
);

const galleryError = (index) => {
  const raw = usageStore.validationErrors?.['gallery.' + index];
  if (!raw) return null;
  if (raw.includes('validation.image') || raw.includes('validation.mimes')) {
    return t.value.gallery_error_invalid || 'Invalid file. Only JPEG, JPG, PNG, GIF, WEBP, and AVIF images are allowed.';
  }
  if (raw.includes('validation.max')) {
    return t.value.gallery_error_size || 'File exceeds the 5 MB size limit.';
  }
  return raw;
};

const removeExistingGalleryItem = (id) => {
  form.value.gallery_delete.push(id);
};

const removeNewGalleryItem = (index) => {
  galleryNewPreviews.value.splice(index, 1);
  form.value.gallery.splice(index, 1);
};

const handleGalleryChange = (event) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    form.value.gallery.push(markRaw(file));
    const reader = new FileReader();
    reader.onload = (e) => {
      galleryNewPreviews.value.push({ url: e.target.result });
    };
    reader.readAsDataURL(file);
  });
  event.target.value = '';
};

const onFacilityChange = (newFacilityId) => {
  form.value.facility_branch_id = '';
  const selectedFacility = props.facilities.find(f => String(f.id) === String(newFacilityId));
  if (selectedFacility?.facility_type_id) {
    form.value.facility_type_id = String(selectedFacility.facility_type_id);
  }
};
</script>
