<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class NativePushOptions implements MessageOptionsInterface
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        public array $data = [],
        public ?NativePushPriority $priority = null,
        public ?string $token = null,
    ) {
    }

    /**
     * @return array{data: mixed[], priority: ?NativePushPriority, token: ?string}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'priority' => $this->priority,
            'token' => $this->token,
        ];
    }

    public function getRecipientId(): ?string
    {
        return $this->token;
    }

    public function token(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function priority(NativePushPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Using string:string, as in the lowest common denominator of FCM:
     * https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#Message.FIELDS.data
     * 
     * @param array<string, string> $data
     */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
