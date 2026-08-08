<template>
  <div class="space-y-3">
    <!-- Identity Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
          Role Identity
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-white mb-1">
            Role Name <span class="text-destructive">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
            :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/20': form.errors.name }"
            placeholder="e.g. content_manager"
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
          <p class="mt-1 text-xs text-muted-foreground">
            Lowercase letters, numbers, spaces, hyphens, and underscores. Reserved names: super_admin, admin.
          </p>
        </div>
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
          Permissions
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-3">
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="selectAll"
            class="text-xs px-2 py-1 rounded border border-border hover:bg-primary/10 hover:text-primary transition-colors"
          >
            Select all
          </button>
          <button
            type="button"
            @click="clearAll"
            class="text-xs px-2 py-1 rounded border border-border hover:bg-destructive/10 hover:text-destructive transition-colors"
          >
            Clear
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label
            v-for="perm in allPermissions"
            :key="perm"
            class="flex items-center gap-2 px-3 py-2 rounded-md border border-border bg-background/40 hover:border-amber-400/50 transition-colors cursor-pointer"
            :class="{ 'border-amber-400 bg-amber-500/10': form.permissions.includes(perm) }"
          >
            <input
              type="checkbox"
              :value="perm"
              v-model="form.permissions"
              class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500"
            />
            <span class="text-sm">{{ perm }}</span>
          </label>
        </div>
        <p v-if="form.errors.permissions" class="text-sm text-destructive">{{ form.errors.permissions }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
  allPermissions: { type: Array, required: true },
});

function selectAll() {
  props.form.permissions = [...props.allPermissions];
}
function clearAll() {
  props.form.permissions = [];
}
</script>
