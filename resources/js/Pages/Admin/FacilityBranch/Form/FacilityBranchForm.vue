<template>
  <div class="space-y-3">
    <!-- Facility Branch Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden flex flex-wrap items-center gap-2">
          <span class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building title-icon">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            {{ t.facility_branch?.information || 'Facility Branch Information' }}
          </span>
          <!-- Shown even when it cannot run: a button that quietly disappears
               reads as a missing feature, while a disabled one with its reason
               attached reads as something to go and switch on. -->
          <button
            v-if="translateHint"
            type="button"
            class="ml-auto inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-border text-[11px] font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
            :title="translateHint"
            :aria-label="translateHint"
            @click="showTranslateHint = !showTranslateHint"
          >
            i
          </button>
          <span v-if="translateHint && showTranslateHint" class="max-w-[16rem] text-[11px] font-normal text-amber-300">
            {{ translateHint }}
          </span>
          <button
            type="button"
            :disabled="!canTranslate || translating"
            :class="[
              'inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none',
              translateHint ? '' : 'ml-auto'
            ]"
            :title="translateHint || (t.facility_branch?.fix_languages_hint || 'If the Arabic or the English is empty, in the wrong language or badly written, fix both')"
            @click="fixEnglish"
          >
            <svg v-if="translating" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
            </svg>
            {{ translating
              ? (t.facility_branch?.fix_languages_fixing || 'Fixing languages…')
              : (t.facility_branch?.fix_languages || 'Fix languages with AI') }}
          </button>
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Row 1: Parent Facility -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="formFacilityId"
              :label="t.facility_branch?.parent_facility || 'Parent Facility'"
              :options="facilityOptions"
              :error="facilityBranchStore.validationErrors?.facility_id"
              :placeholder="t.facility?.all || 'Select a facility'"
              required
            />
            <!-- Straight to the parent facility's own edit page. -->
            <Link
              v-if="parentFacilityEditUrl"
              :href="parentFacilityEditUrl"
              class="mt-1 inline-flex h-8 w-fit items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              {{ t.facility_branch?.edit_facility || 'Edit the facility' }}
            </Link>
          </div>
        </div>


        <!-- Row 2: Branch Name, then Address — each on its own line, so a row
             is one field in its two languages instead of four boxes from two. -->
        <div class="space-y-4">
          <div data-slot="form-item" class="grid gap-1">
            <!-- Required, in at least one of the two languages — the save
                 refuses a branch with neither. -->
            <FormTranslatableInput
              v-model="formName"
              :label="t.facility_branch?.branch_name || 'Branch Name'"
              :error="nameError"
              :placeholder="t.facility_branch?.branch_name_placeholder || 'Enter branch name'"
              :locales="['ar', 'en']"
              required
            />
            <!-- Branch names are usually "<facility> - <city>", so the city is
                 one click rather than retyped. One button per language, each
                 appending that language's spelling of the city. -->
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <button
                v-for="lang in ['ar', 'en']"
                :key="lang"
                type="button"
                :disabled="!canAddCity(lang)"
                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
                :title="addCityHint(lang)"
                @click="addCityToName(lang)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14"></path><path d="M12 5v14"></path>
                </svg>
                {{ (t.facility_branch?.add_city_to_name || 'Add city to name') }} ({{ lang.toUpperCase() }})
              </button>
              <span v-if="addCityHint('ar') && addCityHint('ar') === addCityHint('en')" class="text-[11px] text-muted-foreground">
                {{ addCityHint('ar') }}
              </span>
            </div>
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <!-- Required in both languages: an address in one language only
                 leaves half the directory with nothing to show, and it is what
                 the AI geocoder below reads to place the branch on the map. -->
            <FormTranslatableInput
              v-model="formAddress"
              :label="t.common?.address || 'Address'"
              :error="addressError"
              :placeholder="t.facility_branch?.address_placeholder || 'Enter branch address'"
              :locales="['ar', 'en']"
              multiline
              :rows="3"
              required
            />
          </div>
        </div>

        <!-- Row 3: Governorate + City -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="facilityBranchStore.form.governorate_id"
              :label="t.governorate?.label || 'Governorate'"
              :options="governorateOptions"
              :error="facilityBranchStore.validationErrors?.governorate_id"
              :placeholder="t.governorate?.select || 'Select a governorate'"
              required
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="facilityBranchStore.form.city_id"
              :label="t.city?.label || 'City'"
              :options="cityOptions"
              :error="facilityBranchStore.validationErrors?.city_id"
              :placeholder="t.city?.select || 'Select a city'"
              required
            />
          </div>
        </div>

        <!-- Row 3b: the two readers of the address typed above — one fills the
             place it names, the other the point on the map. -->
        <div class="flex flex-wrap items-center justify-end gap-2">
          <p v-if="locateHint" class="order-2 sm:order-1 text-[11px] text-muted-foreground">{{ locateHint }}</p>
          <button
            v-if="placeAiEnabled"
            type="button"
            :disabled="!canResolvePlace || resolvingPlace"
            class="order-1 inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
            :title="placeHint || (t.facility_branch?.place_generate_hint || 'Read the address above and choose the governorate and city it names')"
            @click="resolvePlace"
          >
            <svg v-if="resolvingPlace" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path>
            </svg>
            {{ resolvingPlace
              ? (t.facility_branch?.place_generating || 'Reading address…')
              : (t.facility_branch?.place_generate || 'Fill governorate & city with AI') }}
          </button>
          <button
            v-if="locationAiEnabled"
            type="button"
            :disabled="!canLocate || locating"
            class="order-3 inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
            :title="locateHint || (t.facility?.location_generate_hint || 'Read the address above and fill in the coordinates and the Google Maps link')"
            @click="locate"
          >
            <svg v-if="locating" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            {{ locateLabel }}
          </button>
        </div>

        <!-- Row 4: Latitude + Longitude -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.latitude"
              :label="t.facility?.latitude || 'Latitude'"
              type="number"
              step="any"
              :placeholder="t.facility?.latitude_placeholder || 'e.g. 30.0444'"
              :error="facilityBranchStore.validationErrors?.latitude"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.longitude"
              :label="t.facility?.longitude || 'Longitude'"
              type="number"
              step="any"
              :placeholder="t.facility?.longitude_placeholder || 'e.g. 31.2357'"
              :error="facilityBranchStore.validationErrors?.longitude"
            />
          </div>
        </div>

        <!-- Row 4a: where those coordinates land -->
        <FacilityBranchLocationMap
          :latitude="facilityBranchStore.form.latitude"
          :longitude="facilityBranchStore.form.longitude"
        />

        <!-- Row 4b: Google Location URL -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.google_location_url"
              :label="t.facility_branch?.google_location_url || 'Google Location URL'"
              type="url"
              :placeholder="t.facility_branch?.google_location_url_placeholder || 'https://maps.app.goo.gl/...'"
              :error="facilityBranchStore.validationErrors?.google_location_url"
            />
          </div>
        </div>

        <!-- Row 5: Phone Numbers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <BranchPhonesInput
              v-model="form.phone"
              :label="t.facility_branch?.phone_numbers || 'Phone Numbers'"
              :hint="t.facility_branch?.phone_help || '(one number per row)'"
              :errors="facilityBranchStore.validationErrors || {}"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormSelect, FormInput, BranchPhonesInput } from "@/Components/form";
