<?php
declare(strict_types=1);

namespace App\View\User\Theme\Tokyo;

use App\Consts\Render;

interface Config
{
    const INFO = [
        "NAME" => "东京",
        "AUTHOR" => "荔枝",
        "VERSION" => "2.0.2",
        "WEB_SITE" => "#",
        "DESCRIPTION" => "你好，东京！",
        "RENDER" => Render::ENGINE_SMARTY
    ];

    const SUBMIT = [
        [
            "title" => "ICP备案号",
            "name" => "icp",
            "type" => "input",
            "placeholder" => "填写后将会在店铺底部显示ICP备案号，不填写则不显示。"
        ],
        [
            "title" => "销量显示",
            "name" => "show_sold",
            "type" => "switch",
            "text" => "开启"
        ],
    ];

    const THEME = [
        "INDEX" => "Index.html",
        "ITEM" => "Item.html",
        "QUERY" => "Query.html",
        "CLOSED" => "Closed.html"
    ];
}
