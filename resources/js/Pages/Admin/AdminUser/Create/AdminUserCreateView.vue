<template>
  <AdminUserLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 rounded-xl border border-border py-3 shadow-sm">
          <div class="flex items-center gap-2 px-4 sm:px-6">
            <Link
              :href="route('admin.admin-users.index')"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-8 sm:h-9 px-2 sm:px-3"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-4 w-4">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.back || 'Back' }}</span>
            </Link>
            <div class="title-golden flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span class="text-sm sm:text-base">{{ t.create_title || 'Add Admin User' }}</span>
            </div>
          </div>
        </div>

        <!-- "Create and stay" lands back here, so the confirmation has to show
             on this page or the save looks like it did nothing. -->
        <div
          v-if="$page.props.flash?.success"
          class="rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 px-3 py-2 text-sm"
        >
          {{ $page.props.flash.success }}
        </div>

        <form class="space-y-3" @submit.prevent="submit('return')">
          <AdminUserForm
            :form="form"
            :assignable-roles="assignableRoles"
            :all-permissions="allPermissions"
            :partner-options="partnerOptions"
          />

          <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
            <div class="flex flex-col sm:flex-row p-4">
              <div class="flex-1"></div>
              <div class="flex flex-wrap gap-3 justify-end">
                <Link
                  :href="route('admin.admin-users.index')"
                  type="button"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                >
                  {{ t.cancel || 'Cancel' }}
                </Link>
                <button
                  type="button"
                  :disabled="form.processing"
                  :title="t.create_and_update_hint || 'Create the admin and keep editing it'"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-3 sm:order-2"
                  @click="submit('update')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                  </svg>
                  {{ t.create_and_update || 'Create and update' }}
                </button>
                <button
                  type="button"
                  :disabled="form.processing"
                  :title="t.create_and_stay_hint || 'Create the admin and open a blank form for the next one'"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-4 sm:order-3"
                  @click="submit('stay')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M19 8v6"></path>
                    <path d="M22 11h-6"></path>
                  </svg>
                  {{ t.create_and_stay || 'Create and stay' }}
                </button>
                <button
                  type="submit"
                  :disabled="form.processing"
                  :title="t.create_and_return_hint || 'Create the admin and go back to the list'"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-4 btn-golden"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M5 12h14"></path>
                    <path d="M12 5v14"></path>
                  </svg>
                  {{ t.create_and_return || 'Create and return' }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminUserLayout>
</template>

<script setup>
import { computed } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AdminUserLayout from "../AdminUserLayout.vue";
import AdminUserForm from "../_components/Form/AdminUserForm.vue";

defineProps({
  assignableRoles: { type: Array, required: true },
  allPermissions: { type: Array, required: true },
  partnerOptions: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.admin_user || {});

const form = useForm({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  roles: [],
  permissions: [],
  partner_id: null,
  email_verified: true,
  // Which button was pressed; the controller reads it to pick the redirect.
  after_save: "return",
});

function submit(afterSave = "return") {
  form.after_save = afterSave;
  form.post(route("admin.admin-users.store"));
}
</script>
