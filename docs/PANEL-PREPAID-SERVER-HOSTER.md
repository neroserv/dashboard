# Panel für Prepaid & Server-Hoster – Fehlende Funktionen & Ideen

**Ziel:** Das Panel soll ein klares Prepaid- und Server-Hosting-Panel sein.  
**Produkte (nur diese):** Webspace, Gameserver, Domains.

---

## 1. Aktueller Stand (Kurzüberblick)

| Bereich | Status |
|--------|--------|
| **Webspace** | HostingPlans, WebspaceAccounts, Checkout via Stripe, Plesk-Provisioning, Stripe-Subscriptions (Auto-Verlängerung) |
| **Gameserver** | HostingPlans (panel_type pterodactyl), GameServerAccounts, Checkout via Stripe, Pterodactyl-Provisioning |
| **Domains** | ResellerDomain, Domain-Shop, Checkout via Stripe, Skrime API, Verlängerungs-Rechnungen (Draft) |
| **Prepaid (Guthaben)** | `CustomerBalance` + `BalanceTransaction` vorhanden; **nur Admin** kann Guthaben vergeben. **Nirgends im Checkout nutzbar.** |
| **Rechnungen** | PDF, E-Rechnung, Mahnungen (Dunning), Stripe Billing Portal |
| **Meine Seiten (Sites/Templates)** | Voll integriert (eigenes Produkt) – soll laut Vorgabe **nicht** zu den drei Hauptprodukten zählen |

---

## 2. Was fehlt (konkret)

### 2.1 Prepaid für Kunden sichtbar und nutzbar

- **Guthaben im Frontend anzeigen**  
  Auf der Billing-Seite (`/billing`) wird aktuell **kein** Kundenguthaben angezeigt.  
  - BillingController liefert kein `customerBalance` / `balanceTransactions`.  
  - Idee: Eigenen Bereich „Guthaben“ mit aktuellem Stand und letzten Transaktionen.

- **Selbstaufladung (Guthaben aufladen)**  
  Kunden können Guthaben nur durch Admin erhalten.  
  - Option A: Stripe Checkout für „Guthaben aufladen“ (festes Paket oder Freibetrag).  
  - Option B: Nur Anzeige + Hinweis „Guthaben nur durch Support/Admin“.

- **Zahlung mit Guthaben im Checkout**  
  Alle Checkouts (Webspace, Gameserver, Domains) laufen aktuell **ausschließlich** über Stripe.  
  - Fehlt: Prüfung „Hat der Kunde genug Guthaben?“ und Option **„Mit Guthaben bezahlen“**.  
  - Bei Bezahlung mit Guthaben: `BalanceTransaction` (Debit), `CustomerBalance` reduzieren, Rechnung erzeugen, **kein** Stripe-Checkout.

### 2.2 Verlängerungen & Rechnungen

- **Webspace / Gameserver**  
  Verlängerung läuft über Stripe Subscriptions (automatisch).  
  - Es fehlt eine **Option „Manuelle Verlängerung“** (z. B. 1 Monat/Jahr aus Guthaben), falls ihr Prepaid-first werden wollt.  
  - Dafür: Rechnung erzeugen (oder vorhandene Nutzung), bei Zahlung aus Guthaben Abzug + ggf. Stripe-Subscription anpassen oder lokale Laufzeit verlängern.

- **Domains**  
  `CreateDomainRenewalInvoicesJob` erstellt bereits **Draft-Rechnungen** für Domain-Verlängerungen.  
  - Fehlt: Kundenfluss **„Rechnung bezahlen“** (Stripe **oder** Guthaben).  
  - Nach Zahlung: Skrime/Vertrag „Verlängerung“ auslösen und Rechnung auf „paid“ setzen.

- **Offene Rechnungen (Draft)**  
  Einheitlicher Ablauf: Rechnung anzeigen → „Jetzt bezahlen“ → Wahl „Stripe“ oder „Guthaben“ (wenn ausreichend).  
  Gilt für Domain-Verlängerungen und später ggf. für manuelle Webspace/Gameserver-Verlängerungen.

### 2.3 Produkt-Fokus: Nur Webspace, Gameserver, Domains

- **„Meine Seiten“ (Sites/Templates)**  
  Ist aktuell gleichberechtigt (eigenes Produkt, Checkout, Subscriptions).  
  - Entweder in der **Navigation ausblenden** (Konfig/Feature-Flag),  
  - oder komplett **entfernen**, wenn ihr nur Webspace, Gameserver, Domains anbieten wollt.  
  - Technisch: `ProcessExpiredSubscriptions` betrifft nur SiteSubscriptions; Webspace/Gameserver laufen über Stripe-Webhooks.

- **Produkttypen im Admin**  
  In DB/Migration steht `type`: `meine_seiten | webspace | domain`.  
  - `game_server` wird bei HostingPlan (pterodactyl) genutzt.  
  - Admin-Oberfläche (Produkte, Hosting-Pläne) so führen, dass nur **webspace**, **game_server**, **domain** als aktive Produkte sichtbar/konfigurierbar sind.

### 2.4 Navigation & Kundenbereich

