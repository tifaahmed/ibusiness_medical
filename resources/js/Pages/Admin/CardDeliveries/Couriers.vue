<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.card_deliveries?.couriers || 'Delivery couriers'"
        :breadcrumbs="[{ label: t.card_deliveries?.title || 'Card deliveries', link: route('admin.card-deliveries.index') }, { label: t.card_deliveries?.couriers || 'Delivery couriers', link: '#', active: true }]"
      />
      <div class="max-w-4xl mx-auto space-y-4">
        <form v-if="canManage" class="bg-card rounded-xl border border-border shadow-sm p-3 grid grid-cols-1 sm:grid-cols-2 gap-2" data-testid="courier-add" @submit.prevent="add">
          <input v-model="form.name" required maxlength="150" :placeholder="t.card_deliveries?.courier_name || 'Courier name'" class="h-9 rounded-md border border-border bg-background px-2 text-sm" />
          <input v-model="form.phone" maxlength="30" :placeholder="t.card_deliveries?.courier_phone || 'Phone'" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
          <textarea v-model="form.notes" rows="2" :placeholder="t.card_deliveries?.courier_notes || 'Notes (area, hours…)'" class="rounded-md border border-border bg-background px-2 py-1.5 text-sm sm:col-span-2" />
          <p v-if="error" class="text-xs text-red-400 sm:col-span-2">{{ error }}</p>
          <button type="submit" class="h-9 min-w-[8rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground sm:col-span-2 sm:w-fit">{{ t.card_deliveries?.add_courier || 'Add courier' }}</button>
        </form>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <p v-if="!couriers.length" class="p-10 text-center text-sm text-muted-foreground" data-testid="couriers-empty">{{ t.card_deliveries?.no_couriers || 'No couriers yet.' }}</p>
          <div v-else class="divide-y divide-border">
            <div v-for="c in couriers" :key="c.id" class="px-3 py-2.5 text-sm space-y-2" data-testid="courier-row">
              <div v-if="editId !== c.id" class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ c.name }}</span>
                <span v-if="c.phone" class="text-xs text-foreground/80" dir="ltr">{{ c.phone }}</span>
                <span class="chip" :class="c.is_active ? 'chip-positive' : 'chip-neutral'">{{ c.is_active ? (t.card_deliveries?.active || 'Active') : (t.card_deliveries?.inactive || 'Inactive') }}</span>
                <span class="chip chip-accent tabular-nums">{{ c.deliveries_count }} {{ t.card_deliveries?.deliveries || 'deliveries' }}</span>
                <span v-if="c.notes" class="basis-full text-xs text-foreground/80">{{ c.notes }}</span>
                <span v-if="canManage" class="ms-auto flex flex-wrap gap-2">
                  <button type="button" class="text-xs text-blue-400 hover:underline min-w-[3rem]" @click="startEdit(c)">{{ t.card_deliveries?.edit || 'Edit' }}</button>
                  <button type="button" class="text-xs text-red-400 hover:underline min-w-[3rem]" @click="remove(c)">{{ t.card_deliveries?.delete || 'Delete' }}</button>
                </span>
              </div>
              <form v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2" @submit.prevent="save(c)">
                <input v-model="draft.name" required maxlength="150" class="h-9 rounded-md border border-border bg-background px-2 text-sm" />
                <input v-model="draft.phone" maxlength="30" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
                <textarea v-model="draft.notes" rows="2" class="rounded-md border border-border bg-background px-2 py-1.5 text-sm sm:col-span-2" />
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input v-model="draft.is_active" type="checkbox" /> {{ t.card_deliveries?.active || 'Active' }}</label>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                  <button type="submit" class="h-9 min-w-[6rem] rounded-md bg-primary px-3 text-sm text-primary-foreground">{{ t.card_deliveries?.save || 'Save' }}</button>
                  <button type="button" class="h-9 min-w-[6rem] rounded-md border border-border px-3 text-sm" @click="editId = null">{{ t.card_deliveries?.cancel || 'Cancel' }}</button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';

defineProps({
  couriers: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({ name: '', phone: '', notes: '' });
const error = ref('');
const add = () => {
  error.value = '';
  router.post(route('admin.delivery-couriers.store'), { ...form }, {
    preserveScroll: true,
    onSuccess: () => Object.assign(form, { name: '', phone: '', notes: '' }),
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not save.'; },
  });
};

const editId = ref(null);
const draft = reactive({ name: '', phone: '', notes: '', is_active: true });
const startEdit = (c) => { Object.assign(draft, { name: c.name, phone: c.phone || '', notes: c.notes || '', is_active: c.is_active }); editId.value = c.id; };
const save = (c) => router.put(route('admin.delivery-couriers.update', c.id), { ...draft }, { preserveScroll: true, onSuccess: () => { editId.value = null; } });
const remove = (c) => { if (window.confirm(t.value.card_deliveries?.confirm_delete_courier || 'Delete this courier?')) router.delete(route('admin.delivery-couriers.destroy', c.id), { preserveScroll: true }); };
</script>
