<script setup>
import { onMounted, onBeforeUnmount, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    autoFaceLogin: {
        type: Boolean,
        default: false,
    },
    fields: {
        type: Array,
        default: () => ['email'], // can be ['email', 'mobile', 'otp'], etc.
    },
    fieldLabels: {
        type: Object,
        default: () => ({
            email: 'Email',
            mobile: 'Mobile',
            user_id: 'User ID',
            otp: 'One-Time Password',
            id_number: 'ID Number',
            id_type: 'ID Type'
        }),
    },
    validationRules: {
        type: Object,
        default: () => ({
            email: ['required', 'email'],
            mobile: ['required', 'regex:^09\\d{9}$'],
            user_id: ['required'],
            id_number: ['required'],
            id_type: ['required'],
            otp: ['required', 'digits:6'],
        }),
    },
    idTypes: {
        type: Object,
        default: null,
    }
});

const webcamRef = ref(null);
const base64img = ref('');
const hasCaptured = ref(false);
const stream = ref(null);
const timeoutId = ref(null);
const showRetry = ref(false);
const retrying = ref(false);
const formFields = Object.fromEntries(
    props.fields.map((field) => [field, ''])
);
const form = useForm({
    ...formFields,
    base64img: '',
});

const validateFields = () => {
    for (const field of props.fields) {
        const rules = props.validationRules[field] || [];
        const value = form[field];

        for (const rule of rules) {
            if (rule === 'required' && !value) {
                form.setError(field, `${props.fieldLabels[field] || field} is required.`);
                return false;
            }
            if (rule === 'email' && value && !/^\S+@\S+\.\S+$/.test(value)) {
                form.setError(field, 'Invalid email format.');
                return false;
            }
            if (rule.startsWith('regex:')) {
                const pattern = new RegExp(rule.split('regex:')[1]);
                if (!pattern.test(value)) {
                    form.setError(field, `Invalid ${props.fieldLabels[field] || field}`);
                    return false;
                }
            }
            if (rule.startsWith('digits:')) {
                const length = rule.split('digits:')[1];
                if (!/^\d+$/.test(value) || value.length !== +length) {
                    form.setError(field, `${props.fieldLabels[field] || field} must be ${length} digits`);
                    return false;
                }
            }
        }
    }
    return true;
};

const capture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = webcamRef.value.videoWidth;
    canvas.height = webcamRef.value.videoHeight;
    canvas.getContext('2d').drawImage(webcamRef.value, 0, 0);
    base64img.value = canvas.toDataURL('image/jpeg');
    form.base64img = base64img.value;
    hasCaptured.value = true;
    retrying.value = false; // ✅ hide transition states
    stopCamera(); // 👈 Immediately stop after capture

    if (props.autoFaceLogin) {
        // Automatically login after capture
        submit();
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop());
    }
    if (timeoutId.value) {
        clearTimeout(timeoutId.value);
    }
};

const submit = () => {
    stopCamera();
    if (!validateFields()) return;

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
    retrying.value = true;
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

    // Auto-capture after short delay (let camera warm up)
    setTimeout(() => {
        capture(); // 👈 capture will call submit() again if autoFaceLogin is true
    }, 1000); // adjust delay if needed
};

const fieldTypes = {
    email: 'email',
    mobile: 'tel',
    user_id: 'text',
    id_number: 'text',
    id_type: 'text',
    otp: 'text',
};

</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="min-h-screen flex items-center justify-center bg-gray-50">
            <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Login with Face</h2>

                <form @submit.prevent="submit" class="space-y-6">
                    <div v-for="field in fields" :key="field" class="mb-4">
                        <label :for="field" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ fieldLabels[field] || field }}
                        </label>

                        <!-- Render dropdown for id_type -->
                        <select
                            v-if="field === 'id_type' && props.idTypes"
                            :id="field"
                            v-model="form[field]"
                            class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm"
                        >
                            <option value="">Select ID Type</option>
                            <option
                                v-for="option in props.idTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <!-- Render regular input for all other fields -->
                        <input
                            v-else
                            :id="field"
                            v-model="form[field]"
                            :type="fieldTypes[field] || 'text'"
                            :placeholder="fieldLabels[field]"
                            class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm"
                        />

                        <div v-if="form.errors[field]" class="text-sm text-red-600 mt-1">
                            {{ form.errors[field] }}
                        </div>
                    </div>

                    <!-- Camera or Captured Image -->
                    <div class="relative w-full aspect-video rounded-md border overflow-hidden">
                        <video
                            v-if="!hasCaptured"
                            ref="webcamRef"
                            autoplay
                            playsinline
                            muted
                            class="w-full h-full object-cover"
                        ></video>

                        <img
                            v-else
                            :src="base64img"
                            alt="Captured selfie"
                            class="w-full h-full object-cover"
                        />
                    </div>

                    <!-- Capture or Retry Buttons -->
                    <div class="mt-3 flex justify-between gap-2">
                        <button
                            v-if="!hasCaptured && !retrying"
                            type="button"
                            @click="capture"
                            class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm"
                        >
                            📸 Capture Selfie
                        </button>

                        <button
                            v-if="hasCaptured && showRetry && !retrying"
                            type="button"
                            @click="retake"
                            class="w-full px-4 py-2 text-sm font-medium text-indigo-700 border border-indigo-300 rounded-md hover:bg-indigo-50"
                        >
                            🔁 Retake Selfie
                        </button>
                    </div>

                    <!-- Error Messages -->
                    <div v-if="form.errors.base64img || form.errors.email" class="text-sm text-red-600 text-center">
                        {{ form.errors.base64img || form.errors.email }}
                    </div>

                    <!-- Submit Button -->
                    <button
                        v-if="hasCaptured && !autoFaceLogin && !retrying"
                        type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md shadow-sm"
                    >
                        ✅ Log In with Face
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
