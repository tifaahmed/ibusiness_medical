<template>
  <div class="mt-2 space-y-2 text-xs" data-testid="phone-conflict">
    <button type="button" class="rounded border border-border px-2 py-1 hover:bg-muted" data-testid="phone-conflict-open" @click="toggle">
      {{ open ? m('phone_conflict_hide', 'Hide the other person') : m('phone_conflict_view', 'View the other person') }}
    </button>

    <div v-if="open" class="rounded-md border border-border bg-muted/40 p-3 space-y-3">
      <p v-if="loading" class="text-muted-foreground">{{ m('checking', 'Checking…') }}</p>
      <p v-else-if="loadError" class="value-negative">{{ loadError }}</p>
      <p v-else-if="done" class="value-positive font-medium" data-testid="phone-conflict-done">{{ done }}</p>

      <template v-else-if="holder">
        <div class="flex items-start gap-3">
          <img v-if="holder.avatar_url" :src="holder.avatar_url" :alt="holder.name" class="w-12 h-12 rounded-full object-cover flex-shrink-0" />
          <div class="min-w-0 space-y-1">
            <div class="font-semibold text-sm truncate" :title="holder.name">{{ holder.name }}</div>
            <div v-if="holder.email" class="text-muted-foreground truncate">{{ holder.email }}</div>
            <div class="font-mono text-muted-foreground">{{ holder.phone }}</div>
            <div class="flex flex-wrap gap-1">
              <span v-for="c in holder.takeover?.cards || []" :key="c.id" class="chip chip-neutral">
                {{ c.is_relative ? m('relative', 'Relative') : m('membership_number', 'Membership #') }} {{ c.membership_number }}
              </span>
              <span v-if="holder.takeover?.can_take_over && !(holder.takeover.cards || []).length" class="chip chip-warning">{{ m('phone_conflict_no_card', 'No membership — just a person') }}</span>
            </div>
            <a v-if="holder.slug && holder.takeover?.cards?.length" :href="route('admin.user.membership.edit', holder.slug)" target="_blank" rel="noopener" class="underline">{{ m('phone_conflict_open_profile', 'Open their profile') }}</a>
          </div>
        </div>

        <p v-if="!holder.takeover?.can_take_over" class="text-muted-foreground">{{ m('phone_conflict_out_of_scope', 'This person is outside your permissions, so their phone cannot be taken.') }}</p>
        <p v-else-if="holder.takeover.is_staff" class="text-muted-foreground">{{ m('phone_conflict_staff', 'This is a staff account; its phone cannot be taken from here.') }}</p>

        <div v-else class="space-y-3">
          <p v-if="actionError" class="value-negative">{{ actionError }}</p>

          <div class="space-y-1">
            <p class="font-medium">{{ m('phone_conflict_change_title', 'Give them another phone and use this one') }}</p>
            <div class="flex flex-wrap gap-2">
              <input v-model="newPhone" type="tel" inputmode="numeric" maxlength="11" class="h-9 w-44 rounded-md border border-border bg-background px-2 font-mono" :placeholder="m('phone_conflict_new_phone', 'Their new phone (01…)')" data-testid="phone-conflict-new" @input="newPhone = newPhone.replace(/\D/g, '')" />
              <button type="button" class="rounded-md border border-border h-9 px-3 min-w-[9rem] hover:bg-muted disabled:opacity-60" :disabled="busy || newPhone.length !== 11" data-testid="phone-conflict-change" @click="confirmThen('change')">
                {{ m('phone_conflict_change', 'Change their phone') }}
              </button>
            </div>
          </div>

          <div class="space-y-1">
            <p class="font-medium">{{ m('phone_conflict_clear_title', 'Take the phone and leave them without one') }}</p>
            <button type="button" class="rounded-md border border-border h-9 px-3 min-w-[9rem] hover:bg-muted disabled:opacity-60" :disabled="busy" data-testid="phone-conflict-clear" @click="confirmThen('clear')">
              {{ m('phone_conflict_clear', 'Remove their phone') }}
            </button>
            <p v-if="(holder.takeover.cards || []).some((c) => !c.is_relative)" class="chip chip-warning">{{ m('phone_conflict_signin_warning', 'They sign in with this phone, so they will not be able to until it is replaced.') }}</p>
          </div>

          <div v-if="holder.takeover.can_delete" class="space-y-1">
            <p class="font-medium">{{ m('phone_conflict_delete_title', 'They have no membership — delete the person') }}</p>
            <button type="button" class="rounded-md bg-destructive text-white h-9 px-3 min-w-[9rem] disabled:opacity-60" :disabled="busy" data-testid="phone-conflict-delete" @click="confirmThen('delete')">
              {{ m('phone_conflict_delete', 'Delete the person') }}
            </button>
            <p class="text-muted-foreground">{{ m('phone_conflict_delete_note', 'They go to the trash; nothing is erased for good.') }}</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  phone: { type: String, required: true },
  excludeUserId: { type: [Number, String], default: null },
  excludeMembershipId: { type: [Number, String], default: null },
});
const emit = defineEmits(['released']);

const page = usePage();
const tm = computed(() => page.props.translations?.admin?.member || {});
const m = (key, fallback) => tm.value[key] || fallback;

const open = ref(false);
const loading = ref(false);
const loadError = ref('');
const holder = ref(null);
const busy = ref(false);
const done = ref('');
const actionError = ref('');
const newPhone = ref('');

async function load() {
  loading.value = true;
  loadError.value = '';
  done.value = '';
  try {
    const params = { field: 'phone', value: props.phone };
    if (props.excludeMembershipId) params.exclude_membership_id = props.excludeMembershipId;
    if (props.excludeUserId) params.exclude_user_id = props.excludeUserId;
    const { data } = await axios.get('/api/member-field/check', { params });
    holder.value = data?.unique === false ? data.member : null;
    if (!holder.value) loadError.value = m('phone_available', 'Phone is available.');
  } catch (e) {
    loadError.value = m('check_failed', 'Could not check right now.');
  } finally {
    loading.value = false;
  }
}

function toggle() {
  open.value = !open.value;
  if (open.value) load();
}

const WORDS = {
  change: () => m('phone_conflict_confirm_change', 'Change their phone to :phone?').replace(':phone', newPhone.value),
  clear: () => m('phone_conflict_confirm_clear', 'Remove their phone and leave them without one?'),
  delete: () => m('phone_conflict_confirm_delete', 'Delete this person? They go to the trash.'),
};

async function confirmThen(action) {
  if (!window.confirm(WORDS[action]())) return;
  busy.value = true;
  actionError.value = '';
  try {
    const body = { phone: props.phone, action };
    if (action === 'change') body.new_phone = newPhone.value;
    if (props.excludeUserId) body.exclude_user_id = props.excludeUserId;
    await axios.post('/api/member-field/phone/release', body);
    done.value = m('phone_conflict_done', 'Done. The phone is free — save to use it.');
    newPhone.value = '';
    emit('released');
  } catch (e) {
    const errs = e?.response?.data?.errors;
    actionError.value = (errs && Object.values(errs).flat()[0]) || e?.response?.data?.message || m('phone_conflict_failed', 'Could not free the phone. Nothing was changed.');
  } finally {
    busy.value = false;
  }
}
</script>