- **Sidebar**  
  - „Meine Sites“ nur anzeigen, wenn das Feature aktiv ist (oder entfernen).  
  - **Domains** und **Gameserver** (bzw. „Meine Gameserver-Accounts“) klar sichtbar machen, falls noch nicht überall verlinkt.  
  - „Guthaben“ / „Rechnungen & Guthaben“ als zentralen Einstieg für Prepaid und Rechnungen.

- **Billing-Seite**  
  - Rechnungen (wie jetzt).  
  - **Guthaben** (Stand + Verlauf).  
  - Optional: Schnellaufladung (wenn Selbstaufladung gebaut wird).

### 2.5 Technische Lücken

- **Invoice-Typen**  
  Bereits vorhanden: z. B. `subscription`, `domain_renewal`.  
  - Für reine Guthaben-Käufe/Verlängerungen: klare Typen wie `webspace`, `game_server`, `domain`, `prepaid_charge` o. ä., damit Reporting und Dunning sauber laufen.

- **ProcessExpiredSubscriptions**  
  Verarbeitet nur **SiteSubscription** (Meine Seiten): Suspend nach Ablauf, Löschung nach Kulanzfrist.  
  - Webspace/Gameserver: Ablauf wird über **Stripe** (subscription deleted/updated) und bestehende Listener abgebildet.  
  - Wenn ihr **prepaid-basierte** Webspace/Gameserver-Laufzeiten einführt (ohne Stripe-Subscription), braucht ihr einen analogen Job für `WebspaceAccount` / `GameServerAccount` (z. B. `current_period_ends_at` + Zahlung aus Guthaben).

- **Dunning**  
  Mahnungen existieren für Rechnungen; funktionieren unabhängig von der Zahlungsart (Stripe vs. Guthaben), sofern Rechnungen mit Status „open“/„overdue“ geführt werden.

---

## 3. Ideen (optional / später)

- **Mindestaufladung**  
  Bei Selbstaufladung z. B. Mindestbetrag z. B. 5 € oder 10 €.

- **Gutschein-Codes**  
  Discount-/Voucher-Codes, die dem Kunden Guthaben gutschreiben (analog zu bestehenden Voucher/Discount-Strukturen, aber mit Gutschrift auf `CustomerBalance`).

- **Low-Balance-Benachrichtigung**  
  E-Mail (oder Notification), wenn Guthaben unter einen Schwellenwert fällt (z. B. unter 5 €).

- **Automatische Verlängerung aus Guthaben**  
  Einstellung „Webspace/Gameserver/Domain automatisch aus Guthaben verlängern“.  
  Geplante Jobs prüfen vor Ablauf: Guthaben ≥ Rechnungsbetrag → Rechnung erzeugen, von Guthaben abbuchen, Laufzeit verlängern.

- **Klare Produktseiten**  
  Eine Übersichtsseite „Produkte“ mit nur drei Kacheln: Webspace, Gameserver, Domains – mit Links zu Shop/Checkout.

- **Admin: Übersicht Prepaid-Umsatz**  
  Auswertung: Guthaben-Aufladungen, Verbräuche pro Produkt (Webspace/Gameserver/Domain), offene Beträge.

---

## 4. Priorisierungsvorschlag

1. **Kern für Prepaid:**  
   - Guthaben auf Billing-Seite anzeigen.  
   - Checkout (Webspace, Gameserver, Domains) um Option „Mit Guthaben bezahlen“ erweitern (inkl. Buchung `BalanceTransaction` + Rechnung).

2. **Produkt-Fokus:**  
   - „Meine Seiten“ aus Navigation entfernen oder per Konfiguration ausblenden.  
   - Admin nur auf Webspace, Gameserver, Domains ausrichten.

3. **Domain-Verlängerung:**  
   - Bezahlfluss für Domain-Renewal-Rechnungen (Draft → Bezahlung per Stripe oder Guthaben → Skrime-Verlängerung).

4. **Selbstaufladung (wenn gewünscht):**  
   - Stripe-Checkout „Guthaben aufladen“ mit festen Beträgen oder Freitext.

5. **Optional:**  
   - Manuelle Verlängerung Webspace/Gameserver aus Guthaben; automatische Verlängerung aus Guthaben; Gutscheine; Low-Balance-Mails.

---

## 5. Referenzen im Code (Stichpunkte)

- **Guthaben:** `CustomerBalance`, `BalanceTransaction`, `BillingController`, `Admin\CustomerController::storeBalance`
- **Checkout:** `CheckoutController::redirect`, `WebspaceController::storeCheckout`, `DomainShopController::storeCheckout`, `GamingController` + Gaming-Checkout in `CheckoutController`
- **Billing-Seite:** `resources/js/pages/billing/Index.vue` – kein Guthaben-Block
- **Produkttypen:** `Product`, `HostingPlan` (panel_type), Migration `products.type` (meine_seiten | webspace | domain), `config/billing.php` (Stripe-Product-IDs)
- **Domain-Renewal:** `CreateDomainRenewalInvoicesJob`, `DomainPricingService`, Skrime API
- **Sidebar:** `resources/js/components/AppSidebar.vue`

Diese Datei kann bei der Umsetzung schrittweise abgehakt und mit konkreten Ticket- oder Commit-Links ergänzt werden.
