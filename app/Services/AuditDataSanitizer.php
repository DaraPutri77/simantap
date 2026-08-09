<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class AuditDataSanitizer
{
    private const REDACTED = '[DISENSOR]';

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        '_token',
        'api_key',
        'authorization',
        'cookie',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'password',
        'password_confirmation',
        'passphrase',
        'private_key',
        'remember_token',
        'secret',
        'signature',
        'signature_data',
        'signature_image',
        'token',
        'token_hash',
    ];

    /**
     * Menghapus nilai sensitif sebelum audit disimpan.
     *
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    public function sanitize(?array $values): ?array
    {
        return $this->filter($values, redact: false);
    }

    /**
     * Menyensor nilai sensitif secara defensif saat audit lama ditampilkan.
     *
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    public function redact(?array $values): ?array
    {
        return $this->filter($values, redact: true);
    }

    public function sanitizeUrl(Request $request): string
    {
        $url = $request->url();
        $route = $request->route();

        if ($route instanceof Route) {
            foreach ($route->parameters() as $name => $value) {
                if (! $this->isSensitiveKey((string) $name) || ! is_scalar($value)) {
                    continue;
                }

                $rawValue = (string) $value;
                $url = str_replace(
                    array_unique([
                        $rawValue,
                        rawurlencode($rawValue),
                    ]),
                    '{credential}',
                    $url,
                );
            }
        }

        $url = (string) preg_replace(
            '#/(aktivasi-akun|reset-kata-sandi)/[^/?\#]+#i',
            '/$1/{credential}',
            $url,
        );

        $query = $this->sanitize($request->query());

        if ($query === null || $query === []) {
            return Str::limit($url, 2048, '');
        }

        return Str::limit(
            $url.'?'.http_build_query(
                $query,
                '',
                '&',
                PHP_QUERY_RFC3986,
            ),
            2048,
            '',
        );
    }

    public function redactStoredUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $redacted = (string) preg_replace(
            '#/(aktivasi-akun|reset-kata-sandi)/[^/?\#]+#i',
            '/$1/{credential}',
            $url,
        );

        return (string) preg_replace(
            '/([?&](?:_token|api[_-]?key|authorization|cookie|password|passphrase|private[_-]?key|secret|token)(?:[^=]*)=)[^&]*/i',
            '$1'.self::REDACTED,
            $redacted,
        );
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    private function filter(?array $values, bool $redact): ?array
    {
        if ($values === null) {
            return null;
        }

        $filtered = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                if ($redact) {
                    $filtered[$key] = self::REDACTED;
                }

                continue;
            }

            $filtered[$key] = is_array($value)
                ? $this->filter($value, $redact)
                : $value;
        }

        return $filtered;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower(trim($key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return preg_match(
            '/(?:^|[_.-])(password|passphrase|secret|token|authorization|cookie|api[_-]?key|private[_-]?key)(?:$|[_.-])/i',
            $normalized,
        ) === 1;
    }
}
