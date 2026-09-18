<?php
declare(strict_types=1);

use App\Consts\Plugin;

return [
    Plugin::NAME => '订单销毁',
    Plugin::AUTHOR => '荔枝',
    Plugin::WEB_SITE => '#',
    Plugin::DESCRIPTION => '该插件可以删除你想删除的任何订单，启用后在"商品订单"中可以看到。「商品订单」「卡密管理」两个页面各有一个销毁按钮，点击后可以在确认框里勾选是否联动：销毁订单时一并删除该订单的卡密，销毁卡密时一并删除卡密所属的订单。',
    Plugin::VERSION => '1.0.2'
];