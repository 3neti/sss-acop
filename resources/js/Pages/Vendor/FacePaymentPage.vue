<script setup>
import FacePay from "@/Components/FacePay.vue";
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    // vendorId: Number,
    voucher_code: String,
    reference_id: String,
    item_description: String,
    amount: Number,
    currency: {
        type: String,
        default: 'PHP'
    },
    id_type: {
        type: String,
        default: '',
    },
    id_number: {
        type: String,
        default: '',
    },
    callbackUrl: {
        type: String,
        default: null
    }
})

watch(
    () => usePage().props.flash.event,
    (event) => {
        if (event?.name === 'payment_successful') {
            console.log('✅ Payment success:', event);
            alert(`✅ Paid ₱${event.data?.amount} for ${event.data?.item_description}!`);

            if (event.data?.callback_url) {
                window.location.href = event.data.callback_url + '?status=success&ref=' + event.data.reference_id;
            }
        }
    }
);
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center">
        <FacePay
            :voucher_code="voucher_code"
            vendor-id="vendorId"
            :reference_id="reference_id"
            :item_description="item_description"
            :amount="amount"
            :currency="currency"
            :id_type="id_type"
            :id_number="id_number"
            :callback-url="callbackUrl"
        />
    </div>
</template>
