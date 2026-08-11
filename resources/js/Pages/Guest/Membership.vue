<template>
  <div class="min-h-screen membership-card-theme py-6 sm:py-12 px-3 sm:px-4" :dir="locale === 'ar' ? 'rtl' : 'ltr'">

    <!-- Decorative background shapes — matching card-template_back_side.tif -->

    <!-- Left vignette -->
    <div class="bg-deco deco-left-vignette"></div>
    <!-- Right vignette -->
    <div class="bg-deco deco-right-vignette"></div>
    <!-- Top gold band -->
    <div class="bg-deco deco-gold-band-top"></div>
    <!-- Bottom gold band -->
    <div class="bg-deco deco-gold-band-bottom"></div>
    <!-- Top-left corner ornament -->
    <div class="bg-deco deco-corner-tl">
      <svg viewBox="0 0 60 60" class="deco-corner-svg"><path d="M0 30 Q0 0 30 0" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.5"/><path d="M6 30 Q6 6 30 6" fill="none" stroke="#C4AD82" stroke-width="0.8" opacity="0.35"/></svg>
    </div>
    <!-- Top-right corner ornament -->
    <div class="bg-deco deco-corner-tr">
      <svg viewBox="0 0 60 60" class="deco-corner-svg"><path d="M60 30 Q60 0 30 0" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.5"/><path d="M54 30 Q54 6 30 6" fill="none" stroke="#C4AD82" stroke-width="0.8" opacity="0.35"/></svg>
    </div>

    <!-- Floating background icons -->
    <div class="bg-deco deco-icon deco-icon-1"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.25"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-2"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-3"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18"/><path d="M12 3v18"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-4"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.2"><path d="M12 3v18"/><path d="M3 12h18"/><path d="M5.64 5.64l12.72 12.72"/><path d="M18.36 5.64L5.64 18.36"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-5"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.15"><path d="M6 6h12v12H6z"/><circle cx="12" cy="12" r="4"/><path d="M12 2v20"/><path d="M2 12h20"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-6"><svg viewBox="0 0 24 24" fill="none" stroke="#D4C4A8" stroke-width="1.5" opacity="0.18"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M12 8v4l3 3"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-7"><svg viewBox="0 0 24 24" fill="none" stroke="#C4AD82" stroke-width="1.5" opacity="0.15"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-8"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.15"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10" fill="none"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-9"><svg viewBox="0 0 24 24" fill="none" stroke="#D4C4A8" stroke-width="1.5" opacity="0.12"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div>
    <div class="bg-deco deco-icon deco-icon-10"><svg viewBox="0 0 24 24" fill="none" stroke="#B19A71" stroke-width="1.5" opacity="0.12"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>

    <div class="max-w-4xl mx-auto relative z-10">
      <!-- Header -->
      <div class="flex items-start justify-between gap-3 sm:gap-4 mb-4 sm:mb-8">
        <div>
          <h1 class="text-xl sm:text-3xl font-bold text-card-foreground mb-1 sm:mb-2">{{ t.title || 'Membership Details' }}</h1>
          <p class="text-xs sm:text-sm text-muted-foreground">{{ t.subtitle || 'View your membership information' }}</p>
        </div>
        <Link
          :href="route('guest.membership-usage.create', membership.slug)"
          class="shrink-0 inline-flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-10 px-3 sm:px-4 py-1.5 sm:py-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-4 sm:h-4">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ t.add_usage || 'Add Usage' }}
        </Link>
      </div>

      <!-- Generated Membership Cards — TOP SECTION -->
      <div v-if="showCardsSection" class="bg-card text-card-foreground rounded-xl border border-border shadow-lg p-4 sm:p-8 mb-3 sm:mb-6">
        <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6">
          <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-primary/20 border-2 border-primary flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary sm:w-6 sm:h-6">
              <rect width="20" height="14" x="2" y="5" rx="2"></rect>
              <line x1="2" x2="22" y1="10" y2="10"></line>
            </svg>
          </div>
          <div>
            <h2 class="text-lg sm:text-xl font-bold text-card-foreground">{{ t.membership_cards || 'Membership Cards' }}</h2>
            <p class="text-[11px] sm:text-xs text-muted-foreground">{{ t.cards_subtitle || 'Full & Minimal card designs' }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
          <div class="flex flex-col items-center">
            <h3 class="text-xs sm:text-sm font-semibold text-card-foreground mb-2 sm:mb-3">{{ t.full_card || 'Full Design' }}</h3>
            <GuestMembershipCardCanvas
              :membership="membership"
              :partner="membership.partner"
              :card-layout="fullCardLayout"
              mode="full"
              :translations="t"
            />
          </div>
          <div class="flex flex-col items-center">
            <h3 class="text-xs sm:text-sm font-semibold text-card-foreground mb-2 sm:mb-3">{{ t.minimal_card || 'Minimal Design' }}</h3>
            <GuestMembershipCardCanvas
              :membership="membership"
              :partner="membership.partner"
              :card-layout="minimalCardLayout"
              mode="minimal"
              :translations="t"
            />
          </div>
        </div>
      </div>

      <!-- Membership Info — Compact -->
      <div class="bg-card text-card-foreground rounded-xl border border-border shadow-lg p-4 sm:p-6 mb-3 sm:mb-6">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <div class="flex items-center gap-2 sm:gap-3">
            <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-primary/20 border-2 border-primary flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary sm:w-6 sm:h-6">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
              </svg>
            </div>
            <div>
              <h2 class="text-base sm:text-xl font-bold text-card-foreground">{{ t.membership_info || 'Membership Info' }}</h2>
              <p class="text-[11px] sm:text-xs text-muted-foreground">{{ t.official_membership || 'Official Membership' }}</p>
            </div>
          </div>
          <div class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-full border shrink-0" :class="membership.is_active ? 'bg-emerald-500/25 border-emerald-500/50' : 'bg-red-500/25 border-red-500/50'">
            <span class="text-[10px] sm:text-xs font-semibold" :class="membership.is_active ? 'text-emerald-800' : 'text-red-800'">{{ membership.is_active ? (t.active || 'ACTIVE') : (t.inactive || 'INACTIVE') }}</span>
          </div>
        </div>

        <!-- Compact member info grid -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
          <div>
            <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase">{{ t.member_name || 'Name' }}</label>
            <p class="text-sm sm:text-base font-bold text-card-foreground mt-0.5 truncate">{{ membership.user?.name || 'N/A' }}</p>
            <p v-if="membership.job_title" class="text-[10px] sm:text-xs text-muted-foreground truncate">{{ membership.job_title }}</p>
            <p v-if="membership.company_name" class="text-[10px] sm:text-xs text-primary font-medium truncate">{{ membership.company_name }}</p>
          </div>
          <div>
            <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase">{{ t.membership_number || 'Number' }}</label>
            <p class="text-sm sm:text-base font-mono font-semibold text-card-foreground mt-0.5">{{ membership.membership_number }}</p>
          </div>
          <div>
            <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase">{{ t.registration_date || 'Registered' }}</label>
            <p class="text-sm sm:text-base font-semibold text-card-foreground mt-0.5">{{ formatDate(membership.registration_date) || 'N/A' }}</p>
          </div>
          <div>
            <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase">{{ t.expiration_date || 'Expires' }}</label>
            <p class="text-sm sm:text-base font-semibold text-card-foreground mt-0.5">{{ formatDate(membership.expiration_date) || 'N/A' }}</p>
          </div>
        </div>

        <div v-if="membership.user?.email" class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-border">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="min-w-0">
              <label class="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase">{{ t.email_address || 'Email' }}</label>
              <p class="text-xs sm:text-sm font-semibold text-card-foreground truncate">{{ membership.user.email }}</p>
            </div>
            <div class="inline-flex items-center gap-2 bg-primary/10 border border-primary/30 rounded-lg px-3 py-1.5 sm:px-5 sm:py-3">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary shrink-0 sm:w-[22px] sm:h-[22px]">
                <rect width="20" height="12" x="2" y="6" rx="2"/>
                <circle cx="12" cy="12" r="2"/>
                <path d="M6 12h.01M18 12h.01"/>
              </svg>
              <span class="text-lg sm:text-2xl font-bold text-primary tracking-tight">
                {{ Number(membership.total_paid ?? 0).toFixed(2) }}
                <span class="text-sm sm:text-base font-semibold text-primary/70 ms-1">{{ locale === 'ar' ? 'ج.م' : 'EGP' }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Family Members Section -->
      <div v-if="membership.family_members && membership.family_members.length > 0" class="bg-card text-card-foreground rounded-xl border border-border shadow-lg p-3 sm:p-5 mb-3 sm:mb-6">
        <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
          <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-primary/20 border-2 border-primary flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary sm:w-6 sm:h-6">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <div>
            <h2 class="text-base sm:text-lg font-bold text-card-foreground">{{ t.family_members || 'Family Members' }}</h2>
            <p class="text-[11px] sm:text-xs text-muted-foreground">{{ t.registered_family || 'Registered family members' }} ({{ membership.family_members.length }})</p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
          <div
            v-for="member in membership.family_members"
            :key="member.id"
            class="p-2 sm:p-3 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors"
          >
            <div class="flex items-center gap-2 sm:gap-3">
              <div v-if="member.has_photo" class="flex-shrink-0">
                <img :src="member.photo_url" :alt="member.name" class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg object-cover border border-border" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                  <h4 class="text-xs sm:text-sm font-semibold text-card-foreground truncate">{{ member.name }}</h4>
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium shrink-0 border" :class="member.is_active ? 'bg-emerald-500/25 text-emerald-800 border-emerald-500/40' : 'bg-red-500/25 text-red-800 border-red-500/40'">
                    {{ member.is_active ? (t.active || 'Active') : (t.inactive || 'Inactive') }}
                  </span>
                </div>
                <p class="text-[11px] sm:text-xs text-muted-foreground truncate">
                  <span class="font-medium">{{ member.relationship_label }}</span>
                  <span v-if="member.date_of_birth" :class="locale === 'ar' ? 'mr-1.5' : 'ml-1.5'">• {{ formatDate(member.date_of_birth) }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap gap-2 sm:gap-3 justify-center">
        <Link
          :href="route('home')"
          class="inline-flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border border-border hover:bg-primary hover:text-primary-foreground h-8 sm:h-10 px-3 sm:px-5 py-1.5 sm:py-2 bg-card text-card-foreground"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-4 sm:h-4" :style="locale === 'ar' ? 'transform: rotate(180deg)' : ''">
            <path d="M15 18l-6-6 6-6"></path>
          </svg>
          {{ t.back_to_home || 'Home' }}
        </Link>
        <button
          v-if="membership.slug"
          @click="showQRCodeModal = true"
          class="inline-flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-10 px-3 sm:px-5 py-1.5 sm:py-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-4 sm:h-4">
            <rect width="5" height="5" x="3" y="3" rx="1"></rect>
            <rect width="5" height="5" x="16" y="3" rx="1"></rect>
            <rect width="5" height="5" x="3" y="16" rx="1"></rect>
            <path d="M21 16h-3a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>
            <path d="M21 8h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3"></path>
          </svg>
          {{ t.show_qr_code || 'QR Code' }}
        </button>
        <button
          v-if="membership.usages && membership.usages.length > 0"
          @click="showUsagesModal = true"
          class="inline-flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-10 px-3 sm:px-5 py-1.5 sm:py-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-4 sm:h-4">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          {{ t.usages_button || 'Usages' }}
        </button>
      </div>
    </div>

    <!-- QR Code Modal -->
    <Modal :show="showQRCodeModal" max-width="md" @close="showQRCodeModal = false">
      <div
        role="dialog"
        data-slot="dialog-content"
        class="bg-popover text-popover-foreground gap-4 rounded-lg border border-border shadow-lg duration-200 max-h-[85vh] w-full p-0 overflow-y-auto"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-start px-4 sm:px-6 py-4 border-b border-border">
          <h2 data-slot="dialog-title" class="break-words pe-2 text-lg font-semibold flex flex-col items-start gap-2">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-qr-code h-5 w-5">
                <rect width="5" height="5" x="3" y="3" rx="1"></rect>
                <rect width="5" height="5" x="16" y="3" rx="1"></rect>
                <rect width="5" height="5" x="3" y="16" rx="1"></rect>
                <path d="M21 16h-3a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>
                <path d="M21 8h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3"></path>
              </svg>
              <span class="text-base sm:text-lg font-semibold">{{ t.qr_code_title || 'Membership QR Code' }}</span>
            </div>
          </h2>
        </div>

        <div class="px-4 sm:px-6 py-4">
          <div class="flex flex-col items-center space-y-4">
            <div class="bg-white p-4 rounded-lg">
              <canvas ref="qrcodeCanvas" class="mx-auto"></canvas>
            </div>
            <div class="text-center">
              <p class="text-sm text-muted-foreground mb-2">{{ t.qr_code_description || 'Scan this QR code to view membership details' }}</p>
              <p class="text-xs text-muted-foreground font-mono break-all" dir="ltr">{{ membershipUrl }}</p>
            </div>
          </div>
        </div>

        <div data-slot="dialog-footer" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end px-4 sm:px-6 py-4 border-t border-border">
          <button
            @click="showQRCodeModal = false"
            data-slot="button"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 w-full sm:w-auto"
          >
            {{ t.close || 'Close' }}
          </button>
          <button
            @click="downloadQRCode"
            data-slot="button"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            {{ t.download_qr || 'Download QR Code' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Usages Modal -->
    <Modal :show="showUsagesModal" max-width="2xl" @close="showUsagesModal = false">
      <div
        role="dialog"
        data-slot="dialog-content"
        class="bg-popover text-popover-foreground gap-4 rounded-lg border border-border shadow-lg duration-200 max-h-[85vh] w-full p-0 overflow-y-auto"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-start px-4 sm:px-6 py-4 border-b border-border sticky top-0 bg-popover z-10">
          <h2 data-slot="dialog-title" class="break-words pe-2 text-lg font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-primary">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <span class="text-base sm:text-lg font-semibold">{{ t.usages_title || 'Membership Usages' }}</span>
          </h2>
          <p class="text-xs text-muted-foreground">{{ t.usages_subtitle || 'Facility usage history' }}</p>
        </div>

        <div class="px-4 sm:px-6 py-4 space-y-4">
          <div v-if="!membership.usages || membership.usages.length === 0" class="text-sm text-muted-foreground text-center py-8">
            {{ t.no_usages || 'No usages recorded yet.' }}
          </div>

          <div
            v-for="usage in membership.usages"
            :key="usage.id"
            class="rounded-lg border border-border bg-card p-4 space-y-3"
          >
            <!-- Header row: facility info + amount + date -->
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="space-y-1">
                <p class="font-semibold text-card-foreground">{{ usage.facility_name }}</p>
                <div class="flex flex-wrap gap-2 text-xs text-card-foreground/70">
                  <span v-if="usage.facility_branch_name" class="inline-flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    {{ usage.facility_branch_name }}
                  </span>
                  <span v-if="usage.facility_type_name" class="inline-flex items-center px-2 py-0.5 rounded-full bg-primary/10 !text-card-foreground font-medium">
                    {{ usage.facility_type_name }}
                  </span>
                </div>
              </div>
              <div class="text-right space-y-1" :class="locale === 'ar' ? 'text-left' : 'text-right'">
                <p class="text-lg font-bold !text-card-foreground">{{ usage.amount }}</p>
                <p class="text-xs text-card-foreground/70">{{ formatDate(usage.created_at) }}</p>
              </div>
            </div>

            <!-- Description -->
            <p v-if="usage.description" class="text-sm text-card-foreground/80 leading-relaxed">
              {{ usage.description }}
            </p>

            <!-- Gallery -->
            <div v-if="usage.gallery && usage.gallery.length > 0" class="space-y-2">
              <p class="text-xs font-medium text-card-foreground/60 uppercase tracking-wide">{{ t.gallery || 'Gallery' }}</p>
              <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                <a
                  v-for="img in usage.gallery"
                  :key="img.id"
                  :href="img.url"
                  target="_blank"
                  rel="noopener"
                  class="block aspect-square rounded-md overflow-hidden border border-border hover:opacity-80 transition-opacity"
                >
                  <img :src="img.url" alt="" class="w-full h-full object-cover" />
                </a>
              </div>
            </div>
          </div>
        </div>

        <div data-slot="dialog-footer" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end px-4 sm:px-6 py-4 border-t border-border">
          <button
            @click="showUsagesModal = false"
            data-slot="button"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 w-full sm:w-auto"
          >
            {{ t.close || 'Close' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Membership Card Modal -->
    <Modal :show="showCardModal" max-width="2xl" @close="showCardModal = false">
      <div
        role="dialog"
        data-slot="dialog-content"
        class="bg-popover text-popover-foreground gap-4 rounded-lg border border-border shadow-lg duration-200 max-h-[85vh] w-full p-0 overflow-y-auto"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-start px-4 sm:px-6 py-4 border-b border-border">
          <h2 data-slot="dialog-title" class="break-words pe-2 text-lg font-semibold flex flex-col items-start gap-2">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-credit-card h-5 w-5">
                <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                <line x1="2" x2="22" y1="10" y2="10"></line>
              </svg>
              <span class="text-base sm:text-lg font-semibold">{{ t.membership_card || 'Membership Card' }}</span>
            </div>
          </h2>
        </div>

        <div class="px-2 sm:px-6 py-4">
          <div class="flex justify-center w-full">
            <GuestMembershipCard :membership="membership" :translations="t" :locale="locale" />
          </div>
        </div>

        <div data-slot="dialog-footer" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end px-4 sm:px-6 py-4 border-t border-border">
          <button
            @click="showCardModal = false"
            data-slot="button"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 w-full sm:w-auto"
          >
            {{ t.close || 'Close' }}
          </button>
        </div>
      </div>
    </Modal>


  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";
import { publicMembershipUrl } from "@/composables/usePublicMembershipUrl.js";
import Modal from "@/Components/Modal.vue";
import GuestMembershipCard from "./_components/GuestMembershipCard.vue";
import GuestMembershipCardCanvas from "./_components/GuestMembershipCardCanvas.vue";

const props = defineProps({
  membership: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.home?.membership_page || {});

const showCardModal = ref(false);
const showQRCodeModal = ref(false);
const showUsagesModal = ref(false);
const showCardsSection = ref(true);
const fullCardLayout = computed(() => {
  return (props.membership.card_layouts || []).find(l => l.mode === 'full') || null;
});

const minimalCardLayout = computed(() => {
  return (props.membership.card_layouts || []).find(l => l.mode === 'minimal') || null;
});
const qrcodeCanvas = ref(null);
const membershipUrl = ref("");

const generateQRCode = async () => {
  if (!props.membership || !props.membership.slug) {
    return;
  }

  try {
    // The Deilar marketing site, matching the code printed on the card — a
    // member who scans the screen and a member who scans their card should
    // land in the same place.
    membershipUrl.value = publicMembershipUrl(props.membership.slug);

    if (qrcodeCanvas.value) {
      await QRCode.toCanvas(qrcodeCanvas.value, membershipUrl.value, {
        width: 300,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      });
    }
  } catch (error) {
    console.error('Error generating QR code:', error);
  }
};

const downloadQRCode = () => {
  if (!qrcodeCanvas.value) return;

  const link = document.createElement('a');
  link.download = `membership-qrcode-${props.membership.membership_number || 'qr'}.png`;
  link.href = qrcodeCanvas.value.toDataURL('image/png');
  link.click();
};

watch(showQRCodeModal, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      generateQRCode();
    }, 100);
  }
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  } catch (error) {
    return dateString;
  }
};
</script>

<style scoped>
/* Card back side theme — warm cream/gold palette matching card-template_back_side.tif */
.membership-card-theme {
  --background: #EDE8E3;
  --foreground: #1A1816;
  --card: #E0DBD5;
  --card-foreground: #1A1816;
  --popover: #F8F5F2;
  --popover-foreground: #1A1816;
  --primary: #B19A71;
  --primary-foreground: #FFFFFE;
  --primary-medium: #C4AD82;
  --primary-medium-foreground: #FFFFFE;
  --secondary: #D6CCC8;
  --secondary-foreground: #1A1816;
  --muted: #F2EFEC;
  --muted-foreground: #8B7D70;
  --accent: #D6CCC8;
  --accent-foreground: #1A1816;
  --destructive: #C76A5E;
  --destructive-foreground: #FFFFFE;
  --border: #C4B9AE;
  --input: #F2EFEC;
  --ring: #B19A71;
  background-color: var(--background);
  color: var(--foreground);
  position: relative;
  overflow-x: hidden;
}

/* More visible scrollbar for membership theme */
.membership-card-theme {
  scrollbar-width: auto;
  scrollbar-color: #B19A71 #E0DBD5;
}
.membership-card-theme::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
.membership-card-theme::-webkit-scrollbar-track {
  background: #E0DBD5;
  border-radius: 5px;
}
.membership-card-theme::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #B19A71, #C4AD82);
  border-radius: 5px;
  border: 2px solid #E0DBD5;
  transition: background 0.3s ease;
}
.membership-card-theme::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #9B8664, #B19A71);
}
.membership-card-theme::-webkit-scrollbar-corner {
  background: #E0DBD5;
}

/* Floating icon animations */
@keyframes deco-float-1 {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  25% { transform: translate(8px, -12px) rotate(5deg); }
  50% { transform: translate(0, -20px) rotate(0deg); }
  75% { transform: translate(-8px, -12px) rotate(-5deg); }
}
@keyframes deco-float-2 {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  33% { transform: translate(-10px, -8px) rotate(-3deg); }
  66% { transform: translate(10px, -15px) rotate(3deg); }
}
@keyframes deco-float-3 {
  0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
  50% { transform: translate(12px, -10px) rotate(180deg) scale(1.1); }
}
@keyframes deco-pulse {
  0%, 100% { opacity: 0.12; }
  50% { opacity: 0.28; }
}
@keyframes deco-spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes deco-drift {
  0%, 100% { transform: translate(0, 0); }
  25% { transform: translate(15px, -5px); }
  50% { transform: translate(5px, -15px); }
  75% { transform: translate(-10px, -8px); }
}

/* Floating icon positions */
.deco-icon {
  position: fixed;
  pointer-events: none;
  z-index: 0;
}
.deco-icon svg {
  width: 100%;
  height: 100%;
}
.deco-icon-1 { top: 8%; left: 5%; width: 36px; height: 36px; animation: deco-float-1 8s ease-in-out infinite; }
.deco-icon-2 { top: 18%; right: 6%; width: 42px; height: 42px; animation: deco-float-2 10s ease-in-out infinite; }
.deco-icon-3 { top: 45%; left: 3%; width: 48px; height: 48px; animation: deco-spin-slow 25s linear infinite; }
.deco-icon-4 { top: 70%; right: 4%; width: 38px; height: 38px; animation: deco-float-1 12s ease-in-out infinite; }
.deco-icon-5 { top: 85%; left: 8%; width: 44px; height: 44px; animation: deco-float-2 9s ease-in-out infinite; }
.deco-icon-6 { top: 35%; right: 3%; width: 40px; height: 40px; animation: deco-drift 11s ease-in-out infinite; }
.deco-icon-7 { top: 55%; left: 6%; width: 46px; height: 46px; animation: deco-float-3 14s ease-in-out infinite; }
.deco-icon-8 { top: 5%; right: 12%; width: 32px; height: 32px; animation: deco-pulse 4s ease-in-out infinite; }
.deco-icon-9 { top: 75%; right: 10%; width: 40px; height: 40px; animation: deco-float-2 13s ease-in-out infinite; }
.deco-icon-10 { top: 60%; left: 2%; width: 34px; height: 34px; animation: deco-spin-slow 30s linear infinite; }

/* Zoom slider styling */
.zoom-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 160px;
  max-width: 60vw;
  height: 6px;
  border-radius: 3px;
  background: linear-gradient(to right, #D6CCC8, #B19A71);
  outline: none;
  cursor: pointer;
  transition: opacity 0.2s;
}
.zoom-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #B19A71;
  border: 2px solid #FFFFFE;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.zoom-slider::-webkit-slider-thumb:hover {
  transform: scale(1.15);
  box-shadow: 0 3px 10px rgba(0,0,0,0.3);
}
.zoom-slider::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #B19A71;
  border: 2px solid #FFFFFE;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  cursor: pointer;
}

