<template>
  <div class="space-y-3">
    <!-- Facility Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building title-icon">
            <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
            <path d="M9 22v-4h6v4"></path>
            <path d="M8 6h.01"></path>
            <path d="M16 6h.01"></path>
            <path d="M12 6h.01"></path>
            <path d="M12 10h.01"></path>
            <path d="M12 14h.01"></path>
            <path d="M16 10h.01"></path>
            <path d="M16 14h.01"></path>
            <path d="M8 10h.01"></path>
            <path d="M8 14h.01"></path>
          </svg>
          {{ t.facility?.information || 'Facility Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formName"
              :label="t.common?.name || 'Name'"
              :error="facilityStore.validationErrors?.name"
              :placeholder="t.facility?.name_placeholder || 'Enter facility name'"
              required
              :locales="['ar', 'en']"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="facilityStore.form.facility_type_id"
              :label="t.facility_type?.label || 'Facility Type'"
              :options="facilityTypeOptions"
              :error="facilityStore.validationErrors?.facility_type_id"
              required
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormSearchableSelect
              v-model="facilityStore.form.sales_id"
              :label="t.sales?.name || 'Sales'"
              :options="salesSelectOptions"
              :error="facilityStore.validationErrors?.sales_id"
              :placeholder="t.facility?.sales_placeholder || 'Select sales (optional)'"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formDiscountPercent"
              :label="t.facility?.discount_percent || 'Discount %'"
              :error="facilityStore.validationErrors?.discount_percent"
              :placeholder="t.facility?.discount_percent_placeholder || 'Optional'"
              type="number"
              step="0.01"
              min="0"
              max="100"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1 lg:col-span-2">
            <FormTranslatableQuillEditor
              v-model="formDescription"
              :label="t.facility?.description || 'Description'"
              :error="facilityStore.validationErrors?.description"
              :locales="['ar', 'en']"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Banner Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          {{ t.facility?.banner || 'Banner' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Enable Toggle -->
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
          <span class="text-sm font-medium text-white">{{ t.facility?.enable_banner || 'Enable Banner' }}</span>
        </div>

        <!-- Config fields (shown when enabled) -->
        <div v-if="bannerEnabled" class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_days || 'Number of Days' }}
            </label>
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
              {{ t.facility?.banner_days_hint || 'Banner will be hidden automatically after this many days from today.' }}
              <br v-if="bannerConfig.days" />
              <span v-if="bannerConfig.days" class="text-golden-yellow font-medium">
                {{ bannerEndDate }}
              </span>
            </p>
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_ar || 'Message (Arabic)' }}
            </label>
            <input
              type="text"
              :value="bannerConfig.message_ar"
              @input="updateBannerConfig('message_ar', $event.target.value)"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              placeholder="قريباً"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_en || 'Message (English)' }}
            </label>
            <input
              type="text"
              :value="bannerConfig.message_en"
              @input="updateBannerConfig('message_en', $event.target.value)"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              placeholder="COMING SOON"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_text_color || 'Text Color' }}
            </label>
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
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_bg_color || 'Background Color' }}
            </label>
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
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_font_size || 'Font Size (px)' }}
            </label>
            <input
              type="number"
              :value="bannerConfig.font_size"
              @input="updateBannerConfig('font_size', Number($event.target.value))"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
              min="8"
              max="72"
              placeholder="15"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_angle || 'Angle (deg)' }}
            </label>
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
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_shadow_color || 'Shadow Color' }}
            </label>
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
          </div>

          <!-- Live Preview -->
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-1">
              {{ t.facility?.banner_preview || 'Preview' }}
            </label>
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

    <!-- Tags Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path>
            <path d="M7 7h.01"></path>
          </svg>
          {{ t.service?.tags || 'Tags' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6">
        <div>
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.tags || 'Tags' }}
            <span v-if="facilityStore.validationErrors?.tag_ids" class="text-destructive ml-2 text-xs">{{ facilityStore.validationErrors.tag_ids }}</span>
          </label>
          <div v-if="tags.length > 0" class="flex flex-wrap gap-2">
            <button
              v-for="tagItem in tags"
              :key="tagItem.id"
              type="button"
              @click="toggleTag(tagItem.id)"
              class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-opacity cursor-pointer"
              :style="tagChipStyle(tagItem, isTagSelected(tagItem.id))"
            >
              <span v-if="tagItem.icon">{{ tagItem.icon }}</span>
              {{ tagItem.name }}
            </button>
          </div>
          <p v-else class="text-xs text-muted-foreground">
            {{ t.service?.no_tags || 'No tags exist yet.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Logo & Cover Image Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
{{ t.facility?.images || 'Images' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <!-- Logo Column -->
          <div class="space-y-4">
            <!-- Logo -->
            <div data-slot="form-item" class="grid gap-1">
<label class="block text-sm font-medium text-white mb-2">
                {{ t.facility?.logo || 'Logo' }}
                <span class="text-xs text-muted-foreground ml-2">{{ t.facility?.optional_max_size || '(Optional - Max 5MB)' }}</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="props.facility?.logo || ''"
                @file-selected="(f) => { facilityStore.form.logo = f; logoError = ''; }"
                @error="(err) => logoError = err"
              />
              <p v-if="facilityStore.validationErrors?.logo || logoError" class="mt-1 text-sm text-destructive">
                {{ facilityStore.validationErrors?.logo || logoError }}
              </p>
            </div>
            <!-- Mobile Logo -->
            <div data-slot="form-item" class="grid gap-1">
<label class="block text-sm font-medium text-white mb-2">
                {{ t.facility?.mobile_logo || 'Mobile Logo' }}
                <span class="text-xs text-muted-foreground ml-2">{{ t.facility?.optional_max_size || '(Optional - Max 5MB)' }}</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="props.facility?.mobile_logo || ''"
                @file-selected="(f) => { facilityStore.form.mobile_logo = f; mobileLogoError = ''; }"
                @error="(err) => mobileLogoError = err"
              />
              <p v-if="facilityStore.validationErrors?.mobile_logo || mobileLogoError" class="mt-1 text-sm text-destructive">
                {{ facilityStore.validationErrors?.mobile_logo || mobileLogoError }}
              </p>
            </div>
          </div>

          <!-- Cover Image Column -->
          <div class="space-y-4">
            <!-- Cover Image -->
            <div data-slot="form-item" class="grid gap-1">
<label class="block text-sm font-medium text-white mb-2">
                {{ t.facility?.cover_image || 'Cover Image' }}
                <span class="text-xs text-muted-foreground ml-2">{{ t.facility?.optional_max_size || '(Optional - Max 5MB)' }}</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="props.facility?.image || ''"
                @file-selected="(f) => { facilityStore.form.image = f; imageError = ''; }"
                @error="(err) => imageError = err"
              />
              <p v-if="facilityStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
                {{ facilityStore.validationErrors?.image || imageError }}
              </p>
            </div>
            <!-- Mobile Cover Image -->
            <div data-slot="form-item" class="grid gap-1">
<label class="block text-sm font-medium text-white mb-2">
                {{ t.facility?.mobile_image || 'Mobile Cover Image' }}
                <span class="text-xs text-muted-foreground ml-2">{{ t.facility?.optional_max_size || '(Optional - Max 5MB)' }}</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="props.facility?.mobile_image || ''"
                @file-selected="(f) => { facilityStore.form.mobile_image = f; mobileImageError = ''; }"
                @error="(err) => mobileImageError = err"
              />
              <p v-if="facilityStore.validationErrors?.mobile_image || mobileImageError" class="mt-1 text-sm text-destructive">
                {{ facilityStore.validationErrors?.mobile_image || mobileImageError }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Gallery Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="7" height="7" x="3" y="3" rx="1"/>
            <rect width="7" height="7" x="14" y="3" rx="1"/>
            <rect width="7" height="7" x="14" y="14" rx="1"/>
            <rect width="7" height="7" x="3" y="14" rx="1"/>
          </svg>
{{ t.facility?.gallery || 'Gallery' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">

        <!-- Existing gallery thumbnails (edit mode) -->
        <div v-if="existingGallery.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div
            v-for="item in existingGallery"
            :key="item.id"
            class="relative group aspect-square"
          >
            <img
              :src="item.url"
              :alt="t.facility?.gallery_image || 'Gallery image'"
              class="w-full h-full object-cover rounded-lg border border-border"
            />
            <button
              type="button"
              class="absolute top-1 right-1 bg-destructive text-destructive-foreground rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-sm font-bold leading-none"
              @click="removeExistingGalleryItem(item.id)"
              :title="t.facility?.remove || 'Remove'"
            >
              ×
            </button>
          </div>
        </div>

        <!-- New image previews -->
        <div v-if="galleryNewPreviews.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div
            v-for="(preview, index) in galleryNewPreviews"
            :key="index"
            class="relative group aspect-square"
          >
            <img
              :src="preview.url"
              :alt="t.facility?.gallery_new_image || 'New gallery image'"
              class="w-full h-full object-cover rounded-lg border border-ring"
            />
            <button
              type="button"
              class="absolute top-1 right-1 bg-destructive text-destructive-foreground rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-sm font-bold leading-none"
              @click="removeNewGalleryItem(index)"
              :title="t.facility?.remove || 'Remove'"
            >
              ×
            </button>
          </div>
        </div>

        <!-- Empty state -->
        <div
          v-if="existingGallery.length === 0 && galleryNewPreviews.length === 0"
          class="text-sm text-muted-foreground"
        >
          {{ t.facility?.no_gallery_images || 'No gallery images yet.' }}
        </div>

        <!-- Add images button -->
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md border border-dashed border-border px-4 py-2 text-sm text-muted-foreground hover:text-foreground hover:border-foreground transition-colors"
          @click="galleryInput.click()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ t.facility?.add_images || 'Add Images' }}
        </button>
        <input
          type="file"
          ref="galleryInput"
          multiple
          accept="image/*"
          class="hidden"
          @change="handleGalleryChange"
        />

        <p v-if="facilityStore.validationErrors?.['gallery.0']" class="text-sm text-destructive">
          {{ facilityStore.validationErrors['gallery.0'] }}
        </p>
      </div>
    </div>
    <!-- Contract Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <path d="M9 13h6"></path>
            <path d="M9 17h3"></path>
          </svg>
          {{ t.facility?.contract || 'Contract' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6">
        <p v-if="t.facility?.contract_help" class="text-xs text-muted-foreground mb-3">{{ t.facility.contract_help }}</p>
        <ContractFileInput
          :max-size="10"
          :initial-contract="props.facility?.contract || null"
          @file-selected="(f) => { facilityStore.form.contract = f; facilityStore.form.contract_delete = false; contractError = ''; }"
          @existing-removed="() => { facilityStore.form.contract_delete = true; }"
          @error="(err) => contractError = err"
        />
        <p v-if="facilityStore.validationErrors?.contract || contractError" class="mt-1 text-sm text-destructive">
          {{ facilityStore.validationErrors?.contract || contractError }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormTranslatableQuillEditor, FormSelect, FormSearchableSelect, FormInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import ContractFileInput from "@/Components/form/ContractFileInput.vue";
import { useFacilityStore } from "../../Stores/FacilityStore";
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  facilityTypes: {
    type: Array,
    default: () => []
  },
  facility: {
    type: Object,
    default: () => null
  },
  tags: {
    type: Array,
    default: () => []
  },
  // Already shaped as { value, label } by the controller.
  salesOptions: {
    type: Array,
    default: () => []
  }
});

const facilityStore = useFacilityStore();
const { form } = storeToRefs(facilityStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const currentLocale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

// Image error states
const logoError = ref('');
const mobileLogoError = ref('');
const imageError = ref('');
const mobileImageError = ref('');
const contractError = ref('');

console.log('[FacilityForm] props.facility:', {
  mobile_logo: props.facility?.mobile_logo,
  mobile_image: props.facility?.mobile_image,
  logo: props.facility?.logo,
  image: props.facility?.image,
});

// Gallery
const galleryInput = ref(null);
const galleryNewPreviews = ref([]);

// Translatable name computed
const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) return {};
    return name;
  },
  set: (value) => {
    form.value.name = value;
  }
});

const formDiscountPercent = computed({
  get: () => form.value.discount_percent,
  set: (value) => { form.value.discount_percent = value === '' || value === null ? null : value; },
});

const formDescription = computed({
  get: () => {
    const desc = form.value.description;
    if (!desc || typeof desc !== 'object' || Array.isArray(desc)) return {};
    return desc;
  },
  set: (value) => {
    form.value.description = value;
  }
});

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
  const current = bannerConfig.value;
  form.value.banner_config = { ...current, enabled: e.target.checked };
};

const updateBannerConfig = (key, value) => {
  const current = bannerConfig.value;
  form.value.banner_config = { ...current, [key]: value };
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
  const current = bannerConfig.value;
  const opacity = hexToOpacity(current[key]);
  const clean = hex6Value.replace('#', '').substring(0, 6);
  form.value.banner_config = { ...current, [key]: '#' + clean + opacityToHex(opacity) };
};

const setOpacity = (key, opacity) => {
  const current = bannerConfig.value;
  const rgb = hex6(current[key]).replace('#', '');
  form.value.banner_config = { ...current, [key]: '#' + rgb + opacityToHex(opacity) };
};

// Existing gallery filtered by pending deletions
const existingGallery = computed(() => {
  return (props.facility?.gallery || []).filter(
    item => !facilityStore.form.gallery_delete.includes(item.id)
  );
});

const removeExistingGalleryItem = (id) => {
  facilityStore.form.gallery_delete.push(id);
};

const removeNewGalleryItem = (index) => {
  galleryNewPreviews.value.splice(index, 1);
  facilityStore.form.gallery.splice(index, 1);
};

// After a save the store empties form.gallery; drop the local previews too so the
// just-uploaded images don't render twice alongside the refreshed existing gallery.
watch(() => facilityStore.form.gallery.length, (count) => {
  if (count === 0) galleryNewPreviews.value = [];
});

const handleGalleryChange = (event) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    facilityStore.form.gallery.push(file);
    const reader = new FileReader();
    reader.onload = (e) => {
      galleryNewPreviews.value.push({ url: e.target.result });
    };
    reader.readAsDataURL(file);
  });
  event.target.value = '';
};

const getTranslatedName = (name, currentLocale) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[currentLocale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const facilityTypeOptions = computed(() => {
  const currentLocale = locale.value;
  return props.facilityTypes.map(type => ({
    value: type.id,
    label: getTranslatedName(type.name, currentLocale)
  }));
});

const salesSelectOptions = computed(() =>
  props.salesOptions.map(option => ({
    value: option.value,
    label: getTranslatedName(option.label, locale.value),
  }))
);

const isTagSelected = (id) => (facilityStore.form.tag_ids || []).includes(id);

const toggleTag = (id) => {
  if (!facilityStore.form.tag_ids) facilityStore.form.tag_ids = [];
  const idx = facilityStore.form.tag_ids.indexOf(id);
  if (idx === -1) {
    facilityStore.form.tag_ids.push(id);
  } else {
    facilityStore.form.tag_ids.splice(idx, 1);
  }
};

const tagChipStyle = (tagItem, selected) => {
  const color = tagItem.color || '#6B7280';
  if (selected) {
    return {
      backgroundColor: color,
      color: '#fff',
      borderColor: color,
    };
  }
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
};
</script>

<style lang="scss" scoped></style>
