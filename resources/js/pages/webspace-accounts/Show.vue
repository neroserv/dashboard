<template>
  <DefaultLayout>
    <Head :title="webspaceAccount?.domain ?? 'Webspace'" />
    <PageBreadcrumb
      :title="webspaceAccount?.domain ?? 'Webspace'"
      subtitle="Meine Webspace-Accounts"
      subtitle-url="/webspace-accounts"
    />

    <BRow>
      <BCol lg="4" class="mb-4">
        <BCard no-body>
          <BCardBody class="text-center">
            <BBadge :variant="statusVariant(webspaceAccount?.status ?? '')" class="mb-2">
              {{ displayStatus(webspaceAccount?.status ?? '') }}
            </BBadge>
            <div class="mb-2 d-flex justify-content-center">
              <Icon icon="server" class="fs-1 text-muted" />
            </div>
            <h5 class="mb-1">Webspace</h5>
            <p class="font-monospace small mb-0 text-break">{{ webspaceAccount?.domain }}</p>
            <p class="text-muted small mb-0">{{ webspaceAccount?.hosting_plan?.name }}</p>
            <div class="border rounded p-2 mt-3 bg-light">
              <span class="text-muted small">Läuft bis</span>
              <div class="fw-semibold">{{ formatDate(webspaceAccount?.current_period_ends_at) }}</div>
            </div>
            <div class="d-flex flex-column gap-2 mt-3">
              <a
                v-if="!isSuspendedOrExpired"
                :href="pleskLoginUrl"
                target="_blank"
                rel="noopener"
                class="btn btn-primary"
              >
                <Icon icon="external-link" class="me-2" />
                {{ panelLoginLabel }}
              </a>
              <a v-else class="btn btn-secondary disabled">Gesperrt – bitte verlängern</a>
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
              <span class="d-inline-flex align-items-center">Übersicht</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'access'" @click="activeTab = 'access'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="key" />
              </span>
              <span class="d-inline-flex align-items-center">Zugang</span>
            </span>
          </BNavItem>
          <BNavItem
            v-if="isKeyhelpPanel"
            link-class="d-inline-flex align-items-center"
            :active="activeTab === 'panel'"
            @click="activeTab = 'panel'"
          >
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="settings" />
              </span>
              <span class="d-inline-flex align-items-center">Panel-Details</span>
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
              <span class="d-inline-flex align-items-center">Teilen</span>
            </span>
          </BNavItem>
        </BNav>

        <BCard v-if="activeTab === 'overview'" no-body>
          <BCardBody>
            <BTable :items="overviewRows" :fields="[{ key: 'label', label: '' }, { key: 'value', label: '' }]" small stacked="">
              <template #cell(label)="{ item }">{{ item.label }}</template>
              <template #cell(value)="{ item }">
                <BBadge v-if="item.key === 'status'" :variant="statusVariant(item.value)">{{ displayStatus(item.value) }}</BBadge>
                <span v-else>{{ item.value }}</span>
              </template>
            </BTable>
            <template v-if="resourceUsage">
              <h6 class="mt-4 mb-2">Genutzte Ressourcen</h6>
              <div class="small">
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Speicherplatz</span>
                    <span>{{ formatDiskUsageLine(resourceUsage.disk_used_bytes, resourceUsage.disk_limit_bytes) }}</span>
                  </div>
                  <div class="progress" style="height: 6px">
                    <div
                      class="progress-bar"
                      :style="{
                        width:
                          resourcePercentDisk(resourceUsage.disk_used_bytes, resourceUsage.disk_limit_bytes) + '%',
                      }"
                    />
                  </div>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Domains</span>
                    <span>{{ formatUsagePair(resourceUsage.domains_used, resourceUsage.domains_limit) }}</span>
                  </div>
                  <div class="progress" style="height: 6px">
                    <div
                      class="progress-bar"
                      :style="{
                        width: resourcePercentCount(resourceUsage.domains_used, resourceUsage.domains_limit) + '%',
                      }"
                    />
                  </div>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span>E-Mail-Postfächer</span>
                    <span>{{ formatUsagePair(resourceUsage.mailboxes_used, resourceUsage.mailboxes_limit) }}</span>
                  </div>
                  <div class="progress" style="height: 6px">
                    <div
                      class="progress-bar"
                      :style="{
                        width:
                          resourcePercentCount(resourceUsage.mailboxes_used, resourceUsage.mailboxes_limit) + '%',
                      }"
                    />
                  </div>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Datenbanken</span>
                    <span>{{ formatUsagePair(resourceUsage.databases_used, resourceUsage.databases_limit) }}</span>
                  </div>
                  <div class="progress" style="height: 6px">
                    <div
                      class="progress-bar"
                      :style="{
                        width:
                          resourcePercentCount(resourceUsage.databases_used, resourceUsage.databases_limit) + '%',
                      }"
                    />
                  </div>
                </div>
                <div
                  v-if="
                    isUnlimitedQuota(resourceUsage.subdomains_limit ?? 0) ||
                    (resourceUsage.subdomains_limit ?? 0) > 0 ||
                    (resourceUsage.subdomains_used ?? 0) > 0
                  "
                  class="mb-2"
                >
                  <div class="d-flex justify-content-between mb-1">
                    <span>Subdomains</span>
                    <span>{{
                      formatUsagePair(resourceUsage.subdomains_used ?? 0, resourceUsage.subdomains_limit ?? 0)
                    }}</span>
                  </div>
                  <div class="progress" style="height: 6px">
                    <div
                      class="progress-bar"
                      :style="{
                        width:
                          resourcePercentCount(
                            resourceUsage.subdomains_used ?? 0,
                            resourceUsage.subdomains_limit ?? 0,
                          ) + '%',
                      }"
                    />
                  </div>
                </div>
                <template v-for="row in keyhelpExtraUsageRows" :key="row.key">
                  <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                      <span>{{ row.label }}</span>
                      <span>{{ row.display }}</span>
                    </div>
                    <div v-if="row.showBar" class="progress" style="height: 6px">
                      <div class="progress-bar" :style="{ width: row.barPercent + '%' }" />
                    </div>
                  </div>
                </template>
              </div>
            </template>
          </BCardBody>
        </BCard>

        <ProductSharingCard
          v-else-if="activeTab === 'sharing' && canManageCollaborators"
          :product-shares="productShares ?? []"
          :product-invitations="productInvitations ?? []"
          :allowed-share-permissions="allowedSharePermissions ?? []"
          :store-invitation-url="storeInvitationUrl ?? ''"
        />

        <BCard v-else-if="activeTab === 'panel' && isKeyhelpPanel" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">
              Übersicht aus KeyHelp (Domains, Zertifikate, E-Mail, Datenbanken, FTP, Cronjobs, Verzeichnisschutz). Passwörter
              und Schlüssel werden nicht angezeigt.
            </p>
            <Deferred data="keyhelpResources">
              <template #fallback>
                <div class="placeholder-glow">
                  <span class="placeholder col-12 mb-2 d-block rounded" style="height: 1rem"></span>
                  <span class="placeholder col-10 mb-2 d-block rounded" style="height: 1rem"></span>
                  <span class="placeholder col-11 d-block rounded" style="height: 1rem"></span>
                </div>
              </template>
              <template #default>
                <div v-if="keyhelpResourcesData">
                  <section v-if="keyhelpResourcesData.domains?.length" class="mb-4">
                    <h6 class="mb-2">Domains</h6>
                    <BTable
                      :items="keyhelpResourcesData.domains"
                      :fields="[
                        { key: 'domain', label: 'Domain' },
                        { key: 'php_version', label: 'PHP' },
                        { key: 'traffic', label: 'Traffic' },
                        { key: 'is_subdomain', label: 'Sub' },
                      ]"
                      small
                      striped
                    >
                      <template #cell(traffic)="{ item }">{{ formatBytes(item.traffic ?? 0) }}</template>
                      <template #cell(is_subdomain)="{ item }">{{ item.is_subdomain ? 'Ja' : 'Nein' }}</template>
                    </BTable>
                  </section>
                  <section v-if="keyhelpResourcesData.certificates?.length" class="mb-4">
                    <h6 class="mb-2">SSL-Zertifikate</h6>
                    <BTable
                      :items="keyhelpResourcesData.certificates"
                      :fields="[
                        { key: 'name', label: 'Name' },
                        { key: 'secured_domains', label: 'Domains' },
                        { key: 'valid_till', label: 'Gültig bis' },
                      ]"
                      small
                      striped
                    >
                      <template #cell(secured_domains)="{ item }">
                        {{ Array.isArray(item.secured_domains) ? item.secured_domains.join(', ') : '–' }}
                      </template>
                    </BTable>
                  </section>
                  <section v-if="keyhelpResourcesData.emails?.length" class="mb-4">
                    <h6 class="mb-2">E-Mail-Konten</h6>
                    <BTable
                      :items="keyhelpResourcesData.emails"
                      :fields="[
                        { key: 'email', label: 'Adresse' },
                        { key: 'size', label: 'Belegt' },
                        { key: 'max_size', label: 'Limit' },
                      ]"
                      small
                      striped
                    >
                      <template #cell(size)="{ item }">{{ formatBytes(item.size ?? 0) }}</template>
                      <template #cell(max_size)="{ item }">{{ formatBytes(item.max_size ?? 0) }}</template>
                    </BTable>
                  </section>
                  <section v-if="keyhelpResourcesData.databases?.length" class="mb-4">
                    <h6 class="mb-2">Datenbanken</h6>
                    <BTable
                      :items="keyhelpResourcesData.databases"
                      :fields="[
                        { key: 'database_name', label: 'Name' },
                        { key: 'database_username', label: 'Benutzer' },
                        { key: 'size', label: 'Größe' },
                      ]"
                      small
                      striped
                    >
                      <template #cell(size)="{ item }">{{ formatBytes(item.size ?? 0) }}</template>
                    </BTable>
                  </section>
                  <section v-if="keyhelpResourcesData.ftp_users?.length" class="mb-4">
                    <h6 class="mb-2">FTP-Benutzer</h6>
                    <BTable
                      :items="keyhelpResourcesData.ftp_users"
                      :fields="[
                        { key: 'username', label: 'Benutzer' },
                        { key: 'home_directory', label: 'Home' },
                      ]"
                      small
                      striped
                    />
                  </section>
                  <section v-if="keyhelpResourcesData.scheduled_tasks?.length" class="mb-4">
                    <h6 class="mb-2">Geplante Aufgaben (Cron)</h6>
                    <BTable
                      :items="keyhelpResourcesData.scheduled_tasks"
                      :fields="[
                        { key: 'type', label: 'Typ' },
                        { key: 'schedule', label: 'Zeitplan' },
                        { key: 'command', label: 'Befehl' },
                      ]"
                      small
                      striped
                    >
                      <template #cell(schedule)="{ item }">{{ formatCronSchedule(item.schedule) }}</template>
                    </BTable>
                  </section>
                  <section v-if="keyhelpResourcesData.directory_protections?.length" class="mb-4">
                    <h6 class="mb-2">Verzeichnisschutz</h6>
                    <BTable
                      :items="keyhelpResourcesData.directory_protections"
                      :fields="[
                        { key: 'path', label: 'Pfad' },
                        { key: 'auth_name', label: 'Bereich' },
                        { key: 'username', label: 'Benutzer' },
                      ]"
                      small
                      striped
                    />
                  </section>
                  <p v-if="!keyhelpResourcesHasAnySection" class="text-muted small mb-0">Keine Einträge gemeldet.</p>
                </div>
                <p v-else class="text-muted small mb-0">Ressourcen konnten nicht geladen werden.</p>
              </template>
            </Deferred>
          </BCardBody>
        </BCard>

        <BCard v-else-if="activeTab === 'access'" no-body>
          <BCardBody>
            <div class="mb-3">
              <label class="form-label small">{{ panelUsernameLabel }}</label>
              <div class="d-flex gap-2">
                <BFormInput :value="webspaceAccount?.plesk_username ?? '–'" readonly class="font-monospace" />
                <BButton v-if="webspaceAccount?.plesk_username" size="sm" variant="outline-secondary" @click="copyToClipboard(webspaceAccount!.plesk_username!)">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="mb-3" v-if="pleskPassword != null">
              <label class="form-label small">Passwort</label>
              <div class="d-flex gap-2">
                <BFormInput :type="showPassword ? 'text' : 'password'" :value="displayPassword" readonly class="font-monospace" />
                <BButton size="sm" variant="outline-secondary" @click="showPassword = !showPassword">
                  <Icon :icon="showPassword ? 'eye-off' : 'eye'" />
                </BButton>
                <BButton size="sm" variant="outline-secondary" @click="copyToClipboard(pleskPassword ?? '')">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <a v-if="webmailUrl" :href="webmailUrl" target="_blank" rel="noopener" class="btn btn-outline-primary">
              <Icon icon="mail" class="me-2" />
              Webmail öffnen
            </a>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

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
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
  BBadge,
  BCard,
  BCardBody,
  BButton,
  BCol,
  BFormInput,
  BFormRadio,
  BFormSelect,
  BNav,
  BNavItem,
  BRow,
  BTable,
  BModal,
} from 'bootstrap-vue-next'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import ProductSharingCard from '@/components/product-sharing/ProductSharingCard.vue'
import AutoRenewModal from '@/components/AutoRenewModal.vue'
import Icon from '@/components/wrappers/Icon.vue'

