<template>
  <DefaultLayout>
    <Head :title="`Domain: ${domain.domain}`" />
    <PageBreadcrumb :title="domain.domain" subtitle="Meine Domains" :subtitle-url="domains_index_url" />

    <div class="row g-4">
      <aside class="col-lg-3">
        <BCard no-body>
          <BCardBody class="border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div class="text-start flex-grow-1">
                <BButton
                  v-if="domain.auto_renew"
                  variant="secondary"
                  size="sm"
                  class="text-start"
                  :disabled="autorenewSubmitting"
                  @click="setAutoRenew(false)"
                >
                  <Icon icon="shield-check" class="me-1" />
                  Auto. Verlängerung
                </BButton>
                <BButton
                  v-else
                  variant="outline-warning"
                  size="sm"
                  class="text-start text-warning-emphasis"
                  :disabled="autorenewSubmitting"
                  @click="setAutoRenew(true)"
                >
                  <Icon icon="shield-x" class="me-1" />
                  Manuelle Verlängerung
                </BButton>
              </div>
              <div class="d-flex flex-column align-items-end gap-1 shrink-0">
                <BBadge :variant="domainStatusBadgeVariant">{{ domainStatusLabel }}</BBadge>
                <BBadge v-if="domain.is_sandbox" variant="warning" class="text-dark">Sandbox</BBadge>
              </div>
            </div>
            <div class="text-center">
              <div class="mb-2 d-flex justify-content-center">
                <Icon icon="server" class="fs-1 text-muted" />
              </div>
              <h5 class="mt-0 mb-0">Domain</h5>
              <p class="text-muted small mb-0">{{ domain.domain }}</p>
            </div>
          </BCardBody>
          <BCardBody>
            <div class="d-grid gap-2">
              <BButton variant="outline-danger" size="sm" class="text-start" @click="openNameserverDialog">
                <Icon icon="pencil" class="me-2" />
                Nameserver ändern
              </BButton>
              <BButton variant="outline-secondary" size="sm" class="text-start" @click="openDnssecDialog">
                <Icon icon="shield-check" class="me-2" />
                DNSSEC verwalten
              </BButton>
              <BButton
                v-if="domain.renew_price != null"
                variant="outline-primary"
                size="sm"
                class="text-start"
                @click="renewDialogOpen = true"
              >
                <Icon icon="calendar-plus" class="me-2" />
                Verlängern
              </BButton>
            </div>
          </BCardBody>
        </BCard>
      </aside>
      <div class="col-lg-9">
        <BAlert
          v-if="domain.show_rr_pending_validation_notice"
          variant="warning"
          show
          class="mb-3"
        >
          <strong>Registrierung wird geprüft.</strong>
          Realtime Register hat Ihnen eine E-Mail zur Bestätigung geschickt. Bitte folgen Sie den Anweisungen in der Nachricht
          (z.&nbsp;B. Link klicken). Erst danach ist die Domain vollständig freigeschaltet.
        </BAlert>
        <BNav tabs class="mb-3 flex-wrap">
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'overview'" @click="activeTab = 'overview'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="layout-dashboard" />
              </span>
              <span>Übersicht</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'dns'" @click="activeTab = 'dns'; loadDnsRecords()">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="world-www" />
              </span>
              <span>DNS Manager</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'contact'" @click="activeTab = 'contact'; loadContact()">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="user" />
              </span>
              <span>Kontakt</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'whois'" @click="activeTab = 'whois'; loadWhoisLookup()">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="shield-check" />
              </span>
              <span>Whois Privacy</span>
            </span>
          </BNavItem>
          <BNavItem
            v-if="canManageCollaborators"
            link-class="d-inline-flex align-items-center"
            :active="activeTab === 'sharing'"
            @click="activeTab = 'sharing'"
          >
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="share" />
              </span>
              <span>Teilen</span>
            </span>
          </BNavItem>
        </BNav>

        <BCard v-show="activeTab === 'overview'" no-body>
          <BCardBody>
            <BTable :items="overviewRows" :fields="[{ key: 'label', label: '' }, { key: 'value', label: '' }]" small>
              <template #cell(value)="{ item }">
                <span v-if="item.key === 'authcode'">
                  <BButton size="sm" variant="outline-secondary" @click="fetchAuthcode">Authcode anzeigen</BButton>
                </span>
                <template v-else>{{ item.value }}</template>
              </template>
            </BTable>
          </BCardBody>
        </BCard>

        <BCard v-show="activeTab === 'dns'" no-body>
          <BCardBody>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">{{ dns_manager?.ui?.tab_title ?? 'DNS Manager' }}</h5>
              <div class="d-flex align-items-center gap-3">
                <BButton
                  v-if="dnsMode === 'simple'"
                  size="sm"
                  variant="success"
                  :disabled="dnsLoading || dnsSaving"
                  @click="openEasyDnsDialog"
                >
                  {{ dns_manager?.toolbar?.easy_dns_button_label ?? 'Easy DNS' }}
                </BButton>

                <BButton
                  v-if="dnsMode === 'simple'"
                  size="sm"
                  variant="primary"
                  :disabled="dnsLoading || dnsSaving"
                  @click="openAddDnsRecordDialog"
                >
                  {{ dns_manager?.toolbar?.add_record_button_label ?? 'Eintrag hinzufügen' }}
                </BButton>

                <div id="expertModeToggleContainer">
                  <div class="d-flex align-items-center">
                    <span class="me-2">{{ dns_manager?.ui?.mode?.easy_label ?? 'Easy Mode' }}</span>
                    <div class="form-check form-switch">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="expertModeToggle"
                        :checked="dnsMode === 'pro'"
                        @change="dnsMode = $event.target.checked ? 'pro' : 'simple'"
                      >
                    </div>
                    <span class="ms-2">{{ dns_manager?.ui?.mode?.expert_label ?? 'Expert Mode' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="dnsMode === 'simple'" class="d-flex justify-content-between align-items-center mb-3">
              <BButton size="sm" @click="loadDnsRecords" :disabled="dnsLoading || dnsSaving">Einträge laden</BButton>
              <BButton size="sm" variant="primary" :disabled="dnsLoading || dnsSaving || !dnsDirty" @click="saveDnsRecords">
                DNS Zone speichern
              </BButton>
            </div>

            <p v-if="dnsError" class="text-danger small">{{ dnsError }}</p>
            <p v-if="dnsFormError" class="text-danger small">{{ dnsFormError }}</p>
            <p v-if="dnsInfo" class="text-success small">{{ dnsInfo }}</p>

            <BTable
              v-if="dnsMode === 'simple'"
              :items="dnsDraftRecords"
              :fields="[{ key: 'type', label: 'Typ' }, { key: 'name', label: 'Name' }, { key: 'data', label: 'Inhalt' }, { key: 'actions', label: '' }]"
              small
              responsive
            >
              <template #cell(actions)="{ item }">
                <BButton size="sm" variant="outline-danger" @click="removeDnsRecord(item)">Entfernen</BButton>
              </template>
            </BTable>

            <div v-else class="table-responsive mt-3">
              <table class="table mb-0">
                <thead>
                  <tr>
                    <th scope="col" class="border-bottom">Name</th>
                    <th scope="col" class="border-bottom">Typ</th>
                    <th scope="col" class="border-bottom">Inhalt</th>
                    <th scope="col" class="border-bottom" style="width: 80px;">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(record, idx) in dnsDraftRecords" :key="idx">
                    <td>
                      <BFormInput v-model="dnsDraftRecords[idx].name" class="form-control" placeholder="Subdomain" />
                    </td>
                    <td>
                      <BFormSelect v-model="dnsDraftRecords[idx].type" :options="dnsTypeOptions" class="form-select" />
                    </td>
                    <td>
                      <BFormInput v-model="dnsDraftRecords[idx].data" class="form-control" placeholder="Ziel" />
                    </td>
                    <td class="text-center">
                      <BButton size="sm" variant="outline-danger" @click="removeDnsRecord(record)">Entfernen</BButton>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="d-flex gap-2 mt-3">
                <BButton variant="primary" :disabled="dnsSaving" @click="saveDnsRecords">DNS Zone speichern</BButton>
                <BButton variant="info" :disabled="dnsSaving" type="button" @click="addExpertRow">Zeile hinzufügen</BButton>
              </div>
            </div>

          </BCardBody>
        </BCard>

        <BCard v-show="activeTab === 'contact'" no-body>
          <BCardBody>
            <p v-if="contactError" class="text-danger small">{{ contactError }}</p>
            <BForm v-else @submit.prevent="submitContact" class="row g-3">
              <BCol md="6"><label class="form-label">Firma</label><BFormInput v-model="contactForm.company" /></BCol>
              <BCol md="6"><label class="form-label">Vorname</label><BFormInput v-model="contactForm.firstname" /></BCol>
              <BCol md="6"><label class="form-label">Nachname</label><BFormInput v-model="contactForm.lastname" /></BCol>
              <BCol md="6"><label class="form-label">Straße</label><BFormInput v-model="contactForm.street" /></BCol>
              <BCol md="6"><label class="form-label">Hausnr.</label><BFormInput v-model="contactForm.number" /></BCol>
              <BCol md="6"><label class="form-label">PLZ</label><BFormInput v-model="contactForm.postcode" /></BCol>
              <BCol md="6"><label class="form-label">Ort</label><BFormInput v-model="contactForm.city" /></BCol>
              <BCol md="6"><label class="form-label">Bundesland</label><BFormInput v-model="contactForm.state" /></BCol>
              <BCol md="6"><label class="form-label">Land</label><BFormSelect v-model="contactForm.country" :options="countriesSortedByName" value-field="code" text-field="name" /></BCol>
              <BCol md="6"><label class="form-label">E-Mail</label><BFormInput v-model="contactForm.email" type="email" /></BCol>
              <BCol md="6"><label class="form-label">Telefon</label><BFormInput v-model="contactForm.phone" /></BCol>
              <BCol xs="12"><BButton type="submit" size="sm">Kontakt speichern</BButton></BCol>
            </BForm>
          </BCardBody>
        </BCard>

        <BCard v-show="activeTab === 'whois'" no-body>
          <BCardBody>
            <p v-if="whoisError" class="text-danger small">{{ whoisError }}</p>
            <div v-else class="mb-3">
              <pre class="bg-light p-3 rounded small overflow-auto" style="max-height: 400px;">{{ whoisResult || 'Whois wird geladen…' }}</pre>
            </div>
          </BCardBody>
        </BCard>

        <ProductSharingCard
          v-if="activeTab === 'sharing' && canManageCollaborators"
          :product-shares="productShares ?? []"
          :product-invitations="productInvitations ?? []"
          :allowed-share-permissions="allowedSharePermissions ?? []"
          :store-invitation-url="storeInvitationUrl ?? ''"
        />
      </div>
    </div>

    <BModal v-model="authcodeDialogOpen" title="Authcode" no-footer>
      <BSpinner v-if="authcodeLoading" />
      <p v-else-if="authcodeError" class="text-danger">{{ authcodeError }}</p>
      <code v-else class="d-block p-2 bg-light rounded">{{ authcodeValue || '–' }}</code>
    </BModal>

    <BModal v-model="nameserverDialogOpen" title="Nameserver ändern" no-footer>
      <BForm @submit.prevent="submitNameserver">
        <div v-for="(ns, idx) in nameserverForm.nameservers" :key="idx" class="mb-2 d-flex gap-2">
          <BFormInput v-model="nameserverForm.nameservers[idx]" :placeholder="`Nameserver ${idx + 1}`" />
          <BButton v-if="nameserverForm.nameservers.length > 2" type="button" variant="outline-danger" size="sm" @click="removeNameserverSlot(idx)">×</BButton>
        </div>
        <BButton v-if="nameserverForm.nameservers.length < 6" type="button" variant="outline-secondary" size="sm" class="mb-2" @click="addNameserverSlot">+ Nameserver</BButton>
        <div v-if="nameserverForm.errors.nameservers" class="invalid-feedback d-block">{{ nameserverForm.errors.nameservers }}</div>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <BButton type="button" variant="outline-warning" @click="resetNameserverToDefault">Auf Standard zurücksetzen</BButton>
          <BButton type="button" variant="secondary" @click="nameserverDialogOpen = false">Abbrechen</BButton>
          <BButton type="submit" :disabled="nameserverForm.processing">Speichern</BButton>
        </div>
      </BForm>
    </BModal>

    <BModal v-model="renewDialogOpen" title="Verlängern" no-footer>
      <p class="text-muted small">
        Verlängerung für <strong>{{ domain.renew_price != null ? Number(domain.renew_price).toLocaleString('de-DE', { minimumFractionDigits: 2 }) : '0,00' }} €</strong> pro Jahr.
      </p>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="renewDialogOpen = false">Abbrechen</BButton>
        <BButton variant="primary" @click="renew">Verlängern</BButton>
      </div>
    </BModal>

    <BModal v-model="easyDnsModalOpen" title="Easy DNS" size="xl" centered no-footer>
      <div class="row g-3">
        <div class="col-md-12">
          <label class="form-label">Preset</label>
          <BFormSelect
            v-model="selectedPresetId"
            :options="[
              { value: '', text: 'Bitte wählen' },
              ...easyDnsPresetOptions,
            ]"
          />
        </div>

        <template v-for="field in selectedPresetFields" :key="field.key">
          <div class="col-md-4">
            <label class="form-label">{{ field.label }}</label>
            <BFormInput
              v-model="presetFormValues[field.key]"
              :type="field.type === 'number' ? 'number' : 'text'"
              class="form-control"
              :placeholder="field.placeholder ?? ''"
            />
          </div>
        </template>
      </div>

      <p v-if="dnsFormError" class="text-danger small mt-2">{{ dnsFormError }}</p>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="easyDnsModalOpen = false">Abbrechen</BButton>
        <BButton variant="primary" :disabled="!selectedPreset" @click="applyPresetAndCloseEasyDns">
          {{ selectedPreset?.button_label ?? 'Preset anwenden' }}
        </BButton>
      </div>
    </BModal>

    <BModal
      v-model="addDnsRecordDialogOpen"
      :title="addRecordModalConfig.title ?? 'DNS Eintrag hinzufügen'"
      size="xl"
      centered
      no-footer
      :header-class="addRecordModalConfig.header_class ?? 'border-bottom'"
      :content-class="addRecordModalConfig.content_class ?? ''"
      :body-class="addRecordModalConfig.body_class ?? ''"
    >
      <div :class="addRecordModalConfig.type_select_container_class ?? 'row mb-3'">
        <div :class="addRecordModalConfig.type_select_col_class ?? 'col-md-6'">
          <label class="form-label">{{ addRecordModalConfig.type_label ?? 'Typ' }}</label>
          <BFormSelect v-model="simpleForm.type" :options="dnsAddRecordTypeOptions" />
        </div>

        <div :class="addRecordModalConfig.type_select_col_class ?? 'col-md-6'">
          <label class="form-label">{{ addRecordModalConfig.name_label ?? 'Name' }}</label>
          <BFormInput v-model="simpleForm.name" placeholder="z.B. www, mail oder @ für neroserv.eu" />
        </div>
      </div>

      <div v-if="activeAddRecordTypeConfig" class="mt-2">
        <template v-for="(block, blockIdx) in activeAddRecordTypeConfig.blocks" :key="blockIdx">
          <div :class="block.class">
            <template v-for="field in block.fields ?? []" :key="field.key">
              <div v-if="block.type === 'row'" :class="field.colClass">
                <label class="form-label">{{ field.label }}</label>
                <BFormInput
                  v-if="field.component === 'input'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  :type="field.inputType ?? 'text'"
                  class="form-control"
                  :placeholder="field.placeholder ?? ''"
                />
                <BFormSelect
                  v-else-if="field.component === 'select'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  :options="field.options ?? []"
                  class="form-select"
                />
                <textarea
                  v-else-if="field.component === 'textarea'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  class="form-control"
                  :rows="field.rows ?? 3"
                  :placeholder="field.placeholder ?? ''"
                />
              </div>

              <template v-else>
                <label class="form-label">{{ field.label }}</label>
                <BFormInput
                  v-if="field.component === 'input'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  :type="field.inputType ?? 'text'"
                  class="form-control"
                  :placeholder="field.placeholder ?? ''"
                />
                <BFormSelect
                  v-else-if="field.component === 'select'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  :options="field.options ?? []"
                  class="form-select"
                />
                <textarea
                  v-else-if="field.component === 'textarea'"
                  v-model="dnsAddRecordFieldValues[field.key]"
                  class="form-control"
                  :rows="field.rows ?? 3"
                  :placeholder="field.placeholder ?? ''"
                />
              </template>
            </template>
          </div>
        </template>
      </div>

      <p v-if="dnsFormError" class="text-danger small mt-2">{{ dnsFormError }}</p>

      <div :class="addRecordModalConfig.footer_class ?? 'modal-footer'" class="mt-3">
        <BButton
          type="button"
          :class="addRecordModalConfig.submit_button_class ?? 'btn btn-primary w-100'"
          variant="primary"
          @click="addSimpleRecordAndClose"
        >
          {{ addRecordModalConfig.submit_button_label ?? 'DNS Eintrag hinzufügen' }}
        </BButton>
      </div>
    </BModal>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
  BButton,
  BCard,
  BCardBody,
  BCol,
  BForm,
  BFormInput,
  BFormSelect,
  BNav,
  BNavItem,
  BModal,
  BSpinner,
  BTable,
  BBadge,
  BAlert,
} from 'bootstrap-vue-next'
import Icon from '@/components/wrappers/Icon.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import ProductSharingCard from '@/components/product-sharing/ProductSharingCard.vue'
import { countriesSortedByName } from '@/lib/countries'
import { customerDomainStatusBadgeVariant, customerDomainStatusLabel } from '@/lib/domainCustomerStatus'

