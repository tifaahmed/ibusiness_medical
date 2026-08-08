<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        {{ t.member?.profile_picture || 'Profile Picture' }}
      </div>
    </div>
    <div class="px-6">
      <div class="space-y-4">
        <ImageFileInput
          :initialPreview="memberStore.form.avatar_url"
          @file-selected="handleAvatarSelected"
          :error="memberStore.validationErrors?.avatar"
        />
        <p
          v-if="memberStore.validationErrors?.avatar"
          class="text-sm text-destructive"
        >
          {{ memberStore.validationErrors.avatar }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ImageFileInput } from "@/Components/form";
import { useMemberStore } from "../../Stores/MemberStore";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const memberStore = useMemberStore();
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const handleAvatarSelected = (file) => {
  if (file) {
    memberStore.form.avatar = file;
  } else {
    memberStore.form.avatar = null;
  }
};
</script>

<style lang="scss" scoped></style>
