import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// "Save & stay" for every dashboard form.
//
// The Save & stay button (components/ui/SaveStayButton.vue) raises `wantsStay`
// just before its form submits. The visit that carries the save is tagged with
// an `X-Save-Stay` header; the backend (RedirectBackWhenStaying) then sends the
// redirect back to the edit page instead of the list. Stores that used to
// `router.visit(list)` after a save call `afterSave(list)` instead, which
// reloads the current page (fresh data, form re-initialised) when the save was a
// "stay" one and goes to the list otherwise.
export const wantsStay = ref(false);
export const saving = ref(false);
let lastSaveStayed = false;
let installed = false;

export function installSaveAndStay() {
  if (installed || typeof document === 'undefined') return;
  installed = true;

  // Any ordinary submit button clears the flag first (capture phase runs before
  // the Save & stay button's own click handler, which sets it again).
  document.addEventListener('click', (e) => {
    const btn = e.target?.closest?.('button[type="submit"], input[type="submit"]');
    if (btn && !btn.hasAttribute('data-save-stay')) wantsStay.value = false;
  }, true);

  router.on('before', (event) => {
    const visit = event.detail.visit;
    if (String(visit.method).toLowerCase() === 'get') return;
    lastSaveStayed = wantsStay.value;
    wantsStay.value = false;
    saving.value = true;
    if (lastSaveStayed) visit.headers = { ...(visit.headers || {}), 'X-Save-Stay': '1' };
  });
  router.on('finish', () => { saving.value = false; });
}

/** After a successful save: reload this page for "stay", else go to the list. */
export function afterSave(listUrl) {
  if (lastSaveStayed) {
    lastSaveStayed = false;
    router.visit(window.location.href, { preserveScroll: true, preserveState: false, replace: true });
    return;
  }
  router.visit(listUrl);
}
