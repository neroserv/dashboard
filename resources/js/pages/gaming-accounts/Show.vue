<template>
  <DefaultLayout>
    <Head :title="displayServerName" />
    <PageBreadcrumb
      :title="displayServerName"
      subtitle="Meine Game Server"
      subtitle-url="/gaming-accounts"
    />

    <BAlert v-if="flash.success" variant="success" show dismissible class="mb-3">{{ flash.success }}</BAlert>
    <BAlert v-if="flash.error" variant="danger" show dismissible class="mb-3">{{ flash.error }}</BAlert>
    <BAlert v-if="copyFeedback" variant="info" show class="mb-3 py-2">In die Zwischenablage kopiert.</BAlert>

    <div v-if="!gameServerAccount" class="alert alert-warning">
      Server-Daten werden geladen oder der Server wurde nicht gefunden.
    </div>

    <BRow v-else>
      <BCol lg="4" class="mb-4">
        <BCard no-body>
          <BCardBody class="text-center">
            <BBadge :variant="statusVariant" class="mb-2">{{ displayStatus }}</BBadge>
            <div class="mb-2 d-flex justify-content-center">
              <Icon icon="device-gamepad-2" class="fs-1 text-muted" />
            </div>
            <h5 class="mb-1">{{ displayServerName }}</h5>
            <p class="text-muted small mb-0">Game-Server</p>
            <p class="text-muted small mb-0">{{ planLabel }}</p>
            <div class="border rounded p-2 mt-3 bg-light">
              <span class="text-muted small">Läuft bis</span>
              <div class="fw-semibold">{{ formatDate(periodEnd) }}</div>
            </div>
            <div class="d-flex flex-column gap-2 mt-3">
              <a
                v-if="loginUrl && isOnline && !isSuspendedOrExpired"
                :href="loginUrl"
                target="_blank"
                rel="noopener"
                class="btn btn-primary"
              >
                <Icon icon="external-link" class="me-2" />
                Zum Panel verbinden
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
        <BCard class="mb-3" no-body>
          <BCardBody>
            <h6 class="mb-3 text-muted text-uppercase small">Übersicht</h6>
            <BTable :items="overviewRows" :fields="overviewFields" small stacked>
              <template #cell(value)="{ item }">
                <span v-if="item.key === 'allocation'" class="d-flex align-items-center gap-1">
                  <code class="small">{{ item.value }}</code>
                  <BButton v-if="item.value" size="sm" variant="outline-secondary" class="py-0 px-1" @click="copyToClipboard(item.value)">
                    <Icon icon="copy" />
                  </BButton>
                </span>
                <span v-else>{{ item.value }}</span>
              </template>
            </BTable>
            <div v-if="displayOverviewUsage" class="row g-3 mt-2 mb-3">
              <div class="col-6 col-md">
                <div class="rounded border bg-light p-2">
                  <span class="text-muted small">CPU</span>
                  <div class="fw-semibold small">{{ formatCpu(displayOverviewUsage.cpu_absolute) }}</div>
                </div>
              </div>
              <div class="col-6 col-md">
                <div class="rounded border bg-light p-2">
                  <span class="text-muted small">RAM</span>
                  <div class="fw-semibold small">{{ formatBytes(displayOverviewUsage.memory_bytes) }}</div>
                </div>
              </div>
              <div class="col-6 col-md">
                <div class="rounded border bg-light p-2">
                  <span class="text-muted small">Disk</span>
                  <div class="fw-semibold small">{{ formatBytes(displayOverviewUsage.disk_bytes) }}</div>
                </div>
              </div>
              <div class="col-6 col-md" v-if="displayOverviewNetwork">
                <div class="rounded border bg-light p-2">
                  <span class="text-muted small">Network</span>
                  <div class="fw-semibold small">↓ {{ formatBytes(displayOverviewNetwork.rx) }} / ↑ {{ formatBytes(displayOverviewNetwork.tx) }}</div>
                </div>
              </div>
              <div class="col-6 col-md" v-if="displayOverviewPlayers">
                <div class="rounded border bg-light p-2">
                  <span class="text-muted small">Spieler</span>
                  <div class="fw-semibold small">{{ displayOverviewPlayers }}</div>
                </div>
              </div>
            </div>
            <div v-if="!isSuspendedOrExpired" class="d-flex flex-wrap align-items-center gap-2 mt-3">
              <BButton
                size="sm"
                variant="success"
                class="d-inline-flex align-items-center gap-1 px-2 py-1"
                :disabled="isOnline || powerLoading !== null"
                @click="sendPower('start')"
              >
                <Icon icon="player-play" class="fs-6" />
                Start
              </BButton>
              <BButton
                size="sm"
                variant="outline-warning"
                class="d-inline-flex align-items-center gap-1 px-2 py-1"
                :disabled="powerLoading !== null"
                @click="sendPower('restart')"
              >
                <Icon icon="refresh" class="fs-6" />
                Restart
              </BButton>
              <BButton
                size="sm"
                variant="danger"
                class="d-inline-flex align-items-center gap-1 px-2 py-1"
                :disabled="!isOnline || powerLoading !== null"
                @click="sendPower('stop')"
              >
                <Icon icon="square" class="fs-6" />
                Stop
              </BButton>
              <BButton
                size="sm"
                variant="outline-danger"
                class="d-inline-flex align-items-center gap-1 px-2 py-1"
                :disabled="!isOnline || powerLoading !== null"
                @click="sendPower('kill')"
              >
                <Icon icon="ban" class="fs-6" />
                Kill
              </BButton>
            </div>
          </BCardBody>
        </BCard>

        <BNav tabs class="mb-3 flex-wrap">
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'console'" @click="activeTab = 'console'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="terminal" />
              </span>
              <span class="d-inline-flex align-items-center">Konsole</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'files'" @click="activeTab = 'files'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="folder" />
              </span>
              <span class="d-inline-flex align-items-center">Dateien</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'backups'" @click="activeTab = 'backups'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="archive" />
              </span>
              <span class="d-inline-flex align-items-center">Backups</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'databases'" @click="activeTab = 'databases'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="database" />
              </span>
              <span class="d-inline-flex align-items-center">Datenbank</span>
            </span>
          </BNavItem>
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'schedules'" @click="activeTab = 'schedules'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="calendar" />
              </span>
              <span class="d-inline-flex align-items-center">Schedules</span>
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
          <BNavItem link-class="d-inline-flex align-items-center" :active="activeTab === 'rename'" @click="activeTab = 'rename'">
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="pencil" />
              </span>
              <span class="d-inline-flex align-items-center">Umbenennen</span>
            </span>
          </BNavItem>
          <BNavItem
            v-if="connectDomainShowUrl || (subdomainUpdateUrl && subdomainSuffix)"
            link-class="d-inline-flex align-items-center"
            :active="activeTab === 'domain'"
            @click="activeTab = 'domain'"
          >
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="world" />
              </span>
              <span class="d-inline-flex align-items-center">Domain</span>
            </span>
          </BNavItem>
          <BNavItem
            v-if="isCloudAccount && cloudResourcesUpdateUrl && !isSuspendedOrExpired"
            link-class="d-inline-flex align-items-center"
            :active="activeTab === 'ressourcen'"
            @click="activeTab = 'ressourcen'"
          >
            <span class="d-inline-flex align-items-center gap-2 text-nowrap lh-1">
              <span class="d-inline-flex flex-shrink-0 align-items-center justify-content-center" style="line-height: 0">
                <Icon icon="cpu" />
              </span>
              <span class="d-inline-flex align-items-center">Ressourcen</span>
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

        <!-- Konsole -->
        <GamingAccountConsoleTab
          v-if="activeTab === 'console' && gameServerAccount?.uuid"
          :game-server-account-id="gameServerAccount.uuid"
        />
        <BCard v-else-if="activeTab === 'console'" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">Die Live-Konsole ist im Pterodactyl-Panel verfügbar.</p>
            <a v-if="loginUrl && !isSuspendedOrExpired" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
              <Icon icon="external-link" class="me-1" /> Zum Panel (Konsole)
            </a>
          </BCardBody>
        </BCard>

        <!-- Dateien -->
        <GamingAccountFilesTab
          v-if="activeTab === 'files' && gameServerAccount?.uuid"
          :game-server-account-id="gameServerAccount.uuid"
          :login-url="loginUrl"
        />
        <BCard v-else-if="activeTab === 'files'" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">Dateiverwaltung und Editor sind im Pterodactyl-Panel verfügbar.</p>
            <a v-if="loginUrl && !isSuspendedOrExpired" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
              <Icon icon="external-link" class="me-1" /> Zum Panel (Dateien)
            </a>
          </BCardBody>
        </BCard>

        <!-- Backups -->
        <GamingAccountBackupsTab
          v-if="activeTab === 'backups' && gameServerAccount?.uuid"
          :game-server-account-id="gameServerAccount.uuid"
        />
        <BCard v-else-if="activeTab === 'backups'" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">Backups erstellen und wiederherstellen können Sie im Pterodactyl-Panel.</p>
            <a v-if="loginUrl && !isSuspendedOrExpired" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
              <Icon icon="external-link" class="me-1" /> Zum Panel (Backups)
            </a>
          </BCardBody>
        </BCard>

        <!-- Datenbank -->
        <GamingAccountDatabasesTab
          v-if="activeTab === 'databases' && gameServerAccount?.uuid"
          :game-server-account-id="gameServerAccount.uuid"
          :phpmyadmin-available="phpmyadminAvailable"
        />
        <BCard v-else-if="activeTab === 'databases'" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">Datenbanken verwalten Sie im Pterodactyl-Panel unter „Databases“.</p>
            <a v-if="loginUrl && !isSuspendedOrExpired" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
              <Icon icon="external-link" class="me-1" /> Zum Panel (Datenbank)
            </a>
          </BCardBody>
        </BCard>

        <!-- Schedules -->
        <GamingAccountSchedulesTab
          v-if="activeTab === 'schedules' && gameServerAccount?.uuid"
          :game-server-account-id="gameServerAccount.uuid"
          :login-url="loginUrl"
        />
        <BCard v-else-if="activeTab === 'schedules'" no-body>
          <BCardBody>
            <p class="text-muted small mb-3">Geplante Aufgaben (Schedules) richten Sie im Pterodactyl-Panel ein.</p>
            <a v-if="loginUrl && !isSuspendedOrExpired" :href="loginUrl" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
              <Icon icon="external-link" class="me-1" /> Zum Panel (Schedules)
            </a>
          </BCardBody>
        </BCard>

        <BCard v-else-if="activeTab === 'access'" no-body>
          <BCardBody>
            <h6 class="mb-3">Zugangsdaten</h6>
            <p class="text-muted small mb-4">
              Name, Identifier und Anmeldedaten für das Pterodactyl-Panel. Alles per Klick kopierbar.
            </p>
            <div class="mb-3">
              <label class="form-label small">Server-Name</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="displayServerName" readonly class="font-monospace flex-grow-1" />
                <BButton
                  size="sm"
                  variant="outline-secondary"
                  :disabled="!copyableServerLabel"
                  @click="copyToClipboard(copyableServerLabel)"
                >
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small">Server-ID (Identifier)</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="gameServerAccount?.identifier ?? '—'" readonly class="font-monospace flex-grow-1" />
                <BButton
                  size="sm"
                  variant="outline-secondary"
                  :disabled="!gameServerAccount?.identifier"
                  @click="copyToClipboard(gameServerAccount!.identifier!)"
                >
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small">E-Mail-Adresse (Panel-Login)</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="userEmail || '—'" readonly class="font-monospace flex-grow-1" />
                <BButton size="sm" variant="outline-secondary" :disabled="!userEmail" @click="copyToClipboard(userEmail)">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small">Panel-URL</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="loginUrl ?? '—'" readonly class="font-monospace flex-grow-1" />
                <BButton size="sm" variant="outline-secondary" :disabled="!loginUrl" @click="copyToClipboard(loginUrl!)">
                  <Icon icon="copy" />
                </BButton>
              </div>
              <p v-if="!loginUrl" class="text-muted small mt-1 mb-0">
                Die Panel-URL steht zur Verfügung, sobald der Server bereitgestellt ist (Identifier im Panel).
              </p>
            </div>
            <div class="mb-3">
              <label class="form-label small">Server-Adresse</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="displayAllocation ?? '—'" readonly class="font-monospace flex-grow-1" />
                <BButton size="sm" variant="outline-secondary" :disabled="!displayAllocation" @click="copyToClipboard(displayAllocation!)">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div v-if="gameServerAccount?.allocation?.subdomain" class="mb-3">
              <label class="form-label small">Subdomain</label>
              <div class="d-flex gap-2">
                <BFormInput :model-value="gameServerAccount.allocation.subdomain" readonly class="font-monospace flex-grow-1" />
                <BButton size="sm" variant="outline-secondary" @click="copyToClipboard(gameServerAccount.allocation!.subdomain!)">
                  <Icon icon="copy" />
                </BButton>
              </div>
            </div>
            <div class="pt-2 border-top">
              <a
                v-if="loginUrl && !isSuspendedOrExpired"
                :href="loginUrl"
                target="_blank"
                rel="noopener"
                class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"
              >
                <Icon icon="external-link" />
                Im Panel anmelden
              </a>
              <p v-else-if="isSuspendedOrExpired" class="text-muted small mb-0">Server gesperrt. Bitte verlängern Sie, um das Panel zu nutzen.</p>
            </div>
            <div class="mt-4 pt-3 border-top">
              <h6 class="mb-2">Passwort</h6>
              <p class="text-muted small mb-2">
                Ihr Panel-Passwort können Sie im Pterodactyl-Panel unter „Zugangsdaten“ oder „Passwort“ anzeigen und ändern.
              </p>
              <a
                v-if="loginUrl && !isSuspendedOrExpired"
                :href="loginUrl"
                target="_blank"
                rel="noopener"
                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
              >
                <Icon icon="external-link" />
                Panel öffnen (Passwort verwalten)
              </a>
            </div>
          </BCardBody>
        </BCard>

        <!-- Umbenennen -->
        <BCard v-else-if="activeTab === 'rename'" no-body>
          <BCardBody>
            <h6 class="mb-2">Server umbenennen</h6>
            <p class="text-muted small mb-3">
              Den Anzeigenamen ändern Sie im Pterodactyl-Panel unter Server-Einstellungen („Settings“ / Name).
            </p>
            <div class="mb-3">
              <label class="form-label small">Aktueller Name</label>
              <BFormInput :model-value="displayServerName" readonly />
              <p v-if="!String(gameServerAccount?.name ?? '').trim()" class="text-muted small mt-1 mb-0">
                In der Datenbank ist noch kein Name hinterlegt — im Panel können Sie einen festen Anzeigenamen setzen.
              </p>
            </div>
            <a
              v-if="loginUrl && !isSuspendedOrExpired"
              :href="loginUrl"
              target="_blank"
              rel="noopener"
              class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
            >
              <Icon icon="pencil" />
              Im Panel umbenennen
            </a>
            <p v-else-if="isSuspendedOrExpired" class="text-muted small mb-0">Server gesperrt. Bitte verlängern Sie, um das Panel zu nutzen.</p>
            <p v-else-if="!loginUrl" class="text-muted small mb-0">Panel-Link folgt, sobald der Server bereitgestellt ist.</p>
          </BCardBody>
        </BCard>

        <BCard v-else-if="activeTab === 'domain' && (connectDomainShowUrl || (subdomainUpdateUrl && subdomainSuffix))" no-body>
          <BCardBody>
            <p class="text-muted small mb-4">
              Mit einer eigenen Domain wirkt dein Server professioneller (z. B. mc.deinedomain.de).
            </p>
            <div v-if="connectDomainShowUrl" class="mb-4">
              <Link :href="connectDomainShowUrl" class="btn btn-primary btn-sm">Eigene Domain verbinden</Link>
            </div>
            <div v-if="subdomainUpdateUrl && subdomainSuffix" class="border-top pt-3">
              <h6 class="mb-2">Subdomain ändern</h6>
              <p class="text-muted small mb-2">Aktuelle Subdomain: {{ gameServerAccount?.allocation?.subdomain ?? '–' }}</p>
              <div class="d-flex flex-wrap align-items-end gap-2">
                <div>
                  <label class="form-label small">Neue Subdomain</label>
                  <div class="d-flex align-items-center gap-1">
                    <BFormInput v-model="subdomainPart" type="text" placeholder="mein-server" class="w-auto" maxlength="32" />
                    <span class="text-muted small">{{ subdomainSuffix }}</span>
                  </div>
                </div>
                <BButton size="sm" variant="secondary" :disabled="subdomainCheckLoading" @click="checkSubdomainAvailability">
                  Prüfen ob frei
                </BButton>
                <BButton size="sm" :disabled="subdomainUpdateLoading || !subdomainPart.trim()" @click="submitSubdomainChange">
                  Subdomain ändern
                </BButton>
              </div>
              <p
                v-if="subdomainCheckResult"
                class="mt-2 small mb-0"
                :class="subdomainCheckResult.available ? 'text-success' : 'text-danger'"
              >
                {{ subdomainCheckResult.available ? (subdomainCheckResult.message ?? 'Subdomain ist frei.') : (subdomainCheckResult.error ?? 'Subdomain vergeben.') }}
              </p>
            </div>
          </BCardBody>
        </BCard>

        <!-- Ressourcen (Cloud) -->
        <BCard v-else-if="activeTab === 'ressourcen' && isCloudAccount && cloudResourcesUpdateUrl && !isSuspendedOrExpired" no-body>
          <BCardBody>
            <h6 class="mb-2">Ressourcen anpassen</h6>
            <p class="text-muted small mb-4">
              CPU, RAM und Speicher innerhalb Ihres Abo-Kontingents anpassen.
            </p>
            <BForm @submit.prevent="submitResources" class="d-flex flex-wrap align-items-end gap-3">
              <div>
                <label class="form-label small">CPU (%)</label>
                <BFormInput
                  v-model.number="resourcesForm.cpu"
                  type="number"
                  min="0"
                  :max="resourcesMax.cpu"
                  step="1"
                  class="w-auto"
                  style="width: 6rem"
                />
              </div>
              <div>
                <label class="form-label small">RAM (MB)</label>
                <BFormInput
                  v-model.number="resourcesForm.memory_mb"
                  type="number"
                  min="64"
                  :max="resourcesMax.memory_mb"
                  step="64"
                  class="w-auto"
                  style="width: 7rem"
                />
              </div>
              <div>
                <label class="form-label small">Speicher (MB)</label>
                <BFormInput
                  v-model.number="resourcesForm.disk_mb"
                  type="number"
                  min="256"
                  :max="resourcesMax.disk_mb"
                  step="256"
                  class="w-auto"
                  style="width: 7rem"
                />
              </div>
              <BButton type="submit" :disabled="resourcesSubmitting">
                <Icon v-if="resourcesSubmitting" icon="loader-2" class="me-1" />
                Übernehmen
              </BButton>
            </BForm>
          </BCardBody>
        </BCard>

        <!-- Teilen -->
        <ProductSharingCard
          v-else-if="activeTab === 'sharing' && canManageCollaborators"
          :product-shares="productShares ?? []"
          :product-invitations="productInvitations ?? []"
          :allowed-share-permissions="allowedSharePermissions ?? []"
          :store-invitation-url="storeInvitationUrl ?? ''"
        />
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
      v-if="showAutoRenewButton && gameServerAccount"
      :open="autoRenewModalOpen"
      :balance-url="`/gaming-accounts/${gameServerAccount.uuid}/auto-renew-balance`"
      :mollie-url="`/gaming-accounts/${gameServerAccount.uuid}/auto-renew-mollie-subscription`"
      :mollie-cancel-url="`/gaming-accounts/${gameServerAccount.uuid}/subscription/cancel`"
      :auto-renew-with-balance="autoRenewWithBalance"
      :has-mollie-subscription="hasMollieSubscription"
      @update:open="autoRenewModalOpen = $event"
    />
  </DefaultLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import {
  BAlert,
  BButton,
  BCard,
  BCardBody,
  BCol,
  BForm,
  BFormInput,
  BFormRadio,
  BFormSelect,
  BModal,
  BNav,
  BNavItem,
  BRow,
  BTable,
  BBadge,
} from 'bootstrap-vue-next'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import PageBreadcrumb from '@/components/PageBreadcrumb.vue'
import Icon from '@/components/wrappers/Icon.vue'
import {
  GamingAccountConsoleTab,
  GamingAccountFilesTab,
  GamingAccountBackupsTab,
  GamingAccountDatabasesTab,
  GamingAccountSchedulesTab,
} from '@/components/gaming-accounts'
import ProductSharingCard from '@/components/product-sharing/ProductSharingCard.vue'
import AutoRenewModal from '@/components/AutoRenewModal.vue'

