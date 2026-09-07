<!--
  The storefront login's code policy, at either of its two sizes.

  Passed a `member`, this edits that one person's code and nothing else. Passed
  none, it edits the site-wide setting every member without their own code
  follows. The two are the same shape — a switch and a code — so they are one
  component rather than two that would drift apart.

  Nothing here is a security control: both routes enforce their own permission
  server-side, and the buttons that open this are hidden from accounts that
  would only be refused.
-->
<template>
  <teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-3 sm:items-center"
      @click.self="close"
    >
      <div class="w-full max-w-lg overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-2xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="space-y-1">
            <p class="text-base font-semibold">
              {{ member ? `Login code for ${member.name}` : 'Storefront login codes' }}
            </p>
            <p class="text-xs text-muted-foreground">
              <template v-if="member">
                A code that belongs to this member alone. While one is set they are
                sent nothing by SMS and this is what signs them in — whatever the
                rest of the site is doing.
              </template>
              <template v-else>
                How every member without a code of their own signs in on the
                storefront.
              </template>
            </p>
          </div>
          <button
            type="button"
            @click="close"
            class="ml-auto shrink-0 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium hover:bg-muted cursor-pointer"
          >Close</button>
        </div>

        <div class="space-y-4 p-4">
          <!-- Site-wide only: the switch between a real code and a fixed one. -->
          <div v-if="!member" class="space-y-2">
            <label class="flex cursor-pointer items-start gap-2">
              <input type="radio" class="mt-0.5" :value="true" v-model="smsEnabled" />
              <span>
                <span class="block text-sm font-medium">Send a real code by SMS</span>
                <span class="block text-[11px] text-muted-foreground">
                  A fresh random code each time, texted to the member.
                </span>
              </span>
            </label>
            <label class="flex cursor-pointer items-start gap-2">
              <input type="radio" class="mt-0.5" :value="false" v-model="smsEnabled" />
              <span>
                <span class="block text-sm font-medium">Stop the codes — use a fixed one</span>
                <span class="block text-[11px] text-muted-foreground">
                  Nothing is sent. Everyone signs in with the code below.
                </span>
              </span>
            </label>

            <!-- The switch can say "SMS on" while nothing could ever send one.
                 Better to say so here than to leave an admin wondering why no
                 message arrives. -->
            <p
              v-if="smsEnabled && policy && !policy.sms_configured"
              class="rounded-md border border-amber-500/50 bg-amber-500/10 p-2 text-[11px] text-amber-600 dark:text-amber-400"
            >
              No SMS credentials are configured, so nothing can actually be sent —
              the fixed code below is what works until they are added.
            </p>
          </div>

          <div v-if="member || !smsEnabled" class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ member ? 'This member’s code' : 'Fixed code' }}
            </label>
            <input
              v-model="code"
              type="text"
              inputmode="numeric"
              autocomplete="off"
              placeholder="4 to 8 digits"
              class="h-9 w-40 rounded-md border border-input bg-background px-3 font-mono text-sm tracking-widest focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <p class="text-[11px] text-muted-foreground">
              <template v-if="member">
                Leave it empty to clear the code and put this member back on the
                site setting.
              </template>
              <template v-else>
                Emptying this closes the storefront login entirely — with no SMS and
                no fixed code, nobody can sign in.
              </template>
            </p>
            <p v-if="error" class="text-[11px] text-destructive">{{ error }}</p>
          </div>

          <p v-else class="text-[11px] text-muted-foreground">
            The fixed code is only used while SMS is off, so it is left alone here.
          </p>
        </div>

        <div class="flex items-center gap-2 border-t border-border p-4">
          <button type="button" @click="close" class="h-9 rounded-md border border-border bg-background px-3 text-sm font-medium hover:bg-muted cursor-pointer">
            Cancel
          </button>
          <button
            type="button"
            @click="save"
            :disabled="saving"
            class="ml-auto inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50 btn-golden cursor-pointer"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  open: { type: Boolean, default: false },
  // Null for the site-wide policy; a member row to edit that one member.
  member: { type: Object, default: null },
  // The site policy as the server currently has it. Unused in member mode
  // except to explain a missing SMS gateway.
  policy: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const smsEnabled = ref(true);
const code = ref('');
const saving = ref(false);
const error = ref('');

/* Re-seeded every time it opens, so a dialog closed without saving does not
   show yesterday's answer the next time it is opened. */
watch(() => props.open, (isOpen) => {
  if (!isOpen) return;

  error.value = '';
  saving.value = false;

  if (props.member) {
    code.value = props.member.otp_fixed_code || '';
  } else {
    smsEnabled.value = props.policy?.sms_enabled !== false;
    code.value = props.policy?.fixed_code || '';
  }
}, { immediate: true });

const close = () => emit('close');

const save = () => {
  const trimmed = code.value.trim();

  // Checked here as well as on the server purely so the answer is instant;
  // the route validates the same rule and is the one that decides.
  if (trimmed !== '' && !/^\d{4,8}$/.test(trimmed)) {
    error.value = 'The code must be 4 to 8 digits.';
    return;
  }

  saving.value = true;
  error.value = '';

  const url = props.member
    ? route('admin.user.membership.otp.member', props.member.id)
    : route('admin.user.membership.otp.site');

  const payload = props.member
    ? { fixed_code: trimmed === '' ? null : trimmed }
    : { sms_enabled: smsEnabled.value, fixed_code: trimmed === '' ? null : trimmed };

  router.put(url, payload, {
    preserveScroll: true,
    onSuccess: () => close(),
    onError: (errors) => { error.value = errors.fixed_code || 'That could not be saved.'; },
    onFinish: () => { saving.value = false; },
  });
};
</script>
