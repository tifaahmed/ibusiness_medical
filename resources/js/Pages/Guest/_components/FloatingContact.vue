<template>
  <!-- Floating Action Button Menu -->
  <div class="menu-container">
    <!-- Main Trigger Button -->
    <div 
      class="menu-trigger cursor-pointer bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 rounded-full p-3 shadow-lg transition-all duration-300 fixed bottom-4 right-4 z-50"
      :class="{ 'active': menuOpen }"
      @click="toggleMenu"
      id="menuTrigger"
    >
      <i class="fas fa-plus text-white text-xl transition-transform duration-300" :class="{ 'rotate-45': menuOpen }"></i>
    </div>

    <!-- Menu Items -->
    <div 
      class="menu-items fixed bottom-20 right-4 flex flex-col gap-3 transition-all duration-300 ltr:justify-end rtl:justify-start ltr:items-end rtl:items-start min-w-48"
      :class="{ 'active': menuOpen }"
      id="menuItems"
    >
      <!-- WhatsApp -->
      <a 
        :href="`https://wa.me/${whatsappNumber}`" 
        target="_blank" 
        rel="noopener noreferrer" 
        class="menu-item transform hover:scale-105 transition-all duration-300 bg-[#25D366] hover:bg-[#128C7E] flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-md hover:shadow-xl whitespace-nowrap w-full text-start"
        :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <i class="fab fa-whatsapp text-white text-lg"></i>
        <span class="text-white text-sm font-medium">
          {{ $page.props.translations?.home?.floating_contact?.whatsapp || 'Contact Us via WhatsApp' }}
        </span>
      </a>

      <!-- Member Card / Register -->
      <a 
        href="/"
        @click.prevent="openMemberCardModal"
        class="menu-item transform hover:scale-105 transition-all duration-300 bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] hover:from-[#B89B6A] hover:to-[#8B7355] flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-md hover:shadow-xl whitespace-nowrap w-full text-start"
        :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <i class="fas fa-id-card text-white text-lg"></i>
        <span class="text-white text-sm font-medium">
          {{ $page.props.translations?.home?.floating_contact?.member_card || 'Member Card' }}
        </span>
      </a>

      <!-- Contact Us -->
      <button 
        type="button"
        @click="openContactForm"
        class="menu-item transform hover:scale-105 transition-all duration-300 bg-[#FF6B6B] hover:bg-[#FF4F4F] flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-md hover:shadow-xl whitespace-nowrap w-full text-start"
        :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <i class="fas fa-envelope text-white text-lg"></i>
        <span class="text-white text-sm font-medium">
          {{ $page.props.translations?.home?.floating_contact?.contact_us || 'Contact Us' }}
        </span>
      </button>

      <!-- Phone Call -->
      <a 
        :href="`tel:${phoneNumberForTel}`"
        class="menu-item transform hover:scale-105 transition-all duration-300 bg-[#4A90E2] hover:bg-[#357ABD] flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-md hover:shadow-xl whitespace-nowrap w-full text-start"
        :dir="$page.props.locale === 'ar' ? 'rtl' : 'ltr'"
      >
        <i class="fas fa-phone text-white text-lg"></i>
        <span class="text-white text-sm font-medium">
          {{ $page.props.translations?.home?.floating_contact?.call_us || 'Call Us' }}
        </span>
      </a>
    </div>
  </div>

  <!-- Contact Form Modal -->
  <Transition name="modal">
    <div 
      v-if="showContactForm" 
      class="contact-modal-overlay"
      @click.self="closeContactForm"
    >
      <div class="contact-modal">
        <!-- Modal Header -->
        <div class="modal-header" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
          <h2 class="modal-title">
            {{ $page.props.translations?.home?.floating_contact?.modal?.title || 'Contact Us' }}
          </h2>
          <button @click="closeContactForm" class="modal-close">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body -->
        <form @submit.prevent="submitForm" class="contact-form">
          <!-- Name -->
          <div class="form-group" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <label for="name" class="form-label">
              {{ $page.props.translations?.home?.floating_contact?.modal?.full_name || 'Full Name *' }}
            </label>
            <input 
              type="text" 
              id="name"
              v-model="form.name"
              class="form-input"
              :class="{ 'input-error': errors.name }"
              required
              :placeholder="$page.props.translations?.home?.floating_contact?.modal?.full_name_placeholder || 'Enter your full name'"
            >
            <span v-if="errors.name" class="error-message">{{ errors.name }}</span>
          </div>

          <!-- Email -->
          <div class="form-group" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <label for="email" class="form-label">
              {{ $page.props.translations?.home?.floating_contact?.modal?.email || 'Email Address *' }}
            </label>
            <input 
              type="email" 
              id="email"
              v-model="form.email"
              class="form-input"
              :class="{ 'input-error': errors.email }"
              required
              :placeholder="$page.props.translations?.home?.floating_contact?.modal?.email_placeholder || 'your.email@example.com'"
            >
            <span v-if="errors.email" class="error-message">{{ errors.email }}</span>
          </div>

          <!-- Phone -->
          <div class="form-group" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <label for="phone" class="form-label">
              {{ $page.props.translations?.home?.floating_contact?.modal?.phone || 'Phone Number' }}
            </label>
            <input 
              type="tel" 
              id="phone"
              v-model="form.phone"
              class="form-input"
              :class="{ 'input-error': errors.phone }"
              :placeholder="$page.props.translations?.home?.floating_contact?.modal?.phone_placeholder || '+20 XXX XXX XXXX'"
            >
            <span v-if="errors.phone" class="error-message">{{ errors.phone }}</span>
          </div>

          <!-- Subject -->
          <div class="form-group" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <label for="subject" class="form-label">
              {{ $page.props.translations?.home?.floating_contact?.modal?.subject || 'Subject *' }}
            </label>
            <input 
              type="text" 
              id="subject"
              v-model="form.subject"
              class="form-input"
              :class="{ 'input-error': errors.subject }"
              required
              :placeholder="$page.props.translations?.home?.floating_contact?.modal?.subject_placeholder || 'What is this about?'"
            >
            <span v-if="errors.subject" class="error-message">{{ errors.subject }}</span>
          </div>

          <!-- Message -->
          <div class="form-group" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <label for="message" class="form-label">
              {{ $page.props.translations?.home?.floating_contact?.modal?.message || 'Message *' }}
            </label>
            <textarea 
              id="message"
              v-model="form.message"
              class="form-textarea"
              :class="{ 'input-error': errors.message }"
              required
              rows="5"
              :placeholder="$page.props.translations?.home?.floating_contact?.modal?.message_placeholder || 'Tell us how we can help you...'"
            ></textarea>
            <span v-if="errors.message" class="error-message">{{ errors.message }}</span>
          </div>

          <!-- Success Message -->
          <Transition name="fade">
            <div v-if="successMessage" class="success-alert">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span>{{ successMessage }}</span>
            </div>
          </Transition>

          <!-- Error Alert -->
          <Transition name="fade">
            <div v-if="generalError" class="error-alert">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <span>{{ generalError }}</span>
            </div>
          </Transition>

          <!-- Submit Button -->
          <div class="form-actions" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <button 
              type="button" 
              @click="closeContactForm"
              class="btn btn-secondary"
              :disabled="submitting"
            >
              {{ $page.props.translations?.home?.floating_contact?.modal?.cancel || 'Cancel' }}
            </button>
            <button 
              type="submit" 
              class="btn btn-primary"
              :disabled="submitting"
            >
              <span v-if="!submitting">
                {{ $page.props.translations?.home?.floating_contact?.modal?.send_message || 'Send Message' }}
              </span>
              <span v-else class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ $page.props.translations?.home?.floating_contact?.modal?.sending || 'Sending...' }}
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const menuOpen = ref(false);
const showContactForm = ref(false);
const submitting = ref(false);
const successMessage = ref('');
const generalError = ref('');