const props = withDefaults(
  defineProps<{
    domain: {
      uuid: string
      domain: string
      status: string
      expires_at: string | null
      auto_renew: boolean
      nameservers: string[]
      renew_price: number | null
      registrar?: string
      is_sandbox?: boolean
      show_rr_pending_validation_notice?: boolean
    }
    domains_index_url: string
    canManageCollaborators?: boolean
    productShares?: Array<{ id: number; user: { id: number; name: string; email: string } | null; permissions: string[]; update_url: string; destroy_url: string }>
    productInvitations?: Array<{ id: number; email: string; permissions: string[]; expires_at: string | null; destroy_url: string }>
    allowedSharePermissions?: string[]
    storeInvitationUrl?: string | null
    easy_dns_presets?: Array<{
      id: string
      label?: string
      button_label?: string | null
      fields?: Array<{ key: string; label: string; type?: string; placeholder?: string; default?: string | number }>
      records?: Array<{
        name?: string
        name_key?: string
        name_template?: string
        type: string
        data_key?: string
        data_template?: string
      }>
    }>
    dns_manager?: any
  }>(),
  {
    canManageCollaborators: false,
    productShares: () => [],
    productInvitations: () => [],
    allowedSharePermissions: () => [],
    storeInvitationUrl: null,
    easy_dns_presets: () => [],
    dns_manager: () => ({}),
  },
)

