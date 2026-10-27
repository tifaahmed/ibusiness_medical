<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.loyalty?.title || 'Loyalty points & badges'"
        :breadcrumbs="[{ label: t.loyalty?.title || 'Loyalty points & badges', link: '#', active: true }]"
      />

      <div class="max-w-7xl mx-auto space-y-4">
        <!-- ===== Give points by hand ===== -->
        <section class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            <h1 class="text-lg font-semibold">{{ t.loyalty?.grant_title || 'Give loyalty points to a member' }}</h1>
          </div>

          <div class="p-3 space-y-3">
            <form class="flex flex-wrap items-center gap-2" @submit.prevent="searchMembers">
              <input v-model="searchText" type="search" :placeholder="t.loyalty?.search || 'Name, phone or card number'" class="h-8 w-64 rounded-md border border-border bg-background px-2 text-sm" />
              <button type="submit" class="h-8 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground cursor-pointer">{{ t.loyalty?.find || 'Find' }}</button>
            </form>

            <p v-if="search && !members.length" class="text-sm text-muted-foreground">{{ t.loyalty?.no_members || 'No member matches.' }}</p>

            <div v-for="member in members" :key="member.id" class="rounded-lg border border-border p-2.5 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold">{{ member.name || '—' }}</span>
                <span class="text-xs text-muted-foreground" dir="ltr">{{ member.phone }}</span>
                <span v-if="member.membership_number" class="font-mono text-xs text-muted-foreground">{{ member.membership_number }}</span>
                <span class="ms-auto text-sm tabular-nums">{{ t.loyalty?.balance || 'Balance' }}: <b :class="member.balance < 0 ? 'text-red-500' : 'text-emerald-500'" dir="ltr">{{ member.balance }}</b></span>
              </div>
              <form class="flex flex-wrap items-center gap-2" @submit.prevent="giveTo(member)">
                <input v-model.number="grantForm(member.id).points" type="number" step="1" :placeholder="t.loyalty?.points || 'Points (negative = take back)'" class="h-8 w-52 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" required />
                <input v-model="grantForm(member.id).note" type="text" maxlength="255" :placeholder="t.loyalty?.note || 'Note the member will see'" class="h-8 w-64 rounded-md border border-border bg-background px-2 text-sm" />
                <button type="submit" :disabled="grantForm(member.id).processing" class="h-8 rounded-md bg-emerald-600 px-3 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer">{{ t.loyalty?.give || 'Save points' }}</button>
              </form>
              <p v-if="grantForm(member.id).errors.points || grantForm(member.id).errors.note" class="text-xs text-red-500">{{ grantForm(member.id).errors.points || grantForm(member.id).errors.note }}</p>
            </div>

            <!-- Latest grants -->
            <div v-if="grants.length" class="pt-1">
              <h2 class="text-sm font-semibold mb-1">{{ t.loyalty?.latest || 'Latest grants' }}</h2>
              <div class="divide-y divide-border rounded-lg border border-border">
                <div v-for="g in grants" :key="g.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-2.5 py-1.5 text-sm">
                  <span class="font-semibold tabular-nums" :class="g.points < 0 ? 'text-red-500' : 'text-emerald-500'" dir="ltr">{{ g.points > 0 ? '+' : '' }}{{ g.points }}</span>
                  <span>{{ g.member_name || '—' }}</span>
                  <span class="text-xs text-muted-foreground" dir="ltr">{{ g.member_phone }}</span>
                  <span v-if="g.note" class="text-xs text-muted-foreground">{{ g.note }}</span>
                  <span class="ms-auto text-xs text-muted-foreground" dir="ltr">{{ g.at }}<template v-if="g.by"> · {{ g.by }}</template></span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ===== Badges ===== -->
        <section class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex flex-wrap items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow"><circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path></svg>
            <h2 class="text-lg font-semibold">{{ t.loyalty?.badges_title || 'Quiz badges' }}</h2>
            <span class="text-xs text-muted-foreground">{{ badges.length }}</span>
            <button type="button" class="ms-auto h-8 rounded-md border border-border px-3 text-sm hover:bg-muted cursor-pointer" @click="showCreate = !showCreate">+ {{ t.loyalty?.new_badge || 'New badge' }}</button>
          </div>
          <p class="px-3 pt-2 text-xs text-muted-foreground">{{ t.loyalty?.badges_help || 'A member earns a badge the moment their quiz record reaches its number, and gets its reward points once. Switch a badge off instead of deleting it: members who already hold it keep it.' }}</p>

          <div class="p-3 space-y-2">
            <!-- New badge -->
            <form v-if="showCreate" class="rounded-lg border border-dashed border-border p-2.5" @submit.prevent="createBadge">
              <BadgeFields :form="createForm" :sections="sections" :kinds="kinds" :labels="kindLabel" :t="t" />
              <div class="mt-2 flex items-center gap-2">
                <button type="submit" :disabled="createForm.processing" class="h-8 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50 cursor-pointer">{{ t.loyalty?.save || 'Save' }}</button>
                <p v-if="firstError(createForm)" class="text-xs text-red-500">{{ firstError(createForm) }}</p>
              </div>
            </form>

            <div v-if="!badges.length" class="p-8 text-center text-sm text-muted-foreground">{{ t.loyalty?.empty || 'No badges yet.' }}</div>

            <div v-for="badge in badges" :key="badge.id" class="rounded-lg border border-border p-2.5" :class="{ 'opacity-60': !badge.is_active }">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-2xl leading-none">{{ badge.icon || '🏅' }}</span>
                <div class="min-w-0">
                  <div class="text-sm font-semibold">{{ badge.title || kindLabel(badge.kind, badge.threshold) }}</div>
                  <div class="text-xs text-muted-foreground">{{ badge.quiz_key }} · {{ kindLabel(badge.kind, badge.threshold) }} · {{ t.loyalty?.reward || 'Reward' }} <b dir="ltr">{{ badge.reward_points }}</b></div>
                </div>
                <span v-if="!badge.is_active" class="rounded-md border border-zinc-500/30 bg-zinc-500/10 px-1.5 py-0.5 text-xs text-zinc-400">{{ t.loyalty?.off || 'Off' }}</span>
                <button type="button" class="ms-auto inline-flex items-center gap-1 rounded-md border border-border px-2 h-8 text-xs hover:bg-muted cursor-pointer" @click="toggleHolders(badge)">
                  👥 {{ badge.earned_count }} {{ t.loyalty?.holders || 'members' }}
                </button>
                <button type="button" class="h-8 rounded-md border border-border px-3 text-xs hover:bg-muted cursor-pointer" @click="startEdit(badge)">{{ t.loyalty?.edit || 'Edit' }}</button>
              </div>

              <form v-if="editingId === badge.id" class="mt-2 border-t border-border pt-2" @submit.prevent="saveBadge(badge)">
                <BadgeFields :form="editForm" :sections="sections" :kinds="kinds" :labels="kindLabel" :t="t" />
                <div class="mt-2 flex items-center gap-2">
                  <button type="submit" :disabled="editForm.processing" class="h-8 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-50 cursor-pointer">{{ t.loyalty?.save || 'Save' }}</button>
                  <button type="button" class="h-8 rounded-md border border-border px-3 text-sm hover:bg-muted cursor-pointer" @click="editingId = null">{{ t.loyalty?.cancel || 'Cancel' }}</button>
                  <p v-if="firstError(editForm)" class="text-xs text-red-500">{{ firstError(editForm) }}</p>
                </div>
              </form>

              <div v-if="holdersOpen === badge.id" class="mt-2 border-t border-border pt-2">
                <p v-if="holdersLoading" class="text-xs text-muted-foreground">…</p>
                <p v-else-if="!holders.length" class="text-xs text-muted-foreground">{{ t.loyalty?.nobody || 'Nobody holds this badge yet.' }}</p>
                <div v-else class="max-h-64 overflow-auto divide-y divide-border rounded-md border border-border">
                  <div v-for="h in holders" :key="h.user_id + h.earned_at" class="flex flex-wrap items-center gap-x-3 px-2 py-1 text-xs">
                    <span class="font-medium">{{ h.name || '—' }}</span>
                    <span class="text-muted-foreground" dir="ltr">{{ h.phone }}</span>
                    <span class="text-emerald-500" dir="ltr">+{{ h.reward_points }}</span>
                    <span class="ms-auto text-muted-foreground" dir="ltr">{{ h.earned_at }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, h, reactive, ref } from "vue";
