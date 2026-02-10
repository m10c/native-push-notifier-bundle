<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle\Tests;

use M10c\NativePushNotifierBundle\NativePushOptions;
use M10c\NativePushNotifierBundle\NativePushPriority;
use PHPUnit\Framework\TestCase;

class NativePushOptionsTest extends TestCase
{
    public function testConstructor(): void
    {
        $options = new NativePushOptions(
            data: ['foo' => 'bar'],
            priority: NativePushPriority::High,
            token: 'abc123',
            collapseKey: 'new_message',
        );

        $this->assertEquals([
            'data' => ['foo' => 'bar'],
            'priority' => NativePushPriority::High,
            'token' => 'abc123',
            'collapseKey' => 'new_message',
        ], $options->toArray());
    }

    public function testSettersGetters(): void
    {
        $options = new NativePushOptions();
        $options->data(['foo' => 'bar']);
        $options->priority(NativePushPriority::High);
        $options->token('abc123');
        $options->collapseKey('new_message');

        $this->assertEquals(['foo' => 'bar'], $options->data);
        $this->assertEquals(NativePushPriority::High, $options->priority);
        $this->assertEquals('abc123', $options->token);
        $this->assertEquals('abc123', $options->getRecipientId());
        $this->assertEquals('new_message', $options->collapseKey);
        $this->assertEquals([
            'data' => ['foo' => 'bar'],
            'priority' => NativePushPriority::High,
            'token' => 'abc123',
            'collapseKey' => 'new_message',
        ], $options->toArray());
    }
}