type ServerOverview = {
  status?: string
  suspended?: boolean
  allocation?: string
  usage?: { cpu_absolute?: number; memory_bytes?: number; disk_bytes?: number; network_rx_bytes?: number; network_tx_bytes?: number }
  limits?: { memory?: number; disk?: number; cpu?: number }
  server_query?: { num_players?: number; max_players?: number }
}
type GameServerAccount = {
  uuid: string
  name?: string | null
  status: string
  identifier?: string | null
  current_period_ends_at: string | null
  cancel_at_period_end?: boolean
  hosting_plan?: { name: string }
  allocation?: { subdomain?: string; cpu?: number; memory_mb?: number; disk_mb?: number; [key: string]: unknown }
}
type GameserverCloudSubscription = {
  current_period_ends_at: string | null
  plan: { name: string }
  remaining_cpu?: number
  remaining_memory_mb?: number
  remaining_disk_mb?: number
}

const props = withDefaults(
  defineProps<{
    gameServerAccount: GameServerAccount | null
    loginUrl: string | null
    userEmail?: string
    serverOverview: ServerOverview | null
    canRenew?: boolean
    renewalAmount?: number
    canPayWithBalance?: boolean
    customerBalance?: number
    renewUrl?: string
    autoRenewWithBalance?: boolean
    hasMollieSubscription?: boolean
    isSuspendedOrExpired?: boolean
    connectDomainShowUrl?: string
    domainsSearchUrl?: string | null
    subdomainCheckUrl?: string | null
    subdomainUpdateUrl?: string | null
    subdomainSuffix?: string | null
    currentSubdomainPart?: string | null
    gameserverCloudSubscription?: GameserverCloudSubscription | null
    cloudResourcesUpdateUrl?: string | null
    canManageCollaborators?: boolean
    phpmyadminAvailable?: boolean
    productShares?: Array<{ id: number; user: { id: number; name: string; email: string } | null; permissions: string[]; update_url: string; destroy_url: string }>
    productInvitations?: Array<{ id: number; email: string; permissions: string[]; expires_at: string | null; destroy_url: string }>
    allowedSharePermissions?: string[]
    storeInvitationUrl?: string | null
  }>(),
  {
    userEmail: '',
    canRenew: false,
    renewalAmount: 0,
    canPayWithBalance: false,
    customerBalance: 0,
    renewUrl: '',
    autoRenewWithBalance: false,
    hasMollieSubscription: false,
    isSuspendedOrExpired: false,
    connectDomainShowUrl: '',
    domainsSearchUrl: null,
    subdomainCheckUrl: null,
    subdomainUpdateUrl: null,
    subdomainSuffix: null,
    currentSubdomainPart: null,
    gameserverCloudSubscription: null,
    cloudResourcesUpdateUrl: null,
    canManageCollaborators: false,
    phpmyadminAvailable: false,
    productShares: () => [],
    productInvitations: () => [],
    allowedSharePermissions: () => [],
    storeInvitationUrl: null,
  },
)

