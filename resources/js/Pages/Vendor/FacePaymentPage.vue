<script setup>
import FacePayOld from '@/Components/FacePayOld.vue'
import FacePay from "@/Components/FacePay.vue";

const props = defineProps({
    vendorId: Number,
    currency: {
        type: String,
        default: 'PHP'
    },
    callbackUrl: {
        type: String,
        default: null
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center">
        <FacePay
            :vendor-id="vendorId"
            :currency="currency"
            :callback-url="callbackUrl"
            amount="200"
            item-description="The quick brown fox"/>
    </div>
</template>
