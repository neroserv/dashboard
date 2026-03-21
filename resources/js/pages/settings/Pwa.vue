<template>
  <DefaultLayout>
    <Head title="PWA &amp; Benachrichtigungen" />
    <PageBreadcrumb title="PWA &amp; Benachrichtigungen" subtitle="Einstellungen" subtitle-url="/settings/profile" />

    <SettingsLayout>
      <BAlert v-if="flash.success" variant="success" show dismissible class="mb-4">
        {{ flash.success }}
      </BAlert>
      <BAlert v-if="flash.error" variant="danger" show dismissible class="mb-4">
        {{ flash.error }}
      </BAlert>

      <div class="mb-4">
        <h4 class="mb-1">PWA &amp; Benachrichtigungen</h4>
        <p class="text-muted mb-0">
          Progressive Web App installieren und Browser-Push aktivieren. Push folgt Ihren Einstellungen unten und optional den
          E-Mail-Benachrichtigungen.
        </p>
      </div>

      <BCard no-body class="mb-4">
        <BCardHeader>
          <h5 class="mb-0">App installieren</h5>
          <p class="text-muted small mb-0">Dieses Panel als installierbare App (Startbildschirm oder Desktop) nutzen.</p>
        </BCardHeader>
        <BCardBody>
          <p v-if="isStandalone" class="text-success small mb-0">
            Die App läuft bereits installiert (Vollbild / eigenes Fenster).
          </p>
          <template v-else>
            <div v-if="!serviceWorkerSupported" class="text-muted small mb-0">
              In diesem Browser sind Service Worker nicht verfügbar – eine Installation als PWA ist nicht möglich.
            </div>
            <template v-else>
              <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <BButton
                  v-if="canUseInstallPrompt"
                  type="button"
                  variant="primary"
                  size="sm"
                  :disabled="installBusy"
                  @click="promptInstall"
                >
                  <span v-if="installBusy" class="d-inline-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                    Installation…
                  </span>
                  <span v-else>App installieren</span>
                </BButton>
              </div>
              <p v-if="!canUseInstallPrompt && serviceWorkerSupported" class="text-muted small mb-2 mb-md-3">
                <strong>Chrome / Edge / Android:</strong> Nutzen Sie das Install-Symbol in der Adressleiste oder das Menü (⋮) →
                <em>App installieren</em> bzw. <em>Als App installieren</em>. Der Button erscheint, sobald der Browser die App
                als installierbar einstuft (einmal die Seite neu laden kann helfen).
              </p>
              <p class="text-muted small mb-0">
                <strong>iPhone / iPad (Safari):</strong> Teilen-Taste
                <span class="text-nowrap">(□↑)</span>
                → <em>Zum Home-Bildschirm</em>. Anschließend die App von dort öffnen.
              </p>
            </template>
          </template>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-4">
        <BCardHeader>
          <h5 class="mb-0">Push-Benachrichtigungen</h5>
          <p class="text-muted small mb-0">Berechtigung wird erst nach Klick auf den Button angefragt.</p>
        </BCardHeader>
        <BCardBody>
          <div v-if="!pushSupported" class="text-muted small">
            Ihr Browser unterstützt keine Web-Push-Benachrichtigungen oder Service Worker.
          </div>
          <template v-else>
            <p class="small mb-2">
              <strong>Status:</strong>
              {{ permissionLabel }}
            </p>
            <p v-if="!vapidPublicKey" class="text-warning small mb-2">
              Push ist auf dem Server noch nicht konfiguriert (VAPID-Schlüssel fehlen). Bitte Administrator kontaktieren.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <BButton
                type="button"
                variant="primary"
                size="sm"
                :disabled="enableBusy || !vapidPublicKey || permissionState === 'denied'"
                @click="onEnablePush"
              >
                <span v-if="enableBusy" class="d-inline-flex align-items-center gap-2">
                  <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                  Wird eingerichtet…
                </span>
                <span v-else>Benachrichtigungen aktivieren</span>
              </BButton>
              <BButton
                v-if="permissionState === 'granted'"
                type="button"
                variant="outline-secondary"
                size="sm"
                :disabled="disableLocalBusy"
                @click="onDisableLocalPush"
              >
                Push in diesem Browser beenden
              </BButton>
            </div>
            <p v-if="permissionState === 'denied'" class="text-danger small mt-2 mb-0">
              Benachrichtigungen wurden blockiert. Ändern Sie die Einstellung in Ihrem Browser, um Push zu nutzen.
            </p>
            <p class="text-muted small mt-3 mb-0">
              iOS: Push funktioniert nur für installierte PWAs ab iOS 16.4 und kann eingeschränkt sein.
            </p>
          </template>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-4">
        <BCardHeader>
          <h5 class="mb-0">Registrierte Geräte</h5>
          <p class="text-muted small mb-0">Dieses Konto – aktuelle Push-Endpunkte.</p>
        </BCardHeader>
        <BCardBody>
          <p v-if="localSubscriptions.length === 0" class="text-muted small mb-0">Noch kein Gerät für Push registriert.</p>
          <ul v-else class="list-unstyled mb-0 small">
            <li
              v-for="sub in localSubscriptions"
              :key="sub.id"
              class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 py-2 border-bottom border-secondary border-opacity-25"
            >
              <div class="min-w-0">
                <code class="small text-break d-block">{{ sub.endpoint_preview }}</code>
                <span v-if="sub.created_at" class="text-muted">seit {{ formatDate(sub.created_at) }}</span>
              </div>
              <BButton
                type="button"
                variant="outline-danger"
                size="sm"
                class="flex-shrink-0"
                :disabled="unregisterBusyId === sub.id"
                @click="unregisterDevice(sub)"
              >
                Entfernen
              </BButton>
            </li>
          </ul>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-4">
        <BCardHeader>
          <h5 class="mb-0">Einstellungen</h5>
          <p class="text-muted small mb-0">Steuern, wann Push gesendet wird.</p>
        </BCardHeader>
        <BCardBody>
          <BForm @submit.prevent="savePreferences">
            <div class="form-check form-switch mb-3">
              <BFormCheckbox v-model="prefs.master_enabled" switch> Push-Benachrichtigungen für mein Konto aktivieren </BFormCheckbox>
            </div>
            <div class="form-check form-switch mb-3">
              <BFormCheckbox v-model="prefs.sync_with_email" switch :disabled="!prefs.master_enabled">
                Push an E-Mail-Kanal koppeln (kein Push, wenn E-Mail für einen Typ deaktiviert ist)
              </BFormCheckbox>
            </div>

            <div v-if="prefs.master_enabled && !prefs.sync_with_email" class="mb-3">
              <p class="small text-muted">Pro Benachrichtigungstyp einzeln aktivieren:</p>
              <div
                v-for="t in templates"
                :key="t.key"
                class="form-check form-switch py-1 border-bottom border-secondary border-opacity-25"
              >
                <BFormCheckbox v-model="typeChecks[t.key]" switch>
                  {{ t.name }}
                </BFormCheckbox>
              </div>
            </div>

            <BButton type="submit" variant="primary" :disabled="prefsBusy">
              {{ prefsBusy ? 'Speichern…' : 'Speichern' }}
            </BButton>
            <span v-if="prefsMessage" class="text-success small ms-2">{{ prefsMessage }}</span>
            <span v-if="prefsError" class="text-danger small ms-2 d-block mt-1">{{ prefsError }}</span>
          </BForm>
        </BCardBody>
      </BCard>
    </SettingsLayout>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { BAlert, BButton, BCard, BCardBody, BCardHeader, BForm, BFormCheckbox } from 'bootstrap-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import PageBreadcrumb from '@/components/PageBreadcrumb.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';
