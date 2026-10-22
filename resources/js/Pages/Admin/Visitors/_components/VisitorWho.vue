<template>
  <div class="min-w-0">
    <div class="flex flex-wrap items-center gap-1">
      <Link v-if="visitor.id" :href="route('admin.visitors.show', visitor.id)" class="font-medium text-foreground hover:underline">
        {{ visitor.name || (visitor.is_member ? 'Member' : 'Guest') }}
      </Link>
      <span v-if="visitor.is_member" class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-500">✓ Member</span>
      <span v-else-if="visitor.phone || visitor.email" class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-500">Identified</span>
      <span v-else class="inline-flex items-center rounded-full border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground">Anonymous</span>
    </div>
    <div v-if="visitor.phone" class="text-xs text-muted-foreground" dir="ltr">{{ visitor.phone }}</div>
    <div v-if="visitor.email" class="text-xs text-muted-foreground break-all" dir="ltr">{{ visitor.email }}</div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ visitor: { type: Object, required: true } });
</script>
