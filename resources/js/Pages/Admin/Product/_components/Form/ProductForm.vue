<template>
  <div class="space-y-2 sm:space-y-3 md:space-y-4">
    <TabBar v-model="activeTab" :tabs="formTabs" />

    <template v-if="activeTab === 'general'">
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path>
            <path d="M12 22V12"></path>
          </svg>
          <span class="text-sm sm:text-base">Product Information</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6 space-y-4">
        <FormTranslatableInput
          v-model="formName"
          label="Name"
          :error="nameError"
          placeholder="Enter product name"
          required
          :locales="['ar', 'en']"
          hint="The main title customers see everywhere — keep it short, clear and searchable."
        />

        <FormTranslatableInput
          v-model="formShortSubject"
          label="Short Description"
          :error="shortSubjectError"
          placeholder="Enter short description"
          :locales="['ar', 'en']"
          hint="One-line summary shown under the name in product lists and cards."
        />

        <div class="space-y-2">
          <FormTranslatableQuillEditor
            v-model="formDescription"
            label="Description"
            :error="descriptionError"
            :locales="['ar', 'en']"
            :image-uploader="uploadEditorImage"
          />
          <p class="text-[11px] text-muted-foreground">Full details customers read on the product page — specs, what's included, how to use it. Images you add here (toolbar, paste or drag &amp; drop) are uploaded and saved to the product gallery.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">New Price</label>
            <input
              v-model="productStore.form.new_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <p v-if="fieldError('new_price')" class="text-xs text-destructive">{{ fieldError('new_price') }}</p>
            <p v-else class="text-[11px] text-muted-foreground">The price customers pay today.</p>
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Old Price</label>
            <input
              v-model="productStore.form.old_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <p v-if="fieldError('old_price')" class="text-xs text-destructive">{{ fieldError('old_price') }}</p>
            <p v-else class="text-[11px] text-muted-foreground">Shown struck-through next to the new price to advertise a discount. Leave empty if none.</p>
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Cost Price</label>
            <input
              v-model="productStore.form.cost_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <p v-if="fieldError('cost_price')" class="text-xs text-destructive">{{ fieldError('cost_price') }}</p>
            <p v-else class="text-[11px] text-muted-foreground">Internal only — what you pay your supplier. Never shown to customers.</p>
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Profit Price</label>
            <input
              v-model="productStore.form.profit_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <p v-if="fieldError('profit_price')" class="text-xs text-destructive">{{ fieldError('profit_price') }}</p>
            <p v-else class="text-[11px] text-muted-foreground">Internal only — expected margin per unit, used for reports.</p>
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Product Type</label>
          <select
            v-model="productStore.form.product_type_id"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          >
            <option value="">— None —</option>
            <option v-for="pt in productTypes" :key="pt.id" :value="pt.id">
              {{ getTranslatedName(pt.name) }}
            </option>
          </select>
          <p v-if="fieldError('product_type_id')" class="text-xs text-destructive">{{ fieldError('product_type_id') }}</p>
            <p v-else class="text-[11px] text-muted-foreground">Groups the product under a category — used for menus and filtering.</p>
        </div>

        <!--
          Storefront availability. Three switches rather than one status because
          they answer different questions and get used in combination: listed
          but not openable (a teaser), openable but not listed (an unlisted link
          for one customer), readable but not sellable (out of stock).
        -->
        <div class="space-y-3">
          <label class="text-sm font-medium">Storefront availability</label>

          <div
            v-for="option in availabilityOptions"
            :key="option.key"
            class="flex items-start gap-3"
          >
            <label class="relative inline-flex items-center cursor-pointer mt-0.5 shrink-0">
              <input
                type="checkbox"
                v-model="productStore.form[option.key]"
                class="sr-only peer"
              />
              <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-golden-yellow"></div>
            </label>
            <div class="grid gap-0.5">
              <span class="text-sm font-medium">{{ option.label }}</span>
              <span class="text-[11px] text-muted-foreground">{{ option.hint }}</span>
              <p v-if="fieldError(option.key)" class="text-xs text-destructive">{{ fieldError(option.key) }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Admin Note</label>
          <textarea
            v-model="productStore.form.admin_note"
            rows="3"
            placeholder="Internal note (not shown to customers)"
            class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
            :class="adminNoteErrorClass"
          ></textarea>
          <p v-if="adminNoteError" class="mt-1 text-sm text-destructive">{{ adminNoteError }}</p>
          <p v-else class="text-[11px] text-muted-foreground">Visible to admins only — useful for supplier names, restock notes or warnings.</p>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between gap-2">
            <label class="text-sm font-medium">Tags</label>
            <a
              :href="route('admin.tag.create')"
              target="_blank"
              rel="noopener"
              class="text-xs text-primary hover:underline"
            >
              + New tag
            </a>
          </div>
          <p v-if="!tags.length" class="text-xs text-muted-foreground">
            No tags yet. Create one first, then reload this page to attach it.
          </p>
          <div v-else class="flex flex-wrap gap-2">
            <label
              v-for="tag in tags"
              :key="tag.id"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border cursor-pointer transition-all text-xs font-medium"
              :style="productTagStyle(tag, productStore.form.tag_ids.includes(tag.id))"
            >
              <input
                type="checkbox"
                :value="tag.id"
                v-model="productStore.form.tag_ids"
                class="sr-only"
              />
              <span v-if="tag.icon">{{ tag.icon }}</span>
              <span
                class="w-2 h-2 rounded-full shrink-0 ring-1 ring-white/30"
                :style="{ backgroundColor: tag.color || '#6B7280' }"
              ></span>
              {{ productTagPrimary(tag) }}
              <!-- The name in the other language, so both are readable while choosing. -->
              <span
                v-if="productTagOther(tag)"
                class="text-[10px] font-normal opacity-60"
                :dir="otherLocale === 'ar' ? 'rtl' : 'ltr'"
              >
                {{ productTagOther(tag) }}
              </span>
            </label>
          </div>
          <p class="text-[11px] text-muted-foreground">Tap to select or deselect. Tags surface the product in tag-based sections and offers.</p>
        </div>
      </div>
    </div>

    </template>

    <template v-else-if="activeTab === 'media'">
    <!-- Banner Card — same config shape as the facility banner -->
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span class="text-sm sm:text-base">Banner</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6 space-y-4">
        <div class="flex items-center gap-3">
          <label class="relative inline-flex items-center cursor-pointer">
            <input
              type="checkbox"
              :checked="bannerEnabled"
              @change="toggleBanner"
              class="sr-only peer"
            />
            <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-golden-yellow"></div>
          </label>
          <span class="text-sm font-medium">Enable Banner</span>
        </div>

        <div v-if="bannerEnabled" class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Number of Days</label>
            <input
              type="number"
              :value="bannerConfig.days"
              @input="updateBannerConfig('days', Number($event.target.value))"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              min="1"
              max="365"
              placeholder="30"
            />
            <p class="text-xs text-muted-foreground mt-1">
              Banner is hidden automatically this many days after the product was created.
              <br v-if="bannerConfig.days" />
              <span v-if="bannerConfig.days" class="text-golden-yellow font-medium">{{ bannerEndDate }}</span>
            </p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Message (Arabic)</label>
            <input
              type="text"
              dir="rtl"
              :value="bannerConfig.message_ar"
              @input="updateBannerConfig('message_ar', $event.target.value)"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              placeholder="قريباً"
            />
            <p class="text-[11px] text-muted-foreground">Ribbon text shown to Arabic-speaking visitors.</p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Message (English)</label>
            <input
              type="text"
              :value="bannerConfig.message_en"
              @input="updateBannerConfig('message_en', $event.target.value)"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              placeholder="COMING SOON"
            />
            <p class="text-[11px] text-muted-foreground">Ribbon text shown to English-speaking visitors.</p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Text Color</label>
            <div class="flex items-center gap-2">
              <input
                type="color"
                :value="hex6(bannerConfig.text_color)"
                @input="setColorWithOpacity('text_color', $event.target.value)"
                class="h-9 w-12 rounded-md border border-input cursor-pointer bg-transparent"
              />
              <input
                type="text"
                :value="bannerConfig.text_color"
                @input="updateBannerConfig('text_color', $event.target.value)"
                class="flex h-9 w-28 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none font-mono"
                placeholder="#ffffff"
              />
              <div class="flex items-center gap-1.5 flex-1">
                <span class="text-xs text-muted-foreground whitespace-nowrap">Opacity</span>
                <input
                  type="range"
                  min="0"
                  max="100"
                  :value="hexToOpacity(bannerConfig.text_color)"
                  @input="setOpacity('text_color', $event.target.value)"
                  class="flex-1 h-1.5 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-golden-yellow"
                />
                <span class="text-xs text-muted-foreground w-8 text-right">{{ hexToOpacity(bannerConfig.text_color) }}%</span>
              </div>
            </div>
            <p class="text-[11px] text-muted-foreground mt-1">Color of the ribbon text.</p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Background Color</label>
            <div class="flex items-center gap-2">
              <input
                type="color"
                :value="hex6(bannerConfig.bg_color)"
                @input="setColorWithOpacity('bg_color', $event.target.value)"
                class="h-9 w-12 rounded-md border border-input cursor-pointer bg-transparent"
              />
              <input
                type="text"
                :value="bannerConfig.bg_color"
                @input="updateBannerConfig('bg_color', $event.target.value)"
                class="flex h-9 w-28 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none font-mono"
                placeholder="#dc2626"
              />
              <div class="flex items-center gap-1.5 flex-1">
                <span class="text-xs text-muted-foreground whitespace-nowrap">Opacity</span>
                <input
                  type="range"
                  min="0"
                  max="100"
                  :value="hexToOpacity(bannerConfig.bg_color)"
                  @input="setOpacity('bg_color', $event.target.value)"
                  class="flex-1 h-1.5 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-golden-yellow"
                />
                <span class="text-xs text-muted-foreground w-8 text-right">{{ hexToOpacity(bannerConfig.bg_color) }}%</span>
              </div>
            </div>
            <p class="text-[11px] text-muted-foreground mt-1">Ribbon background — pick something that contrasts with the photo.</p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Font Size (px)</label>
            <input
              type="number"
              :value="bannerConfig.font_size"
              @input="updateBannerConfig('font_size', Number($event.target.value))"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              min="8"
              max="72"
              placeholder="15"
            />
            <p class="text-[11px] text-muted-foreground">Ribbon text size in pixels — 15 fits most screens.</p>
          </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Angle (deg)</label>
            <div class="flex items-center gap-2">
              <input
                type="range"
                min="0"
                max="360"
                :value="bannerConfig.angle"
                @input="updateBannerConfig('angle', Number($event.target.value))"
                class="flex-1 h-1.5 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-golden-yellow"
              />
                <span class="text-xs text-muted-foreground w-8 text-right">{{ bannerConfig.angle }}°</span>
              </div>
              <p class="text-[11px] text-muted-foreground mt-1">Ribbon rotation — 0° is horizontal, 45° is the classic diagonal.</p>
            </div>

          <div class="grid gap-1">
            <label class="block text-sm font-medium mb-1">Shadow Color</label>
            <div class="flex items-center gap-2">
              <input
                type="color"
                :value="hex6(bannerConfig.shadow_color)"
                @input="setColorWithOpacity('shadow_color', $event.target.value)"
                class="h-9 w-12 rounded-md border border-input cursor-pointer bg-transparent"
              />
              <input
                type="text"
                :value="bannerConfig.shadow_color"
                @input="updateBannerConfig('shadow_color', $event.target.value)"
                class="flex h-9 w-28 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none font-mono"
                placeholder="#00000033"
              />
              <div class="flex items-center gap-1.5 flex-1">
                <span class="text-xs text-muted-foreground whitespace-nowrap">Opacity</span>
                <input
                  type="range"
                  min="0"
                  max="100"
                  :value="hexToOpacity(bannerConfig.shadow_color)"
                  @input="setOpacity('shadow_color', $event.target.value)"
                  class="flex-1 h-1.5 bg-gray-600 rounded-lg appearance-none cursor-pointer accent-golden-yellow"
                />
                <span class="text-xs text-muted-foreground w-8 text-right">{{ hexToOpacity(bannerConfig.shadow_color) }}%</span>
              </div>
            </div>
            <p class="text-[11px] text-muted-foreground mt-1">Soft shadow behind the ribbon so it stands out from busy photos.</p>
          </div>

          <div class="grid gap-1 lg:col-span-2">
            <label class="block text-sm font-medium mb-1">Preview</label>
            <div class="relative h-20 rounded-lg border border-border overflow-hidden bg-muted/30">
              <div class="absolute inset-0 flex items-center justify-center">
                <span
                  :style="{
                    backgroundColor: bannerConfig.bg_color,
                    color: bannerConfig.text_color,
                    fontSize: bannerConfig.font_size + 'px',
                    fontWeight: 900,
                    padding: '4px 24px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.1em',
                    transform: 'rotate(-' + bannerConfig.angle + 'deg)',
                    whiteSpace: 'nowrap',
                    boxShadow: '0 4px 12px ' + bannerConfig.shadow_color,
                  }"
                >
                  {{ currentLocale === 'ar' ? bannerConfig.message_ar : bannerConfig.message_en }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
            <circle cx="9" cy="9" r="2"></circle>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
          </svg>
          <span class="text-sm sm:text-base">Images</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">Large Image</label>

            <div v-if="largePreview || showExistingLarge" class="relative inline-block">
              <img
                :src="largePreview || existingLargeImage"
                class="w-32 h-32 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                @click="openLightbox(largePreview || existingLargeImage)"
              />
              <button
                type="button"
                :title="largePreview ? 'Discard selected file' : 'Remove image'"
                @click="largePreview ? clearLargeSelection() : removeExistingLarge()"
                class="absolute -top-2 -right-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-destructive text-white shadow-sm hover:bg-destructive/90"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
              <p class="text-xs text-muted-foreground mt-1">
                {{ largePreview ? 'New image — replaces the current one on save' : 'Current image' }}
              </p>
            </div>

            <div v-else-if="productStore.form.remove_large_image" class="flex items-center gap-2 text-xs text-muted-foreground">
              <span>Image will be deleted on save.</span>
              <button type="button" @click="undoRemoveLarge" class="text-primary underline underline-offset-2">Undo</button>
            </div>

            <input
              ref="largeInput"
              type="file"
              accept="image/*"
              @change="handleLargeImage"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
            />
            <p class="text-[11px] text-muted-foreground">Main product photo on the product page — square, 800×800 px or larger, JPG/PNG.</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">Small Image</label>

            <div v-if="smallPreview || showExistingSmall" class="relative inline-block">
              <img
                :src="smallPreview || existingSmallImage"
                class="w-24 h-24 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                @click="openLightbox(smallPreview || existingSmallImage)"
              />
              <button
                type="button"
                :title="smallPreview ? 'Discard selected file' : 'Remove image'"
                @click="smallPreview ? clearSmallSelection() : removeExistingSmall()"
                class="absolute -top-2 -right-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-destructive text-white shadow-sm hover:bg-destructive/90"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
              <p class="text-xs text-muted-foreground mt-1">
                {{ smallPreview ? 'New image — replaces the current one on save' : 'Current image' }}
              </p>
            </div>

            <div v-else-if="productStore.form.remove_small_image" class="flex items-center gap-2 text-xs text-muted-foreground">
              <span>Image will be deleted on save.</span>
              <button type="button" @click="undoRemoveSmall" class="text-primary underline underline-offset-2">Undo</button>
            </div>

            <input
              ref="smallInput"
              type="file"
              accept="image/*"
              @change="handleSmallImage"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
            />
            <p class="text-[11px] text-muted-foreground">Compact thumbnail used in lists and carts — square, around 300×300 px.</p>
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Gallery</label>

          <div v-if="visibleExistingGallery.length || galleryPreviews.length || editorGalleryImages.length" class="flex flex-wrap gap-3">
            <div v-for="img in visibleExistingGallery" :key="`existing-${img.key}`" class="relative">
              <img :src="img.url" class="w-20 h-20 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90" @click="openLightbox(img.url)" />
              <button
                type="button"
                title="Remove image"
                :disabled="img.id === null"
                @click="removeExistingGalleryImage(img.id)"
                class="absolute -top-2 -right-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-destructive text-white shadow-sm hover:bg-destructive/90 disabled:opacity-40 disabled:pointer-events-none"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
            </div>

            <div v-for="(preview, i) in galleryPreviews" :key="`new-${i}`" class="relative">
              <img :src="preview.url" class="w-20 h-20 rounded-lg border-2 border-dashed border-primary/60 object-cover cursor-zoom-in transition hover:opacity-90" @click="openLightbox(preview.url)" />
              <button
                type="button"
                title="Discard selected file"
                @click="removeNewGalleryFile(i)"
                class="absolute -top-2 -right-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-destructive text-white shadow-sm hover:bg-destructive/90"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
            </div>

            <div v-for="img in editorGalleryImages" :key="`editor-${img.path}`" class="relative">
              <img :src="img.url" class="w-20 h-20 rounded-lg border-2 border-dashed border-primary/60 object-cover cursor-zoom-in transition hover:opacity-90" @click="openLightbox(img.url)" />
              <span class="absolute -top-2 -left-2 rounded-full bg-primary text-primary-foreground text-[10px] px-1.5 py-0.5 shadow-sm" title="Uploaded from the description editor">desc</span>
            </div>
          </div>

          <p v-if="editorGalleryImages.length" class="text-xs text-muted-foreground">
            {{ editorGalleryImages.length }} image(s) added from the description editor will be saved to the gallery.
          </p>

          <p v-if="removedGalleryCount" class="flex items-center gap-2 text-xs text-muted-foreground">
            <span>{{ removedGalleryCount }} image(s) will be deleted on save.</span>
            <button type="button" @click="undoGalleryRemovals" class="text-primary underline underline-offset-2">Undo</button>
          </p>

          <input
            ref="galleryInput"
            type="file"
            accept="image/*"
            multiple
            @change="handleGallery"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
          />
          <p class="text-xs text-muted-foreground">New files are added to the gallery — existing images stay unless you remove them.</p>
        </div>
      </div>
    </div>

    </template>

    <template v-else-if="activeTab === 'seo'">
      <ProductSeoCard :slug="slug" />
    </template>

    <ImageLightbox :images="formImages" v-model:index="lightboxIndex" />

    <ValidationErrorsDialog
      v-model:open="showValidationDialog"
      :errors="productStore.validationErrors || {}"
      :labels="errorLabels"
      @select="goToErrorField"
    />
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormTranslatableQuillEditor } from "@/Components/form";
import ImageLightbox from "@/Components/ui/ImageLightbox.vue";
import ValidationErrorsDialog from "@/Components/ui/ValidationErrorsDialog.vue";
import TabBar from "@/Components/ui/TabBar.vue";
import ProductSeoCard from "./ProductSeoCard.vue";
import { useProductStore } from "../../Stores/ProductStore";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  productTypes: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  existingLargeImage: { type: String, default: null },
  existingSmallImage: { type: String, default: null },
  existingGallery: { type: Array, default: () => [] },
  slug: { type: String, default: '' },
});

