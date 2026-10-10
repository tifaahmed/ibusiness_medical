<template>
  <div class="space-y-4">
    <!-- Every rejection, wherever its field lives (another tab, a closed branch
         editor, a gallery file, or the save itself failing) — so a save that
         bounces never looks like a save that did nothing. -->
    <div v-if="errorList.length" role="alert" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
      <div class="flex items-center gap-2 font-medium">
        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
        The store was not saved. Fix the following and save again:
      </div>
      <ul class="mt-2 list-disc space-y-0.5 ps-6 text-xs">
        <li v-for="item in errorList" :key="item.key">
          <span class="font-mono opacity-70">{{ item.key }}</span> — {{ item.message }}
        </li>
      </ul>
    </div>

    <TabBar v-model="activeTab" :tabs="formTabs" />

    <div v-show="activeTab === 'general'" class="space-y-4">
    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <h3 class="text-sm font-semibold">Store details</h3>

      <FormTranslatableInput
        v-model="form.title"
        label="Title"
        required
        :error="form.errors['title.ar'] || form.errors['title.en'] || form.errors.title"
      />

      <FormTranslatableInput
        v-model="form.short_description"
        label="Short description"
        multiline
        :rows="2"
        :error="form.errors['short_description.ar'] || form.errors['short_description.en']"
      />

      <FormTranslatableQuillEditor
        v-model="form.description"
        label="Description"
        :locales="['ar', 'en']"
        :image-uploader="uploadDescriptionImage"
        :error="form.errors['description.ar'] || form.errors['description.en']"
      >
        <template #label-actions>
          <button
            v-if="aiEnabled"
            type="button"
            :disabled="enhancing || !hasDescription"
            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
            :title="hasDescription ? 'Rewrite the description with short icon headings and bullet points, keeping every fact' : 'Write a description first.'"
            @click="enhanceDescription"
          >
            <svg v-if="enhancing" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
            {{ enhancing ? 'Enhancing…' : 'Enhance with AI' }}
          </button>
        </template>
      </FormTranslatableQuillEditor>

      <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
          <label class="text-sm font-medium">Categories</label>
          <span class="text-xs text-muted-foreground">{{ form.category_ids.length }} selected</span>
        </div>
        <p v-if="!categories.length" class="text-xs text-muted-foreground">No store categories yet — create one under Products → Store Categories.</p>
        <div class="flex flex-wrap gap-2">
          <label
            v-for="c in categories"
            :key="c.id"
            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium cursor-pointer transition"
            :class="form.category_ids.includes(c.id) ? 'border-sky-500 bg-sky-500/15 text-sky-600' : 'border-border hover:bg-muted'"
          >
            <input type="checkbox" :value="c.id" v-model="form.category_ids" class="sr-only" />
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
            {{ nameIn(c.name, locale) || getName(c.name) }}
            <!-- The other language and how many stores already use it. -->
            <span class="text-[10px] font-normal opacity-60" :dir="otherLocale === 'ar' ? 'rtl' : 'ltr'">{{ nameIn(c.name, otherLocale) }}</span>
            <span class="rounded-full bg-black/20 px-1.5 text-[10px] font-semibold" :title="`${c.stores_count ?? 0} stores`">{{ c.stores_count ?? 0 }}</span>
          </label>
        </div>
        <p v-if="form.errors.category_ids" class="text-xs text-destructive">{{ form.errors.category_ids }}</p>
      </div>

      <!-- Only tags marked "applies to stores"; none, no section. -->
      <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
          <label class="text-sm font-medium">Tags</label>
          <div class="flex items-center gap-2">
          <button v-if="aiEnabled" type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted" @click="tagAiOpen = true">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
            Tags with AI
          </button>
          <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted" @click="openTagDialog(null)">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            New tag
          </button>
          </div>
        </div>
        <p v-if="!tagList.length" class="text-xs text-muted-foreground">No tags for stores yet — create one.</p>
        <div class="flex flex-wrap gap-2">
          <span v-for="tag in tagList" :key="tag.id" class="inline-flex items-center rounded-full border transition-all text-xs font-medium" :style="tagStyle(tag, form.tag_ids.includes(tag.id))">
            <label class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 cursor-pointer">
              <input type="checkbox" :value="tag.id" v-model="form.tag_ids" class="sr-only" />
              <span v-if="tag.icon">{{ tag.icon }}</span>
              {{ getName(tag.name_translations || tag.name) }}
            </label>
            <button type="button" title="Edit tag" class="pr-2 pl-0.5 py-1 opacity-70 hover:opacity-100" @click="openTagDialog(tag)">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path></svg>
            </button>
          </span>
        </div>
        <StoreTagAiDialog :open="tagAiOpen" :form="form" :tags="tagList" @close="tagAiOpen = false" @matched="onTagsMatched" @created="onTagSaved" />
        <StoreTagDialog :open="tagDialogOpen" :tag="editingTag" :icon-options="tagIconOptions" :color-options="tagColorOptions" @close="tagDialogOpen = false" @saved="onTagSaved" />
        <p v-if="form.errors.tag_ids" class="text-xs text-destructive">{{ form.errors.tag_ids }}</p>
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium">YouTube link</label>
        <input
          v-model="form.youtube_link"
          type="url"
          dir="ltr"
          placeholder="https://youtube.com/watch?v=..."
          class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        />
        <p v-if="form.errors.youtube_link" class="text-xs text-destructive">{{ form.errors.youtube_link }}</p>
        <div v-if="youtubeEmbedUrl" class="aspect-video w-full max-w-xl overflow-hidden rounded-lg border border-border bg-black">
          <iframe
            :src="youtubeEmbedUrl"
            class="h-full w-full"
            title="YouTube preview"
            loading="lazy"
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
          ></iframe>
        </div>
        <p v-else-if="form.youtube_link" class="text-xs text-muted-foreground">Not a recognised YouTube link — no preview.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="space-y-2">
          <label class="text-sm font-medium">Offer % from</label>
          <input
            v-model="form.offer_percent_from"
            type="number" min="0" max="100" step="0.01"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          />
          <p v-if="form.errors.offer_percent_from" class="text-xs text-destructive">{{ form.errors.offer_percent_from }}</p>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Offer % to</label>
          <input
            v-model="form.offer_percent_to"
            type="number" min="0" max="100" step="0.01"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          />
          <p v-if="form.errors.offer_percent_to" class="text-xs text-destructive">{{ form.errors.offer_percent_to }}</p>
        </div>
      </div>
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-3">
      <h3 class="flex items-center gap-2 text-sm font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>
        Shipping coverage
      </h3>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" :value="true" v-model="form.supports_shipping" /> Supports shipping</label>
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" :value="false" v-model="form.supports_shipping" /> No shipping</label>
      </div>
      <div v-if="form.supports_shipping" class="flex flex-wrap gap-4 text-sm border-t border-border pt-3">
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" :value="true" v-model="form.ships_everywhere" /> All governorates</label>
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="radio" :value="false" v-model="form.ships_everywhere" /> Selected governorates only</label>
      </div>
      <div v-if="form.supports_shipping && !form.ships_everywhere" class="space-y-2">
        <div class="flex items-center justify-between text-xs text-muted-foreground">
          <span>{{ form.shipping_governorate_ids.length }} of {{ governorates.length }} selected</span>
          <span class="flex gap-3">
            <button type="button" class="underline" @click="form.shipping_governorate_ids = governorates.map(g => g.id)">Select all</button>
            <button type="button" class="underline" @click="form.shipping_governorate_ids = []">Clear</button>
          </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
          <label
            v-for="g in governorates"
            :key="g.id"
            class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm cursor-pointer transition"
            :class="form.shipping_governorate_ids.includes(g.id) ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'"
          >
            <input type="checkbox" :value="g.id" v-model="form.shipping_governorate_ids" />
            {{ getName(g.name) }}
          </label>
        </div>
      </div>
      <p v-if="form.errors.shipping_governorate_ids" class="text-xs text-destructive">{{ form.errors.shipping_governorate_ids }}</p>
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-5">
      <h3 class="flex items-center gap-2 text-sm font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
        Links &amp; coupons <span class="text-xs font-normal text-muted-foreground">(all optional)</span>
      </h3>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium">Websites</label>
          <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted" @click="form.websites.push('')"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg> Add website</button>
        </div>
        <div v-for="(site, i) in form.websites" :key="`w${i}`" class="space-y-1">
          <div class="flex gap-2">
            <input v-model="form.websites[i]" type="url" dir="ltr" placeholder="https://example.com" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
            <button type="button" title="Remove" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border bg-background text-destructive hover:bg-destructive/10" @click="form.websites.splice(i, 1)"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg></button>
          </div>
          <p v-if="form.errors[`websites.${i}`]" class="text-xs text-destructive">{{ form.errors[`websites.${i}`] }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="space-y-1">
          <label class="flex items-center gap-1.5 text-sm font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.800 2.300-1.600 2.800-.4 7 1.200 9.300.8 1.100 1.700 2.400 2.900 2.300 1.200 0 1.600-.7 3-.7s1.800.7 3 .7 2-1.100 2.800-2.200c.9-1.300 1.200-2.500 1.300-2.600-.1 0-2.500-1-2.500-3.900ZM14.200 5.700c.6-.8 1.100-1.800 1-2.900-.9 0-2 .6-2.700 1.400-.6.700-1.100 1.800-1 2.800 1 .1 2-.5 2.700-1.300Z"/></svg>
            App Store link
          </label>
          <input v-model="form.app_store_url" type="url" dir="ltr" placeholder="https://apps.apple.com/…" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
          <p v-if="form.errors.app_store_url" class="text-xs text-destructive">{{ form.errors.app_store_url }}</p>
        </div>
        <div class="space-y-1">
          <label class="flex items-center gap-1.5 text-sm font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M4 2.700v18.600c0 .4.4.600.7.400l9.700-9.300L4.700 2.300c-.3-.2-.7 0-.7.400Zm11.200 8.300 2.700-2.600-11-6.300 8.300 8.900Zm0 2L6.900 21.900l11-6.300-2.700-2.600Zm3.500-3.200-2.500 2.300 2.500 2.300 2.700-1.600c.8-.5.800-1.500 0-2l-2.700-1Z"/></svg>
            Google Play link
          </label>
          <input v-model="form.google_play_url" type="url" dir="ltr" placeholder="https://play.google.com/store/apps/…" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
          <p v-if="form.errors.google_play_url" class="text-xs text-destructive">{{ form.errors.google_play_url }}</p>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium">Social media</label>
          <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted" @click="form.social_links.push({ platform: 'facebook', url: '' })"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg> Add link</button>
        </div>
        <div v-for="(link, i) in form.social_links" :key="`s${i}`" class="space-y-1">
          <div class="flex gap-2">
            <select v-model="link.platform" class="h-9 w-40 shrink-0 rounded-md border border-input bg-background px-2 text-sm">
              <option v-for="p in socialPlatforms" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <input v-model="link.url" type="url" dir="ltr" placeholder="https://…" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
            <button type="button" title="Remove" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border bg-background text-destructive hover:bg-destructive/10" @click="form.social_links.splice(i, 1)"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg></button>
          </div>
          <p v-if="form.errors[`social_links.${i}.url`]" class="text-xs text-destructive">{{ form.errors[`social_links.${i}.url`] }}</p>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium">Coupons</label>
          <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted" @click="form.coupons.push({ code: '', title: { ar: '', en: '' }, expires_at: '' })"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg> Add coupon</button>
        </div>
        <div v-for="(coupon, i) in form.coupons" :key="`c${i}`" class="space-y-2 rounded-lg border border-border p-3">
          <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 text-xs font-semibold uppercase text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M13 5v2"></path><path d="M13 17v2"></path><path d="M13 11v2"></path></svg>
              Coupon {{ i + 1 }}
              <span v-if="isExpired(coupon)" class="rounded-full bg-destructive/15 px-2 py-0.5 text-[10px] normal-case text-destructive">Expired</span>
            </span>
            <button type="button" title="Remove" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border bg-background text-destructive hover:bg-destructive/10" @click="form.coupons.splice(i, 1)"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg></button>
          </div>
          <FormTranslatableInput v-model="coupon.title" label="Title" />
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="space-y-1">
              <label class="text-xs font-medium">Code</label>
              <input v-model="coupon.code" type="text" dir="ltr" maxlength="64" placeholder="SAVE20" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
              <p v-if="form.errors[`coupons.${i}.code`]" class="text-xs text-destructive">{{ form.errors[`coupons.${i}.code`] }}</p>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-medium">Expiration date</label>
              <input v-model="coupon.expires_at" type="date" dir="ltr" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
              <p v-if="form.errors[`coupons.${i}.expires_at`]" class="text-xs text-destructive">{{ form.errors[`coupons.${i}.expires_at`] }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <h3 class="text-sm font-semibold">Images</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="text-sm font-medium">Logo</label>
          <ImageFileInput
            :max-size="5"
            :initial-preview="existingLogo"
            :crop="false"
            :accepted-types="imageTypes"
            @file-selected="onLogoSelected"
          />
          <p v-if="form.errors.logo" class="text-xs text-destructive">{{ form.errors.logo }}</p>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Header</label>
          <ImageFileInput
            :max-size="5"
            :initial-preview="existingHeader"
            :crop="false"
            :accepted-types="imageTypes"
            @file-selected="onHeaderSelected"
          />
          <p v-if="form.errors.header" class="text-xs text-destructive">{{ form.errors.header }}</p>
        </div>
      </div>

      <StoreGalleryInput
        label="Gallery"
        hint="Images and videos shown on the store page, 5 per row — max 5MB each"
        :max-size="5"
        :existing-items="visibleExistingGallery"
        :errors="form.errors"
        @remove-existing="onRemoveExistingGalleryItem"
        @update:images="(files) => (form.gallery_images = files)"
        @update:videos="(files) => (form.gallery_videos = files)"
      />
    </div>

    <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold">Branches</h3>
        <button
          v-if="!form.online_only"
          type="button"
          @click="addBranch"
          class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md border border-border bg-background text-xs font-medium hover:bg-muted"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path><path d="M12 5v14"></path>
          </svg>
          Add branch
        </button>
      </div>

      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Store type">
        <button
          type="button" role="radio" :aria-checked="form.online_only"
          class="flex items-start gap-2 rounded-lg border p-3 text-left transition"
          :class="form.online_only ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'"
          @click="setOnlineOnly(true)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0"><circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20Z" /></svg>
          <span><span class="block text-sm font-medium">Online store only</span><span class="block text-xs text-muted-foreground">No physical branches — sold through the web or app.</span></span>
        </button>
        <button
          type="button" role="radio" :aria-checked="!form.online_only"
          class="flex items-start gap-2 rounded-lg border p-3 text-left transition"
          :class="!form.online_only ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'"
          @click="setOnlineOnly(false)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0"><path d="M3 9l1-5h16l1 5" /><path d="M4 9v11h16V9" /><path d="M9 20v-6h6v6" /></svg>
          <span><span class="block text-sm font-medium">Online and physical store</span><span class="block text-xs text-muted-foreground">Also has branches customers can visit.</span></span>
        </button>
      </div>

      <p v-if="form.online_only" class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
        This is an online-only store, so it has no branches. Any branches it had are removed when you save.
      </p>

      <p v-if="!form.online_only && !form.branches.length" class="text-sm text-muted-foreground">No branches yet — click "Add branch" to add one.</p>

      <div v-if="!form.online_only && form.branches.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div
          v-for="(branch, index) in form.branches"
          :key="index"
          class="rounded-lg border p-3 space-y-2"
          :class="branchHasErrors(index) ? 'border-destructive' : 'border-border'"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold">{{ getName(branch.name) || `Branch ${index + 1}` }}</p>
              <p class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                {{ placeLine(branch) || 'No place chosen' }}
              </p>
            </div>
            <div class="flex shrink-0 gap-1">
              <button type="button" title="Edit branch" @click="openEdit(index)" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background hover:bg-muted">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
              </button>
              <button type="button" title="Remove branch" @click="removeBranch(index)" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-destructive hover:bg-destructive/10">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
              </button>
            </div>
          </div>
          <p v-if="getName(branch.address)" class="line-clamp-2 text-xs">{{ getName(branch.address) }}</p>
          <div class="flex flex-wrap gap-1.5 text-[11px]">
            <span class="rounded-full px-2 py-0.5" :class="hasCoords(branch) ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600'">
              {{ hasCoords(branch) ? 'GPS set' : 'No GPS' }}
            </span>
            <span class="rounded-full bg-muted px-2 py-0.5">{{ (branch.phone || []).length }} phone(s)</span>
            <span v-if="branchHasErrors(index)" class="rounded-full bg-destructive/15 px-2 py-0.5 text-destructive">Needs attention</span>
          </div>
        </div>
      </div>

      <Teleport to="body">
        <!-- No backdrop or Escape handler on purpose: only the Close button leaves. -->
        <div v-if="!form.online_only && editingIndex !== null" class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
          <div class="my-8 w-full max-w-3xl rounded-xl border border-border bg-card text-card-foreground shadow-lg">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
              <h3 class="text-sm font-semibold">{{ editingIndex === -1 ? 'Add branch' : `Edit branch ${editingIndex + 1}` }}</h3>
              <button type="button" @click="requestClose" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                Close
              </button>
            </div>
            <div class="space-y-3 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-medium">Governorate</label>
            <Select
              v-model="draft.governorate_id"
              :options="governorates.map(g => ({ value: g.id, label: `${getName(g.name)} (${citiesFor(g.id).length} cities)` }))"
              placeholder="Select governorate"
              @update:modelValue="draft.city_id = ''"
            />
            <p v-if="form.errors[`branches.${editingIndex}.governorate_id`]" class="text-xs text-destructive">{{ form.errors[`branches.${editingIndex}.governorate_id`] }}</p>
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">City</label>
            <Select
              v-model="draft.city_id"
              :options="citiesFor(draft.governorate_id).map(c => ({ value: c.id, label: `${getName(c.name)} (${c.areas_count ?? 0} areas)` }))"
              placeholder="Select city"
              @update:modelValue="draft.area = { ar: '', en: '' }"
            />
            <p v-if="form.errors[`branches.${editingIndex}.city_id`]" class="text-xs text-destructive">{{ form.errors[`branches.${editingIndex}.city_id`] }}</p>
          </div>
        </div>

        <FormTranslatableInput v-model="draft.name" label="Branch name" />
        <FormTranslatableInput v-model="draft.address" label="Address" multiline :rows="2" />
        <StoreBranchAreaSelect v-model="draft.area" :city-id="draft.city_id" />

        <div v-if="aiEnabled" class="flex justify-end">
          <button
            type="button"
            :disabled="!branchHasAddress(draft) || locatingIndex === editingIndex"
            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
            :title="branchHasAddress(draft) ? 'Read the address above and fill in the coordinates and the Google Maps link' : 'Enter the branch address first.'"
            @click="locateBranch(branch, index)"
          >
            <svg v-if="locatingIndex === editingIndex" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            {{ locatingIndex === editingIndex ? 'Locating…' : (hasCoords(draft) ? 'Replace GPS with AI' : 'Find GPS with AI') }}
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-medium">Latitude</label>
            <input
              v-model="draft.latitude"
              type="number" step="0.0000001" dir="ltr"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">Longitude</label>
            <input
              v-model="draft.longitude"
              type="number" step="0.0000001" dir="ltr"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium">Google Maps link</label>
            <input
              v-model="draft.google_location_url"
              type="url" dir="ltr" placeholder="https://maps.google.com/..."
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
        </div>

        <FacilityBranchLocationMap
          editable
          :latitude="draft.latitude"
          :longitude="draft.longitude"
          @update:latitude="draft.latitude = $event"
          @update:longitude="draft.longitude = $event; draft.google_location_url = mapsLink(draft.latitude, $event)"
        />
        <p class="text-[11px] text-muted-foreground">Drag the pin, or click the map, to adjust the location.</p>

        <BranchPhonesInput
          v-model="draft.phone"
          label="Phone numbers"
          :errors="form.errors"
          :error-prefix="`branches.${editingIndex}.phone`"
        />
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
              <span v-if="isDirty" class="mr-auto text-xs text-amber-600">Unsaved changes</span>
              <button type="button" @click="requestClose" class="h-9 rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted">Cancel</button>
              <button type="button" @click="saveDraft" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">Save branch</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
    </div>

    <!-- SEO tab -->
    <div v-show="activeTab === 'seo'" class="space-y-4">
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-5">
        <div class="space-y-1">
          <h3 class="flex items-center gap-2 text-sm font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            SEO metadata
          </h3>
          <p class="text-xs text-muted-foreground">How the store appears in Google results and when its page is shared. Leave a field empty to fall back to the store title / description.</p>
          <div v-if="aiEnabled" class="pt-1">
            <button type="button" :disabled="seoGenerating" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none" @click="generateSeo">
              <svg v-if="seoGenerating" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
              {{ seoGenerating ? 'Generating…' : 'Fill SEO with AI' }}
            </button>
          </div>
        </div>

        <div class="space-y-1">
          <FormTranslatableInput
            v-model="form.meta_title"
            label="Meta title"
            :locales="['ar', 'en']"
            :maxlength="60"
            :error="form.errors['meta_title.ar'] || form.errors['meta_title.en'] || form.errors.meta_title"
          />
          <div class="flex gap-4 text-[11px]">
            <span :class="counterClass(form.meta_title.ar, 60)">AR {{ (form.meta_title.ar || '').length }}/60</span>
            <span :class="counterClass(form.meta_title.en, 60)">EN {{ (form.meta_title.en || '').length }}/60</span>
          </div>
          <p class="text-[11px] text-muted-foreground">The clickable headline in search results. Aim for 50–60 characters with the store name first.</p>
        </div>

        <div class="space-y-1">
          <label class="block text-sm font-medium">Meta description</label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="loc in ['ar', 'en']" :key="`md-${loc}`">
              <label class="block text-xs font-medium text-muted-foreground mb-1">{{ loc.toUpperCase() }}</label>
              <textarea
                v-model="form.meta_description[loc]"
                :dir="loc === 'ar' ? 'rtl' : 'ltr'"
                rows="3"
                maxlength="160"
                class="w-full py-2 px-3 border border-border text-foreground bg-transparent focus:border-ring focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 resize-y text-sm"
              ></textarea>
              <p class="mt-1 text-[11px]" :class="counterClass(form.meta_description[loc], 160)">{{ (form.meta_description[loc] || '').length }}/160</p>
              <p v-if="form.errors[`meta_description.${loc}`]" class="text-xs text-destructive">{{ form.errors[`meta_description.${loc}`] }}</p>
            </div>
          </div>
          <p class="text-[11px] text-muted-foreground">One or two sentences under the title: what the store offers and one benefit. 120–160 characters is ideal.</p>
        </div>

        <div class="space-y-1">
          <FormTranslatableInput
            v-model="form.meta_keywords"
            label="Meta keywords"
            :locales="['ar', 'en']"
            :error="form.errors['meta_keywords.ar'] || form.errors['meta_keywords.en'] || form.errors.meta_keywords"
          />
          <p class="text-[11px] text-muted-foreground">Comma-separated terms people might search for. Optional.</p>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">SEO image</label>
          <div class="max-w-xs">
            <ImageFileInput
              :max-size="5"
              :initial-preview="existingSeoImage"
              :crop="false"
              :accepted-types="imageTypes"
              @file-selected="onSeoImageSelected"
            />
          </div>
          <p v-if="form.errors.seo_image" class="text-xs text-destructive">{{ form.errors.seo_image }}</p>
          <p class="text-[11px] text-muted-foreground">
            The picture shown when the store is shared or listed by search engines.
            <template v-if="!existingSeoImage && !form.seo_image">Left empty, a separate copy of the store logo is filed as the SEO image when you save.</template>
          </p>
        </div>

        <!-- How it will look in search results -->
        <div class="rounded-lg border border-border p-3 space-y-0.5 bg-background/40">
          <p class="text-[11px] text-muted-foreground">Search result preview</p>
          <p class="text-base text-sky-500 truncate">{{ seoPreview.title || 'Store title' }}</p>
          <p class="text-xs text-emerald-600 truncate" dir="ltr">{{ seoPreview.url }}</p>
          <p class="text-xs text-muted-foreground line-clamp-2">{{ seoPreview.description || 'The store description will be used here.' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { useEditorImageUpload } from '@/composables/useEditorImageUpload';
import { useNotification } from '@/composables/useNotification';
import ImageFileInput from '@/Components/form/ImageFileInput.vue';
import StoreGalleryInput from '@/Components/form/StoreGalleryInput.vue';
import BranchPhonesInput from '@/Components/form/BranchPhonesInput.vue';
import FormTranslatableInput from '@/Components/form/FormTranslatableInput.vue';
import { FormTranslatableQuillEditor } from '@/Components/form';
import StoreBranchAreaSelect from './StoreBranchAreaSelect.vue';
import FacilityBranchLocationMap from '@/Pages/Admin/FacilityBranch/Form/FacilityBranchLocationMap.vue';
import Select from '@/Components/ui/Select.vue';
import { usePage } from '@inertiajs/vue3';
import TabBar from '@/Components/ui/TabBar.vue';
import StoreTagDialog from './StoreTagDialog.vue';
import StoreTagAiDialog from './StoreTagAiDialog.vue';

// Matches the backend rule (mimes:jpeg,jpg,png,gif,webp,avif) on logo/header.
const imageTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif'];

const props = defineProps({
  form: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  tagIconOptions: { type: Array, default: () => [] },
  tagColorOptions: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  existingLogo: { type: String, default: '' },
  existingHeader: { type: String, default: '' },
  existingSeoImage: { type: String, default: '' },
  existingGallery: { type: Array, default: () => [] },
  aiEnabled: { type: Boolean, default: false },
});

const locale = computed(() => usePage().props.locale === 'en' ? 'en' : 'ar');
const otherLocale = computed(() => (locale.value === 'ar' ? 'en' : 'ar'));
const nameIn = (name, loc) => (name && typeof name === 'object' ? name[loc] || '' : '');

const seoGenerating = ref(false);
const generateSeo = async () => {
  if (seoGenerating.value) return;
  seoGenerating.value = true;
  try {
    const f = props.form;
    const { data } = await axios.post(route('admin.store.seo.generate'), {
      title: f.title,
      short_description: f.short_description,
      description: f.description,
      categories: props.categories.filter(c => f.category_ids.includes(c.id)).map(c => c.name?.en || c.name?.ar),
      tags: tagList.value.filter(t => f.tag_ids.includes(t.id)).map(t => t.name_translations?.en || t.name_translations?.ar),
      offer_percent_from: f.offer_percent_from === '' ? null : f.offer_percent_from,
      offer_percent_to: f.offer_percent_to === '' ? null : f.offer_percent_to,
    });
    const seo = data?.seo;
    if (!seo) throw new Error('empty');
    f.meta_title = { ...f.meta_title, ...seo.meta_title };
    f.meta_description = { ...f.meta_description, ...seo.meta_description };
    f.meta_keywords = { ...f.meta_keywords, ...seo.meta_keywords };
    useNotification().success('SEO fields filled in. Review them before saving.');
  } catch (error) {
    reportError(error, 'generate-seo');
    useNotification().error(error?.response?.data?.message || 'Could not generate the SEO. Please try again.');
  } finally {
    seoGenerating.value = false;
  }
};

const activeTab = ref('general');
const seoFields = ['meta_title', 'meta_description', 'meta_keywords'];
const isSeoKey = (k) => seoFields.some(f => k === f || k.startsWith(`${f}.`));
const errorList = computed(() => Object.entries(props.form.errors || {}).map(([key, message]) => ({ key, message })));
const formTabs = computed(() => [
  { key: 'general', label: 'Details', hasError: errorList.value.some(e => !isSeoKey(e.key)) },
  { key: 'seo', label: 'SEO', hasError: errorList.value.some(e => isSeoKey(e.key)) },
]);
const counterClass = (value, max) => {
  const n = (value || '').length;
  return n > max ? 'text-destructive' : n >= max * 0.8 ? 'text-emerald-600' : 'text-muted-foreground';
};
const seoPreview = computed(() => {
  const f = props.form;
  const plain = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  return {
    title: f.meta_title.en || f.meta_title.ar || f.title.en || f.title.ar,
    description: f.meta_description.en || f.meta_description.ar || plain(f.short_description.en || f.short_description.ar || f.description.en || f.description.ar).slice(0, 160),
    url: `${window.location.origin}/stores/…`,
  };
});

// Local copy so a tag created or renamed in the popup shows at once.
const tagList = ref([...props.tags]);
const tagDialogOpen = ref(false);
const tagAiOpen = ref(false);
const onTagsMatched = (ids) => ids.forEach(id => { if (!props.form.tag_ids.includes(id)) props.form.tag_ids.push(id); });
const editingTag = ref(null);
const openTagDialog = (tag) => { editingTag.value = tag; tagDialogOpen.value = true; };
const onTagSaved = (saved) => {
  const i = tagList.value.findIndex(t => t.id === saved.id);
  if (i === -1) {
    tagList.value.push(saved);
    props.form.tag_ids.push(saved.id);
  } else {
    tagList.value.splice(i, 1, saved);
  }
};

const tagStyle = (tag, selected) => {
  const color = tag.color || '#6B7280';
  return selected
    ? { backgroundColor: `${color}26`, color, borderColor: color }
    : { borderColor: `${color}55`, color: 'inherit' };
};

const getName = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') return name['ar'] || name['en'] || Object.values(name)[0] || '';
  return '';
};

const citiesFor = (governorateId) => props.cities.filter(c => String(c.governorate_id) === String(governorateId));

const visibleExistingGallery = computed(() =>
  props.existingGallery.filter(item => !props.form.gallery_delete.includes(item.id))
);

const onLogoSelected = (file) => { props.form.logo = file; if (!file) props.form.logo_delete = true; };
const onSeoImageSelected = (file) => { props.form.seo_image = file; if (!file && 'seo_image_delete' in props.form) props.form.seo_image_delete = true; };
const onHeaderSelected = (file) => { props.form.header = file; if (!file) props.form.header_delete = true; };

const onRemoveExistingGalleryItem = (id) => {
  if (!props.form.gallery_delete.includes(id)) props.form.gallery_delete.push(id);
};

// ---- YouTube preview -------------------------------------------------------
const youtubeEmbedUrl = computed(() => {
  const raw = String(props.form.youtube_link || '').trim();
  if (!raw) return '';
  let url;
  try { url = new URL(raw); } catch { return ''; }
  const host = url.hostname.replace(/^www\.|^m\./, '');
  let id = '';
  if (host === 'youtu.be') id = url.pathname.slice(1).split('/')[0];
  else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
    if (url.pathname === '/watch') id = url.searchParams.get('v') || '';
    else {
      const m = url.pathname.match(/^\/(embed|shorts|live|v)\/([^/?]+)/);
      if (m) id = m[2];
    }
  }
  return /^[\w-]{11}$/.test(id) ? `https://www.youtube-nocookie.com/embed/${id}` : '';
});

// ---- AI helpers ------------------------------------------------------------
// Both answer JSON and write nothing: values land in the open form for the
// admin to check and save. Failures are reported to /api/v1/client-errors.
const reportError = (error, step, extra = {}) => {
  axios.post('/api/v1/client-errors', {
    message: error?.message || String(error),
    stack: error?.stack,
    fatal: false,
    route: window.location.pathname,
    extra: { feature: 'store-form-ai', step, status: error?.response?.status, ...extra },
  }).catch(() => {});
};

const filled = (value) => {
  if (typeof value === 'string') return value.trim() !== '';
  if (value && typeof value === 'object') return Object.values(value).some(v => typeof v === 'string' && v.trim() !== '');
  return false;
};
const textOf = (html) => String(html || '').replace(/<[^>]*>/g, '').trim();

const enhancing = ref(false);
const hasDescription = computed(() =>
  ['ar', 'en'].some(l => textOf(props.form.description?.[l]) !== '' || String(props.form.description?.[l] || '').includes('<img'))
);

const enhanceDescription = async () => {
  if (enhancing.value || !hasDescription.value) return;
  enhancing.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.description.enhance'), {
      description: { ar: props.form.description.ar || '', en: props.form.description.en || '' },
      name: { ar: props.form.title?.ar || '', en: props.form.title?.en || '' },
    });
    const values = data?.values || {};
    if (!values.ar && !values.en) throw new Error('empty');
    props.form.description = { ...props.form.description, ...values };
    const skipped = ['ar', 'en'].filter(l => !values[l]);
    if (skipped.length) {
      useNotification().warning(`Only part of the description was enhanced: ${skipped.map(l => l.toUpperCase()).join(', ')} was left as it was. Try again.`);
    } else {
      useNotification().success('Description reorganised in Arabic and English. Read it over before saving.');
    }
  } catch (error) {
    reportError(error, 'enhance-description');
    useNotification().error(error?.response?.data?.message || 'Could not enhance the description. Please try again.');
  } finally {
    enhancing.value = false;
  }
};

