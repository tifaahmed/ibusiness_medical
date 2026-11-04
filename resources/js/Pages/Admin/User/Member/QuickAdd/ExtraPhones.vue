<template>
  <section class="rounded-md border border-border p-3 space-y-2" data-testid="extra-phones">
    <h4 class="text-sm font-semibold">{{ t.extra_phones || 'Other numbers (notes only)' }}</h4>
    <p class="text-xs text-muted-foreground">{{ t.extra_phones_hint || 'Kept on the member as a note, e.g. the number that has WhatsApp. Not used to sign in and not used for SMS.' }}</p>
    <p v-if="!phones.length" class="text-xs text-muted-foreground">{{ t.none_yet || 'None yet.' }}</p>
    <ul v-else class="space-y-1">
      <li v-for="p in phones" :key="p.id" class="flex flex-wrap items-center gap-2 text-sm" data-testid="extra-phone-row">
        <span class="chip chip-neutral tabular-nums" dir="ltr">{{ p.phone }}</span>
        <span v-if="p.note" class="text-xs text-muted-foreground">{{ p.note }}</span>
        <button type="button" class="ms-auto text-xs underline text-red-400" @click="remove(p)">{{ t.remove || 'Remove' }}</button>
      </li>
    </ul>
    <form class="flex flex-wrap gap-2" @submit.prevent="add">
      <input v-model="phone" inputmode="tel" dir="ltr" :placeholder="t.phone || 'Phone'" class="h-11 min-w-[10rem] flex-1 rounded-md border border-border bg-background px-3 text-base" data-testid="extra-phone-input" />
      <input v-model="note" :placeholder="t.note || 'Note (optional)'" class="h-11 min-w-[8rem] flex-1 rounded-md border border-border bg-background px-3 text-base" />
      <button type="submit" :disabled="!phone.trim() || busy" class="h-11 min-w-[6rem] rounded-md border border-border px-4 text-sm disabled:opacity-50" data-testid="extra-phone-add">{{ t.add || 'Add' }}</button>
    </form>
    <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({ slug: { type: String, required: true }, phones: { type: Array, default: () => [] } });
const page = usePage();
const t = computed(() => ({ ...(page.props.translations?.admin?.quick_add || {}) }));
const phone = ref('');
const note = ref('');
const busy = ref(false);
const error = ref('');

const add = () => {
  busy.value = true; error.value = '';
  router.post(route('admin.user.membership.extra-phones.store', props.slug), { phone: phone.value, note: note.value }, {
    preserveScroll: true,
    onSuccess: () => { phone.value = ''; note.value = ''; },
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not save.'; },
    onFinish: () => { busy.value = false; },
  });
};
const remove = (p) => router.delete(route('admin.user.membership.extra-phones.destroy', [props.slug, p.id]), { preserveScroll: true });
</script>
