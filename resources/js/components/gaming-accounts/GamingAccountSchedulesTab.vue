<template>
  <BCard no-body>
    <BCardBody>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h6 class="mb-0">Schedules</h6>
        <div class="d-flex flex-wrap gap-2">
          <BButton size="sm" variant="outline-primary" :disabled="creating" @click="openCreateModal">
            <Icon v-if="creating" icon="loader-2" class="me-1" />
            <Icon v-else icon="plus" class="me-1" />
            Neuer Schedule
          </BButton>
          <a v-if="loginUrl" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center">
            <Icon icon="external-link" class="me-1" />
            Im Panel
          </a>
        </div>
      </div>

      <div v-if="loading" class="d-flex align-items-center justify-content-center py-5 text-muted">
        <span class="d-inline-flex align-items-center justify-content-center" style="line-height: 0">
          <Icon icon="loader-2" class="fs-2" />
        </span>
      </div>
      <div v-else-if="error" class="alert alert-danger small mb-0">
        {{ error }}
      </div>
      <div v-else-if="schedules.length === 0" class="d-flex flex-column align-items-center justify-content-center gap-2 py-5 text-muted text-center">
        <span class="d-inline-flex align-items-center justify-content-center opacity-50" style="line-height: 0">
          <Icon icon="calendar" class="fs-1" />
        </span>
        <p class="mb-0">Keine Schedules</p>
      </div>
      <div v-else class="d-flex flex-column gap-3">
        <div v-for="s in schedules" :key="s.id" class="border rounded p-3 bg-light bg-opacity-50">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="min-w-0">
              <div class="fw-semibold">{{ s.name ?? `Schedule #${s.id}` }}</div>
              <code class="small d-block text-muted">{{ cronString(s) }}</code>
              <span v-if="s.next_run_at" class="small text-muted">Nächster Lauf: {{ formatDate(s.next_run_at) }}</span>
            </div>
            <div class="d-flex flex-wrap gap-1 align-items-center">
              <BBadge :variant="s.is_active !== false ? 'success' : 'secondary'">
                {{ s.is_active !== false ? 'Aktiv' : 'Inaktiv' }}
              </BBadge>
              <BButton size="sm" variant="outline-primary" class="d-inline-flex align-items-center" @click="executeSchedule(s.id)">
                <Icon icon="player-play" class="me-1" />
                Jetzt
              </BButton>
              <BButton size="sm" variant="outline-secondary" class="d-inline-flex align-items-center" @click="openCreateTaskModal(s.id)">
                <Icon icon="plus" class="me-1" />
                Task
              </BButton>
              <BButton size="sm" variant="outline-danger" title="Löschen" @click="deleteSchedule(s.id)">
                <Icon icon="trash" />
              </BButton>
            </div>
          </div>
          <div class="mt-3 pt-3 border-top">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <span class="small fw-semibold text-muted d-inline-flex align-items-center gap-1">
                <Icon icon="list-check" class="fs-6" />
                Tasks
              </span>
              <BButton size="sm" variant="link" class="p-0 text-decoration-none" :disabled="loadingTasksFor === s.id" @click="fetchScheduleTasks(s.id)">
                <Icon v-if="loadingTasksFor === s.id" icon="loader-2" class="me-1" />
                {{ scheduleTasksMap[s.id] ? 'Aktualisieren' : 'Tasks laden' }}
              </BButton>
            </div>
            <div v-if="loadingTasksFor === s.id" class="small text-muted py-2">Wird geladen …</div>
            <div v-else-if="scheduleTasksMap[s.id]?.length" class="d-flex flex-column gap-2">
              <div
                v-for="t in scheduleTasksMap[s.id]"
                :key="t.id"
                class="d-flex flex-wrap align-items-center justify-content-between gap-2 rounded border bg-white px-2 py-2 small"
              >
                <span>
                  <span class="fw-medium">{{ taskActionLabel(t.action) }}</span>
                  <span v-if="t.payload" class="text-muted">
                    — {{ t.action === 'power' ? powerPayloadLabel(t.payload) : t.payload }}
                  </span>
                  <span class="text-muted"> (Offset: {{ t.time_offset }}s{{ t.continue_on_failure ? ', bei Fehler fortfahren' : '' }})</span>
                </span>
                <BButton size="sm" variant="outline-danger" class="py-0 px-2" title="Task löschen" @click="deleteTask(s.id, t.id)">
                  <Icon icon="trash" />
                </BButton>
              </div>
            </div>
            <p v-else-if="scheduleTasksMap[s.id] && scheduleTasksMap[s.id].length === 0" class="small text-muted mb-0">
              Keine Tasks. „Task“ klicken, um einen hinzuzufügen.
            </p>
          </div>
        </div>
      </div>

      <BModal v-model="createModalOpen" title="Neuer Schedule" size="lg" no-footer>
        <BForm @submit.prevent="submitCreateSchedule">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <BFormInput v-model="form.name" placeholder="z. B. Tägliches Backup" />
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Vorlagen (Cron)</label>
            <div class="d-flex flex-wrap gap-1">
              <BButton
                v-for="p in cronPresets"
                :key="p.label"
                type="button"
                size="sm"
                variant="outline-secondary"
                @click="applyCronPreset(p)"
              >
                {{ p.label }}
              </BButton>
            </div>
            <p class="text-muted small mt-1 mb-0">Oder Felder unten manuell anpassen.</p>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6 col-md">
              <label class="form-label small">Minute</label>
              <BFormInput v-model="form.minute" class="font-monospace text-center" />
            </div>
            <div class="col-6 col-md">
              <label class="form-label small">Stunde</label>
              <BFormInput v-model="form.hour" class="font-monospace text-center" />
            </div>
            <div class="col-6 col-md">
              <label class="form-label small">Tag (Monat)</label>
              <BFormInput v-model="form.day_of_month" class="font-monospace text-center" />
            </div>
            <div class="col-6 col-md">
              <label class="form-label small">Monat</label>
              <BFormInput v-model="form.month" class="font-monospace text-center" />
            </div>
            <div class="col-12 col-md">
              <label class="form-label small">Tag (Woche)</label>
              <BFormInput v-model="form.day_of_week" class="font-monospace text-center" />
            </div>
          </div>
          <div class="mb-3">
            <BButton type="button" size="sm" variant="link" class="p-0 text-decoration-none" @click="showCheatsheet = !showCheatsheet">
              <Icon :icon="showCheatsheet ? 'chevron-up' : 'chevron-down'" class="me-1" />
              {{ showCheatsheet ? 'Cron-Beispiele ausblenden' : 'Cron-Beispiele anzeigen' }}
            </BButton>
            <div v-show="showCheatsheet" class="mt-2 rounded border bg-light p-3 small">
              <p class="fw-medium mb-2">Beispiele</p>
              <ul class="mb-0 ps-3 text-muted">
                <li><code>*/5 * * * *</code> — alle 5 Minuten</li>
                <li><code>0 * * * *</code> — jede volle Stunde</li>
                <li><code>0 4 * * *</code> — täglich um 4:00 Uhr</li>
                <li><code>0 0 * * 0</code> — wöchentlich (Sonntag 0:00)</li>
                <li><code>*</code> — beliebig (Platzhalter)</li>
              </ul>
            </div>
          </div>
          <div class="mb-3 d-flex flex-column gap-2">
            <BFormCheckbox v-model="form.only_when_online">Nur ausführen, wenn der Server online ist</BFormCheckbox>
            <BFormCheckbox v-model="form.is_active">Schedule aktiv</BFormCheckbox>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <BButton variant="secondary" type="button" @click="createModalOpen = false">Abbrechen</BButton>
            <BButton type="submit" :disabled="creating">
              <Icon v-if="creating" icon="loader-2" class="me-1" />
              Erstellen
            </BButton>
          </div>
        </BForm>
      </BModal>

      <BModal v-model="createTaskModalOpen" title="Task erstellen" no-footer>
        <BForm @submit.prevent="submitCreateTask">
          <div class="mb-3">
            <label class="form-label">Aktion</label>
            <BFormSelect v-model="taskForm.action" :options="taskActionOptions" />
          </div>
          <div class="mb-3">
            <label class="form-label">Zeitversatz (Sekunden)</label>
            <BFormInput v-model.number="taskForm.time_offset" type="number" min="0" max="900" />
            <p class="text-muted small mb-0 mt-1">Wartezeit nach dem vorherigen Task (max. 900).</p>
          </div>
          <div v-if="taskForm.action === 'command'" class="mb-3">
            <label class="form-label">Befehl</label>
            <BFormInput v-model="taskForm.payload" class="font-monospace" placeholder="z. B. say Server startet neu" />
          </div>
          <div v-else-if="taskForm.action === 'power'" class="mb-3">
            <label class="form-label">Power-Aktion</label>
            <BFormSelect v-model="taskForm.payload" :options="powerPayloadOptions" />
          </div>
          <div v-else class="mb-3">
            <label class="form-label">Backup-Name (optional)</label>
            <BFormInput v-model="taskForm.payload" placeholder="z. B. Automatisches Backup" />
            <p class="text-muted small mb-0 mt-2">
              Ausschlüsse erfolgen über die Datei <code>.pteroignore</code> auf dem Server, sofern vorhanden.
            </p>
          </div>
          <div class="mb-3">
            <BFormCheckbox v-model="taskForm.continue_on_failure">Bei Fehler mit nächstem Task fortfahren</BFormCheckbox>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <BButton variant="secondary" type="button" @click="createTaskModalOpen = false">Abbrechen</BButton>
            <BButton type="submit" :disabled="creatingTask">
              <Icon v-if="creatingTask" icon="loader-2" class="me-1" />
              Task erstellen
            </BButton>
          </div>
        </BForm>
      </BModal>
    </BCardBody>
  </BCard>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  BButton,
  BBadge,
  BCard,
  BCardBody,
  BForm,
  BFormInput,
  BFormCheckbox,
  BFormSelect,
  BModal,
} from 'bootstrap-vue-next'
import Icon from '@/components/wrappers/Icon.vue'
import { notify } from '@/composables/useNotify'

