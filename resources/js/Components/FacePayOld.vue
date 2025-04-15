<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    vendorId: { type: [Number, String], required: true },
    amount: { type: [Number, String], required: true },
    itemDescription: { type: String, required: true },
    referenceId: { type: String, default: null },
    currency: { type: String, default: 'PHP' },
    callbackUrl: { type: String, default: null },
});

const webcamRef = ref(null);
const base64img = ref('');
const hasCaptured = ref(false);
const stream = ref(null);
const timeoutId = ref(null);
const submitting = ref(false);
const showRetry = ref(false);

const form = useForm({
    vendor_id: props.vendorId,
    amount: props.amount,
    currency: props.currency,
    item_description: props.itemDescription,
    reference_id: props.referenceId,
    callback_url: props.callbackUrl,
    selfie: '',
});

const capture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = webcamRef.value.videoWidth;
    canvas.height = webcamRef.value.videoHeight;
    canvas.getContext('2d').drawImage(webcamRef.value, 0, 0);
    base64img.value = canvas.toDataURL('image/jpeg');
    form.selfie = base64img.value;
    hasCaptured.value = true;
    stopCamera();
};

const stopCamera = () => {
    if (stream.value) stream.value.getTracks().forEach((t) => t.stop());
    if (timeoutId.value) clearTimeout(timeoutId.value);
};

const submit = () => {
    if (!form.selfie) return;
    submitting.value = true;
    form.post(route('face.payment'), {
        onSuccess: () => {
            submitting.value = false;
            if (props.callbackUrl) {
                window.location.href = props.callbackUrl + '?status=success&ref=' + page.props.transfer_uuid;
            } else {
                alert('Payment successful!');
            }
        },
        onError: () => {
            submitting.value = false;
            showRetry.value = true;
        },
    });
};

const retake = async () => {
    showRetry.value = false;
    hasCaptured.value = false;
    base64img.value = '';
    form.selfie = '';
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        if (webcamRef.value) webcamRef.value.srcObject = stream.value;
    } catch (e) {
        console.error('Camera restart failed:', e);
    }
};

onMounted(async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        if (webcamRef.value) webcamRef.value.srcObject = stream.value;
        timeoutId.value = setTimeout(stopCamera, 60000);
    } catch (e) {
        console.error('Camera access denied:', e);
    }
});

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="bg-white shadow-lg rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">
                Confirm Payment
            </h2>

            <div class="text-sm text-gray-700 mb-4">
                <p><strong>Item:</strong> {{ props.itemDescription }}</p>
                <p><strong>Amount:</strong> {{ props.amount }} {{ props.currency }}</p>
                <p v-if="props.referenceId"><strong>Ref ID:</strong> {{ props.referenceId }}</p>
            </div>

            <div class="relative w-full aspect-video rounded-md border overflow-hidden">
                <video v-if="!hasCaptured" ref="webcamRef" autoplay playsinline muted class="w-full h-full object-cover"></video>
                <img v-else :src="base64img" alt="Captured selfie" class="w-full h-full object-cover" />
            </div>

            <div class="mt-4 space-y-2">
                <button v-if="!hasCaptured" @click="capture" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                    📸 Capture Selfie
                </button>

                <button v-if="hasCaptured && showRetry" @click="retake" class="w-full bg-yellow-100 text-yellow-700 py-2 rounded-md border">
                    🔁 Retake
                </button>

                <button
                    v-if="hasCaptured && !submitting"
                    @click="submit"
                    class="w-full bg-green-600 text-white py-2 rounded-md hover:bg-green-700"
                >
                    ✅ Confirm Payment
                </button>
            </div>

            <div v-if="form.errors.selfie" class="text-sm text-red-600 mt-2 text-center">
                {{ form.errors.selfie }}
            </div>
        </div>
    </div>
</template>
