<script setup lang="ts">
/**
 * Einheitliches, kompaktes Layout für Admin-Dashboard-Widgets (Bootstrap, wie Kunden-Dashboard).
 */
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        /**
         * stat: eine Zeile Icon + Titel/Beschreibung/Wert (KPI).
         * panel: schmaler Kopf + scrollbarer Inhalt (Listen, Charts).
         */
        variant?: 'stat' | 'panel';
    }>(),
    { variant: 'panel' },
);
</script>

<template>
    <div class="admin-dashboard-widget-shell d-flex flex-column h-100 min-h-0 text-body">
        <!-- KPI: wie Kunden-Dashboard-Stats (kompakt, kein großer Header-Block) -->
        <div
            v-if="variant === 'stat'"
            class="d-flex align-items-center gap-3 p-3 h-100"
        >
            <div class="admin-dashboard-widget-shell__icon rounded flex-shrink-0">
                <slot name="icon" />
            </div>
            <div class="min-w-0 flex-grow-1">
                <p v-if="title" class="text-muted small mb-0 text-truncate" :title="title">
                    {{ title }}
                </p>
                <p
                    v-if="description"
                    class="text-muted mb-1 mb-md-2 lh-sm"
                    style="font-size: 0.6875rem"
                >
                    {{ description }}
                </p>
                <div class="min-w-0">
                    <slot />
                </div>
            </div>
        </div>

        <!-- Listen / Charts / Formulare -->
        <template v-else>
            <div
                v-if="title || description"
                class="flex-shrink-0 px-3 pt-3 pb-2 border-bottom border-light"
            >
                <div
                    v-if="title"
                    class="fw-semibold small mb-0 text-truncate"
                    :title="title"
                >
                    {{ title }}
                </div>
                <div
                    v-if="description"
                    class="text-muted lh-sm mt-1"
                    style="font-size: 0.75rem"
                >
                    {{ description }}
                </div>
            </div>
            <div class="flex-grow-1 min-h-0 px-3 pb-3 pt-2 overflow-auto">
                <slot />
            </div>
        </template>
    </div>
</template>

<style scoped>
.admin-dashboard-widget-shell__icon {
    padding: 0.5rem;
    background-color: rgba(var(--bs-primary-rgb), 0.1);
    color: var(--bs-primary);
    line-height: 1;
}

.admin-dashboard-widget-shell__icon :deep(svg) {
    width: 1.35rem;
    height: 1.35rem;
    display: block;
}
</style>
