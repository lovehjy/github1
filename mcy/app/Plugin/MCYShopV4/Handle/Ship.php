<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV4\Handle;

use App\Plugin\MCYShopV4\Util\Http;
use App\Service\Common\Config;
use Kernel\Annotation\Inject;
use Kernel\Exception\ServiceException;

class Ship extends \Kernel\Plugin\Abstract\Ship
{

    #[Inject]
    private Config $_config;

    /**
     * @return string
     * @throws ServiceException
     * @throws \ReflectionException
     */
    public function delivery(): string
    {
        $post = [
            "sku_id" => $this->options["id"],
            "quantity" => $this->order->quantity,
            "trade_no" => $this->order->trade_no,
            "async_url" => $this->_config->getAsyncUrl() . $this->plugin->routeUrl . "/async/order.{$this->order->trade_no}"
        ];

        $widgets = json_decode((string)$this->order->widget, true) ?: [];

        foreach ($widgets as $key => $widget) {
            $post[$key] = $widget['value'];
        }

        $request = Http::inst()->request($this->config, "/plugin/open-api/trade", $post);

        $this->order->status = (int)$request['status'];
        $this->order->save();

        return $request['contents'] ?? "发货失败#0";
    }

    /**
     * @return int|string
     * @throws \ReflectionException
     */
    public function stock(): int|string
    {
        try {
            if (!isset($this->options["id"])) {
                return 0;
            }
            $post = Http::inst()->request($this->config, "/plugin/open-api/sku/stock", ["sku_id" => $this->options['id']]);
            return $post['stock'] ?? 0;
        } catch (\Throwable $e) {
            $this->plugin->log("[{$this->item->name}({$this->sku->name})]" . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param int $quantity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasEnoughStock(int $quantity = 1): bool
    {
        try {
            if (!isset($this->options["id"])) {
                return false;
            }
            $post = Http::inst()->request($this->config, "/plugin/open-api/sku/state", ["sku_id" => $this->options['id'], "quantity" => $quantity]);
            return $post['state'] ?? false;
        } catch (\Throwable $e) {
            $this->plugin->log("[{$this->item->name}({$this->sku->name})]" . $e->getMessage());
            return false;
        }
    }
}