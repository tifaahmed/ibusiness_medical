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
        branches, managers, tags, offers and the image files themselves travel in one package.
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

        <div class="rounded-lg border border-border p-4 space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">What goes in the package</p>
          <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="includeMedia" />
              Include image files
              <span class="text-xs text-muted-foreground">(off = data only, much smaller)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="includeBranches" />
              Include branches
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="includeManagers" />
              Include managers
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="includeOffers" />
              Include offers
            </label>
          </div>
          <p v-if="!includeBranches || !includeManagers" class="text-xs text-amber-600 dark:text-amber-400">
            Left out entirely — the package carries no
            {{ [!includeBranches ? 'branches' : null, !includeManagers ? 'managers' : null].filter(Boolean).join(' and ') }},
            and importing it leaves whatever the other site already has there untouched.
          </p>
        </div>

        <div class="rounded-lg border border-border p-4 space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Splitting &amp; storage</p>
          <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="splitParts" />
              Split into several files
            </label>
            <label class="flex items-center gap-2" :class="splitParts ? '' : 'opacity-50'">
              <span class="text-xs text-muted-foreground">facilities per file</span>
              <input
                v-model.number="perPart"
                type="number"
                min="1"
                :disabled="!splitParts"
                class="w-24 px-2 py-1 rounded border border-input bg-background text-sm disabled:opacity-60"
              />
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="keepOnServer" />
              Keep a copy on this server
              <span class="text-xs text-muted-foreground">(so you can import it later without uploading)</span>
            </label>
          </div>
          <p v-if="splitParts" class="text-xs text-muted-foreground">
            Every file is a complete package on its own — import them in any order using <strong>merge</strong>.
            Press <strong>Check what matches</strong> to see how many files that comes to.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" @click="loadPlan" :disabled="planLoading" :class="btnSecondary">
            {{ planLoading ? 'Counting…' : 'Check what matches' }}
          </button>
          <a v-if="!splitParts" :href="exportUrl()" :class="btnPrimary" @click="noteExport">Download package</a>
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
              @click="downloadedParts.push(n); noteExport()"
            >
              Part {{ n }} / {{ plan.parts }}
            </a>
          </div>
          <p v-if="splitParts && plan.parts" class="text-xs text-muted-foreground">
            Download the parts one at a time. Each is a complete package on its own — import them
            in any order on the other site using <strong>merge</strong> mode.
          </p>
          <p v-if="keepOnServer" class="text-xs text-muted-foreground">
            Every package you download is also kept on this server and shows up under
            <button type="button" class="underline font-medium" @click="goToPackages">Packages on this server</button>
            in the Import tab.
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
          <!-- Packages kept on this server: everything exported from here with
               "keep a copy" on, plus anything dropped in over FTP. -->
          <div ref="packagesSection" class="rounded-lg border border-border p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-medium">Packages on this server</p>
              <span class="text-xs text-muted-foreground">
                storage/app/facility-migration — pick one instead of uploading
              </span>
              <button type="button" class="ml-auto text-xs underline" @click="loadPackages" :disabled="packagesLoading">
                {{ packagesLoading ? 'Reading…' : 'Refresh' }}
              </button>
            </div>

            <p v-if="!packages.length && !packagesLoading" class="text-xs text-muted-foreground">
              Nothing here yet. Export with <strong>Keep a copy on this server</strong> ticked, or copy a
              package into that folder.
            </p>

            <ul v-else class="divide-y divide-border text-sm">
              <li
                v-for="pkg in packages"
                :key="pkg.name"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                :class="serverPath === pkg.name ? 'bg-primary/5' : ''"
              >
                <div class="min-w-0 flex-1">
                  <p class="font-mono text-xs break-all">{{ pkg.name }}</p>
                  <p class="text-[11px] text-muted-foreground">
                    {{ humanSize(pkg.size) }} · {{ formatDate(pkg.modified) }}
                    <template v-if="pkg.counts">
                      · {{ pkg.counts.facilities }} facilities
                      · {{ pkg.counts.branches }} branches
                      · {{ pkg.counts.managers }} managers
                      <template v-if="pkg.options">
                        · images {{ pkg.options.include_media_files ? 'in' : 'out' }}
                      </template>
                    </template>
                  </p>
                </div>
                <button
                  type="button"
                  :class="serverPath === pkg.name ? btnPrimary : btnSecondary"
                  class="!h-8 !px-3 !text-xs"
                  @click="usePackage(pkg)"
                >
                  {{ serverPath === pkg.name ? 'Selected' : 'Use this' }}
                </button>
                <a
                  :href="route('admin.facility.migration.packages.download', { name: pkg.name })"
                  class="text-xs underline text-muted-foreground hover:text-foreground"
                >download</a>
                <button type="button" class="text-xs underline text-destructive" @click="deletePackage(pkg)">
                  delete
                </button>
              </li>
            </ul>
          </div>

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

          <label class="flex items-start gap-2 cursor-pointer text-sm">
            <input type="checkbox" v-model="pruneMissing" class="mt-1" />
            <span>
              Remove branches &amp; managers not in the package
              <span class="block text-xs text-muted-foreground">
                Merge normally only adds and updates. With this on, a facility the package carries
                branches or managers for also loses the ones it holds here that the package does not
                name. A relation the package is silent about is never touched.
              </span>
            </span>
          </label>

          <div v-if="importMode === 'fresh' && !dryRun" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
            <strong class="text-destructive">Fresh mode deletes every existing facility, branch and image file.</strong>
            Deleted images cannot be recovered by a rollback.
            <label class="mt-2 flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmWipe" /> I understand — wipe and replace.
            </label>
          </div>

          <div v-if="importError" class="text-sm text-destructive">{{ importError }}</div>

          <div class="flex flex-wrap gap-2">
            <button type="button" @click="inspectPackage" :disabled="!hasPackage || busy" :class="btnPrimary">
              {{ busy ? 'Reading…' : 'Review branches & import' }}
            </button>
          </div>
          <p class="text-xs text-muted-foreground">
            Every package is opened for review first. The import cannot start while any branch still
            has a name shared by other branches, a missing city or governorate, or a phone number
            with a space or over {{ PHONE_MAX }} characters.
          </p>

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
                <span v-if="previewCounts.managersNew" class="rounded bg-emerald-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.managersNew }} new manager{{ previewCounts.managersNew === 1 ? '' : 's' }}
                </span>
                <span v-if="previewCounts.managersExisting" class="rounded bg-amber-600 px-2 py-0.5 font-semibold text-white">
                  {{ previewCounts.managersExisting }} manager{{ previewCounts.managersExisting === 1 ? '' : 's' }}
                  already here
                </span>
                <span v-if="previewCounts.missingBranches" :class="['rounded px-2 py-0.5 font-semibold text-white', pruningNow ? 'bg-red-600' : 'bg-slate-500']">
                  {{ previewCounts.missingBranches }} branch{{ previewCounts.missingBranches === 1 ? '' : 'es' }}
                  here but not in the package — {{ pruningNow ? 'will be deleted' : 'kept' }}
                </span>
                <span v-if="previewCounts.missingManagers" :class="['rounded px-2 py-0.5 font-semibold text-white', pruningNow ? 'bg-red-600' : 'bg-slate-500']">
                  {{ previewCounts.missingManagers }} manager{{ previewCounts.missingManagers === 1 ? '' : 's' }}
                  here but not in the package — {{ pruningNow ? 'will be deleted' : 'kept' }}
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

          <!-- AI: fill the Arabic name / address from the English one. -->
          <div v-if="aiConfigured" class="rounded-lg border border-border p-3 space-y-2">
            <div class="flex flex-wrap items-center gap-3 text-xs">
              <span class="font-semibold uppercase tracking-wide text-muted-foreground">Translate</span>
              <span class="text-muted-foreground">
                Fill every empty Arabic facility name, branch name and branch address from its English value.
              </span>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="checkbox" v-model="bulkTranslateOverwrite" />
                also overwrite Arabic that is already filled
              </label>
              <button
                type="button"
                @click="runBulkTranslate"
                :disabled="bulkTranslate.phase === 'running'"
                class="rounded-md border border-border bg-background px-3 py-1 font-medium hover:bg-muted disabled:opacity-50"
              >
                {{ bulkTranslate.phase === 'running' ? 'Translating…' : 'Fill Arabic from English' }}
              </button>
              <button
                v-if="bulkTranslate.phase === 'running'"
                type="button"
                @click="cancelBulkTranslate"
                class="rounded-md border border-border bg-background px-3 py-1 font-medium hover:bg-muted"
              >
                Stop
              </button>
            </div>
            <div v-if="bulkTranslate.phase !== 'idle'" class="space-y-1">
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary transition-all"
                  :style="{ width: (bulkTranslate.total ? Math.round(bulkTranslate.processed / bulkTranslate.total * 100) : 100) + '%' }"
                ></div>
              </div>
              <p class="text-[11px] text-muted-foreground">
                {{ bulkTranslate.processed }} / {{ bulkTranslate.total }} fields
                <span v-if="bulkTranslate.wait" class="text-amber-600 dark:text-amber-400"> · {{ bulkTranslate.wait }}</span>
                <span v-else-if="bulkTranslate.phase === 'done'"> · done</span>
              </p>
            </div>
            <p v-if="translateNote" class="text-[11px] text-emerald-600 dark:text-emerald-400">Set: {{ translateNote }}</p>
            <p v-if="translateError" class="text-[11px] text-destructive break-all">{{ translateError }}</p>
            <pre
              v-if="translateDebug"
              class="max-h-40 overflow-auto rounded bg-muted p-2 text-[10px] leading-tight text-foreground"
            >{{ JSON.stringify(translateDebug, null, 2) }}</pre>
          </div>

          <!-- What the colours in the table mean. -->
          <div class="rounded-lg border border-border p-3 space-y-2">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px]">
              <span class="font-semibold uppercase tracking-wide text-muted-foreground">Colours</span>
              <span class="flex items-center gap-1.5">
                <span class="rounded bg-emerald-600 px-1.5 py-0.5 font-semibold text-white">added</span>
                not on this site yet — the import creates it
              </span>
              <span class="flex items-center gap-1.5">
                <span class="rounded bg-amber-600 px-1.5 py-0.5 font-semibold text-white">updated</span>
                matched a row here; amber inputs are the values that change
              </span>
              <span class="flex items-center gap-1.5">
                <span class="rounded bg-red-600 px-1.5 py-0.5 font-semibold text-white">deleted</span>
                here today, absent from the package
              </span>
              <span class="flex items-center gap-1.5">
                <span class="rounded bg-slate-500 px-1.5 py-0.5 font-semibold text-white">kept</span>
                absent from the package, left alone
              </span>
            </div>
            <label v-if="importMode === 'merge'" class="flex items-start gap-2 cursor-pointer text-xs">
              <input type="checkbox" v-model="pruneMissing" class="mt-0.5" />
              <span>
                Remove branches &amp; managers not in the package
                <span class="block text-[11px] text-muted-foreground">
                  Off, they stay and show as “kept”. On, they turn red and the import deletes them.
                  A facility whose branches or managers the package does not carry at all is never pruned.
                </span>
              </span>
            </label>
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
                  <th class="px-2 py-2 text-left font-semibold w-44">Sales rep</th>
                  <th class="px-2 py-2 text-left font-semibold w-24">Discount %</th>

                  <th class="px-2 py-2 text-left font-semibold w-20">Branches</th>
                  <th class="px-2 py-2 text-left font-semibold w-20">Managers</th>
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
                        v-if="(facility.branches || []).length || missingBranches(facility).length"
                        type="button"
                        @click="facility._showBranches = !facility._showBranches"
                        :class="['block mx-auto mt-0.5 text-[10px] px-1.5 py-0.5 rounded font-semibold', branchBadgeCls(facility)]"
                        :title="facilityBranchIssues(facility).join('\n') || 'Every branch is ready to import'"
                      >
                        {{ (facility.branches || []).length }} br<template v-if="missingBranches(facility).length">
                          −{{ missingBranches(facility).length }}</template>
                      </button>
                      <button
                        v-if="(facility.managers || []).length || missingManagers(facility).length"
                        type="button"
                        @click="facility._showManagers = !facility._showManagers"
                        :class="['block mx-auto mt-0.5 text-[10px] px-1.5 py-0.5 rounded font-semibold', managerBadgeCls(facility)]"
                        :title="facilityManagerIssues(facility).join('\n') || 'Every manager is ready to import'"
                      >
                        {{ (facility.managers || []).length }} mgr<template v-if="missingManagers(facility).length">
                          −{{ missingManagers(facility).length }}</template>
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
                        @input="rematchFacility(facility)"
                        :class="previewFieldCls(facility._existing, 'name.en', facility.name.en)"
                      />
                      <ExistingValueHint :existing="facility._existing" path="name.en" :current="facility.name.en" />
                    </td>
                    <td class="px-2 py-1 align-top">
                      <input
                        v-model="facility.name.ar"
                        @input="rematchFacility(facility)"
                        :class="previewFieldCls(facility._existing, 'name.ar', facility.name.ar)"
                      />
                      <ExistingValueHint :existing="facility._existing" path="name.ar" :current="facility.name.ar" />
                      <button
                        v-if="canTranslate(facility, 'name')"
                        type="button"
                        @click="translateField(facility, 'name', 'name', `f${facility._index}-name`)"
                        :disabled="!!translating[`f${facility._index}-name`]"
                        class="mt-1 w-full rounded border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground hover:bg-muted disabled:opacity-50"
                      >
                        {{ translateBtnLabel(`f${facility._index}-name`) }}
                      </button>
                      <p
                        v-if="translateError && lastTranslateKey === `f${facility._index}-name`"
                        class="mt-0.5 text-[10px] leading-tight text-destructive"
                      >{{ translateError }}</p>
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
                    <td class="px-2 py-1 align-top">
                      <SearchableSelect
                        class="min-w-[10rem]"
                        :model-value="facility._salesChoice"
                        :options="salesPickerOptions(facility)"
                        :search-keys="LOOKUP_SEARCH_KEYS"
                        placeholder="— no sales rep —"
                        @change="setFacilitySales(facility, $event)"
                      />
                      <ExistingValueHint
                        :existing="facility._existing"
                        path="sales"
                        :current="facility._salesChoice"
                        choice
                      />
                      <p
                        v-if="facility._salesChoice === NEW_LOOKUP"
                        class="mt-1 text-[10px] leading-tight text-amber-600 dark:text-amber-400"
                      >
                        “{{ facility._salesLabel }}” is not one of your sales reps — importing adds them.
                        <button
                          type="button"
                          class="underline font-medium hover:text-amber-800 dark:hover:text-amber-200 ml-0.5"
                          :disabled="quickCreateLoading === `sales-${facility.name?.en || facility.name?.ar}`"
                          @click="quickCreateLookup(facility, 'sales')"
                        >
                          {{ quickCreateLoading === `sales-${facility.name?.en || facility.name?.ar}` ? 'Creating…' : 'Create now' }}
                        </button>
                        to pick them on the other rows too.
                      </p>
                    </td>
                    <td class="px-2 py-1 align-top">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="—"
                        v-model="facility.discount_percent"
                        :class="previewFieldCls(facility._existing, 'discount_percent', facility.discount_percent, false, true)"
                      />
                      <ExistingValueHint
                        :existing="facility._existing"
                        path="discount_percent"
                        :current="facility.discount_percent"
                        numeric
                      />
                    </td>
                    <td class="px-2 py-1 text-center text-muted-foreground">{{ (facility.branches || []).length }}</td>
                    <td class="px-2 py-1 text-center text-muted-foreground">{{ (facility.managers || []).length }}</td>
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
                  <tr v-if="facility._showBranches && ((facility.branches || []).length || missingBranches(facility).length)" class="border-b border-border">
                    <td colspan="12" class="p-3">
                      <!-- Scrolls rather than clips: the address column is wide on
                           purpose, and hidden overflow would cut it off. -->
                      <div class="bg-card border border-violet-500 rounded-md overflow-x-auto">
                        <div class="bg-violet-600 text-white px-3 py-2 text-xs font-semibold flex flex-wrap items-center gap-2">
                          <span>
                            Branches for {{ facility.name?.en || 'facility' }} ({{ (facility.branches || []).length }})
                            <template v-if="missingBranches(facility).length">
                              + {{ missingBranches(facility).length }} here but not in the package
                            </template>
                          </span>
                          <!-- The same fix as the per-row button, applied to every branch
                               that needs it. Only shown when there is something to fix. -->
                          <button
                            v-if="facilityRepeatedBranches(facility).length"
                            type="button"
                            @click="fixRepeatedBranchNames(facility)"
                            class="ml-auto rounded bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white hover:bg-amber-600"
                          >
                            {{ facilityRepeatedBranches(facility).length }} branches share a name — add cities to all
                          </button>
                          <span
                            class="px-1.5 py-0.5 rounded text-[10px]"
                            :class="[
                              facilityRepeatedBranches(facility).length ? '' : 'ml-auto',
                              facilityBranchIssues(facility).length ? 'bg-red-700' : 'bg-emerald-700',
                            ]"
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
                              <th class="px-3 py-1.5 text-left font-semibold">City</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Governorate</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Phones <span class="font-normal opacity-70">(one per line)</span></th>
                              <th class="px-3 py-1.5 text-left font-semibold">Lat</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Lng</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Google URL</th>
                              <!-- Last, and the widest column on the row: an address is a
                                   sentence, and a 12rem cell shows the first three words of
                                   it. Nothing follows it but the remove button, so it can
                                   take the leftover width without squeezing anything. -->
                              <th class="px-3 py-1.5 text-left font-semibold w-[28rem]">Address</th>
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
                                  @input="rematchFacility(facility)"
                                  :class="[
                                    branchIssues(br).length
                                      ? [previewInputCls, 'border-red-500']
                                      : previewFieldCls(br._existing, 'name.en', br.name.en),
                                    'min-w-[18rem]',
                                  ]"
                                />
                                <p v-if="branchIssues(br).length" class="mt-0.5 text-[10px] leading-tight text-red-600 dark:text-red-400">
                                  {{ branchIssues(br).join(' · ') }}
                                </p>
                                <ExistingValueHint :existing="br._existing" path="name.en" :current="br.name.en" />

                                <!-- Re-answered as you type, in either name box. It names the
                                     spelling that is still tied, because editing only the
                                     English name of a sheet that repeats both leaves the row
                                     genuinely ambiguous — and a flag that just stayed on
                                     would look like a check that had missed the edit. -->
                                <p
                                  v-if="branchNameRepeated(facility, br)"
                                  class="mt-0.5 text-[10px] leading-tight text-amber-600 dark:text-amber-400"
                                >
                                  {{ repeatedLocaleLabel(facility, br) }} name is shared by
                                  {{ repeatedBranchCount(facility, br) }} branches here.
                                </p>
                                <p v-else class="mt-0.5 text-[10px] leading-tight text-emerald-600 dark:text-emerald-400">
                                  Name is unique here.
                                </p>

                                <!-- Always here, so the city can be pasted onto any name;
                                     amber when another branch of this facility carries the
                                     same name and it therefore has to be used. -->
                                <button
                                  type="button"
                                  :disabled="!branchHasCity(br)"
                                  :title="!branchHasCity(br)
                                    ? 'Pick a city first — there is nothing to add yet'
                                    : (branchNameRepeated(facility, br)
                                      ? `The ${repeatedLocaleLabel(facility, br)} name is shared by ${repeatedBranchCount(facility, br)} branches here — add the city to tell them apart`
                                      : 'Optional: add the city to this branch name')"
                                  @click="appendCityToBranchName(br)"
                                  :class="[
                                    'mt-1 w-full rounded px-1.5 py-0.5 text-[10px] font-semibold leading-tight transition',
                                    'disabled:cursor-not-allowed disabled:opacity-40',
                                    branchNameRepeated(facility, br)
                                      ? 'bg-amber-500 text-white hover:bg-amber-600'
                                      : 'border border-border text-muted-foreground hover:bg-muted',
                                  ]"
                                >
                                  {{ branchNameRepeated(facility, br)
                                    ? `repeated ${repeatedLocaleLabel(facility, br)} — add city${branchHasCity(br) ? ' “' + branchCityNames(br).ar + '”' : ''}`
                                    : '+ add city to name (optional)' }}
                                </button>
                              </td>
                              <td class="px-3 py-1">
                                <input
                                  v-model="br.name.ar"
                                  @input="rematchFacility(facility)"
                                  :class="[previewFieldCls(br._existing, 'name.ar', br.name.ar), 'min-w-[18rem]']"
                                />
                                <ExistingValueHint :existing="br._existing" path="name.ar" :current="br.name.ar" />
                                <button
                                  v-if="canTranslate(br, 'name')"
                                  type="button"
                                  @click="translateField(br, 'name', 'name', `f${facility._index}-b${bi}-name`)"
                                  :disabled="!!translating[`f${facility._index}-b${bi}-name`]"
                                  class="mt-1 w-full rounded border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground hover:bg-muted disabled:opacity-50"
                                >
                                  {{ translateBtnLabel(`f${facility._index}-b${bi}-name`) }}
                                </button>
                                <p
                                  v-if="translateError && lastTranslateKey === `f${facility._index}-b${bi}-name`"
                                  class="mt-0.5 text-[10px] leading-tight text-destructive"
                                >{{ translateError }}</p>
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
                                    @click="quickCreateLookup(br, 'city')"
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
                                    @click="quickCreateLookup(br, 'governorate')"
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
                                <textarea
                                  :value="br._phoneText"
                                  @input="setBranchPhones(br, $event.target.value)"
                                  rows="3"
                                  placeholder="One phone per line"
                                  :class="[
                                    phoneIssues(br.phone).length
                                      ? [previewInputCls, 'border-red-500']
                                      : previewFieldCls(br._existing, 'phone', br.phone),
                                    'min-w-[12rem] resize-y font-mono leading-tight',
                                  ]"
                                ></textarea>
                                <p
                                  v-for="(issue, ii) in phoneIssues(br.phone)"
                                  :key="ii"
                                  class="mt-0.5 text-[10px] leading-tight text-red-600 dark:text-red-400"
                                >
                                  {{ issue }}
                                </p>
                                <p v-if="!phoneIssues(br.phone).length && (br.phone || []).length > 1" class="mt-0.5 text-[10px] text-muted-foreground">
                                  {{ br.phone.length }} numbers
                                </p>
                                <ExistingValueHint :existing="br._existing" path="phone" :current="br.phone" />
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
                              <td class="px-3 py-1">
                                <textarea
                                  v-model="br.address.en"
                                  rows="3"
                                  placeholder="Address (EN)"
                                  :class="[
                                    previewFieldCls(br._existing, 'address.en', br.address.en),
                                    'min-w-[26rem] resize-y leading-snug',
                                  ]"
                                ></textarea>
                                <ExistingValueHint :existing="br._existing" path="address.en" :current="br.address.en" />
                                <button
                                  v-if="canTranslate(br, 'address')"
                                  type="button"
                                  @click="translateField(br, 'address', 'address', `f${facility._index}-b${bi}-address`)"
                                  :disabled="!!translating[`f${facility._index}-b${bi}-address`]"
                                  class="mt-1 rounded border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground hover:bg-muted disabled:opacity-50"
                                >
                                  {{ translateBtnLabel(`f${facility._index}-b${bi}-address`) }}
                                </button>
                                <p
                                  v-if="translateError && lastTranslateKey === `f${facility._index}-b${bi}-address`"
                                  class="mt-0.5 text-[10px] leading-tight text-destructive"
                                >{{ translateError }}</p>
                                <textarea
                                  v-model="br.address.ar"
                                  rows="2"
                                  dir="rtl"
                                  placeholder="Address (AR)"
                                  :class="[
                                    previewFieldCls(br._existing, 'address.ar', br.address.ar),
                                    'mt-1 min-w-[26rem] resize-y leading-snug',
                                  ]"
                                ></textarea>
                                <ExistingValueHint :existing="br._existing" path="address.ar" :current="br.address.ar" />
                              </td>
                              <td class="px-3 py-1 text-center">
                                <button type="button" @click="facility.branches.splice(bi, 1)" class="text-red-600 hover:underline text-[10px]">remove</button>
                              </td>
                            </tr>

                            <!-- On this site, absent from the package. Read-only:
                                 there is nothing to edit, only a fate to show. -->
                            <tr
                              v-for="ex in missingBranches(facility)"
                              :key="`missing-branch-${ex.id}`"
                              :class="['border-t border-border align-top', pruningNow ? 'bg-red-500/10' : 'bg-muted/40']"
                            >
                              <td class="px-3 py-1.5">
                                <span :class="['mb-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold', missingRowCls]">
                                  {{ missingRowLabel }} #{{ ex.id }}
                                </span>
                                <p class="text-[11px]">{{ ex.name?.en || ex.name?.ar || '—' }}</p>
                              </td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.name?.ar || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.city?.label || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.governorate?.label || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px] font-mono whitespace-pre-line">{{ (ex.phone || []).join('\n') || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.latitude ?? '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.longitude ?? '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px] break-all">{{ ex.google_location_url || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.address?.en || ex.address?.ar || '—' }}</td>
                              <td class="px-3 py-1.5"></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>

                  <!-- Managers sub-table -->
                  <tr v-if="facility._showManagers && ((facility.managers || []).length || missingManagers(facility).length)" class="border-b border-border">
                    <td colspan="12" class="p-3">
                      <div class="bg-card border border-sky-500 rounded-md overflow-hidden">
                        <div class="bg-sky-600 text-white px-3 py-2 text-xs font-semibold flex flex-wrap items-center gap-2">
                          <span>
                            Managers for {{ facility.name?.en || 'facility' }} ({{ (facility.managers || []).length }})
                            <template v-if="missingManagers(facility).length">
                              + {{ missingManagers(facility).length }} here but not in the package
                            </template>
                          </span>
                          <span
                            class="ml-auto px-1.5 py-0.5 rounded text-[10px]"
                            :class="facilityManagerIssues(facility).length ? 'bg-red-700' : 'bg-emerald-700'"
                          >
                            {{ facilityManagerIssues(facility).length
                              ? facilityManagerIssues(facility).length + ' to check'
                              : 'all good' }}
                          </span>
                        </div>
                        <table class="w-full text-[11px]">
                          <thead class="bg-muted">
                            <tr>
                              <th class="px-3 py-1.5 text-left font-semibold">Manager name</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Position</th>
                              <th class="px-3 py-1.5 text-left font-semibold">Phones <span class="font-normal opacity-70">(one per line)</span></th>
                              <th class="px-3 py-1.5 w-12"></th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(mg, mi) in facility.managers"
                              :key="mi"
                              class="border-t border-border align-top"
                              :class="managerIssues(mg).length ? 'bg-red-500/5' : ''"
                            >
                              <td class="px-3 py-1">
                                <span :class="['mb-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold', rowStateCls(mg)]">
                                  {{ rowStateLabel(mg) }}
                                  <template v-if="mg._existing">#{{ mg._existing.id }}</template>
                                </span>
                                <p v-if="managerMovedFrom(facility, mg)" class="mb-1 text-[10px] leading-tight text-amber-600 dark:text-amber-400">
                                  Listed under facility #{{ managerMovedFrom(facility, mg) }} today — importing moves them here.
                                </p>
                                <input
                                  v-model="mg.name"
                                  placeholder="Required"
                                  :class="managerIssues(mg).length
                                    ? [previewInputCls, 'border-red-500']
                                    : previewFieldCls(mg._existing, 'name', mg.name)"
                                />
                                <p v-if="managerIssues(mg).length" class="mt-0.5 text-[10px] leading-tight text-red-600 dark:text-red-400">
                                  {{ managerIssues(mg).join(' · ') }}
                                </p>
                                <ExistingValueHint :existing="mg._existing" path="name" :current="mg.name" />
                              </td>
                              <td class="px-3 py-1">
                                <input
                                  v-model="mg.position"
                                  placeholder="e.g. General Manager"
                                  :class="previewFieldCls(mg._existing, 'position', mg.position)"
                                />
                                <ExistingValueHint :existing="mg._existing" path="position" :current="mg.position" />
                              </td>
                              <td class="px-3 py-1">
                                <textarea
                                  :value="mg._phonesText"
                                  @input="setManagerPhones(mg, $event.target.value)"
                                  rows="3"
                                  placeholder="One phone per line"
                                  :class="[
                                    phoneIssues(mg.phones).length
                                      ? [previewInputCls, 'border-red-500']
                                      : previewFieldCls(mg._existing, 'phones', mg.phones),
                                    'min-w-[12rem] resize-y font-mono leading-tight',
                                  ]"
                                ></textarea>
                                <p
                                  v-for="(issue, ii) in phoneIssues(mg.phones)"
                                  :key="ii"
                                  class="mt-0.5 text-[10px] leading-tight text-red-600 dark:text-red-400"
                                >
                                  {{ issue }}
                                </p>
                                <p v-if="!phoneIssues(mg.phones).length && (mg.phones || []).length > 1" class="mt-0.5 text-[10px] text-muted-foreground">
                                  {{ mg.phones.length }} numbers
                                </p>
                                <ExistingValueHint :existing="mg._existing" path="phones" :current="mg.phones" />
                              </td>
                              <td class="px-3 py-1 text-center">
                                <button type="button" @click="facility.managers.splice(mi, 1)" class="text-red-600 hover:underline text-[10px]">remove</button>
                              </td>
                            </tr>

                            <tr
                              v-for="ex in missingManagers(facility)"
                              :key="`missing-manager-${ex.id}`"
                              :class="['border-t border-border align-top', pruningNow ? 'bg-red-500/10' : 'bg-muted/40']"
                            >
                              <td class="px-3 py-1.5">
                                <span :class="['mb-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold', missingRowCls]">
                                  {{ missingRowLabel }} #{{ ex.id }}
                                </span>
                                <p class="text-[11px]">{{ ex.name || '—' }}</p>
                              </td>
                              <td class="px-3 py-1.5 text-[11px]">{{ ex.position || '—' }}</td>
                              <td class="px-3 py-1.5 text-[11px] font-mono whitespace-pre-line">{{ (ex.phones || []).join('\n') || '—' }}</td>
                              <td class="px-3 py-1.5"></td>
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

          <div
            v-if="hasBlockingIssues"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs space-y-1"
          >
            <p class="font-semibold text-destructive">
              Fix {{ blockingIssues.length }} branch problem{{ blockingIssues.length === 1 ? '' : 's' }} before importing:
            </p>
            <ul class="list-disc pl-4 space-y-0.5 max-h-32 overflow-y-auto">
              <li v-for="(issue, i) in blockingIssues.slice(0, 20)" :key="i">{{ issue }}</li>
            </ul>
            <p v-if="blockingIssues.length > 20" class="text-muted-foreground">
              …and {{ blockingIssues.length - 20 }} more.
            </p>
          </div>

          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
            <button type="button" @click="cancelPreview" :class="btnSecondary">Back</button>
            <div v-if="importError" class="text-xs text-destructive">{{ importError }}</div>
            <button
              type="button"
              @click="startImportFromPreview"
              :disabled="busy || previewData.facilities.length === 0 || hasLookupIssues || hasBlockingIssues || (importMode === 'fresh' && !dryRun && !confirmWipe)"
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

          <div class="flex flex-wrap gap-2 text-sm">
            <span v-for="(v, k) in progress.stats" :key="k" class="px-3 py-1.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-medium border border-emerald-500/20">
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
          <!-- The counts a whole import is judged by, so they sit on a surface of
               their own. A tinted panel would let the page's purple gradient
               through and leave dark ink on a light-on-dark blend — the same trap
               previewInputBase warns about — so the card paints an opaque
               background and names its own ink, and the chips are solid rather
               than a 15% wash. -->
          <div class="bg-card text-card-foreground border border-border rounded-xl p-4 space-y-3">
            <h3 class="text-lg font-semibold text-card-foreground">
              {{ result?.dry_run ? 'Dry run complete — nothing was written' : 'Import complete' }}
            </h3>
            <div class="flex flex-wrap gap-2 text-sm">
              <span
                v-for="(v, k) in result?.stats || {}"
                :key="k"
                :class="[
                  'inline-flex items-baseline gap-1.5 rounded-md px-2.5 py-1 font-medium shadow-sm',
                  result?.dry_run ? 'bg-amber-600 text-white' : 'bg-emerald-600 text-white',
                ]"
              >
                <span class="opacity-90">{{ String(k).replace(/_/g, ' ') }}</span>
                <span class="text-base font-bold tabular-nums">{{ v }}</span>
              </span>
              <span
                v-if="!Object.keys(result?.stats || {}).length"
                class="rounded-md bg-muted px-2.5 py-1 font-medium text-foreground"
              >
                nothing changed
              </span>
            </div>

            <details v-if="result?.warnings?.length" class="rounded-lg border border-amber-500/60 bg-amber-500/10 p-3 text-xs">
              <summary class="cursor-pointer font-semibold text-amber-700 dark:text-amber-300">
                {{ result.warnings.length }} warning(s)
              </summary>
              <p v-for="(w, i) in result.warnings" :key="i" class="mt-1 text-card-foreground">{{ w }}</p>
            </details>

            <details v-if="result?.errors?.length" class="rounded-lg border border-destructive/60 bg-destructive/10 p-3 text-xs">
              <summary class="cursor-pointer font-semibold text-destructive">{{ result.errors.length }} failure(s)</summary>
              <p v-for="(e, i) in result.errors" :key="i" class="mt-1 text-card-foreground">{{ e.facility }}: {{ e.message }}</p>
            </details>
          </div>

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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  facilityTypes: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  salesOptions: { type: Array, default: () => [] },
  aiConfigured: { type: Boolean, default: false },
});

