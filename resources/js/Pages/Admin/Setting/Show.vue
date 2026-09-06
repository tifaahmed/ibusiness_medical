<template>
  <SettingLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
        <div class="flex items-start justify-between gap-3 border-b border-border p-4 sm:p-6">
          <div class="min-w-0">
            <h1 class="text-lg font-semibold truncate">{{ nameIn(setting.name) || setting.slug }}</h1>
            <code class="text-xs text-muted-foreground font-mono" dir="ltr">{{ setting.slug }}</code>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <Link
              v-if="canWrite"
              :href="route('admin.setting.edit', setting.id)"
              class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted"
            >
              {{ t.common?.edit || 'Edit' }}
            </Link>
            <Link
              :href="route('admin.setting.list')"
              class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted"
            >
              {{ t.common?.back || 'Back' }}
            </Link>
          </div>
        </div>

        <dl class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-xs text-muted-foreground mb-1">{{ t.setting?.value_type || 'Type' }}</dt>
            <dd>{{ t.setting?.types?.[setting.value_type] || setting.value_type }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground mb-1">{{ t.common?.updated_at || 'Last updated' }}</dt>
            <dd>{{ setting.updated_at ? new Date(setting.updated_at).toLocaleString() : '—' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs text-muted-foreground mb-1">{{ t.setting?.value || 'Value' }}</dt>
            <dd>
              <img
                v-if="setting.value_type === 'image' && setting.image_url"
                :src="setting.image_url"
                :alt="nameIn(setting.name) || setting.slug"
                class="h-24 w-auto max-w-full object-contain rounded border border-border bg-white/5 p-2"
              />
              <a
                v-else-if="setting.value_type === 'url' && setting.value"
                :href="setting.value"
                target="_blank"
                rel="noopener"
                class="text-primary hover:underline break-all"
                dir="ltr"
              >{{ setting.value }}</a>
              <pre
                v-else-if="setting.value"
                class="whitespace-pre-wrap break-words font-mono text-xs bg-muted/40 rounded p-3 border border-border"
                dir="ltr"
              >{{ setting.value }}</pre>
              <span v-else class="text-muted-foreground italic">{{ t.setting?.empty || 'Not set' }}</span>
            </dd>
          </div>
          <div v-if="setting.value_type === 'image' && setting.value" class="sm:col-span-2">
            <dt class="text-xs text-muted-foreground mb-1">{{ t.setting?.stored_path || 'Stored file' }}</dt>
            <dd class="font-mono text-xs break-all" dir="ltr">{{ setting.value }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </SettingLayout>
</template>

<script setup>
import SettingLayout from "./SettingLayout.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
const canWrite = computed(() => can('manage settings'));

const props = defineProps({
  setting: {
    type: Object,
    required: true
  }
});

const page = usePage();
const locale = page.props.locale || 'ar';
const t = computed(() => page.props.translations?.admin || {});

const nameIn = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') return name[locale] || name.ar || name.en || '';
  return '';
};
</script>