const page = usePage()

const activeTab = ref('console')
const renewModalOpen = ref(false)
const autoRenewModalOpen = ref(false)

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

const renewalAmount = computed(() => props.renewalAmount ?? 0)
const renewPeriodMonths = ref(1)
const paymentMethod = ref<'balance' | 'mollie'>('mollie')
const periodMonthOptions = [
  { value: 1, text: '1 Monat' },
  { value: 3, text: '3 Monate' },
  { value: 6, text: '6 Monate' },
  { value: 12, text: '12 Monate' },
]
const totalRenewAmount = computed(
  () => Math.round(renewalAmount.value * renewPeriodMonths.value * 100) / 100,
)

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

const powerLoading = ref<string | null>(null)
const liveOverview = ref<ServerOverview | null>(props.serverOverview ?? null)
const subdomainPart = ref(props.currentSubdomainPart ?? '')
const subdomainCheckResult = ref<{ available?: boolean; error?: string; message?: string } | null>(null)
const subdomainCheckLoading = ref(false)
const subdomainUpdateLoading = ref(false)
const resourcesForm = ref({ cpu: 0, memory_mb: 512, disk_mb: 1024 })
const resourcesSubmitting = ref(false)
const copyFeedback = ref(false)
const flash = computed(() => (page.props.flash as { error?: string; success?: string }) ?? {})

