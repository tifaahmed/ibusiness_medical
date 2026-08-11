<template>
  <div class="w-full min-w-0 space-y-2">
    <!-- Unified responsive grid: 1 col mobile / 2 col tablet / 4 col desktop -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 w-full">
      <!-- Search by name (autocomplete) — always visible -->
      <div class="min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="search"
        >
          {{ t.search_by_name_label || 'Search by name' }}
        </label>
        <div ref="searchContainer" class="relative">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="lucide lucide-search absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            id="search"
            data-slot="input"
            autocomplete="off"
            type="text"
            v-model="filters.search"
            @input="handleSearch"
            @focus="showSuggestions = true"
            @keydown.escape="showSuggestions = false"
            @keydown.down.prevent="moveSuggestion(1)"
            @keydown.up.prevent="moveSuggestion(-1)"
            @keydown.enter.prevent="confirmHighlighted"
            class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 pl-7 sm:pl-8 md:pl-9 box-border"
            :placeholder="t.search_by_name_placeholder || 'Search by name...'"
          />

          <!-- Suggestions dropdown -->
          <ul
            v-if="showSuggestions && userNames.length > 0"
            class="absolute z-50 left-0 right-0 mt-1 bg-[#0a0d14] border border-border rounded-md shadow-xl max-h-60 overflow-y-auto"
          >
            <li v-if="suggestions.length === 0" class="px-2.5 py-1.5 text-xs text-muted-foreground italic">
              {{ t.no_matches || 'No matches' }}
            </li>
            <li
              v-for="(item, index) in suggestions"
              :key="item.id"
              @mousedown.prevent="selectSuggestion(item)"
              :class="[
                'px-2.5 py-1.5 cursor-pointer text-xs flex items-center gap-2 transition-colors',
                highlightedIndex === index
                  ? 'bg-primary text-primary-foreground'
                  : 'text-foreground hover:bg-white/5'
              ]"
            >
              <svg class="w-3 h-3 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
              </svg>
              <span class="flex-1 truncate">{{ item.name }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Mobile-only "Advanced search" toggle (placed below search on mobile, hidden on sm+) -->
      <button
        type="button"
        @click="showAdvanced = !showAdvanced"
        class="sm:hidden inline-flex items-center justify-center gap-1.5 w-full px-3 h-9 rounded-md text-xs font-medium border border-border bg-transparent text-foreground hover:bg-primary hover:text-primary-foreground transition-colors"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" x2="20" y1="6" y2="6"></line>
          <line x1="8" x2="20" y1="12" y2="12"></line>
          <line x1="12" x2="20" y1="18" y2="18"></line>
        </svg>
        {{ showAdvanced ? (t.hide_advanced || 'Hide advanced search') : (t.show_advanced || 'Advanced search') }}
        <span v-if="advancedActiveCount > 0" class="ml-1 inline-flex items-center justify-center rounded-full bg-primary text-primary-foreground text-[10px] font-semibold w-4 h-4">{{ advancedActiveCount }}</span>
      </button>

      <!-- Partner Filter -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="partner_id"
        >
          {{ t.partner_label || 'Partner' }}
        </label>
        <Select
          v-model="filters.partner_id"
          :options="partnerOptions"
          :placeholder="t.partner_all_option || 'All partners'"
          id="partner_id"
          @change="applyFilters()"
        />
      </div>

      <!-- Creator Filter (hidden when the admin is scoped to own memberships) -->
      <div v-if="canFilterByCreator" :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="creator_id"
        >
          {{ t.creator_label || 'Creator' }}
        </label>
        <Select
          v-model="filters.creator_id"
          :options="creatorSelectOptions"
          :placeholder="t.creator_all_option || 'All creators'"
          id="creator_id"
          @change="applyFilters()"
        />
      </div>

      <!-- Status Filter Buttons -->
      <div :class="['min-w-0 sm:col-span-2 lg:col-span-1', showAdvanced ? '' : 'hidden sm:block']">
        <div class="flex flex-col gap-1.5 sm:gap-2 w-full">
          <label
            data-slot="label"
            class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          >
            {{ t.status_label || 'Status' }}
          </label>
          <div class="flex gap-2 w-full">
            <button
              data-slot="button"
              @click="handleFilter(1)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_active === 1 || filters.is_active === '1' || filters.is_active === true ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big h-3.5 w-3.5">
                <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                <path d="m9 11 3 3L22 4"></path>
              </svg>
              <span>{{ t.status_active || 'Active' }}</span>
            </button>
            <button
              data-slot="button"
              @click="handleFilter(0)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_active === 0 || filters.is_active === '0' || filters.is_active === false ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-x h-3.5 w-3.5">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="m15 9-6 6"></path>
                <path d="m9 9 6 6"></path>
              </svg>
              <span>{{ t.status_inactive || 'Inactive' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Payment Filter Buttons -->
      <div :class="['min-w-0 sm:col-span-2 lg:col-span-1', showAdvanced ? '' : 'hidden sm:block']">
        <div class="flex flex-col gap-1.5 sm:gap-2 w-full">
          <label
            data-slot="label"
            class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          >
            {{ t.payment_label || 'Payment' }}
          </label>
          <div class="flex gap-2 w-full">
            <button
              data-slot="button"
              @click="handlePaymentFilter(1)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_paid === 1 || filters.is_paid === '1' || filters.is_paid === true ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <rect width="20" height="14" x="2" y="5" rx="2"/>
                <line x1="2" x2="22" y1="10" y2="10"/>
              </svg>
              <span>{{ t.payment_paid || 'Paid' }}</span>
            </button>
            <button
              data-slot="button"
              @click="handlePaymentFilter(0)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_paid === 0 || filters.is_paid === '0' || filters.is_paid === false ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="m15 9-6 6"></path>
                <path d="m9 9 6 6"></path>
              </svg>
              <span>{{ t.payment_unpaid || 'Unpaid' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Sales Filter -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="sale_id"
        >
          {{ t.sales_label || 'Sales' }}
        </label>
        <Select
          v-model="filters.sale_id"
          :options="salesOptions"
          :placeholder="t.sales_all_option || 'All sales'"
          id="sale_id"
          @change="applyFilters()"
        />
      </div>

      <!-- Company Filter -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="company_id"
        >
          {{ t.company_label || 'Company' }}
        </label>
        <Select
          v-model="filters.company_id"
          :options="companyOptions"
          :placeholder="t.company_all_option || 'All companies'"
          id="company_id"
          @change="applyFilters()"
        />
      </div>

      <!-- Source Filter (Batch / Manual) -->
      <div :class="['min-w-0 sm:col-span-2 lg:col-span-1', showAdvanced ? '' : 'hidden sm:block']">
        <div class="flex flex-col gap-1.5 sm:gap-2 w-full">
          <label
            data-slot="label"
            class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          >
            {{ t.source_label || 'Source' }}
          </label>
          <div class="flex gap-2 w-full">
            <button
              data-slot="button"
              @click="handleSourceFilter(1)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_from_card_patch === 1 || filters.is_from_card_patch === '1' || filters.is_from_card_patch === true ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" x2="16" y1="21" y2="21"></line>
                <line x1="12" x2="12" y1="17" y2="21"></line>
              </svg>
              <span>{{ t.source_batch || 'Batch' }}</span>
            </button>
            <button
              data-slot="button"
              @click="handleSourceFilter(0)"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.is_from_card_patch === 0 || filters.is_from_card_patch === '0' || filters.is_from_card_patch === false ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span>{{ t.source_manual || 'Manual' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Card Filter (Custom / Default look) -->
      <div :class="['min-w-0 sm:col-span-2 lg:col-span-1', showAdvanced ? '' : 'hidden sm:block']">
        <div class="flex flex-col gap-1.5 sm:gap-2 w-full">
          <label
            data-slot="label"
            class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          >
            {{ t.card_label || 'Card' }}
          </label>
          <div class="flex gap-2 w-full">
            <button
              data-slot="button"
              @click="handleCardFilter(1)"
              :title="t.card_custom_hint || 'Changed from the design — updates to the design will not reach it.'"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.has_custom_card === 1 || filters.has_custom_card === '1' || filters.has_custom_card === true ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
              </svg>
              <span>{{ t.card_custom || 'Custom' }}</span>
            </button>
            <button
              data-slot="button"
              @click="handleCardFilter(0)"
              :title="t.card_default_hint || 'Drawn from its card template — follows every change to the design.'"
              :class="[
                'flex-1 cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 rounded-md px-3 flex items-center gap-2',
                filters.has_custom_card === 0 || filters.has_custom_card === '0' || filters.has_custom_card === false ? 'bg-primary text-primary-foreground' : ''
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                <path d="M2 10h20"></path>
              </svg>
              <span>{{ t.card_default || 'Default' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Email -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="email"
        >
          {{ t.email_label || 'Email' }}
        </label>
        <div ref="emailContainer" class="relative">
          <input
            id="email"
            autocomplete="off"
            type="email"
            v-model="filters.email"
            @input="handleEmailInput"
            @focus="showEmailSuggestions = true"
            @keydown.escape="showEmailSuggestions = false"
            @keydown.down.prevent="moveEmailSuggestion(1)"
            @keydown.up.prevent="moveEmailSuggestion(-1)"
            @keydown.enter.prevent="confirmEmailHighlighted"
            :placeholder="t.email_placeholder || 'filter by exact or partial email...'"
            class="border border-border bg-transparent text-foreground placeholder:text-white rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark]"
          />

          <!-- Email suggestions dropdown -->
          <ul
            v-if="showEmailSuggestions && userEmails.length > 0"
            class="absolute z-50 left-0 right-0 mt-1 bg-[#0a0d14] border border-border rounded-md shadow-xl max-h-60 overflow-y-auto"
          >
            <li v-if="emailSuggestions.length === 0" class="px-2.5 py-1.5 text-xs text-muted-foreground italic">
              {{ t.no_matches || 'No matches' }}
            </li>
            <li
              v-for="(item, index) in emailSuggestions"
              :key="item.id"
              @mousedown.prevent="selectEmailSuggestion(item)"
              :class="[
                'px-2.5 py-1.5 cursor-pointer text-xs flex items-center gap-2 transition-colors',
                highlightedEmailIndex === index
                  ? 'bg-primary text-primary-foreground'
                  : 'text-foreground hover:bg-white/5'
              ]"
            >
              <svg class="w-3 h-3 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
              </svg>
              <span class="flex-1 truncate">{{ item.email }}</span>
              <span v-if="item.name" class="text-muted-foreground truncate ml-2">{{ item.name }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Phone -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="phone"
        >
          {{ t.phone_label || 'Phone' }}
        </label>
        <div ref="phoneContainer" class="relative">
          <input
            id="phone"
            autocomplete="off"
            type="tel"
            v-model="filters.phone"
            @input="handlePhoneInput"
            @focus="showPhoneSuggestions = true"
            @keydown.escape="showPhoneSuggestions = false"
            @keydown.down.prevent="movePhoneSuggestion(1)"
            @keydown.up.prevent="movePhoneSuggestion(-1)"
            @keydown.enter.prevent="confirmPhoneHighlighted"
            :placeholder="t.phone_placeholder || 'filter by phone number...'"
            class="border border-border bg-transparent text-foreground placeholder:text-white rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark]"
          />

          <!-- Phone suggestions dropdown -->
          <ul
            v-if="showPhoneSuggestions && userPhones.length > 0"
            class="absolute z-50 left-0 right-0 mt-1 bg-[#0a0d14] border border-border rounded-md shadow-xl max-h-60 overflow-y-auto"
          >
            <li v-if="phoneSuggestions.length === 0" class="px-2.5 py-1.5 text-xs text-muted-foreground italic">
              {{ t.no_matches || 'No matches' }}
            </li>
            <li
              v-for="(item, index) in phoneSuggestions"
              :key="item.id"
              @mousedown.prevent="selectPhoneSuggestion(item)"
              :class="[
                'px-2.5 py-1.5 cursor-pointer text-xs flex items-center gap-2 transition-colors',
                highlightedPhoneIndex === index
                  ? 'bg-primary text-primary-foreground'
                  : 'text-foreground hover:bg-white/5'
              ]"
            >
              <svg class="w-3 h-3 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
              </svg>
              <span class="flex-1 truncate">{{ item.phone }}</span>
              <span v-if="item.name" class="text-muted-foreground truncate ml-2">{{ item.name }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Membership number -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="membership_number"
        >
          {{ t.membership_number_label || 'Membership number' }}
        </label>
        <input
          id="membership_number"
          autocomplete="off"
          type="text"
          v-model="filters.membership_number"
          @input="handleMembershipNumberInput"
          :placeholder="t.membership_number_placeholder || 'filter by membership number...'"
          class="border border-border bg-transparent text-foreground placeholder:text-white rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] font-mono"
        />
      </div>

      <!-- Created from -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="created_from"
        >
          {{ t.created_from_label || 'Created from' }}
        </label>
        <input
          id="created_from"
          v-model="filters.created_from"
          @change="applyFilters()"
          type="date"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Created to -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="created_to"
        >
          {{ t.created_to_label || 'Created to' }}
        </label>
        <input
          id="created_to"
          v-model="filters.created_to"
          @change="applyFilters()"
          type="date"
          :min="filters.created_from || null"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Registration date from -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="registration_date_from"
        >
          {{ t.registration_date_from_label || 'Reg. date from' }}
        </label>
        <input
          id="registration_date_from"
          v-model="filters.registration_date_from"
          @change="applyFilters()"
          type="date"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Registration date to -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="registration_date_to"
        >
          {{ t.registration_date_to_label || 'Reg. date to' }}
        </label>
        <input
          id="registration_date_to"
          v-model="filters.registration_date_to"
          @change="applyFilters()"
          type="date"
          :min="filters.registration_date_from || null"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Expiration date from -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="expiration_date_from"
        >
          {{ t.expiration_date_from_label || 'Exp. date from' }}
        </label>
        <input
          id="expiration_date_from"
          v-model="filters.expiration_date_from"
          @change="applyFilters()"
          type="date"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Expiration date to -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="expiration_date_to"
        >
          {{ t.expiration_date_to_label || 'Exp. date to' }}
        </label>
        <input
          id="expiration_date_to"
          v-model="filters.expiration_date_to"
          @change="applyFilters()"
          type="date"
          :min="filters.expiration_date_from || null"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Last activation changer -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="last_activation_changer"
        >
          {{ t.last_activation_changer_label || 'Last activation by' }}
        </label>
        <Select
          v-model="filters.last_activation_changer"
          :options="lastActivationChangerOptions"
          :placeholder="t.last_activation_changer_placeholder || 'All changers'"
          id="last_activation_changer"
          @change="applyFilters()"
        />
      </div>

      <!-- Last activation from -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="last_activation_from"
        >
          {{ t.last_activation_from_label || 'Last activation from' }}
        </label>
        <input
          id="last_activation_from"
          v-model="filters.last_activation_from"
          @change="applyFilters()"
          type="date"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Last activation to -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="last_activation_to"
        >
          {{ t.last_activation_to_label || 'Last activation to' }}
        </label>
        <input
          id="last_activation_to"
          v-model="filters.last_activation_to"
          @change="applyFilters()"
          type="date"
          :min="filters.last_activation_from || null"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer"
        />
      </div>

      <!-- Outstanding days from -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="outstanding_days_from"
        >
          {{ t.outstanding_days_from_label || 'Out. days from' }}
        </label>
        <input
          id="outstanding_days_from"
          v-model.number="filters.outstanding_days_from"
          @change="applyFilters()"
          type="number"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark]"
        />
      </div>

      <!-- Outstanding days to -->
      <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="outstanding_days_to"
        >
          {{ t.outstanding_days_to_label || 'Out. days to' }}
        </label>
        <input
          id="outstanding_days_to"
          v-model.number="filters.outstanding_days_to"
          @change="applyFilters()"
          type="number"
          class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark]"
        />
      </div>
    </div>

    <!-- Reset Filter - Only show if there's an active filter -->
    <button
      v-if="hasActiveFilters"
      data-slot="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 h-7 sm:h-8 rounded-md px-2 sm:px-3 has-[>svg]:px-2 inline-flex items-center gap-1.5 sm:gap-2"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="lucide lucide-x h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4"
      >
        <path d="M18 6 6 18"></path>
        <path d="m6 6 12 12"></path>
      </svg>
      <span class="hidden sm:inline">{{ t.clear || 'Clear' }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import Fuse from 'fuse.js';
import Select from '@/Components/ui/Select.vue';

const page = usePage();
const partnerOptions = computed(() => page.props.partnerOptions || []);
const creatorOptions = computed(() => page.props.creatorOptions || []);
const salesOptions = computed(() => page.props.salesOptions || []);
const companyOptions = computed(() => page.props.companyOptions || []);
const creatorSelectOptions = computed(() =>
  creatorOptions.value.map(opt => ({
    value: opt.value,
    label: opt.email ? `${opt.label} (${opt.email})` : opt.label,
  }))
);
const canFilterByCreator = computed(() => page.props.canFilterByCreator !== false);
const lastActivationChangerOptions = computed(() => page.props.lastActivationChangerOptions || []);
const userNames = computed(() => page.props.userNames || []);
const userPhones = computed(() => page.props.userPhones || []);
const userEmails = computed(() => page.props.userEmails || []);
// Translations namespace for this page — keyed under admin.member_list.filters
const t = computed(() => page.props.translations?.admin?.member_list?.filters || {});

const emit = defineEmits(['filter-change']);

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      email: '',
      phone: '',
      membership_number: '',
    is_active: null,
    is_paid: null,
    is_from_card_patch: null,
    has_custom_card: null,
    partner_id: null,
    creator_id: null,
    sale_id: null,
    company_id: null,
    created_from: '',
    created_to: '',
    registration_date_from: '',
    registration_date_to: '',
    expiration_date_from: '',
    expiration_date_to: '',
    last_activation_changer: '',
    last_activation_from: '',
    last_activation_to: '',
    outstanding_days_from: null,
    outstanding_days_to: null,
    }),
  },
});

const normalizePartnerId = (val) => {
  if (val === null || val === undefined || val === '') return null;
  const n = Number(val);
  return Number.isFinite(n) ? n : null;
};

const normalizeId = normalizePartnerId;

const getInitialFilters = () => {
  // Normalize a tri-state boolean filter (1 | 0 | null) from various inputs.
  const normalizeBool = (val) => {
    if (val === null || val === undefined) return null;
    if (val === true || val === 1 || val === '1') return 1;
    if (val === false || val === 0 || val === '0') return 0;
    return null;
  };

  // First check props (from server)
  if (props.initialFilters) {
    return {
      search: props.initialFilters.search || '',
      email: props.initialFilters.email || '',
      phone: props.initialFilters.phone || '',
      membership_number: props.initialFilters.membership_number || '',
      is_active: normalizeBool(props.initialFilters.is_active),
      is_paid: normalizeBool(props.initialFilters.is_paid),
      is_from_card_patch: normalizeBool(props.initialFilters.is_from_card_patch),
      has_custom_card: normalizeBool(props.initialFilters.has_custom_card),
      partner_id: normalizePartnerId(props.initialFilters.partner_id),
      creator_id: normalizeId(props.initialFilters.creator_id),
      sale_id: normalizePartnerId(props.initialFilters.sale_id),
      company_id: normalizePartnerId(props.initialFilters.company_id),
      created_from: props.initialFilters.created_from || '',
      created_to: props.initialFilters.created_to || '',
      registration_date_from: props.initialFilters.registration_date_from || '',
      registration_date_to: props.initialFilters.registration_date_to || '',
      expiration_date_from: props.initialFilters.expiration_date_from || '',
      expiration_date_to: props.initialFilters.expiration_date_to || '',
      last_activation_changer: props.initialFilters.last_activation_changer || '',
      last_activation_from: props.initialFilters.last_activation_from || '',
      last_activation_to: props.initialFilters.last_activation_to || '',
      outstanding_days_from: props.initialFilters.outstanding_days_from !== null && props.initialFilters.outstanding_days_from !== undefined
        ? Number(props.initialFilters.outstanding_days_from) : null,
      outstanding_days_to: props.initialFilters.outstanding_days_to !== null && props.initialFilters.outstanding_days_to !== undefined
        ? Number(props.initialFilters.outstanding_days_to) : null,
    };
  }

  // Fallback to URL params if props not available
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    const isActiveParam = urlParams.get('is_active');
    const isPaidParam = urlParams.get('is_paid');
    return {
      search: urlParams.get('search') || '',
      email: urlParams.get('email') || '',
      phone: urlParams.get('phone') || '',
      membership_number: urlParams.get('membership_number') || '',
      is_active: isActiveParam !== null ? Number(isActiveParam) : null,
      is_paid: isPaidParam !== null ? Number(isPaidParam) : null,
      is_from_card_patch: urlParams.get('is_from_card_patch') !== null ? Number(urlParams.get('is_from_card_patch')) : null,
      has_custom_card: urlParams.get('has_custom_card') !== null ? Number(urlParams.get('has_custom_card')) : null,
      partner_id: normalizePartnerId(urlParams.get('partner_id')),
      creator_id: normalizeId(urlParams.get('creator_id')),
      sale_id: normalizePartnerId(urlParams.get('sale_id')),
      company_id: normalizePartnerId(urlParams.get('company_id')),
      created_from: urlParams.get('created_from') || '',
      created_to: urlParams.get('created_to') || '',
      registration_date_from: urlParams.get('registration_date_from') || '',
      registration_date_to: urlParams.get('registration_date_to') || '',
      expiration_date_from: urlParams.get('expiration_date_from') || '',
      expiration_date_to: urlParams.get('expiration_date_to') || '',
      last_activation_changer: urlParams.get('last_activation_changer') || '',
      last_activation_from: urlParams.get('last_activation_from') || '',
      last_activation_to: urlParams.get('last_activation_to') || '',
      outstanding_days_from: urlParams.has('outstanding_days_from') ? Number(urlParams.get('outstanding_days_from')) : null,
      outstanding_days_to: urlParams.has('outstanding_days_to') ? Number(urlParams.get('outstanding_days_to')) : null,
    };
  }

  return {
    search: '',
    email: '',
    phone: '',
    is_active: null,
    is_paid: null,
    partner_id: null,
    creator_id: null,
    created_from: '',
    created_to: '',
    last_activation_changer: '',
    last_activation_from: '',
    last_activation_to: '',
  };
};

const filters = ref(getInitialFilters());

// Computed property to check if any filter is active
const hasActiveFilters = computed(() => {
  return !!(filters.value.search
    || filters.value.email
    || filters.value.phone
    || filters.value.membership_number
    || (filters.value.is_active !== null && filters.value.is_active !== undefined)
    || (filters.value.is_paid !== null && filters.value.is_paid !== undefined)
    || (filters.value.is_from_card_patch !== null && filters.value.is_from_card_patch !== undefined)
    || (filters.value.has_custom_card !== null && filters.value.has_custom_card !== undefined)
    || filters.value.partner_id
    || filters.value.creator_id
    || filters.value.sale_id
    || filters.value.company_id
    || filters.value.created_from
    || filters.value.created_to
    || filters.value.registration_date_from
    || filters.value.registration_date_to
    || filters.value.expiration_date_from
    || filters.value.expiration_date_to
    || filters.value.last_activation_changer
    || filters.value.last_activation_from
    || filters.value.last_activation_to
    || filters.value.outstanding_days_from
    || filters.value.outstanding_days_to);
});

// Count of "advanced" filters that are currently active (everything except `search`)
const advancedActiveCount = computed(() => {
  let n = 0;
  if (filters.value.email) n++;
  if (filters.value.phone) n++;
  if (filters.value.membership_number) n++;
  if (filters.value.is_active !== null && filters.value.is_active !== undefined) n++;
  if (filters.value.is_paid !== null && filters.value.is_paid !== undefined) n++;
  if (filters.value.is_from_card_patch !== null && filters.value.is_from_card_patch !== undefined) n++;
  if (filters.value.has_custom_card !== null && filters.value.has_custom_card !== undefined) n++;
  if (filters.value.partner_id) n++;
  if (filters.value.creator_id) n++;
  if (filters.value.sale_id) n++;
  if (filters.value.company_id) n++;
  if (filters.value.created_from) n++;
  if (filters.value.created_to) n++;
  if (filters.value.registration_date_from) n++;
  if (filters.value.registration_date_to) n++;
  if (filters.value.expiration_date_from) n++;
  if (filters.value.expiration_date_to) n++;
  if (filters.value.last_activation_changer) n++;
  if (filters.value.last_activation_from) n++;
  if (filters.value.last_activation_to) n++;
  if (filters.value.outstanding_days_from) n++;
  if (filters.value.outstanding_days_to) n++;
  return n;
});

// On mobile only: collapse advanced filters by default (open if any advanced filter is already set)
const showAdvanced = ref(advancedActiveCount.value > 0);

let searchTimeout = null;
const debouncedSearch = (value) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, search: value });
  }, 300);
};