const productStore = useProductStore();
const { form } = storeToRefs(productStore);

// What each storefront switch does, in the words an admin needs rather than
// the column names. Order matters: it reads as a funnel — found, opened, bought.
const availabilityOptions = [
  {
    key: 'is_visible',
    label: 'Visible in the shop',
    hint: 'Off: it disappears from the product listing, search and related rails. A direct link still works.',
  },
  {
    key: 'is_accessible',
    label: 'Product page can be opened',
    hint: 'Off: its own page returns "not found", and its card in the listing stops being a link.',
  },
  {
    key: 'is_purchasable',
    label: 'Can be bought',
    hint: 'Off: no add-to-basket button, and any order still carrying it is refused.',
  },
];

// --- Tabs ----------------------------------------------------------------

const activeTab = ref('general');

// Which tab owns which field — drives the tab error badges and the jump from
// a row of the validation dialog.
const TAB_FIELDS = {
  general: ['name', 'short_subject', 'description', 'old_price', 'new_price', 'cost_price', 'profit_price', 'product_type_id', 'is_visible', 'is_accessible', 'is_purchasable', 'admin_note', 'tag_ids'],
  media: ['large_image', 'small_image', 'gallery', 'banner_config', 'editor_gallery_paths'],
  seo: ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url'],
};

