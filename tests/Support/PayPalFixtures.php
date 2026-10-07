<?php

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use RuntimeException;

/**
 * PayPal-Antworten für Tests, geprüft gegen PayPals OpenAPI-Spezifikationen.
 *
 * Jede Fixture unter tests/Fixtures/paypal/responses nennt Spezifikation,
 * Operation und HTTP-Status. Beim Laden werden die Overrides des Tests
 * eingemischt und das Ergebnis validiert: Ein Test kann so keine Antwort
 * zusammenbauen, die PayPal nie schicken würde (Anlass: createOrder liefert
 * per Default nur id/status/links, die Tests hatten einen Betrag erfunden).
 *
 * Die Spezifikationen stammen unverändert aus
 * github.com/paypal/paypal-rest-api-specifications (Commit 90e8041).
 */
final class PayPalFixtures
{
    private const string BASE_PATH = __DIR__.'/../Fixtures/paypal';

    /**
     * @var array<string, ResponseValidator>
     */
    private static array $validators = [];

    /**
     * Antwortbody einer Fixture, mit Overrides, gegen die Spezifikation geprüft.
     *
     * @param  array<string, mixed>  $overrides  rekursiv eingemischt (Listen per Index)
     * @return array<string, mixed>
     */
    public static function body(string $name, array $overrides = []): array
    {
        $fixture = self::load($name);
        self::assertOverridesExist($fixture['body'], $overrides, $name);
        $body = array_replace_recursive($fixture['body'], $overrides);

        self::validate($fixture['spec'], $fixture['method'], $fixture['path'], $fixture['status'], $body, $name);

        return $body;
    }

    /**
     * Was das SDK an PayPalService zurückgibt: den dekodierten Body, bei
     * HTTP-Fehlern (ab 400) verpackt als ['error' => Body].
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function sdkResponse(string $name, array $overrides = []): array
    {
        $body = self::body($name, $overrides);

        return self::load($name)['status'] >= 400 ? ['error' => $body] : $body;
    }

    /**
     * Antwort eines Captures (orders/capture-*), Overrides nur für die erste
     * Capture in purchase_units[0].payments.captures[0].
     *
     * @param  array<string, mixed>  $captureOverrides
     * @return array<string, mixed>
     */
    public static function capture(string $name, array $captureOverrides = []): array
    {
        return self::sdkResponse($name, ['purchase_units' => [['payments' => ['captures' => [$captureOverrides]]]]]);
    }

    /**
     * Webhook-Event, wie PayPal es an den Webhook-Endpunkt schickt. Die
     * Resource kommt aus einer Fixture und wird gegen ihre eigene Operation
     * geprüft, die Hülle gegen das Event-Schema der Webhooks-API.
     *
     * @param  array<string, mixed>  $resourceOverrides
     * @return array<string, mixed>
     */
    public static function webhookEvent(string $eventType, string $resourceName, array $resourceOverrides = []): array
    {
        $resourceType = str_starts_with($eventType, 'CHECKOUT.ORDER') ? 'checkout-order' : (str_contains($eventType, 'REFUNDED') || str_contains($eventType, 'REVERSED') ? 'refund' : 'capture');

        $event = [
            'id' => 'WH-'.strtoupper(substr(md5($eventType.$resourceName), 0, 17)),
            'create_time' => '2026-10-07T08:00:00.000Z',
            'resource_type' => $resourceType,
            'event_type' => $eventType,
            'summary' => $eventType,
            'resource' => self::body($resourceName, $resourceOverrides),
        ];

        self::validate('notifications_webhooks_v1.json', 'get', '/v1/notifications/webhooks-events/{event_id}', 200, $event, "webhook {$eventType}");

        return $event;
    }

    /**
     * @return array{spec: string, method: string, path: string, status: int, body: array<string, mixed>}
     */
    private static function load(string $name): array
    {
        $file = self::BASE_PATH."/responses/{$name}.json";

        if (! is_file($file)) {
            throw new RuntimeException("PayPal-Fixture '{$name}' nicht gefunden ({$file}).");
        }

        /** @var array{spec: string, method: string, path: string, status: int, body: array<string, mixed>} $fixture */
        $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return $fixture;
    }

    /**
     * Overrides dürfen nur Felder ändern, die die Fixture schon hat. Die
     * Spezifikationen verbieten Zusatzfelder nicht; ohne diese Regel könnte
     * ein Test ein Feld erfinden, das PayPal an dieser Stelle nie schickt.
     * Wer ein neues Feld braucht, legt eine eigene Fixture an.
     *
     * @param  array<array-key, mixed>  $fixture
     * @param  array<array-key, mixed>  $overrides
     */
    private static function assertOverridesExist(array $fixture, array $overrides, string $path): void
    {
        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $fixture)) {
                throw new RuntimeException("PayPal-Fixture '{$path}': Feld '{$key}' gibt es in der Fixture nicht. Neue Felder bitte als eigene Fixture anlegen.");
            }

            if (is_array($value) && is_array($fixture[$key])) {
                self::assertOverridesExist($fixture[$key], $value, "{$path}.{$key}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function validate(string $spec, string $method, string $path, int $status, array $body, string $label): void
    {
        $validator = self::$validators[$spec] ??= (new ValidatorBuilder)
            ->fromJsonFile(self::BASE_PATH."/openapi/{$spec}")
            ->getResponseValidator();

        $response = new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body, JSON_THROW_ON_ERROR));

        try {
            $validator->validate(new OperationAddress($path, $method), $response);
        } catch (ValidationFailed $e) {
            $reasons = [];

            for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                $reasons[] = $cause->getMessage();
            }

            throw new RuntimeException("PayPal-Fixture '{$label}' entspricht nicht {$spec} ({$method} {$path} → {$status}): ".implode(' ← ', $reasons), previous: $e);
        }
    }
}
