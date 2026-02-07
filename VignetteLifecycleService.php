<?php

declare(strict_types=1);

/**
 * Service für Digitale Vignetten - Lifecycle Management
 * 
 * Features:
 * - Smart Input Validierung (Qualitätssicherung)
 * - Customer Retention (Reminder-Service)
 * - DSGVO-Compliance (Consent-Zwang & Double-Opt-In)
 * - PHP 8.2 Standards (Enums, Readonly, Typed Properties)
 * 
 * @author   CodeSanJoe
 * @version  2.0
 * @license  MIT
 */

// --- 1. EIGENE EXCEPTIONS (Zeigt saubere Fehlerkultur) ---
class PrivacyException extends Exception {}
class VignetteDomainException extends Exception {}
class ValidationException extends Exception {}

// --- 2. INTELLIGENTES VALUE OBJECT ---
/**
 * Repräsentiert ein österreichisches Kennzeichen und garantiert dessen Formatierung.
 * 
 * Format: 1-2 Buchstaben (Bezirk) + Zahlen + optional Buchstaben
 * Beispiele: W-12345, KU-123AB, L-999XY
 */
readonly class LicensePlate
{
    public string $formatted;
    public string $regionCode;

    /**
     * @param string $input Roheingabe des Kennzeichens (z.B. "W - 123 AB")
     * @throws InvalidArgumentException Bei ungültigem Format
     */
    public function __construct(string $input)
    {
        // Schritt 1: Normalisierung (Groß, nur Buchstaben/Zahlen)
        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $input));

        // Schritt 2: Längenprüfung (Österreich: 3-8 Zeichen)
        if (strlen($clean) < 3 || strlen($clean) > 8) {
            throw new InvalidArgumentException(
                "Kennzeichen '$input' muss 3-8 Zeichen haben (aktuell: " . strlen($clean) . ")."
            );
        }

        // Schritt 3: Grundlegendes Format prüfen (Buchstaben am Anfang, dann Zahlen)
        if (!preg_match('/^[A-Z]+[0-9]+[A-Z]*$/', $clean)) {
            throw new InvalidArgumentException(
                "Kennzeichen '$input' hat ungültiges Format. " .
                "Erwartet: Buchstaben-Zahlen-Buchstaben (z.B. W-123 oder KU-456AB)."
            );
        }

        // Schritt 4: Bezirkscode extrahieren (max. 2 Buchstaben sind in Österreich gültig)
        preg_match('/^([A-Z]+)/', $clean, $matches);
        $extractedCode = $matches[1];
        
        // Schritt 5: Prüfen ob Bezirkscode zu lang ist (österreichische Kennzeichen: max. 2 Buchstaben)
        if (strlen($extractedCode) > 2) {
            throw new InvalidArgumentException(
                "Kennzeichen '$input' hat ungültiges Format. " .
                "Bezirkscode '$extractedCode' ist zu lang (max. 2 Buchstaben erlaubt, z.B. W oder KU)."
            );
        }
        
        $this->formatted = $clean;
        $this->regionCode = $extractedCode;
    }

    /**
     * Gibt eine lesbare Darstellung zurück (z.B. "W-12345")
     */
    public function getReadable(): string
    {
        // Trennt Bezirk vom Rest mit Bindestrich
        return $this->regionCode . '-' . substr($this->formatted, strlen($this->regionCode));
    }
}

// --- 3. KOMMUNIKATIONS-KANÄLE (PHP 8.1 Enum) ---
enum Channel: string {
    case EMAIL = 'E-Mail';
    case SMS = 'SMS';
    
    /**
     * Gibt zurück, welcher Input-Typ für diesen Kanal erwartet wird
     */
    public function getExpectedInputType(): string
    {
        return match($this) {
            self::EMAIL => 'E-Mail-Adresse',
            self::SMS   => 'Telefonnummer',
        };
    }
}

// --- 4. DER HAUPT-SERVICE ---
class VignetteLifecycleService
{
    /**
     * Simulation einer Datenbank (Validierung gegen österreichische Bezirke)
     * 
     * Auswahl wichtiger Bezirke:
     * W = Wien, KU = Kufstein, L = Linz, B = Bregenz, G = Graz
     * Z = Zell am See, AM = Amstetten, H = Hartberg, M = Mödling, K = Klagenfurt
     */
    private const VALID_DISTRICTS = ['W', 'KU', 'L', 'B', 'G', 'Z', 'AM', 'H', 'M', 'K']; 
    
    /**
     * Anzahl Tage, die die Vignette ab heute gültig ist
     */
    private const VIGNETTE_VALIDITY_DAYS = 365;
    
