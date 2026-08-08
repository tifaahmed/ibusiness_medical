<template>
  <div class="min-h-screen membership-card-theme py-4 sm:py-8 px-3 sm:px-4" :dir="locale === 'ar' ? 'rtl' : 'ltr'">

    <!-- Floating background icons -->
    <div class="bg-deco deco-icon deco-icon-1"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.25"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-2"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-3"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18"/><path d="M12 3v18"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-4"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.2"><path d="M12 3v18"/><path d="M3 12h18"/><path d="M5.64 5.64l12.72 12.72"/><path d="M18.36 5.64L5.64 18.36"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-5"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.15"><path d="M6 6h12v12H6z"/><circle cx="12" cy="12" r="4"/><path d="M12 2v20"/><path d="M2 12h20"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-6"><svg viewBox="0 0 24 24" fill="none" stroke="#D4C4A8" stroke-width="1.5" opacity="0.18"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M12 8v4l3 3"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-7"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.15"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-8"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-9"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.15"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-10"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.18"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M2 10h20"/><line x1="12" y1="10" x2="12" y2="22"/></svg></div>
    <div class="bg-deco deco-left-vignette"></div>
    <div class="bg-deco deco-right-vignette"></div>
    <div class="bg-deco deco-gold-band-top"></div>
    <div class="bg-deco deco-gold-band-bottom"></div>
    <div class="bg-deco deco-corner-tl">
      <svg viewBox="0 0 60 60" class="deco-corner-svg"><path d="M0 30 Q0 0 30 0" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.5"/><path d="M6 30 Q6 6 30 6" fill="none" stroke="#C4AD82" stroke-width="0.8" opacity="0.35"/></svg>
    </div>
    <div class="bg-deco deco-corner-tr">
      <svg viewBox="0 0 60 60" class="deco-corner-svg"><path d="M60 30 Q60 0 30 0" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.5"/><path d="M54 30 Q54 6 30 6" fill="none" stroke="#C4AD82" stroke-width="0.8" opacity="0.35"/></svg>
    </div>

    <div class="max-w-2xl mx-auto relative z-10">

      <!-- Header -->
      <div class="flex items-start justify-between gap-3 sm:gap-4 mb-3 sm:mb-5">
        <div>
          <h1 class="text-xl sm:text-3xl font-bold text-card-foreground mb-1 sm:mb-2">{{ t.title || 'Add Membership Usage' }}</h1>
          <p class="text-xs sm:text-sm text-muted-foreground">{{ t.subtitle || 'Record a new facility usage' }}</p>
        </div>
        <Link
          :href="route('guest.membership.show', membership.slug)"
          class="shrink-0 inline-flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border border-border hover:bg-primary hover:text-primary-foreground h-8 sm:h-10 px-3 sm:px-5 py-1.5 sm:py-2 bg-card text-card-foreground"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-4 sm:h-4" :style="locale === 'ar' ? 'transform: rotate(180deg)' : ''">
            <path d="M15 18l-6-6 6-6"/>
          </svg>
          {{ t.cancel || 'Cancel' }}
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm">
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-lg p-3 sm:p-5 space-y-3">

          <!-- Facility -->
          <div class="grid gap-1">
            <label class="block text-sm font-medium text-card-foreground">
              {{ t.facility || 'Facility' }} <span class="text-destructive">*</span>
            </label>
            <SearchableSelect
              v-model="form.facility_id"
              :options="facilityOptions"
              :placeholder="t.facility_placeholder || 'Select facility'"
              :error="form.errors.facility_id"
              @change="onFacilityChange"
            />
            <p v-if="form.errors.facility_id" class="mt-1 text-sm text-destructive">{{ form.errors.facility_id }}</p>
          </div>

          <!-- Branch -->
          <div class="grid gap-1">
            <label class="block text-sm font-medium text-card-foreground">
              {{ t.facility_branch || 'Branch' }}
            </label>
            <SearchableSelect
              v-model="form.facility_branch_id"
              :options="filteredBranchOptions"
              :placeholder="t.branch_placeholder || 'Select branch (optional)'"
              :error="form.errors.facility_branch_id"
            />
            <p v-if="form.errors.facility_branch_id" class="mt-1 text-sm text-destructive">{{ form.errors.facility_branch_id }}</p>
          </div>

          <!-- Amount -->
          <div class="grid gap-1">
            <label class="block text-sm font-medium text-card-foreground">
              {{ t.amount || 'Total Paid at Facility' }} <span class="text-destructive">*</span>
            </label>
            <input
              v-model="form.amount"
              type="number"
              step="0.01"
              min="0"
              :placeholder="t.amount_placeholder || '0.00'"
              class="w-full py-2 px-3 border border-border text-card-foreground placeholder:text-muted-foreground focus:border-ring bg-transparent focus:outline-none rounded-md h-9 focus:ring-[3px] focus:ring-ring/50 text-sm"
              :class="form.errors.amount ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''"
            />
            <p v-if="form.errors.amount" class="mt-1 text-sm text-destructive">{{ form.errors.amount }}</p>
          </div>

          <!-- Description -->
          <div class="grid gap-1">
            <label class="block text-sm font-medium text-card-foreground">
              {{ t.description || 'Description' }}
              <span class="text-xs text-muted-foreground">({{ t.description_optional || 'optional' }})</span>
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              maxlength="1000"
              :placeholder="t.description_placeholder || 'Enter description...'"
              class="w-full py-2 px-3 border border-border text-card-foreground placeholder:text-muted-foreground focus:border-ring bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 resize-none text-sm"
              :class="form.errors.description ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''"
            ></textarea>
            <p v-if="form.errors.description" class="mt-1 text-sm text-destructive">{{ form.errors.description }}</p>
          </div>

          <!-- Gallery -->
          <div class="grid gap-3">
            <div>
              <label class="block text-sm font-medium text-card-foreground">{{ t.add_images || 'Add Receipt / Bill Images' }}</label>
              <p class="text-xs text-muted-foreground mt-0.5">{{ t.gallery_hint || 'Attach receipt or bill images to confirm the payment' }}</p>
            </div>

            <div v-if="galleryPreviews.length > 0" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
              <div v-for="(preview, index) in galleryPreviews" :key="index" class="flex flex-col gap-1">
                <div class="relative group aspect-square">
                  <img
                    :src="preview.url"
                    alt=""
                    class="w-full h-full object-cover rounded-lg border"
                    :class="galleryError(index) ? 'border-destructive' : 'border-border'"
                  />
                  <button
                    type="button"
                    class="absolute top-1 right-1 bg-destructive text-destructive-foreground rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-sm font-bold leading-none"
                    @click="removeImage(index)"
                  >×</button>
                </div>
                <p v-if="galleryError(index)" class="text-xs text-destructive leading-tight">{{ galleryError(index) }}</p>
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-dashed border-border px-4 py-2 text-sm text-muted-foreground hover:text-card-foreground hover:border-primary transition-colors w-fit"
                @click="galleryInput.click()"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"/>
                  <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                {{ t.add_images || 'Add Receipt / Bill Images' }}
              </button>
              <p class="text-xs text-muted-foreground">{{ t.gallery_formats || 'Accepted: JPEG, JPG, PNG, GIF, WEBP, AVIF · Max 5 MB per image' }}</p>
            </div>

            <input
              type="file"
              ref="galleryInput"
              multiple
              accept="image/*"
              class="hidden"
              @change="handleGalleryChange"
            />
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 justify-end mt-4">
          <Link
            :href="route('guest.membership.show', membership.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border border-border hover:bg-primary hover:text-primary-foreground h-10 px-6 py-2 bg-card text-card-foreground"
          >
            {{ t.cancel || 'Cancel' }}
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-10 px-6 py-2 disabled:opacity-50 disabled:pointer-events-none"
          >
            <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
            </svg>
            {{ t.save || 'Save' }}
          </button>
        </div>
      </form>

    </div>
  </div>
