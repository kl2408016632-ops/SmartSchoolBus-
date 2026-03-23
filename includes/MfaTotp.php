<?php
/**
 * Minimal RFC6238 TOTP helper (Google Authenticator compatible)
 */
class MfaTotp {
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function buildOtpAuthUri(string $issuer, string $account, string $secret): string {
        $label = rawurlencode($issuer . ':' . $account);
        $issuerParam = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";
    }

    public static function verifyCode(string $secret, string $code, int $window = 1): bool {
        $code = preg_replace('/\D+/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }

        $timeSlice = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::generateCode($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function generateCode(string $secret, ?int $timeSlice = null): string {
        $timeSlice = $timeSlice ?? (int)floor(time() / 30);
        $secretKey = self::base32Decode($secret);

        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashPart = substr($hm, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string {
        $binaryString = '';
        foreach (str_split($data) as $char) {
            $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binaryString, 5);
        $base32 = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $base32 .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $base32;
    }

    private static function base32Decode(string $base32): string {
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
        $binaryString = '';

        foreach (str_split($base32) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = str_split($binaryString, 8);
        $decoded = '';
        foreach ($bytes as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