const props = defineProps<{
  gameServerAccountId: string
  loginUrl?: string | null
}>()

type Schedule = {
  id: number
  name?: string
  cron?: { minute?: string; hour?: string; day_of_month?: string; month?: string; day_of_week?: string }
  is_active?: boolean
  next_run_at?: string
}

type ScheduleTask = {
  id: number
  sequence_id?: number
  action: string
  payload: string
  time_offset: number
  continue_on_failure?: boolean
}

type CronPreset = {
  label: string
  minute: string
  hour: string
  day_of_month: string
  month: string
  day_of_week: string
}

const schedules = ref<Schedule[]>([])
const scheduleTasksMap = ref<Record<number, ScheduleTask[]>>({})
const loading = ref(false)
const creating = ref(false)
const creatingTask = ref(false)
const loadingTasksFor = ref<number | null>(null)
const error = ref<string | null>(null)
const createModalOpen = ref(false)
const createTaskModalOpen = ref(false)
const createTaskScheduleId = ref<number | null>(null)
const showCheatsheet = ref(false)

const form = ref({
  name: '',
  minute: '*/5',
  hour: '*',
  day_of_month: '*',
  month: '*',
  day_of_week: '*',
  only_when_online: false,
  is_active: true,
})

const taskForm = ref({
  action: 'command' as 'command' | 'power' | 'backup',
  payload: '',
  time_offset: 0,
  continue_on_failure: false,
})