</template>

<script setup>
import { computed, ref, markRaw } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
  membership:       { type: Object, required: true },
  facilities:       { type: Array,  default: () => [] },
  facilityBranches: { type: Array,  default: () => [] },
  facilityTypes:    { type: Array,  default: () => [] },
});

const page   = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t      = computed(() => page.props.translations?.home?.membership_page?.usage_create || {});

const form = useForm({
  facility_id:        '',
  facility_branch_id: '',
  facility_type_id:   '',
  amount:             '',
  description:        '',
  gallery:            [],
});

const facilityOptions = computed(() =>
  props.facilities.map(f => ({ value: String(f.id), label: f.name }))
);

const filteredBranchOptions = computed(() => {
  if (!form.facility_id) return props.facilityBranches.map(b => ({ value: String(b.id), label: b.name }));
  return props.facilityBranches
    .filter(b => String(b.facility_id) === String(form.facility_id))
    .map(b => ({ value: String(b.id), label: b.name }));
});

const selectedFacilityTypeName = computed(() => {
  if (!form.facility_type_id) return '';
  return props.facilityTypes.find(t => String(t.id) === String(form.facility_type_id))?.name || '';
});

const onFacilityChange = (newId) => {
  form.facility_branch_id = '';
  const selected = props.facilities.find(f => String(f.id) === String(newId));
  form.facility_type_id = selected?.facility_type_id ? String(selected.facility_type_id) : '';
};