import {
  pushPreferencesRequest,
  pushSubscribeRequest,
  pushSupported,
  pushUnsubscribeRequest,
  registerServiceWorker,
  subscribeWithVapid,
} from '@/composables/useWebPush';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type TemplateRow = { key: string; name: string };
type SubRow = { id: number; endpoint: string; endpoint_preview: string; created_at: string | null };

const props = defineProps<{
  templates: TemplateRow[];
  pushSettings: { master_enabled: boolean; sync_with_email: boolean; types: Record<string, boolean> };
  pushSubscriptions: SubRow[];
  vapidPublicKey: string | null;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

const { isStandalone, installBusy, canUseInstallPrompt, promptInstall } = usePwaInstall();

const serviceWorkerSupported = typeof window !== 'undefined' && 'serviceWorker' in navigator;

const permissionState = ref<NotificationPermission | 'unsupported'>(
  typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
);

const localSubscriptions = ref<SubRow[]>([...props.pushSubscriptions]);

watch(
  () => props.pushSubscriptions,
  (v) => {
    localSubscriptions.value = [...v];
  },
  { deep: true },
);

const permissionLabel = computed(() => {
  if (permissionState.value === 'unsupported') {
    return 'Nicht unterstützt';
  }
  if (permissionState.value === 'granted') {
    return 'Erlaubt';
  }
  if (permissionState.value === 'denied') {
    return 'Verweigert';
  }

  return 'Noch nicht angefragt';
});

const prefs = reactive({
  master_enabled: props.pushSettings.master_enabled,
  sync_with_email: props.pushSettings.sync_with_email,
});

const typeChecks = reactive<Record<string, boolean>>({});
for (const t of props.templates) {
  typeChecks[t.key] = props.pushSettings.types?.[t.key] ?? false;
}

const enableBusy = ref(false);
const disableLocalBusy = ref(false);
const unregisterBusyId = ref<number | null>(null);
const prefsBusy = ref(false);
const prefsMessage = ref('');
const prefsError = ref('');

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

async function onEnablePush(): Promise<void> {
  if (!props.vapidPublicKey) {
    return;
  }
  prefsError.value = '';
  enableBusy.value = true;
  try {
    if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
      const result = await Notification.requestPermission();
      permissionState.value = result;
      if (result !== 'granted') {
        return;
      }
    } else if (typeof Notification !== 'undefined') {
      permissionState.value = Notification.permission;
      if (Notification.permission !== 'granted') {
        return;
      }
    }

    const sub = await subscribeWithVapid(props.vapidPublicKey);
    if (!sub) {
      prefsError.value = 'Push-Abonnement konnte nicht erstellt werden.';
      return;
    }

    const res = await pushSubscribeRequest(sub);
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      prefsError.value = (err as { message?: string }).message ?? 'Speichern des Abonnements fehlgeschlagen.';
      return;
    }

    router.reload({ only: ['pushSubscriptions'] });
  } finally {
    enableBusy.value = false;
  }
}