const taskActionOptions = [
  { value: 'command', text: 'Befehl senden' },
  { value: 'power', text: 'Power-Aktion' },
  { value: 'backup', text: 'Backup erstellen' },
]

const powerPayloadOptions = [
  { value: 'start', text: 'Server starten' },
  { value: 'stop', text: 'Server stoppen' },
  { value: 'restart', text: 'Server neustarten' },
  { value: 'kill', text: 'Kill' },
]

const cronPresets: CronPreset[] = [
  { label: 'Alle 5 Min.', minute: '*/5', hour: '*', day_of_month: '*', month: '*', day_of_week: '*' },
  { label: 'Stündlich', minute: '0', hour: '*', day_of_month: '*', month: '*', day_of_week: '*' },
  { label: 'Täglich 4:00', minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*' },
  { label: 'Täglich Mitternacht', minute: '0', hour: '0', day_of_month: '*', month: '*', day_of_week: '*' },
  { label: 'So. 0:00', minute: '0', hour: '0', day_of_month: '*', month: '*', day_of_week: '0' },
]

const baseUrl = () => `/gaming-accounts/${props.gameServerAccountId}/api/schedules`

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match) {
    return decodeURIComponent(match[1])
  }
  const meta = document.querySelector('meta[name="csrf-token"]')
  return (meta && (meta as HTMLMetaElement).content) || ''
}