const baseUrl = () => `/domains/${props.domain.uuid}`
const activeTab = ref('overview')

const domainStatusLabel = computed(() => customerDomainStatusLabel(props.domain.status))
const domainStatusBadgeVariant = computed(() => customerDomainStatusBadgeVariant(props.domain.status))

const overviewRows = computed(() => [
  { key: 'domain', label: 'Domain', value: props.domain.domain },
  { key: 'authcode', label: 'Authcode', value: '' },
  { key: 'status', label: 'Registry-Status', value: domainStatusLabel.value },
  { key: 'dns', label: 'DNS-Status', value: 'Aktiv' },
])

const nameserverDialogOpen = ref(false)
const nameserverForm = useForm({
  nameservers: props.domain.nameservers?.length ? [...props.domain.nameservers] : ['', ''],
})
function openNameserverDialog() {
  nameserverForm.nameservers = props.domain.nameservers?.length ? [...props.domain.nameservers] : ['', '']
  if (nameserverForm.nameservers.length < 2) {
    while (nameserverForm.nameservers.length < 2) nameserverForm.nameservers.push('')
  }
  nameserverDialogOpen.value = true
}
function addNameserverSlot() {
  if (nameserverForm.nameservers.length < 6) nameserverForm.nameservers.push('')
}
function removeNameserverSlot(index: number) {
  if (nameserverForm.nameservers.length > 2) nameserverForm.nameservers.splice(index, 1)
}
function submitNameserver() {
  const ns = nameserverForm.nameservers.filter(Boolean)
  if (ns.length < 2 || ns.length > 6) {
    nameserverForm.setError('nameservers', 'Es sind 2 bis 6 Nameserver erforderlich.')
    return
  }
  nameserverForm.nameservers = ns
  nameserverForm.put(`${baseUrl()}/nameserver`, { preserveScroll: true, onSuccess: () => { nameserverDialogOpen.value = false } })
}