const locatingIndex = ref(null);
const branchHasAddress = (branch) => filled(branch.address);
const hasCoords = (branch) => String(branch.latitude ?? '').trim() !== '' && String(branch.longitude ?? '').trim() !== '';

const locateBranch = async (branch, index) => {
  if (locatingIndex.value !== null || !branchHasAddress(branch)) return;
  if (hasCoords(branch) && !window.confirm("This branch already has coordinates. Replace them with the AI's answer?")) return;

  const label = (list, id) => {
    const match = list.find(x => String(x.id) === String(id));
    return match ? getName(match.name) : null;
  };

  locatingIndex.value = index;
  try {
    const { data } = await axios.post(route('admin.facility.branch.locate'), {
      address: branch.address || {},
      name: branch.name || {},
      facility_name: props.form.title || {},
      governorate: label(props.governorates, branch.governorate_id),
      city: label(props.cities, branch.city_id),
    });
    const location = data?.location;
    if (!location) throw new Error('empty');
    branch.latitude = location.latitude;
    branch.longitude = location.longitude;
    branch.google_location_url = location.google_location_url;
    useNotification().success('Location filled in. Open the map link to check the pin before saving.' + (location.matched_place ? ` (${location.matched_place})` : ''));
  } catch (error) {
    if (error?.response?.status !== 422) reportError(error, 'locate-branch', { branch_index: index });
    useNotification().error(error?.response?.data?.message || 'Could not find the location. Please try again.');
  } finally {
    locatingIndex.value = null;
  }
};

