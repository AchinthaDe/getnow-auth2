<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Auth;
use League\OAuth2\Server\ResourceServer;
use RuntimeException;

trait InteractsWithPassportKeys
{
    protected function setUpInteractsWithPassportKeys(): void
    {
        $this->ensurePassportKeys();
    }

    public function ensurePassportKeys(): void
    {
        /** @var string|null $cachedPrivateKey */
        static $cachedPrivateKey = null;
        /** @var string|null $cachedPublicKey */
        static $cachedPublicKey = null;

        if ($cachedPrivateKey === null || $cachedPublicKey === null) {
            $config = [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            $candidates = [
                'C:/tools/php85/extras/ssl/openssl.cnf',
                'C:/Program Files/Git/mingw64/etc/ssl/openssl.cnf',
                'C:/Program Files/Git/usr/ssl/openssl.cnf',
            ];

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $config['config'] = $candidate;
                    break;
                }
            }

            $res = openssl_pkey_new($config);
            if ($res === false) {
                throw new RuntimeException('Failed to generate test RSA key pair: '.(openssl_error_string() ?: 'unknown error'));
            }

            openssl_pkey_export($res, $privateKey, null, $config);
            $details = openssl_pkey_get_details($res);
            if ($details === false) {
                throw new RuntimeException('Failed to get RSA key details.');
            }

            $cachedPrivateKey = $privateKey;
            $cachedPublicKey = $details['key'];
        }

        config([
            'passport.private_key' => $cachedPrivateKey,
            'passport.public_key' => $cachedPublicKey,
        ]);

        app()->forgetInstance(ResourceServer::class);
        Auth::forgetGuards();
    }
}
