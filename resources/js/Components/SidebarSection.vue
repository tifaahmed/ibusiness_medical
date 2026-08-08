<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div v-if="!isCollapsed" class="px-3 py-1.5 mb-1 mt-4 first:mt-0">
        <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            {{ title }}
        </h2>
    </div>
    <div v-else class="border-t border-sidebar-border my-2"></div>
</template>