const handleSearch = (event) => {
  filters.value.search = event.target.value;
  showSuggestions.value = true;
  debouncedSearch(event.target.value);
};

// Autocomplete suggestions (matches members by name)
const searchContainer = ref(null);
const showSuggestions = ref(false);
const highlightedIndex = ref(-1);

const fuse = computed(() => new Fuse(userNames.value, {
  keys: ['name'],
  threshold: 0.45,
  ignoreLocation: true,
  minMatchCharLength: 1,
  includeScore: true,
}));

const RECENT_SEARCHES_KEY = 'member_recent_searches';
const MAX_RECENT = 10;

const recentSearches = ref([]);

const loadRecentSearches = () => {
  try {
    const raw = localStorage.getItem(RECENT_SEARCHES_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch { return []; }
};

const saveRecentSearch = (item) => {
  const list = loadRecentSearches();
  const filtered = list.filter(s => s.id !== item.id);
  filtered.unshift({ id: item.id, name: item.name, slug: item.slug });
  if (filtered.length > MAX_RECENT) filtered.length = MAX_RECENT;
  localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(filtered));
  recentSearches.value = filtered;
};

recentSearches.value = loadRecentSearches();

const suggestions = computed(() => {
  const q = (filters.value.search || '').trim();
  if (!q) {
    const recent = recentSearches.value;
    if (recent.length > 0) {
      const latest = userNames.value.filter(u => !recent.some(r => r.id === u.id));
      return [...recent, ...latest].slice(0, 8);
    }
    return userNames.value.slice(0, 8);
  }
  return fuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(suggestions, () => { highlightedIndex.value = -1; });

const selectSuggestion = (item) => {
  filters.value.search = item.name;
  showSuggestions.value = false;
  highlightedIndex.value = -1;
  if (searchTimeout) clearTimeout(searchTimeout);
  saveRecentSearch(item);
  applyFilters();
};

const moveSuggestion = (dir) => {
  if (!suggestions.value.length) return;
  highlightedIndex.value = Math.max(-1, Math.min(suggestions.value.length - 1, highlightedIndex.value + dir));
};

const confirmHighlighted = () => {
  const item = suggestions.value[highlightedIndex.value];
  if (item) {
    selectSuggestion(item);
  } else {
    showSuggestions.value = false;
    if (searchTimeout) clearTimeout(searchTimeout);
    applyFilters();
  }
};

onClickOutside(searchContainer, () => { showSuggestions.value = false; });

let emailTimeout = null;
const handleEmailInput = (event) => {
  filters.value.email = event.target.value;
  showEmailSuggestions.value = true;
  if (emailTimeout) clearTimeout(emailTimeout);
  emailTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, email: event.target.value });
  }, 300);
};