// `name.ar` belongs to the `name` field.
const matchesField = (key, field) => key === field || key.startsWith(`${field}.`);

const hasAnyError = (fields) => {
  const errors = productStore.validationErrors || {};
  return Object.keys(errors).some((key) => fields.some((field) => matchesField(key, field)));
};

const formTabs = computed(() => [
  { key: 'general', label: 'General', hasError: hasAnyError(TAB_FIELDS.general) },
  { key: 'media', label: 'Images & Banner', hasError: hasAnyError(TAB_FIELDS.media) },
  { key: 'seo', label: 'SEO', hasError: hasAnyError(TAB_FIELDS.seo) },
]);

const showValidationDialog = ref(false);

const errorLabels = {
  name: 'Name',
  short_subject: 'Short description',
  description: 'Description',
  new_price: 'New price',
  old_price: 'Old price',
  cost_price: 'Cost price',
  profit_price: 'Profit price',
  product_type_id: 'Product type',
  is_visible: 'Visible in the shop',
  is_accessible: 'Product page can be opened',
  is_purchasable: 'Can be bought',
  admin_note: 'Admin note',
  tag_ids: 'Tags',
  large_image: 'Large image',
  small_image: 'Small image',
  gallery: 'Gallery',
  editor_gallery_paths: 'Description images',
  banner_config: 'Banner',
  meta_title: 'Meta title',
  meta_description: 'Meta description',
  meta_keywords: 'Meta keywords',
  canonical_url: 'Canonical URL',
};

