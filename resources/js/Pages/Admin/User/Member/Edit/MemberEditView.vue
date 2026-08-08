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
      <div class="flex gap-2 mb-4 border-b border-border" role="tablist">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'profile'"
          @click="activeTab = 'profile'"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-t-md border-b-2 -mb-px transition-colors',
            activeTab === 'profile'
              ? 'border-primary text-primary font-semibold'
              : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted',
          ]"
        >
          {{ t.member?.tab_profile || 'Profile' }}
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'password'"
          @click="activeTab = 'password'"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-t-md border-b-2 -mb-px transition-colors',
            activeTab === 'password'
              ? 'border-primary text-primary font-semibold'
              : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted',
          ]"
        >
          {{ t.member?.tab_password || 'Password' }}
        </button>
      </div>

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
    </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MemberLayout from "../MemberLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useMemberStore } from "../Stores/MemberStore";
import { MemberForm, ProfilePictureCard, ContractImageCard, GalleryImagesCard, FamilyMemberCard, ChangePasswordCard } from "../_components/Form";
import { onMounted, computed, ref } from "vue";

const activeTab = ref('profile');

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const memberStore = useMemberStore();
const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
});

onMounted(() => {
  memberStore.setMember(props.member);
});

const handleSubmit = () => {
  memberStore.updateMember();
};
</script>

<style lang="scss" scoped></style>
