<script setup>
import { Link } from '@inertiajs/vue3';

const handleImageError = (event) => {
    console.error('Logo failed to load');
    event.target.style.display = 'none';
};
</script>

<template>
    <Link :href="'/'">
        <img 
            src="/images/logo/ash-health-care.png" 
            alt="ASH Health Care" 
            class="h-16 w-auto mx-auto mb-4"
            @error="handleImageError"
        />
    </Link>
</template>
