<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    isMobile: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'toggle']);

const touchStart = ref(0);
const touchEnd = ref(0);
const navScrollRef = ref(null);

// The sidebar remounts on every Inertia page visit, so its scroll
// position resets to 0 unless we manually restore it. Persisted per-tab
// (not localStorage) so a fresh login doesn't jump to an old scroll spot.
const SCROLL_STORAGE_KEY = 'membership-sidebar-scroll';

const saveScrollPosition = () => {
    if (navScrollRef.value) {
        sessionStorage.setItem(SCROLL_STORAGE_KEY, navScrollRef.value.scrollTop.toString());
    }
};

const closeSidebar = () => {
    emit('close');
};

const toggleSidebar = () => {
    emit('toggle');
};

// Touch gesture handlers for mobile swipe
const handleTouchStart = (e) => {
    touchStart.value = e.targetTouches[0].clientX;
};

const handleTouchMove = (e) => {
    touchEnd.value = e.targetTouches[0].clientX;
};

const handleTouchEnd = () => {
    const distance = touchStart.value - touchEnd.value;
    const isLeftSwipe = distance > 50;
    
    if (isLeftSwipe && !props.isCollapsed && props.isMobile) {
        closeSidebar();
    }
};

// Lock body scroll when mobile sidebar is open
onMounted(() => {
    console.log('[Sidebar component] props → isOpen:', props.isOpen, '| isCollapsed:', props.isCollapsed, '| isMobile:', props.isMobile);
    if (props.isMobile && props.isOpen) {
        document.body.style.overflow = 'hidden';
    }

    const savedScroll = sessionStorage.getItem(SCROLL_STORAGE_KEY);
    if (savedScroll !== null && navScrollRef.value) {
        navScrollRef.value.scrollTop = parseInt(savedScroll, 10) || 0;
    }
});

onUnmounted(() => {
    document.body.style.overflow = 'unset';
    saveScrollPosition();
});
</script>

<template>
    <!-- Mobile Overlay -->
    <div 
        v-if="isMobile && isOpen" 
        class="fixed inset-0 bg-background/80 backdrop-blur-sm z-40 transition-all duration-500 ease-in-out animate-in fade-in"
        @click="closeSidebar"
    ></div>

    <!-- Sidebar -->
    <!--
      Positioning uses logical properties (`start-0`, `border-e`) so the
      sidebar anchors to the inline-start side of the page: LEFT in LTR,
      RIGHT in RTL. The hide-on-mobile transform is mirrored too —
      `-translate-x-full` slides off the LTR start edge, and the `rtl:`
      override slides off the RTL start edge.
    -->
    <aside
        :class="[
            'fixed inset-y-0 start-0 flex h-full flex-col bg-sidebar text-sidebar-foreground border-e border-sidebar-border sidebar-transition-smooth',
            {
                'w-72': !isCollapsed,
                'w-20': isCollapsed && !isMobile,
                'z-50': isMobile,
                'z-30': !isMobile,
                'shadow-2xl': isMobile && isOpen,
                '-translate-x-full rtl:translate-x-full': isMobile && !isOpen,
                'translate-x-0': !isMobile || isOpen,
            }
        ]"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
    >
        <!-- Header Section (Logo Area) -->
        <div class="flex h-16 items-center border-b border-sidebar-border flex-shrink-0 px-4 justify-between">
            <div class="flex items-center gap-3">
                <slot name="logo">
                    <div :class="['font-bold text-sidebar-primary transition-all duration-300', isCollapsed ? 'text-xl' : 'text-2xl']">
                        {{ isCollapsed ? 'M' : 'Membership' }}
                    </div>
                </slot>
            </div>
            
            <!-- Toggle button for desktop -->
            <button 
                v-if="!isMobile"
                @click="toggleSidebar"
                class="p-2 rounded-md text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent focus:outline-none focus:ring-2 focus:ring-sidebar-ring transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isCollapsed ? 'M13 5l7 7-7 7M5 5l7 7-7 7' : 'M11 19l-7-7 7-7m8 14l-7-7 7-7'" />
                </svg>
            </button>
            
            <!-- Close button for mobile -->
            <button 
                v-if="isMobile"
                @click="closeSidebar"
                class="p-2 rounded-md text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent focus:outline-none focus:ring-2 focus:ring-sidebar-ring transition-colors"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Scrollable Navigation Area -->
        <div ref="navScrollRef" class="flex-1 min-h-0 overflow-y-auto sidebar-scrollbar" @scroll="saveScrollPosition">
            <nav class="flex flex-col p-4 space-y-1">
                <slot />
            </nav>
        </div>

        <!-- Bottom Sticky Section -->
        <div class="border-t border-sidebar-border p-4 flex-shrink-0 space-y-3">
            <slot name="footer" />
        </div>
    </aside>
</template>

