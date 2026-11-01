<template>
  <div class="w-full min-w-0 space-y-3" data-testid="store-filters">
    <!-- Search + the things you filter by most -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
      <label class="block sm:col-span-2 min-w-0">
        <span class="mb-1 block text-xs font-medium">{{ L('search', 'Search') }}</span>
        <input v-model="f.search" type="text" :placeholder="L('search_ph', 'Title (Arabic / English) or slug…')" class="flex h-9 w-full rounded-md border border-border bg-transparent px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" @input="apply(true)" />
      </label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('store_type', 'Store type') }}</span>
        <select v-model="f.store_type" :class="sel" data-testid="store-type" @change="typeChanged"><option value="">{{ L('all', 'All') }}</option><option value="physical">{{ L('type_physical', 'Physical (has branches)') }}</option><option value="physical_only">{{ L('type_physical_only', 'Physical, does not ship') }}</option><option value="physical_online">{{ L('type_physical_online', 'Physical + sells online (ships)') }}</option><option value="online">{{ L('type_online', 'Online only') }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('category', 'Category') }}</span>
        <select v-model="f.category_id" :class="sel" @change="apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="c in options.categories" :key="c.id" :value="String(c.id)">{{ nm(c.name) }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('tag', 'Tag') }}</span>
        <select v-model="f.tag_id" :class="sel" @change="apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="c in filterOptions.tags" :key="c.id" :value="String(c.id)">{{ nm(c.name) }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('sales', 'Sales rep') }}</span>
        <select v-model="f.sales_id" :class="sel" :disabled="f.sales_presence === 'without'" @change="apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="s in options.sales" :key="s.value" :value="String(s.value)">{{ s.label }}</option></select></label>
      <label v-if="!isOnline" class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('governorate', 'Branch governorate') }}</span>
        <select v-model="f.governorate_id" :class="sel" @change="f.city_id = ''; apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="g in options.governorates" :key="g.id" :value="String(g.id)">{{ nm(g.name) }}</option></select></label>
      <label v-if="!isOnline" class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('city', 'Branch city') }}</span>
        <select v-model="f.city_id" :class="sel" @change="apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="c in cities" :key="c.id" :value="String(c.id)">{{ nm(c.name) }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('created_by', 'Created by') }}</span>
        <select v-model="f.created_by" :class="sel" @change="apply()"><option value="">{{ L('all', 'All') }}</option><option v-for="u in filterOptions.creators" :key="u.id" :value="String(u.id)">{{ u.name }}</option></select></label>
    </div>

    <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground min-h-8" @click="more = !more">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="more ? 'rotate-180' : ''"><path d="m6 9 6 6 6-6"/></svg>
      {{ more ? L('less', 'Fewer filters') : L('more', 'More filters') }}
      <span v-if="activeCount" class="rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground">{{ activeCount }}</span>
    </button>

    <div v-show="more" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
      <label v-for="p in presenceFilters" :key="p.key" class="block min-w-0">
        <span class="mb-1 block text-xs font-medium">{{ p.label }}</span>
        <select v-model="f[p.key]" :class="sel" @change="apply()">
          <option value="">{{ L('any', 'Any') }}</option>
          <option :value="p.yes">{{ p.yesLabel || L('with', 'Has') }}</option>
          <option :value="p.no">{{ p.noLabel || L('without', 'Missing') }}</option>
        </select>
      </label>
      <template v-if="!isOnline">
        <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('branches', 'Branches (physical stores)') }}</span>
          <select v-model="f.branches_presence" :class="sel" @change="apply()"><option value="">{{ L('any', 'Any') }}</option><option value="with">{{ L('has_branches', 'Has branches') }}</option><option value="without">{{ L('physical_no_branches', 'Physical store with no branch yet') }}</option></select></label>
        <div class="grid grid-cols-2 gap-2 min-w-0"><label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('branches_min', 'Branches at least') }}</span><input v-model="f.branches_min" type="number" min="0" :class="inp" @change="apply()" /></label>
          <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('branches_max', 'Branches at most') }}</span><input v-model="f.branches_max" type="number" min="0" :class="inp" @change="apply()" /></label></div>
      </template>
      <label v-if="!isOnline" class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('branches_missing', 'A branch missing its place') }}</span>
        <select v-model="f.branches_missing" :class="sel" @change="apply()"><option value="">{{ L('any', 'Any') }}</option><option value="governorate">{{ L('no_gov', 'No governorate') }}</option><option value="city">{{ L('no_city', 'No city') }}</option><option value="either">{{ L('no_either', 'No governorate or city') }}</option><option value="both">{{ L('no_both', 'Neither governorate nor city') }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('ship_gov', 'Ships to governorate (stores that ship)') }}</span>
        <select v-model="f.shipping_governorate_id" :class="sel" @change="apply()"><option value="">{{ L('any', 'Any') }}</option><option v-for="g in options.governorates" :key="g.id" :value="String(g.id)">{{ nm(g.name) }}</option></select></label>
      <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('offer_format', 'Offer percent format') }}</span>
        <select v-model="f.offer_format" :class="sel" @change="apply()"><option value="">{{ L('any', 'Any') }}</option><option value="round">{{ L('round', 'Whole (10, 20…)') }}</option><option value="decimal">{{ L('decimal', 'With a fraction (5.5)') }}</option></select></label>
      <div class="grid grid-cols-2 gap-2 min-w-0"><label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('offer_min', 'Offer from %') }}</span><input v-model="f.offer_min" type="number" min="0" max="100" :class="inp" @change="apply()" /></label>
        <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('offer_max', 'Offer up to %') }}</span><input v-model="f.offer_max" type="number" min="0" max="100" :class="inp" @change="apply()" /></label></div>
      <div class="grid grid-cols-2 gap-2 min-w-0"><label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('contracted_from', 'Contracted from') }}</span><input v-model="f.contracted_from" type="date" :class="inp" @change="apply()" /></label>
        <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('contracted_to', 'Contracted to') }}</span><input v-model="f.contracted_to" type="date" :class="inp" @change="apply()" /></label></div>
      <div class="grid grid-cols-2 gap-2 min-w-0"><label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('created_from', 'Created from') }}</span><input v-model="f.created_from" type="date" :class="inp" @change="apply()" /></label>
        <label class="block min-w-0"><span class="mb-1 block text-xs font-medium">{{ L('created_to', 'Created to') }}</span><input v-model="f.created_to" type="date" :class="inp" @change="apply()" /></label></div>
      <label class="flex items-center gap-2 text-xs font-medium min-h-9 pt-5"><input v-model="f.seo_missing" type="checkbox" true-value="yes" false-value="" class="h-4 w-4" @change="apply()" /> {{ L('seo_missing', 'Missing SEO title or description') }}</label>
    </div>

    <div v-if="activeCount" class="flex justify-end"><button type="button" class="h-8 min-w-[7rem] rounded-md border border-border px-3 text-xs hover:bg-muted" data-testid="store-filters-clear" @click="clear">{{ L('clear', 'Clear filters') }}</button></div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({ filters: { type: Object, required: true }, options: { type: Object, required: true }, filterOptions: { type: Object, required: true } });
