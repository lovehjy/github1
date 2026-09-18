<?php
declare(strict_types=1);

namespace App\Plugin\DestroyOrder\Hook;


use App\Controller\Base\View\ManagePlugin;
use Kernel\Annotation\Hook;
use Kernel\Exception\ViewException;

class Main extends ManagePlugin
{

    /**
     * @throws ViewException
     */
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_ORDER_TOOLBAR)]
    public function aide()
    {
        echo $this->render(null, "Aide.html");
    }


    /**
     * @throws ViewException
     */
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_CARD_TOOLBAR)]
    public function cardAide()
    {
        echo $this->render(null, "CardAide.html");
    }

}
