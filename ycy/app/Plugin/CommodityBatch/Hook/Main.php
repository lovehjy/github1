<?php
declare(strict_types=1);

namespace App\Plugin\CommodityBatch\Hook;

use App\Controller\Base\View\ManagePlugin;
use Kernel\Annotation\Hook;
use Kernel\Exception\ViewException;

class Main extends ManagePlugin
{
    /**
     * 往「商品」列表页工具栏注入批量操作按钮。
     * @throws ViewException
     */
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_COMMODITY_TOOLBAR)]
    public function toolbar(): void
    {
        echo $this->render(null, "Toolbar.html");
    }
}
