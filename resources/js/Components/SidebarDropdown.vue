<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    active: {
        type: Boolean,
        default: false,
    },
    iconAnimation: {
        type: String,
        default: 'animate-icon-breathe',
    },
    autoOpen: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isOpen = ref(false);

// Auto-open dropdown if current route matches
watch(() => page.url, (newUrl) => {
    if (props.autoOpen.some(route => newUrl.includes(route))) {
        isOpen.value = true;
    }
}, { immediate: true });

const toggleDropdown = () => {
    if (!props.isCollapsed) {
        isOpen.value = !isOpen.value;
    }
};

const buttonClasses = computed(() => {
    return [
        'w-full flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold',
        'sidebar-item-hover sidebar-transition-smooth group',
        'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
        props.isCollapsed ? 'justify-center px-2' : 'justify-between',
        props.active || isOpen.value
            ? 'bg-sidebar-primary/30 text-white border-2 border-sidebar-primary/50'
            : 'text-sidebar-foreground'
    ].filter(Boolean).join(' ');
});

const chevronClasses = computed(() => {
    return [
        'size-4 text-muted-foreground chevron-smooth transition-transform duration-200',
        isOpen.value ? 'chevron-smooth-rotate' : ''
    ].join(' ');
});
</script>

<template>
    <div>
        <!-- Dropdown Trigger -->
        <button
            @click="toggleDropdown"
            :class="buttonClasses"
            type="button"
        >
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div :class="['shrink-0', iconAnimation, 'sidebar-icon-hover']">
                    <slot name="icon" />
                </div>
                <span v-if="!isCollapsed" class="truncate">{{ label }}</span>
            </div>
            
            <svg 
                v-if="!isCollapsed"
                :class="chevronClasses"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Content -->
        <div 
            v-if="!isCollapsed"
            v-show="isOpen"
            class="dropdown-smooth overflow-hidden"
            :data-state="isOpen ? 'open' : 'closed'"
        >
            <div class="ml-6 mt-1 space-y-0.5 border-l border-sidebar-border pl-4 pb-1">
                <slot />
            </div>
        </div>
    </div>
</template>