// Configuration - Get from page props or use defaults
const whatsappNumber = page.props.translations?.home?.footer?.whatsapp?.replace(/[^0-9]/g, '') || '01156385251';
const phoneNumber = '01156385251'; // Updated with actual number
const phoneNumberForTel = `+20${phoneNumber.replace(/^0/, '')}`; // Format for tel: link (remove leading 0, add country code)

const form = reactive({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: ''
});

const errors = reactive({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: ''
});

const toggleMenu = () => {
  menuOpen.value = !menuOpen.value;
};

const openContactForm = () => {
  menuOpen.value = false;
  showContactForm.value = true;
  document.body.style.overflow = 'hidden';
};

const closeContactForm = () => {
  showContactForm.value = false;
  document.body.style.overflow = '';
  resetForm();
};

const emit = defineEmits(['open-member-card']);

const openMemberCardModal = () => {
  menuOpen.value = false;
  // Check if we're on the home page
  const currentUrl = window.location.pathname;
  if (currentUrl === '/' || currentUrl === '/home') {
    // Emit event to parent component (HomePage)
    emit('open-member-card');
    // Also dispatch a custom event for global listeners
    window.dispatchEvent(new CustomEvent('open-member-card-modal'));
  } else {
    // Navigate to home page - the modal will be handled there
    router.visit('/', {
      onSuccess: () => {
        // Small delay to ensure page is loaded, then trigger modal
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent('open-member-card-modal'));
        }, 100);
      }
    });
  }
};