const goToErrorField = (key) => {
  const entry = Object.entries(TAB_FIELDS).find(([, fields]) => fields.some((field) => matchesField(key, field)));
  if (entry) activeTab.value = entry[0];
};

// A failed submit puts the errors on screen twice over: the tab badge plus the
// inline message can both sit out of view, so the full list opens as a dialog.
watch(() => productStore.validationErrors, (errors) => {
  if (!errors || Object.keys(errors).length === 0) {
    showValidationDialog.value = false;
    return;
  }
  const failing = formTabs.value.find((tab) => tab.hasError);
  if (failing) activeTab.value = failing.key;
  showValidationDialog.value = true;
});

const currentLocale = usePage().props.locale || 'ar';
const otherLocale = currentLocale === 'ar' ? 'en' : 'ar';

// Both names travel with every tag; fall back to the single localized
// `name` for older payloads that have no translation map.
const tagNameIn = (tag, lang) => {
  const map = tag.name_translations;
  if (map && typeof map === 'object') return map[lang] || '';
  return lang === currentLocale ? (typeof tag.name === 'string' ? tag.name : '') : '';
};

const productTagPrimary = (tag) =>
  tagNameIn(tag, currentLocale) || tagNameIn(tag, otherLocale) || (typeof tag.name === 'string' ? tag.name : '');

