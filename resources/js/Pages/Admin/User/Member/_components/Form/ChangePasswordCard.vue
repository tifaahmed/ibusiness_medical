<template>
  <form @submit.prevent="handleSubmit" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        {{ t.member?.change_password || 'Change Password' }}
      </div>
    </div>
    <div class="px-6">
      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormInput
            v-model="form.password"
            :label="t.common?.new_password || 'New Password'"
            type="password"
            :error="form.errors.password"
            :placeholder="t.member?.password_placeholder || 'Enter new password'"
            required
          />
          <FormInput
            v-model="form.password_confirmation"
            :label="t.common?.confirm_password || 'Confirm Password'"
            type="password"
            :error="form.errors.password_confirmation"
            :placeholder="t.member?.password_confirmation_placeholder || 'Confirm new password'"
            required
          />
        </div>
        <p class="text-xs text-muted-foreground">
          {{ t.member?.password_change_help || 'This sets a new password for the member immediately — it is independent from the profile form.' }}
        </p>
        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px] btn-golden"
          >
            {{ t.member?.update_password || 'Update Password' }}
          </button>
        </div>
      </div>
    </div>
  </form>
</template>

<script setup>
import { FormInput } from "@/Components/form";
import { useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  userSlug: { type: String, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = useForm({
  password: '',
  password_confirmation: '',
});

const handleSubmit = () => {
  form.put(route('admin.user.membership.password.update', props.userSlug), {
    preserveScroll: true,
    onSuccess: () => {
      useNotification().success(t.value.member?.password_updated || 'Password updated successfully');
      form.reset();
    },
    onError: () => {
      useNotification().error(t.value.member?.password_update_failed || 'Failed to update password');
    },
  });
};
</script>

<style lang="scss" scoped></style>
