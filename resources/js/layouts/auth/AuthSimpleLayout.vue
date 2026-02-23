<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';

interface Props {
    title?: string;
    description?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Welcome',
    description: '',
});

const page = usePage();
const brand = computed(() => page.props.brand as { themeColors?: Record<string, string> } | null);
const brandThemeStyle = computed(() => {
    const colors = brand.value?.themeColors;
    if (!colors || typeof colors !== 'object') return undefined;
    const vars: Record<string, string> = {};
    for (const [key, value] of Object.entries(colors)) {
        if (value) vars[`--${key.replace(/_/g, '-')}`] = value;
    }
    return Object.keys(vars).length ? vars : undefined;
});
</script>

<template>
    <div :style="brandThemeStyle" class="flex min-h-screen items-center justify-center bg-gradient-to-br from-gray-50 via-white to-primary/10 p-4 dark:from-gray-950 dark:via-gray-900 dark:to-primary/20">
        <div class="w-full max-w-md">
            <Card class="shadow-modern-xl">
                <CardHeader class="text-center">
                    <CardTitle class="text-2xl">{{ title }}</CardTitle>
                    <CardDescription v-if="description" class="mt-2">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
