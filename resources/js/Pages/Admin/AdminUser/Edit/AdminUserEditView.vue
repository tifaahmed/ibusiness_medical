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
            <div class="title-golden flex items-center gap-2 min-w-0">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
                <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
              </svg>
              <span class="text-sm sm:text-base truncate">
                {{ t.edit_title || 'Edit Admin' }}: <span class="text-muted-foreground font-normal">{{ admin.email }}</span>
              </span>
            </div>
          </div>
        </div>

        <form class="space-y-3" @submit.prevent="submit">
          <AdminUserForm
            :form="form"
            :assignable-roles="assignableRoles"
            :all-permissions="allPermissions"
            :partner-options="partnerOptions"
            :is-edit="true"
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
                  {{ t.cancel || 'Cancel' }}
                </Link>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                  </svg>
                  {{ t.save_changes || 'Save Changes' }}
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

const props = defineProps({
  admin: { type: Object, required: true },
  assignableRoles: { type: Array, required: true },
  allPermissions: { type: Array, required: true },
  partnerOptions: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.admin_user || {});

const form = useForm({
  name: props.admin.name,
  email: props.admin.email,
  password: "",
  password_confirmation: "",
  roles: [...(props.admin.roles || [])],
  permissions: [...(props.admin.direct_permissions || [])],
  partner_id: props.admin.partner_id ?? null,
  email_verified: Boolean(props.admin.email_verified),
});

function submit() {
  form.put(route("admin.admin-users.update", props.admin.id));
}
</script>
