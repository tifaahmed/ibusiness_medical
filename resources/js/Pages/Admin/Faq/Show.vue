<template>
  <FaqLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View FAQ"
        :breadcrumbs="[
          { label: 'FAQs', link: route('admin.faq.list'), active: false },
          { label: 'View FAQ', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Question &amp; Answer">
          <div class="space-y-3">
            <TranslatedField label="Question" :value="faq.question" />
            <TranslatedField label="Answer" :value="faq.answer" multiline text-class="" />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-1">
              <ShowField label="Status">
                <div class="mt-0.5">
                  <StatusPill :active="faq.is_active" />
                </div>
              </ShowField>
              <ShowField label="Sort order" :value="faq.sort_order" />
            </div>
          </div>
        </ShowCard>

        <RecordMeta
          :id="faq.id"
          :created-at="faq.created_at"
          :updated-at="faq.updated_at"
          :creator="faq.creator"
        />

        <ShowActions
          :list-href="route('admin.faq.list')"
          :edit-href="canManage('manage faqs', 'manage own faqs') ? route('admin.faq.edit', faq.id) : null"
          edit-label="Edit FAQ"
        />
      </div>
    </div>
  </FaqLayout>
</template>

<script setup>
import FaqLayout from './FaqLayout.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, TranslatedField, StatusPill, RecordMeta } from '@/Pages/Admin/_components/Show';
import { usePermissions } from '@/composables/usePermissions';

defineProps({
  faq: { type: Object, required: true },
});

const { canManage } = usePermissions();
</script>
