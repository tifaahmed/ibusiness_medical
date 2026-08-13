<template>
    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm" :key="`member-table-${locale}`">
      <div v-if="members?.data?.length > 0">
        <div class="overflow-x-auto">
          <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
            <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
              <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
                <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                  <th v-if="cols.details" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[220px]">
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
                  <th v-if="cols.status" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                    {{ t.column_status || 'Status' }}
                  </th>
                  <th v-if="cols.partner" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center">
                    {{ t.column_partner || 'Partner' }}
                  </th>
                  <th v-if="cols.creator" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center">
                    {{ t.column_creator || 'Creator' }}
                  </th>
                  <th v-if="cols.sales" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center">
                    {{ t.column_sales || 'Sales' }}
                  </th>
                  <th v-if="cols.source" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center">
                    {{ t.column_source || 'Source' }}
                  </th>
                  <th v-if="cols.card" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center">
                    {{ t.column_card || 'Card' }}
                  </th>
                  <th v-if="cols.created" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center">
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
                  <th v-if="cols.updated" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center">
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
                  <th v-if="cols.last_active_history" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center">
                    {{ t.column_last_active_history || 'Last Activation' }}
                  </th>
                  <th v-if="cols.registration_date" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center">
                    <button
                      type="button"
                      data-slot="button"
                      @click="toggleSortByRegistrationDate"
                      :class="[
                        'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                        sort === 'registration_date' ? 'text-golden-yellow' : ''
                      ]"
                      :title="sort === 'registration_date' ? (direction === 'asc' ? (t.sorted_asc_date_tooltip || 'Sorted oldest first') : (t.sorted_desc_date_tooltip || 'Sorted newest first')) : (t.sort_by_registration_date_tooltip || 'Sort by registration date')"
                    >
                      {{ t.column_registration_date || 'Reg. Date' }}
                      <svg v-if="sort === 'registration_date' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <path d="m3 8 4-4 4 4"></path>
                        <path d="M7 4v16"></path>
                      </svg>
                      <svg v-else-if="sort === 'registration_date' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
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
                  <th v-if="cols.dates" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center">
                    {{ t.column_dates || 'Member Dates' }}
                  </th>
                  <th v-if="cols.amount_paid" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center">
                    {{ t.column_amount_paid || 'Amount Paid' }}
                  </th>
                  <th v-if="cols.days_covered" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center">
                    <button
                      type="button"
                      data-slot="button"
                      @click="toggleSortByDaysCovered"
                      :class="[
                        'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                        sort === 'days_covered' ? 'text-golden-yellow' : ''
                      ]"
                      :title="sort === 'days_covered' ? (direction === 'asc' ? (t.sorted_asc_tooltip || 'Sorted low to high') : (t.sorted_desc_tooltip || 'Sorted high to low')) : (t.sort_by_days_covered_tooltip || 'Sort by days covered')"
                    >
                      {{ t.column_days_covered || 'Days Cov.' }}
                      <svg v-if="sort === 'days_covered' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <path d="m3 8 4-4 4 4"></path>
                        <path d="M7 4v16"></path>
                      </svg>
                      <svg v-else-if="sort === 'days_covered' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
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
                  <th v-if="cols.outstanding_days" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center">
                    <button
                      type="button"
                      data-slot="button"
                      @click="toggleSortByOutstandingDays"
                      :class="[
                        'inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent',
                        sort === 'outstanding_days' ? 'text-golden-yellow' : ''
                      ]"
                      :title="sort === 'outstanding_days' ? (direction === 'asc' ? (t.sorted_asc_tooltip || 'Sorted low to high') : (t.sorted_desc_tooltip || 'Sorted high to low')) : (t.sort_by_outstanding_days_tooltip || 'Sort by outstanding days')"
                    >
                      {{ t.column_outstanding_days || 'Out. Days' }}
                      <svg v-if="sort === 'outstanding_days' && direction === 'asc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <path d="m3 8 4-4 4 4"></path>
                        <path d="M7 4v16"></path>
                      </svg>
                      <svg v-else-if="sort === 'outstanding_days' && direction === 'desc'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
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
                  <th v-if="cols.actions" data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                    {{ t.column_actions || 'Actions' }}
                  </th>
                </tr>
              </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="member in members.data"
                :key="member.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td v-if="cols.details" data-slot="table-cell" class="p-2 sm:p-3 align-middle [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[220px]">
                  <div class="flex items-start gap-2 sm:gap-3">
                    <div class="flex-shrink-0">
                      <img
                        :src="member.avatar_url"
                        :alt="member.name"
                        class="w-9 h-9 sm:w-11 sm:h-11 rounded-full object-cover"
                      />
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden space-y-0.5">
                      <Link
                        :href="getEditRoute(member.slug)"
                        class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer block truncate w-full"
                        :title="member.name"
                      >
                        {{ member.name }}
                      </Link>
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
                <td v-if="cols.status" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
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
                <td v-if="cols.partner" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
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
                <td v-if="cols.creator" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
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
                <td v-if="cols.sales" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-foreground text-xs sm:text-sm truncate max-w-[160px]" :title="member.membership?.sale_name || ''">
                    {{ member.membership?.sale_name || '—' }}
                  </span>
                </td>
                <td v-if="cols.source" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div v-if="member.membership?.card_patch_batch_names?.length" class="flex flex-col items-center gap-0.5">
                    <span class="inline-flex items-center rounded-md bg-sky-100 dark:bg-sky-900/40 px-1.5 py-0.5 text-[10px] sm:text-xs font-medium text-sky-700 dark:text-sky-300">
                      {{ t.source_batch || 'Batch' }}
                    </span>
                    <span
                      v-for="name in member.membership.card_patch_batch_names"
                      :key="name"
                      class="text-[10px] text-muted-foreground truncate max-w-[120px]"
                      :title="name"
                    >{{ name }}</span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs sm:text-sm">—</span>
                </td>
                <td v-if="cols.card" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <!-- A custom card is pinned to its own look; a default one is
                       drawn from its template and follows every change to it. -->
                  <span
                    v-if="member.membership?.has_custom_card"
                    class="inline-flex items-center rounded-md bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 text-[10px] sm:text-xs font-medium text-amber-700 dark:text-amber-300"
                    :title="t.card_custom_hint || 'Changed from the design — updates to the design will not reach it.'"
                  >
                    {{ t.card_custom || 'Custom' }}
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center rounded-md bg-muted px-1.5 py-0.5 text-[10px] sm:text-xs font-medium text-muted-foreground"
                    :title="t.card_default_hint || 'Drawn from its card template — follows every change to the design.'"
                  >
                    {{ t.card_default || 'Default' }}
                  </span>
                </td>
                <td v-if="cols.created" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-muted-foreground text-xs sm:text-sm" :title="formatFullDate(member.created_at)">
                    {{ formatDate(member.created_at) }}
                  </span>
                </td>
                <td v-if="cols.updated" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-muted-foreground text-xs sm:text-sm" :title="formatFullDate(member.membership?.updated_at || member.updated_at)">
                    {{ formatDate(member.membership?.updated_at || member.updated_at) }}
                  </span>
                </td>
                <td v-if="cols.last_active_history" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div v-if="member.membership?.last_active_history" class="flex flex-col items-center gap-0.5">
                    <span class="text-xs text-foreground font-medium" :title="formatFullDate(member.membership.last_active_history.created_at)">
                      {{ formatDate(member.membership.last_active_history.created_at) }}
                    </span>
                    <span class="text-[10px] text-muted-foreground truncate max-w-[120px]" :title="member.membership.last_active_history.changer_name">
                      {{ member.membership.last_active_history.changer_name }}
                    </span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs sm:text-sm">-</span>
                </td>
                <td v-if="cols.registration_date" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-xs text-foreground" :title="formatFullDate(member.membership?.registration_date)">
                    {{ formatDate(member.membership?.registration_date) }}
                  </span>
                </td>
                <td v-if="cols.dates" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex flex-col items-center gap-0.5">
                    <span v-if="member.membership?.registration_date" class="text-xs text-foreground font-medium">
                      {{ formatDate(member.membership.registration_date) }}
                    </span>
                    <span v-else class="text-xs text-muted-foreground">—</span>
                    <span v-if="member.membership?.expiration_date" class="text-[10px] text-muted-foreground">
                      {{ formatDate(member.membership.expiration_date) }}
                    </span>
                    <span v-else class="text-[10px] text-muted-foreground">—</span>
                  </div>
                </td>
                <td v-if="cols.amount_paid" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-foreground text-xs sm:text-sm font-semibold">
                    {{ Number(member.membership?.total_amount || 0).toFixed(2) }}
                  </span>
                </td>
                <td v-if="cols.days_covered" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span class="text-foreground text-xs sm:text-sm font-medium">
                    {{ Number(member.membership?.days_covered || 0).toLocaleString() }}
                  </span>
                </td>
                <td v-if="cols.outstanding_days" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <span :class="['text-xs sm:text-sm font-medium', outstandingDaysClass(member.membership)]">
                    {{ Number(member.membership?.outstanding_days || 0).toLocaleString() }}
                  </span>
                </td>
                <td v-if="cols.actions" data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center justify-center gap-2">
                    <a
                      :href="getCardGeneratorUrl(member)"
                      target="_blank"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2"
                      style="color:#D4AF6E;"
                      :title="t.action_create_card || 'Create Card'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                        <line x1="2" x2="22" y1="10" y2="10"/>
                      </svg>
                    </a>
                    <Link
                      :href="getShowRoute(member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-golden-yellow hover:!bg-golden-yellow/10 hover:!text-golden-yellow"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !member.slug }"
                      :title="t.action_view || 'View'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye w-3 h-3">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </Link>
                    <Link
                      :href="getLogsRoute(member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-blue-500 hover:!bg-blue-500/10 hover:!text-blue-500"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !member.slug }"
                      :title="t.action_logs || 'View Activity Logs'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history w-3 h-3">
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path>
                        <path d="M3 3v5h5"></path>
                        <path d="M12 7v5l4 2"></path>
                      </svg>
                    </Link>
                    <Link
                      v-if="can('view member active histories')"
                      :href="getActiveHistoryRoute(member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-amber-500 hover:!bg-amber-500/10 hover:!text-amber-500"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !member.slug }"
                      :title="t.action_active_history || 'Active Status History'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-toggle-left w-3 h-3">
                        <rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect>
                        <circle cx="8" cy="12" r="3"></circle>
                      </svg>
                    </Link>
                    <Link
                      :href="route('admin.member-payment.create')"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-violet-500 hover:!bg-violet-500/10 hover:!text-violet-500"
                      :title="t.action_payments || 'Payments'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wallet w-3 h-3">
                        <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
                        <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                      </svg>
                    </Link>
                    <Link
                      :href="getEditRoute(member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !member.slug }"
                      :title="t.action_edit || 'Edit'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!member.slug"
                      @click="member.slug && $emit('delete', member.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.action_delete || 'Delete'"
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
            <div class="flex items-center gap-2 order-1 sm:order-2 flex-shrink-0">
              <div class="relative">
                <button
                  ref="columnToggleRef"
                  type="button"
                  @click="showColumnMenu = !showColumnMenu"
                  class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-7 sm:h-8 px-2 sm:px-3"
                  :title="t.columns_tooltip || 'Toggle columns'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                    <line x1="3" x2="21" y1="9" y2="9"></line>
                    <line x1="3" x2="21" y1="15" y2="15"></line>
                    <line x1="9" x2="9" y1="3" y2="21"></line>
                    <line x1="15" x2="15" y1="3" y2="21"></line>
                  </svg>
                  <span class="hidden sm:inline">{{ t.columns_label || 'Columns' }}</span>
                </button>
                <Teleport to="body">
                  <div
                    v-if="showColumnMenu"
                    ref="columnMenuRef"
                    :style="columnMenuStyle"
                    class="fixed z-[1000] w-48 rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-2 space-y-0.5"
                  >
                    <label
                      v-for="col in columnOptions"
                      :key="col.key"
                      class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted/50 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        :checked="cols[col.key]"
                        @change="toggleColumn(col.key)"
                        class="h-3.5 w-3.5 rounded border-border accent-primary"
                      />
                      <span class="text-xs text-foreground">{{ col.label }}</span>
                    </label>
                  </div>
                </Teleport>
              </div>
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
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.empty_state_title || 'No Users Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.empty_state_message || 'No users match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          :href="route('admin.user.membership.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.empty_state_button || 'Add User' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { membershipQrUrl } from "@/composables/usePublicMembershipUrl.js";
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  members: {
    type: Object,
    required: true
  }
});