function resetNameserverToDefault() {
  router.post(`${baseUrl()}/nameserver/reset`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      nameserverDialogOpen.value = false
    },
  })
}

const authcodeDialogOpen = ref(false)
const authcodeValue = ref('')
const authcodeLoading = ref(false)
const authcodeError = ref('')
function fetchAuthcode() {
  authcodeLoading.value = true
  authcodeError.value = ''
  authcodeValue.value = ''
  authcodeDialogOpen.value = true
  fetch(`${baseUrl()}/authcode`)
    .then((r) => r.json())
    .then((data) => {
      if (data.error) authcodeError.value = data.error
      else authcodeValue.value = data.authcode ?? ''
    })
    .catch(() => { authcodeError.value = 'Fehler beim Laden' })
    .finally(() => { authcodeLoading.value = false })
}

const dnssecDialogOpen = ref(false)

const renewDialogOpen = ref(false)
const autorenewSubmitting = ref(false)

function renew() {
  renewDialogOpen.value = false
  router.post(`${baseUrl()}/renew`)
}

function setAutoRenew(enabled: boolean) {
  autorenewSubmitting.value = true
  router.post(
    `${baseUrl()}/autorenew`,
    { auto_renew: enabled },
    {
      preserveScroll: true,
      onFinish: () => {
        autorenewSubmitting.value = false
      },
    },
  )
}

