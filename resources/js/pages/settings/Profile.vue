<template>
  <DefaultLayout>
    <Head title="Profil" />
    <PageBreadcrumb title="Profil" subtitle="Einstellungen" subtitle-url="/settings/profile" />

    <SettingsLayout>
      <BAlert v-if="flash.success" variant="success" show dismissible class="mb-4">
        {{ flash.success }}
      </BAlert>

      <div class="mb-4">
        <h4 class="mb-1">Profil</h4>
        <p class="text-muted mb-0">
          Profilbild, Kontaktdaten und Rechnungsadresse verwalten
        </p>
      </div>

      <BCard no-body class="mb-4 border-0 shadow-sm profile-photo-card">
        <BCardBody class="p-4">
          <div class="d-flex flex-column flex-sm-row align-items-start gap-4">
            <div class="profile-photo-preview flex-shrink-0">
              <UserAvatarOrInitials
                class="profile-photo-avatar"
                :name="displayName"
                :src="userAvatar"
                :size="112"
                rounded-class="rounded-circle"
              />
            </div>
            <div class="flex-grow-1 min-w-0">
              <h5 class="mb-1">Profilbild</h5>
              <p class="text-muted small mb-3">
                JPG, PNG, GIF oder WebP, maximal 2&nbsp;MB. Wird in der Kopfzeile angezeigt.
              </p>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <input
                  ref="avatarInputRef"
                  type="file"
                  class="d-none"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  @change="onAvatarFileSelected"
                />
                <BButton
                  type="button"
                  variant="primary"
                  size="sm"
                  :disabled="avatarForm.processing"
                  @click="avatarInputRef?.click()"
                >
                  <span v-if="avatarForm.processing" class="d-inline-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                    Wird hochgeladen…
                  </span>
                  <span v-else>{{ userAvatar ? 'Neues Bild wählen' : 'Bild hochladen' }}</span>
                </BButton>
                <BButton
                  v-if="userAvatar"
                  type="button"
                  variant="outline-danger"
                  size="sm"
                  :disabled="avatarForm.processing || removeAvatarProcessing"
                  @click="removeAvatar"
                >
                  Entfernen
                </BButton>
              </div>
              <div v-if="avatarForm.errors.avatar" class="invalid-feedback d-block mt-2">
                {{ avatarForm.errors.avatar }}
              </div>
            </div>
          </div>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-4 border-0 shadow-sm">
        <BCardHeader class="bg-transparent border-bottom py-3">
          <h5 class="mb-0">Persönliche Daten</h5>
          <p class="text-muted small mb-0">Name, E-Mail und Erreichbarkeit</p>
        </BCardHeader>
        <BCardBody class="p-4">
          <BForm @submit.prevent="submit">
            <BRow class="g-3">
              <BCol md="6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <BFormInput
                  v-model="form.name"
                  name="name"
                  required
                  autocomplete="name"
                  placeholder="Vollständiger Name"
                  :class="{ 'is-invalid': form.errors.name }"
                />
                <div v-if="form.errors.name" class="invalid-feedback d-block">{{ form.errors.name }}</div>
              </BCol>
              <BCol md="6">
                <label class="form-label">E-Mail <span class="text-danger">*</span></label>
                <BFormInput
                  v-model="form.email"
                  type="email"
                  name="email"
                  required
                  autocomplete="username"
                  placeholder="E-Mail-Adresse"
                  :class="{ 'is-invalid': form.errors.email }"
                />
                <div v-if="form.errors.email" class="invalid-feedback d-block">{{ form.errors.email }}</div>
              </BCol>
              <BCol md="6">
                <label class="form-label">Unternehmen (optional)</label>
                <BFormInput
                  v-model="form.company"
                  name="company"
                  autocomplete="organization"
                  placeholder="Firma / Unternehmen"
                  :class="{ 'is-invalid': form.errors.company }"
                />
                <div v-if="form.errors.company" class="invalid-feedback d-block">{{ form.errors.company }}</div>
              </BCol>
              <BCol md="6">
                <label class="form-label">Telefonnummer</label>
                <BFormInput
                  v-model="form.phone"
                  name="phone"
                  type="tel"
                  autocomplete="tel"
                  placeholder="z. B. +49 123 456789"
                  :class="{ 'is-invalid': form.errors.phone }"
                />
                <div v-if="form.errors.phone" class="invalid-feedback d-block">{{ form.errors.phone }}</div>
                <BFormText class="text-muted small">
                  Wird u. a. für Domain-Registrierung (WHOIS) benötigt. Format z. B. +49.123456789.
                </BFormText>
              </BCol>
            </BRow>

            <hr class="my-4" />

            <h6 class="mb-3 fw-semibold">Rechnungsadresse</h6>
            <BAlert variant="info" show class="small mb-3">
              Für Rechnungen und Bestellungen benötigen wir Straße, PLZ, Ort und Land.
            </BAlert>
            <BRow class="g-3">
              <BCol xs="12">
                <label class="form-label">Straße und Hausnummer</label>
                <BFormInput
                  v-model="form.street"
                  name="street"
                  autocomplete="street-address"
                  placeholder="Musterstraße 1"
                  :class="{ 'is-invalid': form.errors.street }"
                />
                <div v-if="form.errors.street" class="invalid-feedback d-block">{{ form.errors.street }}</div>
              </BCol>
              <BCol md="4">
                <label class="form-label">PLZ</label>
                <BFormInput
                  v-model="form.postal_code"
                  name="postal_code"
                  autocomplete="postal-code"
                  placeholder="12345"
                  :class="{ 'is-invalid': form.errors.postal_code }"
                />
                <div v-if="form.errors.postal_code" class="invalid-feedback d-block">{{ form.errors.postal_code }}</div>
              </BCol>
              <BCol md="4">
                <label class="form-label">Ort</label>
                <BFormInput
                  v-model="form.city"
                  name="city"
                  autocomplete="address-level2"
                  placeholder="Stadt"
                  :class="{ 'is-invalid': form.errors.city }"
                />
                <div v-if="form.errors.city" class="invalid-feedback d-block">{{ form.errors.city }}</div>
              </BCol>
              <BCol md="4">
                <label class="form-label">Bundesland / State</label>
                <BFormInput
                  v-model="form.state"
                  name="state"
                  autocomplete="address-level1"
                  placeholder="z. B. Bayern oder BY"
                  :class="{ 'is-invalid': form.errors.state }"
                />
                <div v-if="form.errors.state" class="invalid-feedback d-block">{{ form.errors.state }}</div>
                <BFormText class="text-muted small">
                  Erforderlich für Domain-Registrierung (WHOIS).
                </BFormText>
              </BCol>
              <BCol xs="12">
                <label class="form-label">Land</label>
                <BFormSelect v-model="form.country" name="country" autocomplete="country-code" :class="{ 'is-invalid': form.errors.country }">
                  <option value="">Bitte wählen</option>
                  <option v-for="c in countriesSortedByName" :key="c.code" :value="c.code">
                    {{ c.name }}
                  </option>
                </BFormSelect>
                <div v-if="form.errors.country" class="invalid-feedback d-block">{{ form.errors.country }}</div>
              </BCol>
              <BCol v-if="isAdmin" xs="12" class="border-top pt-3 mt-1">
                <label class="form-label">Signatur für Support-Tickets</label>
                <BFormTextarea
                  v-model="form.ticket_signature"
                  name="ticket_signature"
                  placeholder="Wird automatisch unter jeder Ihrer Ticket-Antworten eingefügt."
                  rows="4"
                  :class="{ 'is-invalid': form.errors.ticket_signature }"
                />
                <div v-if="form.errors.ticket_signature" class="invalid-feedback d-block">{{ form.errors.ticket_signature }}</div>
                <BFormText class="text-muted small">
                  Nur für Mitarbeiter sichtbar. Wird unter jeder Ihrer Antworten im Support-Ticket-System angehängt.
                </BFormText>
              </BCol>
              <BCol xs="12">
                <BAlert v-if="mustVerifyEmail && !authUser?.email_verified_at" variant="warning" show>
                  Ihre E-Mail-Adresse ist nicht verifiziert.
                  <Link href="/email/verification-notification" method="post" as="button" class="alert-link">Verifizierungs-E-Mail erneut senden</Link>
                </BAlert>
                <BAlert v-if="status === 'verification-link-sent'" variant="success" show>
                  Ein neuer Verifizierungslink wurde an Ihre E-Mail-Adresse gesendet.
                </BAlert>
              </BCol>
              <BCol xs="12" class="d-flex align-items-center gap-3 pt-1">
                <BButton type="submit" variant="primary" :disabled="form.processing" data-test="update-profile-button">
                  Speichern
                </BButton>
                <span v-if="form.recentlySuccessful" class="text-success small">Gespeichert.</span>
              </BCol>
            </BRow>
          </BForm>
        </BCardBody>
      </BCard>

      <DeleteUser />
    </SettingsLayout>
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  BAlert,
  BButton,
  BCard,
  BCardBody,
  BCardHeader,
  BCol,
  BForm,
  BFormInput,
  BFormSelect,
  BFormText,
  BFormTextarea,
  BRow,
} from 'bootstrap-vue-next'
import DeleteUser from '@/components/DeleteUser.vue'
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import { countriesSortedByName } from '@/lib/countries'