const page = usePage();
console.log('[DEBUG] MemberListTable mounted | members count:', props.members?.data?.length, '| sort:', page.props.sort, '| direction:', page.props.direction);

defineEmits(['delete']);

const STORAGE_KEY = 'member_table_columns';

const defaultCols = {
  details: true,
  status: true,
  partner: true,
  creator: true,
  sales: true,
  source: true,
  card: true,
  amount_paid: true,
  days_covered: true,
  outstanding_days: true,
  created: true,
  updated: true,
  last_active_history: true,
  registration_date: true,
  dates: true,
  actions: true,
};

const columnOptions = [
  { key: 'details', label: 'User Details' },
  { key: 'status', label: 'Status' },
  { key: 'partner', label: 'Partner' },
  { key: 'creator', label: 'Creator' },
  { key: 'sales', label: 'Sales' },
  { key: 'source', label: 'Source' },
  { key: 'card', label: 'Card' },
  { key: 'amount_paid', label: 'Amount Paid' },
  { key: 'days_covered', label: 'Days Covered' },
  { key: 'outstanding_days', label: 'Outstanding Days' },
  { key: 'created', label: 'Created Date' },
  { key: 'updated', label: 'Updated Date' },
  { key: 'registration_date', label: 'Registration Date' },
  { key: 'dates', label: 'Member Dates' },
  { key: 'last_active_history', label: 'Last Activation' },
  { key: 'actions', label: 'Actions' },
];

