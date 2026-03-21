<script setup lang="ts">
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import Icon from '@/components/wrappers/Icon.vue';

defineProps<{
    data?: { minutesAgo?: number | null } | null;
}>();
</script>

<template>
    <AdminDashboardWidgetShell
        variant="stat"
        title="Mollie Webhook"
        description="Letzter empfangener Webhook"
    >
        <template #icon>
            <Icon icon="bolt" aria-hidden="true" />
        </template>
        <p v-if="data?.minutesAgo != null" class="small fw-medium mb-0">
            Vor {{ data.minutesAgo }} Minute(n)
        </p>
        <p v-else class="text-muted small mb-0">
            Kein Webhook empfangen (oder Cache leer)
        </p>
    </AdminDashboardWidgetShell>
</template>