const mapsLink = (lat, lng) => `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;

const socialPlatforms = [
  { value: 'facebook', label: 'Facebook' }, { value: 'instagram', label: 'Instagram' },
  { value: 'x', label: 'X (Twitter)' }, { value: 'tiktok', label: 'TikTok' },
  { value: 'youtube', label: 'YouTube' }, { value: 'linkedin', label: 'LinkedIn' },
  { value: 'whatsapp', label: 'WhatsApp' }, { value: 'snapchat', label: 'Snapchat' },
  { value: 'telegram', label: 'Telegram' }, { value: 'other', label: 'Other' },
];
const isExpired = (coupon) => !!coupon.expires_at && new Date(`${coupon.expires_at}T23:59:59`) < new Date();

const blankBranch = () => ({
  governorate_id: '',
  city_id: '',
  name: { ar: '', en: '' },
  address: { ar: '', en: '' },
  area: { ar: '', en: '' },
  latitude: '',
  longitude: '',
  google_location_url: '',
  phone: [],
});

// Switching to online-only drops the branches on save, so a form that already
// holds some asks first rather than silently emptying them.
const setOnlineOnly = (value) => {
  if (value && props.form.branches.length
    && !window.confirm(`This store has ${props.form.branches.length} branch(es). Online-only stores have none — they will be removed when you save. Continue?`)) return;
  props.form.online_only = value;
};

// Images added inside the description are stored at once and tied to the store
// as hidden gallery images on save; the description keeps a host-less URL.
const uploadDescriptionImage = useEditorImageUpload('store', (path) => {
  if (!props.form.editor_gallery_paths.includes(path)) props.form.editor_gallery_paths.push(path);
});

const clone = (v) => JSON.parse(JSON.stringify(v));

// The modal edits a copy; nothing reaches the form until "Save branch".
const editingIndex = ref(null); // null = closed, -1 = adding, n = editing branch n
const draft = ref(blankBranch());
const snapshot = ref('');
const isDirty = computed(() => editingIndex.value !== null && JSON.stringify(draft.value) !== snapshot.value);

const openModal = (index, data) => {
  draft.value = clone(data);
  snapshot.value = JSON.stringify(draft.value);
  editingIndex.value = index;
};
const addBranch = () => openModal(-1, blankBranch());
const openEdit = (index) => openModal(index, props.form.branches[index]);

const requestClose = () => {
  if (isDirty.value && !window.confirm('You have unsaved changes in this branch. Close without saving?')) return;
  editingIndex.value = null;
};

const saveDraft = () => {
  if (editingIndex.value === -1) props.form.branches.push(clone(draft.value));
  else props.form.branches.splice(editingIndex.value, 1, clone(draft.value));
  editingIndex.value = null;
};

const removeBranch = (index) => {
  if (window.confirm('Remove this branch?')) props.form.branches.splice(index, 1);
};

const branchHasErrors = (index) => Object.keys(props.form.errors).some(k => k.startsWith(`branches.${index}.`));
const placeLine = (branch) => {
  const g = props.governorates.find(x => String(x.id) === String(branch.governorate_id));
  const c = props.cities.find(x => String(x.id) === String(branch.city_id));
  return [c && getName(c.name), g && getName(g.name)].filter(Boolean).join('، ');
};
</script>