type DnsRecord = { name: string; type: string; data: string }

const dnsRecords = ref<DnsRecord[]>([])
const dnsDraftRecords = ref<DnsRecord[]>([])
const dnsLoading = ref(false)
const dnsSaving = ref(false)
const dnsError = ref('')
const dnsFormError = ref('')
const dnsInfo = ref('')
const dnsMode = ref<'simple' | 'pro'>('simple')
const simpleForm = ref<{ type: string; name: string; data: string }>({
  type: 'A',
  name: '@',
  data: '',
})
const proForm = ref<DnsRecord>({
  type: 'A',
  name: '@',
  data: '',
})
const dnsTypeOptions = [
  { value: 'A', text: 'A' },
  { value: 'AAAA', text: 'AAAA' },
  { value: 'CNAME', text: 'CNAME' },
  { value: 'ALIAS', text: 'ALIAS' },
  { value: 'MX', text: 'MX' },
  { value: 'NS', text: 'NS' },
  { value: 'SRV', text: 'SRV' },
  { value: 'TXT', text: 'TXT' },
  { value: 'CAA', text: 'CAA' },
  { value: 'PTR', text: 'PTR' },
  { value: 'TLSA', text: 'TLSA' },
  { value: 'DS', text: 'DS' },
  { value: 'DNSKEY', text: 'DNSKEY' },
  { value: 'NAPTR', text: 'NAPTR' },
  { value: 'SOA', text: 'SOA' },
]

