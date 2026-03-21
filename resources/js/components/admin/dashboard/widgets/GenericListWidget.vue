<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';

const props = withDefaults(
    defineProps<{
        data?: Record<string, unknown> | null;
        title?: string;
        description?: string;
        itemsKey?: string;
        itemLink?: (item: Record<string, unknown>) => string;
        itemLabel?: (item: Record<string, unknown>) => string;
    }>(),
    { itemsKey: 'items' },
);

const items = computed(() => {
    const d = props.data;
    if (!d) {
        return [];
    }
    const list = d[props.itemsKey];
    return Array.isArray(list) ? list : [];
});
</script>

<template>
    <AdminDashboardWidgetShell :title="title" :description="description">
        <ul v-if="items.length" class="list-unstyled small mb-0">
            <li v-for="(item, i) in items" :key="i" class="mb-1 text-truncate">
                <Link
                    v-if="itemLink?.(item)"
                    :href="itemLink(item)"
                    class="text-primary text-decoration-none"
                >
                    {{ itemLabel?.(item) ?? (item as { number?: string }).number ?? String((item as { id?: number }).id ?? i) }}
                </Link>
                <span v-else>{{
                    itemLabel?.(item) ?? (item as { number?: string }).number ?? String((item as { id?: number }).id ?? i)
                }}</span>
            </li>
        </ul>
        <p v-else class="text-muted small mb-0">Keine Einträge.</p>
    </AdminDashboardWidgetShell>
</template>
