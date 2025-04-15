<!-- resources/js/Pages/Index.vue -->
<template>
    <div class="max-w-md mx-auto p-6 shadow rounded-xl bg-white">
        <h2 class="text-xl font-bold mb-4">Face Payment Checkout</h2>

        <form @submit.prevent="submitPayment">
            <div class="space-y-4">
                <input v-model="form.vendor_id" type="hidden" />

                <div>
                    <label>Amount (PHP)</label>
                    <input v-model.number="form.amount" type="number" step="0.01" min="0.01" class="input" required />
                </div>

                <div>
                    <label>Description</label>
                    <input v-model="form.item_description" type="text" class="input" required />
                </div>

                <div>
                    <label>Reference ID</label>
                    <input v-model="form.reference_id" type="text" class="input" />
                </div>

                <div>
                    <label>Selfie (Base64)</label>
                    <input v-model="form.selfie" type="text" class="input" required />
                    <!-- Replace with camera capture UI later -->
                </div>

                <div>
                    <button :disabled="loading" class="btn btn-primary w-full">
                        <span v-if="loading">Processing...</span>
                        <span v-else>Submit Payment</span>
                    </button>
                </div>
            </div>
        </form>

        <div v-if="successMessage" class="mt-4 text-green-600">
            {{ successMessage }}
        </div>

        <div v-if="errorMessage" class="mt-4 text-red-600">
            {{ errorMessage }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const form = ref({
    vendor_id: 1, // update dynamically if needed
    amount: null,
    item_description: '',
    reference_id: '',
    selfie: '', // this will be filled in by face capture component later
    currency: 'PHP',
    callback_url: '', // optional
})

const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const submitPayment = async () => {
    successMessage.value = ''
    errorMessage.value = ''
    loading.value = true

    try {
        const { data } = await axios.post('/api/face-payment', form.value)
        successMessage.value = `✅ ${data.message}. Transaction ID: ${data.transfer_uuid}`
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Something went wrong.'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.input {
    @apply w-full border rounded px-3 py-2;
}
.btn-primary {
    @apply bg-blue-600 text-white font-semibold py-2 px-4 rounded;
}
</style>
