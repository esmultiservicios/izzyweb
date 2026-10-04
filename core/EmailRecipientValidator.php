<?php
declare(strict_types=1);

/**
 * Central outbound-recipient validation for IZZY.
 *
 * This validator intentionally uses only local/native checks. It does not call
 * paid verification APIs and it does not probe remote SMTP servers. DNS errors
 * that look temporary/unavailable fail open so legitimate customers are not
 * blocked just because the resolver is having a problem.
 */
final class EmailRecipientValidator
{
    private const DOMAIN_TYPOS = [
        'gmail.con' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yahho.com' => 'yahoo.com',
    ];

    private const OBVIOUSLY_FAKE_ADDRESSES = [
        'alguien@algo.com',
        'prueba@prueba.com',
        'test@test.com',
        'usuario@example.com',
        'correo@correo.com',
    ];

    private const RESERVED_EXAMPLE_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
    ];

    /** @var array<string,array{status:string,reason:string}> */
    private static array $dnsCache = [];

    /**
     * @return array{
     *   valid:bool,
     *   email:string,
     *   reason:string,
     *   code:string,
     *   suggestion:?string,
     *   dns_status:string
     * }
     */
    public static function validate(string $email): array
    {
        $normalized = self::normalize($email);

        if ($normalized === '') {
            return self::invalid('', 'empty', 'El destinatario está vacío.');
        }

        if (preg_match('/[\r\n\x00-\x1F\x7F]/', $normalized)) {
            return self::invalid($normalized, 'invalid_characters', 'El correo contiene caracteres no permitidos.');
        }

        if (preg_match('/\s/', $normalized)) {
            return self::invalid($normalized, 'spaces', 'El correo contiene espacios.');
        }

        if (strlen($normalized) > 254 || substr_count($normalized, '@') !== 1) {
            return self::invalid($normalized, 'format', 'El formato del correo no es válido.');
        }

        [$local, $domain] = explode('@', $normalized, 2);
        $domain = strtolower($domain);
        $normalized = $local.'@'.$domain;

        if (!self::validSyntax($local, $domain, $normalized)) {
            return self::invalid($normalized, 'format', 'El formato del correo no es válido.');
        }

        $lowerEmail = strtolower($normalized);
        if (
            in_array($lowerEmail, self::OBVIOUSLY_FAKE_ADDRESSES, true)
            || in_array($domain, self::RESERVED_EXAMPLE_DOMAINS, true)
        ) {
            return self::invalid($normalized, 'placeholder', 'El correo parece ser una dirección ficticia o de ejemplo.');
        }

        if (isset(self::DOMAIN_TYPOS[$domain])) {
            $suggestion = $local.'@'.self::DOMAIN_TYPOS[$domain];
            return self::invalid(
                $normalized,
                'domain_typo',
                'El dominio parece contener un error de escritura. Sugerencia: '.$suggestion.'.',
                $suggestion
            );
        }

        if (self::isDisposableDomain($domain)) {
            return self::invalid($normalized, 'temporary', 'El dominio corresponde a un servicio de correo temporal o desechable.');
        }

        $dns = self::validateDns($domain);
        if ($dns['status'] === 'invalid') {
            return self::invalid($normalized, 'dns', $dns['reason'], null, 'invalid');
        }

        return [
            'valid' => true,
            'email' => $normalized,
            'reason' => $dns['status'] === 'unavailable'
                ? 'Correo aceptado; la verificación DNS no estuvo disponible temporalmente.'
                : 'Correo válido para intento de entrega.',
            'code' => 'valid',
            'suggestion' => null,
            'dns_status' => $dns['status'],
        ];
    }

    public static function normalize(string $email): string
    {
        $email = trim($email);
        if ($email === '' || substr_count($email, '@') !== 1) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = strtolower(trim($domain));

        if (function_exists('idn_to_ascii') && $domain !== '') {
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = @idn_to_ascii($domain, $flags, $variant);
            if (is_string($ascii) && $ascii !== '') {
                $domain = strtolower($ascii);
            }
        }

        return $local.'@'.$domain;
    }

    private static function validSyntax(string $local, string $domain, string $email): bool
    {
        if (
            $local === ''
            || $domain === ''
            || strlen($local) > 64
            || strlen($domain) > 253
            || str_starts_with($local, '.')
            || str_ends_with($local, '.')
            || str_contains($local, '..')
            || str_contains($domain, '..')
            || !str_contains($domain, '.')
        ) {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+$/', $local)) {
            return false;
        }

        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function isDisposableDomain(string $domain): bool
    {
        $path = __DIR__.'/../config/disposable-email-domains.php';
        $domains = is_file($path) ? require $path : [];
        if (!is_array($domains)) {
            $domains = [];
        }

        foreach ($domains as $blocked) {
            $blocked = strtolower(trim((string) $blocked));
            if ($blocked !== '' && ($domain === $blocked || str_ends_with($domain, '.'.$blocked))) {
                return true;
            }
        }

        foreach ([
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
        ] as $signal) {
            if (str_contains($domain, $signal)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{status:string,reason:string} */
    private static function validateDns(string $domain): array
    {
        if (isset(self::$dnsCache[$domain])) {
            return self::$dnsCache[$domain];
        }

        if (!function_exists('checkdnsrr') && !function_exists('dns_get_record')) {
            return self::$dnsCache[$domain] = [
                'status' => 'unavailable',
                'reason' => 'Las funciones DNS de PHP no están disponibles en este servidor.',
            ];
        }

        try {
            $mx = self::hasDnsRecord($domain, 'MX', defined('DNS_MX') ? DNS_MX : null);
            if ($mx === true) {
                return self::$dnsCache[$domain] = [
                    'status' => 'valid',
                    'reason' => 'El dominio tiene registros MX.',
                ];
            }

            // RFC-compatible fallback: a domain without explicit MX may still
            // receive mail through an A/AAAA host. Do not use NS as a delivery
            // signal because nameservers alone do not imply mail acceptance.
            $a = self::hasDnsRecord($domain, 'A', defined('DNS_A') ? DNS_A : null);
            $aaaa = self::hasDnsRecord($domain, 'AAAA', defined('DNS_AAAA') ? DNS_AAAA : null);

            if ($a === true || $aaaa === true) {
                return self::$dnsCache[$domain] = [
                    'status' => 'valid',
                    'reason' => 'El dominio no publica MX, pero tiene fallback A/AAAA.',
                ];
            }

            if ($mx === null || $a === null || $aaaa === null) {
                return self::$dnsCache[$domain] = [
                    'status' => 'unavailable',
                    'reason' => 'La consulta DNS no pudo completarse de forma confiable.',
                ];
            }

            return self::$dnsCache[$domain] = [
                'status' => 'invalid',
                'reason' => 'El dominio no publica registros MX ni fallback A/AAAA válidos para recibir correo.',
            ];
        } catch (Throwable $e) {
            error_log('[IZZY Email Recipient Validation] DNS unavailable for '.$domain.': '.$e->getMessage());
            return self::$dnsCache[$domain] = [
                'status' => 'unavailable',
                'reason' => 'La consulta DNS falló temporalmente.',
            ];
        }
    }

    private static function hasDnsRecord(string $domain, string $recordName, ?int $recordType): ?bool
    {
        if (function_exists('dns_get_record') && $recordType !== null) {
            $warning = null;
            set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
                $warning = $message;
                return true;
            });

            try {
                $records = dns_get_record($domain, $recordType);
            } finally {
                restore_error_handler();
            }

            if ($records === false) {
                if ($warning !== null) {
                    error_log('[IZZY Email Recipient Validation] DNS '.$recordName.' lookup unavailable for '.$domain.': '.$warning);
                }
                return null;
            }

            return is_array($records) && $records !== [];
        }

        if (function_exists('checkdnsrr')) {
            return @checkdnsrr($domain, $recordName);
        }

        return null;
    }

    /**
     * @return array{valid:false,email:string,reason:string,code:string,suggestion:?string,dns_status:string}
     */
    private static function invalid(
        string $email,
        string $code,
        string $reason,
        ?string $suggestion = null,
        string $dnsStatus = 'not_checked'
    ): array {
        return [
            'valid' => false,
            'email' => $email,
            'reason' => $reason,
            'code' => $code,
            'suggestion' => $suggestion,
            'dns_status' => $dnsStatus,
        ];
    }
}