type WebspaceAccount = {
  id?: number
  uuid?: string
  domain: string
  plesk_username: string
  keyhelp_user_id?: number | null
  status: string
  current_period_ends_at: string | null
  cancel_at_period_end: boolean
  hosting_plan: { name: string; panel_type?: string | null }
}
type KeyhelpExtraMetric = { value: number; max: number }
type ResourceUsage = {
  disk_used_bytes: number
  disk_limit_bytes: number
  domains_used: number
  domains_limit: number
  subdomains_used?: number
  subdomains_limit?: number
  mailboxes_used: number
  mailboxes_limit: number
  databases_used: number
  databases_limit: number
  keyhelp_extras?: Record<string, KeyhelpExtraMetric>
}

type KeyhelpResourcesPayload = {
  domains?: Array<Record<string, unknown>>
  certificates?: Array<Record<string, unknown>>
  emails?: Array<Record<string, unknown>>
  databases?: Array<Record<string, unknown>>
  ftp_users?: Array<Record<string, unknown>>
  scheduled_tasks?: Array<Record<string, unknown>>
  directory_protections?: Array<Record<string, unknown>>
}

const props = withDefaults(
  defineProps<{
    webspaceAccount: WebspaceAccount | null
    pleskPassword: string | null
    webmailUrl: string
    resourceUsage: ResourceUsage | null
    keyhelpResources?: KeyhelpResourcesPayload | null
    renewUrl?: string
    canRenew?: boolean
    renewalAmount?: number
    canPayWithBalance?: boolean
    customerBalance?: number
    auto_renew_with_balance?: boolean
    has_mollie_subscription?: boolean
    isSuspendedOrExpired?: boolean
    connectDomainShowUrl?: string
    canManageCollaborators?: boolean
    productShares?: Array<{ id: number; user: { id: number; name: string; email: string } | null; permissions: string[]; update_url: string; destroy_url: string }>
    productInvitations?: Array<{ id: number; email: string; permissions: string[]; expires_at: string | null; destroy_url: string }>
    allowedSharePermissions?: string[]
    storeInvitationUrl?: string | null
  }>(),
  {
    canRenew: false,
    renewalAmount: 0,
    canPayWithBalance: false,
    customerBalance: 0,
    auto_renew_with_balance: false,
    has_mollie_subscription: false,
    canManageCollaborators: false,
    productShares: () => [],
    productInvitations: () => [],
    allowedSharePermissions: () => [],
    storeInvitationUrl: null,
  },
)