import { AppLayout, Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  badges: { type: Array, default: () => [] },
  sections: { type: Array, default: () => [] },
  kinds: { type: Array, default: () => [] },
  search: { type: String, default: "" },
  members: { type: Array, default: () => [] },
  grants: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const kindWords = {
  solved: (n) => (t.value.loyalty?.kind_solved || "Right answers: :n").replace(":n", n),
  streak: (n) => (t.value.loyalty?.kind_streak || "Right in a row: :n").replace(":n", n),
  stage: (n) => (t.value.loyalty?.kind_stage || "Stage reached: :n").replace(":n", n),
};
const kindLabel = (kind, threshold) => (kindWords[kind] ? kindWords[kind](threshold) : `${kind} ${threshold}`);

/* ----- Give points ----- */
const searchText = ref(props.search);
const searchMembers = () => router.get(route("admin.loyalty.index"), { search: searchText.value }, { preserveState: true, replace: true });

const grantForms = reactive({});
const grantForm = (id) => (grantForms[id] ??= useForm({ user_id: id, points: null, note: "" }));
const giveTo = (member) => {
  const form = grantForm(member.id);
  form.post(route("admin.loyalty.grants.store"), {
    preserveScroll: true,
    onSuccess: (response) => {
      const flash = response.props.flash || {};
      if (flash.error) useNotification().error(flash.error);
      else useNotification().success(flash.success || "Saved");
      form.reset("points", "note");
    },
    onError: (errors) => reportClientError("Loyalty grant failed", { member_id: member.id, errors }),
  });
};

/* ----- Badges ----- */
const blank = () => ({ quiz_key: props.sections[0] || "", kind: props.kinds[0] || "solved", threshold: null, title: "", icon: "", reward_points: 0, is_active: true });
const showCreate = ref(false);
const createForm = useForm(blank());
const createBadge = () => {
  createForm.post(route("admin.loyalty.badges.store"), {
    preserveScroll: true,
    onSuccess: () => {
      useNotification().success("Badge created");
      createForm.defaults(blank()).reset();
      showCreate.value = false;
    },
    onError: (errors) => reportClientError("Badge create failed", { errors }),
  });
};

const editingId = ref(null);
const editForm = useForm(blank());
const startEdit = (badge) => {
  editingId.value = badge.id;
  editForm.defaults({ quiz_key: badge.quiz_key, kind: badge.kind, threshold: badge.threshold, title: badge.title || "", icon: badge.icon || "", reward_points: badge.reward_points, is_active: badge.is_active }).reset();
  editForm.clearErrors();
};
const saveBadge = (badge) => {
  editForm.put(route("admin.loyalty.badges.update", badge.id), {
    preserveScroll: true,
    onSuccess: () => {
      useNotification().success("Badge updated");
      editingId.value = null;
    },
    onError: (errors) => reportClientError("Badge update failed", { badge_id: badge.id, errors }),
  });
};
const firstError = (form) => Object.values(form.errors)[0] || "";

/* ----- Who holds a badge ----- */
const holdersOpen = ref(null);
const holders = ref([]);
const holdersLoading = ref(false);
const toggleHolders = async (badge) => {
  if (holdersOpen.value === badge.id) {
    holdersOpen.value = null;
    return;
  }
  holdersOpen.value = badge.id;
  holders.value = [];
  holdersLoading.value = true;
  try {
    const response = await fetch(route("admin.loyalty.badges.holders", badge.id), { headers: { Accept: "application/json" }, credentials: "same-origin" });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    holders.value = (await response.json()).data;
  } catch (error) {
    useNotification().error("Could not load the members.");
    reportClientError("Badge holders failed", { badge_id: badge.id, message: String(error) });
  } finally {
    holdersLoading.value = false;
  }
};

/* The fields of a badge form, shared by "new" and "edit". */
const BadgeFields = {
  props: { form: Object, sections: Array, kinds: Array, labels: Function, t: Object },
  setup(p) {
    const input = "h-8 rounded-md border border-border bg-background px-2 text-sm";
    const field = (label, child) => h("label", { class: "flex flex-col gap-0.5 text-xs text-muted-foreground" }, [label, child]);
    return () => {
      const f = p.form;
      const L = p.t.loyalty || {};
      return h("div", { class: "grid gap-2 sm:grid-cols-2 lg:grid-cols-4" }, [
        field(L.f_section || "Section", h("select", { class: input, value: f.quiz_key, onChange: (e) => (f.quiz_key = e.target.value) }, p.sections.map((s) => h("option", { value: s }, s)))),
        field(L.f_kind || "Earned by", h("select", { class: input, value: f.kind, onChange: (e) => (f.kind = e.target.value) }, p.kinds.map((k) => h("option", { value: k }, p.labels(k, "N"))))),
        field(L.f_threshold || "Number to reach", h("input", { class: input, type: "number", min: 1, dir: "ltr", value: f.threshold, onInput: (e) => (f.threshold = e.target.value === "" ? null : Number(e.target.value)), required: true })),
        field(L.f_reward || "Reward points", h("input", { class: input, type: "number", min: 0, dir: "ltr", value: f.reward_points, onInput: (e) => (f.reward_points = Number(e.target.value || 0)), required: true })),
        field(L.f_title || "Title shown to the member", h("input", { class: input, type: "text", maxlength: 120, value: f.title, onInput: (e) => (f.title = e.target.value) })),
        field(L.f_icon || "Icon (an emoji)", h("input", { class: input, type: "text", maxlength: 16, value: f.icon, onInput: (e) => (f.icon = e.target.value) })),
        field(L.f_active || "Active", h("select", { class: input, value: f.is_active ? "1" : "0", onChange: (e) => (f.is_active = e.target.value === "1") }, [h("option", { value: "1" }, L.on || "On"), h("option", { value: "0" }, L.off || "Off")])),
      ]);
    };
  },
};

/* AGENTS.md: front-end failures go to the client-error endpoint. */
function reportClientError(message, extra) {
  try {
    fetch("/api/v1/client-errors", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ message, route: window.location.pathname, fatal: false, extra }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}
</script>
