<template>
  <CompanyLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Company"
        :breadcrumbs="[
          { label: 'Companies', link: route('admin.company.list'), active: false },
          { label: getTranslated(company.name) || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Company Information">
          <div class="space-y-3">
            <TranslatedField label="Name" :value="company.name" />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-1">
              <ShowField label="Members">
                <p class="text-sm font-medium mt-0.5">
                  <Link
                    v-if="company.memberships_count"
                    :href="route('admin.company.members', company.slug)"
                    class="text-primary hover:underline"
                  >
                    {{ company.memberships_count }}
                  </Link>
                  <span v-else>0</span>
                </p>
              </ShowField>
            </div>
          </div>
        </ShowCard>

        <RecordMeta
          :id="company.id"
          :slug="company.slug"
          :created-at="company.created_at"
          :updated-at="company.updated_at"
          :creator="company.creator"
        />

        <ShowActions
          :list-href="route('admin.company.list')"
          :edit-href="canManage('manage companies', 'manage own companies') ? route('admin.company.edit', company.slug) : null"
          edit-label="Edit Company"
        >
          <Link
            :href="route('admin.company.members', company.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            View Members
          </Link>
        </ShowActions>
      </div>
    </div>
  </CompanyLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CompanyLayout from './CompanyLayout.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, TranslatedField, RecordMeta } from '@/Pages/Admin/_components/Show';
import { useTranslatedField } from '@/composables/useTranslatedField';
import { usePermissions } from '@/composables/usePermissions';

defineProps({
  company: { type: Object, required: true },
});

const { getTranslated } = useTranslatedField();
const { canManage } = usePermissions();
</script>
