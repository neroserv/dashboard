<template>
  <li v-if="asLi" class="side-nav-item" :class="{ active: isActive }">
    <Link
      :href="item.url ?? '#'"
      class="side-nav-link"
      :class="{ disabled: item.isDisabled, 'special-menu': item.isSpecial, active: isActive }"
      :target="item.target"
      :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
    >
      <span v-if="item.icon && !isTopLevel" class="menu-icon">
        <Icon :icon="item.icon" />
      </span>
      <span class="menu-text">{{ item.label }}</span>
      <span v-if="item.badge" :class="item.badge.className" class="badge">{{ item.badge.text }}</span>
    </Link>
  </li>
  <Link
    v-else
    :href="item.url ?? '#'"
    class="side-nav-link"
    :class="{ disabled: item.isDisabled, 'special-menu': item.isSpecial, active: isActive }"
    :target="item.target"
    :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
  >
    <span v-if="item.icon && !isTopLevel" class="menu-icon">
      <Icon :icon="item.icon" />
    </span>
    <span class="menu-text">{{ item.label }}</span>
    <span v-if="item.badge" :class="item.badge.className" class="badge">{{ item.badge.text }}</span>
  </Link>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/components/wrappers/Icon.vue';
import type { MenuItemType } from '@/types/paces';

type PropsType = {
  item: MenuItemType;
  isTopLevel?: boolean;
  /** false: nur Link (für äußeres motion.li im Submenü) */
  asLi?: boolean;
};

const props = withDefaults(defineProps<PropsType>(), {
  asLi: true,
});

const page = usePage();
const pathname = computed(() => {
  const url = page.url;
  const q = url.indexOf('?');
  return q >= 0 ? url.slice(0, q) : url;
});

const isActive = computed(() => {
  return !!props.item.url && pathname.value.endsWith(props.item.url);
});
</script>
