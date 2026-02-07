<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\VignetteStoreRequest;
use App\Services\VignetteLifecycleService;
use App\Enums\Channel;
use Exception;

/**
 * 💡 LARAVEL INTEGRATION DEMO
 * * Diese Datei demonstriert, wie ich den 'VignetteLifecycleService' 
 * in eine moderne Laravel-Applikation integrieren würde.
 * * Konzepte:
 * - Dependency Injection (Service in Controller injizieren)
 * - Form Requests (Validierung vom Controller trennen)
 * - Exception Handling (HTTP Status Codes mappen)
 */

class VignetteController extends Controller
{
    // PHP 8 Constructor Promotion: Der Service wird automatisch injiziert (DI)
    public function __construct(
        private VignetteLifecycleService $vignetteService
    ) {}

    /**
     * API-Endpoint: POST /api/v1/reminders
     */
    public function store(VignetteStoreRequest $request): JsonResponse
    {
        try {
            // Die Daten sind hier bereits validiert (durch VignetteStoreRequest)
            $validated = $request->validated();

            // Aufruf der Business-Logik (Service)
            // Hinweis: Wir casten den String 'channel' zurück in das PHP Enum
            $result = $this->vignetteService->registerExpirationReminder(
                hasConsent: (bool) $validated['has_consent'],
                plateInput: $validated['plate'],
                contact:    $validated['contact'],
                channel:    Channel::from($validated['channel'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Reminder erfolgreich angelegt.',
                'details' => $result
            ], 201); // HTTP 201 Created

        } catch (\PrivacyException $e) {
            // DSGVO-Fehler -> HTTP 403 Forbidden
            return response()->json(['error' => $e->getMessage()], 403);

        } catch (\VignetteDomainException $e) {
            // Logik-Fehler (falscher Bezirk) -> HTTP 422 Unprocessable Entity
            return response()->json(['error' => $e->getMessage()], 422);

        } catch (Exception $e) {
            // Sonstige Fehler -> HTTP 500 Internal Server Error
            return response()->json(['error' => 'Serverfehler'], 500);
        }
    }
}

/**
 * 💡 LARAVEL FORM REQUEST
 * Validiert den Input, BEVOR er den Controller erreicht.
 */
class VignetteStoreRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'       => ['required', 'string', 'min:3', 'max:10'],
            'contact'     => ['required', 'string'],
            'has_consent' => ['required', 'accepted'], // 'accepted' zwingt zu true/1/yes
            'channel'     => ['required', 'string', 'in:E-Mail,SMS'], // Muss zum Enum passen
        ];
    }

    public function messages(): array
    {
        return [
            'has_consent.accepted' => 'Ohne Ihre Einwilligung (DSGVO) können wir keinen Reminder setzen.',
            'plate.required'       => 'Bitte geben Sie ein Kennzeichen ein.',
        ];
    }
}