defineProps<{
  mustVerifyEmail?: boolean
  status?: string
}>()

const page = usePage()
const flash = computed(() => (page.props.flash as { error?: string; success?: string }) ?? {})

type AuthUser = Record<string, unknown> & {
  is_admin?: boolean
  name?: string
  email?: string
  avatar?: string | null
  email_verified_at?: string | null
}

const initialAuthUser = (page.props.auth as { user?: AuthUser })?.user

const authUser = computed(() => (page.props.auth as { user?: AuthUser })?.user)
const displayName = computed(() => authUser.value?.name?.trim() || 'Benutzer')
const userAvatar = computed(() => {
  const a = authUser.value?.avatar
  return a && String(a).trim() !== '' ? String(a) : null
})
const isAdmin = computed(() => authUser.value?.is_admin === true)

const avatarInputRef = ref<HTMLInputElement | null>(null)
const removeAvatarProcessing = ref(false)

const avatarForm = useForm<{ avatar: File | null }>({
  avatar: null,
})

const form = useForm({
  name: (initialAuthUser?.name as string) ?? '',
  email: (initialAuthUser?.email as string) ?? '',
  company: (initialAuthUser?.company as string) ?? '',
  phone: (initialAuthUser?.phone as string) ?? '',
  street: (initialAuthUser?.street as string) ?? '',
  postal_code: (initialAuthUser?.postal_code as string) ?? '',
  city: (initialAuthUser?.city as string) ?? '',
  state: (initialAuthUser?.state as string) ?? '',
  country: (initialAuthUser?.country as string) ?? '',
  ticket_signature: (initialAuthUser?.ticket_signature as string) ?? '',
})

function onAvatarFileSelected(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) {
    return
  }

  avatarForm.avatar = file
  avatarForm.post('/settings/profile/avatar', {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      input.value = ''
      avatarForm.reset()
    },
  })
}

function removeAvatar(): void {
  removeAvatarProcessing.value = true
  router.delete('/settings/profile/avatar', {
    preserveScroll: true,
    onFinish: () => {
      removeAvatarProcessing.value = false
    },
  })
}

function submit(): void {
  form.patch('/settings/profile')
}
</script>

<style scoped>
.profile-photo-card {
  overflow: hidden;
}

.profile-photo-preview {
  padding: 4px;
  border-radius: 50%;
  background: linear-gradient(
    145deg,
    rgba(79, 70, 229, 0.2) 0%,
    rgba(147, 51, 234, 0.18) 50%,
    rgba(219, 39, 119, 0.16) 100%
  );
}

:deep(.profile-photo-avatar) {
  display: block;
}
</style>
