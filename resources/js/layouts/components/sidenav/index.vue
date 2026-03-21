<template>
  <div class="sidenav-menu">
    <Link href="/" class="logo">
      <span class="logo logo-light">
        <span class="logo-lg"><img :src="logoForDarkBg" alt="logo" /></span>
        <span class="logo-sm"><img :src="logoCollapsed" alt="small logo" /></span>
      </span>

      <span class="logo logo-dark">
        <span class="logo-lg"><img :src="logoForLightBg" alt="dark logo" /></span>
        <span class="logo-sm"><img :src="logoCollapsed" alt="small logo" /></span>
      </span>
    </Link>

    <OffcanvasToggle />

    <simplebar id="sidenav" class="scrollbar">
      <UserProfileSettings />

      <AppMenu />
    </simplebar>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import simplebar from 'simplebar-vue';
import { useBrandLogos } from '@/composables/useBrandLogos';
import AppMenu from './components/AppMenu.vue';
import OffcanvasToggle from './components/OffcanvasToggle.vue';
import UserProfileSettings from './components/UserProfileSettings.vue';

const { logoForDarkBg, logoForLightBg, logoCollapsed } = useBrandLogos();
</script>
