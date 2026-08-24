<template>
  <MemberLayout>
    <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
    <Breadcrumb
      :title="t.member?.edit || 'Edit Member'"
      :breadcrumbs="[
        { label: t.member?.title || 'Members', link: route('admin.user.membership.list'), active: false },
        { label: t.member?.edit || 'Edit Member', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto">
      <!-- Tabs -->
      <TabBar v-model="activeTab" :tabs="tabs" class="mb-4" />

      <form v-show="activeTab === 'profile'" @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
          <!-- Left Column: Main Form Fields -->
          <div class="lg:col-span-3 space-y-4">
            <MemberForm />
          </div>

          <!-- Right Column: Profile Picture + Contract Image -->
          <div class="lg:col-span-2 space-y-4">
            <ProfilePictureCard />
            <ContractImageCard />
          </div>
        </div>

        <!-- Gallery — full width below the two columns -->
        <GalleryImagesCard />

        <!-- Family Members - Full Width -->
        <FamilyMemberCard
          v-if="member?.membership?.slug"
          :family-members="member?.membership?.family_members || []"
          :user-slug="member?.slug"
          :membership-slug="member?.membership?.slug"
        />

        <!-- Sticky Form Actions -->
        <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
          <div class="flex flex-col sm:flex-row p-4 gap-3">
            <div class="flex-1"></div>
            <div class="flex gap-3 justify-end">
              <Link
                :href="route('admin.user.membership.list')"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              >
                {{ t.common?.cancel || 'Cancel' }}
              </Link>
              <button
                type="submit"
                :disabled="memberStore.form.processing"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                  <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                  <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                </svg>
                {{ t.member?.update || 'Update Member' }}
              </button>
            </div>
          </div>
        </div>
      </form>

      <!-- Password tab — its own form, independent of the profile form above -->
      <div v-show="activeTab === 'password'">
        <ChangePasswordCard :user-slug="member?.slug" />
      </div>

      <!-- Payments tab — read-only list; recording and editing happen in the
           member-payment module, which this links straight into. -->
      <div v-show="activeTab === 'payments'">
        <MemberPaymentsCard :membership="member?.membership" />
      </div>

      <!-- Addresses tab — multiple addresses per member, each with a type
           (home / work / other), managed independently of the profile form. -->
      <div v-show="activeTab === 'addresses'">
        <MemberAddressesCard
          v-if="member?.membership?.slug"
          :addresses="member?.membership?.addresses || []"
          :user-slug="member?.slug"
          :membership-slug="member?.membership?.slug"
        />
      </div>
    </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MemberLayout from "../MemberLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useMemberStore } from "../Stores/MemberStore";
import { MemberForm, ProfilePictureCard, ContractImageCard, GalleryImagesCard, FamilyMemberCard, ChangePasswordCard, MemberPaymentsCard, MemberAddressesCard } from "../_components/Form";
import TabBar from "@/Components/ui/TabBar.vue";
import { onMounted, computed, ref, watch } from "vue";

// Tabs live in the URL (?tab=...) so a switch is linkable and
// refresh/back keeps the tab the admin was working in.
const TAB_KEYS = ['profile', 'password', 'payments', 'addresses'];

const initialTab = new URLSearchParams(window.location.search).get('tab');
const activeTab = ref(TAB_KEYS.includes(initialTab) ? initialTab : 'profile');

watch(activeTab, (key) => {
  const url = new URL(window.location.href);
  // Default tab stays canonical: .../edit instead of .../edit?tab=profile
  if (key === 'profile') {
    url.searchParams.delete('tab');
  } else {
    url.searchParams.set('tab', key);
  }
  window.history.replaceState(window.history.state, '', url);
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const memberStore = useMemberStore();
const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
});

const paymentsCount = computed(() => props.member?.membership?.member_payments?.length || 0);
const addressesCount = computed(() => props.member?.membership?.addresses?.length || 0);

const tabs = computed(() => [
  { key: 'profile', label: t.value.member?.tab_profile || 'Profile' },
  { key: 'password', label: t.value.member?.tab_password || 'Password' },
  {
    key: 'payments',
    label: paymentsCount.value
      ? `${t.value.member?.payments || 'Payments'} (${paymentsCount.value})`
      : (t.value.member?.payments || 'Payments'),
  },
  {
    key: 'addresses',
    label: addressesCount.value
      ? `${t.value.member?.addresses || 'Addresses'} (${addressesCount.value})`
      : (t.value.member?.addresses || 'Addresses'),
  },
]);

onMounted(() => {
  memberStore.setMember(props.member);
});

const handleSubmit = () => {
  memberStore.updateMember();
};
</script>

<style lang="scss" scoped></style>
