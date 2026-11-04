<template>
  <div class="px-3 py-2.5 text-sm space-y-1.5" data-testid="note-row">
    <div class="flex flex-wrap items-center gap-2">
      <span class="chip chip-neutral">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        {{ note.author }}
      </span>
      <span class="text-xs font-medium text-foreground/80" dir="ltr">{{ note.created_at }}</span>
      <span v-if="note.edited" class="chip chip-warning">{{ t.member_notes?.edited || 'edited' }}</span>
      <template v-if="showMember && note.member">
        <Link :href="route('admin.user.membership.show', note.member.slug)" class="font-medium hover:underline">{{ note.member.name }}</Link>
        <span v-if="note.member.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ note.member.membership_number }}</span>
        <span v-if="note.member.phone" class="text-xs text-foreground/80" dir="ltr">{{ note.member.phone }}</span>
      </template>
      <span v-if="note.mine" class="ms-auto flex flex-wrap gap-2">
        <button v-if="!editing" type="button" class="text-xs text-blue-400 hover:underline min-w-[3rem]" @click="startEdit">{{ t.member_notes?.edit || 'Edit' }}</button>
        <button v-if="!editing" type="button" class="text-xs text-red-400 hover:underline min-w-[3rem]" @click="remove">{{ t.member_notes?.delete || 'Delete' }}</button>
      </span>
    </div>

    <p v-if="!editing" class="whitespace-pre-line break-words text-foreground">{{ note.body }}</p>
    <form v-else class="space-y-2" @submit.prevent="save">
      <textarea v-model="draft" rows="3" maxlength="5000" class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm" required />
      <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
      <div class="flex flex-wrap gap-2">
        <button type="submit" class="h-8 min-w-[6rem] rounded-md bg-primary px-3 text-sm text-primary-foreground">{{ t.member_notes?.save || 'Save' }}</button>
        <button type="button" class="h-8 min-w-[6rem] rounded-md border border-border px-3 text-sm" @click="editing = false">{{ t.member_notes?.cancel || 'Cancel' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  note: { type: Object, required: true },
  showMember: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const editing = ref(false);
const draft = ref('');
const error = ref('');

const startEdit = () => { draft.value = props.note.body; error.value = ''; editing.value = true; };
const save = () => {
  router.put(route('admin.member-notes.update', props.note.id), { body: draft.value }, {
    preserveScroll: true,
    onSuccess: () => { editing.value = false; },
    onError: (e) => { error.value = e.body || Object.values(e)[0] || 'Could not save.'; },
  });
};
const remove = () => {
  if (window.confirm(t.value.member_notes?.confirm_delete || 'Delete this note?')) {
    router.delete(route('admin.member-notes.destroy', props.note.id), { preserveScroll: true });
  }
};
</script>