import { useFacilityBranchStore } from "../Stores/FacilityBranchStore";
import FacilityBranchLocationMap from "./FacilityBranchLocationMap.vue";
import { computed, nextTick, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { Link, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import { useNotification } from "@/composables/useNotification";
import { bilingualLabel, nameIn, primaryName } from "@/lib/lookupNames";

const props = defineProps({
  facilities: {
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
  },
  // False when GEMINI_API_KEY is unset — the buttons stay put but explain
  // themselves rather than being offered and then refused by the route.
  locationAiEnabled: {
    type: Boolean,
    default: false
  },
  englishFixEnabled: {
    type: Boolean,
    default: false
  },
  placeAiEnabled: {
    type: Boolean,
    default: false
  }
});

const facilityBranchStore = useFacilityBranchStore();
const { form } = storeToRefs(facilityBranchStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

// Convert facilities to select options
const facilityOptions = computed(() => {
  const currentLocale = locale.value; // Ensure locale is tracked as dependency
  return props.facilities.map(facility => {
    const name = typeof facility.name === 'object'
      ? (facility.name[currentLocale] || facility.name['ar'] || facility.name['en'] || Object.values(facility.name)[0] || '')
      : facility.name;
    const branchesCount = facility.branches_count ?? 0;
    const branchesLabel = currentLocale === 'ar'
      ? `(${branchesCount} فروع)`
      : `(${branchesCount} branches)`;
    return {
      value: facility.id,
      label: `${name} ${branchesLabel}`
    };
  });
});

// The parent facility's edit page — only for accounts that may edit facilities.
const { canManage } = usePermissions();
const parentFacilityEditUrl = computed(() => {
  if (!canManage('manage facilities', 'manage own facilities')) return null;
  const facility = props.facilities.find(f => String(f.id) === String(form.value.facility_id));
  return facility?.slug ? route('admin.facility.edit', facility.slug) : null;
});

// Get selected facility details
const selectedFacility = computed(() => {
  if (!form.value.facility_id) return null;
  return props.facilities.find(f => f.id === form.value.facility_id) || null;
});

/* Both spellings in the option, because picking the city is what the branch
   name is then built from in both languages — and several cities read alike in
   one language while being plainly different in the other. Search reads the
   whole label, so typing either spelling finds the row. */
const governorateOptions = computed(() =>
  props.governorates.map(governorate => ({
    value: governorate.id,
    label: bilingualLabel(governorate.name, locale.value),
  }))
);

const cityOptions = computed(() => {
  const selectedGov = facilityBranchStore.form.governorate_id;
  return props.cities
    .filter(city => !selectedGov || String(city.governorate_id) === String(selectedGov))
    .map(city => ({
      value: city.id,
      label: bilingualLabel(city.name, locale.value),
    }));
});

watch(() => facilityBranchStore.form.governorate_id, (newGov, oldGov) => {
  if (oldGov !== undefined && newGov !== oldGov) {
    const stillValid = props.cities.some(
      c => String(c.id) === String(facilityBranchStore.form.city_id) && String(c.governorate_id) === String(newGov)
    );
    if (!stillValid) {
      facilityBranchStore.form.city_id = '';
    }
  }
});

// Ensure we always have a valid facility_id for the form
const formFacilityId = computed({
  get: () => {
    return form.value.facility_id || '';
  },
  set: (value) => {
    form.value.facility_id = value;
  }
});

// Ensure we always have a valid name object for the form
const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return {};
    }
    return name;
  },
  set: (value) => {
    form.value.name = value;
  }
});