const productTagOther = (tag) => {
  const other = tagNameIn(tag, otherLocale);
  return other && other !== tagNameIn(tag, currentLocale) ? other : '';
};

// Selected chips wear the solid tag color; unselected ones keep a readable
// foreground label with the color carried by the dot and border instead of
// dim colored text that disappears against the card background.
const productTagStyle = (tag, selected) => {
  const color = tag.color || '#6B7280';
  if (selected) {
    return {
      backgroundColor: color,
      borderColor: color,
      color: '#fff',
      boxShadow: `0 0 0 3px ${color}59`,
    };
  }
  return {
    backgroundColor: `${color}1F`,
    borderColor: `${color}80`,
  };
};

// Banner: one JSON blob on the product, same shape as the facility banner.
const DEFAULT_BANNER = {
  enabled: false,
  message_ar: 'قريباً',
  message_en: 'COMING SOON',
  text_color: '#ffffff',
  bg_color: '#dc2626ff',
  font_size: 15,
  angle: 45,
  shadow_color: '#00000033',
  days: 30,
};

const bannerConfig = computed(() => {
  const cfg = form.value.banner_config;
  if (!cfg || typeof cfg !== 'object') return { ...DEFAULT_BANNER };
  return { ...DEFAULT_BANNER, ...cfg };
});

