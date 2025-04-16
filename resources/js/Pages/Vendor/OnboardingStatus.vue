<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    transactionId: String,
})

const status = ref(null)
const loading = ref(true)
const error = ref(null)
const interval = ref(null)

const checkStatus = async () => {
    try {
        const response = await axios.get(route('onboarding.status.check', { transactionId: props.transactionId }))
        status.value = response.data.status

        if (status.value === 'auto_approved') {
            clearInterval(interval.value)
            // router.visit(route('profile.edit'))
            router.visit(route('onboarding.redirect', props.transactionId))
        } else if (status.value === 'user_cancelled') {
            clearInterval(interval.value)
            error.value = 'You have cancelled the onboarding process.'
            loading.value = false
        }
    } catch (err) {
        clearInterval(interval.value)
        error.value = 'An error occurred while checking status.'
        loading.value = false
    }
}

onMounted(() => {
    interval.value = setInterval(checkStatus, 2000)
})

onBeforeUnmount(() => {
    clearInterval(interval.value)
})
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
        <Head title="Verifying..." />

        <div class="p-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl text-center max-w-md w-full">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white mb-2">
                Verifying Your Identity
            </h1>

            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
                Please wait while we confirm your onboarding status. This may take a few seconds.
            </p>

            <div v-if="loading" class="animate-pulse text-blue-600 dark:text-blue-400">
                Checking status...
            </div>

            <div v-if="error" class="text-red-600 dark:text-red-400 mt-4">
                {{ error }}
            </div>

            <div v-if="status === 'user_cancelled'" class="mt-4">
                <a :href="route('onboard')" class="text-blue-600 dark:text-blue-400 underline text-sm">
                    Try again
                </a>
            </div>
        </div>
    </div>
</template>