const dnsManagerConfig = computed(() => props.dns_manager ?? {})
const addRecordModalConfig = computed(() => dnsManagerConfig.value.add_record_modal ?? {})
const addRecordTypesConfig = computed(() => addRecordModalConfig.value.types ?? {})

const dnsAddRecordTypeOptions = computed(() =>
  (addRecordModalConfig.value.record_types ?? []).map((t: { value: string; label: string }) => ({
    value: t.value,
    text: t.label,
  })),
)

const activeAddRecordTypeConfig = computed(() => {
  const type = simpleForm.value.type
  if (!type) return null
  return addRecordTypesConfig.value[type] ?? null
})

const dnsAddRecordFieldValues = ref<Record<string, string>>({})

function initDnsAddRecordFieldValues(type: string): void {
  const typeConfig = addRecordTypesConfig.value[type]
  if (!typeConfig) {
    dnsAddRecordFieldValues.value = {}
    return
  }

  const values: Record<string, string> = {}
  for (const block of typeConfig.blocks ?? []) {
    for (const field of block.fields ?? []) {
      if (field.default !== undefined) {
        values[field.key] = String(field.default)
      } else if (field.component === 'select' && Array.isArray(field.options) && field.options.length > 0) {
        values[field.key] = String(field.options[0].value)
      } else {
        values[field.key] = ''
      }
    }
  }
  dnsAddRecordFieldValues.value = values
}

function buildDnsAddRecordDataFromConfig(): string {
  const typeConfig = addRecordTypesConfig.value[simpleForm.value.type]
  const template = typeConfig?.data_template
  if (!template || typeof template !== 'string') return ''

  // Allow templates to reference fields by key.
  const values: Record<string, string> = {
    ...dnsAddRecordFieldValues.value,
    domain: props.domain.domain,
  }

  const placeholders = [...template.matchAll(/\{\{([^}]+)\}\}/g)].map((m) => m[1].trim())
  for (const key of placeholders) {
    if (!Object.prototype.hasOwnProperty.call(values, key) || values[key].trim() === '') {
      return ''
    }
  }

  return templateValue(template, values).trim()
}

watch(
  () => simpleForm.value.type,
  (newType) => {
    initDnsAddRecordFieldValues(newType)
  },
  { immediate: true },
)
const selectedPresetId = ref('')
const easyDnsModalOpen = ref(false)
const addDnsRecordDialogOpen = ref(false)
const presetFormValues = ref<Record<string, string>>({})
const easyDnsPresetOptions = computed(() =>
  (props.easy_dns_presets ?? []).map((preset) => ({ value: preset.id, text: preset.label ?? preset.id })),
)
const selectedPreset = computed(() =>
  (props.easy_dns_presets ?? []).find((preset) => preset.id === selectedPresetId.value) ?? null,
)
const selectedPresetFields = computed(() => selectedPreset.value?.fields ?? [])
const dnsDirty = computed(() => JSON.stringify(dnsRecords.value) !== JSON.stringify(dnsDraftRecords.value))

