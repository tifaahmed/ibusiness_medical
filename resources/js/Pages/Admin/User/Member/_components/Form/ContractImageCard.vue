<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        {{ t.member?.contract_image || 'Contract Image' }}
      </div>
      <p class="text-xs text-muted-foreground mt-1">
        {{ t.member?.contract_image_help || 'Upload a single image of the signed contract.' }}
      </p>
    </div>
    <div class="px-6">
      <ImageFileInput
        :initialPreview="memberStore.form.contract_image_url"
        @file-selected="handleFileSelected"
        :error="memberStore.validationErrors?.contract_image"
      />
      <p
        v-if="memberStore.validationErrors?.contract_image"
        class="text-sm text-destructive mt-2"
      >
        {{ memberStore.validationErrors.contract_image }}
      </p>
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

const handleFileSelected = (file) => {
  if (file) {
    memberStore.form.contract_image = file;
    memberStore.form.contract_image_remove = false;
  } else {
    memberStore.form.contract_image = null;
    // Flag for the server to clear the existing collection on update.
    if (memberStore.form.contract_image_url) {
      memberStore.form.contract_image_remove = true;
      memberStore.form.contract_image_url = null;
    }
  }
};
</script>
