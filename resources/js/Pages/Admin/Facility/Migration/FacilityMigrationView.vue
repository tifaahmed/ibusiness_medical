<template>
  <FacilityLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Facility Migration"
        :breadcrumbs="[
          { label: 'Facilities', link: route('admin.facility.list'), active: false },
          { label: 'Migration', link: '#', active: true },
        ]"
      />

      <div class="bg-card border border-border rounded-xl p-4 text-sm text-muted-foreground">
        Move facilities between sites without losing anything: every column in every language,
        branches, tags, offers and the image files themselves travel in one package.
        Large sites are handled in <strong class="text-foreground">parts</strong> — export a few
        facilities at a time, and import them step by step so the browser never has to hold the
        whole job in one request.
        <div class="mt-2 flex flex-wrap gap-3 text-xs">
          <span class="px-2 py-1 rounded bg-muted">{{ summary.facilities }} facilities</span>
          <span class="px-2 py-1 rounded bg-muted">{{ summary.branches }} branches</span>
          <span class="px-2 py-1 rounded bg-muted">{{ summary.media }} images</span>
          <span class="px-2 py-1 rounded bg-muted">upload limit: {{ summary.upload_max }} / post: {{ summary.post_max }}</span>
        </div>
      </div>

      <div class="flex gap-2 border-b border-border">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          @click="activeTab = tab.key"
          :class="[
            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
            activeTab === tab.key
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ============================ EXPORT ============================ -->
      <div v-show="activeTab === 'export'" class="bg-card border border-border rounded-xl p-4 sm:p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold">Build a migration package</h2>
          <p class="text-sm text-muted-foreground mt-1">
            Produces a .zip holding the data and the image files. Import it on the other site.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Search name or slug</span>
            <input v-model="exportFilters.search" type="text" placeholder="All facilities" :class="inputCls" />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Facility type</span>
            <select v-model="exportFilters.facility_type_id" :class="inputCls">
              <option value="">All types</option>
              <option v-for="o in facilityTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Governorate</span>
            <select v-model="exportFilters.governorate_id" :class="inputCls">
              <option value="">All governorates</option>
              <option v-for="o in governorates" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Sales rep</span>
            <select v-model="exportFilters.sales_id" :class="inputCls">
              <option value="">All</option>
              <option v-for="o in salesOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Created from</span>
            <input v-model="exportFilters.created_from" type="date" :class="inputCls" />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Created to</span>
            <input v-model="exportFilters.created_to" type="date" :class="inputCls" />
          </label>
        </div>

        <div class="flex flex-wrap items-center gap-5 text-sm">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="includeMedia" />
            Include image files
            <span class="text-xs text-muted-foreground">(off = data only, much smaller)</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="includeOffers" />
            Include offers
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="splitParts" />
            Split into parts
          </label>
          <label v-if="splitParts" class="flex items-center gap-2">
            <span class="text-xs text-muted-foreground">facilities per part</span>
            <input v-model.number="perPart" type="number" min="1" class="w-24 px-2 py-1 rounded border border-input bg-background text-sm" />
          </label>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" @click="loadPlan" :disabled="planLoading" :class="btnSecondary">
            {{ planLoading ? 'Counting…' : 'Check what matches' }}
          </button>
          <a v-if="!splitParts" :href="exportUrl()" :class="btnPrimary">Download package</a>
        </div>

        <div v-if="plan" class="rounded-lg border border-border p-4 space-y-3">
          <p class="text-sm">
            <strong>{{ plan.total }}</strong> facilit{{ plan.total === 1 ? 'y' : 'ies' }} match.
            <template v-if="splitParts">
              That is <strong>{{ plan.parts }}</strong> part{{ plan.parts === 1 ? '' : 's' }}
              of {{ plan.per_part }}.
            </template>
          </p>
          <div v-if="splitParts && plan.parts" class="flex flex-wrap gap-2">
            <a
              v-for="n in plan.parts"
              :key="n"
              :href="exportUrl(n)"
              :class="[btnSecondary, downloadedParts.includes(n) ? 'opacity-60' : '']"
              @click="downloadedParts.push(n)"
            >
              Part {{ n }} / {{ plan.parts }}
            </a>
          </div>
          <p v-if="splitParts && plan.parts" class="text-xs text-muted-foreground">
            Download the parts one at a time. Each is a complete package on its own — import them
            in any order on the other site using <strong>merge</strong> mode.
          </p>
        </div>

        <div v-if="plan === null && planError" class="text-sm text-destructive">{{ planError }}</div>
      </div>

      <!-- ============================ IMPORT ============================ -->
      <div v-show="activeTab === 'import'" class="bg-card border border-border rounded-xl p-4 sm:p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold">Restore a migration package</h2>
          <p class="text-sm text-muted-foreground mt-1">
            Upload a .zip built by the other site — or, if it is too big to upload, drop it into
            <code class="px-1 rounded bg-muted">storage/app/facility-migration/</code> over FTP and
            give the filename below.
          </p>
        </div>

        <!-- Template Downloads -->
        <div class="flex flex-wrap gap-3">
          <a
            :href="route('admin.facility.migration.template.example')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
            Download Example & Instructions
          </a>
          <a
            :href="route('admin.facility.migration.template.blank')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="12" x2="12" y1="18" y2="12"/><line x1="9" x2="15" y1="15" y2="15"/></svg>
            Download Empty Template
          </a>
          <a
            :href="route('admin.facility.migration.template.zip.example')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
            Download Example ZIP
          </a>
          <a
            :href="route('admin.facility.migration.template.zip.blank')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="12" x2="12" y1="18" y2="12"/><line x1="9" x2="15" y1="15" y2="15"/></svg>
            Download Empty ZIP
          </a>
        </div>

        <!-- Step 1: choose a package -->
        <div v-if="importStep === 'choose'" class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Upload package (.zip, .xlsx, .xls, .csv)</span>
              <input type="file" accept=".zip,.json,.xlsx,.xls,.csv" @change="onPackageChange" :class="inputCls" />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">…or filename already on the server</span>
              <input v-model="serverPath" type="text" placeholder="facility-migration-full-2026-01-01_120000.zip" :class="inputCls" />
            </label>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Mode</span>
              <select v-model="importMode" :class="inputCls">
                <option value="merge">Merge — update by slug, insert what is missing</option>
                <option value="fresh">Fresh — delete everything first</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Facilities per step</span>
              <input v-model.number="chunkSize" type="number" min="1" max="200" :class="inputCls" />
            </label>
            <label class="flex items-end gap-2 pb-2 cursor-pointer text-sm">
              <input type="checkbox" v-model="dryRun" /> Dry run (write nothing)
            </label>
            <label class="flex items-end gap-2 pb-2 cursor-pointer text-sm">
              <input type="checkbox" v-model="skipMedia" /> Skip image files
            </label>
          </div>

          <div v-if="importMode === 'fresh' && !dryRun" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
            <strong class="text-destructive">Fresh mode deletes every existing facility, branch and image file.</strong>
            Deleted images cannot be recovered by a rollback.
            <label class="mt-2 flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmWipe" /> I understand — wipe and replace.
            </label>
          </div>

          <div v-if="importError" class="text-sm text-destructive">{{ importError }}</div>

          <div class="flex flex-wrap gap-2">
            <button type="button" @click="inspectPackage" :disabled="!hasPackage || busy" :class="btnSecondary">
              {{ busy ? 'Reading…' : 'Inspect package' }}
            </button>
            <button type="button" @click="startImport" :disabled="!canStart || busy" :class="btnPrimary">
              Start import
            </button>
          </div>

          <div v-if="inspection" class="rounded-lg border border-border p-4 text-sm space-y-2">
            <p class="font-medium">Package contents</p>
            <p class="text-muted-foreground text-xs">
              Built {{ inspection.generated_at }} by {{ inspection.source?.app_url || 'unknown site' }} ·
              images bundled: {{ inspection.options?.include_media_files ? 'yes' : 'no' }}
            </p>
            <div class="flex flex-wrap gap-2 text-xs">
              <span v-for="(v, k) in inspection.counts" :key="k" class="px-2 py-1 rounded bg-muted">
                {{ String(k).replace(/_/g, ' ') }}: {{ v }}
              </span>
            </div>
          </div>
        </div>

        <!-- Step 1.5: preview / edit -->
        <div v-if="importStep === 'preview'" class="space-y-4">
          <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
            <div class="space-y-1">
              <p class="text-sm font-medium">
                {{ previewData.facilities.length }} facilities loaded — review and edit before importing.
              </p>
              <div class="flex flex-wrap gap-1.5 text-[11px]">
                <span class="rounded bg-emerald-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.facilitiesNew }} new facilit{{ previewCounts.facilitiesNew === 1 ? 'y' : 'ies' }}
                </span>
                <span class="rounded bg-amber-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.facilitiesExisting }} facilit{{ previewCounts.facilitiesExisting === 1 ? 'y' : 'ies' }}
                  already here — will be updated
                </span>
                <span class="rounded bg-emerald-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.branchesNew }} new branch{{ previewCounts.branchesNew === 1 ? '' : 'es' }}
                </span>
                <span class="rounded bg-amber-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.branchesExisting }} branch{{ previewCounts.branchesExisting === 1 ? '' : 'es' }}
                  already here
                </span>
              </div>
            </div>
            <div class="ml-auto flex items-center gap-3 text-sm">
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="merge" v-model="importMode" /> Merge
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="fresh" v-model="importMode" /> Fresh
              </label>
            </div>
          </div>

          <div v-if="importMode === 'fresh' && !dryRun" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
            <strong class="text-destructive">Fresh mode deletes every existing facility, branch and image file.</strong>
            <span class="text-muted-foreground">
              The “already here” marks below describe a merge — in fresh mode every row is deleted and
              created again, and the values shown as “now” go with them.
            </span>
            <label class="mt-2 flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmWipe" /> I understand — wipe and replace.
            </label>
          </div>

          <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
            <table class="text-xs text-foreground" style="min-width: 1200px; width: 100%;">
              <thead class="bg-muted/50">
                <tr class="border-b border-border">
                  <th class="px-2 py-2 text-left font-semibold w-10 sticky left-0 bg-muted/80 backdrop-blur z-10">#</th>
                  <th class="px-2 py-2 text-left font-semibold w-28">Status</th>
                  <th class="px-2 py-2 text-left font-semibold w-56">Name (EN)</th>
                  <th class="px-2 py-2 text-left font-semibold w-56">Name (AR)</th>
                  <th class="px-2 py-2 text-left font-semibold w-40">Facility type</th>

                  <th class="px-2 py-2 text-left font-semibold w-20">Branches</th>
                  <th class="px-2 py-2 text-left font-semibold w-20">Offers</th>
                  <th class="px-2 py-2 text-left font-semibold w-20">Media</th>
                  <th class="px-2 py-2 text-left font-semibold w-16 sticky right-0 bg-muted/80 backdrop-blur z-10"></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(facility, i) in paginatedFacilities" :key="facility._index">
                  <tr class="border-b border-border hover:bg-muted/30 transition-colors">
                    <td class="px-2 py-1.5 text-center font-mono sticky left-0 z-[5] bg-card">
                      {{ previewPage * previewPageSize + i + 1 }}
                      <button
                        v-if="(facility.branches || []).length"
                        type="button"
                        @click="facility._showBranches = !facility._showBranches"
                        :class="['block mx-auto mt-0.5 text-[10px] px-1.5 py-0.5 rounded font-semibold', branchBadgeCls(facility)]"
                        :title="facilityBranchIssues(facility).join('\n') || 'Every branch is ready to import'"
                      >
                        {{ facility.branches.length }} br
                      </button>
                    </td>
                    <td class="px-2 py-1 align-top">
                      <span :class="['inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold', rowStateCls(facility)]">
                        {{ rowStateLabel(facility) }}
                      </span>
                      <p v-if="facility._existing" class="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                        updates #{{ facility._existing.id }} ·
                        {{ facility._existing.branches_count }} branch{{ facility._existing.branches_count === 1 ? '' : 'es' }} here now
                      </p>
                    </td>
                    <td class="px-2 py-1 align-top">
                      <input
                        v-model="facility.name.en"
                        :class="previewFieldCls(facility._existing, 'name.en', facility.name.en)"
                      />
                      <ExistingValueHint :existing="facility._existing" path="name.en" :current="facility.name.en" />
                    </td>
                    <td class="px-2 py-1 align-top">
                      <input
                        v-model="facility.name.ar"
                        :class="previewFieldCls(facility._existing, 'name.ar', facility.name.ar)"
                      />
                      <ExistingValueHint :existing="facility._existing" path="name.ar" :current="facility.name.ar" />
                    </td>
                    <td class="px-2 py-1 align-top">
                      <SearchableSelect
                        :model-value="facility._facility_typeChoice"
                        :options="facilityTypeOptions(facility)"
                        :search-keys="LOOKUP_SEARCH_KEYS"
                        placeholder="— use default type —"
                        @change="setFacilityType(facility, $event)"
                      />
                      <ExistingValueHint
                        :existing="facility._existing"
                        path="facility_type"
                        :current="facility._facility_typeChoice"
                        choice
                      />
                      <p
                        v-if="facility._facility_typeChoice === NEW_LOOKUP"
                        class="mt-1 text-[10px] leading-tight text-amber-600 dark:text-amber-400"
                      >
                        “{{ facility._facility_typeLabel }}” is not one of your facility types — it will be
                        created on import. Pick an existing one above to use it instead.
                      </p>
                    </td>
                    <td class="px-2 py-1 text-center text-muted-foreground">{{ (facility.branches || []).length }}</td>
                    <td class="px-2 py-1 text-center text-muted-foreground">{{ (facility.offers || []).length }}</td>
                    <td class="px-2 py-1 text-center text-muted-foreground">{{ (facility.media || []).length }}</td>
                    <td class="px-2 py-1 text-center sticky right-0 z-[5] bg-card">
                      <button
                        type="button"
                        @click="previewData.facilities.splice(previewPage * previewPageSize + i, 1)"
                        class="text-destructive hover:underline text-[11px]"
                        title="Skip this facility"
                      >skip</button>
                    </td>
                  </tr>

                  <!-- Branches sub-table -->
                  <tr v-if="facility._showBranches && (facility.branches || []).length" class="border-b border-border">
                    <td colspan="12" class="p-3">
                      <div class="bg-card border border-violet-500 rounded-md overflow-hidden">
                        <div class="bg-violet-600 text-white px-3 py-2 text-xs font-semibold flex flex-wrap items-center gap-2">
                          <span>Branches for {{ facility.name?.en || 'facility' }} ({{ facility.branches.length }})</span>
                          <span
                            class="ml-auto px-1.5 py-0.5 rounded text-[10px]"
                            :class="facilityBranchIssues(facility).length ? 'bg-red-700' : 'bg-emerald-700'"
                          >
                            {{ facilityBranchIssues(facility).length
                              ? facilityBranchIssues(facility).length + ' to check'
                              : 'all good' }}
                          </span>
                        </div>
                        <table class="w-full text-[11px]">
                          <thead class="bg-muted">
                            <tr>
                              <th class="px-3 py-1.5 text-left font-semibold">Branch name</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Name (AR)</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Address</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Phones <span class="font-normal opacity-70">(one per line)</span></th>
                              <th class="px-3 py-1.5 text-left font-semibold">Governorate</th>
                              <th class="px-3 py-1.5 text-left font-semibold">City</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Lat</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Lng</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Google URL</th>
                              <th class="px-3 py-1.5 w-12"></th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(br, bi) in facility.branches"
                              :key="bi"
                              class="border-t border-border align-top"
                              :class="branchIssues(br).length ? 'bg-red-500/5' : ''"
                            >
                              <td class="px-3 py-1">
                                <span :class="['mb-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold', rowStateCls(br)]">
                                  {{ rowStateLabel(br) }}
                                  <template v-if="br._existing">#{{ br._existing.id }}</template>
                                </span>
                                <p v-if="movedFrom(facility, br)" class="mb-1 text-[10px] leading-tight text-amber-600 dark:text-amber-400">
                                  Sits under facility #{{ movedFrom(facility, br) }} today — importing moves it here.
                                </p>
                                <input
                                  v-model="br.name.en"
                                  :class="branchIssues(br).length
                                    ? [previewInputCls, 'border-red-500']
                                    : previewFieldCls(br._existing, 'name.en', br.name.en)"
                                />
                                <p v-if="branchIssues(br).length" class="mt-0.5 text-[10px] leading-tight text-red-600 dark:text-red-400">
                                  {{ branchIssues(br).join(' · ') }}
                                </p>
                                <ExistingValueHint :existing="br._existing" path="name.en" :current="br.name.en" />
                              </td>
                              <td class="px-3 py-1">
                                <input v-model="br.name.ar" :class="previewFieldCls(br._existing, 'name.ar', br.name.ar)" />
                                <ExistingValueHint :existing="br._existing" path="name.ar" :current="br.name.ar" />
                              </td>
                              <td class="px-3 py-1">
                                <input v-model="br.address.en" :class="previewFieldCls(br._existing, 'address.en', br.address.en)" />
                                <ExistingValueHint :existing="br._existing" path="address.en" :current="br.address.en" />
                              </td>
                              <td class="px-3 py-1">
                                <textarea
                                  :value="br._phoneText"
                                  @input="setBranchPhones(br, $event.target.value)"
                                  rows="2"
                                  placeholder="One phone per line"
                                  :class="[
                                    previewFieldCls(br._existing, 'phone', br.phone),
                                    'min-w-[9rem] resize-y font-mono leading-tight',
                                  ]"
                                ></textarea>
                                <p v-if="(br.phone || []).length > 1" class="mt-0.5 text-[10px] text-muted-foreground">
                                  {{ br.phone.length }} numbers
                                </p>
                                <ExistingValueHint :existing="br._existing" path="phone" :current="br.phone" />
                              </td>
                              <td class="px-3 py-1">
                                <SearchableSelect
                                  class="min-w-[10rem]"
                                  :model-value="br._governorateChoice"
                                  :options="governorateOptions(br)"
                                  :search-keys="LOOKUP_SEARCH_KEYS"
                                  :error="lookupError(br._governorateChoice)"
                                  placeholder="— none —"
                                  @change="setBranchGovernorate(br, $event)"
                                />
                                <p
                                  v-if="br._governorateChoice === NEW_LOOKUP"
                                  class="mt-0.5 text-[10px] leading-tight text-amber-600 dark:text-amber-400"
                                >
                                  "{{ br._governorateLabel }}" — not on this site yet.
                                  <button
                                    type="button"
                                    class="underline font-medium hover:text-amber-800 dark:hover:text-amber-200 ml-0.5"
                                    :disabled="quickCreateLoading === `governorate-${br.name?.en || br.name?.ar}`"
                                    @click="quickCreateLookup(br, 'governorate', br)"
                                  >
                                    {{ quickCreateLoading === `governorate-${br.name?.en || br.name?.ar}` ? 'Creating…' : 'Create it' }}
                                  </button>
                                </p>
                                <ExistingValueHint
                                  :existing="br._existing"
                                  path="governorate"
                                  :current="br._governorateChoice"
                                  choice
                                />
                              </td>
                              <td class="px-3 py-1">
                                <SearchableSelect
                                  class="min-w-[10rem]"
                                  :model-value="br._cityChoice"
                                  :options="cityOptions(br)"
                                  :search-keys="LOOKUP_SEARCH_KEYS"
                                  :error="lookupError(br._cityChoice)"
                                  placeholder="— none —"
                                  @change="setBranchCity(br, $event)"
                                />
                                <p
                                  v-if="br._cityChoice === NEW_LOOKUP"
                                  class="mt-0.5 text-[10px] leading-tight text-amber-600 dark:text-amber-400"
                                >
                                  "{{ br._cityLabel }}" — not among this governorate's cities.
                                  <button
                                    type="button"
                                    class="underline font-medium hover:text-amber-800 dark:hover:text-amber-200 ml-0.5"
                                    :disabled="quickCreateLoading === `city-${br.name?.en || br.name?.ar}` || !br._governorateChoice || br._governorateChoice === NEW_LOOKUP"
                                    @click="quickCreateLookup(br, 'city', br)"
                                  >
                                    {{ quickCreateLoading === `city-${br.name?.en || br.name?.ar}` ? 'Creating…' : 'Create it' }}
                                  </button>
                                </p>
                                <ExistingValueHint
                                  :existing="br._existing"
                                  path="city"
                                  :current="br._cityChoice"
                                  choice
                                />
                              </td>
                              <td class="px-3 py-1">
                                <input
                                  type="number"
                                  step="any"
                                  v-model="br.latitude"
                                  :class="previewFieldCls(br._existing, 'latitude', br.latitude, false, true)"
                                />
                                <ExistingValueHint :existing="br._existing" path="latitude" :current="br.latitude" numeric />
                              </td>
                              <td class="px-3 py-1">
                                <input
                                  type="number"
                                  step="any"
                                  v-model="br.longitude"
                                  :class="previewFieldCls(br._existing, 'longitude', br.longitude, false, true)"
                                />
                                <ExistingValueHint :existing="br._existing" path="longitude" :current="br.longitude" numeric />
                              </td>
                              <td class="px-3 py-1">
                                <input
                                  v-model="br.google_location_url"
                                  placeholder="https://maps.app.goo.gl/..."
                                  :class="previewFieldCls(br._existing, 'google_location_url', br.google_location_url)"
                                />
                                <ExistingValueHint
                                  :existing="br._existing"
                                  path="google_location_url"
                                  :current="br.google_location_url"
                                />
                              </td>
                              <td class="px-3 py-1 text-center">
                                <button type="button" @click="facility.branches.splice(bi, 1)" class="text-red-600 hover:underline text-[10px]">remove</button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="totalPreviewPages > 1" class="flex items-center justify-center gap-2">
            <button type="button" @click="previewPage = Math.max(0, previewPage - 1)" :disabled="previewPage === 0" :class="btnSecondary">
              Previous
            </button>
            <span class="text-sm text-muted-foreground">
              Page {{ previewPage + 1 }} of {{ totalPreviewPages }} ({{ previewData.facilities.length }} facilities)
            </span>
            <button type="button" @click="previewPage = Math.min(totalPreviewPages - 1, previewPage + 1)" :disabled="previewPage >= totalPreviewPages - 1" :class="btnSecondary">
              Next
            </button>
          </div>

          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
            <button type="button" @click="cancelPreview" :class="btnSecondary">Back</button>
            <div v-if="importError" class="text-xs text-destructive">{{ importError }}</div>
            <button
              type="button"
              @click="startImportFromPreview"
              :disabled="busy || previewData.facilities.length === 0 || hasLookupIssues || (importMode === 'fresh' && !dryRun && !confirmWipe)"
              class="ml-auto inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden cursor-pointer"
            >
              {{ busy ? 'Saving edits…' : 'Start import' }}
            </button>
          </div>
        </div>

        <!-- Step 2: running -->
        <div v-if="importStep === 'running'" class="space-y-4">
          <div class="flex items-center justify-between text-sm">
            <span class="font-medium">
              {{ dryRun ? 'Dry run' : 'Importing' }} — {{ progress.processed }} of {{ progress.total }} facilities
            </span>
            <span class="text-muted-foreground">{{ progress.percent }}%</span>
          </div>
          <div class="h-3 rounded-full bg-muted overflow-hidden">
            <div class="h-full bg-primary transition-all duration-300" :style="{ width: progress.percent + '%' }"></div>
          </div>

          <div class="flex flex-wrap gap-2 text-xs">
            <span v-for="(v, k) in progress.stats" :key="k" class="px-2 py-1 rounded bg-muted">
              {{ String(k).replace(/_/g, ' ') }}: {{ v }}
            </span>
          </div>

          <div v-if="progress.errors?.length" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs space-y-1">
            <p class="font-semibold text-destructive">{{ progress.errors.length }} facility(ies) failed — the rest continue:</p>
            <p v-for="(e, i) in progress.errors.slice(-5)" :key="i">{{ e.facility }}: {{ e.message }}</p>
          </div>

          <div class="flex gap-2">
            <button v-if="paused" type="button" @click="runLoop" :class="btnPrimary">Resume</button>
            <button v-else type="button" @click="paused = true" :class="btnSecondary">Pause</button>
            <button type="button" @click="cancelImport" :class="btnSecondary">Cancel</button>
          </div>
          <p class="text-xs text-muted-foreground">
            Each step is its own request, so you can pause, or close and reopen this page —
            nothing already imported is lost.
          </p>
        </div>

        <!-- Step 3: done -->
        <div v-if="importStep === 'done'" class="space-y-4">
          <h3 class="text-lg font-semibold">
            {{ result?.dry_run ? 'Dry run complete — nothing was written' : 'Import complete' }}
          </h3>
          <div class="flex flex-wrap gap-2 text-sm">
            <span v-for="(v, k) in result?.stats || {}" :key="k" class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-medium">
              {{ String(k).replace(/_/g, ' ') }}: {{ v }}
            </span>
          </div>

          <details v-if="result?.warnings?.length" class="rounded-lg border border-border p-3 text-xs">
            <summary class="cursor-pointer font-medium">{{ result.warnings.length }} warning(s)</summary>
            <p v-for="(w, i) in result.warnings" :key="i" class="mt-1 text-muted-foreground">{{ w }}</p>
          </details>

          <details v-if="result?.errors?.length" class="rounded-lg border border-destructive/40 p-3 text-xs">
            <summary class="cursor-pointer font-medium text-destructive">{{ result.errors.length }} failure(s)</summary>
            <p v-for="(e, i) in result.errors" :key="i" class="mt-1">{{ e.facility }}: {{ e.message }}</p>
          </details>

          <div class="flex gap-2">
            <Link :href="route('admin.facility.list')" :class="btnPrimary">Go to facilities</Link>
            <button type="button" @click="resetImport" :class="btnSecondary">Import another package</button>
          </div>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import FacilityLayout from '../FacilityLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import ExistingValueHint from './ExistingValueHint.vue';
