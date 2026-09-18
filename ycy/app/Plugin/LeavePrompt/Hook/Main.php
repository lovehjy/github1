<?php

declare(strict_types=1);

namespace App\Plugin\LeavePrompt\Hook;

use App\Controller\Base\View\UserPlugin;
use Kernel\Annotation\Hook;

class Main extends UserPlugin
{
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_HEADER)]
    public function header()
    {
        $title = getPluginConfig('LeavePrompt')['title'];
        if (empty($title)) {
            $title = "(つェ⊂)我藏好了哦 -  Issuing a card";
        }
        echo "<script>document.addEventListener('visibilitychange',function(){if(document.visibilityState=='hidden'){normal_title=document.title;document.title='{$title}';}else{document.title=normal_title;}});</script>";
    }
}