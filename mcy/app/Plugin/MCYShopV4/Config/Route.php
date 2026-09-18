<?php
declare (strict_types=1);


use App\Plugin\MCYShopV4\Controller\Async;

return [
    [
        "name" => "订单完成通知",
        "route" => "/async/order",
        "class" => Async::class,
        "action" => "order",
        "method" => "POST"
    ]
];