// Ensure we always have a valid address object for the form
const formAddress = computed({
  get: () => {
    const address = form.value.address;
    if (!address || typeof address !== 'object' || Array.isArray(address)) {
      return {};
    }
    return address;
  },
  set: (value) => {
    form.value.address = value;
  }
});

/* Laravel reports a translatable field per locale ("address.ar"), while the
   input takes one message. Whichever language was complained about is shown. */
const localeError = (field) => {
  const errors = facilityBranchStore.validationErrors || {};
  return errors[field]
    || errors[`${field}.ar`]
    || errors[`${field}.en`]
    || null;
};

const nameError = computed(() => localeError('name'));
const addressError = computed(() => localeError('address'));

const selectedOptionLabel = (options, id) => {
  if (!id) return '';
  const match = options.find(option => String(option.value) === String(id));
  return match ? match.label : '';
};

/* ---- AI location ----------------------------------------------------------
   The same button as the facility form's branch modal: the address the admin
   has typed goes to the server, which asks the model to geocode it and builds
   the Google Maps link from the coordinates it returns. Nothing is saved — the
   values land in the open form so the pin can be checked first.
--------------------------------------------------------------------------- */
const locating = ref(false);

const filledText = (value) => {
  if (typeof value === 'string') return value.trim() !== '';
  if (value && typeof value === 'object') {
    return Object.values(value).some(entry => typeof entry === 'string' && entry.trim() !== '');
  }
  return false;
};

// An address is what the model actually searches on; a governorate or city
// alone would only ever produce a centre-of-town pin.
const hasAddress = computed(() => filledText(form.value.address));

const canLocate = computed(() => props.locationAiEnabled && hasAddress.value);

const locateHint = computed(() => {
  if (!props.locationAiEnabled) return '';
  if (!hasAddress.value) return t.value.facility?.location_needs_address || 'Enter the branch address first.';
  return '';
});

// The button's own words change with what it is about to do: fill the empty
// boxes, or replace what is in them.
const locateLabel = computed(() => {
  if (locating.value) return t.value.facility?.location_generating || 'Locating…';
  if (hasCoordinates.value) return t.value.facility?.location_replace || 'Replace GPS with AI';
  return t.value.facility?.location_generate || 'Find GPS on map with AI';
});

