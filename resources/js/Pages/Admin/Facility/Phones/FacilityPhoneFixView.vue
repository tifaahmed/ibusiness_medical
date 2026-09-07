<template>
  <FacilityLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.facility?.phone_fix_title || 'Fix phone numbers'"
        :breadcrumbs="[
          { label: t.facility?.management || 'Facilities', link: route('admin.facility.list'), active: false },
          { label: t.facility?.phone_fix || 'Fix phones', link: '#', active: true },
        ]"
      />

      <!-- What the rule is, so the suggested column can be read at a glance. -->
      <div class="bg-card border border-border rounded-xl p-4 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-white">
            {{ t.facility?.phone_fix_heading || 'Branches with a phone number in the wrong shape' }}
          </h2>
          <span class="text-xs text-muted-foreground">
            {{ remaining }} / {{ problems.meta.total }}
          </span>
        </div>

        <!-- Which numbers to work through. A number of the other kind is left
             exactly as it is, so the two passes never tread on each other. -->
        <div class="flex flex-wrap items-center gap-1.5">
          <button
            v-for="option in kindOptions"
            :key="option.value"
            type="button"
            class="inline-flex items-center rounded-md border px-3 h-8 text-xs font-medium transition-colors"
            :class="filters.kind === option.value
              ? 'border-golden-yellow bg-golden-yellow/15 text-golden-yellow'
              : 'border-border bg-background text-white/80 hover:bg-muted'"
            @click="selectKind(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
        <p class="text-xs text-muted-foreground">
          {{ t.facility?.phone_fix_rule
            || 'A mobile is 11 digits starting with 01. A landline is 8 digits with no area code — "066 3222328" becomes "63222328", "0212345678" becomes "12345678". Numbers packed into one line are split apart.' }}
        </p>
      </div>

      <!-- One row per branch: what is stored, what it should be, and the button. -->
      <div v-if="rows.length > 0" class="space-y-2">
        <div
          v-for="row in rows"
          :key="row.branch_id"
          class="bg-card border rounded-xl p-3 sm:p-4"
          :class="row.done ? 'border-emerald-500/40' : 'border-border'"
        >
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <!-- Which branch -->
            <div class="min-w-0 lg:w-64 lg:flex-shrink-0">
              <p class="truncate text-sm font-medium text-white">{{ row.branch_name || (t.facility_branch?.unnamed_branch || 'Unnamed Branch') }}</p>
              <Link
                v-if="row.facility_slug"
                :href="route('admin.facility.edit', row.facility_slug)"
                class="truncate block text-xs text-primary hover:underline"
              >
                {{ row.facility_name }}
              </Link>
              <p v-else class="truncate text-xs text-muted-foreground">{{ row.facility_name }}</p>
            </div>

            <!-- Wrong on the left, correct on the right -->
            <div class="flex flex-1 min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
              <div class="min-w-0 flex-1">
                <p class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                  {{ t.facility?.phone_fix_current || 'Stored now' }}
                </p>
                <ul class="space-y-1">
                  <li
                    v-for="(phone, phoneIndex) in row.current"
                    :key="phoneIndex"
                    class="font-mono text-sm break-all"
                    :class="row.done ? 'text-white/60' : 'text-destructive'"
                    dir="ltr"
                  >
                    {{ phone }}
                  </li>
                </ul>
                <!-- The kind each number is filed under today. A hotline stored
                     as a landline is wrong in a way the digits never show, so
                     the old type is named where it is about to change. -->
                <ul v-if="retypedEntries(row).length" class="mt-1 space-y-0.5">
                  <li
                    v-for="(entry, i) in retypedEntries(row)"
                    :key="`retype-${i}`"
                    class="text-[11px] text-amber-500 dark:text-amber-400"
                  >
                    <span class="font-mono" dir="ltr">{{ entry.original }}</span>
                    — filed as {{ typeLabel(entry.type) }}, it is a {{ typeLabel(entry.suggested_type) }}
                  </li>
                </ul>
              </div>

              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden sm:block flex-shrink-0 text-muted-foreground" :class="isRtl ? 'rotate-180' : ''">
                <path d="M5 12h14"></path>
                <path d="m12 5 7 7-7 7"></path>
              </svg>

              <div class="min-w-0 flex-1">
                <p class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                  {{ t.facility?.phone_fix_suggested || 'Will be saved as' }}
                </p>
                <p v-if="row.done" class="space-y-1">
                  <span
                    v-for="(entry, entryIndex) in row.draft"
                    :key="entryIndex"
                    class="block font-mono text-sm text-emerald-400 break-all"
                    dir="ltr"
                  >{{ entry.number }}</span>
                </p>
                <div v-else class="space-y-1">
                  <div
                    v-for="(entry, entryIndex) in row.draft"
                    :key="entryIndex"
                    class="flex items-center gap-1"
                  >
                    <input
                      v-model="entry.number"
                      type="text"
                      inputmode="tel"
                      dir="ltr"
                      maxlength="20"
                      class="w-full min-w-0 rounded-md border border-border bg-transparent px-2 py-1 font-mono text-sm text-emerald-400 focus:border-ring focus:outline-none focus:ring-[3px] focus:ring-ring/50 dark:bg-input/30"
                    />
                    <!-- The kind of line, not just the digits. A number filed
                         wrong — a hotline stored as a landline, a mobile that
                         lost a digit and got stamped landline — is as broken as
                         a mistyped one, and this is the screen for fixing it. -->
                    <div
                      class="w-32 shrink-0 sm:w-40"
                      :title="t.facility?.phone_fix_type || 'What kind of line this number is'"
                    >
                      <Select v-model="entry.type" :options="phoneTypeOptions" />
                    </div>
                    <button
                      type="button"
                      class="flex-shrink-0 rounded-md p-1 text-muted-foreground hover:bg-destructive/20 hover:text-destructive"
                      :title="t.common?.remove || 'Remove'"
                      @click="row.draft.splice(entryIndex, 1)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 text-[11px] text-primary hover:underline"
                      @click="row.draft.push({ number: '', type: DEFAULT_PHONE_TYPE })"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path><path d="M12 5v14"></path>
                      </svg>
                      {{ t.facility?.phone_fix_add || 'Add a number' }}
                    </button>
                    <button
                      v-if="isEdited(row)"
                      type="button"
                      class="text-[11px] text-muted-foreground hover:underline"
                      @click="row.draft = row.suggested.map(entry => ({ ...entry }))"
                    >
                      {{ t.facility?.phone_fix_reset || 'Reset to the suggestion' }}
                    </button>
                  </div>
                  <p v-if="row.needs_review" class="text-[11px] text-amber-400">
                    {{ t.facility?.phone_fix_needs_review || 'One of these could not be worked out — check it before confirming.' }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Confirm, on the same line -->
            <div class="flex-shrink-0 lg:w-36 lg:text-end">
              <span
                v-if="row.done"
                class="inline-flex items-center gap-1.5 rounded-md px-3 h-9 text-sm font-medium text-emerald-400"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                {{ t.facility?.phone_fix_done || 'Saved' }}
              </span>
              <button
                v-else
                type="button"
                :disabled="row.saving"
                class="inline-flex w-full lg:w-auto items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 btn-golden"
                @click="confirmRow(row)"
              >
                <svg v-if="row.saving" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                </svg>
                {{ t.facility?.phone_fix_confirm || 'Confirm' }}
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <Pagination v-if="problems.meta.links?.length > 0" :links="problems.meta.links" />
        </div>
      </div>

      <!-- Nothing left to fix -->
      <div v-else class="bg-card border border-border rounded-xl p-10 text-center space-y-3">
        <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10 border border-emerald-500/20">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-400">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        <p class="text-sm text-white">{{ t.facility?.phone_fix_empty || 'Every branch phone number is already in shape.' }}</p>
        <Link
          :href="route('admin.facility.list')"
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted"
        >
          {{ t.common?.back_to_list || 'Back to list' }}
        </Link>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FacilityLayout from '../FacilityLayout.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import Pagination from '@/Pages/_components/Pagination.vue';
import { useNotification } from '@/composables/useNotification';
import { PHONE_TYPES, DEFAULT_PHONE_TYPE, phoneTypeLabel } from '@/lib/branchPhones';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
  problems: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ kind: 'all', per_page: 15 }),
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const isRtl = computed(() => page.props.locale === 'ar');

