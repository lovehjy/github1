<?php
declare(strict_types=1);

use App\Consts\Plugin;

return [
    Plugin::NAME => '商品批量助手',
    Plugin::AUTHOR => 'jiuchi',
    Plugin::WEB_SITE => '#',
    Plugin::DESCRIPTION => '在商品列表工具栏新增批量操作：勾选多个商品后，可一键批量修改「单价 / 会员价 / 成本价」（支持设为固定值、按比例%、按金额加减）。启用后在「商品」页面工具栏可见。',
    Plugin::VERSION => '1.0.0'
];