import { oldValue } from './existingValue.js';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { computed, ref } from 'vue';

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  facilityTypes: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  salesOptions: { type: Array, default: () => [] },
});

const tabs = [
  { key: 'export', label: 'Export' },
  { key: 'import', label: 'Import' },
];
const activeTab = ref('export');

const inputCls = 'w-full px-2 py-1.5 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-1 focus:ring-primary';
const btnPrimary = 'inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden cursor-pointer';
const btnSecondary = 'inline-flex items-center justify-center rounded-md text-sm font-medium border border-border bg-background h-9 px-4 hover:bg-muted disabled:opacity-50 cursor-pointer';

/* ------------------------------- export ------------------------------- */

const exportFilters = ref({
  search: '',
  facility_type_id: '',
  governorate_id: '',
  sales_id: '',
  created_from: '',
  created_to: '',
});
const includeMedia = ref(true);
const includeOffers = ref(true);
const splitParts = ref(false);
const perPart = ref(25);
const plan = ref(null);
const planError = ref('');
const planLoading = ref(false);
const downloadedParts = ref([]);

const exportParams = () => {
  const params = new URLSearchParams();
  Object.entries(exportFilters.value).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined) params.set(k, v);
  });
  params.set('include_media', includeMedia.value ? '1' : '0');
  params.set('include_offers', includeOffers.value ? '1' : '0');
  return params;
};

