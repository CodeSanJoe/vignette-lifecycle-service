<?php

/**
 * --------------------------------------------------------------------------
 * VIGNETTE SYSTEM - COMPLETE DEMO
 * --------------------------------------------------------------------------
 * Diese Datei demonstriert den gesamten Stack:
 * 1. Die Business Logik (Service)
 * 2. Die Laravel Integration (Controller)
 * 3. Die Simulation des Frameworks (Mocks)
 */

// ==========================================================================
// 1. MOCKING FRAMEWORK (Simulation von Laravel)
// ==========================================================================
// Damit dieser Code überall läuft (auch ohne Laravel Installation),
// simulieren wir hier die Basis-Klassen.

if (!class_exists('Controller')) {
    class Controller {}
}

class JsonResponse {
    public function __construct(public mixed $data, public int $status = 200) {
        // Wir geben das Ergebnis direkt aus, damit du es siehst
        echo "\n--------------------------------------------------\n";
        echo "📡 HTTP RESPONSE [Status: $status]\n";
        echo "--------------------------------------------------\n";
        if (is_array($data) && isset($data['error'])) {
            echo "❌ FEHLER: " . $data['error'] . "\n";
        } else {
            echo "✅ ERFOLG: " . ($data['message'] ?? 'OK') . "\n";
            if (isset($data['data'])) echo "   Details: " . $data['data'] . "\n";
        }
        echo "\n";
    }
}

class FormRequest {
    public function validated(): array {
        // Simulation einer User-Eingabe für den Test
        return [
            'plate'       => 'W-12345',
            'contact'     => 'test@beispiel.at',
            'has_consent' => true,
            'channel'     => 'E-Mail'
        ];
    }
}

if (!function_exists('response')) {
    function response() {
        return new class {
            public function json($data, $status = 200) {
                return new JsonResponse($data, $status);
            }
        };
    }
}

// ==========================================================================
// 2. BUSINESS LOGIC (Der Service)
// ==========================================================================

// Exceptions
class PrivacyException extends Exception {}
class VignetteDomainException extends Exception {}

// Enum für Kanäle
enum Channel: string {
    case EMAIL = 'E-Mail';
    case SMS = 'SMS';
}

// Value Object für Kennzeichen
readonly class LicensePlate {
    public string $formatted;
    public string $regionCode;

    public function __construct(string $input) {
        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $input));
        
        if (strlen($clean) < 3) {
            throw new InvalidArgumentException("Kennzeichen zu kurz.");
        }

        $this->formatted = $clean;
        // Einfache Logik: Wenn 2. Zeichen eine Ziffer ist, ist Bezirk 1 Buchstabe (W-1...), sonst 2 (KU-1...)
        $this->regionCode = ctype_digit($clean[1] ?? '') ? substr($clean, 0, 1) : substr($clean, 0, 2);
    }
}

// Der Service
class VignetteLifecycleService {
    private const VALID_DISTRICTS = ['W', 'KU', 'L', 'B', 'G', 'Z', 'AM', 'H', 'M', 'K']; 

    public function registerExpirationReminder(
        bool $hasConsent, 
        string $plateInput, 
        string $contact, 
        Channel $channel
    ): string {
        // 1. DSGVO Check
        if (!$hasConsent) {
            throw new PrivacyException("Verarbeitung abgelehnt: Fehlender Consent.");
        }

        // 2. Validierung
        $plate = new LicensePlate($plateInput);
        if (!in_array($plate->regionCode, self::VALID_DISTRICTS)) {
            throw new VignetteDomainException("Bezirk '{$plate->regionCode}' unbekannt.");
        }

        // 3. Logik
        $expiryDate = new DateTimeImmutable('+5 days');
        $reminderDate = $expiryDate->modify('-3 days');
        
        // 4. Output
        return sprintf(
            "Reminder gesetzt für %s (%s) am %s via %s",
            $plate->formatted,
            $contact,
            $reminderDate->format('d.m.Y'),
            $channel->value
        );
    }
}

// ==========================================================================
// 3. LARAVEL INTEGRATION (Der Controller)
// ==========================================================================

class VignetteController extends Controller {
    public function __construct(
        private VignetteLifecycleService $vignetteService
    ) {}

    public function store(FormRequest $request): JsonResponse {
        try {
            $validated = $request->validated();
            
            // Aufruf des Services
            $result = $this->vignetteService->registerExpirationReminder(
                hasConsent: (bool) $validated['has_consent'],
                plateInput: $validated['plate'],
                contact:    $validated['contact'],
                channel:    Channel::from($validated['channel'])
            );

            return response()->json(['success' => true, 'message' => 'Gespeichert', 'data' => $result], 201);

        } catch (PrivacyException $e) {
            return response()->json(['error' => 'DSGVO: ' . $e->getMessage()], 403);
        } catch (VignetteDomainException $e) {
            return response()->json(['error' => 'Validierung: ' . $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'Serverfehler: ' . $e->getMessage()], 500);
        }
    }
}

// ==========================================================================
// 4. TEST LAUF (Execution)
// ==========================================================================

echo "🚀 START DES TESTS...\n";

// Instanzieren
$service = new VignetteLifecycleService();
$controller = new VignetteController($service);
$request = new FormRequest();

// Ausführen
$controller->store($request);