const selectedFacilityName = computed(() => selectedFacility.value?.name || {});

const hasCoordinates = computed(() =>
  String(form.value.latitude ?? '').trim() !== '' && String(form.value.longitude ?? '').trim() !== ''
);

const locate = async () => {
  if (!canLocate.value || locating.value) return;

  /* Filling an empty pair is the whole point of the button, so that happens
     without ceremony. Coordinates already on the branch may have been checked
     against the map by hand, which is worth more than a fresh guess — so
     replacing them is asked about rather than done quietly. */
  if (hasCoordinates.value && !window.confirm(
    t.value.facility?.location_replace_confirm
    || 'This branch already has coordinates. Replace them with the AI\'s answer?'
  )) {
    return;
  }

  locating.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.locate'), {
      address: form.value.address || {},
      name: form.value.name || {},
      facility_name: typeof selectedFacilityName.value === 'object' ? selectedFacilityName.value : {},
      governorate: selectedOptionLabel(governorateOptions.value, form.value.governorate_id) || null,
      city: selectedOptionLabel(cityOptions.value, form.value.city_id) || null,
    });

    const location = data?.location;
    if (!location) throw new Error('empty');

    form.value.latitude = location.latitude;
    form.value.longitude = location.longitude;
    form.value.google_location_url = location.google_location_url;

    clearErrors('latitude', 'longitude', 'google_location_url');

    const place = location.matched_place ? ` (${location.matched_place})` : '';
    useNotification().success(
      (t.value.facility?.location_generated || 'Location filled in. Open the map link to check the pin before saving.') + place
    );
  } catch (error) {
    const message = error?.response?.data?.message
      || error?.response?.data?.errors?.address?.[0]
      || (t.value.facility?.location_generate_failed || 'Could not find the location. Please try again.');
    useNotification().error(message);
  } finally {
    locating.value = false;
  }
};

/* ---- Governorate & city from the address ----------------------------------
   The address almost always names the place already, and both fields are
   required to save. The server hands back the ids of rows that exist, so what
   lands here is always a real governorate and a real city inside it.
--------------------------------------------------------------------------- */
const resolvingPlace = ref(false);

const canResolvePlace = computed(() => props.placeAiEnabled && hasAddress.value);

const placeHint = computed(() => {
  if (!props.placeAiEnabled) return '';
  if (!hasAddress.value) return t.value.facility?.location_needs_address || 'Enter the branch address first.';
  return '';
});

const resolvePlace = async () => {
  if (!canResolvePlace.value || resolvingPlace.value) return;

  resolvingPlace.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.place'), {
      address: form.value.address || {},
      name: form.value.name || {},
      facility_name: typeof selectedFacilityName.value === 'object' ? selectedFacilityName.value : {},
    });

    const place = data?.place;
    if (!place) throw new Error('empty');

    /* The governorate is set first and on its own tick: the watcher below
       clears a city that does not belong to the governorate, and it would
       otherwise wipe the city we are about to set. */
    if (place.governorate_id) {
      form.value.governorate_id = place.governorate_id;
      await nextTick();
    }
    if (place.city_id) {
      form.value.city_id = place.city_id;
    }

    clearErrors('governorate_id', 'city_id');

    const chosen = [place.governorate_name, place.city_name]
      .map(name => primaryName(name, locale.value))
      .filter(Boolean)
      .join(' — ');

    useNotification().success(
      (t.value.facility_branch?.place_generated || 'Governorate and city filled in. Check them before saving.')
      + (chosen ? ` (${chosen})` : '')
    );
  } catch (error) {
    useNotification().error(
      error?.response?.data?.message
      || error?.response?.data?.errors?.address?.[0]
      || (t.value.facility_branch?.place_generate_failed || 'Could not read the place from the address. Please choose it by hand.')
    );
  } finally {
    resolvingPlace.value = false;
  }
};

/* ---- Fix languages with AI ------------------------------------------------
   A problem on either side — a box empty, the wrong language in it, the two
   swapped, or one copied into the other — has BOTH languages fixed together, so
   name and address end up consistent. There is nothing to do until at least one
   of the four boxes has something in it.
--------------------------------------------------------------------------- */
const translating = ref(false);
const showTranslateHint = ref(false);

const typed = (value) => String(value || '').trim() !== '';

const canTranslate = computed(() =>
  props.englishFixEnabled
  && ['name', 'address'].some(field => typed(form.value[field]?.ar) || typed(form.value[field]?.en))
);

