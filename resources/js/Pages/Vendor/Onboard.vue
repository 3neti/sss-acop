<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    voucher: '',
});

const submit = () => {
    form.post(route('face.onboard'), {
        onFinish: () => form.reset('voucher'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Onboard" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="voucher" value="Voucher" />

                <TextInput
                    id="voucher"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.voucher"
                    autofocus
                />

                <InputError class="mt-2" :message="form.errors.voucher" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                >
                    Already registered?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Onboard
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
