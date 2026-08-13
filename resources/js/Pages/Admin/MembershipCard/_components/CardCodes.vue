<template>
  <div class="rounded-md border border-border bg-muted/30 px-3 py-2 space-y-1 text-xs">
    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
      <span class="text-muted-foreground shrink-0">Barcode:</span>
      <span class="font-mono break-all" dir="ltr">{{ barcode || '—' }}</span>
    </div>
    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
      <span class="text-muted-foreground shrink-0">QR code:</span>
      <a
        v-if="qrcode"
        :href="qrcode"
        target="_blank"
        rel="noopener"
        class="font-mono break-all text-blue-600 hover:underline"
        dir="ltr"
      >{{ qrcode }}</a>
      <span v-else class="font-mono">—</span>
      <button
        v-if="qrcode"
        type="button"
        class="shrink-0 rounded border border-border bg-background px-1.5 py-0.5 text-[11px] hover:bg-muted cursor-pointer"
        @click="copyQr"
      >
        {{ copied ? 'Copied' : 'Copy' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { cardBarcodeValue, cardQrValue } from './cardRenderer.js';

const props = defineProps({
  /**
   * The membership as the card prints it — display prefix already applied, so
   * the number shown here is the one the bars encode.
   */
  membership: { type: Object, required: true },
});

// Read from the renderer itself, so what is shown can never drift from what
// is drawn onto the card.
const barcode = computed(() => cardBarcodeValue(props.membership));
const qrcode = computed(() => cardQrValue(props.membership));

const copied = ref(false);
async function copyQr() {
  try {
    await navigator.clipboard.writeText(qrcode.value);
  } catch {
    // Clipboard API is unavailable (older browser / insecure context) — fall
    // back to a throwaway textarea so the button still works.
    const textarea = document.createElement('textarea');
    textarea.value = qrcode.value;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
  }

  copied.value = true;
  setTimeout(() => { copied.value = false; }, 2000);
}
</script>
