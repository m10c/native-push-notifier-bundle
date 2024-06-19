<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApnsTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        private readonly CacheInterface $cache,
        ?EventDispatcherInterface $dispatcher = null,
        ?HttpClientInterface $client = null
    ) {
        parent::__construct($dispatcher, $client);
    }

    public function create(Dsn $dsn): ApnsTransport
    {
        $scheme = $dsn->getScheme();

        if ('apns' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'apns', $this->getSupportedSchemes());
        }

        $keyId = $dsn->getUser();
        $privateKey = $dsn->getPassword();
        $teamId = $dsn->getRequiredOption('team_id');
        $topic = $dsn->getRequiredOption('topic');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if (!$keyId) {
            throw new IncompleteDsnException('Username (Key ID) is required');
        }
        if (!$privateKey) {
            throw new IncompleteDsnException('Password (private key) is required');
        }
        if (!$teamId || !is_string($teamId)) {
            throw new IncompleteDsnException('team_id is required');
        }
        if (!$topic || !is_string($topic)) {
            throw new IncompleteDsnException('topic is required');
        }

        return (new ApnsTransport($keyId, $privateKey, $teamId, $topic, $this->cache, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['apns'];
    }
}