function loadCols() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) return { ...defaultCols, ...JSON.parse(saved) };
  } catch {}
  return { ...defaultCols };
}

const cols = ref(loadCols());

watch(cols, (val) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
  } catch {}
}, { deep: true });

const showColumnMenu = ref(false);
const columnToggleRef = ref(null);
const columnMenuRef = ref(null);
const columnMenuStyle = ref({});

function positionColumnMenu() {
  if (!columnToggleRef.value) return;
  const r = columnToggleRef.value.getBoundingClientRect();
  const menuW = 192;
  const menuH = 260;
  const margin = 8;
  let left = r.left;
  if (left + menuW > window.innerWidth - margin) {
    left = window.innerWidth - menuW - margin;
  }
  const spaceBelow = window.innerHeight - r.bottom;
  const spaceAbove = r.top;
  let top;
  if (spaceBelow >= menuH) {
    top = r.bottom + 4;
  } else if (spaceAbove >= menuH) {
    top = r.top - menuH - 4;
  } else {
    top = Math.max(margin, Math.min(r.top - menuH - 4, window.innerHeight - menuH - margin));
  }
  columnMenuStyle.value = {
    top: `${top}px`,
    left: `${Math.max(margin, left)}px`,
    maxHeight: `${Math.min(menuH, window.innerHeight - margin * 2)}px`,
    overflowY: 'auto',
  };
}