const currentAllocation = computed(() => {
  const a = props.gameServerAccount?.allocation
  if (!a || typeof a !== 'object') return { cpu: 0, memory_mb: 512, disk_mb: 1024 }
  return {
    cpu: typeof a.cpu === 'number' ? a.cpu : 0,
    memory_mb: typeof a.memory_mb === 'number' ? a.memory_mb : 512,
    disk_mb: typeof a.disk_mb === 'number' ? a.disk_mb : 1024,
  }
})
const resourcesMax = computed(() => {
  const sub = props.gameserverCloudSubscription
  if (!sub) return { cpu: 0, memory_mb: 512, disk_mb: 1024 }
  const cur = currentAllocation.value
  return {
    cpu: cur.cpu + (sub.remaining_cpu ?? 0),
    memory_mb: cur.memory_mb + (sub.remaining_memory_mb ?? 0),
    disk_mb: cur.disk_mb + (sub.remaining_disk_mb ?? 0),
  }
})

const isCloudAccount = computed(() => !!props.gameserverCloudSubscription)
const planLabel = computed(() =>
  isCloudAccount.value && props.gameserverCloudSubscription
    ? props.gameserverCloudSubscription.plan.name
    : props.gameServerAccount?.hosting_plan?.name ?? '–',
)
const periodEnd = computed(() =>
  isCloudAccount.value && props.gameserverCloudSubscription
    ? props.gameserverCloudSubscription.current_period_ends_at
    : props.gameServerAccount?.current_period_ends_at ?? null,
)
const displayOverview = computed(() => liveOverview.value ?? props.serverOverview ?? null)
const isOnline = computed(() => {
  const s = displayOverview.value?.status?.toLowerCase()
  return s === 'running' || s === 'started'
})
const isSuspendedOrExpired = computed(() => props.isSuspendedOrExpired ?? false)
const displayStatus = computed(() => {
  if (isSuspendedOrExpired.value) return 'Gesperrt'
  const s = displayOverview.value?.status
  if (s?.toLowerCase() === 'running' || s?.toLowerCase() === 'started') return 'Online'
  if (s?.toLowerCase() === 'stopping' || s?.toLowerCase() === 'starting') return s
  if (displayOverview.value?.suspended) return 'Gesperrt'
  return displayOverview.value?.status ?? props.gameServerAccount?.status ?? '–'
})
const statusVariant = computed((): 'success' | 'secondary' | 'danger' => {
  if (isSuspendedOrExpired.value || displayOverview.value?.suspended) return 'danger'
  const s = displayOverview.value?.status?.toLowerCase()
  if (s === 'running' || s === 'started') return 'success'
  return 'secondary'
})
const displayAllocation = computed(() => displayOverview.value?.allocation ?? null)

