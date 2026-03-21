<template>
    <DefaultLayout>
        <Head :title="`Ticket #${ticket.id} » ${ticket.subject}`" />
        <PageBreadcrumb
            :title="`Ticket #${ticket.id}`"
            subtitle="Support Tickets"
            subtitle-url="/support"
        />

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <BButton variant="link" class="p-0 me-2" @click="back">
                <Icon icon="arrow-left" class="me-1" />
                Zurück
            </BButton>
            <h4 class="mb-0 flex-grow-1 text-truncate">Ticket #{{ ticket.id }} » {{ ticket.subject }}</h4>
            <Link v-if="ticket.status !== 'closed' && ticket.status !== 'resolved'" :href="support.index().url">
                <BButton size="sm" variant="outline-secondary">Zurück zur Liste</BButton>
            </Link>
        </div>

        <BRow>
            <BCol xl="3" class="mb-4">
                <BCard class="mb-3 border-danger">
                    <BCardBody class="d-flex gap-3 align-items-start">
                        <div
                            class="rounded bg-danger bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                            style="width: 2.75rem; height: 2.75rem"
                        >
                            <span class="d-inline-flex align-items-center justify-content-center lh-0 text-danger">
                                <Icon icon="home" />
                            </span>
                        </div>
                        <div>
                            <h6 class="fw-semibold text-danger">Verzögerter Support in der Nacht</h6>
                            <p class="small text-muted mb-0">Bearbeitung kann in der Nacht länger dauern.</p>
                        </div>
                    </BCardBody>
                </BCard>
                <BCard class="mb-3">
                    <BCardHeader class="py-2 d-flex align-items-center gap-2">
                        <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center lh-0 text-body-secondary">
                            <Icon icon="activity" />
                        </span>
                        <span>Ticket Status</span>
                    </BCardHeader>
                    <BCardBody class="py-3">
                        <p class="fw-semibold mb-1">{{ statusLabel }}</p>
                        <p class="small text-muted mb-0">Zuletzt aktualisiert: {{ formatDateTime(ticket.updated_at) }}</p>
                    </BCardBody>
                </BCard>
                <BCard>
                    <BCardHeader class="py-2 d-flex align-items-center gap-2">
                        <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center lh-0 text-body-secondary">
                            <Icon icon="info-circle" />
                        </span>
                        <span>Informationen</span>
                    </BCardHeader>
                    <BCardBody class="py-3">
                        <p class="small mb-2">
                            <span class="fw-semibold">Betroffener Dienst:</span><br />
                            <template v-if="affectedServices?.length">
                                <ul class="list-unstyled mb-0 ps-2">
                                    <li v-for="(svc, idx) in affectedServices" :key="`${svc.type}-${svc.id}-${idx}`">{{ svc.label }}</li>
                                </ul>
                            </template>
                            <template v-else>{{ serviceName }}</template>
                        </p>
                        <p class="small mb-2"><span class="fw-semibold">Kategorie:</span> {{ ticketCategory?.name ?? '–' }}</p>
                        <p class="small mb-0"><span class="fw-semibold">Priorität:</span> {{ ticketPriority?.name ?? '–' }}</p>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol xl="9">
                <div ref="ticketMessagesRef" class="ticket_messages relative space-y-6">
                    <div
                        v-for="item in timeline"
                        :key="item.type === 'message' ? `msg-${(item.data as Message).id}` : (item.data as StatusChange).id"
                        class="ticket_message_row flex gap-4"
                    >
                        <div class="relative hidden shrink-0 md:block md:w-12" style="z-index: 1">
                            <div
                                class="absolute inset-0 h-12 w-12 rounded-2xl bg-gray-50 dark:bg-gray-950"
                                aria-hidden="true"
                            />
                            <div
                                v-if="item.type === 'message'"
                                class="relative d-flex align-items-center justify-content-center h-12 w-12"
                            >
                                <UserAvatarOrInitials
                                    :name="(item.data as Message).user?.name ?? '?'"
                                    :src="(item.data as Message).user?.avatar ?? null"
                                    :size="48"
                                    rounded-class="rounded-3"
                                />
                            </div>
                            <div
                                v-else
                                class="relative d-flex align-items-center justify-content-center h-12 w-12 rounded-3 border border-2 border-secondary border-dashed text-muted"
                            >
                                <Icon icon="lock" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <BCard v-if="item.type === 'message'" class="mb-0">
                                <BCardHeader class="py-2 d-flex justify-content-between align-items-center">
                                    <span>
                                        {{ (item.data as Message).user.name }}
                                        <BBadge v-if="(item.data as Message).sent_via_admin" variant="warning" class="ms-2">Mitarbeiter</BBadge>
                                    </span>
                                    <span class="small text-muted">{{ formatMessageDate((item.data as Message).created_at) }}</span>
                                </BCardHeader>
                                <BCardBody class="py-2">
                                    <template v-if="!(item.data as Message).is_hidden">
                                        <div
                                            v-if="isHtml((item.data as Message).body)"
                                            class="ticket-message-body small"
                                            v-html="sanitizeHtml((item.data as Message).body ?? '')"
                                        />
                                        <div v-else class="small whitespace-pre-wrap">{{ (item.data as Message).body }}</div>
                                        <div v-if="(item.data as Message).attachments?.length" class="mt-2 d-flex flex-wrap gap-2">
                                            <a
                                                v-for="att in (item.data as Message).attachments"
                                                :key="att.id"
                                                :href="att.download_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                <Icon icon="file-text" class="me-1" />
                                                {{ att.name }}
                                            </a>
                                        </div>
                                    </template>
                                    <p v-else class="small fst-italic text-muted mb-0">[ Interne Notiz – nur für Support sichtbar ]</p>
                                </BCardBody>
                            </BCard>
                            <BCard v-else class="mb-0 border-secondary">
                                <BCardBody class="py-2 d-flex justify-content-between align-items-center">
                                    <span class="small">{{ (item.data as StatusChange).label }}</span>
                                    <span class="small text-muted">{{ formatMessageDate((item.data as StatusChange).created_at) }}</span>
                                </BCardBody>
                            </BCard>
                        </div>
                    </div>
                </div>

                <BCard v-if="ticket.status !== 'closed' && ticket.status !== 'resolved'" class="mt-4 border-primary">
                    <BCardHeader class="py-2 d-flex align-items-center justify-content-between">
                        <span>Neue Antwort verfassen</span>
                        <BButton variant="link" size="sm" class="p-0" @click="replyCardFullscreen = !replyCardFullscreen">
                            <Icon :icon="replyCardFullscreen ? 'minimize-2' : 'maximize-2'" />
                        </BButton>
                    </BCardHeader>
                    <form @submit.prevent="submitMessage">
                        <BCardBody>
                            <BFormGroup>
                                <BFormTextarea
                                    v-model="form.body"
                                    rows="4"
                                    placeholder="Deine Nachricht..."
                                    :class="{ 'is-invalid': form.errors.body }"
                                />
                                <div v-if="form.errors.body" class="invalid-feedback d-block">{{ form.errors.body }}</div>
                            </BFormGroup>
                            <div v-if="attachmentFiles.length > 0" class="d-flex flex-wrap gap-2 mb-2">
                                <div
                                    v-for="(file, index) in attachmentFiles"
                                    :key="index"
                                    class="d-inline-flex align-items-center gap-2 border rounded px-2 py-1 small"
                                >
                                    <span class="text-truncate" style="max-width: 180px;">{{ file.name }}</span>
                                    <BButton type="button" variant="link" size="sm" class="p-0 text-danger" @click="removeAttachment(index)">
                                        <Icon icon="x" />
                                    </BButton>
                                </div>
                            </div>
                        </BCardBody>
                        <BCardFooter class="d-flex flex-wrap justify-content-between gap-2 py-2">
                            <div>
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    class="d-none"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                                    multiple
                                    @change="onFileChange"
                                />
                                <BButton type="button" variant="outline-secondary" size="sm" @click="triggerFileInput">
                                    <Icon icon="paperclip" class="me-1" />
                                    Datei anhängen
                                </BButton>
                            </div>
                            <BButton type="submit" variant="primary" :disabled="sending">
                                Nachricht senden
                            </BButton>
                        </BCardFooter>
                    </form>
                </BCard>
            </BCol>
        </BRow>
    </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import {
    BRow,
    BCol,
    BCard,
    BCardHeader,
    BCardBody,
    BCardFooter,
    BButton,
    BBadge,
    BFormGroup,
    BFormTextarea,
} from 'bootstrap-vue-next';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import PageBreadcrumb from '@/components/PageBreadcrumb.vue';
import Icon from '@/components/wrappers/Icon.vue';
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue';
import { sanitizeHtml, isHtml } from '@/lib/sanitize';
import support from '@/routes/support';