function fetchSchedules() {
  loading.value = true
  error.value = null
  fetch(baseUrl(), { credentials: 'same-origin' })
    .then((r) => r.json())
    .then((data) => {
      if (data.success && Array.isArray(data.schedules)) {
        schedules.value = data.schedules
      } else {
        error.value = data.message ?? 'Fehler beim Laden'
      }
    })
    .catch((e) => {
      error.value = e.message ?? 'Verbindungsfehler'
    })
    .finally(() => {
      loading.value = false
    })
}

watch(() => props.gameServerAccountId, fetchSchedules, { immediate: true })

watch(
  () => taskForm.value.action,
  (a) => {
    if (a === 'power') {
      const p = taskForm.value.payload
      if (!p || !['start', 'stop', 'restart', 'kill'].includes(p)) {
        taskForm.value.payload = 'start'
      }
    } else {
      taskForm.value.payload = ''
    }
  },
)

function cronString(s: Schedule) {
  const c = s.cron ?? {}
  const parts = [
    c.minute ?? '*',
    c.hour ?? '*',
    c.day_of_month ?? '*',
    c.month ?? '*',
    c.day_of_week ?? '*',
  ]
  return parts.join(' ')
}

function applyCronPreset(p: CronPreset) {
  form.value.minute = p.minute
  form.value.hour = p.hour
  form.value.day_of_month = p.day_of_month
  form.value.month = p.month
  form.value.day_of_week = p.day_of_week
}

function openCreateModal() {
  form.value = {
    name: '',
    minute: '*/5',
    hour: '*',
    day_of_month: '*',
    month: '*',
    day_of_week: '*',
    only_when_online: false,
    is_active: true,
  }
  showCheatsheet.value = false
  createModalOpen.value = true
}

function submitCreateSchedule() {
  if (creating.value) {
    return
  }
  const name = form.value.name.trim() || 'Schedule'
  creating.value = true
  const payload = {
    name,
    minute: form.value.minute || '*',
    hour: form.value.hour || '*',
    day_of_month: form.value.day_of_month || '*',
    month: form.value.month || '*',
    day_of_week: form.value.day_of_week || '*',
    is_active: form.value.is_active,
    only_when_online: form.value.only_when_online,
  }
  fetch(baseUrl(), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        notify.success('Schedule erstellt.')
        createModalOpen.value = false
        fetchSchedules()
      } else {
        notify.error(data.message ?? 'Fehler')
      }
    })
    .catch(() => notify.error('Verbindungsfehler'))
    .finally(() => {
      creating.value = false
    })
}

function deleteSchedule(scheduleId: number) {
  if (!confirm('Schedule wirklich löschen?')) {
    return
  }
  fetch(`${baseUrl()}/${scheduleId}`, {
    method: 'DELETE',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  })
    .then((r) => r.json().catch(() => ({})))
    .then((data) => {
      if (data?.success !== false) {
        notify.success('Schedule gelöscht.')
        const next = { ...scheduleTasksMap.value }
        delete next[scheduleId]
        scheduleTasksMap.value = next
        fetchSchedules()
      } else {
        notify.error(data?.message ?? 'Fehler')
      }
    })
    .catch(() => notify.error('Verbindungsfehler'))
}

function executeSchedule(scheduleId: number) {
  fetch(`${baseUrl()}/${scheduleId}/execute`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({}),
  })
    .then((r) => r.json().catch(() => ({})))
    .then((data) => {
      if (data?.success !== false) {
        notify.success('Schedule ausgeführt.')
      } else {
        notify.error(data?.message ?? 'Fehler')
      }
    })
    .catch(() => notify.error('Verbindungsfehler'))
}