function normalizeDnsName(name: string): string {
  const normalized = name.trim().toLowerCase()
  return normalized === '' ? '@' : normalized
}

function normalizeRecord(record: DnsRecord): DnsRecord {
  return {
    name: normalizeDnsName(record.name),
    type: record.type.trim().toUpperCase(),
    data: record.data.trim(),
  }
}

function recordKey(record: DnsRecord): string {
  return `${record.type}\u0000${record.name}`
}

function mergeRecordIntoDraft(input: DnsRecord): void {
  const record = normalizeRecord(input)
  const map = new Map<string, DnsRecord>()
  for (const existing of dnsDraftRecords.value) {
    map.set(recordKey(normalizeRecord(existing)), normalizeRecord(existing))
  }
  map.set(recordKey(record), record)
  dnsDraftRecords.value = Array.from(map.values())
}

function removeDnsRecord(record: DnsRecord): void {
  const key = recordKey(normalizeRecord(record))
  dnsDraftRecords.value = dnsDraftRecords.value.filter((item) => recordKey(normalizeRecord(item)) !== key)
}

function addExpertRow(): void {
  dnsFormError.value = ''
  dnsError.value = ''
  dnsInfo.value = ''
  dnsDraftRecords.value.push({ name: '@', type: 'A', data: '' })
}

function validateRecord(record: DnsRecord): string | null {
  if (record.name.trim() === '') {
    return 'Name darf nicht leer sein.'
  }
  if (record.type.trim() === '') {
    return 'Typ darf nicht leer sein.'
  }
  if (record.data.trim() === '') {
    return 'Inhalt darf nicht leer sein.'
  }
  return null
}

function addSimpleRecord(): void {
  dnsFormError.value = ''
  const data = buildDnsAddRecordDataFromConfig()
  const candidate: DnsRecord = {
    type: simpleForm.value.type,
    name: simpleForm.value.name,
    data,
  }
  const error = validateRecord(candidate)
  if (error !== null) {
    dnsFormError.value = error
    return
  }
  mergeRecordIntoDraft(candidate)
  dnsInfo.value = 'Eintrag zum Entwurf hinzugefügt.'
}

function addSimpleRecordAndClose(): void {
  addSimpleRecord()
  if (dnsFormError.value === '') {
    addDnsRecordDialogOpen.value = false
  }
}

function addProRecord(): void {
  dnsFormError.value = ''
  const error = validateRecord(proForm.value)
  if (error !== null) {
    dnsFormError.value = error
    return
  }
  mergeRecordIntoDraft(proForm.value)
  dnsInfo.value = 'Eintrag zum Entwurf hinzugefügt.'
}

function addProRecordAndClose(): void {
  addProRecord()
  if (dnsFormError.value === '') {
    addDnsRecordDialogOpen.value = false
  }
}

function templateValue(template: string, values: Record<string, string>): string {
  return template.replace(/\{\{([^}]+)\}\}/g, (_match, key: string) => values[key.trim()] ?? '')
}

function applyPreset(): void {
  dnsFormError.value = ''
  const preset = selectedPreset.value
  if (preset === null) {
    dnsFormError.value = 'Bitte zuerst ein Preset wählen.'
    return
  }

  const values: Record<string, string> = { ...presetFormValues.value, domain: props.domain.domain }
  for (const field of preset.fields ?? []) {
    if (!(field.key in values) || values[field.key] === '') {
      if (field.default !== undefined) {
        values[field.key] = String(field.default)
      }
    }
  }

  for (const record of preset.records ?? []) {
    const name = record.name
      ?? (record.name_key ? values[record.name_key] : undefined)
      ?? (record.name_template ? templateValue(record.name_template, values) : undefined)
    const data = (record.data_key ? values[record.data_key] : '')
      || (record.data_template ? templateValue(record.data_template, values) : '')
    const candidate: DnsRecord = {
      name: name ?? '@',
      type: record.type ?? 'A',
      data: data ?? '',
    }
    const error = validateRecord(candidate)
    if (error !== null) {
      dnsFormError.value = `Preset unvollständig: ${error}`
      return
    }
    mergeRecordIntoDraft(candidate)
  }

  dnsInfo.value = 'Preset in den Entwurf übernommen.'
}