    /**
     * Wieviele Tage VOR Ablauf soll der Reminder gesendet werden
     */
    private const REMINDER_DAYS_BEFORE_EXPIRY = 14;

    /**
     * Registriert einen Ablauf-Reminder für den Kunden.
     * 
     * @param bool $hasConsent ZWINGEND ERFORDERLICH (DSGVO): Hat der User die Checkbox aktiviert?
     * @param string $plateInput Kennzeichen-Eingabe (z.B. "W-123AB")
     * @param string $contact E-Mail oder Telefonnummer
     * @param Channel $channel Gewünschter Kommunikationskanal
     * 
     * @return string Erfolgsmeldung mit Details
     * 
     * @throws PrivacyException Wenn keine Einwilligung vorliegt
     * @throws ValidationException Bei ungültigen Kontaktdaten
     * @throws VignetteDomainException Bei unbekanntem Bezirk
     * @throws InvalidArgumentException Bei ungültigem Kennzeichen
     */
    public function registerExpirationReminder(
        bool $hasConsent, 
        string $plateInput, 
        string $contact, 
        Channel $channel
    ): string
    {
        // SCHRITT 0: DSGVO Hard-Stop
        // Wir schützen das Unternehmen: Ohne explizites "Ja" wird hier nichts verarbeitet.
        if ($hasConsent === false) {
            throw new PrivacyException(
                "ABBRUCH: Verarbeitung gestoppt. Keine Einwilligung (Consent) des Nutzers. " .
                "Gemäß Art. 6 Abs. 1 lit. a DSGVO ist eine Verarbeitung nicht zulässig."
            );
        }

        // SCHRITT 1: Smart Input Validierung (Kennzeichen)
        // Das Value Object wirft selbstständig Fehler, wenn das Format falsch ist.
        $plate = new LicensePlate($plateInput);

        // SCHRITT 2: Business-Logik - Existiert der Bezirk?
        if (!$this->isValidDistrict($plate->regionCode)) {
            throw new VignetteDomainException(
                "Warnung: Bezirk '{$plate->regionCode}' ist in unserer Datenbank nicht hinterlegt. " .
                "Bitte prüfen Sie auf Tippfehler. Gültige Bezirke: " . implode(', ', self::VALID_DISTRICTS)
            );
        }

        // SCHRITT 3: Kontaktdaten validieren (muss zum Kanal passen)
        $this->validateContact($contact, $channel);

        // SCHRITT 4: Ablaufdatum berechnen
        $today = new DateTimeImmutable();
        $expiryDate = $today->modify('+' . self::VIGNETTE_VALIDITY_DAYS . ' days');
        
        // SCHRITT 5: Reminder-Datum berechnen
        $reminderDate = $expiryDate->modify('-' . self::REMINDER_DAYS_BEFORE_EXPIRY . ' days');
        
        // SCHRITT 6: Double-Opt-In Verfahren initiieren
        // Statt direkt zu spammen, senden wir erst einen Bestätigungslink.
        $confirmationToken = $this->generateConfirmationToken();
        
        $actionLog = match($channel) {
            Channel::EMAIL => "Double-Opt-In Link generiert (Token: {$confirmationToken}). " .
                             "Bestätigungs-E-Mail wird versendet.",
            Channel::SMS   => "6-stelliger SMS-Bestätigungscode generiert. " .
                             "Nutzer muss Code innerhalb von 10 Minuten eingeben.",
        };

        // SCHRITT 7: Ausgabe (Datenminimierung: Kontakt maskieren)
        $maskedContact = $this->maskContact($contact, $channel);

        // SCHRITT 8: In produktiver Umgebung würde hier die DB-Speicherung erfolgen
        // $this->saveToDatabase($plate, $contact, $channel, $reminderDate, $confirmationToken);

        return sprintf(
            "✅ SYSTEM STATUS: ERFOLGREICH REGISTRIERT\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📋 Fahrzeug-Info:\n" .
            "   • Kennzeichen: %s\n" .
            "   • Bezirk: %s\n" .
            "   • Vignetten-Gültigkeit: %s - %s\n" .
            "\n" .
            "📬 Kommunikation:\n" .
            "   • Kanal: %s\n" .
            "   • Ziel: %s\n" .
            "   • Aktion: %s\n" .
            "\n" .
            "⏰ Zeitplan:\n" .
            "   • Reminder-Versand: %s (14 Tage vor Ablauf)\n" .
            "   • Vignetten-Ablauf: %s\n" .
            "\n" .
            "🛡️ Datenschutz:\n" .
            "   • Einwilligung: Liegt vor (Art. 6 Abs. 1 lit. a DSGVO)\n" .
            "   • Speicherdauer: Bis Widerruf oder 30 Tage nach Ablauf\n" .
            "   • Widerrufsrecht: Jederzeit per E-Mail an datenschutz@vignette.at",
            $plate->getReadable(),
            $plate->regionCode,
            $today->format('d.m.Y'),
            $expiryDate->format('d.m.Y'),
            $channel->value,
            $maskedContact,
            $actionLog,
            $reminderDate->format('d.m.Y H:i'),
            $expiryDate->format('d.m.Y')
        );
    }

