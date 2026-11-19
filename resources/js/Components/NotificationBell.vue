<!--
  The bell in the admin header. Polls /admin/notifications; a new line rings once
  (a short two-note chime made with Web Audio — no sound file), the badge counts
  what this admin has not opened, and a line opens the thing it is about.
  Browsers keep a page silent until the admin has clicked something on it, so
  the chime is armed by the first click / key press anywhere.
-->
<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const POLL_MS = 20000;
const LAST_KEY = 'admin-bell-last-id';

const page = usePage();
const isAr = computed(() => (page.props.locale || 'ar') === 'ar');
const roles = computed(() => page.props.auth?.user?.roles || []);
const perms = computed(() => page.props.auth?.user?.permissions || []);
const allowed = computed(() => roles.value.includes('super_admin') || ['manage orders', 'manage own orders', 'view orders'].some((p) => perms.value.includes(p)));

const open = ref(false);
const items = ref([]);
const unread = ref(0);
const root = ref(null);
let timer = null;
let ctx = null;
let armed = false;

const L = computed(() => isAr.value
    ? { title: 'الإشعارات', empty: 'لا توجد إشعارات', all: 'تحديد الكل كمقروء' }
    : { title: 'Notifications', empty: 'No notifications', all: 'Mark all as read' });

const readStored = () => { try { const v = localStorage.getItem(LAST_KEY); return v === null ? null : Number(v); } catch { return null; } };
const writeStored = (v) => { try { localStorage.setItem(LAST_KEY, String(v)); } catch { /* private window */ } };

const arm = () => {
    armed = true;
    try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); ctx.resume?.(); } catch { /* no audio */ }
};

const chime = () => {
    if (!armed || !ctx) return;
    try {
        [[880, 0], [1175, 0.18]].forEach(([freq, at]) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = freq;
            g.gain.setValueAtTime(0.0001, ctx.currentTime + at);
            g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + at + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.4);
            o.connect(g); g.connect(ctx.destination);
            o.start(ctx.currentTime + at); o.stop(ctx.currentTime + at + 0.45);
        });
    } catch { /* ignore */ }
};

const poll = async () => {
    if (!allowed.value) return;
    try {
        const { data } = await window.axios.get(route('admin.notifications.index'));
        items.value = data.items;
        unread.value = data.unread;
        const last = readStored();
        // Ring when something newer than the last one this browser has seen is still unread.
        if (last !== null && data.latest_id > last && data.unread > 0) chime();
        if (last === null || data.latest_id > last) writeStored(data.latest_id);
        document.title = document.title.replace(/^\(\d+\)\s*/, '');
        if (data.unread > 0) document.title = `(${data.unread}) ${document.title}`;
    } catch { /* the bell is a convenience: stay quiet on a bad poll */ }
};

const markRead = (ids) => window.axios.post(route('admin.notifications.read'), ids === 'all' ? { all: true } : { ids }).catch(() => {});

const openItem = async (n) => {
    open.value = false;
    if (!n.read) {
        n.read = true;
        unread.value = Math.max(0, unread.value - 1);
        markRead([n.id]);
    }
    if (n.url) router.visit(n.url);
};

const markAll = () => {
    items.value.forEach((n) => { n.read = true; });
    unread.value = 0;
    markRead('all');
};

const ago = (iso) => {
    const m = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (m < 1) return isAr.value ? 'الآن' : 'now';
    if (m < 60) return isAr.value ? `منذ ${m} د` : `${m}m ago`;
    const h = Math.round(m / 60);
    if (h < 24) return isAr.value ? `منذ ${h} س` : `${h}h ago`;
    return new Date(iso).toLocaleDateString(isAr.value ? 'ar-EG' : 'en-GB');
};

const outside = (e) => { if (open.value && root.value && !root.value.contains(e.target)) open.value = false; };

onMounted(() => {
    window.addEventListener('pointerdown', arm, { once: true });
    window.addEventListener('keydown', arm, { once: true });
    document.addEventListener('pointerdown', outside);
    poll();
    timer = setInterval(poll, POLL_MS);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('pointerdown', outside);
});
</script>

<template>
    <div v-if="allowed" ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-11 w-11 items-center justify-center rounded-lg transition-colors hover:bg-accent"
            :title="L.title"
            :aria-label="L.title"
            @click="open = !open; if (open) poll()"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="unread ? 'text-golden-yellow' : 'text-muted-foreground'">
                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
            </svg>
            <span v-if="unread" class="chip chip-negative absolute -top-0.5 -end-0.5 min-w-[1.25rem] px-1 text-center text-[10px] leading-5">{{ unread > 99 ? '99+' : unread }}</span>
        </button>

        <div v-if="open" class="absolute end-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
            <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                <span class="text-sm font-semibold">{{ L.title }}</span>
                <button v-if="unread" type="button" class="text-xs underline" @click="markAll">{{ L.all }}</button>
            </div>
            <ul class="max-h-[26rem] overflow-y-auto divide-y divide-border">
                <li v-if="!items.length" class="px-3 py-6 text-center text-sm text-muted-foreground">{{ L.empty }}</li>
                <li v-for="n in items" :key="n.id">
                    <button type="button" class="flex w-full items-start gap-2 px-3 py-2.5 text-start transition-colors hover:bg-accent" :class="n.read ? 'opacity-70' : ''" @click="openItem(n)">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read ? 'bg-transparent' : 'bg-golden-yellow'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="chip chip-accent">{{ n.type === 'order' ? (isAr ? 'طلب' : 'Order') : n.type }}</span>
                                <span class="truncate text-sm font-medium">{{ n.title }}</span>
                            </span>
                            <span v-if="n.body" class="mt-0.5 block truncate text-xs text-muted-foreground">{{ n.body }}</span>
                            <span class="mt-0.5 block text-[11px] text-muted-foreground">{{ ago(n.at) }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