const displayServerName = computed(() => {
  const n = String(props.gameServerAccount?.name ?? '').trim()
  if (n !== '') {
    return n
  }
  const id = props.gameServerAccount?.identifier
  if (id) {
    return id
  }
  return 'Game Server'
})

const copyableServerLabel = computed(() => {
  const n = String(props.gameServerAccount?.name ?? '').trim()
  if (n !== '') {
    return n
  }
  return String(props.gameServerAccount?.identifier ?? '').trim()
})

const overviewFields = [{ key: 'label', label: '' }, { key: 'value', label: '' }]
const overviewRows = computed(() => {
  const o = displayOverview.value
  const acc = props.gameServerAccount
  return [
    { key: 'allocation', label: 'Adresse', value: o?.allocation ?? '–' },
    { key: 'status', label: 'Status', value: o?.status ?? acc?.status ?? '–' },
    { key: 'plan', label: 'Paket', value: planLabel.value },
  ]
})

const displayOverviewUsage = computed(() => {
  const u = displayOverview.value?.usage
  if (!u) return null
  return {
    cpu_absolute: u.cpu_absolute ?? 0,
    memory_bytes: u.memory_bytes ?? 0,
    disk_bytes: u.disk_bytes ?? 0,
  }
})
const displayOverviewNetwork = computed(() => {
  const u = displayOverview.value?.usage
  if (!u || (u.network_rx_bytes == null && u.network_tx_bytes == null)) return null
  return { rx: u.network_rx_bytes ?? 0, tx: u.network_tx_bytes ?? 0 }
})
const displayOverviewPlayers = computed(() => {
  const q = displayOverview.value?.server_query
  if (!q || q.max_players == null || q.max_players <= 0) return null
  return `${q.num_players ?? 0} / ${q.max_players}`
})