const exportUrl = (part = null) => {
  const params = exportParams();
  if (part !== null) {
    params.set('part', part);
    params.set('per_part', perPart.value);
  }
  return route('admin.facility.migration.export') + '?' + params.toString();
};

const loadPlan = async () => {
  planLoading.value = true;
  planError.value = '';
  try {
    const params = exportParams();
    params.set('per_part', perPart.value);
    const { data } = await axios.get(route('admin.facility.migration.export.plan') + '?' + params.toString());
    plan.value = data;
    downloadedParts.value = [];
  } catch (e) {
    plan.value = null;
    planError.value = e.response?.data?.message || 'Could not count the facilities.';
  } finally {
    planLoading.value = false;
  }
};

/* ------------------------------- import ------------------------------- */

const packageFile = ref(null);
const serverPath = ref('');
const importMode = ref('merge');
const chunkSize = ref(5);
const dryRun = ref(false);
const skipMedia = ref(false);
const confirmWipe = ref(false);
const busy = ref(false);
const importError = ref('');
const inspection = ref(null);

const importStep = ref('choose');
const token = ref(null);
const paused = ref(false);
const progress = ref({ processed: 0, total: 0, percent: 0, stats: {}, errors: [] });
const result = ref(null);

const hasPackage = computed(() => !!packageFile.value || serverPath.value.trim() !== '');
const canStart = computed(
  () => hasPackage.value && (importMode.value !== 'fresh' || dryRun.value || confirmWipe.value)
);

