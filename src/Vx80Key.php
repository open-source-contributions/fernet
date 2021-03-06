<?php

/*
 * This file is part of the MNC\Fernet project.
 * (c) Matías Navarro-Carter <mnavarrocarter@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MNC\Fernet;

use InvalidArgumentException;
use MNC\Fernet\Random\PhpRandomSource;
use MNC\Fernet\Random\RandomSource;
use function MNC\Fernet\UrlBase64\decode;
use function MNC\Fernet\UrlBase64\encode;

/**
 * Class Vx80Key.
 */
class Vx80Key
{
    private const FLAGS = OPENSSL_ZERO_PADDING + OPENSSL_RAW_DATA;

    /**
     * @var string
     */
    private $signingKey;
    /**
     * @var string
     */
    private $encryptionKey;

    /**
     * @param RandomSource|null $randomSource
     *
     * @return Vx80Key
     *
     * @throws Random\EntropyError
     */
    public static function random(RandomSource $randomSource = null): Vx80Key
    {
        $randomSource = $randomSource ?? new PhpRandomSource();

        return new self($randomSource->read(16), $randomSource->read(16));
    }

    /**
     * @param string $key
     *
     * @return Vx80Key
     *
     * @throws InvalidArgumentException invalid key provided
     */
    public static function fromString(string $key): Vx80Key
    {
        $bytes = decode($key);
        if (strlen($bytes) !== 32) {
            throw new InvalidArgumentException('Invalid key provided. Key must be 32 bytes encoded in base64 (url-safe)');
        }
        [$signingKey, $encryptionKey] = str_split($bytes, 16);

        return new self($signingKey, $encryptionKey);
    }

    /**
     * Key constructor.
     *
     * @param string $encryptionKey
     * @param string $signingKey
     */
    private function __construct(string $signingKey, string $encryptionKey)
    {
        $this->signingKey = $signingKey;
        $this->encryptionKey = $encryptionKey;
        $this->guard();
    }

    /**
     * @param string $data
     * @param string $iv
     *
     * @return string
     */
    public function encrypt(string $data, string $iv): string
    {
        $cipher = openssl_encrypt($data, 'aes-128-cbc', $this->encryptionKey, self::FLAGS, $iv);
        if ($cipher === false) {
            throw new InvalidArgumentException('Message length must be a multiple of 16 bytes');
        }

        return $cipher;
    }

    /**
     * @param string $cipher
     * @param string $iv
     *
     * @return string
     */
    public function decrypt(string $cipher, string $iv): string
    {
        $message = openssl_decrypt($cipher, 'aes-128-cbc', $this->encryptionKey, self::FLAGS, $iv);
        if ($message === false) {
            throw new InvalidArgumentException('Invalid decryption');
        }

        return $message;
    }

    /**
     * @param string $data
     *
     * @return string
     */
    public function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->signingKey, true);
    }

    private function guard(): void
    {
        if (strlen($this->encryptionKey) !== 16) {
            throw new InvalidArgumentException('Encryption key must be 128 bits (16 bytes)');
        }
        if (strlen($this->signingKey) !== 16) {
            throw new InvalidArgumentException('Signing key must be 128 bits (16 bytes)');
        }
    }

    public function toString(): string
    {
        return encode($this->signingKey.$this->encryptionKey);
    }
}
