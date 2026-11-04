<template>
  <form class="space-y-2" @submit.prevent="submit" data-testid="delivery-form">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
      <select v-model="form.courier_id" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
        <option value="">{{ t.card_deliveries?.no_courier || 'Courier (none)' }}</option>
        <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}{{ c.is_active ? '' : ' (off)' }}</option>
      </select>
      <select v-model="form.status" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
        <option v-for="s in ['scheduled', 'delivered', 'failed']" :key="s" :value="s">{{ statusLabel(s) }}</option>
      </select>
      <input v-model="form.delivered_at" type="date" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
      <input v-model.number="form.cards_count" type="number" min="1" max="100" :placeholder="t.card_deliveries?.cards_count || 'How many cards'" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
      <input v-model="form.card_numbers" type="text" :placeholder="t.card_deliveries?.card_numbers || 'Card numbers (optional)'" class="h-9 rounded-md border border-border bg-background px-2 text-sm sm:col-span-2 lg:col-span-4" dir="ltr" />
    </div>
    <textarea v-model="form.notes" rows="2" maxlength="3000" :placeholder="t.card_deliveries?.notes || 'Notes about this delivery'" class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm" />
    <label class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
      <span>{{ t.card_deliveries?.photos || 'Photos of the delivered cards' }}</span>
      <input type="file" accept="image/*" multiple class="text-xs max-w-full" @change="form.photos = Array.from($event.target.files)" />
    </label>
    <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
    <div class="flex flex-wrap gap-2">
      <button type="submit" :disabled="busy" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">{{ delivery ? (t.card_deliveries?.save || 'Save') : (t.card_deliveries?.add || 'Record delivery') }}</button>
      <button v-if="delivery" type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="$emit('done')">{{ t.card_deliveries?.cancel || 'Cancel' }}</button>
    </div>
  </form>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  delivery: { type: Object, default: null },
  couriers: { type: Array, default: () => [] },
  userId: { type: Number, default: null },
});
const emit = defineEmits(['done']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const statusLabel = (s) => t.value.card_deliveries?.['status_' + s] || { scheduled: 'Sent with courier', delivered: 'Delivered', failed: 'Not delivered' }[s];

const form = reactive({
  courier_id: props.delivery?.courier_id || '',
  status: props.delivery?.status || 'delivered',
  delivered_at: props.delivery?.delivered_at || new Date().toISOString().slice(0, 10),
  cards_count: props.delivery?.cards_count || 1,
  card_numbers: props.delivery?.card_numbers || '',
  notes: props.delivery?.notes || '',
  photos: [],
});
const error = ref('');
const busy = ref(false);

const submit = () => {
  error.value = '';
  busy.value = true;
  const data = { ...form, courier_id: form.courier_id || '' };
  if (!props.delivery) data.user_id = props.userId;
  const url = props.delivery ? route('admin.card-deliveries.update', props.delivery.id) : route('admin.card-deliveries.store');
  router.post(url, data, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { form.photos = []; if (!props.delivery) { form.notes = ''; form.card_numbers = ''; } emit('done'); },
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not save.'; },
    onFinish: () => { busy.value = false; },
  });
};
</script>
