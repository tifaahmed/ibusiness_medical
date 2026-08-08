<template>
  <AdminUserLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 rounded-xl border border-border py-3 shadow-sm">
          <div class="flex items-center gap-2 px-4 sm:px-6 flex-wrap">
            <Link
              :href="route('admin.admin-users.index')"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-8 sm:h-9 px-2 sm:px-3"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-4 w-4">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">Back</span>
            </Link>
            <div class="title-golden flex items-center gap-2 min-w-0 flex-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
                <circle cx="12" cy="7" r="4"></circle>
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
              </svg>
              <span class="text-sm sm:text-base truncate">
                Admin: <span class="text-muted-foreground font-normal">{{ admin.email }}</span>
              </span>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="downloadPdf"
                :disabled="downloading"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-8 sm:h-9 px-2 sm:px-3 disabled:opacity-50 disabled:pointer-events-none"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span class="hidden sm:inline">{{ downloading ? 'Generating…' : 'Download PDF' }}</span>
                <span class="sm:hidden">PDF</span>
              </button>
              <Link
                :href="route('admin.admin-users.edit', admin.id)"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                  <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                </svg>
                Edit
              </Link>
            </div>
          </div>
        </div>

        <!-- Account details -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
          <div class="px-6">
            <div class="title-golden flex items-center gap-2 mb-3">
              <span class="text-sm font-semibold">Account details</span>
            </div>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <div>
                <dt class="text-xs text-muted-foreground">Name</dt>
                <dd class="text-foreground">{{ admin.name || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Email</dt>
                <dd class="text-foreground break-all">{{ admin.email || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Phone</dt>
                <dd class="text-foreground">{{ admin.phone || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Email verified</dt>
                <dd>
                  <span
                    v-if="admin.email_verified_at"
                    class="inline-flex items-center rounded-md px-1.5 py-0.5 bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-xs"
                  >
                    Verified · {{ admin.email_verified_at }}
                  </span>
                  <span v-else class="text-muted-foreground">Not verified</span>
                </dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Partner</dt>
                <dd class="text-foreground">{{ admin.partner?.title || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Admin ID</dt>
                <dd class="text-foreground font-mono">#{{ admin.id }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Created</dt>
                <dd class="text-foreground">{{ admin.created_at || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">Last updated</dt>
                <dd class="text-foreground">{{ admin.updated_at || '—' }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Roles -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
          <div class="px-6">
            <div class="title-golden flex items-center gap-2 mb-3">
              <span class="text-sm font-semibold">Roles</span>
            </div>
            <div v-if="!admin.roles.length" class="text-sm text-muted-foreground italic">No roles assigned.</div>
            <ul v-else class="space-y-2">
              <li
                v-for="role in admin.roles"
                :key="role"
                class="rounded-md border border-border bg-background/40 px-3 py-2"
              >
                <div class="text-sm font-medium">{{ role }}</div>
                <div v-if="admin.role_descriptions?.[role]" class="text-[11px] text-muted-foreground mt-0.5 leading-snug">
                  {{ admin.role_descriptions[role] }}
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Effective permissions (union of direct + role-granted) -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
          <div class="px-6">
            <div class="title-golden flex items-center gap-2 mb-3">
              <span class="text-sm font-semibold">Effective permissions</span>
              <span class="text-xs text-muted-foreground font-normal">({{ admin.effective_permissions.length }})</span>
            </div>
            <div v-if="!admin.effective_permissions.length" class="text-sm text-muted-foreground italic">
              This admin has no permissions.
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div
                v-for="p in admin.effective_permissions"
                :key="p.name"
                class="rounded-md border border-border bg-background/40 px-3 py-2"
              >
                <div class="text-sm">{{ p.name }}</div>
                <div class="text-[11px] mt-1 flex flex-wrap gap-1">
                  <span
                    v-if="p.direct"
                    class="inline-flex items-center rounded px-1.5 py-0.5 bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10px] uppercase tracking-wide"
                  >direct</span>
                  <span
                    v-for="role in p.via_roles"
                    :key="role"
                    class="inline-flex items-center rounded px-1.5 py-0.5 bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] uppercase tracking-wide"
                  >via {{ role }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminUserLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import AdminUserLayout from "../AdminUserLayout.vue";

const props = defineProps({
  admin: { type: Object, required: true },
});

const downloading = ref(false);

async function downloadPdf() {
  if (downloading.value) return;
  downloading.value = true;
  try {
    // Dynamic import keeps jsPDF out of the main bundle — already chunked.
    const { jsPDF } = await import("jspdf");
    const doc = new jsPDF({ unit: "pt", format: "a4" });

    const marginX = 48;
    const pageW = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    let y = 56;

    const ensureSpace = (needed) => {
      if (y + needed > pageH - 48) {
        doc.addPage();
        y = 56;
      }
    };

    // Title
    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.text("Admin user details", marginX, y);
    y += 22;

    doc.setFont("helvetica", "normal");
    doc.setFontSize(10);
    doc.setTextColor(120);
    doc.text(
      `Generated ${new Date().toLocaleString()}`,
      marginX,
      y,
    );
    y += 22;
    doc.setTextColor(0);

    const writeSection = (title) => {
      ensureSpace(28);
      doc.setFont("helvetica", "bold");
      doc.setFontSize(13);
      doc.text(title, marginX, y);
      y += 6;
      doc.setDrawColor(200);
      doc.line(marginX, y, pageW - marginX, y);
      y += 14;
      doc.setFont("helvetica", "normal");
      doc.setFontSize(11);
    };

    const writeKeyValue = (label, value) => {
      const text = value == null || value === "" ? "—" : String(value);
      const lines = doc.splitTextToSize(text, pageW - marginX - 160);
      ensureSpace(14 + (lines.length - 1) * 13);
      doc.setFont("helvetica", "bold");
      doc.setTextColor(80);
      doc.text(label, marginX, y);
      doc.setFont("helvetica", "normal");
      doc.setTextColor(0);
      doc.text(lines, marginX + 130, y);
      y += 14 + (lines.length - 1) * 13;
    };

    // Account details
    writeSection("Account details");
    writeKeyValue("Admin ID", `#${props.admin.id}`);
    writeKeyValue("Name", props.admin.name);
    writeKeyValue("Email", props.admin.email);
    writeKeyValue("Phone", props.admin.phone);
    writeKeyValue("Email verified", props.admin.email_verified_at ? `Yes (${props.admin.email_verified_at})` : "No");
    writeKeyValue("Partner", props.admin.partner?.title);
    writeKeyValue("Created", props.admin.created_at);
    writeKeyValue("Last updated", props.admin.updated_at);

    y += 8;

    // Roles
    writeSection("Roles");
    if (!props.admin.roles.length) {
      ensureSpace(14);
      doc.setTextColor(120);
      doc.text("No roles assigned.", marginX, y);
      doc.setTextColor(0);
      y += 14;
    } else {
      for (const role of props.admin.roles) {
        const desc = props.admin.role_descriptions?.[role];
        const line = desc ? `• ${role} — ${desc}` : `• ${role}`;
        const lines = doc.splitTextToSize(line, pageW - marginX * 2);
        ensureSpace(13 * lines.length);
        doc.text(lines, marginX, y);
        y += 13 * lines.length;
      }
    }

    y += 8;

    // Effective permissions
    writeSection(`Effective permissions (${props.admin.effective_permissions.length})`);
    if (!props.admin.effective_permissions.length) {
      ensureSpace(14);
      doc.setTextColor(120);
      doc.text("This admin has no permissions.", marginX, y);
      doc.setTextColor(0);
      y += 14;
    } else {
      for (const p of props.admin.effective_permissions) {
        const sources = [];
        if (p.direct) sources.push("direct");
        for (const r of p.via_roles || []) sources.push(`via ${r}`);
        const line = `• ${p.name}${sources.length ? `  [${sources.join(", ")}]` : ""}`;
        const lines = doc.splitTextToSize(line, pageW - marginX * 2);
        ensureSpace(13 * lines.length);
        doc.text(lines, marginX, y);
        y += 13 * lines.length;
      }
    }

    const filename = `admin-user-${props.admin.id}-${(props.admin.email || "details").replace(/[^a-z0-9.-]+/gi, "_")}.pdf`;
    doc.save(filename);
  } catch (e) {
    console.error("Failed to generate PDF", e);
    alert("Failed to generate PDF: " + (e?.message || e));
  } finally {
    downloading.value = false;
  }
}
</script>
