<?php

declare(strict_types=1);

namespace App\Api;

use App\Core\Request;
use App\Core\Response;
use JsonException;

/**
 * JSON in and out for the API (spec §6). Every failure uses one envelope:
 * {"error": {"code": "...", "message": "...", "fields": {...}}}.
 */
final class Json
{
    /** @param array<string, string> $fields per-field messages, for 422 */
    public static function error(int $status, string $code, string $message, array $fields = []): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message, 'fields' => (object) $fields]], $status);
    }

    /** @param array<string, string> $fields field => message */
    public static function invalid(array $fields): Response
    {
        return self::error(422, 'validation_failed', 'The given data was invalid.', $fields);
    }

    public static function notFound(): Response
    {
        return self::error(404, 'not_found', 'Not found.');
    }

    /**
     * The request body as a JSON object, or a 400 when it isn't one.
     *
     * @return array<string, mixed>|Response
     */
    public static function body(Request $request): array|Response
    {
        try {
            $body = json_decode($request->content, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::error(400, 'malformed_json', 'The request body must be valid JSON.');
        }
        if (!$body instanceof \stdClass) {
            return self::error(400, 'malformed_json', 'The request body must be a JSON object.');
        }

        return get_object_vars($body);
    }
}