const onPackageChange = (e) => {
  packageFile.value = e.target.files?.[0] || null;
  inspection.value = null;
  importError.value = '';
};

// The package travels as multipart when uploaded, or by name when it was put on
// the server out of band — both endpoints accept either.
const packageForm = (extra = {}) => {
  const fd = new FormData();
  if (packageFile.value) fd.append('package', packageFile.value);
  else fd.append('server_path', serverPath.value.trim());
  Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
  return fd;
};

/* ----------------------------- preview / edit ----------------------------- */

const previewData = ref({ token: null, facilities: [], source: {}, generated_at: '', counts: {} });
const previewPage = ref(0);
const previewPageSize = 25;
const totalPreviewPages = computed(() => Math.ceil(previewData.value.facilities.length / previewPageSize));
const paginatedFacilities = computed(() => {
  const start = previewPage.value * previewPageSize;
  return previewData.value.facilities.slice(start, start + previewPageSize);
});
/* The text colour travels with the background rather than sitting in the base:
   this theme's --foreground is near-white in light mode too — the admin sits on
   a purple gradient — so a field painted a light colour has to say outright
   which ink it wants, or it writes white on cream. */
const previewInputBase = 'w-full px-1.5 py-1 rounded border placeholder:text-muted-foreground text-xs focus:outline-none focus:ring-1 focus:ring-primary';
const previewInputCls = `${previewInputBase} border-input bg-background text-foreground`;