// Email autocomplete (matches members by email)
const emailContainer = ref(null);
const showEmailSuggestions = ref(false);
const highlightedEmailIndex = ref(-1);

const emailFuse = computed(() => new Fuse(userEmails.value, {
  keys: ['email', 'name'],
  threshold: 0.45,
  ignoreLocation: true,
  minMatchCharLength: 1,
  includeScore: true,
}));

const emailSuggestions = computed(() => {
  const q = (filters.value.email || '').trim();
  if (!q) return userEmails.value.slice(0, 8);
  return emailFuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(emailSuggestions, () => { highlightedEmailIndex.value = -1; });

const selectEmailSuggestion = (item) => {
  filters.value.email = item.email;
  showEmailSuggestions.value = false;
  highlightedEmailIndex.value = -1;
  if (emailTimeout) clearTimeout(emailTimeout);
  applyFilters();
};

const moveEmailSuggestion = (dir) => {
  if (!emailSuggestions.value.length) return;
  highlightedEmailIndex.value = Math.max(-1, Math.min(emailSuggestions.value.length - 1, highlightedEmailIndex.value + dir));
};

const confirmEmailHighlighted = () => {
  const item = emailSuggestions.value[highlightedEmailIndex.value];
  if (item) {
    selectEmailSuggestion(item);
  } else {
    showEmailSuggestions.value = false;
    if (emailTimeout) clearTimeout(emailTimeout);
    applyFilters();
  }
};

onClickOutside(emailContainer, () => { showEmailSuggestions.value = false; });

let phoneTimeout = null;
const handlePhoneInput = (event) => {
  filters.value.phone = event.target.value;
  showPhoneSuggestions.value = true;
  if (phoneTimeout) clearTimeout(phoneTimeout);
  phoneTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, phone: event.target.value });
  }, 300);
};

