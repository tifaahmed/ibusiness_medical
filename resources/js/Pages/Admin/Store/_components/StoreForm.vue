<template>
  <div class="space-y-4">
    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <h3 class="text-sm font-semibold">Store details</h3>

      <FormTranslatableInput
        v-model="form.title"
        label="Title"
        required
        :error="form.errors['title.ar'] || form.errors['title.en'] || form.errors.title"
      />

      <FormTranslatableInput
        v-model="form.short_description"
        label="Short description"
        multiline
        :rows="2"
        :error="form.errors['short_description.ar'] || form.errors['short_description.en']"
      />

      <FormTranslatableInput
        v-model="form.description"
        label="Description"
        multiline
        :rows="5"
        :error="form.errors['description.ar'] || form.errors['description.en']"
      />

      <div class="space-y-2">
        <label class="text-sm font-medium">YouTube link</label>
        <input
          v-model="form.youtube_link"
          type="url"
          dir="ltr"
          placeholder="https://youtube.com/watch?v=..."
          class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        />
        <p v-if="form.errors.youtube_link" class="text-xs text-destructive">{{ form.errors.youtube_link }}</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="space-y-2">
          <label class="text-sm font-medium">Offer % from</label>
          <input
            v-model="form.offer_percent_from"
            type="number" min="0" max="100" step="0.01"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          />
          <p v-if="form.errors.offer_percent_from" class="text-xs text-destructive">{{ form.errors.offer_percent_from }}</p>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Offer % to</label>
          <input
            v-model="form.offer_percent_to"
            type="number" min="0" max="100" step="0.01"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          />
          <p v-if="form.errors.offer_percent_to" class="text-xs text-destructive">{{ form.errors.offer_percent_to }}</p>
        </div>
      </div>
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <h3 class="text-sm font-semibold">Images</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="text-sm font-medium">Logo</label>
          <ImageFileInput
            :max-size="5"
            :initial-preview="existingLogo"
            :crop="false"
            @file-selected="onLogoSelected"
          />
          <p v-if="form.errors.logo" class="text-xs text-destructive">{{ form.errors.logo }}</p>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Header</label>
          <ImageFileInput
            :max-size="5"
            :initial-preview="existingHeader"
            :crop="false"
            @file-selected="onHeaderSelected"
          />
          <p v-if="form.errors.header" class="text-xs text-destructive">{{ form.errors.header }}</p>
        </div>
      </div>

      <StoreGalleryInput
        label="Gallery"
        hint="Images and videos shown on the store page, 5 per row — max 5MB each"
        :max-size="5"
        :existing-items="visibleExistingGallery"
        :errors="form.errors"
        @remove-existing="onRemoveExistingGalleryItem"
        @update:images="(files) => (form.gallery_images = files)"
        @update:videos="(files) => (form.gallery_videos = files)"
      />
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold">Branches</h3>
        <button
          type="button"
          @click="addBranch"
          class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path><path d="M12 5v14"></path>
          </svg>
          Add branch
        </button>
      </div>

      <p v-if="!form.branches.length" class="text-sm text-muted-foreground">No branches yet — click "Add branch" to add one.</p>

      <div
        v-for="(branch, index) in form.branches"
        :key="index"
        class="rounded-lg border border-border p-3 space-y-3"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Branch {{ index + 1 }}</span>
          <button
            type="button"
            @click="removeBranch(index)"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-destructive hover:bg-destructive/10"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-medium">Governorate</label>
            <Select
              v-model="branch.governorate_id"
              :options="governorates.map(g => ({ value: g.id, label: getName(g.name) }))"
              placeholder="Select governorate"
              @update:modelValue="branch.city_id = ''"
            />
            <p v-if="form.errors[`branches.${index}.governorate_id`]" class="text-xs text-destructive">{{ form.errors[`branches.${index}.governorate_id`] }}</p>
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">City</label>
            <Select
              v-model="branch.city_id"
              :options="citiesFor(branch.governorate_id).map(c => ({ value: c.id, label: getName(c.name) }))"
              placeholder="Select city"
            />
            <p v-if="form.errors[`branches.${index}.city_id`]" class="text-xs text-destructive">{{ form.errors[`branches.${index}.city_id`] }}</p>
          </div>
        </div>

        <FormTranslatableInput v-model="branch.name" label="Branch name" />
        <FormTranslatableInput v-model="branch.address" label="Address" multiline :rows="2" />
        <FormTranslatableInput v-model="branch.area" label="Area" />

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-medium">Latitude</label>
            <input
              v-model="branch.latitude"
              type="number" step="0.0000001" dir="ltr"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">Longitude</label>
            <input
              v-model="branch.longitude"
              type="number" step="0.0000001" dir="ltr"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">Google Maps link</label>
            <input
              v-model="branch.google_location_url"
              type="url" dir="ltr" placeholder="https://maps.google.com/..."
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
        </div>

        <BranchPhonesInput
          v-model="branch.phone"
          label="Phone numbers"
          :errors="form.errors"
          :error-prefix="`branches.${index}.phone`"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ImageFileInput from '@/Components/form/ImageFileInput.vue';
import StoreGalleryInput from '@/Components/form/StoreGalleryInput.vue';
import BranchPhonesInput from '@/Components/form/BranchPhonesInput.vue';
import FormTranslatableInput from '@/Components/form/FormTranslatableInput.vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
  form: { type: Object, required: true },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  existingLogo: { type: String, default: '' },
  existingHeader: { type: String, default: '' },
  existingGallery: { type: Array, default: () => [] },
});

const getName = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') return name['ar'] || name['en'] || Object.values(name)[0] || '';
  return '';
};

const citiesFor = (governorateId) => props.cities.filter(c => String(c.governorate_id) === String(governorateId));

const visibleExistingGallery = computed(() =>
  props.existingGallery.filter(item => !props.form.gallery_delete.includes(item.id))
);

const onLogoSelected = (file) => { props.form.logo = file; if (!file) props.form.logo_delete = true; };
const onHeaderSelected = (file) => { props.form.header = file; if (!file) props.form.header_delete = true; };

const onRemoveExistingGalleryItem = (id) => {
  if (!props.form.gallery_delete.includes(id)) props.form.gallery_delete.push(id);
};

const blankBranch = () => ({
  governorate_id: '',
  city_id: '',
  name: { ar: '', en: '' },
  address: { ar: '', en: '' },
  area: { ar: '', en: '' },
  latitude: '',
  longitude: '',
  google_location_url: '',
  phone: [],
});

const addBranch = () => props.form.branches.push(blankBranch());
const removeBranch = (index) => props.form.branches.splice(index, 1);
</script>