function formatDate(d: string | null | undefined): string {
  return d ? new Date(d).toLocaleDateString('de-DE', { timeZone: 'UTC' }) : '–'
}
function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}
function formatCpu(cpuAbsolute: number): string {
  if (cpuAbsolute <= 0) return '0%'
  return `${Math.round(cpuAbsolute * 100)}%`
}

function copyToClipboard(text: string) {
  if (!text) return
  navigator.clipboard.writeText(text).then(
    () => {
      copyFeedback.value = true
      setTimeout(() => { copyFeedback.value = false }, 2000)
    },
    () => {},
  )
}

function sendPower(action: 'start' | 'stop' | 'restart' | 'kill') {
  if (!props.gameServerAccount?.uuid) return
  powerLoading.value = action
  const prev = displayOverview.value
  if (prev && (action === 'stop' || action === 'restart' || action === 'kill')) {
    liveOverview.value = { ...prev, status: 'stopping' }
  } else if (prev && action === 'start') {
    liveOverview.value = { ...prev, status: 'starting' }
  }
  router.post(`/gaming-accounts/${props.gameServerAccount.uuid}/power`, { action }, {
    preserveScroll: true,
    onSuccess: () => {
      powerLoading.value = null
      fetchOverview()
    },
    onError: () => {
      powerLoading.value = null
      liveOverview.value = props.serverOverview ?? null
    },
    onFinish: () => {
      powerLoading.value = null
    },
  })
}

