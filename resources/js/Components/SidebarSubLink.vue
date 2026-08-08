<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
});

const classes = computed(() => {
    return [
        'flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm',
        'sidebar-transition-smooth',
        props.active
            ? 'bg-sidebar-primary/10 text-sidebar-primary border border-sidebar-primary/20 font-medium'
            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground'
    ].join(' ');
});
</script>

<template>
    <Link :href="href" :class="classes" preserve-scroll>
        <slot />
    </Link>
</template>


