<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BButton } from 'bootstrap-vue-next';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import Icon from '@/components/wrappers/Icon.vue';

defineProps<{
    data?: { unpaidSum?: number; overdueCount?: number } | null;
}>();
</script>

<template>
    <AdminDashboardWidgetShell
        variant="stat"
        title="Offene Posten"
        description="Summe unbezahlter Rechnungen"
    >
        <template #icon>
            <Icon icon="file-text" aria-hidden="true" />
        </template>
        <span class="fs-4 fw-bold lh-sm d-block">{{ (data?.unpaidSum ?? 0).toFixed(2) }} €</span>
        <p class="text-muted small mb-0 mt-1">{{ data?.overdueCount ?? 0 }} überfällig</p>
        <div class="mt-2">
            <Link href="/admin/invoices">
                <BButton variant="outline-primary" size="sm">Rechnungen</BButton>
            </Link>
        </div>
    </AdminDashboardWidgetShell>
</template>