const galleryInput    = ref(null);
const galleryPreviews = ref([]);

const galleryError = (index) => {
  const raw = form.errors?.['gallery.' + index];
  if (!raw) return null;
  if (raw.includes('validation.image') || raw.includes('validation.mimes')) {
    return t.value.gallery_error_invalid || 'Invalid file type. Only JPEG, JPG, PNG, GIF, WEBP, and AVIF images are allowed.';
  }
  if (raw.includes('validation.max')) {
    return t.value.gallery_error_size || 'File exceeds 5 MB.';
  }
  return raw;
};

const handleGalleryChange = (event) => {
  Array.from(event.target.files).forEach(file => {
    form.gallery.push(markRaw(file));
    const reader = new FileReader();
    reader.onload = (e) => galleryPreviews.value.push({ url: e.target.result });
    reader.readAsDataURL(file);
  });
  event.target.value = '';
};

const removeImage = (index) => {
  galleryPreviews.value.splice(index, 1);
  form.gallery.splice(index, 1);
};

const submitForm = () => {
  form.post(route('guest.membership-usage.store', props.membership.slug), {
    preserveScroll: true,
    forceFormData: true,
  });
};
</script>

<style scoped>
.membership-card-theme {
  --background: #EDE8E3;
  --foreground: #1A1816;
  --card: #E0DBD5;
  --card-foreground: #1A1816;
  --popover: #F8F5F2;
  --popover-foreground: #1A1816;
  --primary: #B19A71;
  --primary-foreground: #FFFFFE;
  --primary-medium: #C4AD82;
  --primary-medium-foreground: #FFFFFE;
  --secondary: #D6CCC8;
  --secondary-foreground: #1A1816;
  --muted: #F2EFEC;
  --muted-foreground: #8B7D70;
  --accent: #D6CCC8;
  --accent-foreground: #1A1816;
  --destructive: #C76A5E;
  --destructive-foreground: #FFFFFE;
  --border: #C4B9AE;
  --input: #F2EFEC;
  --ring: #B19A71;
  background-color: var(--background);
  color: var(--foreground);
  position: relative;
  overflow-x: hidden;
}

.membership-card-theme {
  scrollbar-width: auto;
  scrollbar-color: #B19A71 #E0DBD5;
}
.membership-card-theme::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
.membership-card-theme::-webkit-scrollbar-track {
  background: #E0DBD5;
  border-radius: 5px;
}
.membership-card-theme::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #B19A71, #C4AD82);
  border-radius: 5px;
  border: 2px solid #E0DBD5;
  transition: background 0.3s ease;
}
.membership-card-theme::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #9B8664, #B19A71);
}
.membership-card-theme::-webkit-scrollbar-corner {
  background: #E0DBD5;
}

@keyframes deco-float-1 {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  25% { transform: translate(8px, -12px) rotate(5deg); }
  50% { transform: translate(0, -20px) rotate(0deg); }
  75% { transform: translate(-8px, -12px) rotate(-5deg); }
}
@keyframes deco-float-2 {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  33% { transform: translate(-10px, -8px) rotate(-3deg); }
  66% { transform: translate(10px, -15px) rotate(3deg); }
}
@keyframes deco-float-3 {
  0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
  50% { transform: translate(12px, -10px) rotate(180deg) scale(1.1); }
}
@keyframes deco-pulse {
  0%, 100% { opacity: 0.12; }
  50% { opacity: 0.28; }
}
@keyframes deco-spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes deco-drift {
  0%, 100% { transform: translate(0, 0); }
  25% { transform: translate(15px, -5px); }
  50% { transform: translate(5px, -15px); }
  75% { transform: translate(-10px, -8px); }
}

