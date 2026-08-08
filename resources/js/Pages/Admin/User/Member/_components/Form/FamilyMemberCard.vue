<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          {{ t.member?.family_members || 'Family Members' }}
        </div>
        <button
          v-if="!showAddForm"
          @click.stop="showAddForm = true; editingMember = null"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.member?.add_family_member || 'Add Family Member' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Add/Edit Form -->
      <div v-if="showAddForm || editingMember" class="mb-6 p-4 bg-accent/50 rounded-lg border border-border">
        <h3 class="text-sm font-semibold mb-4 text-white">
          {{ editingMember ? (t.member?.edit_family_member || 'Edit Family Member') : (t.member?.add_new_family_member || 'Add New Family Member') }}
        </h3>
        <form @submit.stop.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormInput
              v-model="form.name"
              :label="t.common?.name || 'Name'"
              :error="errors.name"
              :placeholder="t.member?.family_member_name_placeholder || 'Enter family member name'"
              required
            />
            <FormSelect
              v-model="form.relationship"
              :label="t.member?.relationship || 'Relationship'"
              :options="relationshipOptions"
              :error="errors.relationship"
              required
            />
            <FormDateInput
              v-model="form.date_of_birth"
              :label="t.member?.date_of_birth || 'Date of Birth'"
              :error="errors.date_of_birth"
            />
            <FormInput
              v-model="form.phone"
              :label="t.common?.phone || 'Phone'"
              type="tel"
              :error="errors.phone"
              :placeholder="t.member?.phone_placeholder || 'Enter phone number'"
            />
            <FormInput
              v-model="form.email"
              :label="t.common?.email || 'Email'"
              type="email"
              :error="errors.email"
              :placeholder="t.member?.email_address_placeholder || 'Enter email address'"
            />
            <div class="flex items-center">
              <FormCheckbox
                v-model="form.is_active"
                :label="t.member?.active || 'Active'"
                :error="errors.is_active"
              />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-white mb-2">{{ t.member?.photo || 'Photo' }}</label>
              <ImageFileInput
                :initial-preview="form.photo_preview"
                @file-selected="handlePhotoSelect"
                @error="handlePhotoError"
              />
              <p v-if="errors.photo" class="mt-1 text-sm text-destructive">{{ errors.photo }}</p>
            </div>
          </div>
          <div class="flex gap-3 justify-end">
            <button
              type="button"
              @click.stop="cancelForm"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            >
              {{ t.common?.cancel || 'Cancel' }}
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
            >
              {{ editingMember ? (t.member?.update_family_member || 'Update Family Member') : (t.member?.add_family_member || 'Add Family Member') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Family Members List -->
      <div v-if="familyMembers.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="member in familyMembers"
          :key="member.id"
          class="p-4 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4 flex-1">
              <div v-if="member.photo_url" class="flex-shrink-0">
                <img
                  :src="member.photo_url"
                  :alt="member.name"
                  class="w-16 h-16 rounded-lg object-cover border border-border"
                />
              </div>
              <div v-else class="flex-shrink-0 w-16 h-16 rounded-lg bg-muted flex items-center justify-center border border-border">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/70">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-semibold text-white">{{ member.name }}</h4>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    :class="member.is_active ? 'bg-emerald-500/30 text-emerald-200 border border-emerald-500/40' : 'bg-slate-500/25 text-slate-300 border border-slate-500/30'"
                  >
                    {{ member.is_active ? (t.member?.active || 'Active') : (t.member?.inactive || 'Inactive') }}
                  </span>
                </div>
                <p class="text-sm text-white/80 mb-2">
                  <span class="font-medium">{{ member.relationship_label || getRelationshipLabel(member.relationship) }}</span>
                  <span v-if="member.date_of_birth" class="ml-2">
                    • {{ t.member?.born || 'Born' }}: {{ formatDate(member.date_of_birth) }}
                  </span>
                </p>
                <div class="flex flex-wrap gap-4 text-xs text-white/70">
                  <span v-if="member.phone">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    {{ member.phone }}
                  </span>
                  <span v-if="member.email">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    {{ member.email }}
                  </span>
                </div>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button
                @click.stop="editMember(member)"
                type="button"
                class="p-2 rounded-md hover:bg-accent transition-colors"
                :title="t.common?.edit || 'Edit'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button
                @click.stop="deleteMember(member)"
                type="button"
                class="p-2 rounded-md hover:bg-destructive/20 transition-colors"
                :title="t.common?.delete || 'Delete'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-destructive">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!showAddForm" class="text-center py-8 text-white/70">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 opacity-50 text-white/50">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <p class="text-white">{{ t.member?.no_family_members || 'No family members added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.member?.add_family_member_help || 'Click "Add Family Member" to get started.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { FormInput, FormSelect, FormDateInput, FormCheckbox, ImageFileInput } from '@/Components/form';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  familyMembers: {
    type: Array,
    default: () => []
  },
  userSlug: {
    type: String,
    required: true
  },
  membershipSlug: {
    type: String,
    required: false,
    default: null
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const notification = useNotification();
const showAddForm = ref(false);
const editingMember = ref(null);
const isSubmitting = ref(false);
const errors = ref({});
const photoFile = ref(null);

const relationshipOptions = computed(() => {
  const options = page.props.relationshipOptions || [];
  return options.map(opt => ({
    value: opt.value,
    label: t.value.member?.[opt.value] || opt.label
  }));
});

const form = ref({
  name: '',
  relationship: '',
  date_of_birth: '',
  phone: '',
  email: '',
  is_active: true,
  photo_preview: null
});

const resetForm = () => {
  form.value = {
    name: '',
    relationship: '',
    date_of_birth: '',
    phone: '',
    email: '',
    is_active: true,
    photo_preview: null
  };
  photoFile.value = null;
  errors.value = {};
};

const cancelForm = () => {
  showAddForm.value = false;
  editingMember.value = null;
  resetForm();
};

const editMember = (member) => {
  editingMember.value = member;
  showAddForm.value = true;
  form.value = {
    name: member.name || '',
    relationship: member.relationship || '',
    date_of_birth: member.date_of_birth || '',
    phone: member.phone || '',
    email: member.email || '',
    is_active: member.is_active ?? true,
    photo_preview: member.photo_url || null
  };
  photoFile.value = null;
  errors.value = {};
};

const handlePhotoSelect = (file) => {
  photoFile.value = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      form.value.photo_preview = e.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    form.value.photo_preview = null;
  }
};

const handlePhotoError = (error) => {
  errors.value.photo = error;
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getRelationshipLabel = (value) => {
  const option = relationshipOptions.value.find(opt => opt.value === value);
  return option ? option.label : value;
};

const handleSubmit = () => {
  if (!props.membershipSlug) {
    notification.error(t.value.member?.no_active_membership || 'No active membership found. Please create a membership first.');
    return;
  }

  errors.value = {};
  isSubmitting.value = true;

  const formData = new FormData();
  formData.append('name', form.value.name);
  formData.append('relationship', form.value.relationship);
  if (form.value.date_of_birth) {
    formData.append('date_of_birth', form.value.date_of_birth);
  }
  if (form.value.phone) {
    formData.append('phone', form.value.phone);
  }
  if (form.value.email) {
    formData.append('email', form.value.email);
  }
  formData.append('is_active', form.value.is_active ? '1' : '0');
  if (photoFile.value) {
    formData.append('photo', photoFile.value);
  }

  if (editingMember.value) {
    router.put(
      route('admin.user.membership.family-member.update', [
        props.userSlug,
        props.membershipSlug,
        editingMember.value.id
      ]),
      formData,
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
          notification.success(t.value.member?.family_member_updated || 'Family member updated successfully');
          cancelForm();
          router.reload({ only: ['member'] });
        },
        onError: (pageErrors) => {
          errors.value = pageErrors;
          notification.error(t.value.member?.fix_errors || 'Please fix the errors and try again');
        },
        onFinish: () => {
          isSubmitting.value = false;
        }
      }
    );
  } else {
    router.post(
      route('admin.user.membership.family-member.store', [
        props.userSlug,
        props.membershipSlug
      ]),
      formData,
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
          notification.success(t.value.member?.family_member_added || 'Family member added successfully');
          cancelForm();
          router.reload({ only: ['member'] });
        },
        onError: (pageErrors) => {
          errors.value = pageErrors;
          notification.error(t.value.member?.fix_errors || 'Please fix the errors and try again');
        },
        onFinish: () => {
          isSubmitting.value = false;
        }
      }
    );
  }
};

const deleteMember = (member) => {
  if (!props.membershipSlug) {
    notification.error(t.value.member?.no_active_membership || 'No active membership found.');
    return;
  }

  if (!confirm((t.value.member?.confirm_delete_family_member || 'Are you sure you want to delete :name?').replace(':name', member.name))) {
    return;
  }

  router.delete(
    route('admin.user.membership.family-member.destroy', [
      props.userSlug,
      props.membershipSlug,
      member.id
    ]),
    {
      preserveScroll: true,
      onSuccess: () => {
        notification.success(t.value.member?.family_member_deleted || 'Family member deleted successfully');
        router.reload({ only: ['member'] });
      },
      onError: () => {
        notification.error(t.value.member?.failed_delete_family_member || 'Failed to delete family member');
      }
    }
  );
};
</script>

<style scoped></style>
