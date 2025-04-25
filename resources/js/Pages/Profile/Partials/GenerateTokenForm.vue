<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';

import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    tokenName: '',
});

const tokenValue = ref(null);
const statusMessage = ref('');
const showingTokenModal = ref(false);

const generateToken = () => {
    tokenValue.value = null; // Reset modal view
    axios.post(route('profile.token.generate'), form)
        .then(({ data }) => {
            if (data.success) {
                tokenValue.value = data.token;
                statusMessage.value = 'Token generated successfully.';
            } else {
                statusMessage.value = data.message || 'Failed to generate token.';
                showingTokenModal.value = false;
            }
        })
        .catch(() => {
            statusMessage.value = 'An error occurred while generating token.';
            showingTokenModal.value = false;
        })
        .finally(() => {
            setTimeout(() => {
                statusMessage.value = '';
            }, 3000);
        });
};

const openTokenModal = () => {
    if (!form.tokenName) return;
    showingTokenModal.value = true;
    generateToken();
};

const closeModal = () => {
    showingTokenModal.value = false;
    tokenValue.value = null;
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Generate API Token
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Create a personal access token to authenticate API requests.
            </p>
        </header>

        <div class="mt-6 space-y-6">
            <div>
                <InputLabel for="tokenName" value="Token Name" />
                <TextInput
                    id="tokenName"
                    v-model="form.tokenName"
                    type="text"
                    required
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.tokenName" class="mt-2" />
            </div>

            <div class="flex items-center justify-start">
                <PrimaryButton :disabled="form.processing || !form.tokenName" @click="openTokenModal">
                    Generate Token
                </PrimaryButton>

                <span v-if="statusMessage" class="ml-4 text-sm text-blue-600">
                    {{ statusMessage }}
                </span>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showingTokenModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-center text-gray-800 dark:text-white mb-2">
                    Your New API Token
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-4">
                    Copy and store this token securely. You will not see it again.
                </p>

                <div class="bg-gray-100 dark:bg-gray-700 text-center text-sm p-4 rounded-md select-all">
                    <code>{{ tokenValue || 'Generating token...' }}</code>
                </div>

                <div class="flex justify-center mt-4">
                    <button
                        @click="closeModal"
                        class="inline-block text-sm text-blue-600 hover:underline"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
