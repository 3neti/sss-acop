<script setup>
import { usePage } from '@inertiajs/vue3'

const payment = usePage().props.flash.event?.data
</script>

<template>
    <div class="p-6 max-w-md mx-auto text-center">
        <h2 class="text-2xl font-bold text-green-700">✅ Payment Successful</h2>
        <p class="mt-2 text-gray-600">Reference: {{ payment.reference_id }}</p>
        <p>Amount: ₱{{ payment.amount }} ({{ payment.currency }})</p>
        <p class="mt-4">Thank you for using Face Payment.</p>
    </div>
</template>
