<?php
namespace InvoiceTool;

/** AES-256-GCM encryption for secrets stored in the DB (users' Jira tokens). */
class Crypto
{
    /** @var string */
    private $key;

    public function __construct(string $appKey)
    {
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('app_key must be at least 32 characters (set it in config.local.php).');
        }
        $this->key = hash('sha256', $appKey, true);
    }

    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    /** Encrypt a money value (rates, amounts) so it isn't readable in the DB. */
    public function encryptNumber(float $value): string
    {
        return $this->encrypt((string) $value);
    }

    /** Decrypt a money value; plain numbers saved before encryption was added are read as-is. */
    public function decryptNumber($stored): float
    {
        if ($stored === null || $stored === '') {
            return 0.0;
        }
        if (is_numeric($stored)) {
            return (float) $stored;
        }
        return (float) $this->decrypt((string) $stored);
    }

    public function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
