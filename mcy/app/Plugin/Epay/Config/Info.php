<?php
declare(strict_types=1);

use Kernel\Plugin\Const\Plugin;

return [
    Plugin::NAME => '易支付',
    Plugin::AUTHOR => '荔枝',
    Plugin::DESCRIPTION => '使用本插件，你可以轻松接入市面上所有基于"易支付"协议的支付平台。',
    Plugin::VERSION => '1.1.0',
    Plugin::ARCH => Plugin::ARCH_CLI | Plugin::ARCH_FPM,
    Plugin::TYPE => Plugin::TYPE_PAY
];