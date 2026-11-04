<template>
  <div class="px-3 py-3 text-sm space-y-2" data-testid="delivery-row">
    <div class="flex flex-wrap items-center gap-2">
      <span class="chip" :class="{ 'chip-positive': d.status === 'delivered', 'chip-warning': d.status === 'scheduled', 'chip-negative': d.status === 'failed' }">{{ statusLabel(d.status) }}</span>
      <span class="chip chip-accent tabular-nums" dir="ltr">{{ d.cards_count }} {{ t.card_deliveries?.cards || 'cards' }}</span>
      <span v-if="d.courier" class="chip chip-info">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        {{ d.courier.name }}<span v-if="d.courier.phone" dir="ltr"> · {{ d.courier.phone }}</span>
      </span>
      <span v-else class="chip chip-neutral">{{ t.card_deliveries?.no_courier_short || 'No courier' }}</span>
      <span v-if="d.delivered_at" class="text-xs font-medium text-foreground/80" dir="ltr">{{ d.delivered_at }}</span>
      <template v-if="showMember && d.member">
        <Link :href="route('admin.user.membership.show', d.member.slug)" class="font-medium hover:underline">{{ d.member.name }}</Link>
        <span v-if="d.member.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ d.member.membership_number }}</span>
        <span v-if="d.member.phone" class="text-xs text-foreground/80" dir="ltr">{{ d.member.phone }}</span>
      </template>
      <span v-if="canManage" class="ms-auto flex flex-wrap gap-2">
        <button type="button" class="text-xs text-blue-400 hover:underline min-w-[3rem]" @click="editing = !editing">{{ t.card_deliveries?.edit || 'Edit' }}</button>
        <button type="button" class="text-xs text-red-400 hover:underline min-w-[3rem]" @click="remove">{{ t.card_deliveries?.delete || 'Delete' }}</button>
      </span>
    </div>
    <p v-if="d.card_numbers" class="text-xs text-foreground/80" dir="ltr">{{ d.card_numbers }}</p>
    <p v-if="d.recorded_by" class="text-xs text-muted-foreground">{{ t.card_deliveries?.recorded_by || 'Recorded by' }}: {{ d.recorded_by }} · <span dir="ltr">{{ d.created_at }}</span></p>

    <DeliveryForm v-if="editing" :delivery="d" :couriers="couriers" @done="editing = false" />
    <p v-else-if="d.notes" class="whitespace-pre-line break-words">{{ d.notes }}</p>

    <div v-if="d.photos.length" class="flex flex-wrap gap-2">
      <div v-for="p in d.photos" :key="p.id" class="relative">
        <a :href="p.url" target="_blank" rel="noopener"><img :src="p.url" alt="" class="h-20 w-28 rounded-md border border-border object-cover" loading="lazy" /></a>
        <button v-if="canManage && editing" type="button" class="absolute -top-1 -end-1 h-5 w-5 rounded-full bg-red-600 text-xs leading-5 text-white" :title="t.card_deliveries?.remove_photo || 'Remove photo'" @click="removePhoto(p.id)">×</button>
      </div>
    </div>

    <!-- Notes on this delivery: any number, each with who wrote it -->
    <div class="space-y-1.5 rounded-lg border border-border p-2">
      <div v-for="n in d.note_lines" :key="n.id" class="text-xs space-y-1">
        <template v-if="editingNote === n.id">
          <textarea v-model="noteDraft" rows="2" class="w-full rounded-md border border-border bg-background px-2 py-1 text-sm" />
          <div class="flex flex-wrap gap-2">
            <button type="button" class="h-7 min-w-[5rem] rounded-md bg-primary px-3 text-xs text-primary-foreground" @click="saveNote(n)">{{ t.card_deliveries?.save || 'Save' }}</button>
            <button type="button" class="h-7 min-w-[5rem] rounded-md border border-border px-3 text-xs" @click="editingNote = null">{{ t.card_deliveries?.cancel || 'Cancel' }}</button>
          </div>
        </template>
        <div v-else class="flex flex-wrap items-baseline gap-x-2">
          <span class="chip chip-neutral">{{ n.author }}</span>
          <span class="text-foreground/80" dir="ltr">{{ n.at }}</span>
          <span class="basis-full whitespace-pre-line break-words text-sm text-foreground">{{ n.body }}</span>
          <template v-if="canManage">
            <button type="button" class="text-blue-400 hover:underline" @click="editingNote = n.id; noteDraft = n.body">{{ t.card_deliveries?.edit || 'Edit' }}</button>
            <button type="button" class="text-red-400 hover:underline" @click="removeNote(n)">{{ t.card_deliveries?.delete || 'Delete' }}</button>
          </template>
        </div>
      </div>
      <form v-if="canManage" class="flex flex-wrap gap-2" @submit.prevent="addNote">
        <input v-model="newNote" type="text" maxlength="3000" :placeholder="t.card_deliveries?.add_note || 'Add a note on this delivery'" class="h-8 min-w-[10rem] flex-1 rounded-md border border-border bg-background px-2 text-sm" />
        <button type="submit" :disabled="!newNote.trim()" class="h-8 min-w-[5rem] rounded-md bg-primary px-3 text-xs text-primary-foreground disabled:opacity-50">{{ t.card_deliveries?.add_note_btn || 'Add' }}</button>
      </form>
      <p v-if="!d.note_lines.length && !canManage" class="text-xs text-muted-foreground">—</p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import DeliveryForm from './DeliveryForm.vue';

const props = defineProps({
  d: { type: Object, required: true },
  couriers: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  showMember: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const statusLabel = (s) => t.value.card_deliveries?.['status_' + s] || { scheduled: 'Sent with courier', delivered: 'Delivered', failed: 'Not delivered' }[s];

const editing = ref(false);
const newNote = ref('');
const editingNote = ref(null);
const noteDraft = ref('');

const opts = { preserveScroll: true };
const remove = () => { if (window.confirm(t.value.card_deliveries?.confirm_delete || 'Delete this delivery?')) router.delete(route('admin.card-deliveries.destroy', props.d.id), opts); };
const removePhoto = (id) => router.delete(route('admin.card-deliveries.photos.destroy', [props.d.id, id]), opts);
const addNote = () => router.post(route('admin.card-deliveries.notes.store', props.d.id), { body: newNote.value }, { ...opts, onSuccess: () => { newNote.value = ''; } });
const saveNote = (n) => router.put(route('admin.card-delivery-notes.update', n.id), { body: noteDraft.value }, { ...opts, onSuccess: () => { editingNote.value = null; } });
const removeNote = (n) => { if (window.confirm(t.value.card_deliveries?.confirm_delete_note || 'Delete this note?')) router.delete(route('admin.card-delivery-notes.destroy', n.id), opts); };
</script>