function fetchOverview() {
  if (!props.gameServerAccount?.uuid) return
  fetch(`/gaming-accounts/${props.gameServerAccount.uuid}/overview`, {
    method: 'GET',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then((res) => {
      if (!res.ok) return
      return res.json()
    })
    .then((data) => {
      if (data?.serverOverview !== undefined) {
        liveOverview.value = data.serverOverview
      }
    })
    .catch(() => {})
}

let overviewPollInterval: ReturnType<typeof setInterval> | null = null
function initResourcesForm() {
  const cur = currentAllocation.value
  resourcesForm.value = { cpu: cur.cpu, memory_mb: cur.memory_mb, disk_mb: cur.disk_mb }
}

function submitResources() {
  if (!props.cloudResourcesUpdateUrl) return
  resourcesSubmitting.value = true
  router.put(props.cloudResourcesUpdateUrl, {
    cpu: resourcesForm.value.cpu,
    memory_mb: resourcesForm.value.memory_mb,
    disk_mb: resourcesForm.value.disk_mb,
  }, {
    preserveScroll: true,
    onFinish: () => { resourcesSubmitting.value = false },
  })
}

onMounted(() => {
  fetchOverview()
  overviewPollInterval = setInterval(fetchOverview, 3000)
  if (props.subdomainUpdateUrl) {
    subdomainPart.value = props.currentSubdomainPart ?? ''
  }
  if (props.cloudResourcesUpdateUrl) {
    initResourcesForm()
  }
})
onUnmounted(() => {
  if (overviewPollInterval) clearInterval(overviewPollInterval)
})
watch(
  () => props.currentSubdomainPart,
  (v) => { subdomainPart.value = v ?? '' },
)

function checkSubdomainAvailability() {
  const part = subdomainPart.value.trim().toLowerCase()
  if (!part || !props.subdomainCheckUrl) return
  if (!/^[a-z0-9-]+$/.test(part) || part.length > 32) {
    subdomainCheckResult.value = { available: false, error: 'Nur Kleinbuchstaben, Ziffern und Bindestriche (max. 32 Zeichen).' }
    return
  }
  subdomainCheckLoading.value = true
  subdomainCheckResult.value = null
  fetch(`${props.subdomainCheckUrl}?subdomain=${encodeURIComponent(part)}`, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then((res) => res.json())
    .then((data) => { subdomainCheckResult.value = data })
    .catch(() => { subdomainCheckResult.value = { available: false, error: 'Prüfung fehlgeschlagen.' } })
    .finally(() => { subdomainCheckLoading.value = false })
}

function submitSubdomainChange() {
  const part = subdomainPart.value.trim().toLowerCase()
  if (!part || !props.subdomainUpdateUrl) return
  if (!/^[a-z0-9-]+$/.test(part) || part.length > 32) return
  subdomainUpdateLoading.value = true
  router.put(props.subdomainUpdateUrl, { subdomain: part }, {
    preserveScroll: true,
    onFinish: () => { subdomainUpdateLoading.value = false },
  })
}
</script>

