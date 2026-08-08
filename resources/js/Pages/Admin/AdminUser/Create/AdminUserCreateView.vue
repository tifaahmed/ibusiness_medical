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
              <span class="hidden sm:inline">Back</span>
            </Link>
            <div class="title-golden flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span class="text-sm sm:text-base">Add Admin User</span>
            </div>
          </div>
        </div>

        <form class="space-y-3" @submit.prevent="submit">
          <AdminUserForm
            :form="form"
            :assignable-roles="assignableRoles"
            :all-permissions="allPermissions"
            :partner-options="partnerOptions"
          />

          <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
            <div class="flex flex-col sm:flex-row p-4">
              <div class="flex-1"></div>
              <div class="flex gap-3 justify-end">
                <Link
                  :href="route('admin.admin-users.index')"
                  type="button"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2">
                    <path d="M5 12h14"></path>
                    <path d="M12 5v14"></path>
                  </svg>
                  Create Admin
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
import { Link, useForm } from "@inertiajs/vue3";
import AdminUserLayout from "../AdminUserLayout.vue";
import AdminUserForm from "../_components/Form/AdminUserForm.vue";

defineProps({
  assignableRoles: { type: Array, required: true },
  allPermissions: { type: Array, required: true },
  partnerOptions: { type: Array, default: () => [] },
});

const form = useForm({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  roles: [],
  permissions: [],
  partner_id: null,
  email_verified: true,
});

function submit() {
  form.post(route("admin.admin-users.store"));
}
</script>
