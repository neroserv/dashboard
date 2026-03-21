<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BButton } from 'bootstrap-vue-next';
import { computed } from 'vue';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';

const props = defineProps<{
    data?: Record<string, unknown> | null;
    title?: string;
    description?: string;
    linkHref?: string;
    linkLabel?: string;
    keys?: string[];
}>();

const entries = computed(() => {
    const d = props.data;
    if (!d || typeof d !== 'object') {
        return [];
    }
    const keys = props.keys ?? Object.keys(d).filter((k) => k !== 'items' && d[k] !== undefined && d[k] !== null);
    return keys.map((k) => [k, d[k]] as const).filter(([, v]) => v !== undefined && v !== null);
});

const isSingleCount = computed(() => entries.value.length === 1 && String(entries.value[0]?.[0]) === 'count');
</script>

<template>
    <AdminDashboardWidgetShell :title="title" :description="description">
        <div v-if="isSingleCount" class="d-flex flex-column gap-2">
            <span class="fs-4 fw-bold lh-sm">{{ entries[0][1] }}</span>
            <div v-if="linkHref">
                <Link :href="linkHref">
                    <BButton variant="outline-primary" size="sm">{{ linkLabel ?? 'Anzeigen' }}</BButton>
                </Link>
            </div>
        </div>
        <dl v-else-if="entries.length" class="row small mb-0 gx-2 gy-1">
            <template v-for="(entry, i) in entries" :key="i">
                <dt class="col-6 text-muted text-capitalize mb-0">
                    {{ String(entry[0]).replace(/_/g, ' ') }}
                </dt>
                <dd class="col-6 fw-medium mb-0 text-end text-break">
                    {{ typeof entry[1] === 'object' ? JSON.stringify(entry[1]) : String(entry[1]) }}
                </dd>
            </template>
        </dl>
        <p v-else class="text-muted small mb-0">Keine Daten.</p>
    </AdminDashboardWidgetShell>
</template>
