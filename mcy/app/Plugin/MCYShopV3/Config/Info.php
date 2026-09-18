<?php
declare(strict_types=1);

use Kernel\Plugin\Const\Plugin;

return [
    Plugin::NAME => '异次元V3.0货源接入',
    Plugin::AUTHOR => '荔枝',
    Plugin::DESCRIPTION => '通过此插件，你可以轻松对接异次元V3.0货源至本系统。',
    Plugin::VERSION => '1.1.1',
    Plugin::ARCH => Plugin::ARCH_CLI | Plugin::ARCH_FPM,
    Plugin::TYPE => Plugin::TYPE_SHIP
];