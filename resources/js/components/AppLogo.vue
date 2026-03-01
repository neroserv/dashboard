<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

import { usePage } from "@inertiajs/vue3";
const page = usePage();
</script>

<template>
    <div class="flex items-center gap-2">
  
        <div class="ml-1 grid min-w-0 flex-1 text-left text-sm">
            <img :src="page.props.brand.logoUrl" alt="Logo" class="size-64" v-if="page.props.brand.logoUrl" />
            <span class="mb-0.5 truncate leading-tight font-semibold" v-else>
                {{page.props.brand.name}}
            </span>
        </div>
    </div>
</template>
