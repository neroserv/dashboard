<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import Icon from '@/components/wrappers/Icon.vue';

withDefaults(
    defineProps<{
        data?: { count?: number } | null;
        title?: string;
        description?: string;
        linkHref?: string;
        linkLabel?: string;
        statIcon?: string;
    }>(),
    { statIcon: 'users' },
);
</script>

<template>
    <AdminDashboardWidgetShell variant="stat" :title="title" :description="description">
        <template #icon>
            <Icon :icon="statIcon" aria-hidden="true" />
        </template>
        <span class="fs-4 fw-bold lh-sm d-block">{{ data?.count ?? 0 }}</span>
        <div v-if="linkHref" class="mt-1">
            <Link :href="linkHref" class="btn btn-link btn-sm p-0 align-baseline">
                {{ linkLabel ?? 'Anzeigen' }}
            </Link>
        </div>
    </AdminDashboardWidgetShell>
</template>
