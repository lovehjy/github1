<?php
declare(strict_types=1);

use Kernel\Plugin\Const\Plugin;

return [
    Plugin::NAME => '萌次元V4.0货源接入',
    Plugin::AUTHOR => '荔枝',
    Plugin::DESCRIPTION => '通过此插件，你可以轻松对接萌次元V4.0货源至本系统。',
    Plugin::VERSION => '1.0.4',
    Plugin::ARCH => Plugin::ARCH_CLI | Plugin::ARCH_FPM,
    Plugin::TYPE => Plugin::TYPE_SHIP
];