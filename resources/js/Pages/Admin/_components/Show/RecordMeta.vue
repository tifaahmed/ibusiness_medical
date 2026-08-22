<template>
  <ShowCard title="Record">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <ShowField v-if="id !== null" label="ID" :value="`#${id}`" />
      <ShowField v-if="slug" label="Slug" :value="slug" value-class="font-mono text-xs" />
      <ShowField label="Created" :value="createdAt" />
      <ShowField label="Last updated" :value="updatedAt" />
      <ShowField v-if="creator" label="Created by" :value="creator.name">
        <p class="text-sm font-medium mt-0.5">{{ creator.name || '—' }}</p>
        <p v-if="creator.email" class="text-xs text-muted-foreground">{{ creator.email }}</p>
      </ShowField>
    </div>
  </ShowCard>
</template>

<script setup>
import ShowCard from './ShowCard.vue';
import ShowField from './ShowField.vue';

defineProps({
  id: { type: [Number, String], default: null },
  slug: { type: String, default: '' },
  createdAt: { type: String, default: '' },
  updatedAt: { type: String, default: '' },
  creator: { type: Object, default: null },
});
</script>