function toggleColumn(key) {
  cols.value = { ...cols.value, [key]: !cols.value[key] };
}

watch(showColumnMenu, (val) => {
  if (val) {
    requestAnimationFrame(positionColumnMenu);
  }
});

function handleColumnClickOutside(e) {
  if (!showColumnMenu.value) return;
  if (columnToggleRef.value?.contains(e.target)) return;
  if (columnMenuRef.value?.contains(e.target)) return;
  showColumnMenu.value = false;
}

onMounted(() => {
  document.addEventListener('click', handleColumnClickOutside);
});
onUnmounted(() => {
  document.removeEventListener('click', handleColumnClickOutside);
});

// Make locale reactive by accessing it directly from page.props in computed
const locale = computed(() => {
  return page.props.locale || 'ar';
});

const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const userRoles = computed(() => page.props.auth?.user?.roles || []);
const isSuperAdmin = computed(() => userRoles.value.includes('super_admin'));
const can = (permission) => isSuperAdmin.value || userPermissions.value.includes(permission);

const sort = computed(() => {
  const val = page.props.sort || null;
  console.log('[DEBUG] sort computed:', val, '| page.props keys:', Object.keys(page.props).filter(k => !['errors','auth','translations','ziggy','toast','locale'].includes(k)).join(','));
  return val;
});
const direction = computed(() => page.props.direction || 'asc');

