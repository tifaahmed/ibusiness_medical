<template>
  <MemberLayout>
    <div class="max-w-2xl mx-auto mt-3 sm:mt-4 px-3 sm:px-4 pb-8 space-y-4" data-testid="quick-add-done">
      <div class="rounded-xl border border-border bg-card p-4 sm:p-6 space-y-4">
        <div class="flex items-center gap-2">
          <span class="chip chip-positive">✓</span>
          <h3 class="text-base font-semibold">{{ t.member_added || 'Member added' }}</h3>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm" data-testid="done-details">
          <div><dt class="text-xs text-muted-foreground">{{ t.customer_name || 'Customer name' }}</dt><dd class="font-medium">{{ member.name }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">{{ t.customer_phone || 'Phone' }}</dt><dd class="font-medium tabular-nums" dir="ltr">{{ member.phone }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">{{ t.membership_number || 'Membership number' }}</dt><dd class="font-medium tabular-nums" dir="ltr">{{ member.membership_number }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">{{ t.customer_kind || 'Individual or company' }}</dt><dd>{{ member.member_kind === 'company' ? (t.company || 'Company') : (t.individual || 'Individual') }} · {{ member.device === 'iphone' ? (t.iphone || 'iPhone') : (t.android || 'Android') }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">{{ t.trial_until || 'Trial until' }}</dt><dd class="tabular-nums" dir="ltr">{{ member.expiration_date || '—' }}</dd></div>
          <div v-if="member.wants_card"><dt class="text-xs text-muted-foreground">{{ t.wants_card_label || 'Wants to receive a card' }}</dt><dd><span class="chip chip-warning">✓</span></dd></div>
        </dl>

        <div v-if="member.sms" class="rounded-md border border-border p-3 space-y-1" data-testid="done-sms">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold">{{ t.sms_message || 'SMS message' }}</span>
            <span class="chip" :class="member.sms.status === 'sent' ? 'chip-positive' : member.sms.status === 'failed' ? 'chip-negative' : 'chip-warning'">{{ member.sms.status }}</span>
            <span class="chip chip-neutral tabular-nums" dir="ltr">{{ member.sms.characters }} / {{ member.sms.segments }}</span>
          </div>
          <p class="whitespace-pre-line break-words text-sm" dir="rtl">{{ member.sms.body }}</p>
        </div>

        <!-- Next steps -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" data-testid="done-actions">
          <Link :href="route('admin.user.membership.quick-add.whatsapp', member.slug)" class="h-12 inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground sm:col-span-2" data-testid="done-whatsapp">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.5A8.4 8.4 0 1 1 21 11.5z"/></svg>
            {{ t.send_whatsapp || 'Send the details on WhatsApp' }}
          </Link>
          <Link :href="route('admin.user.membership.edit', member.slug)" class="h-12 inline-flex items-center justify-center rounded-md border border-border px-4 text-sm" data-testid="done-edit">{{ t.go_edit || 'Edit the member' }}</Link>
          <Link :href="route('admin.user.membership.show', member.slug)" class="h-12 inline-flex items-center justify-center rounded-md border border-border px-4 text-sm" data-testid="done-view">{{ t.go_view || 'View the member' }}</Link>
          <Link :href="route('admin.user.membership.list')" class="h-12 inline-flex items-center justify-center rounded-md border border-border px-4 text-sm" data-testid="done-back">{{ t.back_to_list || 'Back to the members list' }}</Link>
          <Link :href="route('admin.user.membership.quick-add.page')" class="h-12 inline-flex items-center justify-center rounded-md border border-border px-4 text-sm" data-testid="done-another">{{ t.add_another || 'Add another customer' }}</Link>
        </div>
      </div>

      <ExtraPhones :slug="member.slug" :phones="extra_phones" />
    </div>
  </MemberLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MemberLayout from '../MemberLayout.vue';
import ExtraPhones from './ExtraPhones.vue';

defineProps({ member: { type: Object, required: true }, extra_phones: { type: Array, default: () => [] } });
const page = usePage();
const t = computed(() => ({ ...(page.props.translations?.admin?.member_list || {}), ...(page.props.translations?.admin?.quick_add || {}) }));
</script>
