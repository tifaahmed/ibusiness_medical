<template>
  <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-4 shadow-sm" data-testid="member-referral">
    <div class="px-6 title-golden leading-none font-semibold">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      {{ t.referral_title || 'Referral code' }}
    </div>

    <p v-if="loading" class="px-6 text-sm text-muted-foreground">…</p>

    <p v-else-if="state.brought_by" class="px-6 text-sm" data-testid="referral-brought-by">
      {{ t.referral_brought_by || 'Brought by card' }}
      <span class="chip chip-neutral" dir="ltr">#{{ state.brought_by.number }}</span>
      <span class="chip chip-accent">{{ state.brought_by.source }}</span>
      <span class="text-xs text-muted-foreground" dir="ltr">{{ state.brought_by.at }}</span>
    </p>

    <form v-else class="px-6 space-y-3" @submit.prevent="check">
      <p class="text-xs text-muted-foreground">{{ t.referral_hint || 'Enter the card number of the member who brought this member. It can be done once only; that member gets the signup bonus in their wallet.' }}</p>
      <div class="flex flex-wrap items-end gap-3">
        <input v-model.trim="number" type="text" maxlength="32" required dir="ltr" data-testid="referral-number" :placeholder="t.referral_card_number || 'Referrer card number'" class="h-9 w-56 rounded-md border border-border bg-background px-2 text-sm" />
        <button type="submit" :disabled="busy" data-testid="referral-check" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">{{ t.referral_check || 'Check' }}</button>
      </div>
      <p v-if="error" class="text-xs value-negative" data-testid="referral-error">{{ error }}</p>

      <div v-if="owner" class="panel-note flex flex-wrap items-center gap-3 p-3 text-sm" data-testid="referral-confirm">
        <span>{{ t.referral_confirm_text || 'Is this the right member?' }}</span>
        <span class="chip chip-neutral" dir="ltr">#{{ owner.number }}</span>
        <span class="font-semibold">{{ owner.full_name }}</span>
        <span class="chip chip-neutral" dir="ltr">{{ owner.phone }}</span>
        <span class="chip chip-positive">+ {{ state.bonus }} EGP</span>
        <button type="button" :disabled="busy" data-testid="referral-confirm-btn" class="ms-auto h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" @click="apply">{{ t.referral_confirm || 'Confirm and apply' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ userId: { type: Number, required: true } });
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const loading = ref(true);
const busy = ref(false);
const number = ref('');
const owner = ref(null);
const error = ref('');
const state = reactive({ brought_by: null, bonus: 0 });
const url = `/admin/user/membership/${props.userId}/referral`;

const REASONS = {
  not_found: 'No member card with this number.', self: 'A member cannot bring themselves.', mutual: 'That member was brought by this member.',
  already_referred: 'A code was already entered for this member.', already_paid: 'This member already subscribed.', company: 'Company members cannot use a referral code.', disabled: 'Referrals are switched off.',
};

async function load() {
  loading.value = true;
  try { Object.assign(state, (await axios.get(url)).data); } catch { error.value = 'Could not load.'; } finally { loading.value = false; }
}

async function check() {
  error.value = ''; owner.value = null; busy.value = true;
  try {
    const { data } = await axios.get(url, { params: { number: number.value } });
    owner.value = data.owner; state.bonus = data.bonus;
  } catch (e) {
    error.value = REASONS[e.response?.data?.reason] || 'Could not check this number.';
  } finally { busy.value = false; }
}

async function apply() {
  busy.value = true; error.value = '';
  try {
    await axios.post(url, { number: number.value, confirmed: true });
    owner.value = null; number.value = ''; await load();
  } catch (e) {
    error.value = REASONS[e.response?.data?.reason] || 'Could not apply the code.';
  } finally { busy.value = false; }
}

onMounted(load);
</script>
