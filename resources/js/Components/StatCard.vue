<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    icon: {
        type: String,
        default: 'calendar',
    },
    iconColor: {
        type: String,
        default: 'text-cyan-400',
    },
    iconBgColor: {
        type: String,
        default: 'from-cyan-500/20 to-cyan-500/10',
    },
    sublabel: {
        type: String,
        default: null,
    },
    sublabelValue: {
        type: [String, Number],
        default: null,
    },
    sublabelClassName: {
        type: String,
        default: 'text-cyan-400',
    },
    sublabelValueClassName: {
        type: String,
        default: 'font-semibold',
    },
    href: {
        type: String,
        default: null,
    },
});

const iconPaths = {
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'calendar-check': 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'check-circle': 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'book-open': 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    coins: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'dollar-sign': 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

const iconPath = computed(() => iconPaths[props.icon] || iconPaths.calendar);

const cardClass = computed(() => {
    return 'bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm card-modern hover:shadow-lg transition-all duration-300 cursor-pointer hover:scale-105';
});
</script>

<template>
    <component 
        :is="href ? Link : 'div'" 
        :href="href"
        :class="cardClass"
    >
        <div class="px-6 lg:pr-2 lg:pl-2 md:pr-4 md:pl-4">
            <div class="flex items-center gap-2">
                <!-- Icon Container -->
                <div :class="['flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center', iconBgColor]">
                    <svg :class="['h-6 w-6', iconColor]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
                    </svg>
                </div>
                
                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="text-3xl xl:text-2xl font-bold text-foreground">
                        {{ value }}
                    </div>
                    <p class="text-sm font-medium flex items-center text-muted-foreground">
                        {{ label }}
                    </p>
                    <p v-if="sublabel && sublabelValue" class="text-sm mt-1 text-muted-foreground/80 flex items-center gap-1">
                        <span :class="sublabelValueClassName">{{ sublabelValue }}</span>
                        <span :class="sublabelClassName">{{ sublabel }}</span>
                    </p>
                </div>
            </div>
        </div>
    </component>
</template>