/* A package may spell a translatable field as { en, ar }, as a bare string
   (spreadsheet imports) or leave it out entirely — the editable inputs below
   need the object shape in every case. */
const asTranslation = (value) => {
  if (typeof value === 'string') return { en: value, ar: '' };
  return value && typeof value === 'object' ? value : {};
};

/* Same story for a lookup reference (facility type / governorate / city):
   { id, slug, name } from an exported package, a plain name from a sheet.
   The id is dropped: it numbers a row on the site the package came from, and
   keeping it would make the importer ignore whatever is edited here. Slug and
   name are what the importer matches on. */
const asRef = (value) => {
  if (value === null || value === undefined || value === '') return { name: {} };
  if (typeof value === 'string') return { name: { en: value, ar: '' } };

  const { id, ...rest } = value;

  return { ...rest, name: asTranslation(value.name) };
};

/* A branch holds a list of phones. A package can spell it as that list, or as
   one string with several numbers in it (spreadsheet imports) — the textarea
   below shows one number per line either way, and the lines are the list. */
const asPhoneList = (raw) => {
  const flat = Array.isArray(raw)
    ? raw.map(p => (p === null || p === undefined ? '' : String(p))).join('\n')
    : String(raw ?? '');

  return flat.split(/[\r\n,;|]+/).map(p => p.trim()).filter(p => p !== '');
};

