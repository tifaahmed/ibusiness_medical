<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 h-8 text-sm transition hover:bg-muted hover:border-blue-500/40 cursor-pointer"
    :title="`Show the ${count} facility/facilities in this governorate`"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-blue-500">
      <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
      <path d="M9 22v-4h6v4"></path>
      <path d="M8 6h.01"></path><path d="M16 6h.01"></path><path d="M12 6h.01"></path>
      <path d="M12 10h.01"></path><path d="M12 14h.01"></path><path d="M16 10h.01"></path>
      <path d="M16 14h.01"></path><path d="M8 10h.01"></path><path d="M8 14h.01"></path>
    </svg>
    <span class="font-medium">{{ count }}</span>
  </button>

  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="closeDialog"
    >
      <div class="flex w-full max-w-2xl max-h-[85vh] flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="min-w-0">
            <h2 class="text-base font-semibold">Facilities in {{ governorateName }}</h2>
            <p class="text-xs text-muted-foreground">
              Every facility based in this governorate or with a branch here. Branches in this governorate are listed first.
            </p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Close (Esc)"
            @click="closeDialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
          <p v-if="loading" class="py-8 text-center text-sm text-muted-foreground">Loading…</p>
          <p v-else-if="errorMessage" class="py-8 text-center text-sm text-destructive">{{ errorMessage }}</p>
          <p v-else-if="facilities.length === 0" class="py-8 text-center text-sm text-muted-foreground">
            No facilities are registered in this governorate yet.
          </p>

          <ul v-else class="space-y-3">
            <li
              v-for="facility in facilities"
              :key="facility.id"
              class="rounded-lg border border-border p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-sm break-words">{{ tr(facility.name) }}</p>
                  <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                    <span
                      v-if="tr(facility.city)"
                      class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600"
                    >{{ tr(facility.city) }}</span>
                    <span v-if="facility.facility_type" class="text-xs text-muted-foreground">
                      {{ tr(facility.facility_type) }}
                    </span>
                  </div>
                </div>
                <a
                  :href="route('admin.facility.show', facility.slug)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border bg-background px-3 h-8 text-xs font-medium transition hover:bg-primary hover:text-primary-foreground"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  Show
                </a>
              </div>

              <ul v-if="facility.branches.length" class="mt-2 space-y-1.5 border-t border-border/60 pt-2">
                <li
                  v-for="branch in facility.branches"
                  :key="branch.id"
                  class="flex items-start gap-2 text-xs"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 w-3.5 h-3.5 shrink-0 text-emerald-500">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span class="font-medium">{{ tr(branch.name) || '—' }}</span>
                      <span
                        v-if="tr(branch.city)"
                        class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600"
                      >{{ tr(branch.city) }}</span>
                      <span
                        v-if="branch.other_governorate"
                        class="rounded bg-amber-500/15 px-1 py-0.5 text-[10px] text-amber-600"
                        :title="`This branch is located in ${tr(branch.governorate) || 'another governorate'}`"
                      >{{ tr(branch.governorate) || 'other governorate' }}</span>
                    </div>
                    <p v-if="tr(branch.address)" class="text-muted-foreground break-words">{{ tr(branch.address) }}</p>
                  </div>
                </li>
              </ul>
              <p v-else class="mt-2 border-t border-border/60 pt-2 text-xs text-muted-foreground">No branches.</p>
            </li>
          </ul>
        </div>

        <div class="flex justify-end border-t border-border p-3">
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted"
            @click="closeDialog"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  governorate: { type: Object, required: true },
});

const page = usePage();
const locale = page.props.locale || "ar";

const open = ref(false);
const loading = ref(false);
const errorMessage = ref("");
const facilities = ref([]);

const count = computed(() => props.governorate.facilities_count || 0);
const governorateName = computed(() => tr(props.governorate.name));

const tr = (value) => {
  if (!value) return "";
  if (typeof value === "string") return value;
  return value[locale] || value.ar || value.en || Object.values(value)[0] || "";
};

const openDialog = async () => {
  open.value = true;

  if (facilities.value.length || loading.value) return;

  loading.value = true;
  errorMessage.value = "";
  try {
    const { data } = await axios.get(route("admin.governorate.facilities", props.governorate.slug));
    facilities.value = data.facilities || [];
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || "Could not load the facilities. Please try again.";
  } finally {
    loading.value = false;
  }
};

const closeDialog = () => {
  open.value = false;
};
</script>