const translateHint = computed(() => {
  if (!props.englishFixEnabled) {
    return t.value.facility?.english_fix_disabled
      || 'AI is not configured on this server: set GEMINI_API_KEY in the .env file, then restart, to enable this.';
  }
  if (translating.value) return '';
  if (!canTranslate.value) {
    return t.value.facility_branch?.fix_languages_needs_text
      || 'Fill in the name or address first.';
  }
  return '';
});

// A field the AI has just written is no longer the one the server complained
// about, so its message goes with it — per locale as well as whole.
const clearErrors = (...fields) => {
  const all = facilityBranchStore.validationErrors;
  if (!all) return;

  const keys = fields.flatMap(field => [field, `${field}.ar`, `${field}.en`]);
  const kept = Object.fromEntries(
    Object.entries(all).filter(([key]) => !keys.includes(key))
  );

  facilityBranchStore.validationErrors = Object.keys(kept).length ? kept : null;
};

const fixEnglish = async () => {
  if (!canTranslate.value || translating.value) return;

  translating.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.fix-languages'), {
      name: { ar: form.value.name?.ar || '', en: form.value.name?.en || '' },
      address: { ar: form.value.address?.ar || '', en: form.value.address?.en || '' },
      facility_name: primaryName(selectedFacilityName.value, locale.value) || null,
      governorate: selectedOptionLabel(governorateOptions.value, form.value.governorate_id) || null,
      city: selectedOptionLabel(cityOptions.value, form.value.city_id) || null,
    });

    const values = data?.values || {};
    const fixed = [];

    ['name', 'address'].forEach((field) => {
      const pair = values[field];
      if (!pair?.ar || !pair?.en) return;
      form.value[field] = { ...(form.value[field] || {}), ar: pair.ar, en: pair.en };
      clearErrors(field);
      fixed.push(field);
    });

    if (fixed.length === 0) {
      // Not a failure: both languages already read correctly.
      useNotification().success(
        data?.message
        || t.value.facility_branch?.fix_languages_nothing
        || 'Both languages already look right.'
      );
      return;
    }

    useNotification().success(
      t.value.facility_branch?.fix_languages_done
      || 'Arabic and English fixed. Check them before saving.'
    );
  } catch (error) {
    useNotification().error(
      error?.response?.data?.message
      || (t.value.facility_branch?.fix_languages_failed || 'Could not fix the languages. Please try again.')
    );
  } finally {
    translating.value = false;
  }
};

/* ---- "Add city to name" ---------------------------------------------------
   A branch is nearly always "<facility> - <city>", and typing that out in both
   languages is the same edit every time.
--------------------------------------------------------------------------- */
const selectedCity = computed(() =>
  props.cities.find(city => String(city.id) === String(form.value.city_id)) || null
);

const cityNameIn = (lang) => {
  const name = selectedCity.value?.name;
  if (!name) return '';
  if (typeof name === 'object') return nameIn(name, lang);

  // A city stored as a plain string has one spelling; offer it for whichever
  // side it reads as, rather than guessing it must be the Arabic one.
  const plain = String(name).trim();
  const isArabic = /[\u0600-\u06FF]/.test(plain);

  return (lang === 'ar') === isArabic ? plain : '';
};

// A city already written into this language's name is not appended a second
// time — the button for that language goes dead instead.
const canAddCity = (lang) => {
  const city = cityNameIn(lang);
  if (city === '') return false;

  return !String(form.value.name?.[lang] || '').toLowerCase().includes(city.toLowerCase());
};

const addCityHint = (lang) => {
  if (!form.value.city_id) return t.value.city?.select || 'Select a city first';
  if (cityNameIn(lang) === '') {
    return t.value.facility_branch?.city_missing_locale
      || `This city has no ${lang.toUpperCase()} name`;
  }
  if (!canAddCity(lang)) {
    return t.value.facility_branch?.city_already_in_name || 'The city is already in the name';
  }
  return '';
};

const addCityToName = (lang) => {
  if (!canAddCity(lang)) return;

  const city = cityNameIn(lang);
  const current = String(form.value.name?.[lang] || '').trim();

  form.value.name = {
    ...(form.value.name || {}),
    [lang]: current === '' ? city : `${current} - ${city}`,
  };

  clearErrors('name');
};
</script>

<style lang="scss" scoped></style>
