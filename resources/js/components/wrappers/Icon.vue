<template>
    <IconifyIcon v-bind="iconifyAttrs" />
</template>

<script setup lang="ts">
import { Icon as IconifyIcon } from '@iconify/vue';
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

type Props = { icon: string };

const props = defineProps<Props>();
const attrs = useAttrs();

const iconifyAttrs = computed(() => ({
    ...attrs,
    icon: `tabler:${props.icon}`,
}));
</script>
