<template>
  <div class="sidenav-user" style="background: url(/images/user-bg-pattern.svg)">
    <div class="d-flex justify-content-between align-items-center">
      <div class="min-w-0">
        <Link href="/settings/profile" class="link-reset d-block">
          <UserAvatarOrInitials
            class="sidenav-user-avatar mb-2"
            :name="displayName"
            :src="userAvatar"
            :size="48"
            rounded-class="rounded-circle"
          />
          <span class="sidenav-user-name fw-bold d-block text-truncate">{{ displayName }}</span>
          <span v-if="userEmail" class="fs-12 fw-semibold text-truncate d-block opacity-75">{{ userEmail }}</span>
        </Link>
      </div>

      <BDropdown
        :variant="null"
        no-caret
        placement="bottom-end"
        toggle-class="link-reset sidenav-user-set-icon border-0 p-0"
        offset="7"
      >
        <template #button-content>
          <Icon icon="settings" class="fs-24 align-middle ms-1" />
        </template>

        <BDropdownHeader class="noti-title">
          <h6 class="text-overflow m-0">Willkommen zurück</h6>
        </BDropdownHeader>

        <Link href="/settings/profile" class="dropdown-item">
          <Icon icon="user-circle" class="me-1 fs-lg align-middle" />
          <span class="align-middle">Profil</span>
        </Link>

        <Link href="/settings/profile" class="dropdown-item">
          <Icon icon="settings-2" class="me-1 fs-lg align-middle" />
          <span class="align-middle">Einstellungen</span>
        </Link>

        <button type="button" class="dropdown-item text-danger fw-semibold" @click="onLogout">
          <Icon icon="logout" class="me-1 fs-lg align-middle" />
          <span class="align-middle">Abmelden</span>
        </button>
      </BDropdown>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { BDropdown, BDropdownHeader } from 'bootstrap-vue-next'
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue'
import Icon from '@/components/wrappers/Icon.vue'

const page = usePage()

type AuthUser = {
  name?: string
  email?: string
  avatar?: string | null
}

const authUser = computed(() => (page.props.auth as { user?: AuthUser })?.user)
const displayName = computed(() => authUser.value?.name?.trim() || 'Benutzer')
const userEmail = computed(() => authUser.value?.email?.trim() || '')
const userAvatar = computed(() => {
  const a = authUser.value?.avatar
  return a && String(a).trim() !== '' ? String(a) : null
})

function onLogout(): void {
  router.post('/logout')
}
</script>

<style scoped>
.sidenav-user-avatar {
  display: block;
}
</style>
