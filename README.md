# 🚗 Vignette Lifecycle Service (PHP 8.2 Demo)

> **Modernes Backend-Modul zur Verwaltung digitaler Vignetten mit Fokus auf Datenqualität, Kundenbindung und DSGVO-Compliance.**

## 🎯 Über das Projekt

Dieses Repository demonstriert die saubere Abbildung geschäftskritischer Prozesse in einer Web-Anwendung. Es löst drei Kernprobleme:
1. **Datenqualität:** Automatische Reparatur fehlerhafter Kennzeichen-Eingaben.
2. **Kundenbindung:** Proaktive Reminder-Logik vor Ablauf der Gültigkeit.
3. **Rechtssicherheit:** Erzwingung der DSGVO-Einwilligung direkt im Backend-Code.

### ✨ Kern-Features

* **🛡️ Smart Input Validation:** Ein `LicensePlate` Value Object repariert Eingabefehler (Trimmen, Formatieren) automatisch.
* **🔄 Customer Retention:** Berechnung des optimalen Reminder-Zeitpunkts (14 Tage vor Ablauf).
* **⚖️ DSGVO Compliance:** * Technischer "Hard-Stop" ohne Consent.
    * Maskierung personenbezogener Daten in Logs.
    * Vorbereitung für Double-Opt-In Verfahren.

---

## 🏗️ Technische Architektur

Der Code nutzt moderne **PHP 8.2+** Standards:
* **Enums & Readonly Classes:** Für maximale Typsicherheit.
* **Custom Exceptions:** Professionelles Error-Handling.
* **Strict Types:** Vermeidung von Laufzeitfehlern.

### 🚀 Installation & Test

Da dies ein modulares PHP-Skript ist, kann es direkt in der Konsole oder über einen Online-Compiler (z.B. OnlinePHP.io) getestet werden. 
Eine integrierte **Test-Suite** am Ende der Datei simuliert verschiedene Szenarien:
* ✅ Erfolgreiche Registrierung
* 🛡️ DSGVO-Abbruch (fehlender Consent)
* 🔍 Validierungsfehler (falsche Kennzeichen/Kontakte)

---

## 🚀 Laravel Integration (Enterprise Architecture)

Neben der reinen PHP-Logik demonstriert die Datei [`Laravel_Integration_Demo.php`](./Laravel_Integration_Demo.php), wie dieser Prozess in einer skalierbaren **Laravel-Architektur** abgebildet wird. 

Der Fokus liegt hier auf **Clean Code** und **Separation of Concerns**:

### 🏗️ Architektur-Highlights

* **🎮 Thin Controllers:** Der `VignetteController` steuert nur den Ablauf, enthält aber keine Geschäftslogik.
* **request 🛡️ Form Requests:** Die Validierung (`VignetteStoreRequest`) ist vom Controller entkoppelt. Das garantiert, dass nur valide, saubere Daten die Applikationslogik erreichen.
* **⚙️ Service Layer:** Die eigentliche Business-Logik (Berechnung, Speicherung) liegt isoliert im `VignetteService`. Dies macht den Code wiederverwendbar und testbar.
* **json 🔌 JSON Responses:** Standardisierte API-Antworten (Status 201 Created, 422 Unprocessable Entity) für die Kommunikation mit Frontends.

### 🛠️ Implementierte Laravel-Features

| Feature | Zweck im Projekt |
| :--- | :--- |
| **Dependency Injection** | Automatisches Laden des `VignetteService` in den Controller. |
| **Route Grouping** | Strukturierte API-Routen (z.B. `/api/v1/vignettes`). |
| **Validation Rules** | Einsatz von Laravels Validierungs-Regeln (`required`, `email`, `string`). |
| **Log-Maskierung** | Datenschutzkonformes Logging sensibler Daten. |

> **Hinweis:** Die Datei `Laravel_Integration_Demo.php` dient als **Architektur-Blaupause**. Sie zeigt, wie die Klassen (Controller, Service, Request) in einem echten Laravel-Projekt zusammenspielen würden.

---

## 💡 Warum dieser Ansatz?

Als Quereinsteiger mit **6 Jahren Erfahrung in der Industrie-Logistik (Komatsu)** verfolge ich das Prinzip "Quality at Source". 
Fehlerhafte Daten oder rechtliche Risiken werden abgefangen, bevor sie das System belasten. Dies reduziert Support-Kosten und steigert die Prozess-Sicherheit.

---

## 📩 Kontakt

* **Entwickler:** Alexander S.
* **Fokus:** PHP Backend / Laravel
* **LinkedIn:** https://www.linkedin.com/in/alexander-susskij-0457542b1?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app
* 
