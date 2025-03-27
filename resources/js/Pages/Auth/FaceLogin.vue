<script setup>
import { useForm } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';

const webcamRef = ref(null);
const base64img = ref('');
const hasCaptured = ref(false);
const stream = ref(null);
const timeoutId = ref(null);
const showRetry = ref(false);

const form = useForm({
    email: '',
    base64img: '',
});

const capture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = webcamRef.value.videoWidth;
    canvas.height = webcamRef.value.videoHeight;
    canvas.getContext('2d').drawImage(webcamRef.value, 0, 0);
    base64img.value = canvas.toDataURL('image/jpeg');
    form.base64img = base64img.value;
    hasCaptured.value = true;
};

const submit = () => {
    stopCamera();
    form.post(route('face.login.attempt'), {
        onSuccess: () => {
            showRetry.value = false; // login success
        },
        onError: () => {
            showRetry.value = true; // login failed
        },
        onFinish: () => {
            // allow re-capture if login fails
        },
    });
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop());
    }
    if (timeoutId.value) {
        clearTimeout(timeoutId.value);
    }
};

onMounted(async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        console.error('Webcam not supported in this browser or insecure context.');
        return;
    }

    try {
        // stream.value = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user' // 👈 front camera
            }
        });
        if (webcamRef.value) {
            webcamRef.value.srcObject = stream.value;
        }

        // Set auto timeout to stop the camera after 60 seconds
        timeoutId.value = setTimeout(() => {
            stopCamera();
            console.warn('Camera auto-stopped after timeout.');
        }, 60000); // 60 seconds
    } catch (error) {
        console.error('Failed to access webcam:', error);
    }
});

onBeforeUnmount(() => {
    stopCamera();
});

const retake = async () => {
    hasCaptured.value = false;
    base64img.value = '';
    form.base64img = '';
    showRetry.value = false;

    // Restart camera
    try {
        // stream.value = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user' // 👈 front camera
            }
        });
        if (webcamRef.value) {
            webcamRef.value.srcObject = stream.value;
        }
    } catch (error) {
        console.error('Error restarting camera:', error);
    }
    document.getElementById('email')?.focus();
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="min-h-screen flex items-center justify-center bg-gray-50">
            <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login with Face</h2>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm"
                        />
                    </div>

                    <!-- Webcam Preview + Capture Button -->
                    <div>
                        <video
                            ref="webcamRef"
                            autoplay
                            playsinline
                            muted
                            class="rounded-md border w-full max-w-full aspect-video object-cover"
                            width="100%"
                            height="auto"
                        />
                        <button
                            type="button"
                            @click="capture"
                            class="mt-3 w-full inline-flex justify-center items-center px-4 py-3 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm"
                        >
                            📸 Capture Selfie
                        </button>
                    </div>

                    <!-- Captured Image Preview -->
                    <div v-if="hasCaptured" class="flex justify-center">
                        <img
                            :src="base64img"
                            alt="Captured selfie"
                            class="rounded-full shadow-md w-24 h-24 object-cover ring ring-indigo-300"
                        />
                    </div>

                    <div v-if="hasCaptured" class="flex flex-col items-center space-y-3">
                        <img
                            :src="base64img"
                            alt="Captured selfie"
                            class="rounded-full shadow-md w-24 h-24 object-cover ring ring-indigo-300"
                        />

                        <!-- Retry Button if login fails -->
                        <button
                            v-if="showRetry"
                            type="button"
                            @click="retake"
                            class="mt-3 w-full inline-flex justify-center items-center px-4 py-3 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm"
                        >
                            Retake Selfie
                        </button>
                    </div>

                    <!-- Error Messages -->
                    <div v-if="form.errors.base64img || form.errors.email" class="text-sm text-red-600 text-center">
                        {{ form.errors.base64img || form.errors.email }}
                    </div>

                    <!-- Submit Button -->
                    <button
                        v-if="hasCaptured"
                        type="submit"
                        class="mt-3 w-full inline-flex justify-center items-center px-4 py-3 text-base font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm"
                    >
                        Log In with Face
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
