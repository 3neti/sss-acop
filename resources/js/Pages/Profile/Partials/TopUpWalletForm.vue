<script setup>
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import Modal from '@/Components/Modal.vue'

import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

// User info from Inertia page props
const user = usePage().props.auth?.user ?? { id: 0, name: 'Demo User', balanceFloat: 0 }

// Reactive states
const form = useForm({
    amount: '100',
    account: user.mobile
})
const qrCode = ref(null)
const statusMessage = ref('')
const showingQrModal = ref(false)
const userBalance = ref(user.balanceFloat)

// Format the wallet balance
const formattedBalance = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
}).format(userBalance.value)

const generateQRCode = () => {
    qrCode.value = null // Show loading state in modal
    console.log('[generateQRCode] Sending form:', form)
    axios
        .post(route('wallet.qr-code'), form)
        .then(({ data }) => {
            console.log('[generateQRCode] Response:', data)
            if (data.success) {
                qrCode.value = data.qr_code
                statusMessage.value = 'QR code generated successfully.'
            } else {
                console.warn('[generateQRCode] QR generation failed:', data.message)
                statusMessage.value = data.message || 'Failed to generate QR code.'
                showingQrModal.value = false
            }
        })
        .catch(() => {
            console.error('[generateQRCode] Request failed:', error)
            statusMessage.value = 'Error occurred while generating QR code.'
            showingQrModal.value = false
        })
        .finally(() => {
            setTimeout(() => {
                statusMessage.value = ''
            }, 3000)
        })
}

// Modal handlers
const openQrModal = () => {
    if (!form.amount || form.amount <= 0) return

    showingQrModal.value = true
    generateQRCode()
}

const closeModal = () => {
    showingQrModal.value = false
    qrCode.value = null
}

const downloadQRCode = () => {
    if (!qrCode.value) return
    const link = document.createElement('a')
    link.href = qrCode.value
    link.download = `Topup_QR_${form.amount}.png`
    link.click()
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Wallet Top-up
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Add funds to your wallet via GCash by scanning a generated QR code.
            </p>
        </header>

        <div class="mt-6 space-y-6">
            <div>
                <InputLabel for="amount" value="Amount (₱)" />
                <TextInput
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    min="1"
                    step="any"
                    required
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.amount" class="mt-2" />
            </div>

            <div class="flex items-center justify-between">
                <PrimaryButton :disabled="form.processing || !form.amount" @click="openQrModal">
                    Generate Top-up QR
                </PrimaryButton>

                <div class="text-right">
                    <span class="text-sm font-medium text-gray-700">Current Balance:</span>
                    <span class="text-lg font-semibold text-green-500 ml-2">{{ formattedBalance }}</span>
                </div>
            </div>

            <div v-if="statusMessage" class="text-sm text-blue-600">
                {{ statusMessage }}
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showingQrModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-center text-gray-800 dark:text-white mb-2">
                    Scan to Top-Up ₱{{ form.amount }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-4">
                    Open your GCash app and scan the QR code below to complete the top-up.
                </p>

                <div class="flex justify-center mb-4 min-h-[12rem] items-center">
                    <template v-if="qrCode">
                        <img :src="qrCode" alt="Top-up QR Code" class="w-48 h-48 border rounded-md shadow-md" />
                    </template>
                    <template v-else>
                        <p class="text-gray-500">Generating QR code...</p>
                    </template>
                </div>

                <div class="flex justify-center gap-4">
                    <PrimaryButton
                        v-if="qrCode"
                        @click="downloadQRCode"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        Download QR
                    </PrimaryButton>
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
