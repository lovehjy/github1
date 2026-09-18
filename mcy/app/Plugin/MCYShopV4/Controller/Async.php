<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV4\Controller;

use App\Model\PluginConfig;
use App\Model\RepertoryItem;
use App\Model\RepertoryOrder;
use Kernel\Context\Interface\Response;
use Kernel\Exception\JSONException;
use Kernel\Plugin\Abstract\Controller;
use Kernel\Util\Str;
use Kernel\Waf\Firewall;

class Async extends Controller
{
    /**
     * @return Response
     * @throws JSONException
     * @throws \ReflectionException
     */
    public function order(): Response
    {
        $signature = $this->request->header("ApiSignature");
        $post = $this->request->post();


        if (!isset($post['item_trade_no'])) {
            throw new JSONException("订单不存在#0");
        }

        /**
         * @var RepertoryOrder $repertoryOrder
         */
        $repertoryOrder = RepertoryOrder::query()
            ->where("trade_no", $post['item_trade_no'])
            ->first();

        if (!$repertoryOrder) {
            throw new JSONException("订单不存在#1");
        }

        /**
         * @var RepertoryItem $repertoryItem
         */
        $repertoryItem = RepertoryItem::query()->find($repertoryOrder->repertory_item_id);

        $config = PluginConfig::find($repertoryItem->ship_config_id);

        if (!$config) {
            throw new JSONException("配置不存在#0");
        }

        $cfg = is_array($config?->config) ? $config->config : [];

        if (empty($cfg)) {
            throw new JSONException("配置不存在#1");
        }

        if ($signature != Str::generateSignature($post, $cfg['key'])) {
            throw new JSONException("签名验证错误");
        }

        if (($post['status'] == 1 || $post['status'] == 2) && $repertoryOrder->status != 1) {
            $repertoryOrder->status = $post['status'];
        }

        $repertoryOrder->contents = Firewall::inst()->xssKiller($post["contents"]);
        $repertoryOrder->save();
        return $this->response->json();
    }
}