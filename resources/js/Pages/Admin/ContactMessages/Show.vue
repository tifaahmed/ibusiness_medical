<template>
  <AppLayout :title="t.title || 'Contact Messages'">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0 text-blue-400">
                  <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">{{ (t.message_number || 'Message #:id').replace(':id', message.id) }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span :class="sourceBadgeClass(message.source)">{{ message.source_label }}</span>
              <span :class="statusBadgeClass(message.status)">{{ message.status_label }}</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
          <!-- Contact Info -->
          <div class="lg:col-span-1 space-y-3">
            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.information || 'Contact Information' }}</h2>
              </div>
              <div class="p-3 space-y-3">
                <div v-if="message.name">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.name || 'Name' }}</label>
                  <p class="text-sm font-medium text-foreground">{{ message.name }}</p>
                </div>
                <div v-if="message.email">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.email || 'Email' }}</label>
                  <p class="text-sm text-foreground">{{ message.email }}</p>
                </div>
                <div v-if="message.phone">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.phone || 'Phone' }}</label>
                  <p class="text-sm text-foreground" dir="ltr">{{ message.phone }}</p>
                </div>
                <!-- Only a facility applying to join carries one, and it is
                     what sales verifies the applicant against. -->
                <div v-if="message.commercial_register">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.commercial_register || 'Commercial register' }}</label>
                  <p class="text-sm font-medium text-amber-300" dir="ltr">{{ message.commercial_register }}</p>
                </div>
              </div>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.timeline || 'Timeline' }}</h2>
              </div>
              <div class="p-3 space-y-3">
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.submitted || 'Submitted' }}</label>
                  <p class="text-sm text-foreground">{{ formatDate(message.created_at) }}</p>
                  <p class="text-xs text-muted-foreground">{{ message.created_at_human }}</p>
                </div>
                <div v-if="message.read_at">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.read || 'First opened' }}</label>
                  <p class="text-sm text-foreground">{{ formatDate(message.read_at) }}</p>
                </div>
                <div v-if="message.replied_at">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.replied || 'Resolved' }}</label>
                  <p class="text-sm text-foreground">{{ formatDate(message.replied_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Where the enquiry was sent from. Admin-only on the resource,
                 so the whole panel disappears for anybody else. -->
            <div v-if="hasVisitorContext" data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.visitor || 'Visitor' }}</h2>
              </div>
              <div class="p-3 space-y-3">
                <div v-if="message.ip_address">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.ip_address || 'IP address' }}</label>
                  <p class="text-sm text-foreground" dir="ltr">{{ message.ip_address }}</p>
                </div>
                <div v-if="message.locale">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.locale || 'Language' }}</label>
                  <p class="text-sm text-foreground uppercase">{{ message.locale }}</p>
                </div>
                <div v-if="message.referrer">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.referrer || 'Sent from' }}</label>
                  <p class="text-xs text-foreground break-all" dir="ltr">{{ message.referrer }}</p>
                </div>
                <div v-if="message.user_agent">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.user_agent || 'Browser' }}</label>
                  <p class="text-xs text-muted-foreground break-all" dir="ltr">{{ message.user_agent }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Message Content -->
          <div class="lg:col-span-2 space-y-3">
            <div v-if="message.subject" data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.subject || 'Subject' }}</h2>
              </div>
              <div class="p-3">
                <p class="text-sm text-foreground">{{ message.subject }}</p>
              </div>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.message || 'Message' }}</h2>
              </div>
              <div class="p-3">
                <p class="text-sm text-foreground whitespace-pre-wrap leading-relaxed">{{ message.message }}</p>
              </div>
            </div>

            <!-- Working the enquiry -->
            <div v-if="canManage" data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.manage || 'Manage' }}</h2>
              </div>
              <div class="p-3 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="min-w-0">
                    <label class="text-xs font-medium text-muted-foreground mb-1 block">{{ t.status || 'Status' }}</label>
                    <Select
                      :modelValue="form.status"
                      :options="statusOptions"
                      @update:modelValue="val => form.status = val"
                    />
                  </div>
                  <div class="min-w-0">
                    <label class="text-xs font-medium text-muted-foreground mb-1 block">{{ t.sales || 'Salesperson' }}</label>
                    <Select
                      :modelValue="form.sales_id"
                      :options="salesSelectOptions"
                      :placeholder="t.unassigned || 'Unassigned'"
                      @update:modelValue="val => form.sales_id = val"
                    />
                  </div>
                </div>

                <div>
                  <label class="text-xs font-medium text-muted-foreground mb-1 block">{{ t.admin_notes || 'Internal note' }}</label>
                  <textarea
                    v-model="form.admin_notes"
                    rows="3"
                    :placeholder="t.admin_notes_placeholder || 'Only admins see this.'"
                    class="dark:bg-input/30 border border-border text-foreground w-full rounded-md bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                  ></textarea>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="save"
                    :disabled="form.processing || !isDirty"
                    class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50 h-8 px-3 py-1.5"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ t.save || 'Save' }}
                  </button>
                  <span v-if="form.recentlySuccessful" class="text-xs text-emerald-400">{{ t.saved || 'Saved.' }}</span>

                  <button
                    type="button"
                    @click="destroy"
                    class="cursor-pointer ltr:ml-auto rtl:mr-auto inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-8 px-3 py-1.5"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                      <path d="M3 6h18"></path>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                      <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    {{ t.delete || 'Delete' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Audit trail -->
            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
              <div class="p-3 border-b border-border">
                <h2 class="text-sm font-semibold text-foreground">{{ t.activity || 'Activity' }}</h2>
              </div>
              <div class="p-3">
                <ol v-if="message.logs?.length" class="space-y-3">
                  <li v-for="log in message.logs" :key="log.id" class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-400"></span>
                    <div class="min-w-0">
                      <p class="text-sm text-foreground">
                        {{ log.action_label }}
                        <span v-if="log.old_value" class="text-muted-foreground">
                          — {{ log.old_value }} → {{ log.new_value || '—' }}
                        </span>
                        <span v-else-if="log.new_value" class="text-muted-foreground">
                          — {{ log.new_value }}
                        </span>
                      </p>
                      <p class="text-[11px] text-muted-foreground">
                        <!-- No admin means the visitor's own submission. -->
                        {{ log.admin_name || (t.by_visitor || 'From the website') }} · {{ formatDate(log.created_at) }}
                      </p>
                    </div>
                  </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">{{ t.no_activity || 'Nothing recorded yet.' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Back Button -->
        <div class="flex justify-start pt-2">
          <Link
            :href="route('admin.contact-messages.index')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.back_to_messages || 'Back to Messages' }}
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from '@/Components/ui/Select.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.contact_messages || {});

const props = defineProps({
  message: { type: Object, required: true },
  statuses: { type: Array, default: () => [] },
  salesOptions: { type: Array, default: () => [] },
  // The read side admits `view contact messages`, so a viewer reaches this
  // page with no right to change anything. The manage card is hidden for them
  // rather than shown and rejected.
  canManage: { type: Boolean, default: false },
});

const form = useForm({
  status: props.message.status,
  sales_id: props.message.sales_id ?? null,
  admin_notes: props.message.admin_notes ?? '',
});

/* Nothing to save until something actually moved — the button says so rather
   than writing an update that records no change. */
const isDirty = computed(() =>
  form.status !== props.message.status
  || form.sales_id !== (props.message.sales_id ?? null)
  || form.admin_notes !== (props.message.admin_notes ?? '')
);

const statusOptions = computed(() =>
  props.statuses.map(s => ({ value: s.value, label: t.value[s.value] || s.label })),
);

/* Null is a real choice here: taking a salesperson off an enquiry is how it
   goes back into the pool. */
const salesSelectOptions = computed(() => [
  { value: null, label: t.value.unassigned || 'Unassigned' },
  ...props.salesOptions,
]);

/* The visitor panel is admin-only on the resource, so these arrive undefined
   for everybody else and the whole card is dropped. */
const hasVisitorContext = computed(() =>
  Boolean(props.message.ip_address || props.message.locale || props.message.referrer || props.message.user_agent),
);

const save = () => {
  form.put(route('admin.contact-messages.update', props.message.id), {
    preserveScroll: true,
  });
};

/* Soft-deleted, so this is recoverable in the database — but it disappears
   from the inbox, which for a lead is destructive enough to confirm. */
const destroy = () => {
  if (!window.confirm(t.value.confirm_delete || 'Delete this enquiry?')) {
    return;
  }

  router.delete(route('admin.contact-messages.destroy', props.message.id));
};

const statusBadgeClass = (status) => {
  const base = 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1';
  const map = {
    new: `${base} bg-blue-500/20 text-blue-300 ring-blue-500/30`,
    in_progress: `${base} bg-amber-500/20 text-amber-300 ring-amber-500/30`,
    resolved: `${base} bg-emerald-500/20 text-emerald-300 ring-emerald-500/30`,
    closed: `${base} bg-muted text-muted-foreground ring-border`,
  };
  return map[status] || map.new;
};

const sourceBadgeClass = (source) => {
  const base = 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1';
  const map = {
    contact_form: `${base} bg-slate-500/20 text-slate-300 ring-slate-500/30`,
    card_popup: `${base} bg-indigo-500/20 text-indigo-300 ring-indigo-500/30`,
    join_request: `${base} bg-purple-500/20 text-purple-300 ring-purple-500/30`,
  };
  return map[source] || map.contact_form;
};

const formatDate = (s) => {
  if (!s) return '-';
  return new Date(s).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};
</script>
