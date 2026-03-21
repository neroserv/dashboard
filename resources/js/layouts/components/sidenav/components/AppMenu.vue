<template>
  <ul class="side-nav">
    <template v-for="item in menuItems" :key="item.slug">
      <li v-if="item.isTitle" :key="item.slug" class="side-nav-title">{{ item.label }}</li>
      <template v-for="(child, idx) in item.children || [item]" :key="child.slug ?? idx">
        <MenuItemWithChildren v-if="child.children?.length" :item="child" :open-menu-key="openMenuKey" :set-open-menu-key="setOpenMenuKey" />
        <MenuItem v-else :item="child" />
      </template>
    </template>
  </ul>
</template>

<script setup lang="ts">
import MenuItemWithChildren from '@/layouts/components/sidenav/components/MenuItemWithChildren.vue'
import MenuItem from '@/layouts/components/sidenav/components/MenuItem.vue'
import { findActiveTopLevelExpandableSlug } from '@/composables/sidenavMenuHelpers'
import { useSidebarMenu } from '@/composables/useSidebarMenu'
import { useSidenavMenuStore } from '@/stores/sidenav-menu'
import { scrollToElement } from '@/utils/helpers'
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'

const { menuItems } = useSidebarMenu()
const page = usePage()
const sidenavMenu = useSidenavMenuStore()
const { openMenuKey } = storeToRefs(sidenavMenu)

const pathname = computed(() => {
  const url = page.url
  const q = url.indexOf('?')
  return q >= 0 ? url.slice(0, q) : url
})

if (sidenavMenu.lastAccordionInteractionAt === null) {
  const slug = findActiveTopLevelExpandableSlug(menuItems.value, pathname.value)
  if (slug) {
    sidenavMenu.setOpenMenuKey(slug, 'route')
  }
}

function setOpenMenuKey(key: string | null, source: 'route' | 'user' = 'user'): void {
  sidenavMenu.setOpenMenuKey(key, source)
}

const scrollToActiveLink = () => {
  const activeItem: HTMLAnchorElement | null = document.querySelector('.side-nav-link.active')
  if (activeItem) {
    const simpleBarContent = document.querySelector('#sidenav .simplebar-content-wrapper')
    if (simpleBarContent) {
      const containerRect = simpleBarContent.getBoundingClientRect()
      const itemRect = activeItem.getBoundingClientRect()

      const offset = itemRect.top - containerRect.top - window.innerHeight * 0.4

      scrollToElement(simpleBarContent, simpleBarContent.scrollTop + offset, 500)
    }
  }
}

onMounted(() => {
  setTimeout(() => scrollToActiveLink(), 100)
})
</script>