const tabs = [
  { key: 'export', label: 'Export' },
  { key: 'import', label: 'Import' },
];

/* ------------------------------ tab in the url ----------------------------- */

/* The open tab is written into ?tab=, so a reload, a bookmark or a link shared
   with somebody comes back to the pane it was sent from — reaching the import
   side otherwise took a click every time.
 *
 * Rewritten in place rather than pushed: a tab is a view of one page, not a step
 * worth walking back through, and pushing one entry per click would turn Back
 * into an undo of tab switches. Inertia owns this history stack, so its own
 * state object travels along with the rewrite and its restore still works. */
const TAB_PARAM = 'tab';

const tabFromUrl = () => {
  if (typeof window === 'undefined') return tabs[0].key;

  const key = new URLSearchParams(window.location.search).get(TAB_PARAM);

  return tabs.some(t => t.key === key) ? key : tabs[0].key;
};

const activeTab = ref(tabFromUrl());

const syncTabToUrl = (key) => {
  if (typeof window === 'undefined') return;

  const url = new URL(window.location.href);
  if (url.searchParams.get(TAB_PARAM) === key) return; // already says so

  url.searchParams.set(TAB_PARAM, key);
  window.history.replaceState(window.history.state, '', url);
};

// Covers the buttons and the programmatic switches alike — goToPackages() sets
// the tab straight, and the url has to follow it there too.
watch(activeTab, syncTabToUrl);