type Ticket = { id: number; uuid: string; subject: string; status: string; created_at: string; updated_at: string };
type TicketCategory = { id: number; name: string; slug: string } | null;
type TicketPriority = { id: number; name: string; slug: string; color: string | null } | null;
type Site = { uuid: string; name: string; slug: string } | null;
type MessageAttachment = { id: number; name: string; download_url: string };

type Message = {
    id: number;
    body: string | null;
    is_internal: boolean;
    is_hidden?: boolean;
    sent_via_admin?: boolean;
    created_at: string;
    user: { id: number; name: string; avatar?: string };
    attachments?: MessageAttachment[];
};

type StatusChange = { id: string; type: string; created_at: string; label: string };
type AffectedService = { type: string; id: number; label: string };

const props = defineProps<{
    ticket: Ticket;
    ticketCategory: TicketCategory;
    ticketPriority: TicketPriority;
    site: Site;
    statusLabel: string;
    serviceName: string;
    affectedServices?: AffectedService[];
    messages: Message[];
    statusChanges?: StatusChange[];
}>();

const timeline = computed(() => {
    const items: { sort_at: string; type: 'message' | 'status_change'; data: Message | StatusChange }[] = [];
    (props.messages ?? []).forEach((msg) => {
        items.push({ sort_at: msg.created_at, type: 'message', data: msg });
    });
    (props.statusChanges ?? []).forEach((sc) => {
        items.push({ sort_at: sc.created_at, type: 'status_change', data: sc });
    });
    items.sort((a, b) => new Date(a.sort_at).getTime() - new Date(b.sort_at).getTime());
    return items;
});

