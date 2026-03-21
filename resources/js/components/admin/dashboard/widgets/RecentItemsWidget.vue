<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';
import { getAdminRecent } from '@/composables/useAdminRecent';
import type { AdminRecentItem } from '@/composables/useAdminRecent';

const recentItems = ref<AdminRecentItem[]>([]);

onMounted(() => {
    recentItems.value = getAdminRecent();
});
</script>

<template>
    <AdminDashboardWidgetShell
        title="Zuletzt angesehen"
        description="Zuletzt geöffnete Kunden und Einträge"
    >
        <ul v-if="recentItems.length" class="list-unstyled d-flex flex-wrap gap-1 mb-0">
            <li v-for="(item, i) in recentItems" :key="i">
                <Link :href="item.url" class="btn btn-sm btn-outline-secondary">
                    {{ item.label }}
                </Link>
            </li>
        </ul>
        <p v-else class="text-muted small mb-0">Noch keine Einträge.</p>
    </AdminDashboardWidgetShell>
</template>