const bannerEnabled = computed(() => Boolean(bannerConfig.value.enabled));

const bannerEndDate = computed(() => {
  const days = bannerConfig.value.days;
  if (!days || days <= 0) return '';
  const end = new Date();
  end.setDate(end.getDate() + days);
  return end.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
});

const toggleBanner = (e) => {
  form.value.banner_config = { ...bannerConfig.value, enabled: e.target.checked };
};

const updateBannerConfig = (key, value) => {
  form.value.banner_config = { ...bannerConfig.value, [key]: value };
};

// Hex opacity helpers: colors are stored as 8-digit hex (#rrggbbaa)
const hex6 = (color) => {
  if (!color || typeof color !== 'string') return '#000000';
  const clean = color.replace('#', '');
  if (clean.length === 8) return '#' + clean.substring(0, 6);
  if (clean.length === 6) return '#' + clean;
  return '#000000';
};

const hexToOpacity = (color) => {
  if (!color || typeof color !== 'string') return 100;
  const clean = color.replace('#', '');
  if (clean.length === 8) {
    return Math.round((parseInt(clean.substring(6, 8), 16) / 255) * 100);
  }
  return 100;
};

const opacityToHex = (opacity) => {
  const val = Math.round(Math.min(100, Math.max(0, Number(opacity))) * 2.55);
  return val.toString(16).padStart(2, '0');
};