const page = usePage()
const brandFeatures = computed(() => (page.props.brandFeatures as Record<string, boolean> | undefined) ?? {})

const isKeyhelpPanel = computed(
  () =>
    props.webspaceAccount?.hosting_plan?.panel_type === 'keyhelp' &&
    props.webspaceAccount?.keyhelp_user_id != null &&
    props.webspaceAccount.keyhelp_user_id > 0,
)

const keyhelpResourcesData = computed(() => (page.props as { keyhelpResources?: KeyhelpResourcesPayload | null }).keyhelpResources ?? null)

const keyhelpResourcesHasAnySection = computed(() => {
  const d = keyhelpResourcesData.value
  if (!d) return false
  return Boolean(
    (d.domains?.length ?? 0) > 0 ||
      (d.certificates?.length ?? 0) > 0 ||
      (d.emails?.length ?? 0) > 0 ||
      (d.databases?.length ?? 0) > 0 ||
      (d.ftp_users?.length ?? 0) > 0 ||
      (d.scheduled_tasks?.length ?? 0) > 0 ||
      (d.directory_protections?.length ?? 0) > 0,
  )
})

/** Shop-/Import-Platzhalter für „unbegrenzt“ (z. B. KeyHelp-Paket). */
const SHOP_UNLIMITED_QUOTA = 999_999
const INFINITY = '∞'

