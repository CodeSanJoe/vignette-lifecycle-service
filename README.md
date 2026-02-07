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

## 💡 Warum dieser Ansatz?

Als Quereinsteiger mit **6 Jahren Erfahrung in der Industrie-Logistik (Komatsu)** verfolge ich das Prinzip "Quality at Source". 
Fehlerhafte Daten oder rechtliche Risiken werden abgefangen, bevor sie das System belasten. Dies reduziert Support-Kosten und steigert die Prozess-Sicherheit.

---

## 📩 Kontakt

* **Entwickler:** Alexander S.
* **Fokus:** PHP Backend / Laravel
* **LinkedIn:** https://www.linkedin.com/in/alexander-susskij-0457542b1?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app
* 