/* Enhance existing deco with effects */
.deco-left-vignette {
  animation: deco-pulse 6s ease-in-out infinite;
}
.deco-right-vignette {
  animation: deco-pulse 6s ease-in-out infinite;
  animation-delay: 3s;
}
.deco-gold-band-top {
  animation: deco-pulse 5s ease-in-out infinite;
}
.deco-gold-band-bottom {
  animation: deco-pulse 5s ease-in-out infinite;
  animation-delay: 2.5s;
}
</style>

<!-- Unscoped decorative background shapes -->
<style>
.bg-deco {
  position: fixed;
  pointer-events: none;
  z-index: 0;
}

/* Left side vignette — warm gray gradient */
.deco-left-vignette {
  top: 0;
  left: 0;
  width: 140px;
  height: 100vh;
  background: linear-gradient(to right, #C9C3C1 0%, #DCD6D4 45%, transparent 100%);
}

/* Right side vignette */
.deco-right-vignette {
  top: 0;
  right: 0;
  width: 140px;
  height: 100vh;
  background: linear-gradient(to left, #C9C3C1 0%, #DCD6D4 45%, transparent 100%);
}

/* Top gold decorative band — matching card's main gold stripe */
.deco-gold-band-top {
  top: 12vh;
  left: 50%;
  transform: translateX(-50%);
  width: 64%;
  max-width: 900px;
  height: 3px;
  background: linear-gradient(to right,
    transparent 0%,
    #B19A71 18%,
    #A28C68 35%,
    #9B8664 50%,
    #A28C68 65%,
    #B19A71 82%,
    transparent 100%
  );
  opacity: 0.45;
  border-radius: 2px;
}

/* Bottom gold decorative band — narrower, matching secondary stripe */
.deco-gold-band-bottom {
  top: 22vh;
  left: 50%;
  transform: translateX(-50%);
  width: 48%;
  max-width: 650px;
  height: 2px;
  background: linear-gradient(to right,
    transparent 0%,
    #917D5D 20%,
    #9B8664 40%,
    #A28C68 60%,
    #917D5D 80%,
    transparent 100%
  );
  opacity: 0.3;
  border-radius: 1px;
}

/* Corner ornaments */
.deco-corner-tl {
  top: 40px;
  left: 30px;
  width: 60px;
  height: 60px;
}

.deco-corner-tr {
  top: 40px;
  right: 30px;
  width: 60px;
  height: 60px;
}

.deco-corner-svg {
  width: 100%;
  height: 100%;
}

/* Body scrollbar — membership gold theme */
body:has(.membership-card-theme) {
  scrollbar-width: auto;
  scrollbar-color: #B19A71 #E0DBD5;
}
body:has(.membership-card-theme)::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
body:has(.membership-card-theme)::-webkit-scrollbar-track {
  background: #E0DBD5;
  border-radius: 5px;
}
body:has(.membership-card-theme)::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #B19A71, #C4AD82);
  border-radius: 5px;
  border: 2px solid #E0DBD5;
  transition: background 0.3s ease;
}
body:has(.membership-card-theme)::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #9B8664, #B19A71);
}
body:has(.membership-card-theme)::-webkit-scrollbar-corner {
  background: #E0DBD5;
}
</style>