function isUnlimitedQuota(n: number): boolean {
  return n < 0 || n >= SHOP_UNLIMITED_QUOTA
}

function formatLimitCount(n: number): string {
  if (isUnlimitedQuota(n)) {
    return INFINITY
  }

  return String(n)
}

const KEYHELP_EXTRA_LABELS: Record<string, string> = {
  traffic: 'Traffic (Panel)',
  files: 'Dateien',
  ftp_users: 'FTP-Benutzer',
  scheduled_tasks: 'Geplante Aufgaben',
  email_addresses: 'E-Mail-Adressen',
  email_forwardings: 'E-Mail-Weiterleitungen',
}

const keyhelpExtraUsageRows = computed(() => {
  const extras = resourceUsage.value?.keyhelp_extras
  if (!extras) return []
  const rows: Array<{
    key: string
    label: string
    display: string
    showBar: boolean
    barPercent: number
  }> = []
  for (const [key, m] of Object.entries(extras)) {
    const isBytes = key === 'traffic' || key === 'files'
    const label = KEYHELP_EXTRA_LABELS[key] ?? key
    const max = m.max
    const unlimited = max < 0 || (!isBytes && isUnlimitedQuota(max))
    const display = isBytes
      ? `${formatBytes(m.value)} / ${unlimited ? INFINITY : max > 0 ? formatBytes(max) : '–'}`
      : `${m.value} / ${unlimited ? INFINITY : max > 0 ? formatLimitCount(max) : '–'}`
    const limitForBar = unlimited || max <= 0 ? 0 : max
    const showBar = !unlimited && limitForBar > 0
    rows.push({
      key,
      label,
      display,
      showBar,
      barPercent: showBar ? resourcePercent(m.value, limitForBar) : 0,
    })
  }
  return rows
})