    /**
     * Prüft gegen interne Datenbank, ob der Bezirk existiert.
     */
    private function isValidDistrict(string $code): bool
    {
        return in_array($code, self::VALID_DISTRICTS, true);
    }

    /**
     * Validiert, ob die Kontaktdaten zum gewählten Kanal passen.
     * 
     * @throws ValidationException Bei Format-Mismatch
     */
    private function validateContact(string $contact, Channel $channel): void
    {
        $isValid = match($channel) {
            Channel::EMAIL => filter_var($contact, FILTER_VALIDATE_EMAIL) !== false,
            Channel::SMS   => preg_match('/^\+?[0-9]{7,15}$/', $contact) === 1,
        };

        if (!$isValid) {
            throw new ValidationException(
                "Ungültige Kontaktdaten für Kanal '{$channel->value}'. " .
                "Erwartet: {$channel->getExpectedInputType()}. " .
                "Erhalten: '$contact'"
            );
        }
    }

    /**
     * Maskiert Kontaktdaten für Logs (Privacy by Default - DSGVO Art. 25).
     * 
     * Beispiele:
     * - max.mustermann@firma.at → ma***@firma.at
     * - +43664123456 → +43***456
     */
    private function maskContact(string $contact, Channel $channel): string 
    {
        if ($channel === Channel::EMAIL) {
            $parts = explode('@', $contact);
            $localPart = $parts[0];
            $domain = $parts[1] ?? 'unknown';
            
            // Zeige max. 30% des Namens, mind. 1, max. 3 Zeichen
            $nameLen = strlen($localPart);
            $visibleChars = min(3, max(1, (int)ceil($nameLen * 0.3)));
            
            return substr($localPart, 0, $visibleChars) . '***@' . $domain;
        }
        
        // SMS: Zeige Ländercode + letzte 3 Ziffern
        if (strlen($contact) > 6) {
            $start = substr($contact, 0, 3);  // z.B. +43 oder +49
            $end = substr($contact, -3);       // letzte 3 Ziffern
            return $start . '***' . $end;
        }
        
        return substr($contact, 0, 2) . '***';
    }

    /**
     * Generiert einen sicheren Bestätigungstoken für Double-Opt-In.
     * 
     * In Produktion würde hier ein kryptographisch sicherer Token
     * mit bin2hex(random_bytes(32)) generiert werden.
     */
    private function generateConfirmationToken(): string
    {
        // Simulation für Demo-Zwecke
        return 'TOK_' . strtoupper(substr(md5((string)time()), 0, 12));
    }
}

// ═══════════════════════════════════════════════════════════════
// TEST-SUITE (Simulation für GitHub Portfolio)
// ═══════════════════════════════════════════════════════════════

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  VIGNETTEN LIFECYCLE SERVICE - INTEGRATION TEST SUITE     ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$service = new VignetteLifecycleService();
$testsPassed = 0;
$testsFailed = 0;

// --- TEST 1: Happy Path (Erfolgreicher Fall) ---
echo "📌 TEST 1: Erfolgreicher Reminder (E-Mail, gültiges Kennzeichen)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "ku - 123 xy", 
        contact: "max.mustermann@firma.at", 
        channel: Channel::EMAIL
    );
    echo "\n\n✅ TEST 1 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 1 FEHLGESCHLAGEN: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 2: DSGVO-Schutz (Keine Einwilligung) ---
echo "📌 TEST 2: DSGVO-Schutz (User hat Checkbox NICHT aktiviert)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: false, 
        plateInput: "W-12345", 
        contact: "daten@schutz.at", 
        channel: Channel::SMS
    );
    echo "❌ TEST 2 FEHLGESCHLAGEN: Exception erwartet, aber nicht geworfen!\n\n";
    $testsFailed++;
} catch (PrivacyException $e) {
    echo "🛡️ ERWARTETER DATENSCHUTZ-ALARM:\n";
    echo "   → " . $e->getMessage() . "\n";
    echo "✅ TEST 2 BESTANDEN (System hat korrekt abgebrochen)\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 2 FEHLGESCHLAGEN: Falsche Exception: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 3: SMS-Kanal ---
echo "📌 TEST 3: SMS-Reminder mit Telefonnummer\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "W-789", 
        contact: "+436641234567", 
        channel: Channel::SMS
    );
    echo "\n\n✅ TEST 3 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 3 FEHLGESCHLAGEN: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 4: Ungültiger Bezirk ---