const page = usePage();
const t = computed(() => page.props.translations?.admin?.store_filters || {});
const L = (k, fallback) => t.value[k] || fallback;
const nm = (n) => (n && typeof n === 'object' ? n[page.props.locale || 'ar'] || n.ar || n.en || '' : n || '');

const sel = 'h-9 w-full min-w-0 rounded-md border border-border bg-background px-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const inp = sel;
const f = reactive({ ...props.filters });
const more = ref(Object.entries(props.filters).some(([k, v]) => v !== '' && !['search', 'store_type', 'category_id', 'tag_id', 'sales_id', 'governorate_id', 'city_id', 'created_by'].includes(k)));

const isOnline = computed(() => f.store_type === 'online');
// Branch filters mean nothing for an online-only store (it has none by design): switching to Online drops them.
function typeChanged() {
  if (f.store_type === 'online') {
    ['governorate_id', 'city_id', 'branches_presence', 'branches_min', 'branches_max', 'branches_missing'].forEach((k) => { f[k] = ''; });
  }
  apply();
}

const cities = computed(() => (props.options.cities || []).filter((c) => !f.governorate_id || String(c.governorate_id) === f.governorate_id));

const yn = (key, label) => ({ key, label, yes: 'yes', no: 'no', yesLabel: L('yes', 'Yes'), noLabel: L('no', 'No') });
const pr = (key, label, w, wo) => ({ key, label: `${label} — ${L('presence_suffix', 'present?')}`, yes: 'with', no: 'without', yesLabel: w, noLabel: wo });
const presenceFilters = computed(() => [
  pr('category_presence', L('category', 'Category'), L('has_category', 'Has a category'), L('no_category', 'No category')),
  pr('tags_presence', L('tags', 'Tags'), L('has_tags', 'Has tags'), L('no_tags', 'No tags')),
  pr('sales_presence', L('sales', 'Sales rep'), L('has_sales', 'Has a rep'), L('no_sales', 'No rep')),
  pr('managers_presence', L('managers', 'Contact managers'), L('has_managers', 'Has managers'), L('no_managers', 'No managers')),
  pr('products_presence', L('products', 'Products'), L('has_products', 'Has products'), L('no_products', 'No products')),
  yn('supports_shipping', L('supports_shipping', 'Supports shipping')),
  yn('ships_everywhere', L('ships_everywhere', 'Ships everywhere')),
  pr('offer_presence', L('offer', 'Offer percent'), L('has_offer', 'Has an offer'), L('no_offer', 'No offer')),
  pr('contract_presence', L('contract', 'Signed contract file'), L('has_contract', 'Has a contract'), L('no_contract', 'No contract')),
  pr('logo_presence', L('logo', 'Logo'), L('has_logo', 'Has a logo'), L('no_logo', 'No logo')),
  pr('coupons_presence', L('coupons', 'Coupons'), L('has_coupons', 'Has coupons'), L('no_coupons', 'No coupons')),
  pr('website_presence', L('websites', 'Websites'), L('has_websites', 'Has a website'), L('no_websites', 'No website')),
  pr('app_presence', L('apps', 'Mobile app links'), L('has_apps', 'Has an app link'), L('no_apps', 'No app link')),
  pr('social_presence', L('social', 'Social links'), L('has_social', 'Has social links'), L('no_social', 'No social links')),
]);

const activeCount = computed(() => Object.entries(f).filter(([k, v]) => k !== 'search' && v !== '' && v !== null).length + (f.search ? 1 : 0));

let timer = null;
function apply(debounce = false) {
  clearTimeout(timer);
  const go = () => router.get(route('admin.store.list'), Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)), { preserveState: true, preserveScroll: true, replace: true });
  debounce ? (timer = setTimeout(go, 300)) : go();
}
function clear() {
  Object.keys(f).forEach((k) => { f[k] = ''; });
  apply();
}
</script>
