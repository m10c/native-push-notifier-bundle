<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Psr\Cache\CacheItemInterface;
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

final class FcmTransport extends AbstractTransport implements TexterInterface
{
    protected const HOST = 'fcm.googleapis.com';

    public function __construct(
        private readonly string $clientEmail,
        #[\SensitiveParameter]
        private readonly string $privateKey,
        private readonly string $projectId,
        private readonly CacheInterface $cache,
        protected ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('fcm://%s?projectId=%s', $this->getEndpoint(), $this->projectId);
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

        foreach ($options->data as $key => $datum) {
            if (!is_string($datum)) {
                throw new InvalidArgumentException("FCM only supports string keys & values, received bad data for $key");
            }
        }

        $endpoint = sprintf(
            'https://%s/v1/projects/%s/messages:send',
            $this->getEndpoint(),
            $this->projectId
        );

        $accessToken = $this->cache->get('m10c.native_push_notifier.fcm.access_token', function (CacheItemInterface $item) {
            $item->expiresAfter(3540); // 59 min

            return $this->getAccessToken();
        });

        if (!$this->client) {
            throw new InvalidArgumentException(sprintf('The "%s" transport requires an HttpClient.', __CLASS__));
        }

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $message->getSubject(),
                        'body' => $message->getContent(),
                    ],
                    'data' => $options->data,
                ],
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote FCM server.', $response, 0, $e);
        }

        $res = $response->toArray(throw: false);

        if (404 === $statusCode && 'UNREGISTERED' === $res['error']['details'][0]['errorCode']) {
            throw new TokenUnregisteredException(token: $token, transport: 'fcm');
        }

        if (200 !== $statusCode) {
            throw new TransportException("Unable to send the Push, FCM responded with {$statusCode}.", $response);
        }

        $responseArr = $response->toArray();
        if (!isset($responseArr['name']) || !str_contains($responseArr['name'], '/messages/')) {
            throw new TransportException('Unexpected FCM response when sending the push', $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId(explode('/messages/', $responseArr['name'])[1]);

        return $sentMessage;
    }

    private function getAccessToken(): string
    {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $credentials = new ServiceAccountCredentials($scopes, [
            'client_email' => $this->clientEmail,
            'private_key' => $this->privateKey,
        ]);

        $accessToken = $credentials->fetchAuthToken()['access_token'];
        if (!is_string($accessToken)) {
            throw new \Exception("Google didn't return a valid access token");
        }

        return $accessToken;
    }
}
