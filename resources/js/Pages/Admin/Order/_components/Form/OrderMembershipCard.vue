<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border shadow-sm py-4" :class="panel.border">
    <div class="grid auto-rows-min gap-1.5 py-2 px-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
          </svg>
          {{ t.order?.membership_section || 'Membership' }}
        </div>

        <!-- The whole point of the panel: member or not, in one badge. -->
        <span :class="['inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-semibold', panel.badge]">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <template v-if="membership.status === 'valid'">
              <path d="M20 6 9 17l-5-5"></path>
            </template>
            <template v-else-if="membership.status === 'none'">
              <circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path>
            </template>
            <template v-else>
              <path d="M12 9v4"></path><path d="M12 17h.01"></path>
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            </template>
          </svg>
          {{ panel.label }}
        </span>
      </div>
      <p class="text-xs text-muted-foreground">{{ panel.hint }}</p>
    </div>

    <div class="px-6 space-y-3">
      <!-- A number that resolves to nothing: shown on its own, because there
           is no card to draw and the number itself is the finding. -->
      <div
        v-if="membership.status === 'unknown'"
        class="rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2"
      >
        <p class="text-xs text-red-400">
          {{ t.order?.membership_unknown_detail || 'This number matches no visible membership card. The order was priced at the full price.' }}
        </p>
        <p class="mt-1 font-mono text-sm font-semibold text-red-400" dir="ltr">{{ membership.number }}</p>
      </div>

      <!-- The card itself. -->
      <div v-if="card" class="rounded-lg border border-border bg-muted/30 p-3 sm:p-4 space-y-3">
        <div class="flex items-start gap-3">
          <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full border-2 border-border bg-background">
            <img
              v-if="card.user?.avatar_url"
              :src="card.user.avatar_url"
              :alt="card.user?.name || card.membership_number"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-muted-foreground">
              {{ initials }}
            </div>
          </div>

          <div class="min-w-0 flex-1">
            <p class="truncate text-base font-semibold">{{ card.user?.name || (t.order?.membership_holder_unknown || 'Unnamed member') }}</p>
            <p v-if="card.job_title" class="truncate text-xs text-muted-foreground">{{ card.job_title }}</p>
            <p class="mt-1 font-mono text-sm font-semibold tracking-wide" dir="ltr">{{ card.membership_number }}</p>
          </div>

          <Link
            v-if="card.user?.slug"
            :href="route('admin.user.membership.show', card.user.slug)"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border bg-background px-2.5 py-1.5 text-xs font-medium shadow-xs transition-all hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50"
          >
            {{ t.order?.open_member || 'Open member' }}
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M7 7h10v10"></path><path d="M7 17 17 7"></path>
            </svg>
          </Link>
        </div>

        <div class="grid grid-cols-2 gap-3 border-t border-border pt-3 sm:grid-cols-4">
          <div>
            <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_registered || 'Registered' }}</label>
            <p class="mt-0.5 text-sm tabular-nums" dir="ltr">{{ card.registration_date || '—' }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_expires || 'Expires' }}</label>
            <p class="mt-0.5 text-sm tabular-nums" :class="card.is_expired ? 'text-red-400 font-semibold' : ''" dir="ltr">
              {{ card.expiration_date || '—' }}
            </p>
          </div>
          <div>
            <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_state || 'Card state' }}</label>
            <p class="mt-0.5 text-sm font-medium" :class="card.is_active ? 'text-emerald-500' : 'text-red-400'">
              {{ card.is_active ? (t.order?.membership_on || 'Active') : (t.order?.membership_off || 'Switched off') }}
            </p>
          </div>
          <div>
            <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_contact || 'Contact' }}</label>
            <p class="mt-0.5 truncate text-sm" dir="ltr">{{ card.user?.phone || card.user?.email || '—' }}</p>
          </div>
        </div>

        <!-- A card that is fine today but will not be next week is worth
             saying out loud on an order that has not been delivered yet. -->
        <p
          v-if="expiryWarning"
          class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-500"
        >
          {{ expiryWarning }}
        </p>
      </div>

      <!-- The order's own number and the card's differ only when the admin has
           edited the box; saying so beats a badge that silently goes stale. -->
      <p
        v-if="numberEdited"
        class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-2 text-xs text-amber-500"
      >
        {{ t.order?.membership_number_edited || 'The membership number in the form no longer matches the one this card was looked up from. Save to re-check it.' }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  /** The `membership` prop as `AdminOrderMembershipResource` sends it. */
  membership: {
    type: Object,
    default: () => ({ status: 'none', number: null, earns_member_price: false, card: null }),
  },
  /** What the membership box holds right now, so an edit to it is visible. */
  currentNumber: {
    type: String,
    default: '',
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const card = computed(() => props.membership?.card || null);

/**
 * Five states, each with its own colour, because they call for different
 * things: a valid card is a discount to leave alone, an unknown number is a
 * customer to phone, and an empty box is simply not a member.
 */
const panel = computed(() => {
  switch (props.membership?.status) {
    case 'valid':
      return {
        label: t.value.order?.membership_valid || 'Member — card valid',
        hint: t.value.order?.membership_valid_hint || 'This order was priced at the member price.',
        badge: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-400',
        border: 'border-emerald-500/40',
      };
    case 'expired':
      return {
        label: t.value.order?.membership_expired || 'Member — card expired',
        hint: t.value.order?.membership_expired_hint || 'The card has lapsed, so it earns the full price today.',
        badge: 'border-amber-500/40 bg-amber-500/15 text-amber-500',
        border: 'border-amber-500/40',
      };
    case 'inactive':
      return {
        label: t.value.order?.membership_inactive || 'Member — card switched off',
        hint: t.value.order?.membership_inactive_hint || 'The card exists but is not active, so it earns the full price today.',
        badge: 'border-amber-500/40 bg-amber-500/15 text-amber-500',
        border: 'border-amber-500/40',
      };
    case 'unknown':
      return {
        label: t.value.order?.membership_unknown || 'No such card',
        hint: t.value.order?.membership_unknown_hint || 'A number was given on this order but it matches no card.',
        badge: 'border-red-500/40 bg-red-500/15 text-red-400',
        border: 'border-red-500/40',
      };
    default:
      return {
        label: t.value.order?.membership_none || 'Not a member',
        hint: t.value.order?.membership_none_hint || 'No membership number on this order — the customer paid the full price.',
        badge: 'border-zinc-500/40 bg-zinc-500/15 text-zinc-400',
        border: 'border-border',
      };
  }
});

const initials = computed(() => {
  const name = card.value?.user?.name || card.value?.membership_number || '';
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0])
    .join('')
    .toUpperCase() || '—';
});

/* Warned about a month out — long enough to renew before the next order. */
const expiryWarning = computed(() => {
  const days = card.value?.days_to_expiry;
  if (!Number.isFinite(days) || days < 0 || days > 30) return null;
  if (days === 0) return t.value.order?.membership_expires_today || 'This card expires today.';
  return (t.value.order?.membership_expires_soon || 'This card expires in :days days.')
    .replace(':days', String(days));
});

const numberEdited = computed(() => {
  const typed = (props.currentNumber || '').trim();
  const lookedUp = (props.membership?.number || '').trim();
  return typed !== lookedUp;
});
</script>
