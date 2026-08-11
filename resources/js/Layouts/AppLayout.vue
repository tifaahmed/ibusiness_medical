<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import AppHeader from '@/Components/AppHeader.vue';
import Sidebar from '@/Components/Sidebar.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import SidebarDropdown from '@/Components/SidebarDropdown.vue';
import SidebarSubLink from '@/Components/SidebarSubLink.vue';
import SidebarSection from '@/Components/SidebarSection.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({
    title: String,
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

// Brand name comes from APP_NAME via the Inertia share, so it tracks .env
// without needing a frontend rebuild.
const appName = computed(() => page.props.appName || 'Laravel');

const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const userRoles = computed(() => page.props.auth?.user?.roles || []);
const isSuperAdmin = computed(() => userRoles.value.includes('super_admin'));
const can = (permission) => isSuperAdmin.value || userPermissions.value.includes(permission);
// Helper for "any of these permissions" — used by the membership / cards
// nav entries where three permissions all unlock the same route group.
const canAny = (...permissions) => permissions.some((p) => can(p));

const showingNavigationDropdown = ref(false);
const sidebarCollapsed = ref(false);
const isMobile = ref(false);

const STORAGE_KEY = 'membership-sidebar-collapsed';

// Load sidebar state from localStorage
onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    console.log('[Sidebar] localStorage key:', STORAGE_KEY, '| saved value:', saved);
    if (saved !== null) {
        sidebarCollapsed.value = saved === 'true';
    }

    // Check if mobile
    checkMobile();
    window.addEventListener('resize', checkMobile);

    console.log('[Sidebar] onMounted state → isMobile:', isMobile.value, '| sidebarCollapsed:', sidebarCollapsed.value, '| sidebarOpen:', sidebarOpen.value);
    console.log('[Sidebar] window.innerWidth:', window.innerWidth);
});

// Check mobile breakpoint
const checkMobile = () => {
    const mobile = window.innerWidth < 1024; // lg breakpoint
    isMobile.value = mobile;

    // Auto-collapse on mobile
    if (mobile) {
        sidebarCollapsed.value = true;
    }

    console.log('[Sidebar] checkMobile → innerWidth:', window.innerWidth, '| isMobile:', mobile, '| sidebarCollapsed:', sidebarCollapsed.value);
};

// Save sidebar state to localStorage
watch(sidebarCollapsed, (newValue) => {
    if (!isMobile.value) {
        localStorage.setItem(STORAGE_KEY, newValue.toString());
    }
});

// Lock body scroll when mobile sidebar is open
watch([isMobile, sidebarCollapsed], ([mobile, collapsed]) => {
    if (mobile && !collapsed) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'unset';
    }
});

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const closeSidebar = () => {
    if (isMobile.value) {
        sidebarCollapsed.value = true;
    }
};

const sidebarOpen = computed(() => !sidebarCollapsed.value);

const mainContentClass = computed(() => {
    // `ms-*` (margin-inline-start) auto-flips with `dir="rtl"` so the
    // gutter left for the sidebar lands on the correct side in Arabic.
    if (isMobile.value) {
        return 'ms-0';
    }
    return sidebarCollapsed.value ? 'ms-20' : 'ms-72';
});

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

