<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApnsTransport extends AbstractTransport implements TexterInterface
{
    protected const HOST = 'api.push.apple.com';

    public function __construct(
        /**
         * @var non-empty-string
         */
        private readonly string $keyId,
        /**
         * @var non-empty-string
         */
        #[\SensitiveParameter]
        private readonly string $privateKey,
        /**
         * @var non-empty-string
         */
        private readonly string $teamId,
        /**
         * @var non-empty-string
         */
        private readonly string $topic,
        private readonly CacheInterface $cache,
        protected ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('apns://%s?team_id=%s&topic=%s', $this->getEndpoint(), $this->teamId, $this->topic);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        $options = $message->getOptions();
        if (!$options || !($options instanceof NativePushOptions) || !($token = $options->getRecipientId())) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "token" option to be set.', __CLASS__));
        }

        $endpoint = sprintf(
            'https://%s/3/device/%s',
            $this->getEndpoint(),
            $token
        );

        $jwt = $this->cache->get('m10c.native_push_notifier.apns.jwt', function (CacheItemInterface $item) {
            $item->expiresAfter(3540); // 59 min

            return $this->getJwt();
        });

        if (!$this->client) {
            throw new InvalidArgumentException(sprintf('The "%s" transport requires an HttpClient.', __CLASS__));
        }

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'apns-topic' => $this->topic,
                'authorization' => "bearer {$jwt}",
            ],
            'json' => [
                'aps' => [
                    'alert' => [
                        'title' => $message->getSubject(),
                        'body' => $message->getContent(),
                    ],
                ],
                'data' => $options->data,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote APNs server.', $response, 0, $e);
        }

        if ($statusCode > 299) {
            $res = $response->toArray(throw: false);
            if ('Unregistered' === ($res['reason'] ?? null)) {
                $unregisteredAt = $res['timestamp']
                    ? \DateTimeImmutable::createFromFormat('U', sprintf('%d', floor($res['timestamp'] / 1000)))
                    : null;
                throw new TokenUnregisteredException(token: $token, transport: 'apns', unregisteredAt: $unregisteredAt ?: null);
            }
        }

        if (200 !== $statusCode) {
            throw new TransportException("Unable to send the Push, APNs responded with {$statusCode}.", $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);

        return $sentMessage;
    }

    private function getJwt(): string
    {
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->privateKey),
            InMemory::plainText($this->privateKey)
        );

        // Remove milliseconds
        $now = new DatePoint();
        $issuedAt = $now->modify($now->format('Y-m-d H:i:s'));

        $token = $config->builder()
            ->issuedBy($this->teamId)
            ->issuedAt($issuedAt)
            ->withHeader('alg', 'ES256')
            ->withHeader('kid', $this->keyId)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}
