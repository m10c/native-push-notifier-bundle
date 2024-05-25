<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

final class TokenUnregisteredException extends \Exception
{
    public function __construct(
        public readonly string $token,
        /**
         * @var 'apns'|'fcm' $transport
         */
        public readonly string $transport,
        /**
         * Only APNs provides this.
         */
        public readonly ?\DateTimeImmutable $unregisteredAt = null,
    ) {
        parent::__construct("Token is not registered with {$transport}: {$token}");
    }
}