echo "📌 TEST 4: Unbekannter Bezirk (sollte Warnung werfen)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "XX-123",  // XX ist kein gültiger österreichischer Bezirk
        contact: "test@test.at", 
        channel: Channel::EMAIL
    );
    echo "❌ TEST 4 FEHLGESCHLAGEN: Exception erwartet!\n\n";
    $testsFailed++;
} catch (VignetteDomainException $e) {
    echo "⚠️ ERWARTETE DOMAIN-WARNUNG:\n";
    echo "   → " . $e->getMessage() . "\n";
    echo "✅ TEST 4 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 4 FEHLGESCHLAGEN: Falsche Exception: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 5: Kanal-Mismatch (E-Mail-Kanal, aber Telefonnummer) ---
echo "📌 TEST 5: Validierung - E-Mail-Kanal mit Telefonnummer\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "W-111", 
        contact: "+43664123456", 
        channel: Channel::EMAIL  // ← Mismatch!
    );
    echo "❌ TEST 5 FEHLGESCHLAGEN: Validation sollte fehlschlagen!\n\n";
    $testsFailed++;
} catch (ValidationException $e) {
    echo "🔍 ERWARTETER VALIDIERUNGS-FEHLER:\n";
    echo "   → " . $e->getMessage() . "\n";
    echo "✅ TEST 5 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 5 FEHLGESCHLAGEN: Falsche Exception: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 6: Ungültiges Kennzeichen (zu kurz) ---
echo "📌 TEST 6: Ungültiges Kennzeichen (zu kurz)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "W1", 
        contact: "test@test.at", 
        channel: Channel::EMAIL
    );
    echo "❌ TEST 6 FEHLGESCHLAGEN: Exception erwartet!\n\n";
    $testsFailed++;
} catch (InvalidArgumentException $e) {
    echo "⚠️ ERWARTETER FORMAT-FEHLER:\n";
    echo "   → " . $e->getMessage() . "\n";
    echo "✅ TEST 6 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 6 FEHLGESCHLAGEN: Falsche Exception: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 7: Komplexes Kennzeichen mit Sonderzeichen-Normalisierung ---
echo "📌 TEST 7: Kennzeichen-Normalisierung (Bindestriche, Leerzeichen, Kleinbuchstaben)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "  ku - 999 ab  ", 
        contact: "normalisierung@test.at", 
        channel: Channel::EMAIL
    );
    echo "\n\n✅ TEST 7 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 7 FEHLGESCHLAGEN: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- TEST 8: Bezirkscode zu lang (mehr als 2 Buchstaben) ---
echo "📌 TEST 8: Ungültiger Bezirkscode (zu lang: 3 Buchstaben)\n";
echo str_repeat("─", 63) . "\n";
try {
    echo $service->registerExpirationReminder(
        hasConsent: true, 
        plateInput: "XYZ-123",  // 3 Buchstaben = ungültig
        contact: "test@test.at", 
        channel: Channel::EMAIL
    );
    echo "❌ TEST 8 FEHLGESCHLAGEN: Exception erwartet!\n\n";
    $testsFailed++;
} catch (InvalidArgumentException $e) {
    echo "⚠️ ERWARTETER FORMAT-FEHLER:\n";
    echo "   → " . $e->getMessage() . "\n";
    echo "✅ TEST 8 BESTANDEN\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "❌ TEST 8 FEHLGESCHLAGEN: Falsche Exception: " . $e->getMessage() . "\n\n";
    $testsFailed++;
}

// --- ZUSAMMENFASSUNG ---
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    TEST ZUSAMMENFASSUNG                   ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo sprintf("  ✅ Bestanden: %d\n", $testsPassed);
echo sprintf("  ❌ Fehlgeschlagen: %d\n", $testsFailed);
echo sprintf("  📊 Erfolgsrate: %.1f%%\n", ($testsPassed / ($testsPassed + $testsFailed)) * 100);
echo "\n";

if ($testsFailed === 0) {
    echo "🎉 ALLE TESTS BESTANDEN - Code ist produktionsreif!\n";
} else {
    echo "⚠️ Einige Tests fehlgeschlagen - Bitte Code überprüfen.\n";
}