const resetForm = () => {
  form.name = '';
  form.email = '';
  form.phone = '';
  form.subject = '';
  form.message = '';
  
  errors.name = '';
  errors.email = '';
  errors.phone = '';
  errors.subject = '';
  errors.message = '';
  
  successMessage.value = '';
  generalError.value = '';
};

const submitForm = async () => {
  // Clear previous errors
  Object.keys(errors).forEach(key => errors[key] = '');
  generalError.value = '';
  successMessage.value = '';
  
  submitting.value = true;
  
  try {
    const response = await axios.post('/api/contact-messages', form);
    
    successMessage.value = page.props.translations?.home?.floating_contact?.modal?.success || 'Your message has been sent successfully! We will get back to you soon.';
    
    // Reset form after 2 seconds and close modal
    setTimeout(() => {
      closeContactForm();
    }, 2000);
    
  } catch (error) {
    if (error.response && error.response.status === 422) {
      // Validation errors
      const validationErrors = error.response.data.errors;
      Object.keys(validationErrors).forEach(key => {
        if (errors.hasOwnProperty(key)) {
          errors[key] = validationErrors[key][0];
        }
      });
    } else {
      // General error
      generalError.value = page.props.translations?.home?.floating_contact?.modal?.error || 'An error occurred. Please try again later.';
    }
  } finally {
    submitting.value = false;
  }
};

// Close menu when clicking outside
const handleClickOutside = (event) => {
  const menuTrigger = document.getElementById('menuTrigger');
  const menuItems = document.getElementById('menuItems');
  
  if (menuOpen.value && 
      menuTrigger && 
      menuItems &&
      !menuTrigger.contains(event.target) && 
      !menuItems.contains(event.target)) {
    menuOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
/* Menu Container */
.menu-container {
  position: relative;
}

/* Menu Trigger Button */
.menu-trigger {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

/* Mobile: Adjust position to avoid bottom navigation */
@media (max-width: 767px) {
  .menu-trigger {
    bottom: 100px;
    right: 16px;
    width: 48px;
    height: 48px;
  }
}

/* Menu Items */
.menu-items {
  opacity: 0;
  visibility: hidden;
  transform: translateY(20px);
  pointer-events: none;
  z-index: 49;
}

.menu-items.active {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
  pointer-events: all;
}

/* Mobile: Adjust menu items position */
@media (max-width: 767px) {
  .menu-items {
    bottom: 160px;
    right: 16px;
    min-width: auto;
    width: calc(100vw - 32px);
    max-width: 280px;
  }
}

/* Menu Item */
.menu-item {
  cursor: pointer;
  border: none;
  text-decoration: none;
  display: flex;
  align-items: center;
}

/* RTL Support */
[dir="rtl"] .menu-items {
  right: auto;
  left: 16px;
}

[dir="rtl"] .menu-trigger {
  right: auto;
  left: 16px;
}

@media (max-width: 767px) {
  [dir="rtl"] .menu-items {
    left: 16px;
    right: auto;
  }
  
  [dir="rtl"] .menu-trigger {
    left: 16px;
    right: auto;
  }
}

/* Modal Styles */
.contact-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
  overflow-y: auto;
}

.contact-modal {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
}

.modal-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #6b7280;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.contact-form {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.2s;
  background: white;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: #B89B6A;
  box-shadow: 0 0 0 3px rgba(184, 155, 106, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 120px;
}

.input-error {
  border-color: #ef4444 !important;
}

.error-message {
  display: block;
  font-size: 12px;
  color: #ef4444;
  margin-top: 4px;
}

.success-alert,
.error-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 14px;
}

.success-alert {
  background: #d1fae5;
  color: #065f46;
}

.error-alert {
  background: #fee2e2;
  color: #991b1b;
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

.btn {
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-primary {
  background: linear-gradient(135deg, #B89B6A 0%, #8B7355 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(184, 155, 106, 0.3);
}

/* Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-active .contact-modal,
.modal-leave-active .contact-modal {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .contact-modal,
.modal-leave-to .contact-modal {
  transform: scale(0.9);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Mobile Responsive */
@media (max-width: 767px) {
  .contact-modal {
    border-radius: 12px;
    margin: 10px;
  }
  
  .modal-header,
  .contact-form {
    padding: 16px;
  }
  
  .modal-title {
    font-size: 20px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
}
</style>
