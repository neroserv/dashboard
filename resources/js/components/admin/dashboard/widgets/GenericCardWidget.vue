<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import Icon from '@/components/wrappers/Icon.vue';

withDefaults(
    defineProps<{
        data?: Record<string, unknown> | null;
        title?: string;
        description?: string;
        linkHref?: string;
        primary?: string;
        secondary?: string;
        tertiary?: string;
        statIcon?: string;
    }>(),
    { statIcon: 'user' },
);
</script>

<template>
    <AdminDashboardWidgetShell variant="stat" :title="title" :description="description">
        <template #icon>
            <Icon :icon="statIcon" aria-hidden="true" />
        </template>
        <div v-if="data" class="small">
            <p v-if="primary" class="fw-semibold mb-1 text-truncate">{{ primary }}</p>
            <p v-if="secondary" class="text-muted mb-1 text-truncate small">{{ secondary }}</p>
            <p v-if="tertiary" class="text-muted mb-2" style="font-size: 0.6875rem">{{ tertiary }}</p>
            <Link
                v-if="linkHref"
                :href="linkHref"
                class="btn btn-link btn-sm p-0"
            >
                Öffnen →
            </Link>
        </div>
        <p v-else class="text-muted small mb-0">Keine Daten.</p>
    </AdminDashboardWidgetShell>
</template>
