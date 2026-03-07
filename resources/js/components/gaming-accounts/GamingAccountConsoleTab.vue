<script setup lang="ts">
import { ref, computed } from 'vue';
import { Terminal, Send, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { notify } from '@/composables/useNotify';
import gamingAccounts from '@/routes/gaming-accounts';

const props = defineProps<{
    gameServerAccountId: number;
}>();

const command = ref('');
const sending = ref(false);
const logLines = ref<string[]>([]);

const api = computed(() => gamingAccounts.api.console);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    const meta = document.querySelector('meta[name="csrf-token"]');
    return (meta && (meta as HTMLMetaElement).content) || '';
}

async function sendCommand() {
    const cmd = command.value.trim();
    if (!cmd || sending.value) return;
    sending.value = true;
    logLines.value.push(`> ${cmd}`);
    command.value = '';
    try {
        const res = await fetch(api.value.command.url(props.gameServerAccountId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ command: cmd }),
        });
        const data = await res.json().catch(() => ({}));
        if (data.success) {
            notify.success('Befehl gesendet.');
        } else {
            notify.error(data.message ?? 'Fehler beim Senden.');
            logLines.value.push(`[Fehler] ${data.message ?? res.statusText}`);
        }
    } catch (e) {
        notify.error('Verbindungsfehler.');
        logLines.value.push(`[Fehler] ${e instanceof Error ? e.message : 'Unbekannt'}`);
    } finally {
        sending.value = false;
    }
}

function clearLog() {
    logLines.value = [];
}
</script>

<template>
    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4">
                <div class="rounded-lg border bg-muted/30 p-3 font-mono text-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="flex items-center gap-2 text-muted-foreground">
                            <Terminal class="h-4 w-4" />
                            Live-Konsole
                        </span>
                        <Button variant="ghost" size="sm" @click="clearLog">
                            Leeren
                        </Button>
                    </div>
                    <div
                        class="max-h-[400px] min-h-[200px] overflow-y-auto whitespace-pre-wrap break-words rounded bg-zinc-900/80 p-3 text-xs text-zinc-200"
                    >
                        <template v-if="logLines.length">
                            <div
                                v-for="(line, i) in logLines"
                                :key="i"
                                class="mb-0.5"
                            >
                                {{ line }}
                            </div>
                        </template>
                        <span v-else class="text-zinc-500">Befehle erscheinen hier nach dem Senden.</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Input
                        v-model="command"
                        placeholder="Befehl eingeben..."
                        class="font-mono"
                        :disabled="sending"
                        @keydown.enter.prevent="sendCommand()"
                    />
                    <Button
                        :disabled="sending || !command.trim()"
                        @click="sendCommand"
                    >
                        <Loader2
                            v-if="sending"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Senden
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
