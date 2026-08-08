<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <h1 class="text-3xl font-bold text-foreground">{{ title }}</h1>
            <span v-if="subtitle" class="text-3xl font-light text-muted-foreground">•</span>
            <h2 v-if="subtitle" class="text-2xl font-medium text-muted-foreground">
                {{ subtitle }}
            </h2>
        </div>
        <div v-if="$slots.actions">
            <slot name="actions" />
        </div>
    </div>
</template>