async function onDisableLocalPush(): Promise<void> {
  disableLocalBusy.value = true;
  prefsError.value = '';
  try {
    const reg = await registerServiceWorker();
    const sub = reg ? await reg.pushManager.getSubscription() : null;
    if (sub) {
      const json = sub.toJSON();
      if (json.endpoint) {
        await pushUnsubscribeRequest(json.endpoint);
      }
      await sub.unsubscribe();
    }
    router.reload({ only: ['pushSubscriptions'] });
  } finally {
    disableLocalBusy.value = false;
  }
}

async function unregisterDevice(sub: SubRow): Promise<void> {
  unregisterBusyId.value = sub.id;
  prefsError.value = '';
  try {
    const res = await pushUnsubscribeRequest(sub.endpoint);
    if (!res.ok) {
      prefsError.value = 'Gerät konnte nicht entfernt werden.';
      return;
    }
    localSubscriptions.value = localSubscriptions.value.filter((s) => s.id !== sub.id);
    router.reload({ only: ['pushSubscriptions'] });
  } finally {
    unregisterBusyId.value = null;
  }
}

async function savePreferences(): Promise<void> {
  prefsBusy.value = true;
  prefsMessage.value = '';
  prefsError.value = '';
  const types: Record<string, boolean> = {};
  for (const t of props.templates) {
    types[t.key] = !!typeChecks[t.key];
  }
  try {
    const res = await pushPreferencesRequest({
      master_enabled: prefs.master_enabled,
      sync_with_email: prefs.sync_with_email,
      types,
    });
    if (!res.ok) {
      const body = (await res.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
      };
      if (body.errors) {
        prefsError.value = Object.values(body.errors)
          .flat()
          .join(' ');
      } else {
        prefsError.value = body.message ?? 'Speichern fehlgeschlagen.';
      }

      return;
    }
    prefsMessage.value = 'Gespeichert.';
    setTimeout(() => {
      prefsMessage.value = '';
    }, 3000);
  } finally {
    prefsBusy.value = false;
  }
}
</script>
