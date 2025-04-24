<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    // vendorId: Number,
    voucher_code: String,
    reference_id: String,
    item_description: String,
    amount: Number,
    currency: {
        type: String,
        default: 'PHP',
    },
    id_type: {
        type: String,
        default: '',
    },
    id_value: {
        type: String,
        default: '',
    },
    callbackUrl: {
        type: String,
        default: null,
    }
})

const webcamRef = ref(null)
const stream = ref(null)
const base64img = ref('')
const hasCaptured = ref(false)
const showRetry = ref(false)
const timeoutId = ref(null)

const form = useForm({
    // vendor_id: props.vendorId,
    voucher_code: props.voucher_code,
    reference_id: props.reference_id,
    item_description: props.item_description,
    amount: props.amount,
    callback_url: props.callbackUrl,
    currency: props.currency,
    id_type: props.id_type,
    id_value: props.id_value,
    selfie: '',
})

const capture = () => {
    const canvas = document.createElement('canvas')
    canvas.width = webcamRef.value.videoWidth
    canvas.height = webcamRef.value.videoHeight
    canvas.getContext('2d').drawImage(webcamRef.value, 0, 0)
    base64img.value = canvas.toDataURL('image/jpeg')
    form.selfie = base64img.value
    hasCaptured.value = true
    stopCamera()
}

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop())
    }
    if (timeoutId.value) clearTimeout(timeoutId.value)
}

const submit = () => {
    form.post(route('face.payment'), {
        preserveScroll: true,
        onSuccess: () => {
            showRetry.value = false
            // if (props.callbackUrl) {
            //     window.location.href = props.callbackUrl + '?status=success&ref=' + form.reference_id
            // } else {
            //     alert('Payment successful!');
            // }
        },
        onError: () => {
            showRetry.value = true
        }
    })
}

const retake = async () => {
    base64img.value = ''
    form.selfie = ''
    hasCaptured.value = false
    showRetry.value = false
    await startCamera()
}

const startCamera = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
        if (webcamRef.value) webcamRef.value.srcObject = stream.value

        timeoutId.value = setTimeout(() => {
            stopCamera()
        }, 60000)
    } catch (e) {
        console.error('Unable to access webcam', e)
    }
}

onMounted(() => {
    startCamera()
})

onBeforeUnmount(() => {
    stopCamera()
})
</script>

<template>
    <div class="bg-white shadow-md p-6 rounded-lg w-full max-w-md mx-auto space-y-4">
        <h2 class="text-xl font-bold text-gray-800">💳 Face Payment Checkout</h2>
        {{ form.voucher_code }}
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600">Amount ({{ currency }})</label>
                <input v-model="form.amount" type="number" min="0" step="0.01"
                       class="w-full border rounded px-3 py-2 text-sm" />
                <div v-if="form.errors.amount" class="text-red-500 text-xs mt-1">
                    {{ form.errors.amount }}
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-600">Item Description</label>
                <input v-model="form.item_description" type="text" class="w-full border rounded px-3 py-2 text-sm" />
                <div v-if="form.errors.item_description" class="text-red-500 text-xs mt-1">
                    {{ form.errors.item_description }}
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-600">Reference ID (optional)</label>
                <input v-model="form.reference_id" type="text" class="w-full border rounded px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="block text-sm text-gray-600">Id Type</label>
                <input v-model="form.id_type" type="text" class="w-full border rounded px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="block text-sm text-gray-600">Id Number</label>
                <input v-model="form.id_value" type="text" class="w-full border rounded px-3 py-2 text-sm" />
            </div>

            <div class="relative w-full aspect-video rounded border overflow-hidden">
                <video v-if="!hasCaptured" ref="webcamRef" autoplay muted playsinline class="w-full h-full object-cover"></video>
                <img v-else :src="base64img" class="w-full h-full object-cover" />
            </div>

            <div class="flex gap-2 mt-2">
                <button v-if="!hasCaptured" type="button" @click="capture"
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                    📸 Capture
                </button>

                <button v-if="hasCaptured && showRetry" type="button" @click="retake"
                        class="w-full bg-yellow-400 text-white px-4 py-2 rounded-md text-sm hover:bg-yellow-500">
                    🔁 Retake
                </button>
            </div>

            <button v-if="hasCaptured" type="submit"
                    class="w-full bg-green-600 text-white px-4 py-2 rounded-md text-sm hover:bg-green-700">
                ✅ Pay Now
            </button>
        </form>
    </div>
</template>
