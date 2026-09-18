<?php
declare (strict_types=1);

return [
    \Kernel\Plugin\Handle\ForeignShip::class => \App\Plugin\MCYShopV3\Handle\ForeignShip::class,
    \Kernel\Plugin\Handle\Ship::class => \App\Plugin\MCYShopV3\Handle\Ship::class,
];