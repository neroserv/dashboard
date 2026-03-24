<template>
  <DefaultLayout>
    <Head title="Domain suchen" />
    <PageBreadcrumb title="Domain suchen" subtitle="Domains" subtitle-url="/domains" />

    <div class="mb-4">
      <h4 class="mb-1">Domain suchen</h4>
      <p class="text-muted mb-0">
        Prüfen Sie die Verfügbarkeit und bestellen Sie Ihre Wunschdomain
      </p>
    </div>

    <BCard no-body class="mb-4 position-relative overflow-hidden">
      <BCardHeader style="padding-top: var(--theme-card-spacer-y); padding-left: var(--theme-card-spacer-x);">
        <h5 class="mb-0">Verfügbarkeit prüfen</h5>
        <p class="text-muted small mb-0">
          Geben Sie einen Namen ein (z.&nbsp;B. <span class="font-monospace">beispiel</span>) oder inklusive Endung
          (z.&nbsp;B. <span class="font-monospace">beispiel.dev</span>). Die angegebene Endung wird immer mitgeprüft.
          Ohne Endung werden zusätzlich .de, .com, .net und .io geprüft.
        </p>
      </BCardHeader>
      <BCardBody>
        <div class="d-flex flex-wrap gap-3 align-items-end">
          <div class="flex-grow-1" style="min-width: 200px;">
            <label class="form-label" for="domain">Domain-Name</label>
            <BFormInput
              id="domain"
              v-model="domainInput"
              placeholder="beispiel oder beispiel.de"
              autocomplete="off"
              :state="inputState"
              @keyup.enter="search"
            />
            <div v-if="parseMessage" class="invalid-feedback d-block">
              {{ parseMessage }}
            </div>
          </div>
          <BButton :disabled="!canSearch || loading" @click="search">
            <BSpinner v-if="loading" small class="me-2" />
            <Icon v-else icon="search" class="me-2" />
            Prüfen
          </BButton>
        </div>

        <motion.div
          v-if="loading"
          class="mt-4 rounded-3 border border-secondary border-opacity-25 bg-body-secondary bg-opacity-25 p-4"
          :initial="{ opacity: 0.6 }"
          :animate="{ opacity: [0.6, 1, 0.6] }"
          :transition="{ duration: 1.2, repeat: Infinity, ease: 'easeInOut' }"
        >
          <div class="d-flex align-items-center gap-3">
            <BSpinner variant="primary" />
            <div>
              <div class="fw-medium">Verfügbarkeit wird geprüft …</div>
              <div class="text-muted small">
                {{ loadingHint }}
              </div>
            </div>
          </div>
        </motion.div>
      </BCardBody>
    </BCard>

    <AnimatePresence>
      <motion.div
        v-if="highlightCard"
        key="highlight"
        :initial="{ opacity: 0, y: 16, scale: 0.98 }"
        :animate="{ opacity: 1, y: 0, scale: 1 }"
        :transition="{ type: 'spring', stiffness: 380, damping: 28 }"
        class="mb-4"
      >
        <BCard class="border-success shadow-sm">
          <div class="row card-body p-4">
            <div class="col-md-12 col-sm-10">
              <h3 class="alert-heading mb-2">
                Die Domain <b id="domain-highlight">{{ highlightCard.domain }}</b> ist noch verfügbar
              </h3>
              <p class="mb-0 text-muted">
                Registrierung: {{ formatEuro(highlightCard.sale_price) }} € • Verlängerung:
                {{ formatEuro(highlightCard.renew_sale_price ?? 0) }} €
              </p>
            </div>
            <div class="col-12 mt-3">
              <BButton
                variant="success"
                class="w-100 d-inline-flex align-items-center justify-content-center gap-2"
                @click="goToCheckout(highlightCard)"
              >
                <Icon icon="rocket" class="flex-shrink-0" />
                Jetzt bestellen
              </BButton>
            </div>
          </div>
        </BCard>
      </motion.div>
    </AnimatePresence>

    <AnimatePresence>
      <motion.div
        v-if="highlightTransferBanner"
        key="highlight-transfer"
        :initial="{ opacity: 0, y: 16, scale: 0.98 }"
        :animate="{ opacity: 1, y: 0, scale: 1 }"
        :transition="{ type: 'spring', stiffness: 380, damping: 28 }"
        class="mb-4"
      >
        <BCard class="border-warning shadow-sm">
          <div class="row card-body p-4">
            <div class="col-12">
              <div class="d-flex flex-wrap align-items-start gap-3">
                <div
                  class="rounded-3 bg-warning bg-opacity-10 p-3 text-warning d-flex align-items-center justify-content-center flex-shrink-0"
                >
                  <Icon icon="transfer" class="fs-2" />
                </div>
                <div class="flex-grow-1 min-w-0">
                  <h3 class="alert-heading mb-2 h5">
                    Die Domain <b id="domain-transfer-highlight">{{ highlightTransferBanner.domain }}</b> ist bereits
                    registriert
                  </h3>
                  <p class="text-muted mb-2 mb-md-3">
                    <template v-if="(highlightTransferBanner.transfer_sale_price ?? 0) > 0">
                      Sie können die Domain zu uns umziehen (Transfer). Geschätzter Transferpreis:
                      <strong>{{ formatEuro(highlightTransferBanner.transfer_sale_price ?? 0) }} €</strong>
                      <span class="text-muted"> (inkl. Verlängerung je nach TLD)</span>
                    </template>
                    <template v-else>
                      Für diese Endung liegt kein Transferpreis vor – unser Support prüft die Umzugsmöglichkeit gern für
                      Sie.
                    </template>
                  </p>
                  <BButton
                    v-if="(highlightTransferBanner.transfer_sale_price ?? 0) > 0"
                    variant="warning"
                    class="w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    @click="goToTransferCheckout(highlightTransferBanner)"
                  >
                    <Icon icon="arrow-right" class="flex-shrink-0" />
                    Zum Transfer
                  </BButton>
                </div>
              </div>
            </div>
          </div>
        </BCard>
      </motion.div>
    </AnimatePresence>

    <BCard v-if="listResults.length > 0" no-body>
      <BCardHeader style="padding-top: var(--theme-card-spacer-y); padding-left: var(--theme-card-spacer-x);">
        <h5 class="mb-0">Alle Ergebnisse</h5>
        <p class="text-muted small mb-0">Verfügbarkeit und Preise</p>
      </BCardHeader>
      <BCardBody>
        <TransitionGroup name="domain-result" tag="div" class="d-flex flex-column gap-0">
          <div
            v-for="r in listResults"
            :key="r.domain"
            class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-2 border-bottom border-secondary border-opacity-25 last-border-0"
          >
            <span class="fw-medium">{{ r.domain }}</span>
            <div class="d-flex align-items-center gap-3">
              <template v-if="r.error">
                <span class="text-muted small">Fehler beim Prüfen</span>
              </template>
              <template v-else>
                <BBadge :variant="r.available ? 'success' : 'warning'">
                  {{ r.available ? 'Verfügbar' : 'Belegt' }}
                </BBadge>
                <BBadge v-if="r.premium" variant="info">Premium</BBadge>
                <span class="fw-medium">{{ (r.available ? r.sale_price : (r.transfer_sale_price ?? 0)).toFixed(2) }} €</span>
                <BButton v-if="r.available" size="sm" @click="goToCheckout(r)">
                  Bestellen
                </BButton>
                <BButton
                  v-else-if="(r.transfer_sale_price ?? 0) > 0"
                  size="sm"
                  variant="outline-secondary"
                  @click="goToTransferCheckout(r)"
                >
                  Transfer
                </BButton>
              </template>
            </div>
          </div>
        </TransitionGroup>
      </BCardBody>
    </BCard>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import {
  BBadge,
  BButton,
  BCard,
  BCardBody,
  BFormInput,
  BSpinner,
} from 'bootstrap-vue-next'
import { AnimatePresence, motion } from 'motion-v'
import { ref, computed } from 'vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import Icon from '@/components/wrappers/Icon.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

