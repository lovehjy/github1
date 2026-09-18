<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV3\Handle;

use App\Plugin\MCYShopV3\Util\Http;
use Kernel\Exception\HandleException;

class Ship extends \Kernel\Plugin\Abstract\Ship
{

    /**
     * @return string
     * @throws HandleException
     * @throws \Throwable
     */
    public function delivery(): string
    {
        $post = [
            "shared_code" => $this->options["code"],
            "num" => $this->order->quantity,
            "race" => $this->options["race"] ?? "",
            "request_no" => substr($this->order->trade_no, -19)
        ];
        $widgets = (array)json_decode((string)$this->order->widget, true) ?: [];

        foreach ($widgets as $key => $widget) {
            $post[$key] = $widget['value'];
        }

        $trade = Http::inst()->post($this->plugin, trim($this->config['url'], "/") . "/shared/commodity/trade", $this->config['pid'], $this->config['key'], $post);

        $this->order->status = 1;
        $this->order->save();
        return (string)$trade['secret'];
    }

    /**
     * @return int|string
     * @throws \ReflectionException
     */
    public function stock(): int|string
    {
        try {
            if (!isset($this->options["code"])) {
                return 0;
            }
            if ($this->config['version'] == 0) {
                $post = Http::inst()->post($this->plugin, trim($this->config['url'], "/") . "/shared/commodity/stock", $this->config['pid'], $this->config['key'], [
                    "code" => $this->options["code"],
                    "race" => $this->options["race"] ?? ""
                ]);

                return $post['stock'] ?? 0;
            } else {
                $post = Http::inst()->post($this->plugin, trim($this->config['url'], "/") . "/shared/commodity/inventory", $this->config['pid'], $this->config['key'], [
                    "sharedCode" => $this->options["code"],
                    "race" => $this->options["race"] ?? ""
                ]);

                if (isset($post['delivery_way']) && $post['delivery_way'] == 1) {
                    return "在线发货";
                }
                return $post['count'] ?? 0;
            }
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
            if (!isset($this->options["code"])) {
                return false;
            }
            if ($this->config['version'] == 0) {
                $post = Http::inst()->post($this->plugin, trim($this->config['url'], "/") . "/shared/commodity/stock", $this->config['pid'], $this->config['key'], [
                    "code" => $this->options["code"],
                    "race" => $this->options["race"] ?? ""
                ]);
                return $post['stock'] >= $quantity;
            } else {
                Http::inst()->post($this->plugin, trim($this->config['url'], "/") . "/shared/commodity/inventoryState", $this->config['pid'], $this->config['key'], [
                    "shared_code" => $this->options["code"],
                    "race" => $this->options["race"] ?? "",
                    "num" => $quantity
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            $this->plugin->log("[{$this->item->name}({$this->sku->name})]" . $e->getMessage());
            return false;
        }
    }
}