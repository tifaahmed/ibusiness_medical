<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      data-testid="trial-history-dialog"
      @click.self="$emit('close')"
      @keydown.esc="$emit('close')"
    >
      <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-center gap-3 border-b border-border p-4">
          <span class="chip chip-accent">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
          </span>
          <h2 class="text-base font-semibold">{{ t.member?.trial_history || 'Trial history' }}<span v-if="name"> — {{ name }}</span></h2>
          <button type="button" class="ms-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground" :title="t.common?.close || 'Close'" @click="$emit('close')">✕</button>
        </div>

        <div class="space-y-4 overflow-y-auto p-4">
          <p v-if="loading" class="text-sm text-muted-foreground">…</p>
          <p v-else-if="error" class="text-sm value-negative">{{ error }}</p>
          <p v-else-if="!cards.length" class="text-sm text-muted-foreground">{{ t.member?.trial_history_empty || 'No trial history yet.' }}</p>

          <section v-for="c in cards" :key="c.membership_id" class="space-y-2" data-testid="trial-history-card">
            <div class="flex flex-wrap items-center gap-2 text-sm">
              <span class="chip chip-neutral" dir="ltr">#{{ c.membership_number }}</span>
              <span v-if="c.is_trial" class="chip chip-info">{{ t.member?.on_trial || 'On free trial' }} · {{ c.days_left }} {{ t.member?.days || 'days' }}</span>
              <span v-else class="chip chip-neutral">{{ t.member?.trial_not_active || 'Not on trial' }}</span>
              <span v-if="c.locked" class="chip chip-warning">{{ t.member?.trial_locked_chip || 'Trial closed' }}</span>
              <span class="chip" :class="c.extensions ? 'chip-warning' : 'chip-neutral'" data-testid="trial-extensions">
                {{ t.member?.trial_extended_times || 'Extended' }}: {{ c.extensions }}
              </span>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border">
              <table class="w-full text-xs sm:text-sm">
                <thead class="bg-muted/40 text-muted-foreground">
                  <tr>
                    <th class="px-2 py-1.5 text-start font-medium">{{ t.member?.trial_event || 'Event' }}</th>
                    <th class="px-2 py-1.5 text-start font-medium">{{ t.member?.trial_period || 'Trial period' }}</th>
                    <th class="px-2 py-1.5 text-center font-medium">{{ t.member?.days || 'Days' }}</th>
                    <th class="px-2 py-1.5 text-start font-medium">{{ t.member?.trial_membership_expiry || 'Membership ends' }}</th>
                    <th class="px-2 py-1.5 text-start font-medium">{{ t.member?.trial_by || 'By / when' }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!c.events.length"><td colspan="5" class="px-2 py-3 text-center text-muted-foreground">—</td></tr>
                  <tr v-for="e in c.events" :key="e.id" class="border-t border-border/60">
                    <td class="px-2 py-1.5"><span class="chip" :class="CHIP[e.event] || 'chip-neutral'">{{ label(e.event) }}</span></td>
                    <td class="whitespace-nowrap px-2 py-1.5" dir="ltr">
                      {{ e.starts_on || '—' }} → <strong>{{ e.ends_on || '—' }}</strong>
                      <span v-if="e.previous_ends_on" class="text-muted-foreground"> (was {{ e.previous_ends_on }})</span>
                    </td>
                    <td class="px-2 py-1.5 text-center tabular-nums">{{ e.days ?? '—' }}</td>
                    <td class="whitespace-nowrap px-2 py-1.5" dir="ltr">
                      {{ e.expires_on || '—' }}
                      <span v-if="e.previous_expires_on && e.previous_expires_on !== e.expires_on" class="text-muted-foreground"> (was {{ e.previous_expires_on }})</span>
                    </td>
                    <td class="px-2 py-1.5 text-muted-foreground">{{ e.by || (t.member?.trial_by_system || 'Member / system') }} · <span dir="ltr">{{ e.at }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ open: Boolean, userSlug: { type: String, default: '' } });
defineEmits(['close']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const CHIP = { started: 'chip-info', extended: 'chip-warning', shortened: 'chip-neutral', ended: 'chip-negative' };
const LABELS = computed(() => ({
  started: t.value.member?.trial_ev_started || 'Started',
  extended: t.value.member?.trial_ev_extended || 'Extended',
  shortened: t.value.member?.trial_ev_shortened || 'Shortened',
  ended: t.value.member?.trial_ev_ended || 'Ended',
}));
const label = (e) => LABELS.value[e] || e;

const loading = ref(false);
const error = ref('');
const cards = ref([]);
const name = ref('');

async function load() {
  if (!props.userSlug) return;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/admin/user/membership/${props.userSlug}/trials`);
    cards.value = data.data.cards || [];
    name.value = data.data.user || '';
  } catch {
    error.value = t.value.member?.trial_history_failed || 'Could not load the trial history.';
  } finally {
    loading.value = false;
  }
}

watch(() => props.open, (o) => { if (o) load(); }, { immediate: true });
</script>
