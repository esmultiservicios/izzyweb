<?php
declare(strict_types=1);

final class EmailValidation
{
    private const COMMON_DOMAINS = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'yahoo.com',
        'icloud.com',
        'live.com',
        'proton.me',
        'protonmail.com',
    ];

    private const DOMAIN_TYPOS = [
        'gmail.con' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cmo' => 'gmail.com',
        'gmail.om' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
        'iclod.com' => 'icloud.com',
    ];

    public static function config(array $settings): array
    {
        return [
            'enabled' => setting_enabled($settings, 'email_validation_enabled', true),
            'dns_enabled' => setting_enabled($settings, 'email_validation_dns_enabled', true),
            'disposable_enabled' => setting_enabled($settings, 'email_validation_disposable_enabled', true),
            'api_enabled' => setting_enabled($settings, 'email_validation_api_enabled', false),
            'api_url' => trim((string) ($settings['email_validation_api_url'] ?? '')),
            'api_token_stored' => trim((string) ($settings['email_validation_api_token'] ?? '')) !== '',
        ];
    }

    public static function validate(string $email, array $settings, bool $allowCache = true): array
    {
        $config = self::config($settings);
        $email = trim($email);

        if (!$config['enabled']) {
            return self::result(true, 'valid', 'Correo válido', $email);
        }

        if ($allowCache) {
            $cached = self::cachedResult($email);
            if ($cached !== null) {
                return $cached;
            }
        }

        $basic = self::validateSyntax($email);
        if (!$basic['ok']) {
            return self::cacheResult($email, $basic);
        }

        $normalizedEmail = $basic['email'];
        $domain = $basic['domain'];
        $suggestion = self::suggestEmail($normalizedEmail);

        if ($suggestion !== null && strcasecmp($suggestion, $normalizedEmail) !== 0) {
            return self::cacheResult(
                $email,
                self::result(
                    false,
                    'suggestion',
                    '¿Quisiste escribir '.$suggestion.'?',
                    $normalizedEmail,
                    $suggestion
                )
            );
        }

        if ($config['disposable_enabled'] && self::isDisposableDomain($domain)) {
            return self::cacheResult(
                $email,
                self::result(
                    false,
                    'temporary',
                    'Utiliza un correo electrónico permanente para continuar.',
                    $normalizedEmail
                )
            );
        }

        if ($config['dns_enabled']) {
            $dns = self::validateMx($domain);
            if ($dns['status'] === 'invalid') {
                return self::cacheResult(
                    $email,
                    self::result(
                        false,
                        'domain',
                        'El dominio de este correo no parece válido. Revisa la dirección e inténtalo nuevamente.',
                        $normalizedEmail
                    )
                );
            }
        }

        if ($config['api_enabled'] && $config['api_url'] !== '') {
            $external = self::verifyExternal($normalizedEmail, $settings, $config);
            if ($external['status'] === 'invalid') {
                return self::cacheResult(
                    $email,
                    self::result(
                        false,
                        'mailbox',
                        'Este correo no parece estar disponible para recibir mensajes. Revisa la dirección e inténtalo nuevamente.',
                        $normalizedEmail
                    )
                );
            }
        }

        return self::cacheResult(
            $email,
            self::result(true, 'valid', 'Correo válido', $normalizedEmail)
        );
    }

    public static function validateSyntax(string $email): array
    {
        $email = trim($email);

        if (
            $email === ''
            || strlen($email) > 180
            || preg_match('/\s/', $email)
            || substr_count($email, '@') !== 1
        ) {
            return self::result(
                false,
                'format',
                'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
                $email
            );
        }

        [$local, $domain] = explode('@', $email, 2);
        $domain = strtolower(trim($domain));
        $normalizedEmail = $local.'@'.$domain;

        if (
            $local === ''
            || $domain === ''
            || str_starts_with($local, '.')
            || str_ends_with($local, '.')
            || str_contains($local, '..')
            || str_contains($domain, '..')
            || !str_contains($domain, '.')
            || !preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+$/', $local)
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)
            || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)
        ) {
            return self::result(
                false,
                'format',
                'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
                $normalizedEmail
            );
        }

        return [
            'ok' => true,
            'status' => 'syntax_valid',
            'message' => '',
            'email' => $normalizedEmail,
            'domain' => $domain,
            'suggestion' => null,
        ];
    }

    private static function suggestEmail(string $email): ?string
    {
        [$local, $domain] = explode('@', $email, 2);
        $domain = strtolower($domain);

        if (isset(self::DOMAIN_TYPOS[$domain])) {
            return $local.'@'.self::DOMAIN_TYPOS[$domain];
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach (self::COMMON_DOMAINS as $candidate) {
            $distance = levenshtein($domain, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        if ($best !== null && $bestDistance > 0 && $bestDistance <= 2) {
            return $local.'@'.$best;
        }

        return null;
    }

    private static function isDisposableDomain(string $domain): bool
    {
        $domains = require __DIR__.'/../config/disposable-email-domains.php';
        if (!is_array($domains)) {
            $domains = [];
        }

        $domain = strtolower($domain);
        foreach ($domains as $blocked) {
            $blocked = strtolower(trim((string) $blocked));
            if ($blocked === '') {
                continue;
            }
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        $signals = [
            '10minutemail',
            'tempmail',
            'temp-mail',
            'throwaway',
            'guerrillamail',
            'mailinator',
            'yopmail',
            'dispostable',
            'fakeinbox',
            'trashmail',
        ];

        foreach ($signals as $signal) {
            if (str_contains($domain, $signal)) {
                return true;
            }
        }

        return false;
    }

    private static function validateMx(string $domain): array
    {
        if (!function_exists('checkdnsrr') && !function_exists('dns_get_record')) {
            return ['status' => 'unavailable'];
        }

        try {
            if (function_exists('dns_get_record')) {
                $mx = self::dnsQuery($domain, DNS_MX);
                if ($mx['status'] === 'unavailable') {
                    return ['status' => 'unavailable'];
                }
                if ($mx['records'] !== []) {
                    return ['status' => 'valid'];
                }

                $domainRecords = self::dnsQuery($domain, DNS_A | DNS_AAAA | DNS_NS);
                if ($domainRecords['status'] === 'unavailable') {
                    return ['status' => 'unavailable'];
                }

                return ['status' => 'invalid'];
            }

            return @checkdnsrr($domain, 'MX')
                ? ['status' => 'valid']
                : ['status' => 'invalid'];
        } catch (Throwable $e) {
            error_log('[IZZY Email Validation] DNS verification unavailable: '.$e->getMessage());
            return ['status' => 'unavailable'];
        }
    }

    private static function dnsQuery(string $domain, int $type): array
    {
        $warning = null;
        set_error_handler(static function(int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            $records = dns_get_record($domain, $type);
        } finally {
            restore_error_handler();
        }

        if ($records === false) {
            if ($warning !== null) {
                error_log('[IZZY Email Validation] DNS lookup unavailable: '.$warning);
            }
            return ['status' => 'unavailable', 'records' => []];
        }

        return [
            'status' => 'ok',
            'records' => is_array($records) ? $records : [],
        ];
    }

    private static function verifyExternal(string $email, array $settings, array $config): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 'unavailable'];
        }

        $url = $config['api_url'];
        $token = '';

        try {
            if ($config['api_token_stored']) {
                $token = secret_decrypt((string) ($settings['email_validation_api_token'] ?? ''));
            }
        } catch (Throwable $e) {
            error_log('[IZZY Email Validation] External API token unavailable: '.$e->getMessage());
            return ['status' => 'unavailable'];
        }

        $containsPlaceholder = str_contains($url, '{email}');
        if ($containsPlaceholder) {
            $url = str_replace('{email}', rawurlencode($email), $url);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $url)) {
            return ['status' => 'unavailable'];
        }

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['status' => 'unavailable'];
            }

            $headers = ['Accept: application/json'];
            if ($token !== '') {
                $headers[] = 'Authorization: Bearer '.$token;
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_HTTPHEADER => $headers,
            ];

            if (!$containsPlaceholder) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                $options[CURLOPT_POSTFIELDS] = json_encode(['email' => $email], JSON_UNESCAPED_SLASHES);
            }

            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($raw) || $status < 200 || $status >= 300) {
                error_log('[IZZY Email Validation] External API unavailable. HTTP '.$status.($error !== '' ? ' cURL: '.$error : ''));
                return ['status' => 'unavailable'];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['status' => 'unavailable'];
            }

            return self::interpretExternalResult($decoded);
        } catch (Throwable $e) {
            error_log('[IZZY Email Validation] External API failed: '.$e->getMessage());
            return ['status' => 'unavailable'];
        }
    }

    private static function interpretExternalResult(array $payload): array
    {
        $sources = [$payload];
        foreach (['data', 'result', 'email'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $sources[] = $payload[$key];
            }
        }

        foreach ($sources as $source) {
            foreach (['deliverable', 'is_deliverable', 'valid', 'is_valid'] as $key) {
                if (array_key_exists($key, $source) && is_bool($source[$key])) {
                    return ['status' => $source[$key] ? 'valid' : 'invalid'];
                }
            }

            foreach (['status', 'result', 'verdict', 'state'] as $key) {
                if (!isset($source[$key]) || !is_scalar($source[$key])) {
                    continue;
                }

                $value = strtolower(trim((string) $source[$key]));
                if (in_array($value, ['deliverable', 'valid', 'ok', 'verified', 'safe'], true)) {
                    return ['status' => 'valid'];
                }
                if (in_array($value, ['undeliverable', 'invalid', 'rejected', 'bad'], true)) {
                    return ['status' => 'invalid'];
                }
                if (in_array($value, ['unknown', 'risky', 'catch_all', 'catch-all'], true)) {
                    return ['status' => 'unavailable'];
                }
            }
        }

        return ['status' => 'unavailable'];
    }

    private static function result(
        bool $ok,
        string $status,
        string $message,
        string $email,
        ?string $suggestion = null
    ): array {
        return [
            'ok' => $ok,
            'status' => $status,
            'message' => $message,
            'email' => $email,
            'suggestion' => $suggestion,
        ];
    }

    private static function cachedResult(string $email): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $email === '') {
            return null;
        }

        $cache = $_SESSION['izzy_email_validation_cache'] ?? [];
        if (!is_array($cache)) {
            return null;
        }

        $key = hash('sha256', strtolower(trim($email)));
        $entry = $cache[$key] ?? null;
        if (!is_array($entry) || (int) ($entry['expires'] ?? 0) < time()) {
            return null;
        }

        return is_array($entry['result'] ?? null) ? $entry['result'] : null;
    }

    private static function cacheResult(string $email, array $result): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $email === '') {
            return $result;
        }

        $cache = $_SESSION['izzy_email_validation_cache'] ?? [];
        if (!is_array($cache)) {
            $cache = [];
        }

        foreach ($cache as $key => $entry) {
            if (!is_array($entry) || (int) ($entry['expires'] ?? 0) < time()) {
                unset($cache[$key]);
            }
        }

        $key = hash('sha256', strtolower(trim($email)));
        $cache[$key] = [
            'expires' => time() + 600,
            'result' => $result,
        ];

        if (count($cache) > 20) {
            $cache = array_slice($cache, -20, null, true);
        }

        $_SESSION['izzy_email_validation_cache'] = $cache;
        return $result;
    }
}
