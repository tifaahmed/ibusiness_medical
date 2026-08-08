<template>
  <div class="space-y-3">
    <!-- Account Details Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          {{ t.account_details || 'Account Details' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.name || 'Name' }} <span class="text-destructive">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
              :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/20': form.errors.name }"
              :placeholder="t.name_placeholder || 'Full name'"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.email || 'Email' }} <span class="text-destructive">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
              :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/20': form.errors.email }"
              :placeholder="t.email_placeholder || 'user@example.com'"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
          </div>
        </div>

        <div>
          <label class="flex items-start gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="form.email_verified"
              class="h-4 w-4 mt-0.5 rounded border-gray-300 text-primary focus:ring-primary"
            />
            <span class="text-sm">
              <span class="font-medium text-white">{{ t.email_verified || 'Email verified' }}</span>
              <span class="block text-[11px] text-muted-foreground mt-0.5">
                {{ isEdit
                  ? (t.email_verified_hint_edit || 'Check to mark the address as verified; uncheck to revoke it.')
                  : (t.email_verified_hint_create || 'Check to mark the address as verified at creation time.') }}
              </span>
            </span>
          </label>
          <p v-if="form.errors.email_verified" class="mt-1 text-sm text-destructive">{{ form.errors.email_verified }}</p>
        </div>

        <FormSelect
          v-model="form.partner_id"
          :label="t.partner || 'Partner'"
          :description="t.partner_description || '(required for &quot;manage partner memberships&quot; — scopes membership access)'"
          :placeholder="t.partner_none || '— None —'"
          :options="partnerOptions"
          :error="form.errors.partner_id"
        />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.password || 'Password' }}
              <span v-if="!isEdit" class="text-destructive">*</span>
              <span v-else class="text-xs text-muted-foreground font-normal">{{ t.password_keep_current || '(leave blank to keep current)' }}</span>
            </label>
            <input
              v-model="form.password"
              type="password"
              :required="!isEdit"
              class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
              :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/20': form.errors.password }"
              :placeholder="t.password_placeholder || 'Minimum 8 characters'"
              autocomplete="new-password"
            />
            <p v-if="form.errors.password" class="mt-1 text-sm text-destructive">{{ form.errors.password }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.confirm_password || 'Confirm Password' }}
              <span v-if="!isEdit" class="text-destructive">*</span>
            </label>
            <input
              v-model="form.password_confirmation"
              type="password"
              :required="!isEdit"
              class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
              :placeholder="t.confirm_password_placeholder || 'Repeat password'"
              autocomplete="new-password"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Roles Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
          {{ t.roles || 'Roles' }} <span class="text-destructive">*</span>
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-2">
        <p class="text-xs text-muted-foreground">
          {{ t.roles_help || 'Choose one or more roles. Each role grants its own preset of permissions — those will appear locked below.' }}
        </p>
        <div v-if="normalizedRoles.length === 0" class="text-sm text-muted-foreground italic py-2">
          {{ t.no_roles_found_prefix || 'No assignable roles found. Create one in' }}
          <a :href="route('admin.roles.create')" class="underline hover:text-primary">{{ t.roles_permissions_link || 'Roles & Permissions' }}</a>.
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label
            v-for="role in normalizedRoles"
            :key="role.name"
            class="flex items-start gap-2 px-3 py-2 rounded-md border border-border bg-background/40 hover:border-primary/50 transition-colors cursor-pointer"
            :class="{ 'border-primary bg-primary/10': form.roles.includes(role.name) }"
          >
            <input
              type="checkbox"
              :value="role.name"
              v-model="form.roles"
              class="h-4 w-4 mt-0.5 rounded border-gray-300 text-primary focus:ring-primary"
            />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-medium">{{ role.name }}</span>
                <span
                  v-if="role.name === 'admin'"
                  class="text-[10px] uppercase tracking-wide font-semibold text-blue-300 border border-blue-500/40 bg-blue-500/10 rounded px-1.5 py-0.5"
                >
                  {{ t.login_only || 'Login only' }}
                </span>
              </div>
              <div v-if="role.description" class="text-[11px] text-muted-foreground mt-0.5 leading-snug">
                {{ role.description }}
              </div>
              <div v-if="role.permissions.length" class="text-[11px] text-muted-foreground mt-0.5 leading-snug">
                {{ t.grants || 'grants' }}: {{ role.permissions.join(", ") }}
              </div>
              <div v-else-if="!role.description" class="text-[11px] text-muted-foreground mt-0.5 italic">{{ t.no_permissions || 'no permissions' }}</div>
            </div>
          </label>
        </div>
        <p v-if="form.errors.roles" class="text-sm text-destructive">{{ form.errors.roles }}</p>
      </div>
    </div>

    <!-- Permissions Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          {{ t.extra_permissions || 'Extra Permissions' }}
          <span class="text-xs text-muted-foreground font-normal ml-2">{{ t.extra_permissions_note || '(in addition to role permissions)' }}</span>
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label
            v-for="perm in allPermissions"
            :key="perm"
            :class="[
              'flex items-center justify-between gap-2 px-3 py-2 rounded-md border transition-colors',
              grantedByRoles.has(perm)
                ? 'border-emerald-500/40 bg-emerald-500/10 cursor-not-allowed'
                : form.permissions.includes(perm)
                  ? 'border-amber-400 bg-amber-500/10 cursor-pointer hover:border-amber-400/50'
                  : 'border-border bg-background/40 cursor-pointer hover:border-amber-400/50'
            ]"
          >
            <span class="flex items-center gap-2 min-w-0">
              <input
                type="checkbox"
                :value="perm"
                v-model="form.permissions"
                :disabled="grantedByRoles.has(perm)"
                class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <span class="text-sm" :class="{ 'text-muted-foreground': grantedByRoles.has(perm) }">{{ perm }}</span>
            </span>
            <span
              v-if="grantedByRoles.has(perm)"
              class="text-[10px] uppercase tracking-wide font-semibold text-emerald-300 whitespace-nowrap flex-shrink-0"
              :title="`${t.granted_by_roles || 'Granted by role(s)'}: ${grantedByWhich(perm).join(', ')}`"
            >
              {{ t.via_role || 'via role' }}
            </span>
          </label>
        </div>
        <p v-if="form.errors.permissions" class="text-sm text-destructive">{{ form.errors.permissions }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { FormSelect } from "@/Components/form";

const props = defineProps({
  form: { type: Object, required: true },
  assignableRoles: { type: Array, required: true },
  allPermissions: { type: Array, required: true },
  partnerOptions: { type: Array, default: () => [] },
  isEdit: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.admin_user || {});

// Normalize: support both legacy string entries and new {name, permissions, description} objects
const normalizedRoles = computed(() =>
  props.assignableRoles.map((r) =>
    typeof r === "string"
      ? { name: r, permissions: [], description: null }
      : { name: r.name, permissions: r.permissions || [], description: r.description || null }
  )
);

// Set of permissions granted by any currently-selected role
const grantedByRoles = computed(() => {
  const set = new Set();
  for (const roleName of props.form.roles) {
    const role = normalizedRoles.value.find((r) => r.name === roleName);
    if (role) role.permissions.forEach((p) => set.add(p));
  }
  return set;
});

// Auto-strip permissions that became granted by a role, so the user can't double-assign.
// `immediate` cleans up duplicates already present when the form loads (edit case).
watch(
  grantedByRoles,
  (granted) => {
    if (!Array.isArray(props.form.permissions)) return;
    const filtered = props.form.permissions.filter((p) => !granted.has(p));
    if (filtered.length !== props.form.permissions.length) {
      props.form.permissions = filtered;
    }
  },
  { immediate: true }
);

function grantedByWhich(perm) {
  const out = [];
  for (const roleName of props.form.roles) {
    const role = normalizedRoles.value.find((r) => r.name === roleName);
    if (role && role.permissions.includes(perm)) out.push(roleName);
  }
  return out;
}
</script>
