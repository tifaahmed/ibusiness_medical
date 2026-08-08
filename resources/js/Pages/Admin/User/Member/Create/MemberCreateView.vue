<template>
  <MemberLayout>


    <div class="max-w-7xl mx-auto mt-4 px-4 pb-20 sm:pb-4">
      <form @submit.prevent="handleSubmit" class="space-y-4">
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
        <FamilyMemberCreateCard v-model="familyMembers" />

        <!-- Sticky Form Actions -->
        <div class="fixed sm:sticky bottom-0 left-0 right-0 sm:left-auto sm:right-auto z-10 bg-card border-t sm:border border-border rounded-t-lg sm:rounded-lg shadow-lg sm:shadow-sm mt-4 -mx-4 px-4 sm:mx-0 sm:px-0">
          <div class="flex flex-col sm:flex-row p-4 gap-3">
            <div class="flex-1"></div>
            <div class="flex gap-3 justify-end">
              <Link
                :href="route('admin.user.membership.list')"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background text-white shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
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
                {{ t.member?.create || 'Create Member' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </MemberLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MemberLayout from "../MemberLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useMemberStore } from "../Stores/MemberStore";
import { MemberForm, ProfilePictureCard, ContractImageCard, GalleryImagesCard, FamilyMemberCreateCard } from "../_components/Form";
import { onMounted, ref, computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const memberStore = useMemberStore();
const familyMembers = ref([]);

onMounted(() => {
  memberStore.initializeForm();
});

const handleSubmit = () => {
  console.log('=== handleSubmit called ===');
  console.log('Family members before processing:', familyMembers.value);
  
  // Prepare family members data with files
  const familyMembersData = familyMembers.value.map((member, index) => {
    const memberData = {
      name: member.name,
      relationship: member.relationship,
      date_of_birth: member.date_of_birth || null,
      phone: member.phone || null,
      email: member.email || null,
      is_active: member.is_active ?? true,
    };
    
    // Add photo file if exists
    if (member.photo_file) {
      memberData.photo_file = member.photo_file;
      console.log(`Family member ${index} has photo file:`, member.photo_file.name);
    }
    
    return memberData;
  });

  console.log('Family members data prepared:', familyMembersData);

  // Pass family members to submitForm
  memberStore.submitForm(familyMembersData);
};
</script>

<style lang="scss" scoped></style>