// Get user initials for avatar fallback
const getUserInitials = (name) => {
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="flex min-h-screen bg-background relative w-full max-w-full overflow-x-hidden">
            <!-- Sidebar -->
            <Sidebar 
                :is-open="sidebarOpen"
                :is-collapsed="sidebarCollapsed"
                :is-mobile="isMobile"
                @close="closeSidebar"
                @toggle="toggleSidebar"
            >
                <!-- Logo Slot -->
                <template #logo>
                    <Link :href="route('admin.dashboard')" class="flex items-center gap-2" :class="sidebarCollapsed ? 'justify-center' : ''" preserve-scroll>
                        <img :src="$page.props.appLogo" :alt="appName" class="transition-all duration-300 rounded" :class="sidebarCollapsed ? 'h-8 w-8' : 'h-10 w-auto'" />
                        <span v-if="!sidebarCollapsed" class="font-bold text-base tracking-wider animate-text-fizzy-sidebar">{{ appName }}</span>
                    </Link>
                </template>

                <!-- Main Navigation -->
                <SidebarSection :title="t.sidebar?.main_menu || 'Main Menu'" :is-collapsed="sidebarCollapsed" />
                
                <SidebarLink 
                    :href="route('admin.dashboard')" 
                    :active="route().current('admin.dashboard')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-dashboard-glow-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.dashboard || 'Dashboard' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="canAny('manage memberships', 'manage own memberships', 'manage partner memberships')"
                    :href="route('admin.user.membership.list')"
                    :active="route().current('admin.user.membership.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.members || 'Members' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('view member active histories')"
                    :href="route('admin.active-history.list')"
                    :active="route().current('admin.active-history.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <circle cx="8" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.active_status_history || 'Active Status History' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="canAny('manage membership usages', 'manage own membership usages')"
                    :href="route('admin.membership-usage.list')"
                    :active="route().current('admin.membership-usage.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.membership_usages || 'Membership Usages' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="canAny('view membership card patches', 'create membership card patches', 'view own membership card patches', 'create own membership card patches', 'view partner membership card patches', 'create partner membership card patches')"
                    :href="route('admin.membership-card-patches.list')"
                    :active="route().current('admin.membership-card-patches.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="2" y="5" width="20" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <line x1="2" y1="10" x2="22" y2="10" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <line x1="6" y1="15" x2="10" y2="15" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.membership_card_patches || 'Membership Card Patches' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage card templates')"
                    :href="route('admin.card-templates.index')"
                    :active="route().current('admin.card-templates.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="16" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <circle cx="8.5" cy="9.5" r="1.8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <line x1="13" y1="9" x2="18" y2="9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <line x1="13" y1="13" x2="18" y2="13" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <line x1="6" y1="16" x2="18" y2="16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.card_templates || 'Card Templates' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="canAny('manage member payments', 'manage own member payments', 'manage partner member payments')"
                    :href="route('admin.member-payment.list')"
                    :active="route().current('admin.member-payment.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.member_payments || 'Member Payments' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="isSuperAdmin"
                    :href="route('admin.client-error-logs.index')"
                    :active="route().current('admin.client-error-logs.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.client_error_logs || 'App Error Logs' }}</template>
                </SidebarLink>

                <!-- Management Section -->
                <SidebarSection
                    :title="t.sidebar?.management || 'Management'"
                    :is-collapsed="sidebarCollapsed"
                />

                <SidebarLink
                    v-if="can('manage governorates')"
                    :href="route('admin.governorate.list')"
                    :active="route().current('admin.governorate.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.governorates || 'Governorates' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage facilities')"
                    :href="route('admin.facility-type.list')"
                    :active="route().current('admin.facility-type.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.facility_types || 'Facility Types' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage facilities')"
                    :href="route('admin.facility.list')"
                    :active="route().current('admin.facility.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.facilities || 'Facilities' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage facility branches')"
                    :href="route('admin.facility-branch.list')"
                    :active="route().current('admin.facility-branch.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.facility_branches || 'Facility Branches' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage offers')"
                    :href="route('admin.offer.list')"
                    :active="route().current('admin.offer.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.offers || 'Offers' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage contracts')"
                    :href="route('admin.contract.list')"
                    :active="route().current('admin.contract.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                            <polyline stroke-linecap="round" stroke-linejoin="round" stroke-width="2" points="14 2 14 8 20 8" />
                            <line stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="16" y1="13" x2="8" y2="13" />
                            <line stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.contracts || 'Contracts' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage news tickers')"
                    :href="route('admin.news-ticker.list')"
                    :active="route().current('admin.news-ticker.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.news_tickers || 'News Tickers' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage partners')"
                    :href="route('admin.partner.list')"
                    :active="route().current('admin.partner.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.partners || 'Partners' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage partner offers')"
                    :href="route('admin.partner-offer.list')"
                    :active="route().current('admin.partner-offer.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2H2v10l9.29 9.29a2 2 0 0 0 2.83 0l8.17-8.17a2 2 0 0 0 0-2.83L12 2z"/>
                            <circle cx="7" cy="7" r="1.5" fill="currentColor"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.partner_offers || 'Partner Offers' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage partner offers')"
                    :href="route('admin.partner-offer-request.list')"
                    :active="route().current('admin.partner-offer-request.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.partner_offer_requests || 'Offer Requests' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage sales')"
                    :href="route('admin.sales.list')"
                    :active="route().current('admin.sales.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.sales || 'Sales' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage services')"
                    :href="route('admin.service.list')"
                    :active="route().current('admin.service.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="7" width="18" height="13" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"/>
                            <line x1="3" y1="12" x2="21" y2="12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.services || 'Services' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage services')"
                    :href="route('admin.service-type.list')"
                    :active="route().current('admin.service-type.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7a1 1 0 11-2 0 1 1 0 012 0z"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.serviceTypes || 'Service Categories' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage services')"
                    :href="route('admin.tag.list')"
                    :active="route().current('admin.tag.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-pulse"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.24-4.24a2 2 0 0 0 .01-2.83z"/>
                            <circle cx="7.5" cy="7.5" r="1.2"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.tags || 'Tags' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage companies')"
                    :href="route('admin.company.list')"
                    :active="route().current('admin.company.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect width="20" height="14" x="2" y="7" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.companies || 'Companies' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage users')"
                    :href="route('admin.admin-users.index')"
                    :active="route().current('admin.admin-users.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-7 9a7 7 0 0114 0v1H5v-1z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 8l2 2-2 2" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.admin_users || 'Admin Users' }}</template>
                </SidebarLink>

                <SidebarLink
                    v-if="can('manage roles')"
                    :href="route('admin.roles.index')"
                    :active="route().current('admin.roles.*')"
                    :is-collapsed="sidebarCollapsed"
                    icon-animation="animate-icon-breathe"
                    @click="closeSidebar"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                    </template>
                    <template #label>{{ t.sidebar?.roles_permissions || 'Roles & Permissions' }}</template>
                </SidebarLink>

                <SidebarDropdown
                    v-if="can('manage profile') || $page.props.jetstream.hasApiFeatures"
                    :label="t.sidebar?.settings || 'Settings'"
                    :is-collapsed="sidebarCollapsed"
                    :active="route().current('profile.*') || route().current('api-tokens.*')"
                    icon-animation="animate-rotate-slow"
                    :auto-open="['/profile', '/api-tokens']"
                >
                    <template #icon>
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </template>

                    <SidebarSubLink
                        v-if="can('manage profile')"
                        :href="route('profile.show')"
                        :active="route().current('profile.show')"
                    >
                        {{ t.sidebar?.profile || 'Profile' }}
                    </SidebarSubLink>

                    <SidebarSubLink
                        v-if="$page.props.jetstream.hasApiFeatures"
                        :href="route('api-tokens.index')"
                        :active="route().current('api-tokens.index')"
                    >
                        {{ t.sidebar?.api_tokens || 'API Tokens' }}
                    </SidebarSubLink>
                </SidebarDropdown>

                <!-- Footer Section -->
                <template #footer>
                    <!-- User Profile Card -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                class="w-full flex items-center gap-3 p-3 rounded-lg bg-gradient-to-r from-secondary/20 to-secondary/15 border border-secondary/30 hover:border-secondary/40 transition-all duration-300 shadow-lg hover:shadow-xl"
                            >
                                <div class="h-8 w-8 rounded-full bg-sidebar-primary text-sidebar-primary-foreground flex items-center justify-center font-semibold text-sm">
                                    {{ getUserInitials($page.props.auth.user.name) }}
                                </div>
                                
                                <div v-if="!sidebarCollapsed" class="flex-1 min-w-0 text-left">
                                    <p class="text-sm font-medium text-sidebar-foreground truncate">
                                        {{ $page.props.auth.user.name }}
                                    </p>
                                    <p class="text-xs text-sidebar-foreground/60 truncate">
                                        {{ $page.props.auth.user.email }}
                                    </p>
                                </div>
                                
                                <svg v-if="!sidebarCollapsed" class="w-4 h-4 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ t.sidebar?.manage_account || 'Manage Account' }}
                            </div>

                            <DropdownLink v-if="can('manage profile')" :href="route('profile.show')">
                                {{ t.sidebar?.profile || 'Profile' }}
                            </DropdownLink>

                            <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                {{ t.sidebar?.api_tokens || 'API Tokens' }}
                            </DropdownLink>

                            <div class="border-t border-gray-200" />

                            <form @submit.prevent="logout">
                                <DropdownLink as="button">
                                    {{ t.sidebar?.logout || 'Log Out' }}
                                </DropdownLink>
                            </form>
                        </template>
                    </Dropdown>
                </template>
            </Sidebar>

            <!-- Main Content Area -->
            <div :class="['flex-1 flex flex-col transition-all duration-500 min-h-screen w-full max-w-full overflow-x-hidden', mainContentClass]">
                <!-- New App Header -->
                <AppHeader
                    :sidebar-collapsed="sidebarCollapsed"
                    @toggle-sidebar="toggleSidebar"
                />

                <!-- Page Content - natural scroll on all screen sizes -->
                <main class="flex-1 w-full max-w-full">
                    <div class="max-w-7xl mx-auto w-full">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
