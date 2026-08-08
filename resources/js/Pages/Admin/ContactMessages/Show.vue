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
            <span :class="statusBadgeClass(message.status)">{{ message.status_label }}</span>
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
                  <p class="text-sm text-foreground">{{ message.phone }}</p>
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
                  <label class="text-xs font-medium text-muted-foreground">{{ t.read || 'Read' }}</label>
                  <p class="text-sm text-foreground">{{ formatDate(message.read_at) }}</p>
                </div>
                <div v-if="message.replied_at">
                  <label class="text-xs font-medium text-muted-foreground">{{ t.replied || 'Replied' }}</label>
                  <p class="text-sm text-foreground">{{ formatDate(message.replied_at) }}</p>
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
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.contact_messages || {});

const props = defineProps({
  message: { type: Object, required: true },
});

const statusBadgeClass = (status) => {
  const map = {
    new: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30',
    read: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30',
    replied: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-purple-500/20 text-purple-300 ring-1 ring-purple-500/30',
    archived: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-300 ring-1 ring-amber-500/30',
  };
  return map[status] || map.new;
};

const formatDate = (s) => {
  if (!s) return '-';
  return new Date(s).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};
</script>