function formatDate(val: string | undefined) {
  if (!val) {
    return '—'
  }
  try {
    return new Date(val).toLocaleString('de-DE')
  } catch {
    return '—'
  }
}

function extractTasksFromSchedule(scheduleData: Record<string, unknown>): ScheduleTask[] {
  const rel = scheduleData?.relationships as Record<string, unknown> | undefined
  const tasksData = rel?.tasks as { data?: Array<Record<string, unknown>> } | undefined
  const list = tasksData?.data ?? []
  return list.map((t) => {
    const attrs = (t.attributes ?? t) as Record<string, unknown>
    const idRaw = attrs.id ?? t.id
    const id = typeof idRaw === 'number' ? idRaw : parseInt(String(idRaw ?? '0'), 10) || 0
    return {
      id,
      sequence_id: attrs.sequence_id as number | undefined,
      action: (attrs.action as string) ?? 'command',
      payload: (attrs.payload as string) ?? '',
      time_offset: (attrs.time_offset as number) ?? 0,
      continue_on_failure: attrs.continue_on_failure as boolean | undefined,
    }
  })
}

function fetchScheduleTasks(scheduleId: number) {
  if (loadingTasksFor.value === scheduleId) {
    return
  }
  loadingTasksFor.value = scheduleId
  fetch(`${baseUrl()}/${scheduleId}`, { credentials: 'same-origin' })
    .then((r) => r.json())
    .then((data) => {
      if (data.success && data.schedule) {
        const tasksList = extractTasksFromSchedule(data.schedule as Record<string, unknown>)
        scheduleTasksMap.value = { ...scheduleTasksMap.value, [scheduleId]: tasksList }
      }
    })
    .catch(() => {})
    .finally(() => {
      loadingTasksFor.value = null
    })
}

function openCreateTaskModal(scheduleId: number) {
  createTaskScheduleId.value = scheduleId
  taskForm.value = {
    action: 'command',
    payload: '',
    time_offset: 0,
    continue_on_failure: false,
  }
  if (!scheduleTasksMap.value[scheduleId]) {
    fetchScheduleTasks(scheduleId)
  }
  createTaskModalOpen.value = true
}

function submitCreateTask() {
  const sid = createTaskScheduleId.value
  if (creatingTask.value || sid === null) {
    return
  }
  const payload =
    taskForm.value.action === 'power' ? taskForm.value.payload || 'start' : taskForm.value.payload
  creatingTask.value = true
  fetch(`${baseUrl()}/${sid}/tasks`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      action: taskForm.value.action,
      payload,
      time_offset: Math.max(0, Math.min(900, taskForm.value.time_offset)),
      continue_on_failure: taskForm.value.continue_on_failure,
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        notify.success('Task erstellt.')
        createTaskModalOpen.value = false
        fetchScheduleTasks(sid)
      } else {
        notify.error(data.message ?? 'Fehler')
      }
    })
    .catch(() => notify.error('Verbindungsfehler'))
    .finally(() => {
      creatingTask.value = false
    })
}

function deleteTask(scheduleId: number, taskId: number) {
  if (!confirm('Task wirklich löschen?')) {
    return
  }
  fetch(`${baseUrl()}/${scheduleId}/tasks/${taskId}`, {
    method: 'DELETE',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  })
    .then((r) => r.json().catch(() => ({})))
    .then((data) => {
      if (data?.success !== false) {
        notify.success('Task gelöscht.')
        fetchScheduleTasks(scheduleId)
      } else {
        notify.error(data?.message ?? 'Fehler')
      }
    })
    .catch(() => notify.error('Verbindungsfehler'))
}

function taskActionLabel(action: string): string {
  switch (action) {
    case 'command':
      return 'Befehl senden'
    case 'power':
      return 'Power-Aktion'
    case 'backup':
      return 'Backup erstellen'
    default:
      return action
  }
}

function powerPayloadLabel(payload: string): string {
  const labels: Record<string, string> = {
    start: 'Server starten',
    stop: 'Server stoppen',
    restart: 'Server neustarten',
    kill: 'Kill',
  }
  return labels[payload] ?? payload
}
</script>
