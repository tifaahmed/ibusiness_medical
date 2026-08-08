<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';

defineProps({
    statistics: {
        type: Array,
        required: true,
        default: () => [],
    },
    recentMembers: {
        type: Array,
        required: true,
        default: () => [],
    },
    recentSales: {
        type: Array,
        required: true,
        default: () => [],
    },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.dashboard || {});

const getStatusColor = (status) => {
    const colors = {
        active: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        inactive: 'bg-gray-100 text-gray-800 border-gray-200',
    };
    return colors[status] || colors.inactive;
};

const statusLabel = (status) => {
    const map = {
        active: t.value.active || 'Active',
        inactive: t.value.inactive || 'Inactive',
        pending: t.value.pending || 'Pending',
    };
    return map[status] || status;
};
</script>

<template>
    <AppLayout :title="t.title || 'Dashboard'">
        <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
            <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
                <!-- Page Header -->
                <PageHeader
                    :title="t.title || 'Dashboard'"
                    :subtitle="t.subtitle || 'Membership App'"
                />

                <!-- Statistics Grid -->
                <div class="grid gap-2 sm:gap-3 md:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        v-for="(stat, index) in statistics"
                        :key="index"
                        v-bind="stat"
                    />
                </div>

                <!-- Recent Members Card -->
                <div class="card-modern overflow-hidden">
                    <div class="flex flex-row items-center justify-between py-2 sm:py-3 md:py-4 px-3 sm:px-4 md:px-6 border-b border-border">
                        <h2 class="title-golden">
                            <svg class="title-icon w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="text-sm sm:text-base">{{ t.recent_members || 'Recent Members' }}</span>
                        </h2>
                        <a
                            :href="route('admin.user.membership.list')"
                            class="group inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-semibold text-primary shadow-sm transition-all duration-200 hover:bg-primary hover:text-primary-foreground hover:border-primary hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        >
                            <span>{{ t.view_all || 'View All' }}</span>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                            >
                                <path d="M5 12h14" />
                                <path d="m12 5 7 7-7 7" />
                            </svg>
                        </a>
                    </div>


                    <div class="p-0">
                        <!-- Table -->
                        <div v-if="recentMembers && recentMembers.length > 0" class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-muted/50 border-b border-border">
                                    <tr>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                            {{ t.member || 'Member' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider hidden md:table-cell">
                                            {{ t.email || 'Email' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                            {{ t.status || 'Status' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider hidden sm:table-cell">
                                            {{ t.joined || 'Joined' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider w-[70px]">
                                            {{ t.actions || 'Actions' }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="member in recentMembers"
                                        :key="member.id"
                                        class="hover:bg-accent/10 transition-colors"
                                    >
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2 sm:gap-3">
                                                <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-sidebar-primary text-sidebar-primary-foreground flex items-center justify-center font-semibold text-xs sm:text-sm">
                                                    {{ member.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) }}
                                                </div>
                                                <div class="font-medium text-xs sm:text-sm text-foreground">
                                                    {{ member.name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-muted-foreground hidden md:table-cell">
                                            {{ member.email }}
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-center">
                                            <span :class="['inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusColor(member.status)]">
                                                {{ statusLabel(member.status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-center text-xs sm:text-sm text-muted-foreground hidden sm:table-cell">
                                            {{ member.joinedAt }}
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-center">
                                            <a
                                                :href="member.slug ? route('admin.user.membership.show', member.slug) : route('admin.user.membership.list')"
                                                class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-md border border-border hover:bg-accent transition-colors"
                                                :title="t.view_details || 'View Details'"
                                            >
                                                <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-golden-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Empty State -->
                        <div v-else class="p-8 sm:p-12 text-center">
                            <p class="text-sm sm:text-base text-muted-foreground">{{ t.no_recent_members || 'No recent members found.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales Card -->
                <div class="card-modern overflow-hidden">
                    <div class="flex flex-row items-center justify-between py-2 sm:py-3 md:py-4 px-3 sm:px-4 md:px-6 border-b border-border">
                        <h2 class="title-golden">
                            <svg class="title-icon w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm sm:text-base">{{ t.recent_sales || 'Recent Sales' }}</span>
                        </h2>
                        <a
                            :href="route('admin.sales.list')"
                            class="group inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 sm:px-4 sm:py-1.5 text-xs sm:text-sm font-semibold text-primary shadow-sm transition-all duration-200 hover:bg-primary hover:text-primary-foreground hover:border-primary hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        >
                            <span>{{ t.view_all || 'View All' }}</span>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                            >
                                <path d="M5 12h14" />
                                <path d="m12 5 7 7-7 7" />
                            </svg>
                        </a>
                    </div>

                    <div class="p-0">
                        <div v-if="recentSales && recentSales.length > 0" class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-muted/50 border-b border-border">
                                    <tr>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                            {{ t.sales_image || 'Image' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                            {{ t.sales_name || 'Name' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider hidden sm:table-cell">
                                            {{ t.created || 'Created' }}
                                        </th>
                                        <th class="px-3 sm:px-4 md:px-6 py-2 sm:py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider w-[70px]">
                                            {{ t.actions || 'Actions' }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="sale in recentSales"
                                        :key="sale.id"
                                        class="hover:bg-accent/10 transition-colors"
                                    >
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-muted overflow-hidden border border-border">
                                                <img
                                                    v-if="sale.image"
                                                    :src="sale.image"
                                                    :alt="sale.name"
                                                    class="w-full h-full object-cover"
                                                    loading="lazy"
                                                />
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-muted-foreground">
                                                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                                    <circle cx="9" cy="9" r="2"/>
                                                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap">
                                            <div class="font-medium text-xs sm:text-sm text-foreground">
                                                {{ sale.name }}
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-center text-xs sm:text-sm text-muted-foreground hidden sm:table-cell">
                                            {{ sale.created_at }}
                                        </td>
                                        <td class="px-3 sm:px-4 md:px-6 py-3 sm:py-4 whitespace-nowrap text-center">
                                            <a
                                                :href="route('admin.sales.edit', sale.id)"
                                                class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-md border border-border hover:bg-accent transition-colors"
                                                :title="t.view_details || 'View Details'"
                                            >
                                                <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-golden-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="p-8 sm:p-12 text-center">
                            <p class="text-sm sm:text-base text-muted-foreground">{{ t.no_recent_sales || 'No recent sales entries found.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