function applyPresetAndClose(): void {
  applyPreset()
  if (dnsFormError.value === '') {
    addDnsRecordDialogOpen.value = false
  }
}

function applyPresetAndCloseEasyDns(): void {
  applyPreset()
  if (dnsFormError.value === '') {
    easyDnsModalOpen.value = false
  }
}

function openEasyDnsDialog(): void {
  dnsFormError.value = ''
  dnsInfo.value = ''
  presetFormValues.value = {}
  selectedPresetId.value = ''
  easyDnsModalOpen.value = true
}

function openAddDnsRecordDialog(): void {
  dnsFormError.value = ''
  dnsInfo.value = ''
  initDnsAddRecordFieldValues(simpleForm.value.type)
  addDnsRecordDialogOpen.value = true
}

function loadDnsRecords() {
  dnsLoading.value = true
  dnsError.value = ''
  dnsFormError.value = ''
  dnsInfo.value = ''
  fetch(`${baseUrl()}/dns`)
    .then((r) => r.json())
    .then((data) => {
      if (data.error) dnsError.value = data.error
      else {
        dnsRecords.value = (data.records ?? []).map((r: { name: string; type: string; data: string }) => ({ name: r.name, type: r.type, data: r.data }))
        dnsDraftRecords.value = [...dnsRecords.value]
      }
    })
    .catch(() => { dnsError.value = 'Fehler beim Laden' })
    .finally(() => { dnsLoading.value = false })
}

function saveDnsRecords() {
  dnsSaving.value = true
  dnsError.value = ''
  dnsFormError.value = ''
  dnsInfo.value = ''

  for (const record of dnsDraftRecords.value) {
    const error = validateRecord(record)
    if (error !== null) {
      dnsError.value = error
      dnsSaving.value = false
      return
    }
  }

  router.put(
    `${baseUrl()}/dns`,
    { records: dnsDraftRecords.value.map((record) => normalizeRecord(record)) },
    {
      preserveScroll: true,
      onSuccess: () => {
        loadDnsRecords()
        dnsInfo.value = 'DNS-Zone gespeichert.'
      },
      onError: (errors) => {
        const firstError = Object.values(errors ?? {}).find((value) => typeof value === 'string')
        dnsError.value = typeof firstError === 'string' && firstError.trim() !== ''
          ? firstError
          : 'DNS-Zone konnte nicht gespeichert werden.'
      },
      onFinish: () => {
        dnsSaving.value = false
      },
    },
  )
}

const contactForm = useForm({
  company: '', firstname: '', lastname: '', street: '', number: '', postcode: '', city: '', state: '', country: 'DE', email: '', phone: '',
})
const contactError = ref('')
const contactLoadedOnce = ref(false)
function loadContact() {
  if (contactLoadedOnce.value) return
  fetch(`${baseUrl()}/contact`)
    .then((r) => r.json())
    .then((data) => {
      if (data.error) contactError.value = data.error
      else {
        const c = data.contact ?? {}
        contactForm.company = c.company ?? ''
        contactForm.firstname = c.firstname ?? ''
        contactForm.lastname = c.lastname ?? ''
        contactForm.street = c.street ?? ''
        contactForm.number = c.number ?? ''
        contactForm.postcode = c.postcode ?? ''
        contactForm.city = c.city ?? ''
        contactForm.state = c.state ?? ''
        contactForm.country = c.country ?? 'DE'
        contactForm.email = c.email ?? ''
        contactForm.phone = c.phone ?? ''
        contactLoadedOnce.value = true
      }
    })
    .catch(() => { contactError.value = 'Fehler' })
}
function submitContact() {
  router.put(`${baseUrl()}/contact`, {
    contact: {
      company: contactForm.company, firstname: contactForm.firstname, lastname: contactForm.lastname,
      street: contactForm.street, number: contactForm.number, postcode: contactForm.postcode,
      city: contactForm.city, state: contactForm.state, country: contactForm.country,
      email: contactForm.email, phone: contactForm.phone,
    },
  }, { preserveScroll: true })
}

const whoisResult = ref('')
const whoisLoading = ref(false)
const whoisError = ref('')
function loadWhoisLookup() {
  whoisLoading.value = true
  whoisResult.value = ''
  fetch(`${baseUrl()}/whois`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then((r) => r.json())
    .then((data) => {
      if (data.error) whoisResult.value = data.error
      else whoisResult.value = data.whois ?? ''
    })
    .catch(() => { whoisResult.value = 'Whois-Abfrage fehlgeschlagen.' })
    .finally(() => { whoisLoading.value = false })
}

</script>
