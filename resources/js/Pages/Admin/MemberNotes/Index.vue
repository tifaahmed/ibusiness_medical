<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.member_notes?.title || 'Member notes'"
        :breadcrumbs="[{ label: t.member_notes?.title || 'Member notes', link: '#', active: true }]"
      />

      <div class="max-w-5xl mx-auto space-y-4">
        <!-- Add a note: pick the member, write it -->
        <form v-if="canAdd" class="bg-card rounded-xl border border-border shadow-sm p-3 space-y-2" data-testid="note-add" @submit.prevent="add">
          <h3 class="flex items-center gap-2 text-sm font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            {{ t.member_notes?.add_title || 'Add a note about a member' }}
          </h3>
          <div class="relative">
            <input v-if="!picked" v-model="memberQuery" type="search" :placeholder="t.member_notes?.find_member || 'Find the member: name, phone or card number'" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm" data-testid="note-member-search" @input="searchMembers" />
            <div v-else class="flex flex-wrap items-center gap-2 text-sm">
              <span class="chip chip-accent">{{ picked.name }}</span>
              <span v-if="picked.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ picked.membership_number }}</span>
              <button type="button" class="text-xs text-blue-400 hover:underline" @click="picked = null">{{ t.member_notes?.change_member || 'Change' }}</button>
            </div>
            <ul v-if="!picked && options.length" class="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-md border border-border bg-card shadow-lg">
              <li v-for="o in options" :key="o.id">
                <button type="button" class="flex w-full flex-wrap items-center gap-2 px-3 py-2 text-start text-sm hover:bg-muted" @click="pick(o)">
                  <span class="font-medium">{{ o.name }}</span>
                  <span v-if="o.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ o.membership_number }}</span>
                  <span v-if="o.phone" class="text-xs text-foreground/80" dir="ltr">{{ o.phone }}</span>
                </button>
              </li>
            </ul>
          </div>
          <textarea v-model="body" rows="3" maxlength="5000" :placeholder="t.member_notes?.placeholder || 'Write the note…'" class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm" data-testid="note-body" />
          <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
          <button type="submit" :disabled="!picked || !body.trim()" class="h-9 min-w-[8rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">{{ t.member_notes?.add || 'Add note' }}</button>
        </form>

        <form class="bg-card rounded-xl border border-border shadow-sm p-3 grid grid-cols-1 sm:grid-cols-3 gap-3" @submit.prevent="apply">
          <input v-model="form.search" type="search" :placeholder="t.member_notes?.search || 'Search note, member name, phone or card'" class="h-9 rounded-md border border-border bg-background px-2 text-sm sm:col-span-2" />
          <select v-model="form.author_id" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.member_notes?.all_authors || 'Every author' }}</option>
            <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <div class="flex flex-wrap gap-2 sm:col-span-3">
            <button type="submit" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">{{ t.member_notes?.filter || 'Filter' }}</button>
            <button type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="reset">{{ t.member_notes?.reset || 'Reset' }}</button>
          </div>
        </form>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <div v-if="!rows.length" class="flex flex-col items-center gap-2 p-10 text-muted-foreground" data-testid="notes-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
            <p class="text-sm">{{ t.member_notes?.empty || 'No notes yet.' }}</p>
          </div>
          <div v-else class="divide-y divide-border">
            <NoteItem v-for="n in rows" :key="n.id" :note="n" show-member />
          </div>
        </section>

        <nav v-if="meta.last_page > 1" class="flex flex-wrap gap-1 justify-center">
          <template v-for="(l, i) in meta.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="min-w-[2.25rem] h-9 inline-flex items-center justify-center rounded-md border border-border px-2 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-background'" v-html="l.label" />
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import NoteItem from './NoteItem.vue';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ last_page: 1, links: [] }) },
  filters: { type: Object, default: () => ({}) },
  authors: { type: Array, default: () => [] },
  canAdd: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({ search: props.filters.search || '', author_id: props.filters.author_id || '' });
const apply = () => {
  const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
  router.get(route('admin.member-notes.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const reset = () => { Object.assign(form, { search: '', author_id: '' }); apply(); };

const memberQuery = ref('');
const options = ref([]);
const picked = ref(null);
const body = ref('');
const error = ref('');
let timer = null;

const searchMembers = () => {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    if (memberQuery.value.trim().length < 2) { options.value = []; return; }
    try {
      const r = await fetch(route('admin.member-notes.members', { q: memberQuery.value }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      options.value = r.ok ? await r.json() : [];
    } catch (e) { options.value = []; }
  }, 250);
};
const pick = (o) => { picked.value = o; options.value = []; memberQuery.value = ''; };
const add = () => {
  error.value = '';
  router.post(route('admin.member-notes.store'), { user_id: picked.value.id, body: body.value }, {
    preserveScroll: true,
    onSuccess: () => { body.value = ''; picked.value = null; },
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not save.'; },
  });
};
</script>