// A local copy so a confirmed row can be marked saved in place, without
// re-fetching the page and shifting every other row up.
const rows = ref([]);

watch(
  () => props.problems,
  (problems) => {
    rows.value = (problems?.data || []).map(problem => ({
      ...problem,
      // What the confirm button will send — pre-filled with the suggestion and
      // editable, so a number the rules could not work out can be retyped.
      draft: problem.suggested.map(entry => ({ ...entry })),
      saving: false,
      done: false,
    }));
  },
  { immediate: true, deep: true },
);

const remaining = computed(() => rows.value.filter(row => !row.done).length);

const kindOptions = computed(() => [
  { value: 'all', label: t.value.facility?.phone_fix_kind_all || 'All kinds' },
  { value: 'mobile', label: t.value.facility?.phone_fix_kind_mobile || 'Mobiles only' },
  { value: 'landline', label: t.value.facility?.phone_fix_kind_landline || 'Landlines only' },
  { value: 'hotline', label: t.value.facility?.phone_fix_kind_hotline || 'Hotlines only' },
]);

// Changing the kind starts the list again from page one; the numbers of the
// other kind stay exactly as they are stored.
const selectKind = (kind) => {
  if (kind === props.filters.kind) return;

  router.get(
    route('admin.facility.phones.page'),
    { kind, per_page: props.filters.per_page },
    { preserveState: false, preserveScroll: true },
  );
};

