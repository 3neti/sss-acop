<script setup>
defineProps({
    imageBase64: String,
});
</script>

<template>
    <div class="p-6">
        <h2 class="text-lg font-semibold mb-4">Base64 Profile Image</h2>

        <div v-if="imageBase64">
            <img :src="imageBase64" alt="Profile Image" class="rounded-full w-32 h-32 object-cover" />
        </div>
        <div v-else>
            <p class="text-gray-500">No image found.</p>
        </div>
    </div>
</template>
