<template>
  <DefaultLayout>
    <Head title="Webspace" />
    <PageBreadcrumb title="Webspace" subtitle="Dashboard" subtitle-url="/dashboard" />

    <div class="mb-4">
      <h4 class="mb-1">Webspace</h4>
      <p class="text-muted mb-0">
        Professioneller Webspace – wählen Sie bei mehreren Angeboten die Steuerungssoftware und Ihr Paket
      </p>
    </div>

    <div v-if="available_webspace_panels.length > 1" class="mb-4">
      <label class="form-label" for="webspace-panel">Steuerungssoftware</label>
      <BFormSelect
        id="webspace-panel"
        :model-value="selected_webspace_panel"
        :options="panelSelectOptions"
        class="max-w-md"
        @update:model-value="onPanelChange"
      />
    </div>

    <BRow class="g-4">
      <BCol v-for="plan in hostingPlans" :key="plan.id" xs="12" md="6" lg="4">
        <BCard no-body class="h-100">
          <BCardBody class="d-flex flex-column">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="rounded bg-primary bg-opacity-10 p-2">
                <Icon icon="server" class="fs-5 text-primary" />
              </div>
              <h5 class="mb-0">{{ plan.name }}</h5>
            </div>
            <p class="text-muted small mb-3">{{ plan.disk_gb }} GB NVMe · {{ plan.traffic_gb }} GB Traffic/Monat</p>
            <ul class="list-unstyled small text-muted mb-3">
              <li>{{ plan.disk_gb }} GB NVMe Webspace</li>
              <li>{{ plan.traffic_gb }} GB Traffic im Monat</li>
              <li>{{ plan.domains }} Domain / {{ plan.subdomains }} Subdomains</li>
              <li>{{ plan.mailboxes }} Mailpostfächer</li>
              <li>{{ plan.databases }} Datenbanken</li>
            </ul>
            <p class="mb-3">
              <span class="fs-4 fw-semibold">{{ plan.price }} €</span>
              <span class="text-muted small">/ Monat</span>
            </p>
            <div class="mt-auto">
              <Link
                :href="`/webspace/checkout?plan=${plan.id}&panel=${encodeURIComponent(selected_webspace_panel)}`"
                class="btn btn-primary w-100"
              >
                Jetzt buchen
              </Link>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <p v-if="hostingPlans.length === 0" class="text-muted">
      Derzeit sind keine Webspace-Pakete verfügbar.
    </p>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { BCol, BCard, BCardBody, BFormSelect, BRow } from 'bootstrap-vue-next'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import Icon from '@/components/wrappers/Icon.vue'

type HostingPlan = {
  id: number
  name: string
  plesk_package_name?: string
  disk_gb: number
  traffic_gb: number
  domains: number
  subdomains: number
  mailboxes: number
  databases: number
  price: string
}

type PanelOption = { value: string; label: string }

const props = defineProps<{
  hostingPlans: HostingPlan[]
  available_webspace_panels: PanelOption[]
  selected_webspace_panel: string
}>()

const panelSelectOptions = computed(() =>
  props.available_webspace_panels.map((p) => ({ value: p.value, text: p.label })),
)

function onPanelChange(v: string | number | null): void {
  if (v === null || v === undefined || v === '') {
    return
  }
  router.get('/webspace', { panel: String(v) }, { preserveState: true, preserveScroll: true })
}
</script>