const showAboVerwalten = computed(
  () => !brandFeatures.value?.prepaid_balance && !props.canRenew,
)
const showRenewButton = computed(
  () => !!props.renewUrl && (props.canRenew || brandFeatures.value?.prepaid_balance === true),
)
const showAutoRenewButton = computed(
  () => showRenewButton.value && brandFeatures.value?.prepaid_balance === true,
)

const renewalAmount = computed(() => props.renewalAmount ?? 0)
const canPayWithBalance = computed(() => props.canPayWithBalance ?? false)
const customerBalance = computed(() => props.customerBalance ?? 0)
const autoRenewWithBalance = computed(() => props.auto_renew_with_balance ?? false)
const hasMollieSubscription = computed(() => props.has_mollie_subscription ?? false)

const autoRenewBalanceUrl = computed(
  () => `/webspace-accounts/${props.webspaceAccount?.uuid ?? ''}/auto-renew-balance`,
)
const autoRenewMollieUrl = computed(
  () => `/webspace-accounts/${props.webspaceAccount?.uuid ?? ''}/auto-renew-mollie-subscription`,
)

const activeTab = ref('overview')
const showPassword = ref(false)
const renewModalOpen = ref(false)
const autoRenewModalOpen = ref(false)
const renewPeriodMonths = ref(1)
const paymentMethod = ref<'balance' | 'mollie'>('mollie')

const periodMonthOptions = [
  { value: 1, text: '1 Monat' },
  { value: 2, text: '2 Monate' },
  { value: 3, text: '3 Monate' },
]

const totalRenewAmount = computed(
  () => Math.round(renewalAmount.value * renewPeriodMonths.value * 100) / 100,
)

const isSuspendedOrExpired = computed(() => props.isSuspendedOrExpired ?? false)
const pleskLoginUrl = computed(() => {
  const acc = props.webspaceAccount
  if (!acc?.uuid) return '#'
  return `/webspace-accounts/${acc.uuid}/plesk-login`
})

