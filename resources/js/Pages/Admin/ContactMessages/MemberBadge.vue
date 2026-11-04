<template>
  <span class="inline-flex flex-wrap items-center gap-1" data-testid="contact-member">
    <template v-if="member">
      <!-- trial / paid / ended -->
      <span class="chip" :class="stateClass" data-testid="contact-member-state">{{ stateLabel }}</span>
      <!-- primary card holder or a relative -->
      <span class="chip" :class="member.type === 'relative' ? 'chip-info' : 'chip-neutral'" data-testid="contact-member-type">{{ member.type === 'relative' ? (t.relative || 'Relative') : (t.primary || 'Primary') }}</span>
      <!-- straight to the member's page -->
      <Link :href="route('admin.user.membership.show', member.slug)" class="chip chip-accent hover:underline" data-testid="contact-member-open" :title="member.name">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        {{ t.open_member || 'Member page' }}
      </Link>
    </template>
    <span v-else-if="isMember === false" class="chip chip-warning">{{ t.not_member_badge || 'Not a member' }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  member: { type: Object, default: null },
  isMember: { type: Boolean, default: null },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.contact_messages || {});

const stateClass = computed(() => ({
  'chip-warning': props.member?.state === 'trial',
  'chip-negative': ['trial_expired', 'expired', 'inactive'].includes(props.member?.state),
  'chip-positive': props.member?.state === 'active',
  'chip-neutral': props.member?.state === 'pending',
}));
const stateLabel = computed(() => ({
  trial: t.value.trial || 'Trial',
  trial_expired: t.value.trial_expired || 'Trial ended',
  active: t.value.paid || 'Paid',
  expired: t.value.expired || 'Expired',
  inactive: t.value.inactive || 'Inactive',
  pending: t.value.pending || 'Pending',
}[props.member?.state] || props.member?.state));
</script>
