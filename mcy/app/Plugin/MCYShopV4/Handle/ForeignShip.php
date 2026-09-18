<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV4\Handle;

use App\Plugin\MCYShopV4\Util\Http;
use Kernel\Exception\HandleException;
use Kernel\Exception\ServiceException;
use Kernel\Plugin\Entity\Item;
use Kernel\Plugin\Entity\Sku;
use Kernel\Plugin\Entity\Widget;

class ForeignShip extends \Kernel\Plugin\Abstract\ForeignShip
{


    /**
     * @return Item[]
     * @throws HandleException
     * @throws ServiceException
     * @throws \ReflectionException
     */
    public function getItems(): array
    {
        $list = Http::inst()->request($this->config, "/plugin/open-api/items");

        if (count($list) == 0) {
            throw new HandleException("对方没有任何商品可以对接，请联系对方站长");
        }

        $url = trim($this->config['url'], "/");

        $items = [];

        foreach ($list as $child) {
            $items[] = $this->createItem($url, $child, (string)$this->config['pid']);
        }


        return $items;
    }


    /**
     * @param string $url
     * @param array $child
     * @param string $pid
     * @return Item
     * @throws \ReflectionException
     */
    private function createItem(string $url, array $child, string $pid): Item
    {
        $skus = [];

        foreach ($child['sku'] as $sku) {
            $skuEntity = new Sku($pid . $child['id'] . $sku['id'], $sku['name'], str_starts_with($sku['picture_url'], "http") ? $sku['picture_url'] : $url . $sku['picture_url'], $sku['stock_price']);
            $skuEntity->setOptions([
                "id" => $sku['id']
            ]);
            $skuEntity->setCost($sku['stock_price']);

            $sku['message'] && $skuEntity->setMessage($sku['message']);


            if ($sku['market_control'] == 1) {
                $sku['market_control_min_price'] > $sku['stock_price'] && $skuEntity->setPrice($sku['market_control_min_price']);
                $skuEntity->setMarketControl(true);
                $skuEntity->setMarketControlMinPrice($sku['market_control_min_price'] ?? "0");
                $skuEntity->setMarketControlMaxPrice($sku['market_control_max_price'] ?? "0");
                $skuEntity->setMarketControlLevelMinPrice($sku['market_control_level_min_price'] ?? "0");
                $skuEntity->setMarketControlLevelMaxPrice($sku['market_control_level_max_price'] ?? "0");
                $skuEntity->setMarketControlUserMinPrice($sku['market_control_user_min_price'] ?? "0");
                $skuEntity->setMarketControlUserMaxPrice($sku['market_control_user_max_price'] ?? "0");
                $skuEntity->setMarketControlMinNum((int)$sku['market_control_min_num']);
                $skuEntity->setMarketControlMaxNum((int)$sku['market_control_max_num']);
                $skuEntity->setMarketControlOnlyNum((int)$sku['market_control_only_num']);
            }
            $skus[] = $skuEntity;
        }

        $introduce = preg_replace_callback('/<img[^>]+src=["\']?([^"\'>]+)["\']?[^>]*>/i', function ($matches) use ($url) {
            $originalSrc = $matches[1];
            if (preg_match('/^(http:\/\/|https:\/\/)/i', $originalSrc)) {
                return $matches[0];
            }
            $newSrc = $url . "/" . ltrim($originalSrc, '/');
            return str_replace($originalSrc, $newSrc, $matches[0]);
        }, $child['introduce'] ?: "");

        $item = new Item($pid . $child['id'], $child['category']['name'], $child['name'], $introduce, str_starts_with($child['picture_url'], "http") ? $child['picture_url'] : $url . $child['picture_url'], $skus);

        $item->setOptions(["id" => $child['id'], "api_code" => $child['api_code']]);

        $widgets = json_decode($child['widget'] ?? "", true) ?: [];

        if (count($widgets) > 0) {
            $wts = [];
            foreach ($widgets as $widget) {
                $wget = new Widget($widget['type'], $widget['title'], $widget['name'], $widget['placeholder']);
                if (!empty($widget['regex']) && !empty($widget['error'])) {
                    $wget->setError($widget['error']);
                    $wget->setRegex($widget['regex']);
                }
                if (!empty($widget['data'])) {
                    $wget->setData($widget['data']);
                }
                $wts[] = $wget;
            }
            $item->setWidgets($wts);
        }

        return $item;
    }


    /**
     * @param string $uniqueId
     * @param array $options
     * @return Item|null
     * @throws \ReflectionException
     */
    public function getItem(string $uniqueId, array $options = []): ?Item
    {
        try {
            $data = Http::inst()->request($this->config, "/plugin/open-api/item", ["id" => $options['id']]);
            if (empty($data)) {
                return null;
            }
            return $this->createItem(trim($this->config['url'], "/"), $data, (string)$this->config['pid']);
        } catch (\Throwable $e) {
            $this->plugin->log("获取商品出错：{$e->getMessage()}", true);
            return null;
        }
    }
}