const panelLoginLabel = computed(() => {
  const t = props.webspaceAccount?.hosting_plan?.panel_type ?? 'plesk'
  return t === 'keyhelp' ? 'Zum KeyHelp-Panel' : 'Zum Plesk-Panel'
})

const panelUsernameLabel = computed(() => {
  const t = props.webspaceAccount?.hosting_plan?.panel_type ?? 'plesk'
  return t === 'keyhelp' ? 'Panel-Nutzername' : 'Plesk-Nutzername'
})

const displayPassword = computed(() =>
  props.pleskPassword
    ? showPassword.value
      ? props.pleskPassword
      : '••••••••••••••••••••'
    : '—',
)

const overviewRows = computed(() => {
  const acc = props.webspaceAccount
  if (!acc) return []
  return [
    { key: 'domain', label: 'Domain', value: acc.domain },
    { key: 'plesk_username', label: panelUsernameLabel.value, value: acc.plesk_username },
    { key: 'status', label: 'Status', value: acc.status },
    { key: 'plan', label: 'Paket', value: acc.hosting_plan?.name ?? '–' },
  ]
})

const resourceUsage = computed(() => props.resourceUsage)

function formatDate(d: string | null | undefined): string {
  return d ? new Date(d).toLocaleDateString('de-DE', { timeZone: 'UTC' }) : '–'
}

function displayStatus(status: string): string {
  const s = status?.toLowerCase() ?? ''
  if (s === 'active' || s === 'aktiv') return 'Aktiv'
  if (s === 'pending' || s === 'ausstehend') return 'Ausstehend'
  if (s === 'suspended' || s === 'gesperrt') return 'Gesperrt'
  if (s === 'cancelled') return 'Gekündigt'
  return status || '–'
}

function statusVariant(status: string): 'success' | 'secondary' | 'danger' {
  const s = status?.toLowerCase() ?? ''
  if (s === 'active' || s === 'aktiv') return 'success'
  if (s === 'suspended' || s === 'gesperrt' || s === 'cancelled') return 'danger'
  return 'secondary'
}

function formatBytesToGb(bytes: number): string {
  if (bytes === 0) return '0,00'
  const gb = bytes / (1024 * 1024 * 1024)
  return gb.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let v = Math.abs(bytes)
  let u = 0
  while (v >= 1024 && u < units.length - 1) {
    v /= 1024
    u++
  }
  const n = bytes < 0 ? -v : v
  return `${n.toLocaleString('de-DE', { maximumFractionDigits: u === 0 ? 0 : 2 })} ${units[u]}`
}

function formatCronSchedule(s: unknown): string {
  if (!s || typeof s !== 'object') return '–'
  const o = s as Record<string, string>
  return [o.minute, o.hour, o.day_of_month, o.month, o.day_of_week].map((x) => x ?? '*').join(' ')
}

function resourcePercent(used: number, limit: number): number {
  if (limit <= 0) return 0
  return Math.min(100, Math.round((used / limit) * 100))
}

function isUnlimitedDiskBytes(limitBytes: number): boolean {
  if (limitBytes < 0) {
    return true
  }
  const gb = limitBytes / (1024 * 1024 * 1024)

  return gb >= SHOP_UNLIMITED_QUOTA
}

function formatDiskUsageLine(usedBytes: number, limitBytes: number): string {
  const usedStr = formatBytesToGb(usedBytes)
  if (isUnlimitedDiskBytes(limitBytes)) {
    return `${usedStr} / ${INFINITY} GB`
  }

  return `${usedStr} / ${formatBytesToGb(limitBytes)} GB`
}

function formatUsagePair(used: number, limit: number): string {
  return `${used} / ${formatLimitCount(limit)}`
}

function resourcePercentDisk(usedBytes: number, limitBytes: number): number {
  if (isUnlimitedDiskBytes(limitBytes)) {
    return 0
  }

  return resourcePercent(usedBytes, limitBytes)
}

function resourcePercentCount(used: number, limit: number): number {
  if (isUnlimitedQuota(limit)) {
    return 0
  }

  return resourcePercent(used, limit)
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text)
}

function submitRenew() {
  if (!props.renewUrl) return
  router.post(
    props.renewUrl,
    {
      payment_method: canPayWithBalance.value ? paymentMethod.value : 'mollie',
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
</script>