.deco-icon {
  position: fixed;
  pointer-events: none;
  z-index: 0;
}
.deco-icon svg {
  width: 100%;
  height: 100%;
}
.deco-icon-1 { top: 8%; left: 5%; width: 36px; height: 36px; animation: deco-float-1 8s ease-in-out infinite; }
.deco-icon-2 { top: 18%; right: 6%; width: 42px; height: 42px; animation: deco-float-2 10s ease-in-out infinite; }
.deco-icon-3 { top: 45%; left: 3%; width: 48px; height: 48px; animation: deco-spin-slow 25s linear infinite; }
.deco-icon-4 { top: 70%; right: 4%; width: 38px; height: 38px; animation: deco-float-1 12s ease-in-out infinite; }
.deco-icon-5 { top: 85%; left: 8%; width: 44px; height: 44px; animation: deco-float-2 9s ease-in-out infinite; }
.deco-icon-6 { top: 35%; right: 3%; width: 40px; height: 40px; animation: deco-drift 11s ease-in-out infinite; }
.deco-icon-7 { top: 55%; left: 6%; width: 46px; height: 46px; animation: deco-float-3 14s ease-in-out infinite; }
.deco-icon-8 { top: 5%; right: 12%; width: 32px; height: 32px; animation: deco-pulse 4s ease-in-out infinite; }
.deco-icon-9 { top: 75%; right: 10%; width: 40px; height: 40px; animation: deco-float-2 13s ease-in-out infinite; }
.deco-icon-10 { top: 60%; left: 2%; width: 34px; height: 34px; animation: deco-spin-slow 30s linear infinite; }

.deco-left-vignette {
  animation: deco-pulse 6s ease-in-out infinite;
}
.deco-right-vignette {
  animation: deco-pulse 6s ease-in-out infinite;
  animation-delay: 3s;
}
.deco-gold-band-top {
  animation: deco-pulse 5s ease-in-out infinite;
}
.deco-gold-band-bottom {
  animation: deco-pulse 5s ease-in-out infinite;
  animation-delay: 2.5s;
}
</style>

<style>
.bg-deco {
  position: fixed;
  pointer-events: none;
  z-index: 0;
}

.deco-left-vignette {
  top: 0;
  left: 0;
  width: 140px;
  height: 100vh;
  background: linear-gradient(to right, #C9C3C1 0%, #DCD6D4 45%, transparent 100%);
}

.deco-right-vignette {
  top: 0;
  right: 0;
  width: 140px;
  height: 100vh;
  background: linear-gradient(to left, #C9C3C1 0%, #DCD6D4 45%, transparent 100%);
}

.deco-gold-band-top {
  top: 12vh;
  left: 50%;
  transform: translateX(-50%);
  width: 64%;
  max-width: 900px;
  height: 3px;
  background: linear-gradient(to right,
    transparent 0%,
    #B19A71 18%,
    #A28C68 35%,
    #9B8664 50%,
    #A28C68 65%,
    #B19A71 82%,
    transparent 100%
  );
  opacity: 0.45;
  border-radius: 2px;
}

.deco-gold-band-bottom {
  top: 22vh;
  left: 50%;
  transform: translateX(-50%);
  width: 48%;
  max-width: 650px;
  height: 2px;
  background: linear-gradient(to right,
    transparent 0%,
    #917D5D 20%,
    #9B8664 40%,
    #A28C68 60%,
    #917D5D 80%,
    transparent 100%
  );
  opacity: 0.3;
  border-radius: 1px;
}

.deco-corner-tl {
  top: 40px;
  left: 30px;
  width: 60px;
  height: 60px;
}

.deco-corner-tr {
  top: 40px;
  right: 30px;
  width: 60px;
  height: 60px;
}

.deco-corner-svg {
  width: 100%;
  height: 100%;
}
</style>