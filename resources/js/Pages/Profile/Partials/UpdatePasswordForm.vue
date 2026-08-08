<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { FormInput } from '@/Components/form';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <FormSection @submitted="updatePassword">
        <template #title>
            Update Password
        </template>

        <template #description>
            Ensure your account is using a long, random password to stay secure.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <FormInput
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    label="Current Password"
                    type="password"
                    autocomplete="current-password"
                    :error="form.errors.current_password"
                />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <FormInput
                    ref="passwordInput"
                    v-model="form.password"
                    label="New Password"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password"
                />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <FormInput
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    type="password"
                    autocomplete="new-password"
                    :error="form.errors.password_confirmation"
                />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