type SearchPageProps = {
  csrf_token?: string
}

const page = usePage<SearchPageProps>()

function resolveCsrfToken(): string {
  const fromProps = page.props.csrf_token
  if (typeof fromProps === 'string' && fromProps.length > 0) {
    return fromProps
  }
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match) {
    return decodeURIComponent(match[1])
  }
  const meta = document.querySelector('meta[name="csrf-token"]')

  return meta?.getAttribute('content') ?? ''
}

type Result = {
  domain: string
  available: boolean
  premium: boolean
  sale_price: number
  purchase_price: number
  renew_sale_price?: number
  transfer_sale_price?: number
  error?: boolean
}

const domainInput = ref('')
const loading = ref(false)
const results = ref<Result[]>([])
const parseMessage = ref('')
const lastExplicitTld = ref<string | null>(null)
const lastParsedBase = ref<string | null>(null)

const defaultTlds = ['de', 'com', 'net', 'io']

/** Single DNS label (Laravel CheckDomainAvailabilityRequest domain field). */
const labelRegex = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i
const tldRegex = /^[a-z]{2,}$/i

const canSearch = computed(() => domainInput.value.trim().length >= 2)

const inputState = computed<boolean | undefined>(() => {
  if (!parseMessage.value) {
    return undefined
  }

  return false
})