const fingerprint = (entries) => entries.map(entry => `${entry.number}\u0000${entry.type}`).join('|');

const isEdited = (row) => fingerprint(row.draft) !== fingerprint(row.suggested);

/* The kinds a number can be filed under, straight from the shared list rather
   than spelled out again here — a type added to the model has to appear in this
   picker, or a number could never be moved to it. */
const typeLabel = (type) => t.value.facility_branch?.phone_types?.[type] || phoneTypeLabel(type);

// Shaped for the shared picker, and re-labelled when the language changes.
const phoneTypeOptions = computed(() =>
  PHONE_TYPES.map(type => ({ value: type, label: typeLabel(type) }))
);

// The numbers on this row whose kind is about to change, so the old one can be
// named beside the new. WhatsApp is never among them: it says how a number is
// reached, which no amount of digits can settle.
const retypedEntries = (row) => (row.entries || []).filter(entry => entry.type_changed);

const confirmRow = async (row) => {
  if (row.saving || row.done) return;

  row.saving = true;
  try {
    const { data } = await axios.post(route('admin.facility.phones.fix'), {
      branch_id: row.branch_id,
      phones: row.draft
        .map(entry => ({ number: (entry.number ?? '').trim(), type: entry.type }))
        .filter(entry => entry.number !== ''),
    });

    row.current = data.phones;
    row.draft = data.phones.map(entry => ({ ...entry }));
    row.done = true;
    useNotification().success(data.message || (t.value.facility?.phone_fix_saved || 'Phone numbers fixed'));
  } catch (error) {
    const validation = error?.response?.data?.errors;
    useNotification().error(
      (validation ? Object.values(validation)[0]?.[0] : null)
      || error?.response?.data?.message
      || (t.value.facility?.phone_fix_failed || 'Could not save the numbers. Please try again.'),
    );
  } finally {
    row.saving = false;
  }
};
</script>
