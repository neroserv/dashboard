<!--
  Admin-Dashboard: Slot pro Grid-Zelle — JSON via admin/dashboard/widgets/{key},
  Karten wie StarterKit ecommerce (BCard, card-h-100).
-->
<script setup lang="ts">
import { BButton, BCard, BCardBody } from 'bootstrap-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Icon from '@/components/wrappers/Icon.vue';
import WidgetRenderer from '@/components/admin/dashboard/WidgetRenderer.vue';
import adminDashboard from '@/routes/admin/dashboard';
import type { WidgetRegistryItem } from '@/types/admin/dashboard';

const props = withDefaults(
    defineProps<{
        widgetKey: string;
        registryItem?: WidgetRegistryItem | null;
        preview?: boolean;
        demoData?: Record<string, unknown> | null;
        /** Im Dashboard-Bearbeitungsmodus: roter Löschen-Button oben rechts */
        removable?: boolean;
    }>(),
    { preview: false, registryItem: null, demoData: null, removable: false },
);

const emit = defineEmits<{
    remove: [];
}>();

const data = ref<Record<string, unknown> | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const isClientOnly = computed(() => ['global-search', 'recent-items'].includes(props.widgetKey));

const loadingTitle = computed(() => props.registryItem?.title ?? 'Widget');

async function fetchData(): Promise<void> {
    if (props.preview && props.demoData) {
        data.value = props.demoData;
        loading.value = false;

        return;
    }
    if (props.preview) {
        data.value = (props.registryItem?.demoData ?? null) as Record<string, unknown> | null;
        loading.value = false;

        return;
    }
    if (isClientOnly.value) {
        loading.value = false;

        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const url = adminDashboard.widgets.show.url({ widgetKey: props.widgetKey });
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) {
            throw new Error(res.statusText);
        }
        data.value = await res.json();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Fehler';
        data.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void fetchData();
});
watch(
    () => [props.widgetKey, props.preview] as const,
    () => {
        void fetchData();
    },
);

defineExpose({ refresh: fetchData });
</script>

<template>
    <BCard
        no-body
        class="admin-dashboard-widget-slot card card-h-100 mb-0 h-100 border shadow-sm position-relative"
    >
        <BButton
            v-if="removable"
            type="button"
            variant="danger"
            size="sm"
            class="admin-dashboard-widget-remove position-absolute top-0 end-0 m-2 z-3 rounded-circle p-2 lh-1 shadow-sm"
            aria-label="Widget entfernen"
            title="Widget entfernen"
            @click.stop.prevent="emit('remove')"
            @mousedown.stop
        >
            <Icon icon="trash" class="d-block" aria-hidden="true" />
        </BButton>
        <BCardBody v-if="loading" class="placeholder-glow p-3">
            <span class="placeholder col-9 mb-2 d-block rounded" style="height: 0.7rem" />
            <span class="placeholder col-6 mb-3 d-block rounded" style="height: 0.55rem" />
            <span class="placeholder col-5 d-block rounded" style="height: 1.35rem" />
            <span class="visually-hidden">{{ loadingTitle }} wird geladen</span>
        </BCardBody>

        <BCardBody v-else-if="error" class="d-flex flex-column align-items-center justify-content-center text-center py-3 px-3">
            <p class="text-danger fw-semibold small mb-1 mb-md-2">
                Widget konnte nicht geladen werden
            </p>
            <p class="text-muted small mb-0 px-2" style="max-width: 18rem">
                {{ error }}
            </p>
        </BCardBody>

        <BCardBody v-else class="p-0 d-flex flex-column flex-grow-1" style="min-height: 0">
            <WidgetRenderer
                :widget-key="widgetKey"
                :data="data"
                :registry-item="registryItem"
            />
        </BCardBody>
    </BCard>
</template>
