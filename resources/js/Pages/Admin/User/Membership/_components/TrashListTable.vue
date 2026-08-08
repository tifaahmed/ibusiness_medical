<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm" :key="`trash-table-${locale}`">
    <div v-if="members?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[220px]">
                  <button
                    type="button"
                    data-slot="button"
                    @click="toggleSortByName"
                    :class="[
                      'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                      sort === 'name' ? 'text-golden-yellow' : ''
                    ]"
                    :title="sort === 'name' ? (direction === 'asc' ? (t.sorted_asc_tooltip || 'Sorted A-Z') : (t.sorted_desc_tooltip || 'Sorted Z-A')) : (t.sort_by_name_tooltip || 'Sort by name')"
                  >
                    {{ t.column_user_details || 'User Details' }}
                    <svg v-if="sort === 'name' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                    <svg v-else-if="sort === 'name' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                  {{ t.column_status || 'Status' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center hidden md:table-cell">
                  {{ t.column_partner || 'Partner' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center hidden lg:table-cell">
                  {{ t.column_creator || 'Creator' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center hidden md:table-cell">
                  <button
                    type="button"
                    data-slot="button"
                    @click="toggleSortByCreatedAt"
                    :class="[
                      'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                      sort === 'created_at' ? 'text-golden-yellow' : ''
                    ]"
                    :title="sort === 'created_at' ? (direction === 'asc' ? (t.sorted_asc_date_tooltip || 'Sorted oldest first') : (t.sorted_desc_date_tooltip || 'Sorted newest first')) : (t.sort_by_created_at_tooltip || 'Sort by created date')"
                  >
                    {{ t.column_created_date || 'Created Date' }}
                    <svg v-if="sort === 'created_at' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                    <svg v-else-if="sort === 'created_at' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center hidden md:table-cell">
                  <button
                    type="button"
                    data-slot="button"
                    @click="toggleSortByUpdatedAt"
                    :class="[
                      'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                      sort === 'updated_at' ? 'text-golden-yellow' : ''
                    ]"
                    :title="sort === 'updated_at' ? (direction === 'asc' ? (t.sorted_asc_date_tooltip || 'Sorted oldest first') : (t.sorted_desc_date_tooltip || 'Sorted newest first')) : (t.sort_by_updated_at_tooltip || 'Sort by updated date')"
                  >
                    {{ t.column_updated_date || 'Updated Date' }}
                    <svg v-if="sort === 'updated_at' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                    <svg v-else-if="sort === 'updated_at' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                  {{ t.column_actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="member in members.data"
                :key="member.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50 opacity-75"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[220px]">
                  <div class="flex items-start gap-2 sm:gap-3">
                    <div class="flex-shrink-0">
                      <img
                        :src="member.avatar_url"
                        :alt="member.name"
                        class="w-9 h-9 sm:w-11 sm:h-11 rounded-full object-cover"
                      />
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden space-y-0.5">
                      <span class="font-semibold text-sm sm:text-base text-foreground block truncate w-full" :title="member.name">
                        {{ member.name }}
                      </span>
                      <div v-if="member.phone" class="text-xs text-muted-foreground truncate flex items-center gap-1" :title="member.phone">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-60 shrink-0">
                          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <span class="truncate">{{ member.phone }}</span>
                      </div>
                      <div v-if="member.membership?.membership_number" class="text-xs text-muted-foreground font-mono truncate flex items-center gap-1" :title="member.membership.membership_number">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-60 shrink-0">
                          <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                          <line x1="2" x2="22" y1="10" y2="10"></line>
                        </svg>
                        <span class="truncate">{{ member.membership.membership_number }}</span>
                      </div>
                      <div v-if="member.email" class="text-xs text-muted-foreground truncate flex items-center gap-1" :title="member.email">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-60 shrink-0">
                          <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                        <span class="truncate">{{ member.email }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex flex-col items-center gap-1.5">
                    <span
                      data-slot="badge"
                      :class="[
                        'relative inline-flex items-center rounded-md border-transparent px-1.5 py-0.5 h-auto w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden font-roboto-slab font-medium tracking-[0.025em] gap-1.5 transition-[all] duration-300 ease-[ease] text-xs',
                        member.membership?.is_active
                          ? 'bg-emerald-500 text-white'
                          : 'bg-gray-500 text-white'
                      ]"
                    >
                      {{ member.membership?.is_active ? (t.status_active || 'Active') : (t.status_inactive || 'Inactive') }}
                    </span>
                    <span
                      data-slot="badge"
                      :class="[
                        'relative inline-flex items-center rounded-md border-transparent px-1.5 py-0.5 h-auto w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden font-roboto-slab font-medium tracking-[0.025em] gap-1.5 transition-[all] duration-300 ease-[ease] text-xs',
                        member.membership?.is_visible
                          ? 'bg-blue-500 text-white'
                          : 'bg-orange-500 text-white'
                      ]"
                    >
                      {{ member.membership?.is_visible ? (t.visibility_visible || 'Visible') : (t.visibility_hidden || 'Hidden') }}
                    </span>
                    <span
                      data-slot="badge"
                      :class="[
                        'relative inline-flex items-center rounded-md border-transparent px-1.5 py-0.5 h-auto w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden font-roboto-slab font-medium tracking-[0.025em] gap-1.5 transition-[all] duration-300 ease-[ease] text-xs',
                        member.membership?.is_paid
                          ? 'bg-green-600 text-white'
                          : 'bg-red-500 text-white'
                      ]"
                    >
                      {{ member.membership?.is_paid ? (t.payment_paid || 'Paid') : (t.payment_unpaid || 'Unpaid') }}
                    </span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden md:table-cell">
                  <div v-if="member.membership?.partner_name" class="flex items-center justify-center gap-2">
                    <img
                      v-if="member.membership?.partner_image"
                      :src="member.membership.partner_image"
                      :alt="member.membership.partner_name"
                      class="w-6 h-6 rounded-full object-cover flex-shrink-0"
                    />
                    <span class="text-foreground text-xs sm:text-sm truncate" :title="member.membership.partner_name">
                      {{ member.membership.partner_name }}
                    </span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs sm:text-sm">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden lg:table-cell">
                  <div v-if="member.membership?.creator" class="flex flex-col items-center leading-tight">
                    <span class="text-foreground text-xs sm:text-sm font-medium truncate max-w-[160px]" :title="member.membership.creator.name">
                      {{ member.membership.creator.name }}
                    </span>
                    <span v-if="member.membership.creator.email" class="text-muted-foreground text-[10px] sm:text-xs truncate max-w-[160px]" :title="member.membership.creator.email">
                      {{ member.membership.creator.email }}
                    </span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs sm:text-sm">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden md:table-cell">
                  <span class="text-muted-foreground text-xs sm:text-sm" :title="formatFullDate(member.created_at)">
                    {{ formatDate(member.created_at) }}
                  </span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden md:table-cell">
                  <span class="text-muted-foreground text-xs sm:text-sm" :title="formatFullDate(member.membership?.updated_at || member.updated_at)">
                    {{ formatDate(member.membership?.updated_at || member.updated_at) }}
                  </span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      :disabled="!member.slug"
                      @click="member.slug && $emit('restore', member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-green-600 hover:!bg-green-600/10 hover:!text-green-600 dark:text-green-400"
                      :title="t.action_restore || 'Restore'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw w-3 h-3">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                      </svg>
                    </button>
                    <button
                      :disabled="!member.slug"
                      @click="member.slug && $emit('force-delete', member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.action_force_delete || 'Permanently Delete'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-3 h-3">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="border-t border-border flex-shrink-0">
        <div class="border-t border-border/50 px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 lg:gap-4 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0 min-w-0">
              <span class="hidden sm:inline">{{ paginationLabel }}</span>
              <span class="sm:hidden">{{ paginationLabelMobile }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.rows_per_page_label || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.per_page_label_mobile || 'Per page' }}</p>
              <select
                :value="members.meta?.per_page || 15"
                @change="handlePerPageChange"
                dir="ltr"
                translate="no"
                class="border-input data-[placeholder]:text-gray-foreground [&_svg:not([class*='text-'])]:text-gray-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
              >
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination
                v-if="members?.meta?.links?.length > 0"
                :links="members?.meta?.links"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M3 6h18"></path>
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
            <line x1="10" x2="10" y1="11" y2="17"></line>
            <line x1="14" x2="14" y1="11" y2="17"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.trash_empty_title || 'Trash is Empty' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.trash_empty_message || 'No deleted memberships found. Deleted items will appear here.' }}</p>
        <Link
          :href="route('admin.user.membership.list')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-4 h-4">
            <path d="m12 19-7-7 7-7"></path>
            <path d="M19 12H5"></path>
          </svg>
          {{ t.trash_back_to_list || 'Back to List' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

const props = defineProps({
  members: {
    type: Object,
    required: true
  }
});

defineEmits(['restore', 'force-delete']);

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');

const sort = computed(() => page.props.sort || null);
const direction = computed(() => page.props.direction || 'asc');

// Reuse the regular member-list translations so the trash table inherits the
// same headers/badges/pagination labels without needing a duplicate keyset.
const t = computed(() => page.props.translations?.admin?.member_list?.table || {});

const paginationLabel = computed(() => {
  const tpl = t.value.pagination_showing || 'Showing :from to :to of :total results';
  return tpl
    .replace(':from', props.members.meta?.from || 0)
    .replace(':to', props.members.meta?.to || 0)
    .replace(':total', props.members.meta?.total || 0);
});
const paginationLabelMobile = computed(() => {
  const tpl = t.value.pagination_showing_mobile || ':from-:to/:total';
  return tpl
    .replace(':from', props.members.meta?.from || 0)
    .replace(':to', props.members.meta?.to || 0)
    .replace(':total', props.members.meta?.total || 0);
});

const cycleSort = (column) => {
  const currentUrl = new URL(window.location.href);
  if (sort.value !== column) {
    currentUrl.searchParams.set('sort', column);
    currentUrl.searchParams.set('direction', 'asc');
  } else if (direction.value === 'asc') {
    currentUrl.searchParams.set('sort', column);
    currentUrl.searchParams.set('direction', 'desc');
  } else {
    currentUrl.searchParams.delete('sort');
    currentUrl.searchParams.delete('direction');
  }
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: true,
  });
};

const toggleSortByName = () => cycleSort('name');
const toggleSortByCreatedAt = () => cycleSort('created_at');
const toggleSortByUpdatedAt = () => cycleSort('updated_at');

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatFullDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>