function formatDateTime(iso: string): string {
    const d = new Date(iso);
    const date = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const time = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false });
    return `${date} um ${time} Uhr`;
}

function formatMessageDate(iso: string): string {
    const d = new Date(iso);
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' Uhr';
}

const form = useForm({ body: '' });
const replyCardFullscreen = ref(false);
const ticketMessagesRef = ref<HTMLElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const attachmentFiles = ref<File[]>([]);
const sending = ref(false);

function triggerFileInput() {
    fileInputRef.value?.click();
}

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    const maxSize = 4 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp', 'application/pdf'];
    for (const file of Array.from(input.files)) {
        if (file.size > maxSize) continue;
        if (!allowedTypes.includes(file.type)) continue;
        attachmentFiles.value.push(file);
    }
    input.value = '';
}

function removeAttachment(index: number) {
    attachmentFiles.value.splice(index, 1);
}

function submitMessage() {
    sending.value = true;
    const formData = new FormData();
    formData.append('body', form.body);
    attachmentFiles.value.forEach((file) => formData.append('attachments[]', file));
    router.post(support.messages.store(props.ticket.uuid).url, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            attachmentFiles.value = [];
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

function fixTicketMessageHeight(): void {
    if (ticketMessagesRef.value) {
        const h = ticketMessagesRef.value.offsetHeight + 45;
        ticketMessagesRef.value.style.setProperty('--ticket-message-height', `${h}px`);
    }
}

watch(
    () => [props.messages, props.statusChanges],
    () => nextTick(fixTicketMessageHeight),
    { deep: true },
);

onMounted(() => {
    nextTick(() => {
        fixTicketMessageHeight();
        requestAnimationFrame(fixTicketMessageHeight);
    });
});

function back() {
    router.visit(support.index().url);
}
</script>

<style scoped>
.ticket_messages::before {
    border-left: 2px dashed gray;
    content: '';
    position: absolute;
    height: var(--ticket-message-height, 0);
    margin-left: 23px;
    margin-top: 5px;
    top: 0;
    left: 0;
    pointer-events: none;
    z-index: 0;
}

.ticket_message_row {
    position: relative;
    z-index: 1;
}

:root.dark .ticket_messages::before {
    border-left-color: rgb(75 85 99);
}
</style>