// Phone autocomplete (matches members by phone)
const phoneContainer = ref(null);
const showPhoneSuggestions = ref(false);
const highlightedPhoneIndex = ref(-1);

const phoneFuse = computed(() => new Fuse(userPhones.value, {
  keys: ['phone', 'name'],
  threshold: 0.45,
  ignoreLocation: true,
  minMatchCharLength: 1,
  includeScore: true,
}));

const phoneSuggestions = computed(() => {
  const q = (filters.value.phone || '').trim();
  if (!q) return userPhones.value.slice(0, 8);
  return phoneFuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(phoneSuggestions, () => { highlightedPhoneIndex.value = -1; });

const selectPhoneSuggestion = (item) => {
  filters.value.phone = item.phone;
  showPhoneSuggestions.value = false;
  highlightedPhoneIndex.value = -1;
  if (phoneTimeout) clearTimeout(phoneTimeout);
  applyFilters();
};

const movePhoneSuggestion = (dir) => {
  if (!phoneSuggestions.value.length) return;
  highlightedPhoneIndex.value = Math.max(-1, Math.min(phoneSuggestions.value.length - 1, highlightedPhoneIndex.value + dir));
};

const confirmPhoneHighlighted = () => {
  const item = phoneSuggestions.value[highlightedPhoneIndex.value];
  if (item) {
    selectPhoneSuggestion(item);
  } else {
    showPhoneSuggestions.value = false;
    if (phoneTimeout) clearTimeout(phoneTimeout);
    applyFilters();
  }
};

onClickOutside(phoneContainer, () => { showPhoneSuggestions.value = false; });

let membershipNumberTimeout = null;
const handleMembershipNumberInput = (event) => {
  filters.value.membership_number = event.target.value;
  if (membershipNumberTimeout) clearTimeout(membershipNumberTimeout);
  membershipNumberTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, membership_number: event.target.value });
  }, 300);
};