/* The typed text is kept as typed — blank lines and all — so the caret never
   jumps while editing; the list is re-read from it on every keystroke. */
const setBranchPhones = (branch, text) => {
  branch._phoneText = text;
  branch.phone = text.split(/\r?\n/).map(p => p.trim()).filter(p => p !== '');
};

/* ------------------------------ lookup pickers ----------------------------- */

/* Facility type, governorate and city are all picked from what this site
   already has. The value the package carries is matched against those; when
   nothing matches it stays selectable as a "(new)" entry, spelled out under
   the select, and the importer creates it. */

const NEW_LOOKUP = '__new__';

/* The page loads the governorates and cities this site has today, but the
   preview can add to them: a place the package names and this site lacks is
   created from the row that needs it, and joins the pickers straight away. */
const governorateList = ref([...props.governorates]);
const cityList = ref([...props.cities]);

// What the package calls this row, for display and for creating it if kept.
const refLabel = (ref) => String(ref?.name?.en || ref?.name?.ar || ref?.slug || '').trim();

/* Arabic writes one name several ways — “القاهرة” and “القاهره” differ only by
   the letter shape the keyboard offered, and a package built on another site is
   full of that. Comparing the raw strings would call Cairo a governorate this
   site has never heard of, so the variants are folded away first: harakat and
   tatweel dropped, the alef and yaa family flattened, ة read as ه, Arabic
   digits as western ones, punctuation as a space. The same pass makes the
   English side forgiving too — the slug “beni-suef” folds onto “Beni Suef”. */
const foldName = (value) =>
  String(value ?? '')
    .normalize('NFKC')
    .toLowerCase()
    .replace(/[\u064B-\u0652\u0670\u0640]/g, '')
    .replace(/[\u0622\u0623\u0625\u0627\u0671]/g, '\u0627')
    .replace(/\u0629/g, '\u0647')
    .replace(/[\u0649\u0626]/g, '\u064A')
    .replace(/\u0624/g, '\u0648')
    .replace(/\u0621/g, '')
    .replace(/[\u0660-\u0669\u06F0-\u06F9]/g, d => String(d.charCodeAt(0) & 0xF))
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();

// A looser second look: “البحر الأحمر” and “بحر احمر” are the same governorate.
const withoutArticle = (folded) =>
  folded.split(' ').map(w => w.replace(/^ال/, '')).filter(Boolean).join(' ');

// Every spelling one row can be written under, folded, in both strictnesses.
const spellingsOf = (values) => {
  const strict = new Set();
  const loose = new Set();

  values.forEach(value => {
    const folded = foldName(value);
    if (!folded) return;
    strict.add(folded);
    loose.add(withoutArticle(folded));
  });

  return { strict, loose };
};

// Match by name or slug — never by the id in the package, which numbers a row
// on the site the package came from. Exact spellings win before loose ones, so
// a near-neighbour can never steal a row the package named outright.
const findLookup = (ref, options) => {
  const want = spellingsOf([ref?.name?.en, ref?.name?.ar, ref?.slug]);
  if (!want.strict.size) return null;

  const list = options || [];
  const matches = (option, pass) =>
    [...spellingsOf([option.label, option.name_en, option.name_ar, option.slug])[pass]]
      .some(spelling => want[pass].has(spelling));

  return list.find(o => matches(o, 'strict')) || list.find(o => matches(o, 'loose')) || null;
};

// The select is the source of truth: whatever it shows is written back onto the
// row, so the preview and the import can never disagree.
const applyChoice = (owner, field, choice, options) => {
  const prefix = `_${field}`;
  owner[`${prefix}Choice`] = String(choice);

  if (choice === NEW_LOOKUP) {
    // Both spellings the package carried, not just the one the label shows —
    // creating the row from an Arabic name should not drop its English one.
    owner[field] = { name: { ...(owner[`${prefix}Names`] || { en: owner[`${prefix}Label`], ar: '' }) } };

    return;
  }
  if (choice === '') {
    owner[field] = null;

    return;
  }

  const option = (options || []).find(o => String(o.value) === String(choice));
  if (!option) return;
  owner[field] = { id: option.value, name: { en: option.name_en || option.label, ar: option.name_ar || '' } };
};

const normalizeLookup = (owner, field, raw, options) => {
  const ref = asRef(raw);
  const label = refLabel(ref);
  const match = findLookup(ref, options);

  owner[`_${field}Label`] = label;
  owner[`_${field}Names`] = { en: ref.name?.en || '', ar: ref.name?.ar || '' };
  owner[`_${field}Unknown`] = label !== '' && !match;
  applyChoice(owner, field, match ? match.value : (label !== '' ? NEW_LOOKUP : ''), options);
};

/* Quick-create a governorate or city that the package named but this site
   does not have yet. The backend deduplicates, so two branches pointing at
   the same missing city both end up with one shared row. */
const quickCreateLoading = ref(null);

