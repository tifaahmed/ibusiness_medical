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
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    badge: {
        type: [Number, String],
        default: null,
    },
    badgeVariant: {
        type: String,
        default: 'emerald', // emerald, amber, blue, etc.
    },
    iconAnimation: {
        type: String,
        default: 'animate-icon-breathe',
    },
});

const classes = computed(() => {
    return [
        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold',
        'sidebar-item-hover sidebar-transition-smooth group',
        props.isCollapsed ? 'justify-center px-2' : '',
        props.active
            ? 'bg-sidebar-primary/30 text-white border-2 border-sidebar-primary/50 shadow-sm'
            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
    ].filter(Boolean).join(' ');
});

const badgeClasses = computed(() => {
    const variants = {
        emerald: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        amber: 'bg-amber-100 text-amber-800 border-amber-200',
        blue: 'bg-blue-100 text-blue-800 border-blue-200',
        red: 'bg-red-100 text-red-800 border-red-200',
        purple: 'bg-purple-100 text-purple-800 border-purple-200',
    };
    return `${variants[props.badgeVariant] || variants.emerald} text-xs px-1.5 py-0 rounded-full border`;
});
</script>

<template>
    <Link :href="href" :class="classes" preserve-scroll>
        <slot name="icon">
            <div :class="['shrink-0', iconAnimation, 'sidebar-icon-hover']">
                <slot />
            </div>
        </slot>
        
        <div v-if="!isCollapsed" class="flex items-center justify-between flex-1 min-w-0">
            <span class="truncate">
                <slot name="label" />
            </span>
            
            <span 
                v-if="badge && badge > 0" 
                :class="badgeClasses"
            >
                {{ badge }}
            </span>
        </div>
    </Link>
</template>