const setColorWithOpacity = (key, hex6Value) => {
  const opacity = hexToOpacity(bannerConfig.value[key]);
  const clean = hex6Value.replace('#', '').substring(0, 6);
  form.value.banner_config = { ...bannerConfig.value, [key]: '#' + clean + opacityToHex(opacity) };
};

const setOpacity = (key, opacity) => {
  const rgb = hex6(bannerConfig.value[key]).replace('#', '');
  form.value.banner_config = { ...bannerConfig.value, [key]: '#' + rgb + opacityToHex(opacity) };
};

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) return {};
    return name;
  },
  set: (value) => { form.value.name = value; }
});

const formShortSubject = computed({
  get: () => {
    const val = form.value.short_subject;
    if (!val || typeof val !== 'object' || Array.isArray(val)) return {};
    return val;
  },
  set: (value) => { form.value.short_subject = value; }
});

/* Errors arrive keyed per locale (`name.ar`) from both the client validator and
   Laravel, but the translatable inputs read an { ar, en } object — without this
   regrouping the message has nowhere to render and the field looks fine. */
const translatableError = (field) => {
  const errors = productStore.validationErrors || {};
  const first = (value) => (Array.isArray(value) ? value[0] : value) || null;

  const general = first(errors[field]);
  const ar = first(errors[`${field}.ar`]);
  const en = first(errors[`${field}.en`]);

  if (!general && !ar && !en) return null;
  return { ar: ar || general || null, en: en || general || null };
};

const nameError = computed(() => translatableError('name'));
const shortSubjectError = computed(() => translatableError('short_subject'));
const descriptionError = computed(() => translatableError('description'));

/** Single-value fields: first message for the key, as a string. */
const fieldError = (key) => {
  const value = productStore.validationErrors?.[key];
  return (Array.isArray(value) ? value[0] : value) || null;
};

// The Quill editor works on a plain { ar, en } object; normalize whatever
// shape the backend/store currently holds before handing it over.
const formDescription = computed({
  get: () => {
    const val = form.value.description;
    if (typeof val === 'string') {
      try { return JSON.parse(val) || {}; } catch { return {}; }
    }
    if (!val || Array.isArray(val)) return {};
    return val;
  },
  set: (value) => { form.value.description = value; }
});

const adminNoteError = computed(() =>
  productStore.validationErrors?.['admin_note']
);

const adminNoteErrorClass = computed(() => (adminNoteError.value
  ? 'border-destructive focus:border-destructive focus:ring-destructive/20'
  : ''));

// Object URLs for files picked in this session; existing images come from props.
const largePreview = ref(null);
const smallPreview = ref(null);
const galleryPreviews = ref([]);

const largeInput = ref(null);
const smallInput = ref(null);
const galleryInput = ref(null);

const showExistingLarge = computed(() => Boolean(props.existingLargeImage) && !productStore.form.remove_large_image);
const showExistingSmall = computed(() => Boolean(props.existingSmallImage) && !productStore.form.remove_small_image);

// Gallery items arrive as { id, url }; tolerate a plain url string too.
const existingGalleryItems = computed(() => (props.existingGallery || []).map((img, i) => (
  typeof img === 'string'
    ? { id: null, url: img, key: `url-${i}` }
    : { id: img?.id ?? null, url: img?.url ?? '', key: img?.id ?? `url-${i}` }
)));

const visibleExistingGallery = computed(() => existingGalleryItems.value
  .filter((img) => !productStore.form.removed_gallery_ids.includes(img.id)));

const removedGalleryCount = computed(() => productStore.form.removed_gallery_ids.length);

// Images uploaded from inside the description editor. The file is already on
// disk (the editor needs a URL right away); the gallery row is created on save.
const editorGalleryImages = ref([]);

