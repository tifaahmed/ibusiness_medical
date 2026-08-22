import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permission checks for the admin UI, mirroring the route middleware.
 *
 * Hiding a button is a courtesy, not a control — every route still enforces its
 * own permission server-side. This exists so a read-only account is not shown
 * actions that would only 403.
 */
export function usePermissions() {
  const page = usePage();

  const roles = computed(() => page.props.auth?.user?.roles || []);
  const permissions = computed(() => page.props.auth?.user?.permissions || []);
  const isSuperAdmin = computed(() => roles.value.includes('super_admin'));

  const can = (permission) => isSuperAdmin.value || permissions.value.includes(permission);
  const canAny = (...list) => list.flat().some((permission) => can(permission));

  /**
   * True when the account may change this resource — the test that gates every
   * create/edit/delete/export control. Pass the manage permissions for the
   * resource; `view X` is deliberately not among them.
   */
  const canManage = (...managePermissions) => canAny(managePermissions);

  return { roles, permissions, isSuperAdmin, can, canAny, canManage };
}
