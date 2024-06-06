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

final class FcmTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        private readonly CacheInterface $cache,
        ?EventDispatcherInterface $dispatcher = null,
        ?HttpClientInterface $client = null
    ) {
        parent::__construct($dispatcher, $client);
    }

    public function create(Dsn $dsn): FcmTransport
    {
        $scheme = $dsn->getScheme();

        if ('fcm' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'fcm', $this->getSupportedSchemes());
        }

        $clientEmail = $dsn->getUser();
        $privateKey = $dsn->getPassword();
        $projectId = $dsn->getRequiredOption('project_id');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if (!$clientEmail) {
            throw new IncompleteDsnException('Username (client email) is required');
        }
        if (!$privateKey) {
            throw new IncompleteDsnException('Password (private key) is required');
        }
        if (!$projectId || !is_string($projectId)) {
            throw new IncompleteDsnException('project_id is required');
        }

        return (new FcmTransport($clientEmail, $privateKey, $projectId, $this->cache, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['fcm'];
    }
}
