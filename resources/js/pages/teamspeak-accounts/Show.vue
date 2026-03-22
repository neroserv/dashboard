<template>
  <DefaultLayout>
    <Head :title="teamSpeakServerAccount?.name ?? 'TeamSpeak Server'" />
    <PageBreadcrumb
      :title="teamSpeakServerAccount?.name ?? 'TeamSpeak Server'"
      subtitle="Meine TeamSpeak Server"
      subtitle-url="/teamspeak-accounts"
    />

    <BRow>
      <BCol lg="4" class="mb-4">
        <BCard no-body>
          <BCardBody class="text-center">
            <BBadge :variant="statusVariant" class="mb-2">{{ displayStatus }}</BBadge>
            <div class="mb-2 d-flex justify-content-center">
              <Icon icon="headphones" class="fs-1 text-muted" />
            </div>
            <h5 class="mb-1">TeamSpeak-Server</h5>
            <p class="text-muted small mb-0">{{ teamSpeakServerAccount?.name }}</p>
            <p class="text-muted small mb-0">{{ teamSpeakServerAccount?.hosting_plan?.name }}</p>
            <div class="border rounded p-2 mt-3 bg-light">
              <span class="text-muted small">Läuft bis</span>
              <div class="fw-semibold">{{ formatDate(teamSpeakServerAccount?.current_period_ends_at) }}</div>
            </div>
            <div class="d-flex flex-column gap-2 mt-3">
              <a
                v-if="mergedDisplay?.connection_uri && !isSuspendedOrExpired"
                :href="mergedDisplay.connection_uri"
                target="_blank"
                rel="noopener"
                class="btn btn-primary"
              >
                <Icon icon="external-link" class="me-2" />
                Zum Server verbinden
              </a>
              <BButton
                v-if="showRenewButton && !isSuspendedOrExpired"
                variant="outline-primary"
                class="text-start"
                @click="renewModalOpen = true"
              >
                <Icon icon="calendar-plus" class="me-2" />
                Verlängern
              </BButton>
              <BButton
                v-if="showAutoRenewButton && !isSuspendedOrExpired"
                variant="outline-secondary"
                class="text-start"
                @click="autoRenewModalOpen = true"
              >
                <Icon icon="refresh-ccw" class="me-2" />
                Auto Renew
              </BButton>
              <Link v-if="showAboVerwalten" href="/billing/subscriptions" class="btn btn-outline-secondary">Abo verwalten</Link>
              <Link v-if="connectDomainShowUrl && !isSuspendedOrExpired" :href="connectDomainShowUrl" class="btn btn-outline-secondary">
                <Icon icon="world" class="me-2" />
                Domain verbinden
              </Link>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="8">
        <BNav tabs class="mb-3 flex-wrap">
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'overview'" @click="activeTab = 'overview'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="layout-dashboard" />
              </span>
              <span>Übersicht</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'access'" @click="activeTab = 'access'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="router" />
              </span>
              <span>Zugang</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'tokens'" @click="activeTab = 'tokens'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="key" />
              </span>
              <span>Tokens</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'backups'" @click="activeTab = 'backups'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="archive" />
              </span>
              <span>Backups</span>
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

        <BCard v-if="activeTab === 'overview'" no-body>
          <BCardBody>
            <BTable :items="overviewRows" :fields="[{ key: 'label', label: '' }, { key: 'value', label: '' }]" small stacked="">
              <template #cell(value)="{ item }">
                <span v-if="item.key === 'address'" class="d-flex align-items-center gap-1">
                  <code class="small">{{ item.value }}</code>
                  <BButton
                    v-if="item.value && item.value !== '–'"
                    size="sm"
                    variant="outline-secondary"
                    class="py-0 px-1"
                    @click="copyToClipboard(mergedDisplay?.connection_uri ?? '')"
                  >
                    <Icon icon="copy" />
                  </BButton>
                </span>
                <span v-else>{{ item.value }}</span>
              </template>
            </BTable>
            <div v-if="!isSuspendedOrExpired" class="d-flex flex-wrap gap-2 mt-3">
              <BButton size="sm" variant="success" :disabled="isOnline" @click="sendPower('start')">
                <Icon icon="power" class="me-1" />
                Start
              </BButton>
              <BButton size="sm" variant="danger" :disabled="!isOnline" @click="sendPower('stop')">
                <Icon icon="power-off" class="me-1" />
                Stop
              </BButton>
            </div>
          </BCardBody>
        </BCard>

        <BCard v-else-if="activeTab === 'access'" no-body>
          <BCardBody>
            <div class="mb-3">
              <label class="form-label small">Adresse (IP:Port)</label>
              <div class="d-flex gap-2">
                <BFormInput :value="mergedDisplay?.address ?? '–'" readonly class="font-monospace" />
                <BButton v-if="mergedDisplay?.address" size="sm" variant="outline-secondary" @click="copyToClipboard(mergedDisplay!.address!)">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="mb-0">
              <label class="form-label small">Verbindung (ts3server://)</label>
              <div class="d-flex gap-2">
                <BFormInput :value="mergedDisplay?.connection_uri ?? '–'" readonly class="font-monospace" />
                <BButton
                  v-if="mergedDisplay?.connection_uri"
                  size="sm"
                  variant="outline-secondary"
                  @click="copyToClipboard(mergedDisplay!.connection_uri!)"
                >
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
          </BCardBody>
        </BCard>

        <BCard v-else-if="activeTab === 'tokens'" no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Privilege Keys (Tokens)</h6>
            <BButton v-if="!isSuspendedOrExpired" size="sm" @click="tokenModalOpen = true">
              <Icon icon="key" class="me-1" />
              Neuen Token erstellen
            </BButton>
          </BCardHeader>
          <BCardBody>
            <BTable v-if="tokens.length" :items="tokens" :fields="tokenFields" small />
            <p v-else class="text-muted small mb-0">Keine Tokens</p>
          </BCardBody>
        </BCard>

        <ProductSharingCard
          v-else-if="activeTab === 'sharing' && canManageCollaborators"
          :product-shares="productShares ?? []"
          :product-invitations="productInvitations ?? []"
          :allowed-share-permissions="allowedSharePermissions ?? []"
          :store-invitation-url="storeInvitationUrl ?? ''"
        />

        <BCard v-else-if="activeTab === 'backups'" no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Backups</h6>
            <BButton v-if="!isSuspendedOrExpired" size="sm" @click="createBackup">
              <Icon icon="save" class="me-1" />
              Neues Backup
            </BButton>
          </BCardHeader>
          <BCardBody>
            <BTable v-if="snapshots.length" :items="snapshots" :fields="snapshotFields" small>
              <template #cell(created_at)="{ item }">
                {{ formatSnapshotDate(item.created_at) }}
              </template>
              <template #cell(actions)="{ item }">
                <BButton v-if="!isSuspendedOrExpired" size="sm" variant="outline-primary" class="me-1" @click="deployBackup(item.id)">Wiederherstellen</BButton>
                <BButton v-if="!isSuspendedOrExpired" size="sm" variant="outline-danger" @click="deleteBackup(item.id)">Löschen</BButton>
              </template>
            </BTable>
            <p v-else class="text-muted small mb-0">Keine Backups vorhanden</p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BModal v-model="tokenModalOpen" title="Neuen Token erstellen" no-footer>
      <div class="mb-3">
        <label class="form-label">Beschreibung (optional)</label>
        <BFormInput v-model="newTokenDescription" placeholder="z. B. für Bot" />
      </div>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="tokenModalOpen = false">Abbrechen</BButton>
        <BButton @click="createToken">Erstellen</BButton>
      </div>
    </BModal>

    <BModal v-model="renewModalOpen" title="Verlängern" no-footer>
      <p class="text-muted small">
        Verlängerung für <strong>{{ renewalAmount.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }} €</strong> pro Monat
        <template v-if="renewPeriodMonths > 1"> ({{ totalRenewAmount.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }} € gesamt)</template>.
      </p>
      <div class="mb-3">
        <label class="form-label">Laufzeit (Monate)</label>
        <BFormSelect v-model.number="renewPeriodMonths" :options="periodMonthOptions" />
      </div>
      <div v-if="canPayWithBalance" class="mb-3">
        <label class="form-label">Zahlungsart</label>
        <div class="d-flex flex-column gap-2">
          <BFormRadio v-model="paymentMethod" value="mollie">Mollie (Karte, PayPal, …)</BFormRadio>
          <BFormRadio v-model="paymentMethod" value="balance" :disabled="customerBalance < totalRenewAmount">
            Guthaben ({{ customerBalance.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }} € verfügbar)
          </BFormRadio>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="renewModalOpen = false">Abbrechen</BButton>
        <BButton
          :disabled="paymentMethod === 'balance' && customerBalance < totalRenewAmount"
          @click="submitRenew"
        >
          Verlängern
        </BButton>
      </div>
    </BModal>

    <AutoRenewModal
      v-if="showAutoRenewButton"
      :open="autoRenewModalOpen"
      :balance-url="autoRenewBalanceUrl"
      :mollie-url="autoRenewMollieUrl"
      :auto-renew-with-balance="autoRenewWithBalance"
      :has-mollie-subscription="hasMollieSubscription"
      @update:open="autoRenewModalOpen = $event"
    />
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import {
  BButton,
  BCard,
  BCardBody,
  BCardHeader,
  BCol,
  BFormInput,
  BFormRadio,
  BFormSelect,
  BNav,
  BNavItem,
  BRow,
  BTable,
  BModal,
  BBadge,
} from 'bootstrap-vue-next'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import ProductSharingCard from '@/components/product-sharing/ProductSharingCard.vue'
import AutoRenewModal from '@/components/AutoRenewModal.vue'
import Icon from '@/components/wrappers/Icon.vue'

type TeamSpeakServerAccount = {
  uuid: string
  name: string
  status: string
  port: number | null
  current_period_ends_at: string | null
  cancel_at_period_end: boolean
  hosting_plan: { name: string; price: string }
  hosting_server?: { name: string } | null
}
type ServerInfo = {
  address?: string
  connection_uri?: string
  virtualserver_uptime?: number
  virtualserver_clientsonline?: number
  virtualserver_maxclients?: number
  virtualserver_version?: string
  virtualserver_status?: string
}
type ConnectionPreview = { address: string; connection_uri: string }
type TokenRow = { token: string; group: string; description: string }
type SnapshotRow = { id: number; created_at: string }

const props = withDefaults(
  defineProps<{
    teamSpeakServerAccount: TeamSpeakServerAccount | null
    serverInfo: ServerInfo | null
    connection_preview?: ConnectionPreview | null
    tokens: TokenRow[]
    snapshots: SnapshotRow[]
    renewUrl?: string
    canRenew?: boolean
    renewalAmount?: number
    canPayWithBalance?: boolean
    customerBalance?: number
    autoRenewWithBalance?: boolean
    hasMollieSubscription?: boolean
    isSuspendedOrExpired?: boolean
    connectDomainShowUrl?: string
    canManageCollaborators?: boolean
    productShares?: Array<{ id: number; user: { id: number; name: string; email: string } | null; permissions: string[]; update_url: string; destroy_url: string }>
    productInvitations?: Array<{ id: number; email: string; permissions: string[]; expires_at: string | null; destroy_url: string }>
    allowedSharePermissions?: string[]
    storeInvitationUrl?: string | null
  }>(),
  {
    connection_preview: null,
    canRenew: false,
    renewalAmount: 0,
    canPayWithBalance: false,
    customerBalance: 0,
    autoRenewWithBalance: false,
    hasMollieSubscription: false,
    canManageCollaborators: false,
    productShares: () => [],
    productInvitations: () => [],
    allowedSharePermissions: () => [],
    storeInvitationUrl: null,
  },
)

const page = usePage()
const brandFeatures = computed(() => (page.props.brandFeatures as Record<string, boolean> | undefined) ?? {})

const showAboVerwalten = computed(
  () => !brandFeatures.value?.prepaid_balance && !props.canRenew,
)
const showRenewButton = computed(
  () => !!props.renewUrl && (props.canRenew || brandFeatures.value?.prepaid_balance === true),
)
const showAutoRenewButton = computed(
  () => showRenewButton.value && brandFeatures.value?.prepaid_balance === true,
)

const activeTab = ref('overview')
const tokenModalOpen = ref(false)
const renewModalOpen = ref(false)
const autoRenewModalOpen = ref(false)
const newTokenDescription = ref('')
const renewPeriodMonths = ref(1)
const paymentMethod = ref<'balance' | 'mollie'>('mollie')

const periodMonthOptions = Array.from({ length: 12 }, (_, i) => ({
  value: i + 1,
  text: `${i + 1} Monat${i > 0 ? 'e' : ''}`,
}))

const totalRenewAmount = computed(() =>
  Math.round(props.renewalAmount * renewPeriodMonths.value * 100) / 100,
)

const liveServerInfo = ref<ServerInfo | null>(props.serverInfo)

watch(
  () => props.serverInfo,
  (v) => {
    if (v) {
      liveServerInfo.value = v
    }
  },
  { immediate: true },
)

const mergedDisplay = computed((): ServerInfo | null => {
  const live = liveServerInfo.value
  const initial = props.serverInfo
  const prev = props.connection_preview
  const base = live ?? initial
  if (base && (base.address || base.connection_uri)) {
    return base
  }
  if (base && prev) {
    return { ...prev, ...base }
  }
  if (prev) {
    return {
      ...prev,
      virtualserver_status: base?.virtualserver_status ?? props.teamSpeakServerAccount?.status,
    }
  }
  return base
})

const isOnline = computed(() => (mergedDisplay.value?.virtualserver_status ?? '') === 'online')
const isSuspendedOrExpired = computed(() => props.isSuspendedOrExpired ?? false)

const autoRenewBalanceUrl = computed(
  () => `/teamspeak-accounts/${props.teamSpeakServerAccount?.uuid ?? ''}/auto-renew-balance`,
)
const autoRenewMollieUrl = computed(
  () => `/teamspeak-accounts/${props.teamSpeakServerAccount?.uuid ?? ''}/auto-renew-mollie-subscription`,
)

const displayStatus = computed(() => {
  if (isSuspendedOrExpired.value) return 'Gesperrt'
  const s = mergedDisplay.value?.virtualserver_status ?? props.teamSpeakServerAccount?.status ?? ''
  if (s === 'online') return 'Online'
  if (s === 'offline' || s === 'stopped') return 'Offline'
  return s || '–'
})

const statusVariant = computed((): 'success' | 'secondary' | 'danger' => {
  if (isSuspendedOrExpired.value) return 'danger'
  const s = mergedDisplay.value?.virtualserver_status ?? ''
  if (s === 'online') return 'success'
  return 'secondary'
})

const overviewRows = computed(() => {
  const d = mergedDisplay.value
  const acc = props.teamSpeakServerAccount
  const uptime = d?.virtualserver_uptime != null ? formatUptime(d.virtualserver_uptime) : '–'
  return [
    { key: 'address', label: 'Adresse', value: d?.address ?? '–' },
    { key: 'uptime', label: 'Online-Zeit', value: uptime },
    {
      key: 'clients',
      label: 'User online',
      value: d != null && (d.virtualserver_clientsonline != null || d.virtualserver_maxclients != null)
        ? `${d.virtualserver_clientsonline ?? 0} / ${d.virtualserver_maxclients ?? 0}`
        : '–',
    },
    { key: 'version', label: 'Version', value: d?.virtualserver_version ?? '–' },
    { key: 'node', label: 'Node', value: acc?.hosting_server?.name ?? '–' },
  ]
})

const tokenFields = [
  { key: 'token', label: 'Token' },
  { key: 'group', label: 'Gruppe' },
  { key: 'description', label: 'Beschreibung' },
]
const snapshotFields = [
  { key: 'created_at', label: 'Erstellt am' },
  { key: 'actions', label: 'Aktionen' },
]

function formatDate(d: string | null | undefined): string {
  return d ? new Date(d).toLocaleDateString('de-DE', { timeZone: 'UTC' }) : '–'
}

function formatSnapshotDate(iso: string): string {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatUptime(seconds: number): string {
  if (seconds < 0) return '–'
  const d = Math.floor(seconds / 86400)
  const h = Math.floor((seconds % 86400) / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const parts = []
  if (d > 0) parts.push(`${d} Tag(e)`)
  parts.push(`${h} Std`, `${m} Min`)
  return parts.join(', ')
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text)
}

function fetchOverview() {
  if (!props.teamSpeakServerAccount || isSuspendedOrExpired.value) return
  fetch(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/overview`, {
    method: 'GET',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then((res) => res.json())
    .then((data: { serverInfo?: ServerInfo | null; connection_preview?: ConnectionPreview | null }) => {
      if (data.serverInfo) {
        liveServerInfo.value = data.serverInfo
      } else if (data.connection_preview) {
        liveServerInfo.value = {
          ...data.connection_preview,
          virtualserver_status: liveServerInfo.value?.virtualserver_status ?? props.teamSpeakServerAccount?.status,
        }
      }
    })
    .catch(() => {})
}

let pollInterval: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  fetchOverview()
  pollInterval = setInterval(fetchOverview, 10000)
})
onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})

function sendPower(action: 'start' | 'stop') {
  if (!props.teamSpeakServerAccount) return
  const cur = liveServerInfo.value ?? mergedDisplay.value
  if (action === 'stop') {
    liveServerInfo.value = cur ? { ...cur, virtualserver_status: 'offline' } : { virtualserver_status: 'offline' }
  } else {
    liveServerInfo.value = cur ? { ...cur, virtualserver_status: 'online' } : { virtualserver_status: 'online' }
  }
  router.post(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/power`, { action }, {
    preserveScroll: true,
    onSuccess: () => { fetchOverview() },
    onError: () => {
      liveServerInfo.value = props.serverInfo
        ?? (props.connection_preview
          ? { ...props.connection_preview, virtualserver_status: props.teamSpeakServerAccount?.status }
          : null)
    },
  })
}

function submitRenew() {
  if (!props.renewUrl) return
  router.post(
    props.renewUrl,
    {
      payment_method: props.canPayWithBalance ? paymentMethod.value : 'mollie',
      period_months: renewPeriodMonths.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        renewModalOpen.value = false
      },
    },
  )
}

function createToken() {
  if (!props.teamSpeakServerAccount) return
  router.post(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/tokens`, { description: newTokenDescription.value }, {
    preserveScroll: true,
    onSuccess: () => { tokenModalOpen.value = false; newTokenDescription.value = '' },
  })
}

function createBackup() {
  if (!props.teamSpeakServerAccount) return
  router.post(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/backups`, {}, { preserveScroll: true })
}

function deployBackup(snapshotId: number) {
  if (!confirm('Backup wiederherstellen? Der aktuelle Serverzustand wird überschrieben.') || !props.teamSpeakServerAccount) return
  router.post(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/backups/${snapshotId}/deploy`, {}, { preserveScroll: true })
}

function deleteBackup(snapshotId: number) {
  if (!confirm('Backup wirklich löschen?') || !props.teamSpeakServerAccount) return
  router.delete(`/teamspeak-accounts/${props.teamSpeakServerAccount.uuid}/backups/${snapshotId}`, { preserveScroll: true })
}
</script>
