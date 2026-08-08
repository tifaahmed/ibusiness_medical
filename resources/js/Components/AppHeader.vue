<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const props = defineProps({
    sidebarCollapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();
const showSearch = ref(false);
const searchQuery = ref('');

const user = computed(() => page.props.auth?.user);
const currentLocale = computed(() => page.props.locale || 'en');
const t = computed(() => page.props.translations?.admin?.header || {});
// Permission helpers — mirrors AppLayout's gating so the header dropdown
// hides the Profile link for admins that don't hold `manage profile`.
const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const userRoles = computed(() => page.props.auth?.user?.roles || []);
const isSuperAdmin = computed(() => userRoles.value.includes('super_admin'));
const can = (permission) => isSuperAdmin.value || userPermissions.value.includes(permission);

const toggleSidebar = () => {
    emit('toggle-sidebar');
};

const toggleSearch = () => {
    showSearch.value = !showSearch.value;
};

const switchLanguage = () => {
    const newLocale = currentLocale.value === 'en' ? 'ar' : 'en';
    router.get(route('lang.switch', { locale: newLocale }), {}, {
        preserveState: false,
        preserveScroll: true,
    });
};

const logout = () => {
    router.post(route('logout'));
};

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
    <header class="sticky top-0 z-30 border-b border-border bg-background backdrop-blur supports-[backdrop-filter]:bg-background/90">
        <div class="flex h-16 items-center justify-between px-4 md:px-6 lg:px-8">
            <!-- Left Section: Menu + Search -->
            <div class="flex items-center gap-3 flex-1">
                <!-- Menu Toggle -->
                <button
                    @click="toggleSidebar"
                    class="shrink-0 p-2 hover:bg-accent rounded-md transition-colors"
                >
                    <svg v-if="sidebarCollapsed" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
 


            </div>

            <!-- Right Section: Actions -->
            <div class="flex items-center gap-2 md:gap-3">
                <!-- Language Toggle -->
                <button
                    @click="switchLanguage"
                    class="flex items-center gap-1.5 px-2.5 py-2 rounded-lg hover:bg-accent transition-colors text-sm font-medium"
                    :title="currentLocale === 'en' ? (t.switch_to_arabic || 'Switch to Arabic') : (t.switch_to_english || 'Switch to English')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-muted-foreground">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                        <path d="M2 12h20"></path>
                    </svg>
                    <span class="hidden sm:inline text-muted-foreground">{{ currentLocale === 'en' ? 'AR' : 'EN' }}</span>
                </button>

                <!-- User Profile Dropdown -->
                <Dropdown align="right" width="56">
                    <template #trigger>
                        <button
                            class="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-accent transition-colors bg-card h-11"
                        >
                            <div class="h-8 w-8 rounded-full bg-sidebar-primary text-sidebar-primary-foreground flex items-center justify-center font-semibold text-sm">
                                {{ user ? getUserInitials(user.name) : 'U' }}
                            </div>
                            <div class="hidden lg:block text-left">
                                <div class="font-medium leading-none text-sm">{{ user?.name }}</div>
                                <div class="text-muted-foreground text-xs mt-0.5 truncate max-w-[150px]">{{ user?.email }}</div>
                            </div>
                            <svg class="h-4 w-4 text-muted-foreground hidden lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </template>

                    <template #content>
                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ t.manage_account || 'Manage Account' }}
                        </div>

                        <DropdownLink v-if="can('manage profile')" :href="route('profile.show')">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>{{ t.my_profile || 'My Profile' }}</span>
                            </div>
                        </DropdownLink>

                        <DropdownLink v-if="$page.props.jetstream?.hasApiFeatures" :href="route('api-tokens.index')">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <span>{{ t.api_tokens || 'API Tokens' }}</span>
                            </div>
                        </DropdownLink>

                        <div class="border-t border-gray-200" />

                        <form @submit.prevent="logout">
                            <DropdownLink as="button">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>{{ t.logout || 'Log Out' }}</span>
                                </div>
                            </DropdownLink>
                        </form>
                    </template>
                </Dropdown>
            </div>
        </div>

        <!-- Mobile Search Overlay -->
        <div v-if="showSearch" class="md:hidden border-t border-border p-4 bg-background">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    class="pl-9 h-11 w-full rounded-md border border-border bg-card px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :placeholder="t.search_placeholder || 'Search...'"
                    autofocus
                />
            </div>
        </div>
    </header>
</template>

