<template>
  <component :is="rootIsMotion ? motion.li : 'li'" class="side-nav-item" :class="{ active: isOpen }" v-bind="rootMotionBind">
    <button class="side-nav-link" type="button" :aria-expanded="isOpen" @click.stop="toggleOpen">
      <span v-if="item.icon && isTopLevel" class="menu-icon"><Icon :icon="item.icon" /></span>
      <span class="menu-text">{{ item.label }}</span>
      <span v-if="item.badge" :class="item.badge.className" class="badge">{{ item.badge.text }}</span>
      <span v-else class="menu-arrow"></span>
    </button>
    <AnimatePresence :initial="false">
      <motion.div
        v-if="isOpen"
        :key="`nav-sub-${item.slug}`"
        class="side-nav-submenu-wrap overflow-hidden"
        :variants="submenuPanelVariants"
        initial="collapsed"
        animate="open"
        exit="collapsed"
      >
        <motion.ul
          class="sub-menu"
          :variants="submenuListVariants"
          initial="collapsed"
          animate="open"
          exit="collapsed"
        >
          <template v-for="(child, idx) in item.children" :key="child.slug ?? idx">
            <MenuItemWithChildren v-if="child.children" :item="child" :level="(level ?? 0) + 1" />
            <motion.li v-else class="side-nav-item" :variants="submenuRowVariants">
              <MenuItem :item="child" :is-top-level="isTopLevel" :as-li="false" />
            </motion.li>
          </template>
        </motion.ul>
      </motion.div>
    </AnimatePresence>
  </component>
</template>

<script setup lang="ts">
import type { MenuItemType } from '@/types/paces'
import MenuItem from '@/layouts/components/sidenav/components/MenuItem.vue'
import Icon from '@/components/wrappers/Icon.vue'
import { AnimatePresence, motion } from 'motion-v'
import { useSidenavMenuStore } from '@/stores/sidenav-menu'
import { computed } from 'vue'

type PropsType = {
  item: MenuItemType
  level?: number
}

const props = defineProps<PropsType>()
const sidenavMenu = useSidenavMenuStore()

const isTopLevel = computed(() => (props.level ?? 0) === 0)

const isOpen = computed(() =>
  isTopLevel.value
    ? sidenavMenu.isTopLevelBranchOpen(props.item.slug)
    : sidenavMenu.isNestedBranchOpen(props.item.slug),
)

const rootIsMotion = computed(() => !isTopLevel.value)

const rootMotionBind = computed(() =>
  rootIsMotion.value ? { variants: submenuRowVariants, initial: 'collapsed' } : {},
)

const easeOut = [0.19, 1, 0.22, 1] as const
const easeIn = [0.4, 0, 0.9, 1] as const

const submenuPanelVariants = {
  open: {
    height: 'auto',
    opacity: 1,
    transition: {
      height: { duration: 0.42, ease: easeOut },
      opacity: { duration: 0.22, ease: easeOut },
    },
  },
  collapsed: {
    height: 0,
    opacity: 0,
    transition: {
      opacity: { duration: 0.14, delay: 0.02 },
      height: { duration: 0.34, ease: easeIn, delay: 0.12 },
    },
  },
}

const submenuListVariants = {
  open: {
    transition: {
      delayChildren: 0.05,
      staggerChildren: 0.058,
    },
  },
  collapsed: {
    transition: {
      staggerChildren: 0.038,
      staggerDirection: -1,
    },
  },
}

const submenuRowVariants = {
  open: {
    opacity: 1,
    x: 0,
    y: 0,
    scale: 1,
    filter: 'blur(0px)',
    transition: {
      type: 'spring',
      stiffness: 520,
      damping: 26,
      mass: 0.55,
    },
  },
  collapsed: {
    opacity: 0,
    x: -24,
    y: -18,
    scale: 0.96,
    filter: 'blur(7px)',
    transition: {
      duration: 0.2,
      ease: easeIn,
    },
  },
}

function toggleOpen(): void {
  if (isTopLevel.value) {
    sidenavMenu.toggleTopLevelBranch(props.item.slug)
  } else {
    sidenavMenu.toggleNestedBranch(props.item.slug)
  }
}
</script>
