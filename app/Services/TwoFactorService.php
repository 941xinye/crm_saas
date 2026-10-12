<?php

namespace App\Services;

/**
 * 两步验证（2FA）服务：无第三方依赖的 RFC 6238 TOTP 实现。
 *
 * - Secret 为 Base32（RFC 4648）编码的 32 字符字符串（160 位熵）
 * - 算法 SHA1、时间步长 30 秒、6 位动态码、默认 ±1 窗口容差
 * - 恢复码 8 个（xxxx-xxxx），bcrypt 哈希存储，用后即焚
 */
class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 生成 Base32 编码的 TOTP 密钥（32 字符）。
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * 生成 otpauth URI（认证器 App 扫码用）。
     */
    public function otpauthUri(string $secret, string $email): string
    {
        $issuer = str_replace([':', ' '], ['', '-'], (string) config('app.name', 'CRM'));

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
        );
    }

    /**
     * 校验 6 位动态码，允许 ±window 个时间窗口的时钟偏差。
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = str_replace(' ', '', trim($code));

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $binary = $this->base32Decode($secret);
        if ($binary === '') {
            return false;
        }

        $counter = intdiv(time(), 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->totpAt($binary, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算指定时刻的 6 位动态码（测试与调试用）。
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $binary = $this->base32Decode($secret);
        $counter = intdiv($timestamp ?? time(), 30);

        return $this->totpAt($binary, $counter);
    }

    /**
     * 生成 8 个明文恢复码（xxxx-xxxx），调用方展示后应立即哈希存储。
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2));
        }

        return $codes;
    }

    /**
     * 恢复码 bcrypt 哈希（数据库只存哈希，明文仅展示一次）。
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => bcrypt($code), $codes);
    }

    /**
     * RFC 4226 HOTP：HMAC-SHA1 + 动态截断，返回 6 位十进制码。
     */
    private function totpAt(string $binarySecret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N', 0) . pack('N', $counter), $binarySecret, true);

        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 编码（RFC 4648，无填充）。
     */
    private function base32Encode(string $bytes): string
    {
        $output = '';
        $bitBuffer = 0;
        $bitCount = 0;

        foreach (str_split($bytes) as $byte) {
            $bitBuffer = ($bitBuffer << 8) | ord($byte);
            $bitCount += 8;

            while ($bitCount >= 5) {
                $bitCount -= 5;
                $output .= self::BASE32_ALPHABET[($bitBuffer >> $bitCount) & 0x1F];
            }
        }

        if ($bitCount > 0) {
            $output .= self::BASE32_ALPHABET[($bitBuffer << (5 - $bitCount)) & 0x1F];
        }

        return $output;
    }

    /**
     * Base32 解码（容错处理填充符 =、空格、小写输入）。
     */
    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(str_replace(['=', ' '], '', $encoded));
        $lookup = array_flip(str_split(self::BASE32_ALPHABET));

        $bitBuffer = 0;
        $bitCount = 0;
        $output = '';

        foreach (str_split($encoded) as $char) {
            if (! isset($lookup[$char])) {
                return '';
            }

            $bitBuffer = ($bitBuffer << 5) | $lookup[$char];
            $bitCount += 5;

            if ($bitCount >= 8) {
                $bitCount -= 8;
                $output .= chr(($bitBuffer >> $bitCount) & 0xFF);
            }
        }

        return $output;
    }
}
