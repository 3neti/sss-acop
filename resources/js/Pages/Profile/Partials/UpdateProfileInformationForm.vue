<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const profilePhotoUrl = usePage().props.profile_photo_url;

const form = useForm({
    _method: 'PATCH',
    name: user.name,
    email: user.email,
    mobile: user.mobile,
    photo: null, // added
});

const webcamRef = ref(null);
const base64img = ref('');
const hasCaptured = ref(false);
const stream = ref(null);

const startCamera = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: true });

        // Wait until next tick to make sure video element is mounted
        setTimeout(() => {
            if (webcamRef.value) {
                webcamRef.value.srcObject = stream.value;
            }
        }, 50);
    } catch (err) {
        console.error('Camera access failed:', err);
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
    }
};

const capture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = webcamRef.value.videoWidth;
    canvas.height = webcamRef.value.videoHeight;
    canvas.getContext('2d').drawImage(webcamRef.value, 0, 0);
    base64img.value = canvas.toDataURL('image/jpeg');
    hasCaptured.value = true;

    // Convert base64 to Blob and bind to form.photo
    fetch(base64img.value)
        .then(res => res.blob())
        .then(blob => {
            const file = new File([blob], 'captured-selfie.jpg', { type: 'image/jpeg' });
            form.photo = file;
        });
};

onBeforeUnmount(() => stopCamera());

const nameInput = ref(null)
const emailInput = ref(null)

onMounted(() => {
    if (!form.email) {
        emailInput.value?.focus()
    } else {
        nameInput.value?.focus()
    }
})

</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update your account's profile information and email address.
            </p>
        </header>

<!--        <form-->
<!--            @submit.prevent="form.patch(route('profile.update'))"-->
<!--            class="mt-6 space-y-6"-->
<!--        >-->
        <form
            @submit.prevent="form.post(route('profile.update'), { forceFormData: true })"
            class="mt-6 space-y-6"
            enctype="multipart/form-data"
        >
            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    ref="nameInput"
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    ref="emailInput"
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="mobile" value="Mobile" />

                <TextInput
                    id="mobile"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.mobile"
                    required
                    autocomplete="mobile"
                />

                <InputError class="mt-2" :message="form.errors.mobile" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div>
                <InputLabel for="photo" value="Profile Photo" />

                <!-- Webcam Toggle & Live Preview -->
                <div class="mt-2 space-y-2">
                    <button
                        type="button"
                        @click="startCamera"
                        class="text-sm text-indigo-600 hover:underline"
                    >
                        Use Webcam Instead
                    </button>

                    <div v-if="!hasCaptured && stream" class="mt-2">
                        <video
                            ref="webcamRef"
                            autoplay
                            playsinline
                            muted
                            class="rounded-md border w-full max-w-full aspect-video object-cover"
                            width="640"
                            height="480"
                        />
                        <button
                            type="button"
                            @click="capture"
                            class="mt-2 w-full text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2 text-sm"
                        >
                            Capture Selfie
                        </button>
                    </div>
                </div>

                <!-- File Upload Fallback -->
                <input
                    id="photo"
                    type="file"
                    class="mt-2 block w-full"
                    accept="image/*"
                    @change="form.photo = $event.target.files[0]"
                />

                <!-- Preview captured or uploaded -->
                <div class="mt-4">
                    <img
                        v-if="base64img"
                        :src="base64img"
                        alt="Captured selfie"
                        class="h-16 w-16 rounded-full object-cover ring ring-indigo-300"
                    />
                    <img
                        v-else-if="profilePhotoUrl"
                        :src="profilePhotoUrl"
                        alt="Current profile photo"
                        class="h-16 w-16 rounded-full object-cover"
                    />
                </div>

                <InputError class="mt-2" :message="form.errors.photo" />
            </div>

<!--            <div>-->
<!--                <InputLabel for="photo" value="Profile Photo" />-->

<!--                <input-->
<!--                    id="photo"-->
<!--                    type="file"-->
<!--                    class="mt-1 block w-full"-->
<!--                    @change="form.photo = $event.target.files[0]"-->
<!--                />-->

<!--                <div class="mt-2" v-if="profilePhotoUrl">-->
<!--                    <img-->
<!--                        :src="profilePhotoUrl"-->
<!--                        alt="Current profile photo"-->
<!--                        class="h-16 w-16 rounded-full object-cover"-->
<!--                    />-->
<!--                </div>-->

<!--                <InputError class="mt-2" :message="form.errors.photo" />-->
<!--            </div>-->

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