const quickCreateLookup = async (owner, field, branch) => {
  const type = field === 'governorate' ? 'governorate' : 'city';
  const label = branch[`_${field}Label`];
  if (!label) return;

  const names = branch[`_${field}Names`] || {};
  const payload = { type, name_en: names.en || label, name_ar: names.ar || '' };
  if (type === 'city') {
    payload.governorate_id = branch._governorateChoice;
  }

  const key = `${field}-${branch.name?.en || branch.name?.ar || ''}`;
  quickCreateLoading.value = key;
  try {
    const { data } = await axios.post(route('admin.facility.migration.lookup.store'), payload);
    const option = data.option;

    // Push into the global list so the select can see it.
    const list = field === 'governorate' ? governorateList : cityList;
    if (!list.value.some(o => String(o.value) === String(option.value))) {
      list.value.push(option);
    }

    applyChoice(branch, field, option.value, list.value);

    // If we just created a governorate, the city list changed — re-normalize.
    if (type === 'governorate') {
      normalizeLookup(branch, 'city', branch.city, citiesFor(branch));
    }
  } catch (e) {
    importError.value = e.response?.data?.message || `Could not create the ${type}.`;
  } finally {
    quickCreateLoading.value = null;
  }
};

const setFacilityType = (facility, choice) =>
  applyChoice(facility, 'facility_type', choice, props.facilityTypes);

/* What a picker offers: the rows this site has, plus — when the package named
   something none of them match — that spelling itself, so keeping it is a
   deliberate choice rather than the only thing left. */
const withNewOption = (options, unknown, label) =>
  unknown ? [...(options || []), { value: NEW_LOOKUP, label: `${label} (new)` }] : (options || []);

const facilityTypeOptions = (facility) =>
  withNewOption(props.facilityTypes, facility._facility_typeUnknown, facility._facility_typeLabel);

const governorateOptions = (branch) =>
  withNewOption(governorateList.value, branch._governorateUnknown, branch._governorateLabel);

const cityOptions = (branch) =>
  withNewOption(citiesFor(branch), branch._cityUnknown, branch._cityLabel);

// Typing any spelling should find the row, not just the one the label shows.
const LOOKUP_SEARCH_KEYS = ['label', 'name_en', 'name_ar', 'slug'];

// Nothing picked is the one state the picker itself should show as wrong; the
// "(new)" case is spelled out in words underneath instead.
const lookupError = (choice) => (choice === '' ? 'Nothing picked' : null);

/* Cities belong to a governorate, so the city list narrows to the governorate
   the branch is set to — and a city that no longer fits it is dropped rather
   than quietly importing under the wrong one. */
const citiesFor = (branch) => {
  const cities = cityList.value;
  const gov = branch._governorateChoice;
  if (!gov || gov === NEW_LOOKUP) return cities;

  return cities.filter(c => String(c.governorate_id) === String(gov));
};

const setBranchGovernorate = (branch, choice) => {
  applyChoice(branch, 'governorate', choice, governorateList.value);

  const city = cityList.value.find(c => String(c.value) === String(branch._cityChoice));
  if (city && !citiesFor(branch).includes(city)) {
    setBranchCity(branch, branch._cityLabel ? NEW_LOOKUP : '');
  }
};

const setBranchCity = (branch, choice) => {
  applyChoice(branch, 'city', choice, cityList.value);

  // Picking a city that belongs elsewhere settles the governorate too.
  const city = cityList.value.find(c => String(c.value) === String(choice));
  if (city && String(branch._governorateChoice) !== String(city.governorate_id)) {
    applyChoice(branch, 'governorate', city.governorate_id, governorateList.value);
  }
};

/* --------------------------- new or already here --------------------------- */

/* The preview edits the package, so a facility that this site already knows
   looks exactly like one it has never seen. `_existing` — put on the row by the
   preview endpoint, using the same matching the import itself does — is what
   tells them apart: the row a merge would land on, with the values it carries
   today. It is stripped again before the edits go back. */

const isExisting = (row) => !!row?._existing;

const rowStateLabel = (row) => (isExisting(row) ? 'already here' : 'new');

// Solid fills rather than tints: the preview table sits on a card whose own
// background changes with the theme, and a 15%-opacity badge disappears into it.
const rowStateCls = (row) =>
  (isExisting(row) ? 'bg-amber-600 text-white' : 'bg-emerald-600 text-white');

/* An input whose value the import would overwrite says so in its own colour
   too — the old value alone under it is easy to read past on a wide table.
   The amber is swapped in rather than tacked on: two border-colour utilities on
   one element carry the same weight, so whichever the stylesheet happens to
   define last would win, not the one meant to. */
const previewInputChangedCls = `${previewInputBase} border-amber-600 bg-amber-100 text-amber-950 placeholder:text-amber-700 dark:border-amber-400 dark:bg-amber-900/50 dark:text-amber-50 dark:placeholder:text-amber-200`;

const previewFieldCls = (existing, path, current, choice = false, numeric = false) =>
  (oldValue(existing, path, current, choice, numeric) ? previewInputChangedCls : previewInputCls);

// A branch can be found under another facility; merging moves it across.
const movedFrom = (facility, branch) =>
  (branch._existing && branch._existing.facility_id !== facility._existing?.id
    ? branch._existing.facility_id
    : null);

const previewCounts = computed(() => {
  const counts = { facilitiesNew: 0, facilitiesExisting: 0, branchesNew: 0, branchesExisting: 0 };

  previewData.value.facilities.forEach((facility) => {
    counts[isExisting(facility) ? 'facilitiesExisting' : 'facilitiesNew'] += 1;
    (facility.branches || []).forEach((branch) => {
      counts[isExisting(branch) ? 'branchesExisting' : 'branchesNew'] += 1;
    });
  });

  return counts;
});

/* ------------------------------ branch health ------------------------------ */

/* What the red/green badge on a facility row is counting. A lookup that has to
   be created is a problem worth seeing before the import, not after. */
const branchIssues = (branch) => {
  const issues = [];

  if (!String(branch.name?.en || '').trim() && !String(branch.name?.ar || '').trim()) {
    issues.push('Branch has no name');
  }
  if (branch._governorateChoice === '') {
    issues.push('No governorate');
  } else if (branch._governorateChoice === NEW_LOOKUP) {
    issues.push(`Governorate “${branch._governorateLabel}” does not exist yet`);
  }
  if (branch._cityChoice === '') {
    issues.push('No city');
  } else if (branch._cityChoice === NEW_LOOKUP) {
    issues.push(`City “${branch._cityLabel}” does not exist yet`);
  }

  return issues;
};

