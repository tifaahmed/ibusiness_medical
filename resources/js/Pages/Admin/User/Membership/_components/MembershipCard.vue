<template>
  <div class="membership-card bg-card text-card-foreground border-2 border-border rounded-xl p-6 shadow-lg" style="background-color: var(--card, #ffffff);">
    <!-- Card Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-primary/20 border-2 border-primary flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-card-foreground">Membership Card</h3>
          <p class="text-xs text-muted-foreground">Official Membership</p>
        </div>
      </div>
      <div v-if="membership.is_active" class="px-3 py-1 bg-emerald-500/30 border border-emerald-500/50 rounded-full">
        <span class="text-xs font-semibold text-emerald-200">ACTIVE</span>
      </div>
    </div>

    <!-- Member Info -->
    <div class="space-y-4 mb-6">
      <div>
        <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Member Name</label>
        <p class="text-xl font-bold text-card-foreground mt-1">{{ user.name }}</p>
      </div>
      
      <div>
        <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Membership Number</label>
        <p class="text-lg font-mono font-semibold text-card-foreground mt-1">{{ membership.membership_number }}</p>
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-4 mb-6 pt-4 border-t border-border">
      <div>
        <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Registration Date</label>
        <p class="text-sm font-semibold text-card-foreground mt-1">{{ formatDate(membership.registration_date) }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Expiration Date</label>
        <p class="text-sm font-semibold text-card-foreground mt-1">{{ formatDate(membership.expiration_date) }}</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between pt-4 border-t border-border">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-border flex items-center justify-center">
          <img
            v-if="user.avatar_url"
            :src="user.avatar_url"
            :alt="user.name"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center text-xs font-semibold text-card-foreground"
          >
            {{ getUserInitials(user.name) }}
          </div>
        </div>
        <div>
          <p class="text-xs text-muted-foreground">Issued to</p>
          <p class="text-sm font-semibold text-card-foreground">{{ user.email }}</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-xs text-muted-foreground">Valid Until</p>
        <p class="text-sm font-semibold text-card-foreground">{{ formatDate(membership.expiration_date) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  membership: {
    type: Object,
    required: true,
  },
});

const getUserInitials = (name) => {
  if (!name) return "??";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  } catch (error) {
    return dateString;
  }
};
</script>

<style scoped>
.membership-card {
  min-width: 400px;
  max-width: 500px;
}
</style>
