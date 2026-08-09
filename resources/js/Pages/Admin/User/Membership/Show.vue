<template>
  <MemberLayout>
    <div class="max-w-7xl mx-auto mt-4 sm:mt-6 px-3 sm:px-4 md:px-6 lg:px-8 pb-6 sm:pb-8">
      <div class="space-y-4 sm:space-y-6">
        <!-- User Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-4 sm:p-6 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.member?.user_information || 'User Information' }}</h2>
          </div>
          <div class="p-4 sm:p-6">
            <div class="flex flex-col md:flex-row gap-4 sm:gap-6">
              <!-- Avatar -->
              <div class="flex-shrink-0">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                  <img
                    v-if="user.avatar_url"
                    :src="user.avatar_url"
                    :alt="user.name"
                    class="w-full h-full object-cover"
                  />
                  <div
                    v-else
                    class="w-full h-full flex items-center justify-center text-xl font-semibold text-white"
                  >
                    {{ getUserInitials(user.name) }}
                  </div>
                </div>
              </div>

              <!-- User Details -->
              <div class="flex-1 grid grid-cols-2 md:grid-cols-5 gap-4 sm:gap-6">
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.common?.name || 'Name' }}</label>
                  <p class="text-sm font-medium mt-0.5 text-white">{{ user.name || '—' }}</p>
                </div>
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.common?.email || 'Email' }}</label>
                  <p class="text-sm font-medium mt-0.5 text-white">{{ user.email || '—' }}</p>
                </div>
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.common?.phone || 'Phone' }}</label>
                  <p class="text-sm font-medium mt-0.5 text-white">{{ user.phone || '—' }}</p>
                </div>
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.member?.user_id || 'User ID' }}</label>
                  <p class="text-sm font-medium mt-0.5 text-white">#{{ user.id }}</p>
                </div>
                <div>
                  <label class="text-xs font-medium text-muted-foreground">{{ t.member?.slug_label || 'Slug' }}</label>
                  <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ user.slug }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- All Memberships Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-4 sm:p-6 border-b border-border">
            <h2 class="text-lg font-semibold text-white">
              {{ t.member?.all_memberships || 'All Memberships' }} ({{ user.memberships?.length || 0 }})
            </h2>
          </div>
          <div class="p-4 sm:p-6">
            <div v-if="user.memberships && user.memberships.length > 0" class="space-y-3 sm:space-y-4">
              <div
                v-for="membership in user.memberships"
                :key="membership.id"
                class="border border-border rounded-lg p-4 sm:p-5 hover:bg-muted/10 transition-colors space-y-4"
                :class="membership.is_active ? 'ring-2 ring-green-500/50' : ''"
              >
                <!-- Core fields -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 text-sm">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.membership_number || 'Membership Number' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white font-mono">{{ membership.membership_number }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.registration_date || 'Registration Date' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ formatDateTime(membership.registration_date) }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.expiration_date || 'Expiration Date' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ formatDateTime(membership.expiration_date) }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.job_title || 'Job Title' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ membership.job_title || '—' }}</p>
                  </div>
                </div>

                <!-- Public QR link — the exact URL this membership's QR code encodes. -->
                <div v-if="membership.slug">
                  <label class="text-xs font-medium text-white">{{ t.member?.qr_link || 'QR Code Link' }}</label>
                  <div class="mt-0.5 flex flex-wrap items-center gap-2">
                    <a
                      :href="membershipPublicUrl(membership)"
                      target="_blank"
                      rel="noopener"
                      class="text-xs font-mono break-all text-white hover:underline"
                    >
                      {{ membershipPublicUrl(membership) }}
                    </a>
                    <button
                      type="button"
                      @click="copyMembershipUrl(membership)"
                      class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border border-border bg-background px-2 py-1 text-xs font-medium transition-colors hover:bg-primary hover:text-primary-foreground"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                      </svg>
                      {{ copiedMembershipSlug === membership.slug ? (t.common?.copied || 'Copied') : (t.common?.copy || 'Copy') }}
                    </button>
                  </div>
                </div>

                <!-- Status badges -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 text-sm">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.status || 'Status' }}</label>
                    <p class="mt-0.5">
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                          membership.is_active
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                        ]"
                      >
                        {{ membership.is_active ? (t.member?.active || 'Active') : (t.member?.inactive || 'Inactive') }}
                      </span>
                    </p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.visibility || 'Visibility' }}</label>
                    <p class="mt-0.5">
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                          membership.is_visible
                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        ]"
                      >
                        {{ membership.is_visible ? (t.member?.visible || 'Visible') : (t.member?.hidden || 'Hidden') }}
                      </span>
                    </p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.payment || 'Payment' }}</label>
                    <p class="mt-0.5">
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                          membership.is_paid
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                        ]"
                      >
                        {{ membership.is_paid ? (t.member?.paid || 'Paid') : (t.member?.unpaid || 'Unpaid') }}
                      </span>
                    </p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.completion || 'Completion' }}</label>
                    <p class="mt-0.5">
                      <span
                        :class="[
                          'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                          membership.completed_at
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
                        ]"
                      >
                        {{ membership.completed_at ? (t.member?.completed || 'Completed') : (t.member?.incomplete || 'Incomplete') }}
                      </span>
                    </p>
                  </div>
                </div>

                <!-- Org / location -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 text-sm">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.company || 'Company' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ membership.company_name || '—' }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.partner || 'Partner' }}</label>
                    <div class="flex items-center gap-2 mt-0.5">
                      <img
                        v-if="membership.partner_image"
                        :src="membership.partner_image"
                        :alt="membership.partner_name"
                        class="w-5 h-5 rounded-full object-cover"
                      />
                      <p class="text-sm font-medium text-white truncate">{{ membership.partner_name || '—' }}</p>
                    </div>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.sales || 'Sales' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ membership.sale_name || '—' }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.governorate || 'Governorate' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ membership.governorate_name || '—' }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member?.city || 'City' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ membership.city_name || '—' }}</p>
                  </div>
                </div>

                <!-- Audit -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 text-sm pt-2 border-t border-border/60">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.common?.created_date || 'Created Date' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ formatDateTime(membership.created_at) }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member_list?.table?.column_updated_date || 'Updated Date' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ formatDateTime(membership.updated_at) }}</p>
                  </div>
                  <div v-if="membership.creator">
                    <label class="text-xs font-medium text-muted-foreground">{{ t.member_list?.table?.column_creator || 'Creator' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white truncate">{{ membership.creator.name }}</p>
                    <p v-if="membership.creator.email" class="text-xs text-muted-foreground truncate">{{ membership.creator.email }}</p>
                  </div>
                </div>

                <!-- Contract image -->
                <div class="pt-4 border-t border-border">
                  <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    {{ t.member?.contract_image_label || 'Contract Image' }}
                  </h3>
                  <div v-if="membership.contract_image_url" class="max-w-xs">
                    <a :href="membership.contract_image_url" target="_blank" rel="noopener">
                      <img :src="membership.contract_image_url" alt="Contract" class="w-full rounded-lg border border-border object-cover" />
                    </a>
                  </div>
                  <p v-else class="text-xs text-white/60">{{ t.member?.no_contract_image || 'No contract image uploaded.' }}</p>
                </div>

                <!-- Gallery -->
                <div class="pt-4 border-t border-border">
                  <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                      <circle cx="9" cy="9" r="2"/>
                      <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                    </svg>
                    {{ t.member?.gallery_label || 'Gallery' }}
                    <span v-if="membership.gallery_images?.length" class="text-xs text-muted-foreground">({{ membership.gallery_images.length }})</span>
                  </h3>
                  <div v-if="membership.gallery_images && membership.gallery_images.length > 0" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    <a
                      v-for="img in membership.gallery_images"
                      :key="img.id"
                      :href="img.url"
                      target="_blank"
                      rel="noopener"
                      class="aspect-square rounded-md overflow-hidden border border-border block"
                    >
                      <img :src="img.url" :alt="img.name" class="w-full h-full object-cover" />
                    </a>
                  </div>
                  <p v-else class="text-xs text-white/60">{{ t.member?.no_gallery_images || 'No gallery images uploaded.' }}</p>
                </div>

                <!-- Family Members Section -->
                <div v-if="membership.family_members && membership.family_members.length > 0" class="pt-4 border-t border-border">
                  <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                      <circle cx="9" cy="7" r="4"></circle>
                      <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    {{ t.member?.family_members_count || 'Family Members' }} ({{ membership.family_members.length }})
                  </h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                      v-for="member in membership.family_members"
                      :key="member.id"
                      class="p-4 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors"
                    >
                      <div class="flex items-start gap-3">
                        <div v-if="member.photo_url" class="flex-shrink-0">
                          <img :src="member.photo_url" :alt="member.name" class="w-12 h-12 rounded-lg object-cover border border-border" />
                        </div>
                        <div v-else class="flex-shrink-0 w-12 h-12 rounded-lg bg-muted flex items-center justify-center border border-border">
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/70">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                          </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-white text-sm">{{ member.name }}</h4>
                            <span
                              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                              :class="member.is_active ? 'bg-emerald-500/30 text-emerald-200 border border-emerald-500/40' : 'bg-slate-500/25 text-slate-300 border border-slate-500/30'"
                            >
                              {{ member.is_active ? (t.member?.active || 'Active') : (t.member?.inactive || 'Inactive') }}
                            </span>
                          </div>
                          <p class="text-xs text-white/80 mb-1">
                            <span class="font-medium">{{ member.relationship_label || member.relationship }}</span>
                            <span v-if="member.date_of_birth" class="ml-2">
                              • {{ t.member?.born_label || 'Born' }}: {{ formatDate(member.date_of_birth) }}
                            </span>
                          </p>
                          <div class="flex flex-wrap gap-3 text-xs text-white/70">
                            <span v-if="member.phone" class="flex items-center gap-1">
                              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                              </svg>
                              {{ member.phone }}
                            </span>
                            <span v-if="member.email" class="flex items-center gap-1">
                              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                              </svg>
                              {{ member.email }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else-if="membership.family_members && membership.family_members.length === 0" class="pt-4 border-t border-border">
                  <p class="text-xs text-white/60">{{ t.member?.no_family_members_membership || 'No family members registered for this membership.' }}</p>
                </div>
              </div>
            </div>
            <div v-else class="text-center text-muted-foreground py-4">
              <p class="text-white">{{ t.member?.no_memberships || 'No memberships found.' }}</p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 justify-end pt-2 sm:pt-4">
          <Link
            :href="route('admin.user.membership.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>

          <button
            v-if="activeMembership && activeMembership.slug"
            @click="showQRCodeModal = true"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white shadow-xs hover:bg-blue-700 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <rect width="5" height="5" x="3" y="3" rx="1"></rect>
              <rect width="5" height="5" x="16" y="3" rx="1"></rect>
              <rect width="5" height="5" x="3" y="16" rx="1"></rect>
              <path d="M21 16h-3a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>
              <path d="M21 8h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3"></path>
            </svg>
            {{ t.member?.show_qr_code || 'Show QR Code' }}
          </button>

          <div class="flex items-center justify-center gap-2">
            <!-- Create Card -->
            <a
              :href="getCardGeneratorUrl(memberForActions)"
              target="_blank"
              class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3"
              style="color:#D4AF6E;"
              :title="t.member_list?.table?.action_create_card || 'Create Card'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
            </a>
            <!-- Logs -->
            <Link
              :href="getLogsRoute(user.slug)"
              class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-blue-500 hover:!bg-blue-500/10 hover:!text-blue-500"
              :title="t.member_list?.table?.action_logs || 'View Activity Logs'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history w-3 h-3"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path></svg>
            </Link>
            <!-- Payments -->
            <Link
              :href="route('admin.member-payment.create')"
              class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-violet-500 hover:!bg-violet-500/10 hover:!text-violet-500"
              :title="t.member_list?.table?.action_payments || 'Payments'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wallet w-3 h-3"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
            </Link>
            <!-- Edit -->
            <Link
              v-if="user.slug"
              :href="route('admin.user.membership.edit', user.slug)"
              class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
              :title="t.member?.edit_membership || 'Edit'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path></svg>
            </Link>
            <!-- Delete -->
            <button
              v-if="user.slug"
              @click="confirmDelete"
              class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive/10 hover:text-destructive dark:bg-input/30 dark:border-input dark:hover:bg-destructive/10 h-8 rounded-md gap-1.5 px-3 text-destructive"
              :title="t.member?.delete_membership || 'Delete'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-3 h-3"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </button>
          </div>
        </div>

        <!-- Generated Cards Section -->
        <div v-for="membership in user.memberships" :key="'cards-' + membership.id" class="rounded-xl border border-border bg-card p-4 sm:p-6 space-y-4">
          <h3 class="text-base font-semibold text-white flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
            Cards — {{ membership.membership_number }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Full Design -->
            <div class="rounded-lg border border-border bg-muted/20 p-3 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-white">Full Design</span>
                <span v-if="getCardLayout(membership, 'full')" class="text-xs text-emerald-400">&#10003; Generated</span>
                <span v-else class="text-xs text-amber-400">Default layout</span>
              </div>
              <div class="card-flip-outer" @click="openAdminCardPopup(membership, 'full')">
                <div class="card-flip-container-admin" :style="{ aspectRatio: '1063 / 650' }">
                  <div class="card-flip-inner-admin" :class="{ flipped: flipState[membership.id + '-full'] }">
                    <div class="card-face-admin card-face-front">
                      <img
                        v-if="getCardLayout(membership, 'full')?.image_url"
                        :src="getCardLayout(membership, 'full').image_url"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px"
                        alt="Full design card"
                      />
                      <canvas
                        v-else
                        :ref="el => setCardCanvas(el, membership.id, 'full')"
                        width="1063"
                        height="650"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px; width: auto"
                      ></canvas>
                    </div>
                    <div class="card-face-admin card-face-back">
                      <img
                        src="/card-template_back_side.png"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px"
                        alt="Card back side"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/30 text-white hover:bg-muted/50 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                  @click="toggleCardFlip(membership.id, 'full')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                  Flip
                </button>
                <a
                  v-if="getCardLayout(membership, 'full')?.image_url"
                  :href="getCardLayout(membership, 'full').image_url"
                  download
                  class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                  Download
                </a>
                <button
                  v-else
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                  @click="downloadCardCanvas(membership, 'full')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                  Download
                </button>
              </div>
            </div>
            <!-- Minimal Design -->
            <div class="rounded-lg border border-border bg-muted/20 p-3 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-white">Minimal Design</span>
                <span v-if="getCardLayout(membership, 'minimal')" class="text-xs text-emerald-400">&#10003; Generated</span>
                <span v-else class="text-xs text-amber-400">Default layout</span>
              </div>
              <div class="card-flip-outer" @click="openAdminCardPopup(membership, 'minimal')">
                <div class="card-flip-container-admin" :style="{ aspectRatio: '1063 / 650' }">
                  <div class="card-flip-inner-admin" :class="{ flipped: flipState[membership.id + '-minimal'] }">
                    <div class="card-face-admin card-face-front">
                      <img
                        v-if="getCardLayout(membership, 'minimal')?.image_url"
                        :src="getCardLayout(membership, 'minimal').image_url"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px"
                        alt="Minimal design card"
                      />
                      <canvas
                        v-else
                        :ref="el => setCardCanvas(el, membership.id, 'minimal')"
                        width="1063"
                        height="650"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px; width: auto"
                      ></canvas>
                    </div>
                    <div class="card-face-admin card-face-back">
                      <img
                        src="/card-template_back_side.png"
                        class="max-w-full h-auto rounded shadow"
                        style="max-height: 220px"
                        alt="Card back side"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/30 text-white hover:bg-muted/50 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                  @click="toggleCardFlip(membership.id, 'minimal')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                  Flip
                </button>
                <a
                  v-if="getCardLayout(membership, 'minimal')?.image_url"
                  :href="getCardLayout(membership, 'minimal').image_url"
                  download
                  class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                  Download
                </a>
                <button
                  v-else
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                  @click="downloadCardCanvas(membership, 'minimal')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                  Download
                </button>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>

      <!-- Payments Section -->
      <div v-for="membership in user.memberships" :key="'payments-' + membership.id" class="rounded-xl border border-border bg-card p-4 sm:p-6 space-y-4">
        <h3 class="text-base font-semibold text-white flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
          Payments — {{ membership.membership_number }}
        </h3>
        <div v-if="membership.member_payments?.length" class="overflow-x-auto">
          <table class="w-full text-xs sm:text-sm">
            <thead>
              <tr class="border-b border-border">
                <th class="text-left text-muted-foreground font-medium px-2 py-1.5">{{ t.member?.payment_amount || 'Amount' }}</th>
                <th class="text-center text-muted-foreground font-medium px-2 py-1.5">{{ t.member?.payment_months || 'Months' }}</th>
                <th class="text-center text-muted-foreground font-medium px-2 py-1.5 hidden sm:table-cell">{{ t.member?.payment_period || 'Period' }}</th>
                <th class="text-left text-muted-foreground font-medium px-2 py-1.5 hidden lg:table-cell">{{ t.member?.payment_notes || 'Notes' }}</th>
                <th class="text-left text-muted-foreground font-medium px-2 py-1.5 hidden md:table-cell">{{ t.member?.payment_date || 'Date' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pmt in membership.member_payments" :key="pmt.id" class="border-b border-border/50 hover:bg-muted/20">
                <td class="px-2 py-1.5 font-medium text-white">{{ formatAmount(pmt.amount) }}</td>
                <td class="px-2 py-1.5 text-center text-muted-foreground">{{ pmt.months_paid }}</td>
                <td class="px-2 py-1.5 text-center text-muted-foreground whitespace-nowrap hidden sm:table-cell">{{ pmt.from_date }} → {{ pmt.to_date }}</td>
                <td class="px-2 py-1.5 text-muted-foreground hidden lg:table-cell">{{ pmt.notes || '—' }}</td>
                <td class="px-2 py-1.5 text-muted-foreground whitespace-nowrap hidden md:table-cell">{{ pmt.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-sm text-muted-foreground">{{ t.member?.no_payments || 'No payments recorded for this membership.' }}</div>
      </div>

      <!-- Membership Card Modal -->
    <Modal :show="showCardModal" max-width="xl" @close="showCardModal = false">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-white">{{ t.member?.membership_card || 'Membership Card' }}</h2>
          <button
            @click="showCardModal = false"
            class="text-gray-400 hover:text-white transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
              <path d="M18 6L6 18"></path>
              <path d="M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div ref="membershipCardRef" class="flex justify-center mb-4 bg-white p-4 rounded-lg">
          <MembershipCard v-if="activeMembership" :user="user" :membership="activeMembership" />
        </div>
      </div>
    </Modal>

    <!-- QR Code Modal -->
    <Modal :show="showQRCodeModal" max-width="md" @close="showQRCodeModal = false">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-white">{{ t.member?.membership_qr_code || 'Membership QR Code' }}</h2>
          <button
            @click="showQRCodeModal = false"
            class="text-gray-400 hover:text-white transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
              <path d="M18 6L6 18"></path>
              <path d="M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="flex flex-col items-center space-y-4">
          <div class="bg-white p-4 rounded-lg">
            <canvas ref="qrcodeCanvas" class="mx-auto"></canvas>
          </div>
          <div class="text-center">
            <p class="text-sm text-muted-foreground mb-2">{{ t.member?.qr_scan_help || 'Scan this QR code to view membership details' }}</p>
            <p class="text-xs text-muted-foreground font-mono break-all">{{ membershipUrl }}</p>
          </div>
          <button
            @click="downloadQRCode"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            {{ t.member?.download_qr_code || 'Download QR Code' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Card Popup Modal -->
    <Modal :show="showAdminCardPopup" max-width="2xl" @close="showAdminCardPopup = false">
      <div class="p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white">
            {{ adminPopupMode === 'full' ? 'Full Design' : 'Minimal Design' }} — {{ adminPopupMembership?.membership_number }}
          </h2>
          <button
            @click="showAdminCardPopup = false"
            class="text-gray-400 hover:text-white transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
              <path d="M18 6L6 18"></path>
              <path d="M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="flex justify-center mb-3">
          <div class="w-full max-w-lg">
            <div class="card-flip-outer">
              <div class="card-flip-container-admin" :style="{ aspectRatio: '1063 / 650' }">
                <div class="card-flip-inner-admin" :class="{ flipped: flipState['popup'] }">
                  <div class="card-face-admin card-face-front">
                    <div class="bg-white rounded-md p-2 flex justify-center">
                      <img
                        v-if="adminPopupMembership && getCardLayout(adminPopupMembership, adminPopupMode)?.image_url"
                        :src="getCardLayout(adminPopupMembership, adminPopupMode).image_url"
                        class="max-w-full h-auto rounded shadow"
                        alt="Card"
                      />
                      <canvas
                        v-else
                        :ref="el => setPopupCardCanvas(el)"
                        width="1063"
                        height="650"
                        class="max-w-full h-auto rounded shadow"
                      ></canvas>
                    </div>
                  </div>
                  <div class="card-face-admin card-face-back">
                    <div class="bg-white rounded-md p-2 flex justify-center">
                      <img
                        src="/card-template_back_side.png"
                        class="max-w-full h-auto rounded shadow"
                        alt="Card back side"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/30 text-white hover:bg-muted/50 px-3 py-1.5 text-sm font-medium shadow-sm transition-colors cursor-pointer"
            @click="toggleCardFlip('popup', '')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Flip Card
          </button>
        </div>
      </div>
    </Modal>
  </MemberLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, reactive } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";
import html2canvas from "html2canvas";
import MemberLayout from "../Member/MemberLayout.vue";
import Modal from "@/Components/Modal.vue";
import MembershipCard from "./_components/MembershipCard.vue";
import { renderCardCanvas } from "@/Pages/Admin/MembershipCard/_components/cardRenderer.js";

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const showCardModal = ref(false);
const showQRCodeModal = ref(false);
const showAdminCardPopup = ref(false);
const adminPopupMembership = ref(null);
const adminPopupMode = ref('full');
const qrcodeCanvas = ref(null);
const membershipCardRef = ref(null);
const membershipUrl = ref("");
const copiedMembershipSlug = ref(null);
const flipState = reactive({});
const popupCardCanvasRef = ref(null);

function toggleCardFlip(membershipId, mode) {
  const key = `${membershipId}-${mode}`;
  flipState[key] = !flipState[key];
}

function openAdminCardPopup(membership, mode) {
  adminPopupMembership.value = membership;
  adminPopupMode.value = mode;
  flipState['popup'] = false;
  showAdminCardPopup.value = true;
  setTimeout(() => {
    if (popupCardCanvasRef.value) {
      renderMembershipCardPopup(membership, mode);
    }
  }, 200);
}

function setPopupCardCanvas(el) {
  if (!el) return;
  popupCardCanvasRef.value = el;
}

const activeMembership = computed(() => {
  if (!props.user.memberships || props.user.memberships.length === 0) {
    return null;
  }
  return props.user.memberships.find((m) => m.is_active) || props.user.memberships[0];
});

const memberForActions = computed(() => {
  const m = activeMembership.value;
  if (!m) return null;
  return {
    name: props.user.name,
    avatar_url: props.user.avatar_url,
    membership: m,
  };
});

const getCardGeneratorUrl = (member) => {
  if (!member) return '#';
  const params = new URLSearchParams();
  if (member.name) params.set('name', member.name);
  if (member.membership?.membership_number) params.set('policy', member.membership.membership_number);
  if (member.membership?.expiration_date) {
    const d = new Date(member.membership.expiration_date);
    params.set('valid', `${d.getMonth() + 1} / ${d.getFullYear()}`);
  }
  if (member.membership?.slug) {
    const base = window.location.origin;
    params.set('url', `${base}/membership/${member.membership.slug}`);
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

// The public page a membership's QR code points at. Returned absolute so the
// value shown on screen is exactly what gets encoded into the card and what
// someone scanning it will land on.
const membershipPublicUrl = (membership) => {
  const slug = typeof membership === "string" ? membership : membership?.slug;
  if (!slug) {
    return "";
  }

  let url = route("guest.membership.show", slug);

  if (!url.startsWith("http://") && !url.startsWith("https://")) {
    if (!url.startsWith("/")) {
      url = "/" + url;
    }
    url = window.location.origin + url;
  }

  return url;
};

const copyMembershipUrl = async (membership) => {
  const url = membershipPublicUrl(membership);
  if (!url) {
    return;
  }

  try {
    await navigator.clipboard.writeText(url);
  } catch {
    // Clipboard API is unavailable (older browser / insecure context) — fall
    // back to a throwaway textarea so the button still works.
    const textarea = document.createElement("textarea");
    textarea.value = url;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand("copy");
    document.body.removeChild(textarea);
  }

  copiedMembershipSlug.value = membership.slug;
  setTimeout(() => {
    if (copiedMembershipSlug.value === membership.slug) {
      copiedMembershipSlug.value = null;
    }
  }, 2000);
};

const generateQRCode = async () => {
  if (!activeMembership.value || !activeMembership.value.slug) {
    return;
  }

  try {
    membershipUrl.value = membershipPublicUrl(activeMembership.value);

    if (qrcodeCanvas.value) {
      await QRCode.toCanvas(qrcodeCanvas.value, membershipUrl.value, {
        width: 300,
        margin: 2,
        color: {
          dark: "#000000",
          light: "#FFFFFF",
        },
      });
    }
  } catch (error) {
    console.error("Error generating QR code:", error);
  }
};

const downloadQRCode = () => {
  if (!qrcodeCanvas.value) return;

  const link = document.createElement("a");
  link.download = `membership-qrcode-${activeMembership.value?.membership_number || "qr"}.png`;
  link.href = qrcodeCanvas.value.toDataURL("image/png");
  link.click();
};

watch(showQRCodeModal, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      generateQRCode();
    }, 100);
  }
});

const getUserInitials = (name) => {
  if (!name) return "??";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const formatDateTime = (dateString) => {
  if (!dateString) return "—";
  try {
    const date = new Date(dateString);
    return date.toLocaleString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch (error) {
    return dateString;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
  } catch (error) {
    return dateString;
  }
};

const formatAmount = (amount) => {
  if (amount === null || amount === undefined) return '-';
  return Number(amount).toFixed(2);
};

const cardCanvases = reactive({});

function setCardCanvas(el, membershipId, mode) {
  if (!el) return;
  const key = `${membershipId}-${mode}`;
  if (cardCanvases[key] === el) return;
  cardCanvases[key] = el;
  renderMembershipCard(membershipId, mode);
}

function getCardLayout(membership, mode) {
  if (!membership.card_layouts) return null;
  const layout = membership.card_layouts.find((cl) => cl.mode === mode) || null;
  if (!layout) return null;
  // For minimal mode, only consider layout as valid if it has actual position data
  if (mode === 'minimal' && layout.qr_x == null && layout.fields_x == null && layout.partner_x == null) {
    return null;
  }
  return layout;
}

async function renderMembershipCard(membershipId, mode) {
    const key = `${membershipId}-${mode}`;
    const canvas = cardCanvases[key];
    if (!canvas) return;

    const membership = props.user.memberships?.find((m) => m.id === membershipId);
    if (!membership) return;

    const layout = getCardLayout(membership, mode);
    const partner = membership.partner_id
      ? { image: membership.partner_image }
      : null;
    const overrides = layout
      ? {
          qr: layout.qr_x != null ? { x: Number(layout.qr_x), y: Number(layout.qr_y), scale: Number(layout.qr_scale) } : undefined,
          fields: layout.fields_x != null ? { x: Number(layout.fields_x), y: Number(layout.fields_y), scale: Number(layout.fields_scale) } : undefined,
          partner: layout.partner_x != null ? { x: Number(layout.partner_x), y: Number(layout.partner_y), scale: Number(layout.partner_scale) } : undefined,
        }
      : null;

    try {
      const rendered = await renderCardCanvas({
        membership: { membership_number: membership.membership_number, slug: membership.slug },
        partner,
        overrides,
      });
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(rendered, 0, 0);
    } catch (e) {
      console.error('Card render failed:', e);
    }
  }

  async function renderMembershipCardPopup(membership, mode) {
    const canvas = popupCardCanvasRef.value;
    if (!canvas || !membership) return;

    const layout = getCardLayout(membership, mode);
    if (layout?.image_url) return;

    const partner = membership.partner_id
      ? { image: membership.partner_image }
      : null;
    const overrides = layout
      ? {
          qr: layout.qr_x != null ? { x: Number(layout.qr_x), y: Number(layout.qr_y), scale: Number(layout.qr_scale) } : undefined,
          fields: layout.fields_x != null ? { x: Number(layout.fields_x), y: Number(layout.fields_y), scale: Number(layout.fields_scale) } : undefined,
          partner: layout.partner_x != null ? { x: Number(layout.partner_x), y: Number(layout.partner_y), scale: Number(layout.partner_scale) } : undefined,
        }
      : null;

    try {
      const rendered = await renderCardCanvas({
        membership: { membership_number: membership.membership_number, slug: membership.slug },
        partner,
        overrides,
      });
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(rendered, 0, 0);
    } catch (e) {
      console.error('Popup card render failed:', e);
    }
  }

function downloadCardCanvas(membership, mode) {
  const key = `${membership.id}-${mode}`;
  const canvas = cardCanvases[key];
  if (!canvas) return;
  const a = document.createElement('a');
  a.download = `card-${membership.membership_number}-${mode}.png`;
  a.href = canvas.toDataURL('image/png');
  a.click();
}

function confirmDelete() {
  if (!confirm('Are you sure you want to delete this member? They will be moved to trash.')) return;
  router.delete(route('admin.user.membership.destroy', props.user.slug), {
    preserveScroll: true,
    onSuccess: () => {
      router.visit(route('admin.user.membership.list'));
    },
  });
}

onMounted(() => {
  setTimeout(() => {
    props.user.memberships?.forEach((m) => {
      ['full', 'minimal'].forEach((mode) => {
        renderMembershipCard(m.id, mode);
      });
    });
  }, 300);
});
</script>

<style lang="scss" scoped>
.card-flip-outer {
  cursor: pointer;
}

.card-flip-container-admin {
  width: 100%;
  perspective: 1200px;
  contain: layout paint;
}

.card-flip-inner-admin {
  position: relative;
  width: 100%;
  height: 100%;
  transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  transform-style: preserve-3d;
}

.card-flip-inner-admin.flipped {
  transform: rotateY(180deg);
}

.card-face-admin {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  -webkit-transform: translate3d(0, 0, 0);
  transform: translate3d(0, 0, 0);
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card-face-front {
  z-index: 2;
}

.card-face-back {
  -webkit-transform: rotateY(180deg) translate3d(0, 0, 0);
  transform: rotateY(180deg) translate3d(0, 0, 0);
}
</style>
