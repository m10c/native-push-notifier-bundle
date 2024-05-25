<?php

declare(strict_types=1);

namespace M10c\NativePushNotifierBundle;

enum NativePushPriority: string
{
    case High = 'high';
    case Normal = 'normal';
}