const handleFilter = (isActive) => {
  // Toggle filter: if already selected, deselect it
  if (filters.value.is_active === isActive) {
    filters.value.is_active = null;
  } else {
    filters.value.is_active = isActive;
  }
  applyFilters();
};

const handlePaymentFilter = (isPaid) => {
  if (filters.value.is_paid === isPaid) {
    filters.value.is_paid = null;
  } else {
    filters.value.is_paid = isPaid;
  }
  applyFilters();
};

const handleSourceFilter = (isFromCardPatch) => {
  if (filters.value.is_from_card_patch === isFromCardPatch) {
    filters.value.is_from_card_patch = null;
  } else {
    filters.value.is_from_card_patch = isFromCardPatch;
  }
  applyFilters();
};

/** Custom = pinned to its own look, Default = still following its template. */
const handleCardFilter = (hasCustomCard) => {
  filters.value.has_custom_card = filters.value.has_custom_card === hasCustomCard ? null : hasCustomCard;
  applyFilters();
};

const handleReset = () => {
  filters.value = {
    search: '',
    email: '',
    phone: '',
    membership_number: '',
    is_active: null,
    is_paid: null,
    partner_id: null,
    creator_id: null,
    sale_id: null,
    company_id: null,
    created_from: '',
    created_to: '',
    registration_date_from: '',
    registration_date_to: '',
    expiration_date_from: '',
    expiration_date_to: '',
    last_activation_changer: '',
    last_activation_from: '',
    last_activation_to: '',
    outstanding_days_from: null,
    outstanding_days_to: null,
  };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;

  const params = {};
  if (currentFilters.search && currentFilters.search.trim()) {
    params.search = currentFilters.search;
  }
  if (currentFilters.email && currentFilters.email.trim()) {
    params.email = currentFilters.email.trim();
  }
  if (currentFilters.phone && currentFilters.phone.trim()) {
    params.phone = currentFilters.phone.trim();
  }
  if (currentFilters.membership_number && currentFilters.membership_number.trim()) {
    params.membership_number = currentFilters.membership_number.trim();
  }
  if (currentFilters.is_active !== null && currentFilters.is_active !== undefined) {
    params.is_active = currentFilters.is_active;
  }
  if (currentFilters.is_paid !== null && currentFilters.is_paid !== undefined) {
    params.is_paid = currentFilters.is_paid;
  }
  if (currentFilters.is_from_card_patch !== null && currentFilters.is_from_card_patch !== undefined) {
    params.is_from_card_patch = currentFilters.is_from_card_patch;
  }
  if (currentFilters.has_custom_card !== null && currentFilters.has_custom_card !== undefined) {
    params.has_custom_card = currentFilters.has_custom_card;
  }
  if (currentFilters.partner_id !== null && currentFilters.partner_id !== undefined && currentFilters.partner_id !== '') {
    params.partner_id = currentFilters.partner_id;
  }
  if (currentFilters.creator_id !== null && currentFilters.creator_id !== undefined && currentFilters.creator_id !== '') {
    params.creator_id = currentFilters.creator_id;
  }
  if (currentFilters.sale_id !== null && currentFilters.sale_id !== undefined && currentFilters.sale_id !== '') {
    params.sale_id = currentFilters.sale_id;
  }
  if (currentFilters.company_id !== null && currentFilters.company_id !== undefined && currentFilters.company_id !== '') {
    params.company_id = currentFilters.company_id;
  }
  if (currentFilters.created_from) {
    params.created_from = currentFilters.created_from;
  }
  if (currentFilters.created_to) {
    params.created_to = currentFilters.created_to;
  }
  if (currentFilters.registration_date_from) {
    params.registration_date_from = currentFilters.registration_date_from;
  }
  if (currentFilters.registration_date_to) {
    params.registration_date_to = currentFilters.registration_date_to;
  }
  if (currentFilters.expiration_date_from) {
    params.expiration_date_from = currentFilters.expiration_date_from;
  }
  if (currentFilters.expiration_date_to) {
    params.expiration_date_to = currentFilters.expiration_date_to;
  }
  if (currentFilters.last_activation_changer && currentFilters.last_activation_changer.trim()) {
    params.last_activation_changer = currentFilters.last_activation_changer.trim();
  }
  if (currentFilters.last_activation_from) {
    params.last_activation_from = currentFilters.last_activation_from;
  }
  if (currentFilters.last_activation_to) {
    params.last_activation_to = currentFilters.last_activation_to;
  }
  if (currentFilters.outstanding_days_from !== null && currentFilters.outstanding_days_from !== undefined && currentFilters.outstanding_days_from !== '') {
    params.outstanding_days_from = currentFilters.outstanding_days_from;
  }
  if (currentFilters.outstanding_days_to !== null && currentFilters.outstanding_days_to !== undefined && currentFilters.outstanding_days_to !== '') {
    params.outstanding_days_to = currentFilters.outstanding_days_to;
  }

  emit('filter-change', currentFilters);

  router.get(route('admin.user.membership.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) {
    const normalizeBool = (val, fallback) => {
      if (val === null || val === undefined) return fallback;
      if (val === true || val === 1 || val === '1') return 1;
      if (val === false || val === 0 || val === '0') return 0;
      return fallback;
    };
    filters.value = {
      search: newFilters.search || filters.value.search,
      email: newFilters.email !== undefined ? (newFilters.email || '') : filters.value.email,
      phone: newFilters.phone !== undefined ? (newFilters.phone || '') : filters.value.phone,
      membership_number: newFilters.membership_number !== undefined ? (newFilters.membership_number || '') : filters.value.membership_number,
      is_active: normalizeBool(newFilters.is_active, filters.value.is_active),
      is_paid: normalizeBool(newFilters.is_paid, filters.value.is_paid),
      is_from_card_patch: normalizeBool(newFilters.is_from_card_patch, filters.value.is_from_card_patch),
      has_custom_card: normalizeBool(newFilters.has_custom_card, filters.value.has_custom_card),
      partner_id: normalizePartnerId(newFilters.partner_id),
      creator_id: normalizeId(newFilters.creator_id),
      sale_id: normalizePartnerId(newFilters.sale_id),
      company_id: normalizePartnerId(newFilters.company_id),
      created_from: newFilters.created_from !== undefined ? (newFilters.created_from || '') : filters.value.created_from,
      created_to: newFilters.created_to !== undefined ? (newFilters.created_to || '') : filters.value.created_to,
      registration_date_from: newFilters.registration_date_from !== undefined ? (newFilters.registration_date_from || '') : filters.value.registration_date_from,
      registration_date_to: newFilters.registration_date_to !== undefined ? (newFilters.registration_date_to || '') : filters.value.registration_date_to,
      expiration_date_from: newFilters.expiration_date_from !== undefined ? (newFilters.expiration_date_from || '') : filters.value.expiration_date_from,
      expiration_date_to: newFilters.expiration_date_to !== undefined ? (newFilters.expiration_date_to || '') : filters.value.expiration_date_to,
      last_activation_changer: newFilters.last_activation_changer !== undefined ? (newFilters.last_activation_changer || '') : filters.value.last_activation_changer,
      last_activation_from: newFilters.last_activation_from !== undefined ? (newFilters.last_activation_from || '') : filters.value.last_activation_from,
      last_activation_to: newFilters.last_activation_to !== undefined ? (newFilters.last_activation_to || '') : filters.value.last_activation_to,
      outstanding_days_from: newFilters.outstanding_days_from !== undefined ? (newFilters.outstanding_days_from !== null && newFilters.outstanding_days_from !== '' ? Number(newFilters.outstanding_days_from) : null) : filters.value.outstanding_days_from,
      outstanding_days_to: newFilters.outstanding_days_to !== undefined ? (newFilters.outstanding_days_to !== null && newFilters.outstanding_days_to !== '' ? Number(newFilters.outstanding_days_to) : null) : filters.value.outstanding_days_to,
    };
  }
}, { deep: true });
</script>

