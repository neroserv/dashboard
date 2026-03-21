<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { getInitials } from '@/composables/useInitials';

defineOptions({
    inheritAttrs: false,
});

const attrs = useAttrs();

const props = withDefaults(
    defineProps<{
        name: string;
        src?: string | null;
        size?: number;
        roundedClass?: string;
    }>(),
    {
        size: 32,
        roundedClass: 'rounded-circle',
    },
);

const initials = computed(() => getInitials(props.name));
const letters = computed(() => initials.value.split(''));
const showImg = computed(() => Boolean(props.src?.trim()));
</script>

<template>
    <img
        v-if="showImg"
        v-bind="attrs"
        :src="src!.trim()"
        :alt="`Profilbild ${name}`"
        class="flex-shrink-0 object-fit-cover"
        :class="roundedClass"
        :width="size"
        :height="size"
    />
    <div
        v-else
        v-bind="attrs"
        class="user-avatar-initials d-flex flex-shrink-0 align-items-center justify-content-center overflow-hidden"
        :class="roundedClass"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            '--uai-font': `${Math.max(10, Math.round(size * 0.34))}px`,
        }"
        :aria-label="`Initialen ${initials}`"
        role="img"
    >
        <span class="user-avatar-initials-inner d-flex align-items-center justify-content-center lh-1">
            <span
                v-for="(c, i) in letters"
                :key="i"
                class="user-avatar-initials-char fw-bold"
            >{{ c }}</span>
        </span>
    </div>
</template>

<style scoped>
.user-avatar-initials {
    background: linear-gradient(
        145deg,
        rgba(79, 70, 229, 0.96) 0%,
        rgba(147, 51, 234, 0.93) 48%,
        rgba(219, 39, 119, 0.9) 100%
    );
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.user-avatar-initials-char {
    font-size: var(--uai-font, 12px);
    line-height: 1;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
</style>