// Back and forward, or anything else that rewrites the query underneath us.
const readTabFromUrl = () => { activeTab.value = tabFromUrl(); };
onMounted(() => {
  syncTabToUrl(activeTab.value); // stamp the default on a bare url
  window.addEventListener('popstate', readTabFromUrl);
});
onUnmounted(() => window.removeEventListener('popstate', readTabFromUrl));

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
// A package can be narrowed to the facilities alone. What is left out is not
// hidden but absent: the other site's own branches and managers stay as they are.
const includeBranches = ref(true);
const includeManagers = ref(true);
// Kept packages land in storage/app/facility-migration and are offered on the
// Import tab, so a package exported here can be restored here later without a
// round trip through the browser's upload limit.
const keepOnServer = ref(true);
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
  params.set('include_branches', includeBranches.value ? '1' : '0');
  params.set('include_managers', includeManagers.value ? '1' : '0');
  params.set('keep', keepOnServer.value ? '1' : '0');
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

/* The download is a plain link, so there is no response to wait on — give the
   server a moment to finish writing the kept copy, then re-read the library. */
const noteExport = () => {
  if (!keepOnServer.value) return;
  setTimeout(loadPackages, 2500);
};

const goToPackages = async () => {
  activeTab.value = 'import';
  await loadPackages();
  await nextTick();
  packagesSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
/* Merge only adds and updates unless this is on, in which case a facility whose
   branches or managers the package does carry also loses the ones here it does
   not name. Off by default — deleting is never the quiet option. */
const pruneMissing = ref(false);
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

/* ------------------------- packages on this server ------------------------ */

const packages = ref([]);
const packagesLoading = ref(false);
const packagesSection = ref(null);

const loadPackages = async () => {
  packagesLoading.value = true;
  try {
    const { data } = await axios.get(route('admin.facility.migration.packages.index'));
    packages.value = data.packages || [];
  } catch (e) {
    packages.value = [];
  } finally {
    packagesLoading.value = false;
  }
};

onMounted(loadPackages);

// Picking one is the same as typing its name into the server-path box — which
// is what the import endpoints already read.
const usePackage = (pkg) => {
  serverPath.value = pkg.name;
  packageFile.value = null;
  inspection.value = null;
  importError.value = '';
};

const deletePackage = async (pkg) => {
  if (!confirm(`Delete ${pkg.name} from this server? The file is gone for good.`)) return;
  try {
    const { data } = await axios.delete(route('admin.facility.migration.packages.destroy'), {
      data: { name: pkg.name },
    });
    packages.value = data.packages || [];
    if (serverPath.value === pkg.name) serverPath.value = '';
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not delete that package.';
  }
};

const humanSize = (bytes) => {
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = Number(bytes) || 0;
  let i = 0;
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024;
    i++;
  }

  return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

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
const PHONE_MAX = 20;

const asPhoneList = (raw) => {
  const flat = Array.isArray(raw)
    ? raw.map(p => (p === null || p === undefined ? '' : String(p))).join('\n')
    : String(raw ?? '');

  // One number per entry: split on newline / comma / semicolon / pipe / slash
  // and on a spaced hyphen, then drop the grouping spaces some numbers were
  // typed with ("066 3400006" -> "0663400006").
  return flat
    .split(/[\r\n,;|/\\]+|\s+[-–—]\s+/)
    .map(p => p.replace(/\s+/g, ''))
    .filter(p => p !== '');
};

/* The typed text is kept as typed — blank lines and all — so the caret never
   jumps while editing; the list is re-read from it on every keystroke. */
const setBranchPhones = (branch, text) => {
  branch._phoneText = text;
  branch.phone = text.split(/\r?\n/).map(p => p.trim()).filter(p => p !== '');
};

// A manager holds the same list of numbers, under the column's own name.
const setManagerPhones = (manager, text) => {
  manager._phonesText = text;
  manager.phones = text.split(/\r?\n/).map(p => p.trim()).filter(p => p !== '');
};

/* --------------------- AI: translate English → Arabic --------------------- */

/* A spreadsheet often fills only the English column. These buttons send the
   English value the operator is looking at to Gemini and write the Arabic it
   returns straight back into the Arabic input — one field at a time, or every
   empty one at once. Nothing is saved until the import runs. */
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Gemini's free tier is ~15 requests/minute — on a 429 the sweep waits out a
// short visible countdown and retries the same slice, like the other AI sweeps.
const RATE_LIMIT_WAIT_SECONDS = 10;

// String keys of the single fields currently being translated, so each button
// can show its own spinner without a per-row ref.
const translating = ref({});
const translateError = ref('');
// Last raw payload the translate endpoint returned — shown in the panel so a
// shape mismatch is visible without opening the console.
const translateDebug = ref(null);
const translateNote = ref('');
// The key of the field whose button was last pressed, so its own error can be
// shown next to it and not only up in the panel.
const lastTranslateKey = ref('');

// Google hands back "Please retry in 12.3s" — use its number when it gives one.
const retrySecondsFrom = (message, fallback) => {
  const m = /retry in ([\d.]+)s/i.exec(String(message || ''));

  return m ? Math.min(90, Math.ceil(parseFloat(m[1])) + 1) : fallback;
};

/**
 * One round trip. Returns { translations: string[] } aligned to `items`, or
 * { rateLimited: true } when the quota for this minute is spent.
 */
const translateBatch = async (items) => {
  try {
    const { data } = await axios.post(route('admin.facility.migration.translate'), { items });
    translateDebug.value = data;
    // eslint-disable-next-line no-console
    console.log('Migration translate — raw response:', data);
    if (data && data.rate_limited) return { rateLimited: true, message: data.message };

    return { translations: (data && data.translations) || [], debug: data ? data.debug : undefined, raw: data };
  } catch (e) {
    if (e.response?.status === 429) {
      return { rateLimited: true, message: e.response?.data?.message };
    }
    throw new Error(e.response?.data?.message || 'The translation request failed.');
  }
};

// Read/write the en/ar sides of a name or address, whichever the button sits on.
const localePair = (owner, path) => {
  if (!owner[path] || typeof owner[path] !== 'object') owner[path] = { en: '', ar: '' };

  return owner[path];
};

// Read-only — never touch state from here, it runs during render.
const canTranslate = (owner, path) =>
  props.aiConfigured && String(owner?.[path]?.en || '').trim() !== '';

const translateBtnLabel = (key) => {
  const state = translating.value[key];
  if (typeof state === 'string') return state;

  return state ? 'translating…' : 'AR ← translate EN';
};

const translateField = async (owner, path, kind, key) => {
  if (translating.value[key] || !canTranslate(owner, path)) return;

  const source = String(localePair(owner, path).en || '').trim();
  if (source === '') return;

  translating.value[key] = true;
  translateError.value = '';
  translateNote.value = '';
  lastTranslateKey.value = key;
  try {
    // Gemini's free tier is ~15 requests/minute; on a 429 wait out the window
    // Google names and try again, rather than making the operator re-click.
    for (let attempt = 0; attempt < 4; attempt += 1) {
      const res = await translateBatch([{ text: source, kind }]);

      if (res.rateLimited) {
        const wait = retrySecondsFrom(res.message, RATE_LIMIT_WAIT_SECONDS);
        for (let s = wait; s > 0; s -= 1) {
          translateError.value = `AI rate limit reached — retrying in ${s}s…`;
          translating.value[key] = `wait ${s}s`;
          await sleep(1000);
        }
        translating.value[key] = true;
        continue;
      }

      const value = String(res.translations[0] ?? '').trim();
      if (value) {
        localePair(owner, path).ar = value;
        translateNote.value = `“${source}” → “${value}”`;
        translateError.value = '';
      } else {
        translateError.value = `No Arabic in the reply. Raw response: ${JSON.stringify(res.raw).slice(0, 400)}`;
      }

      return;
    }
    translateError.value = 'AI is still rate limited after several tries — wait a minute and retry.';
  } catch (e) {
    translateError.value = e.message;
  } finally {
    delete translating.value[key];
  }
};

/* ------------------------------ bulk sweep ------------------------------ */

const bulkTranslate = ref({ phase: 'idle', processed: 0, total: 0, wait: '' });
const bulkTranslateOverwrite = ref(false);
let bulkTranslateCancel = false;

// Every en → ar job the sweep would run, each carrying the setter for its own
// Arabic field so the answers land back on the right row.
const collectTranslateJobs = () => {
  const jobs = [];
  const consider = (owner, path, kind) => {
    const pair = owner[path];
    const en = String(pair?.en || '').trim();
    const ar = String(pair?.ar || '').trim();
    if (en === '' || (ar !== '' && !bulkTranslateOverwrite.value)) return;
    jobs.push({ text: en, kind, apply: (value) => { localePair(owner, path).ar = value; } });
  };

  previewData.value.facilities.forEach((facility) => {
    consider(facility, 'name', 'name');
    (facility.branches || []).forEach((branch) => {
      consider(branch, 'name', 'name');
      consider(branch, 'address', 'address');
    });
  });

  return jobs;
};

const runBulkTranslate = async () => {
  if (bulkTranslate.value.phase === 'running') return;

  const jobs = collectTranslateJobs();
  bulkTranslateCancel = false;
  translateError.value = '';
  bulkTranslate.value = { phase: 'running', processed: 0, total: jobs.length, wait: '' };

  if (jobs.length === 0) {
    bulkTranslate.value.phase = 'done';

    return;
  }

  // Fewer, fatter calls: 25 strings per request keeps a whole part well under
  // Gemini's free-tier 15 requests/minute even without the auto-retry below.
  const CHUNK = 25;
  let i = 0;
  let filled = 0;
  try {
    while (i < jobs.length) {
      if (bulkTranslateCancel) { bulkTranslate.value.phase = 'idle'; return; }

      const slice = jobs.slice(i, i + CHUNK);
      const res = await translateBatch(slice.map(j => ({ text: j.text, kind: j.kind })));

      if (res.rateLimited) {
        const wait = retrySecondsFrom(res.message, RATE_LIMIT_WAIT_SECONDS);
        for (let s = wait; s > 0 && !bulkTranslateCancel; s -= 1) {
          bulkTranslate.value.wait = `AI rate limit reached — retrying in ${s}s`;
          await sleep(1000);
        }
        bulkTranslate.value.wait = '';
        continue; // same slice
      }

      slice.forEach((job, k) => {
        const ar = String(res.translations[k] ?? '').trim();
        if (ar) { job.apply(ar); filled += 1; }
        bulkTranslate.value.processed += 1;
      });
      i += CHUNK;
      if (i < jobs.length) await sleep(4500);
    }

    if (filled === 0) {
      translateError.value = 'The AI returned nothing usable — check the site logs, or try again.';
    }

    // A fresh Arabic name can change which existing row a facility matches —
    // refresh the new/already-here badges so they still tell the truth.
    for (const facility of previewData.value.facilities) {
      if (bulkTranslateCancel) break;
      await rematchNow(facility);
    }

    bulkTranslate.value.phase = bulkTranslateCancel ? 'idle' : 'done';
  } catch (e) {
    translateError.value = e.message;
    bulkTranslate.value.phase = 'idle';
  }
};

const cancelBulkTranslate = () => { bulkTranslateCancel = true; };

/* ------------------- new / already-here, kept in step ------------------- */

/* The badge the preview first paints comes from the name the package carried;
   renaming a row here to match one this site keeps would leave it saying "new"
   while the import quietly updates the existing row. This re-asks the server —
   with the same matching the import uses — a moment after an edit settles. */
const rematchNow = async (facility) => {
  try {
    const { data } = await axios.post(route('admin.facility.migration.rematch'), {
      data: withoutBookkeeping(facility),
    });
    facility._existing = data.facility || null;
    facility._missing_branches = data.missing_branches || [];
    facility._missing_managers = data.missing_managers || [];
    (facility.branches || []).forEach((b, i) => { b._existing = data.branches?.[i] || null; });
    (facility.managers || []).forEach((m, i) => { m._existing = data.managers?.[i] || null; });
  } catch (e) {
    // A stale badge is not worth interrupting the edit over.
  }
};

const rematchFacility = (facility) => {
  clearTimeout(facility._rematchTimer);
  facility._rematchTimer = setTimeout(() => rematchNow(facility), 700);
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
/* Sales reps are picked the same way — a package naming somebody this site has
   never billed for can add them from the row that needs them. */
const salesList = ref([...props.salesOptions]);

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

/* Quick-create a governorate, city or sales rep that the package named but this
   site does not have yet. The backend deduplicates, so two rows reaching for the
   same missing name both end up pointing at one shared row. */
const quickCreateLoading = ref(null);

// Which list a freshly created row joins, so the pickers see it at once.
const lookupLists = { governorate: governorateList, city: cityList, sales: salesList };

const quickCreateLookup = async (owner, field) => {
  const list = lookupLists[field];
  const label = owner[`_${field}Label`];
  if (!list || !label) return;

  const names = owner[`_${field}Names`] || {};
  const payload = { type: field, name_en: names.en || label, name_ar: names.ar || '' };
  if (field === 'city') {
    payload.governorate_id = owner._governorateChoice;
  }

  const key = `${field}-${owner.name?.en || owner.name?.ar || ''}`;
  quickCreateLoading.value = key;
  try {
    const { data } = await axios.post(route('admin.facility.migration.lookup.store'), payload);
    const option = data.option;

    // Push into the global list so the select can see it.
    if (!list.value.some(o => String(o.value) === String(option.value))) {
      list.value.push(option);
    }

    applyChoice(owner, field, option.value, list.value);

    // If we just created a governorate, the city list changed — re-normalize.
    if (field === 'governorate') {
      normalizeLookup(owner, 'city', owner.city, citiesFor(owner));
    }
  } catch (e) {
    importError.value = e.response?.data?.message || `Could not create the ${field}.`;
  } finally {
    quickCreateLoading.value = null;
  }
};

const setFacilityType = (facility, choice) =>
  applyChoice(facility, 'facility_type', choice, props.facilityTypes);

const setFacilitySales = (facility, choice) =>
  applyChoice(facility, 'sales', choice, salesList.value);

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

const salesPickerOptions = (facility) =>
  withNewOption(salesList.value, facility._salesUnknown, facility._salesLabel);

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

// Managers move the same way — matched by name, they follow the facility they
// are listed under in the package.
const managerMovedFrom = (facility, manager) =>
  (manager._existing && manager._existing.facility_id !== facility._existing?.id
    ? manager._existing.facility_id
    : null);

/* The rows this site holds that the package never names — the preview endpoint
   works them out with the same rule the import uses, so what is painted here is
   exactly what a pruning merge would delete. A facility whose branches (or
   managers) the package does not carry at all yields none: silence about a
   relation is not an instruction to empty it. */
const missingBranches = (facility) => facility._missing_branches || [];
const missingManagers = (facility) => facility._missing_managers || [];

// Fresh mode deletes and recreates everything, so pruning is a merge-only idea.
const pruningNow = computed(() => importMode.value === 'merge' && pruneMissing.value);

const missingRowLabel = computed(() => (pruningNow.value ? 'will be deleted' : 'kept — not in package'));
const missingRowCls = computed(() =>
  (pruningNow.value ? 'bg-red-600 text-white' : 'bg-slate-500 text-white'));

const previewCounts = computed(() => {
  const counts = {
    facilitiesNew: 0, facilitiesExisting: 0,
    branchesNew: 0, branchesExisting: 0,
    managersNew: 0, managersExisting: 0,
    missingBranches: 0, missingManagers: 0,
  };

  previewData.value.facilities.forEach((facility) => {
    counts[isExisting(facility) ? 'facilitiesExisting' : 'facilitiesNew'] += 1;
    (facility.branches || []).forEach((branch) => {
      counts[isExisting(branch) ? 'branchesExisting' : 'branchesNew'] += 1;
    });
    (facility.managers || []).forEach((manager) => {
      counts[isExisting(manager) ? 'managersExisting' : 'managersNew'] += 1;
    });
    counts.missingBranches += missingBranches(facility).length;
    counts.missingManagers += missingManagers(facility).length;
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

  issues.push(...phoneIssues(branch.phone));

  return issues;
};

/* Every entry in a phone list has to be ONE dialable number: no spaces, no
   "/" or "," joining two numbers, and short enough for the column (20 chars).
   Several numbers go on their own lines, never packed into one — the branch
   phone column rejects anything longer and the value cannot be saved. */
const PHONE_SEPARATORS = /[/\\,;|]/;

const phoneIssues = (list) => {
  const issues = [];
  (list || []).forEach((raw) => {
    const phone = String(raw ?? '').trim();
    if (phone === '') return;
    if (PHONE_SEPARATORS.test(phone)) {
      issues.push(`Phone “${phone}” holds more than one number — put each number on its own line`);
    } else if (/\s/.test(phone)) {
      issues.push(`Phone “${phone}” has a space — a single number has no spaces; put each number on its own line`);
    } else if (phone.length > PHONE_MAX) {
      issues.push(`Phone “${phone}” is ${phone.length} characters — a single number is at most ${PHONE_MAX}; put each number on its own line`);
    }
  });

  return issues;
};

/* ------------------------- repeated branch names --------------------------- */

/* A spreadsheet almost always writes the facility's own name into every branch
   row, so a lab with 28 addresses arrives as 28 branches all called "معامل
   ميترا". The import matches a branch by name within its facility, so names
   that repeat are names that cannot tell two branches apart — the city is the
   one column that already distinguishes them, which is what the button pastes
   in. */

// Both spellings a branch answers to, folded — the import matches on either.
const branchNameValues = (branch) =>
  [...new Set(['en', 'ar'].map(l => foldName(branch?.name?.[l])).filter(Boolean))];

// The names more than one branch of this facility carries.
const repeatedBranchNames = (facility) => {
  const counts = new Map();
  (facility.branches || []).forEach((br) => {
    branchNameValues(br).forEach(v => counts.set(v, (counts.get(v) || 0) + 1));
  });

  return new Set([...counts.entries()].filter(([, n]) => n > 1).map(([v]) => v));
};

/* Which of a branch's two spellings another branch here also carries, and how
   many rows carry it.

   Both are reported because the import matches on either spelling, so editing
   only the English name leaves the row genuinely still ambiguous — and a flag
   that stayed on without saying why would read as a check that had not noticed
   the edit. Recomputed on every keystroke: the template calls this while it
   renders, so typing in either name box re-answers it. */
const repeatedBranchNameLocales = (facility, branch) => {
  const counts = new Map();
  (facility.branches || []).forEach((br) => {
    branchNameValues(br).forEach(v => counts.set(v, (counts.get(v) || 0) + 1));
  });

  return ['en', 'ar']
    .map((locale) => ({ locale, value: foldName(branch?.name?.[locale]), }))
    .filter(({ value }) => value && (counts.get(value) || 0) > 1)
    .map(({ locale, value }) => ({ locale, count: counts.get(value) }));
};

const branchNameRepeated = (facility, branch) =>
  repeatedBranchNameLocales(facility, branch).length > 0;

// "EN and AR", "AR" — the spellings a row is still tied on, for the label.
const repeatedLocaleLabel = (facility, branch) =>
  repeatedBranchNameLocales(facility, branch)
    .map(({ locale }) => locale.toUpperCase())
    .join(' + ');

// The most rows any one of this branch's spellings is shared by.
const repeatedBranchCount = (facility, branch) =>
  Math.max(0, ...repeatedBranchNameLocales(facility, branch).map(({ count }) => count));

const facilityRepeatedBranches = (facility) =>
  (facility.branches || []).filter(br => branchNameRepeated(facility, br));

/* The city a branch is set to, in both spellings. Reads the picked row rather
   than the label so a city chosen by hand in the preview is used, not the one
   the sheet happened to name. */
const branchCityNames = (branch) => {
  const name = branch?.city?.name || {};
  const en = String(name.en || '').trim();
  const ar = String(name.ar || '').trim();
  const label = String(branch?._cityLabel || '').trim();

  return { en: en || ar || label, ar: ar || en || label };
};

const branchHasCity = (branch) => {
  const city = branchCityNames(branch);

  return !!(city.en || city.ar);
};

/* Paste the city onto the name, in each spelling the branch has. A name that
   already carries the city is left alone, so pressing the button twice is not
   two suffixes. */
const appendCityToBranchName = (branch) => {
  const city = branchCityNames(branch);
  if (!branch.name) branch.name = { en: '', ar: '' };

  ['en', 'ar'].forEach((locale) => {
    const suffix = city[locale];
    if (!suffix) return;

    const current = String(branch.name[locale] || '').trim();
    if (!current) {
      branch.name[locale] = suffix;

      return;
    }
    if (foldName(current).includes(foldName(suffix))) return;

    branch.name[locale] = `${current} - ${suffix}`;
  });
};

/* Fix every repeated name at once. The city settles most of them; branches that
   share a city too — two in حي الزهور, say — are still tied afterwards, so a
   second pass numbers those, or the button would report a fix it did not make. */
const fixRepeatedBranchNames = (facility) => {
  facilityRepeatedBranches(facility).forEach(appendCityToBranchName);

  const stillRepeated = repeatedBranchNames(facility);
  const used = new Map();
  (facility.branches || []).forEach((br) => {
    if (!branchNameValues(br).some(v => stillRepeated.has(v))) return;

    const key = branchNameValues(br).join('|');
    const n = (used.get(key) || 0) + 1;
    used.set(key, n);
    if (n === 1) return; // the first keeps the plain name

    ['en', 'ar'].forEach((locale) => {
      const current = String(br.name?.[locale] || '').trim();
      if (current) br.name[locale] = `${current} ${n}`;
    });
  });
};

const facilityBranchIssues = (facility) =>
  (facility.branches || []).flatMap((branch, index) =>
    branchIssues(branch).map(issue => `Branch ${index + 1}: ${issue}`)
  );

const branchBadgeCls = (facility) =>
  (facilityBranchIssues(facility).length
    ? 'bg-red-600 text-white'
    : pruningNow.value && missingBranches(facility).length
      ? 'bg-red-600 text-white'
      : 'bg-emerald-600 text-white');

/* A manager needs a name — the import skips a nameless row rather than writing
   a contact nobody can be reached on — and its phones face the same one-number-
   per-line rule the branch phones do. */
const managerIssues = (manager) => {
  const issues = [];
  if (!String(manager.name || '').trim()) {
    issues.push('Manager has no name — this row is skipped');
  }
  issues.push(...phoneIssues(manager.phones));

  return issues;
};

const facilityManagerIssues = (facility) =>
  (facility.managers || []).flatMap((manager, index) =>
    managerIssues(manager).map(issue => `Manager ${index + 1}: ${issue}`)
  );

const managerBadgeCls = (facility) =>
  (facilityManagerIssues(facility).length
    ? 'bg-red-600 text-white'
    : pruningNow.value && missingManagers(facility).length
      ? 'bg-red-600 text-white'
      : 'bg-sky-600 text-white');

const hasLookupIssues = computed(() =>
  previewData.value.facilities.some(f =>
    (f.branches || []).some(b =>
      b._governorateChoice === NEW_LOOKUP || b._cityChoice === NEW_LOOKUP
    )
  )
);

/* Everything the import must not run over: a branch with a red issue (no name,
   no governorate/city, a bad phone) or a name still shared by other branches of
   the same facility, and any manager phone that packs several numbers into one
   entry. Listed with a count so the operator knows what to fix. A nameless
   manager is not here — the import quietly skips that row rather than failing. */
const blockingIssues = computed(() => {
  const out = [];
  previewData.value.facilities.forEach((facility) => {
    const facilityName = facility.name?.en || facility.name?.ar || 'facility';
    (facility.branches || []).forEach((branch, index) => {
      const label = `${facilityName} · branch ${index + 1}`;
      branchIssues(branch).forEach(issue => out.push(`${label}: ${issue}`));
      if (branchNameRepeated(facility, branch)) {
        out.push(`${label}: ${repeatedLocaleLabel(facility, branch)} name is shared by ${repeatedBranchCount(facility, branch)} branches — add the city to tell them apart`);
      }
    });
    (facility.managers || []).forEach((manager, index) => {
      const label = `${facilityName} · manager ${index + 1}`;
      phoneIssues(manager.phones).forEach(issue => out.push(`${label}: ${issue}`));
    });
  });

  return out;
});

const hasBlockingIssues = computed(() => blockingIssues.value.length > 0);

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
          managers: (f.managers || []).map(m => {
            const phones = asPhoneList(m.phones);

            return {
              ...m,
              name: m.name == null ? '' : String(m.name),
              position: m.position == null ? '' : String(m.position),
              phones,
              _phonesText: phones.join('\n'),
            };
          }),
          offers: f.offers || [],
          media: f.media || [],
          tags: f.tags || [],
          _showBranches: false,
          _showManagers: false,
        };
        normalizeLookup(facility, 'facility_type', f.facility_type, props.facilityTypes);
        normalizeLookup(facility, 'sales', f.sales, salesList.value);

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
  if (Array.isArray(clean.managers)) {
    clean.managers = clean.managers.map(withoutBookkeeping);
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
      prune_missing: pruneMissing.value ? 1 : 0,
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
