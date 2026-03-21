<template>
  <header ref="topbar" class="app-topbar" :class="{ 'topbar-active': isActive }">
    <BContainer fluid class="topbar-menu">
      <div class="d-flex align-items-center gap-2">
        <!-- Topbar Brand Logo -->
        <div class="logo-topbar">
          <Link href="/" class="logo-light">
            <span class="logo-lg">
              <img :src="logoForDarkBg" alt="logo" />
            </span>
            <span class="logo-sm">
              <img :src="logoCollapsed" alt="small logo" />
            </span>
          </Link>

          <Link href="/" class="logo-dark">
            <span class="logo-lg">
              <img :src="logoForLightBg" alt="dark logo" />
            </span>
            <span class="logo-sm">
              <img :src="logoCollapsed" alt="small logo" />
            </span>
          </Link>
        </div>

        <!-- Sidebar Menu Toggle Button -->
        <MenuToggler />

        <!-- Horizontal Menu Toggle Button -->
        <button class="topnav-toggle-button px-2" data-bs-toggle="collapse" data-bs-target="#topnav-menu">
          <Icon icon="menu-4" />
        </button>

        <MegamenuColumns />

        <MegamenuApps />
      </div>

      <div class="d-flex align-items-center gap-2">
        <ThemeDropdown />

        <FullscreenToggler />

        <MonochromeToggler />

        <UserDropdownDetailed />
      </div>
    </BContainer>
  </header>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, provide } from 'vue';
import Icon from '@/components/wrappers/Icon.vue';
import { useBrandLogos } from '@/composables/useBrandLogos';
import { topbarDropdownKey } from '@/composables/useTopbarDropdown';
import FullscreenToggler from './components/FullscreenToggler.vue';
import MegamenuApps from './components/MegamenuApps.vue';
import MegamenuColumns from './components/MegamenuColumns.vue';
import MenuToggler from './components/MenuToggler.vue';
import MonochromeToggler from './components/MonochromeToggler.vue';
import NotificationDropdownPeople from './components/NotificationDropdownPeople.vue';
import SearchBoxRounded from './components/SearchBoxRounded.vue';
import ThemeDropdown from './components/ThemeDropdown.vue';
import UserDropdownDetailed from './components/UserDropdownDetailed.vue';

const { logoForDarkBg, logoForLightBg, logoCollapsed } = useBrandLogos();

const isActive = ref(false);
const topbar = ref<HTMLElement | null>(null)

const openDropdownId = ref<string | null>(null)
provide(topbarDropdownKey, {
    openDropdownId,
    setOpen: (id: string | null) => {
        openDropdownId.value = id
    },
})

const onScroll = () => {
  isActive.value = window.scrollY > 50
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
})
</script>