const facilityBranchIssues = (facility) =>
  (facility.branches || []).flatMap((branch, index) =>
    branchIssues(branch).map(issue => `Branch ${index + 1}: ${issue}`)
  );

const branchBadgeCls = (facility) =>
  facilityBranchIssues(facility).length
    ? 'bg-red-600 text-white'
    : 'bg-emerald-600 text-white';

const hasLookupIssues = computed(() =>
  previewData.value.facilities.some(f =>
    (f.branches || []).some(b =>
      b._governorateChoice === NEW_LOOKUP || b._cityChoice === NEW_LOOKUP
    )
  )
);

const inspectPackage = async () => {
  busy.value = true;
  importError.value = '';
  try {
    // Load preview data — this also creates the import session
    const { data } = await axios.post(route('admin.facility.migration.preview'), packageForm());
    previewData.value = {
      token: data.token,
      facilities: (data.facilities || []).map(f => {
        const facility = {
          ...f,
          name: asTranslation(f.name),
          branches: (f.branches || []).map(b => {
            const phones = asPhoneList(b.phone);
            const branch = {
              ...b,
              name: asTranslation(b.name),
              address: asTranslation(b.address),
              phone: phones,
              _phoneText: phones.join('\n'),
              google_location_url: b.google_location_url || '',
            };

            // Governorate first: it decides which cities the city picker offers.
            normalizeLookup(branch, 'governorate', b.governorate, governorateList.value);
            normalizeLookup(branch, 'city', b.city, citiesFor(branch));

            return branch;
          }),
          offers: f.offers || [],
          media: f.media || [],
          tags: f.tags || [],
          _showBranches: false,
        };
        normalizeLookup(facility, 'facility_type', f.facility_type, props.facilityTypes);

        return facility;
      }),
      source: data.source || {},
      generated_at: data.generated_at || '',
      counts: data.counts || {},
    };
    previewPage.value = 0;
    inspection.value = data;
    importStep.value = 'preview';
  } catch (e) {
    inspection.value = null;
    importError.value = e.response?.data?.message || 'Could not read that package.';
  } finally {
    busy.value = false;
  }
};

const cancelPreview = () => {
  // End the session created during preview
  if (previewData.value.token) {
    axios.post(route('admin.facility.migration.cancel'), { token: previewData.value.token }).catch(() => {});
  }
  importStep.value = 'choose';
  previewData.value = { token: null, facilities: [], source: {}, generated_at: '', counts: {} };
  inspection.value = null;
  importError.value = '';
};

/* Everything prefixed with "_" — the select choices, the typed phone text, the
   expanded/collapsed flag — belongs to this screen and never to the package. */
const withoutBookkeeping = (row) => {
  const clean = Object.fromEntries(
    Object.entries(row).filter(([key]) => !key.startsWith('_'))
  );
  if (Array.isArray(clean.branches)) {
    clean.branches = clean.branches.map(withoutBookkeeping);
  }

  return clean;
};

const startImportFromPreview = async () => {
  busy.value = true;
  importError.value = '';
  try {
    // Save any remaining edits for the current page before starting
    const sessionToken = previewData.value.token;
    const facilities = previewData.value.facilities;
    for (const facility of facilities) {
      const dataToSend = withoutBookkeeping(facility);
      await axios.post(route('admin.facility.migration.edit'), {
        token: sessionToken,
        index: facility._index,
        data: dataToSend,
      });
    }

    // The session was opened by the preview with placeholder settings — the
    // mode and switches the operator picked here have to reach it before the
    // first step runs.
    await axios.post(route('admin.facility.migration.options'), {
      token: sessionToken,
      mode: importMode.value,
      dry_run: dryRun.value ? 1 : 0,
      skip_media: skipMedia.value ? 1 : 0,
      confirm_wipe: confirmWipe.value ? 1 : 0,
    });

    // The session was already created by preview — just start stepping
    token.value = sessionToken;
    progress.value = { processed: 0, total: facilities.length, percent: 0, stats: {}, errors: [] };
    importStep.value = 'running';
    paused.value = false;
    runLoop();
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not start the import.';
  } finally {
    busy.value = false;
  }
};

const startImport = async () => {
  busy.value = true;
  importError.value = '';
  try {
    const { data } = await axios.post(
      route('admin.facility.migration.begin'),
      packageForm({
        mode: importMode.value,
        dry_run: dryRun.value ? 1 : 0,
        skip_media: skipMedia.value ? 1 : 0,
        confirm_wipe: confirmWipe.value ? 1 : 0,
      })
    );
    token.value = data.token;
    progress.value = { processed: 0, total: data.total, percent: 0, stats: {}, errors: [] };
    importStep.value = 'running';
    paused.value = false;
    runLoop();
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not open the import session.';
  } finally {
    busy.value = false;
  }
};

// One request per chunk, chained — never a single long-running call.
const runLoop = async () => {
  paused.value = false;
  while (!paused.value && token.value) {
    let data;
    try {
      ({ data } = await axios.post(route('admin.facility.migration.step'), {
        token: token.value,
        limit: chunkSize.value,
      }));
    } catch (e) {
      importError.value = e.response?.data?.message || 'A step failed.';
      paused.value = true;
      return;
    }
    progress.value = data;
    if (data.done) {
      await finishImport();
      return;
    }
  }
};

const finishImport = async () => {
  try {
    const { data } = await axios.post(route('admin.facility.migration.finish'), { token: token.value });
    result.value = data;
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not close the session.';
  } finally {
    token.value = null;
    importStep.value = 'done';
  }
};

const cancelImport = async () => {
  paused.value = true;
  const tok = token.value || previewData.value.token;
  if (tok) {
    try {
      await axios.post(route('admin.facility.migration.cancel'), { token: tok });
    } catch (e) {
      // The session is disposable — a failed cleanup is not worth blocking on.
    }
  }
  token.value = null;
  resetImport();
};

const resetImport = () => {
  importStep.value = 'choose';
  packageFile.value = null;
  serverPath.value = '';
  inspection.value = null;
  result.value = null;
  importError.value = '';
  confirmWipe.value = false;
  previewData.value = { token: null, facilities: [], source: {}, generated_at: '', counts: {} };
  previewPage.value = 0;
  progress.value = { processed: 0, total: 0, percent: 0, stats: {}, errors: [] };
};
</script>
