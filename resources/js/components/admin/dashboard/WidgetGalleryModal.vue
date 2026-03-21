<script setup lang="ts">
import { computed } from 'vue';
import { BCard, BCardBody, BModal } from 'bootstrap-vue-next';
import DashboardWidgetSlot from '@/components/admin/dashboard/DashboardWidgetSlot.vue';
import type { WidgetRegistryItem } from '@/types/admin/dashboard';

const props = defineProps<{
    open: boolean;
    widgetRegistry: WidgetRegistryItem[];
}>();

const emit = defineEmits<{
    close: [];
    addWidget: [key: string];
}>();

const modalModel = computed({
    get: () => props.open,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});

function select(key: string): void {
    emit('addWidget', key);
    emit('close');
}
</script>

<template>
    <BModal
        v-model="modalModel"
        size="xl"
        scrollable
        no-footer
        body-class="pt-0"
        content-class="border-0 shadow"
        header-class="border-bottom pb-3"
    >
        <template #title>
            <span class="me-2">Widget hinzufügen</span>
            <span class="badge bg-primary-subtle text-primary fw-normal">
                {{ widgetRegistry.length }} verfügbar
            </span>
        </template>

        <p class="text-muted small mb-3">
            Wählen Sie ein Widget. Die Vorschau zeigt Demo-Daten.
        </p>

        <div class="row g-3">
            <div
                v-for="widget in widgetRegistry"
                :key="widget.key"
                class="col-12 col-sm-6 col-lg-4 col-xxl-3"
            >
                <button
                    type="button"
                    class="widget-gallery-select btn btn-light border w-100 h-100 text-start p-0 rounded-3 shadow-sm"
                    @click="select(widget.key)"
                >
                    <BCard no-body class="mb-0 border-0 rounded-3 bg-transparent">
                        <BCardBody class="p-3">
                            <div
                                class="widget-gallery-preview rounded-2 bg-body-secondary overflow-hidden border"
                            >
                                <div
                                    class="pointer-events-none widget-gallery-preview-inner"
                                >
                                    <DashboardWidgetSlot
                                        :widget-key="widget.key"
                                        :registry-item="widget"
                                        :preview="true"
                                        :demo-data="widget.demoData ?? null"
                                    />
                                </div>
                            </div>
                            <h6 class="fw-semibold mt-3 mb-1 small text-body">{{ widget.title }}</h6>
                            <p class="text-muted small mb-0 lh-sm">
                                {{ widget.description }}
                            </p>
                        </BCardBody>
                    </BCard>
                </button>
            </div>
        </div>
    </BModal>
</template>

<style scoped>
.widget-gallery-select {
    transition:
        border-color 0.15s ease-in-out,
        box-shadow 0.15s ease-in-out,
        background-color 0.15s ease-in-out;
}

.widget-gallery-select:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0.35rem 1rem rgba(var(--bs-primary-rgb), 0.12) !important;
    background-color: var(--bs-body-bg) !important;
}

.widget-gallery-select:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

.widget-gallery-preview {
    min-height: 7.5rem;
}

.widget-gallery-preview-inner {
    min-height: 7.5rem;
    transform: scale(0.88);
    transform-origin: top left;
    width: 113.6%;
}
</style>
