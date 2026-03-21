<template>
  <div id="user-dropdown-detailed" class="topbar-item nav-user">
    <BDropdown
      v-model="isOpen"
      teleport-disabled
      :variant="null"
      no-caret
      toggle-class="topbar-link topbar-user-dropdown-toggle dropdown-toggle-no-caret"
      menu-class="topbar-user-dropdown-menu dropdown-menu-end border-0 shadow py-2"
      placement="bottom-end"
      offset="6"
    >
      <template #button-content>
        <UserAvatarOrInitials
          class="topbar-user-avatar-wrap"
          :name="displayName"
          :src="user?.avatar ?? null"
          :size="32"
          rounded-class="rounded-circle"
        />
        <div class="topbar-user-meta d-none d-lg-flex align-items-center gap-2 min-w-0">
          <div class="d-flex flex-column align-items-start text-start min-w-0">
            <span class="topbar-user-name text-truncate w-100">{{ displayName }}</span>
            <span
              v-if="isAdminDomain && groupLabelsWithColors.length > 0"
              class="topbar-user-subline d-flex flex-wrap gap-1 mt-1"
            >
              <span
                v-for="g in groupLabelsWithColors"
                :key="g.label"
                class="badge rounded-pill"
                :class="g.color ? '' : 'bg-primary-subtle text-primary'"
                :style="g.color ? { backgroundColor: `${g.color}22`, color: g.color } : undefined"
              >
                {{ g.label }}
              </span>
            </span>
            <span
              v-else-if="showBalance"
              class="topbar-user-subline topbar-user-balance text-muted d-inline-flex align-items-center gap-1 text-truncate w-100"
            >
              <Icon icon="wallet" class="topbar-user-balance-icon flex-shrink-0" aria-hidden="true" />
              <span class="text-truncate">{{ formatCurrency(customerBalance) }}&nbsp;€</span>
            </span>
            <span v-else-if="isAdminDomain" class="topbar-user-subline text-muted">
              Administration
            </span>
          </div>
          <Icon icon="chevron-down" class="topbar-user-chevron flex-shrink-0 opacity-75" aria-hidden="true" />
        </div>
      </template>
      <template v-if="isOpen">
        <BDropdownHeader class="topbar-user-dropdown-header noti-title border-0 py-2 px-3 mb-0 small fw-semibold">
          {{ welcomeMessage }}
        </BDropdownHeader>

        <template v-if="isAdminDomain">
          <Link :href="dashboardUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="layout-dashboard" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Zum Kundenbereich</span>
          </Link>
          <Link :href="profileEditUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="settings-2" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Einstellungen</span>
          </Link>
        </template>
        <template v-else>
          <Link :href="profileEditUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="settings-2" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Einstellungen</span>
          </Link>
          <Link :href="billingIndexUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="wallet" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Guthaben aufladen</span>
          </Link>
          <Link :href="supportCreateUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="file-text" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Support-Ticket erstellen</span>
          </Link>
          <Link :href="postfachIndexUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="mail" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Postfach</span>
          </Link>
          <Link :href="billingRedeemUrl" class="dropdown-item topbar-user-menu-item d-flex align-items-center gap-2">
            <Icon icon="gift" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Gutschein einlösen</span>
          </Link>
        </template>

        <BDropdownDivider class="my-1" />
        <div class="dropdown-item-text px-3 py-1">
          <button
            type="button"
            class="btn btn-link p-0 text-danger fw-semibold text-decoration-none d-flex align-items-center gap-2 w-100 text-start"
            @click="onLogout"
          >
            <Icon icon="logout" class="fs-lg flex-shrink-0" aria-hidden="true" />
            <span>Abmelden</span>
          </button>
        </div>
      </template>
    </BDropdown>
  </div>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { BDropdown, BDropdownHeader, BDropdownDivider } from 'bootstrap-vue-next'
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue'
import Icon from '@/components/wrappers/Icon.vue'
import { topbarDropdownKey } from '@/composables/useTopbarDropdown'

type GroupLabelWithColor = { label: string; color: string | null }

const page = usePage()
const topbarDropdown = inject(topbarDropdownKey)
const DROPDOWN_ID = 'user'
const isOpen = computed({
  get: () => topbarDropdown?.openDropdownId.value === DROPDOWN_ID,
  set: (v: boolean) => topbarDropdown?.setOpen(v ? DROPDOWN_ID : null),
})

const auth = computed(() => page.props.auth as { user?: { name: string; email?: string; avatar?: string | null }; customerBalance?: number; group_labels?: string[]; group_labels_with_colors?: GroupLabelWithColor[] } | undefined)
const brandFeatures = computed(() => page.props.brandFeatures as Record<string, boolean> | undefined)
const isAdminDomain = computed(() => (page.props.isAdminDomain as boolean) === true)

const user = computed(() => auth.value?.user)
const displayName = computed(() => user.value?.name?.trim() || 'Benutzer')
const welcomeMessage = computed(() => {
  const name = user.value?.name?.trim()
  if (!name) {
    return 'Willkommen zurück! 👋'
  }
  const first = name.split(/\s+/)[0] ?? name
  return `Willkommen zurück, ${first}! 👋`
})
const customerBalance = computed(() => auth.value?.customerBalance ?? 0)
const showBalance = computed(
  () =>
    (brandFeatures.value?.prepaid_balance === true) &&
    typeof auth.value?.customerBalance === 'number',
)
const groupLabelsWithColors = computed((): GroupLabelWithColor[] => {
  const withColors = auth.value?.group_labels_with_colors
  if (withColors?.length) return withColors
  const labels = auth.value?.group_labels ?? []
  return labels.map((label: string) => ({ label, color: null }))
})

const profileEditUrl = '/settings/profile'
const billingIndexUrl = '/billing'
const billingRedeemUrl = '/billing/redeem-voucher'
const supportCreateUrl = '/support/create'
const postfachIndexUrl = '/account/postfach'
const dashboardUrl = '/dashboard'

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

function onLogout(): void {
  router.post('/logout')
}
</script>
