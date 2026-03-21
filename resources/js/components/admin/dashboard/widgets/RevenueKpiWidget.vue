<script setup lang="ts">
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import Icon from '@/components/wrappers/Icon.vue';

defineProps<{
    data?: { value?: number } | null;
    title?: string;
}>();
</script>

<template>
    <AdminDashboardWidgetShell variant="stat" :title="title">
        <template #icon>
            <Icon icon="coins" aria-hidden="true" />
        </template>
        <span class="fs-4 fw-bold lh-sm d-block">{{ (data?.value ?? 0).toFixed(2) }} €</span>
    </AdminDashboardWidgetShell>
</template>
