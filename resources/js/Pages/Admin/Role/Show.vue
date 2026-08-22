<template>
  <RoleLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Role"
        :breadcrumbs="[
          { label: 'Roles', link: route('admin.roles.index'), active: false },
          { label: role.label || role.name, link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Role Information">
          <div class="space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <ShowField label="Name" :value="role.label || role.name" />
              <ShowField label="Key" :value="role.name" value-class="font-mono text-xs" />
              <ShowField label="Guard" :value="role.guard_name" />
              <ShowField label="Users" :value="role.users_count" />
            </div>
            <ShowField v-if="role.description" label="Description">
              <p class="text-sm mt-0.5">{{ role.description }}</p>
            </ShowField>
            <div v-if="role.is_protected" class="panel-note text-sm">
              This role is protected: its permissions are fixed and it cannot be edited or deleted.
            </div>
          </div>
        </ShowCard>

        <ShowCard :title="`Permissions (${role.permissions.length})`">
          <p v-if="!role.permissions.length" class="text-sm text-muted-foreground">
            No permissions granted. This role only grants entry to the admin area.
          </p>
          <!-- Grouped by verb so a read-only role reads as one block of `view`
               entries rather than a flat alphabetical wall. -->
          <div v-else class="space-y-4">
            <div v-for="group in permissionGroups" :key="group.label">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">
                {{ group.label }} ({{ group.permissions.length }})
              </h3>
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="permission in group.permissions"
                  :key="permission"
                  class="inline-flex items-center rounded-md border border-border bg-muted/40 px-2 py-1 text-xs font-medium"
                >
                  {{ permission }}
                </span>
              </div>
            </div>
          </div>
        </ShowCard>

        <ShowActions
          :list-href="route('admin.roles.index')"
          :edit-href="canEdit ? route('admin.roles.edit', role.id) : null"
          edit-label="Edit Role"
        />
      </div>
    </div>
  </RoleLayout>
</template>

<script setup>
import { computed } from 'vue';
import RoleLayout from './RoleLayout.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions } from '@/Pages/Admin/_components/Show';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  role: { type: Object, required: true },
});

const { canManage } = usePermissions();

const canEdit = computed(() => !props.role.is_protected && canManage('manage roles'));

const permissionGroups = computed(() => {
  const buckets = [
    { label: 'Full access', match: (p) => p.startsWith('manage ') && !p.startsWith('manage own ') },
    { label: 'Own records only', match: (p) => p.startsWith('manage own ') },
    { label: 'Read-only', match: (p) => p.startsWith('view ') },
    { label: 'Create', match: (p) => p.startsWith('create ') },
  ];

  const groups = buckets
    .map(({ label, match }) => ({ label, permissions: props.role.permissions.filter(match) }))
    .filter((group) => group.permissions.length);

  // Anything a bucket did not claim still has to appear somewhere.
  const claimed = new Set(groups.flatMap((group) => group.permissions));
  const rest = props.role.permissions.filter((p) => !claimed.has(p));
  if (rest.length) groups.push({ label: 'Other', permissions: rest });

  return groups;
});
</script>