const loadingHint = computed(() => {
  const p = parseDomainInput(domainInput.value)
  if (p.ok && p.explicitTld) {
    return `Prüfe zuerst .${p.explicitTld}, danach die übrigen Endungen …`
  }

  return 'Prüfe .de, .com, .net und .io …'
})

function parseDomainInput(raw: string):
  | { ok: true; base: string; explicitTld: string | null }
  | { ok: false; message: string } {
  const s = raw.trim().toLowerCase()
  if (s.length < 2) {
    return { ok: false, message: 'Bitte mindestens zwei Zeichen eingeben.' }
  }

  if (!s.includes('.')) {
    if (!labelRegex.test(s)) {
      return {
        ok: false,
        message: 'Nur Buchstaben, Zahlen und Bindestrich im Namen (ohne Punkt).',
      }
    }

    return { ok: true, base: s, explicitTld: null }
  }

  const parts = s.split('.').filter((p) => p.length > 0)
  if (parts.length !== 2 || !parts[0] || !parts[1]) {
    return {
      ok: false,
      message: 'Bitte nur einen Namen mit einer Endung (z. B. beispiel.de), keine zusätzlichen Punkte.',
    }
  }

  const [base, tld] = parts
  if (!labelRegex.test(base)) {
    return {
      ok: false,
      message: 'Ungültiger Domainname vor der Endung.',
    }
  }
  if (!tldRegex.test(tld)) {
    return { ok: false, message: 'Ungültige Endung (nur Buchstaben, mind. 2 Zeichen).' }
  }

  return { ok: true, base, explicitTld: tld }
}

function buildTldList(explicit: string | null): string[] {
  if (!explicit) {
    return [...defaultTlds]
  }
  const rest = defaultTlds.filter((t) => t !== explicit)

  return [explicit, ...rest]
}

const explicitHighlightResult = computed<Result | null>(() => {
  const tld = lastExplicitTld.value
  const base = lastParsedBase.value
  if (!tld || !base || results.value.length === 0) {
    return null
  }
  const want = `${base}.${tld}`.toLowerCase()

  return results.value.find((r) => r.domain.toLowerCase() === want) ?? null
})

const highlightCard = computed<Result | null>(() => {
  const found = explicitHighlightResult.value
  if (!found || found.error || !found.available) {
    return null
  }

  return found
})

/** Banner when the explicitly searched domain (name.tld) is taken – offer transfer. */
const highlightTransferBanner = computed<Result | null>(() => {
  const found = explicitHighlightResult.value
  if (!found || found.error || found.available) {
    return null
  }

  return found
})

/** Avoid duplicating highlighted domains (available or transfer banner) in the list below. */
const listResults = computed(() => {
  const skip = new Set<string>()
  const h = highlightCard.value
  const t = highlightTransferBanner.value
  if (h) {
    skip.add(h.domain.toLowerCase())
  }
  if (t) {
    skip.add(t.domain.toLowerCase())
  }
  if (skip.size === 0) {
    return results.value
  }

  return results.value.filter((r) => !skip.has(r.domain.toLowerCase()))
})

function formatEuro(n: number): string {
  return n.toFixed(2).replace('.', ',')
}

async function search() {
  parseMessage.value = ''
  const parsed = parseDomainInput(domainInput.value)
  if (!parsed.ok) {
    parseMessage.value = parsed.message

    return
  }

  lastExplicitTld.value = parsed.explicitTld
  lastParsedBase.value = parsed.base
  loading.value = true
  results.value = []
  try {
    const res = await fetch('/domains/check-availability', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': resolveCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        domain: parsed.base,
        tlds: buildTldList(parsed.explicitTld),
      }),
    })
    const data = await res.json()
    results.value = data.results ?? []
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

function goToCheckout(r: Result) {
  if (!r.available || r.error) return
  router.visit(`/domains/checkout?domain=${encodeURIComponent(r.domain)}&sale_price=${r.sale_price}&tld=${r.domain.split('.').pop() ?? ''}`)
}

function goToTransferCheckout(r: Result) {
  if (r.available || r.error) return
  const tld = r.domain.split('.').pop() ?? ''
  const salePrice = r.transfer_sale_price ?? 0
  if (salePrice <= 0) return
  router.visit(`/domains/checkout?domain=${encodeURIComponent(r.domain)}&sale_price=${salePrice}&tld=${tld}&transfer=1`)
}
</script>

<style scoped>
.last-border-0:last-child {
  border-bottom: 0 !important;
}

.domain-result-move,
.domain-result-enter-active,
.domain-result-leave-active {
  transition: all 0.2s ease;
}

.domain-result-enter-from,
.domain-result-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
