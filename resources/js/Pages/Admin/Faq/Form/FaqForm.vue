<template>
  <div class="space-y-3">
    <!-- Question Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          {{ t.faq?.question || 'Question' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormTranslatableInput
          v-model="formQuestion"
          :label="t.faq?.question || 'Question'"
          :error="faqStore.validationErrors?.['question.ar'] || faqStore.validationErrors?.['question.en']"
          :placeholder="t.faq?.question_placeholder || 'Enter the question'"
          :locales="['ar', 'en']"
          required
        />
      </div>
    </div>

    <!-- Answer Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          {{ t.faq?.answer || 'Answer' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <label class="block text-sm font-medium text-white mb-2">
          {{ t.faq?.answer || 'Answer' }}
          <span class="text-destructive">*</span>
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">
              {{ t.common?.arabic || 'Arabic' }}
            </label>
            <textarea
              v-model="formAnswerAr"
              dir="rtl"
              rows="8"
              :placeholder="t.faq?.answer_placeholder_ar || 'Enter the answer in Arabic'"
              :class="[
                'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 leading-relaxed',
                faqStore.validationErrors?.['answer.ar'] ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''
              ]"
            ></textarea>
            <p v-if="faqStore.validationErrors?.['answer.ar']" class="mt-1 text-sm text-destructive">
              {{ faqStore.validationErrors['answer.ar'] }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">
              {{ t.common?.english || 'English' }}
            </label>
            <textarea
              v-model="formAnswerEn"
              dir="ltr"
              rows="8"
              :placeholder="t.faq?.answer_placeholder_en || 'Enter the answer in English'"
              :class="[
                'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 leading-relaxed',
                faqStore.validationErrors?.['answer.en'] ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''
              ]"
            ></textarea>
            <p v-if="faqStore.validationErrors?.['answer.en']" class="mt-1 text-sm text-destructive">
              {{ faqStore.validationErrors['answer.en'] }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Settings Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          {{ t.common?.settings || 'Settings' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formSortOrder"
              :label="t.faq?.sort_order || 'Sort Order'"
              :error="faqStore.validationErrors?.sort_order"
              :placeholder="t.faq?.sort_order_placeholder || 'Enter sort order'"
              type="number"
              min="0"
            />
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.common?.status || 'Status' }}
            </label>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formIsActive"
                  class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2 rounded"
                />
                <span class="text-sm text-foreground">{{ t.common?.active || 'Active' }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormInput } from "@/Components/form";
import { useFaqStore } from "../Stores/FaqStore";
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

defineProps({
  faq: {
    type: Object,
    default: () => null,
  },
});

const faqStore = useFaqStore();
const { form } = storeToRefs(faqStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const formQuestion = computed({
  get: () => {
    const value = form.value.question;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return { ar: '', en: '' };
    return value;
  },
  set: (value) => {
    form.value.question = value;
  },
});

const formAnswerAr = computed({
  get: () => {
    const value = form.value.answer;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return '';
    return value.ar || '';
  },
  set: (value) => {
    const next = form.value.answer && typeof form.value.answer === 'object' && !Array.isArray(form.value.answer)
      ? { ...form.value.answer }
      : { ar: '', en: '' };
    next.ar = value;
    form.value.answer = next;
  },
});

const formAnswerEn = computed({
  get: () => {
    const value = form.value.answer;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return '';
    return value.en || '';
  },
  set: (value) => {
    const next = form.value.answer && typeof form.value.answer === 'object' && !Array.isArray(form.value.answer)
      ? { ...form.value.answer }
      : { ar: '', en: '' };
    next.en = value;
    form.value.answer = next;
  },
});

const formSortOrder = computed({
  get: () => form.value.sort_order ?? 0,
  set: (value) => {
    form.value.sort_order = value === '' || value === null ? 0 : parseInt(value);
  },
});

const formIsActive = computed({
  get: () => form.value.is_active ?? true,
  set: (value) => {
    form.value.is_active = value;
  },
});
</script>