// Translations for the table — keyed under admin.member_list.table
const t = computed(() => page.props.translations?.admin?.member_list?.table || {});

// Pagination labels with placeholder substitution. The translation strings
// use Laravel's `:from`, `:to`, `:total` placeholder syntax so the same
// keys can also be consumed server-side via __() if needed.
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

// Tri-state cycle: none -> asc -> desc -> none. Shared by every sortable column.
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

watch(sort, (val, old) => {
  console.log('[DEBUG] sort WATCH changed:', old, '->', val);
});
watch(direction, (val, old) => {
  console.log('[DEBUG] direction WATCH changed:', old, '->', val);
});

const toggleSortByName = () => cycleSort('name');
const toggleSortByCreatedAt = () => cycleSort('created_at');
const toggleSortByUpdatedAt = () => cycleSort('updated_at');
const toggleSortByRegistrationDate = () => cycleSort('registration_date');
const toggleSortByDaysCovered = () => cycleSort('days_covered');
const toggleSortByOutstandingDays = () => cycleSort('outstanding_days');

const getCardGeneratorUrl = (member) => {
  const params = new URLSearchParams();
  if (member.name) params.set('name', member.name);
  if (member.membership?.membership_number) params.set('policy', member.membership.membership_number);
  if (member.membership?.expiration_date) {
    const d = new Date(member.membership.expiration_date);
    params.set('valid', `${d.getMonth() + 1} / ${d.getFullYear()}`);
  }
  if (member.membership?.slug) {
    params.set('slug', member.membership.slug);
    params.set('url', membershipQrUrl(member.membership.slug));
  }
  if (member.avatar_url) params.set('avatar', member.avatar_url);
  if (member.membership?.job_title_ar) params.set('member_ar', member.membership.job_title_ar);
  if (member.membership?.job_title_en) params.set('member_en', member.membership.job_title_en);
  if (member.membership?.job_title) params.set('member', member.membership.job_title);
  if (member.membership?.company_name_ar) params.set('status_ar', member.membership.company_name_ar);
  if (member.membership?.company_name_en) params.set('status_en', member.membership.company_name_en);
  if (member.membership?.company_name) params.set('status', member.membership.company_name);
  if (member.membership?.partner_id) params.set('partner', String(member.membership.partner_id));
  return `${route('admin.card-generator')}?${params.toString()}`;
};

const getShowRoute = (slug) => {
  if (!slug) {
    return route('admin.user.membership.list');
  }
  try {
    return route('admin.user.membership.show', slug);
  } catch (error) {
    console.error('Error generating show route:', error);
    return route('admin.user.membership.list');
  }
};

const getEditRoute = (slug) => {
  if (!slug) {
    return route('admin.user.membership.list');
  }
  try {
    return route('admin.user.membership.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.user.membership.list');
  }
};

const getLogsRoute = (slug) => {
  if (!slug) {
    return route('admin.user.membership.list');
  }
  try {
    return route('admin.user.membership.logs', slug);
  } catch (error) {
    console.error('Error generating logs route:', error);
    return route('admin.user.membership.list');
  }
};

const getActiveHistoryRoute = (slug) => {
  if (!slug) {
    return route('admin.user.membership.list');
  }
  try {
    return route('admin.user.membership.active-history', slug);
  } catch (error) {
    console.error('Error generating active history route:', error);
    return route('admin.user.membership.list');
  }
};

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1'); // Reset to first page
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

const outstandingDaysClass = (membership) => {
  if (!membership) return 'text-foreground';
  const val = Number(membership.outstanding_days) || 0;
  if (val > 0) return 'text-destructive';
  if (val < 0) return 'text-green-500';
  return 'text-foreground';
};
</script>