const uploadEditorImage = async (file) => {
  const data = new FormData();
  data.append('image', file);

  try {
    const { data: uploaded } = await window.axios.post(route('admin.product.editor-image'), data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (!productStore.form.editor_gallery_paths.includes(uploaded.path)) {
      productStore.form.editor_gallery_paths.push(uploaded.path);
      editorGalleryImages.value.push({ path: uploaded.path, url: uploaded.url });
    }

    return uploaded.url;
  } catch (error) {
    const message = error?.response?.data?.errors?.image?.[0] || 'Failed to upload the image';
    useNotification().error(message);
    return null;
  }
};

// Every picture the form currently shows, in display order — one shared
// lightbox walks them all, exactly like on the product show page.
const formImages = computed(() => {
  const name = getTranslatedName(form.value.name);
  const images = [];

  const large = largePreview.value || (showExistingLarge.value ? props.existingLargeImage : null);
  if (large) images.push({ url: large, alt: `${name} — large image` });

  const small = smallPreview.value || (showExistingSmall.value ? props.existingSmallImage : null);
  if (small) images.push({ url: small, alt: `${name} — small image` });

  visibleExistingGallery.value.forEach((img, i) => {
    if (img.url) images.push({ url: img.url, alt: `${name} — gallery ${i + 1}` });
  });

  galleryPreviews.value.forEach((preview, i) => {
    if (preview.url) images.push({ url: preview.url, alt: `${name} — new gallery ${i + 1}` });
  });

  editorGalleryImages.value.forEach((img, i) => {
    if (img.url) images.push({ url: img.url, alt: `${name} — description image ${i + 1}` });
  });

  return images;
});

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = formImages.value.findIndex((img) => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};

const setPreview = (target, file) => {
  if (target.value) URL.revokeObjectURL(target.value);
  target.value = file ? URL.createObjectURL(file) : null;
};

const handleLargeImage = (e) => {
  const file = e.target.files?.[0] || null;
  setPreview(largePreview, file);
  productStore.form.large_image = file;
  if (file) productStore.form.remove_large_image = false;
};

const clearLargeSelection = () => {
  setPreview(largePreview, null);
  productStore.form.large_image = null;
  if (largeInput.value) largeInput.value.value = '';
};

const removeExistingLarge = () => {
  clearLargeSelection();
  productStore.form.remove_large_image = true;
};

const undoRemoveLarge = () => {
  productStore.form.remove_large_image = false;
};

const handleSmallImage = (e) => {
  const file = e.target.files?.[0] || null;
  setPreview(smallPreview, file);
  productStore.form.small_image = file;
  if (file) productStore.form.remove_small_image = false;
};

const clearSmallSelection = () => {
  setPreview(smallPreview, null);
  productStore.form.small_image = null;
  if (smallInput.value) smallInput.value.value = '';
};

const removeExistingSmall = () => {
  clearSmallSelection();
  productStore.form.remove_small_image = true;
};

const undoRemoveSmall = () => {
  productStore.form.remove_small_image = false;
};

const syncGalleryFiles = () => {
  productStore.form.gallery = galleryPreviews.value.map((preview) => preview.file);
};

// Picking files adds to the pending list instead of replacing it.
const handleGallery = (e) => {
  Array.from(e.target.files || []).forEach((file) => {
    galleryPreviews.value.push({ file, url: URL.createObjectURL(file) });
  });
  syncGalleryFiles();
  if (galleryInput.value) galleryInput.value.value = '';
};

const removeNewGalleryFile = (index) => {
  const [removed] = galleryPreviews.value.splice(index, 1);
  if (removed) URL.revokeObjectURL(removed.url);
  syncGalleryFiles();
};

const removeExistingGalleryImage = (id) => {
  if (id === null) return;
  if (!productStore.form.removed_gallery_ids.includes(id)) {
    productStore.form.removed_gallery_ids.push(id);
  }
};

const undoGalleryRemovals = () => {
  productStore.form.removed_gallery_ids = [];
};

const revokeAllPreviews = () => {
  setPreview(largePreview, null);
  setPreview(smallPreview, null);
  galleryPreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
  galleryPreviews.value = [];
};

// A different product loaded into the same form: start from a clean slate.
watch(() => [props.existingLargeImage, props.existingSmallImage], () => {
  clearLargeSelection();
  clearSmallSelection();
  galleryPreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
  galleryPreviews.value = [];
  editorGalleryImages.value = [];
  syncGalleryFiles();
});

onBeforeUnmount(revokeAllPreviews);
</script>
