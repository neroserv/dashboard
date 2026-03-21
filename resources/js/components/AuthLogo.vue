<template>
    <Link href="/" class="logo-dark">
        <img :src="logoForLightBg" alt="Logo" />
    </Link>
    <Link href="/" class="logo-light">
        <img :src="logoForDarkBg" alt="Logo" />
    </Link>
</template>

<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
import { useBrandLogos } from '@/composables/useBrandLogos';

const { logoForDarkBg, logoForLightBg } = useBrandLogos();
</script>
