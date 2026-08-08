<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm" :key="`facility-table-${locale}`">
    <div v-if="facilities?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[200px] sm:min-w-[300px]">
                  <button data-slot="button" class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent">
                    {{ t.facility?.details || 'Facility Details' }}
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 text-center hidden md:table-cell">
                  Cover
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center hidden sm:table-cell">
                  {{ t.common?.type || 'Type' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center hidden sm:table-cell">
                  {{ t.facility?.discount_percent || 'Discount %' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[180px] text-center hidden lg:table-cell">
                  {{ t.facility?.coverage || ((t.governorate?.label || 'Governorate') + ' / ' + (t.city?.label || 'City')) }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-32 text-center hidden lg:table-cell">
                  {{ t.facility_branch?.branches || 'Branches' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="facility in facilities.data"
                :key="facility.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                  <div class="flex items-center gap-3">
                    <!-- Logo -->
                    <img
                      v-if="facility.logo"
                      :src="facility.logo"
                      :alt="getTranslatedName(facility.name)"
                      class="w-9 h-9 rounded-lg object-contain border border-border bg-muted shrink-0"
                    />
                    <div
                      v-else
                      class="w-9 h-9 rounded-lg border border-border bg-muted shrink-0 flex items-center justify-center"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
                        <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
                        <path d="M9 22v-4h6v4"/>
                      </svg>
                    </div>
                    <!-- Name -->
                    <Link
                      :href="getEditRoute(facility.slug)"
                      class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer max-w-[180px] sm:max-w-[230px] break-words"
                      :title="getTranslatedName(facility.name)"
                    >
                      {{ getTranslatedName(facility.name) }}
                    </Link>
                  </div>
                </td>
                <!-- Cover Image -->
                <td data-slot="table-cell" class="p-2 align-middle text-center hidden md:table-cell">
                  <img
                    v-if="facility.image"
                    :src="facility.image"
                    :alt="getTranslatedName(facility.name)"
                    class="w-16 h-10 rounded-md object-cover border border-border mx-auto"
                  />
                  <span v-else class="text-muted-foreground text-xs">—</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden sm:table-cell">
                  <span
                    v-if="facility.facility_type"
                    data-slot="badge"
                    class="relative inline-flex items-center rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] h-auto w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden font-roboto-slab font-medium tracking-[0.025em] gap-1.5 transition-[all] duration-300 ease-[ease] text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground"
                  >
                    {{ getTranslatedName(facility.facility_type?.name) }}
                  </span>
                  <span v-else class="text-muted-foreground text-xs">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden sm:table-cell">
                  <span
                    v-if="facility.discount_percent"
                    data-slot="badge"
                    class="relative inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium"
                  >
                    {{ facility.discount_percent }}%
                  </span>
                  <span v-else class="text-muted-foreground text-xs">—</span>
                </td>
                <!-- Governorates / Cities across this facility's branches -->
                <td data-slot="table-cell" class="p-2 align-middle text-center hidden lg:table-cell">
                  <div v-if="facility.governorates?.length || facility.cities?.length" class="flex flex-col items-center gap-1.5">
                    <div v-if="facility.governorates?.length" class="flex flex-wrap gap-1 justify-center">
                      <span
                        v-for="gov in facility.governorates"
                        :key="`gov-${gov.id}`"
                        data-slot="badge"
                        class="relative inline-flex items-center rounded-md border border-golden-yellow/30 bg-golden-yellow/10 text-golden-yellow px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium"
                      >
                        {{ getTranslatedName(gov.name) }}
                      </span>
                    </div>
                    <div v-if="facility.cities?.length" class="flex flex-wrap gap-1 justify-center">
                      <span
                        v-for="city in facility.cities"
                        :key="`city-${city.id}`"
                        data-slot="badge"
                        class="relative inline-flex items-center rounded-md border border-border bg-muted/50 text-muted-foreground px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap"
                      >
                        {{ getTranslatedName(city.name) }}
                      </span>
                    </div>
                  </div>
                  <span v-else class="text-muted-foreground text-xs">—</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden lg:table-cell">
                  <button
                    type="button"
                    @click="openBranches(facility)"
                    :disabled="!(facility.branches_count > 0)"
                    :title="t.facility_branch?.view_branches || 'View branches'"
                    class="inline-flex items-center gap-1.5 text-sm justify-center rounded-md border border-border bg-background px-2.5 py-1 transition-colors hover:bg-primary hover:text-primary-foreground disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:text-foreground cursor-pointer"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-git-branch w-4 h-4 text-blue-500">
                      <line x1="6" x2="6" y1="3" y2="15"></line>
                      <circle cx="18" cy="6" r="3"></circle>
                      <circle cx="6" cy="18" r="3"></circle>
                      <path d="M18 9a9 9 0 0 1-9 9"></path>
                    </svg>
                    <span>{{ facility.branches_count || 0 }}</span>
                  </button>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getEditRoute(facility.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !facility.slug }"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!facility.slug"
                      @click="facility.slug && $emit('delete', facility.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      title="Delete"
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
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', facilities.meta?.from || 0).replace(':to', facilities.meta?.to || 0).replace(':total', facilities.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ facilities.meta?.from || 0 }}-{{ facilities.meta?.to || 0 }}/{{ facilities.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select 
                :value="facilities.meta?.per_page || 15"
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
                v-if="facilities?.meta?.links?.length > 0"
                :links="facilities?.meta?.links"
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
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
            <path d="M9 22v-4h6v4"></path>
            <path d="M8 6h.01"></path>
            <path d="M16 6h.01"></path>
            <path d="M12 6h.01"></path>
            <path d="M12 10h.01"></path>
            <path d="M12 14h.01"></path>
            <path d="M16 10h.01"></path>
            <path d="M16 14h.01"></path>
            <path d="M8 10h.01"></path>
            <path d="M8 14h.01"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.facility?.not_found || 'No Facilities Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.facility?.not_found_message || 'No facilities match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          :href="route('admin.facility.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.facility?.add || 'Add Facility' }}
        </Link>
      </div>
    </div>
  </div>

  <!-- Branch details popup -->
  <Modal :show="showBranchesModal" max-width="2xl" @close="closeBranches">
    <div class="bg-popover text-popover-foreground rounded-xl border border-border shadow-xl overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-border">
        <div class="flex items-center gap-2 min-w-0">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow shrink-0">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <h3 class="font-semibold text-white truncate">
            {{ t.facility_branch?.title || 'Facility Branches' }}
            <span v-if="getTranslatedName(selectedFacility?.name)" class="text-white/60 text-sm">— {{ getTranslatedName(selectedFacility?.name) }}</span>
          </h3>
        </div>
        <button type="button" @click="closeBranches" class="p-1.5 rounded-md text-white/70 hover:text-white hover:bg-accent transition-colors shrink-0" title="Close">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <!-- Body -->
      <div class="p-5 max-h-[70vh] overflow-y-auto">
        <div v-if="selectedFacility?.branches?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div
            v-for="(branch, index) in selectedFacility.branches"
            :key="branch.id || index"
            class="p-3 bg-accent/30 rounded-lg border border-border"
          >
            <h4 class="font-semibold text-white mb-1">
              {{ getTranslatedName(branch.name) || (t.facility_branch?.unnamed_branch || 'Unnamed Branch') }}
            </h4>
            <p v-if="getTranslatedName(branch.address)" class="text-sm text-white/80 mb-1 flex items-start gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50 shrink-0 mt-0.5">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>{{ getTranslatedName(branch.address) }}</span>
            </p>
            <p v-if="branchLocation(branch)" class="text-sm text-white/80 mb-1 flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50 shrink-0">
                <path d="M3 21h18"></path>
                <path d="M5 21V7l8-4v18"></path>
                <path d="M19 21V11l-6-4"></path>
              </svg>
              <span>{{ branchLocation(branch) }}</span>
            </p>
            <p v-if="hasCoordinates(branch)" class="text-sm text-white/80 mb-1 flex items-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50 shrink-0">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M12 2v3"></path>
                <path d="M12 19v3"></path>
                <path d="M2 12h3"></path>
                <path d="M19 12h3"></path>
              </svg>
              <span>{{ Number(branch.latitude) }}, {{ Number(branch.longitude) }}</span>
            </p>
            <div v-if="branch.phone && branch.phone.length > 0" class="flex flex-wrap gap-2 text-xs text-white/70 mt-1">
              <span v-for="(phone, phoneIndex) in branch.phone" :key="phoneIndex" class="inline-flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                {{ phone }}
              </span>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-6 text-white/70">
          {{ t.facility_branch?.no_branches || 'No branches added yet.' }}
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from 'vue';

const props = defineProps({
  facilities: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

// Branch details popup
const showBranchesModal = ref(false);
const selectedFacility = ref(null);

const openBranches = (facility) => {
  selectedFacility.value = facility;
  showBranchesModal.value = true;
};

const closeBranches = () => {
  showBranchesModal.value = false;
};

const page = usePage();
// Make locale reactive by accessing it directly from page.props in computed
const locale = computed(() => {
  return page.props.locale || 'ar';
});
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const currentLocale = page.props.locale || 'ar';

    return name[currentLocale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

// "City, Governorate" for a branch in the popup (omits whichever is missing)
const branchLocation = (branch) => {
  return [getTranslatedName(branch.city?.name), getTranslatedName(branch.governorate?.name)]
    .filter(Boolean)
    .join('، ');
};

const hasCoordinates = (branch) => {
  const valid = (v) => v !== null && v !== undefined && v !== '';
  return valid(branch.latitude) && valid(branch.longitude);
};

const getEditRoute = (slug) => {
  if (!slug) {
    return route('admin.facility.list');
  }
  try {
    return route('admin.facility.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.facility.list');
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
</script>
