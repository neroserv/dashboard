# Queue-Worker auf Ubuntu (Laravel)

Damit Jobs aus der Laravel-Queue (z. B. E-Mails, Rechnungen, Cron-Jobs die in die Queue gestellt werden) tatsächlich ausgeführt werden, muss auf dem Server ein **Queue-Worker** laufen. Ohne Worker bleiben Jobs in der Tabelle `jobs` liegen und werden nie abgearbeitet.

## Kurzantwort

**Ja** – du musst auf dem Server einen Worker laufen lassen, z. B. mit:

```bash
php artisan queue:work
```

Für den Produktivbetrieb empfiehlt sich ein Prozess-Manager wie **Supervisor**, damit der Worker dauerhaft läuft und bei Absturz neu startet.

---

## 1. Warum ein Worker nötig ist

- Der **Cron** führt nur `php artisan schedule:run` aus (z. B. jede Minute). Der Scheduler **stellt** Jobs in die Queue, führt sie aber nicht selbst aus.
- Die **Queue** (z. B. Datenbank-Tabelle `jobs`) speichert nur die Liste der Jobs. Ein Prozess muss diese Liste abarbeiten – das ist der **Queue-Worker** (`queue:work`).

Ohne laufenden Worker:
- Wartende Jobs bleiben in „Wartende Jobs“ (Admin → Jobs-Monitor) stecken.
- Geplante Aufgaben (z. B. Rechnungen erstellen, Benachrichtigungen) laufen nicht.

---

## 2. Schnelltest (manuell)

Zum Testen kannst du den Worker einmal im Vordergrund starten:

```bash
cd /pfad/zum/projekt
php artisan queue:work
```

- Mit `Ctrl+C` beendest du ihn. Für Dauerbetrieb und Neustart bei Fehlern solltest du Supervisor nutzen (siehe unten).

---

## 3. Dauerbetrieb mit Supervisor (Ubuntu)

Supervisor startet den Worker als Dienst und startet ihn bei Absturz oder nach Server-Neustart automatisch neu.

### 3.1 Supervisor installieren

```bash
sudo apt update
sudo apt install supervisor
```

### 3.2 Konfigurationsdatei anlegen

Erstelle eine neue Config-Datei (z. B. Name der App):

```bash
sudo nano /etc/supervisor/conf.d/praxishosting-worker.conf
```

Inhalt (Pfad und User anpassen):

```ini
[program:praxishosting-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/praxishosting/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/praxishosting/storage/logs/worker.log
stopwaitsecs=3600
```

**Wichtig:**

- **command:** Vollständiger Pfad zu `artisan` und ggf. Queue-Name, z. B. `queue:work redis --queue=default`.
- **user:** Der User, unter dem PHP/Webserver laufen (z. B. `www-data`).
- **numprocs:** Anzahl paralleler Worker (1 reicht oft; bei viel Last erhöhen).
- **stdout_logfile:** Pfad muss existieren und für `user` schreibbar sein.

### 3.3 Supervisor neu laden und Worker starten

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start praxishosting-worker:*
```

### 3.4 Nützliche Befehle

| Befehl | Bedeutung |
|--------|-----------|
| `sudo supervisorctl status` | Status aller Programme (z. B. RUNNING) |
| `sudo supervisorctl restart praxishosting-worker:*` | Worker neu starten (z. B. nach Deployment) |
| `sudo supervisorctl stop praxishosting-worker:*` | Worker anhalten |

Nach Code-Deployments den Worker einmal neu starten, damit er den neuen Code lädt:

```bash
sudo supervisorctl restart praxishosting-worker:*
```

Oder in einem Deploy-Skript:

```bash
php artisan config:cache
php artisan route:cache
sudo supervisorctl restart praxishosting-worker:*
```

---

## 4. Optionen von `queue:work`

| Option | Bedeutung |
|--------|-----------|
| `--queue=default` | Nur die Queue „default“ abarbeiten |
| `--sleep=3` | Sekunden warten, wenn die Queue leer ist |
| `--tries=3` | Max. Versuche pro Job bei Fehler |
| `--max-time=3600` | Worker nach 1 Stunde beenden (Supervisor startet ihn neu; verhindert Speicherleck) |
| `--memory=128` | Max. Speicher in MB, danach Neustart |

Beispiel mit mehreren Queues:

```bash
php artisan queue:work --queue=default,emails --sleep=3 --tries=3
```

---

## 5. Zusammenfassung

1. **Cron** (ein Eintrag): `* * * * * php /pfad/artisan schedule:run >> /dev/null 2>&1`
2. **Queue-Worker** mit Supervisor: Config unter `/etc/supervisor/conf.d/`, dann `supervisorctl update` und `start`.

Damit laufen geplante Tasks (Scheduler) und die Abarbeitung der Queue (Worker) zuverlässig auf deinem Ubuntu-Server.
