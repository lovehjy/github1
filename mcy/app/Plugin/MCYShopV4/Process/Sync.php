<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV4\Process;

use App\Model\PluginConfig;
use App\Model\RepertoryOrder;
use App\Plugin\MCYShopV4\Util\Http;
use Kernel\Annotation\Thread;
use Kernel\Plugin\Abstract\Process;
use Kernel\Waf\Firewall;

#[Thread(name: "mcy.shop.v4.sync", num: 1)]
class Sync extends Process
{

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function handle(): void
    {
        while (true) {
            $repertoryOrder = RepertoryOrder::query()
                ->leftJoin("repertory_item", "repertory_order.repertory_item_id", "=", "repertory_item.id")
                ->where("repertory_order.status", 0)
                ->where("repertory_item.plugin", $this->plugin->name);

            if ($this->plugin->uid == "*") {
                $repertoryOrder = $repertoryOrder->whereNull("repertory_order.user_id");
            } else {
                $repertoryOrder = $repertoryOrder->where("repertory_order.user_id", $this->plugin->uid);
            }
            $repertoryOrder = $repertoryOrder->get();
            foreach ($repertoryOrder as $order) {
                $config = PluginConfig::find($order->ship_config_id);
                if (!$config) {
                    continue;
                }
                try {
                    $post = Http::inst()->request(is_array($config?->config) ? $config->config : [], "/plugin/open-api/query", ["trade_no" => $order->trade_no]);
                    if (isset($post['status'])) {
                        /**
                         * @var RepertoryOrder $od
                         */
                        $od = RepertoryOrder::query()->where("trade_no", $order->trade_no)->first();
                        if ($post['status'] == 1 || $post['status'] == 2) {
                            $od->status = $post['status'];
                        }
                        $od->contents = Firewall::inst()->xssKiller($post["contents"]);
                        $od->save();
                    }
                } catch (\Throwable $e) {
                    $this->plugin->log("同步出错：{$e->getMessage()}");
                }
                sleep(1);
            }
            sleep(10);
        }
    }
}