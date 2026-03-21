<script setup lang="ts">
import AdminSearch from '@/components/AdminSearch.vue';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
</script>

<template>
    <AdminDashboardWidgetShell
        title="Globale Suche"
        description="Kunden, Rechnungen, Abos (Mollie-ID) durchsuchen"
    >
        <AdminSearch />
    </AdminDashboardWidgetShell>
</template>
