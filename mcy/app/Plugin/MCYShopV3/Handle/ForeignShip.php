<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV3\Handle;

use App\Plugin\MCYShopV3\Util\Http;
use App\Plugin\MCYShopV3\Util\Ini;
use Kernel\Exception\HandleException;
use Kernel\Plugin\Entity\Item;
use Kernel\Plugin\Entity\Sku;
use Kernel\Plugin\Entity\Widget;

class ForeignShip extends \Kernel\Plugin\Abstract\ForeignShip
{

    /**
     * @param string $url
     * @param array $child
     * @param string $category
     * @param string $pid
     * @return Item
     * @throws HandleException
     * @throws \ReflectionException
     */
    private function createItem(string $url, array $child, string $category, string $pid): Item
    {
        $pictureUrl = $url . $child['cover'];
        $options = ["code" => $child['code']];
        $skus = [];
        //判断是否有config

        $config = $child['config'];

        if (!is_array($config)) {
            $config = Ini::toArray((string)$config);
        }

        if (count($config) > 0 && isset($config['category'])) {
            foreach ($config['category'] as $name => $price) {
                $options['race'] = $name;
                $sku = new Sku($pid . $child['id'] . $name, $name, $pictureUrl, $config['category'][$name] ?? 0);
                $sku->setOptions($options);
                $sku->setCost($config['category_factory'][$name] ?? 0);

                $child['minimum'] > 0 && $sku->setMarketControlMinNum((int)$child['minimum']);
                $child['maximum'] > 0 && $sku->setMarketControlMaxNum((int)$child['maximum']);
                $child['purchase_count'] > 0 && $sku->setMarketControlOnlyNum((int)$child['purchase_count']);

                $skus[] = $sku;
            }
        } else {
            $sku = new Sku($pid . $child['id'], $child['name'], $pictureUrl, $child['price']);
            $sku->setOptions($options);
            $sku->setCost($child['factory_price'] ?? 0);
            $child['minimum'] > 0 && $sku->setMarketControlMinNum((int)$child['minimum']);
            $child['maximum'] > 0 && $sku->setMarketControlMaxNum((int)$child['maximum']);
            $child['purchase_count'] > 0 && $sku->setMarketControlOnlyNum((int)$child['purchase_count']);
            $skus[] = $sku;
        }

        $introduce = preg_replace_callback('/<img[^>]+src=["\']?([^"\'>]+)["\']?[^>]*>/i', function ($matches) use ($url) {
            $originalSrc = $matches[1];
            if (preg_match('/^(http:\/\/|https:\/\/)/i', $originalSrc)) {
                return $matches[0];
            }
            $newSrc = $url . "/" . ltrim($originalSrc, '/');
            return str_replace($originalSrc, $newSrc, $matches[0]);
        }, (string)$child['description']);

        $item = new Item($pid . $child['id'], $category, $child['name'], $introduce, $pictureUrl, $skus);
        $item->setOptions(["code" => $child['code']]);

        $widgets = (array)json_decode(trim((string)$child['widget']), true);

        if (count($widgets) > 0) {
            $wts = [];
            foreach ($widgets as $widget) {
                $wget = new Widget($widget['type'], $widget['cn'], $widget['name'], $widget['placeholder']);
                if ($widget['regex'] && $widget['error']) {
                    $wget->setError($widget['error']);
                    $wget->setRegex($widget['regex']);
                }

                if ($widget['dict']) {
                    $wget->setData(str_replace(",", "\n", trim(trim($widget['dict']), ",")));
                }

                $wts[] = $wget;
            }

            $item->setWidgets($wts);
        }

        return $item;
    }


    /**
     * @return Item[]
     * @throws HandleException
     * @throws \ReflectionException
     */
    public function getItems(): array
    {
        $url = trim((string)$this->config['url'], "/");

        $list = Http::inst()->post($this->plugin, $url . "/shared/commodity/items", $this->config['pid'], $this->config['key']);

        if (count($list) == 0) {
            throw new HandleException("对方没有任何商品可以对接，请联系对方站长");
        }

        $items = [];

        $this->plugin->log("V3货源返回分类数：" . count($list));

        $failed = 0;

        foreach ($list as $category) {
            if (!isset($category['children']) || !is_array($category['children'])) {
                continue;
            }

            foreach ($category['children'] as $child) {
                try {
                    $items[] = $this->createItem($url, $child, (string)$category['name'], (string)$this->config['pid']);
                } catch (\\Throwable $e) {
                    $failed++;
                    $itemId = $child['id'] ?? 'unknown';
                    $itemName = $child['name'] ?? 'unknown';
                    $this->plugin->log("[V3货源商品转换失败] ID={$itemId}，名称={$itemName}，错误：{$e->getMessage()}", true);
                }
            }
        }

        $this->plugin->log("V3货源转换完成：成功 " . count($items) . " 个，失败 " . $failed . " 个");

        if (count($items) === 0) {
            throw new HandleException("V3货源商品转换失败，请查看插件日志");
        }

        return $items;
    }


    /**
     * @param string $uniqueId
     * @param array $options
     * @return Item|null
     * @throws HandleException
     * @throws \ReflectionException
     */
    public function getItem(string $uniqueId, array $options = []): ?Item
    {
        $url = trim((string)$this->config['url'], "/");
        $list = Http::inst()->post($this->plugin, $url . "/shared/commodity/item", $this->config['pid'], $this->config['key'], [
            $this->config['version'] == 0 ? "code" : "sharedCode" => $options['code']
        ]);

        if ($this->config['version'] == 0) {
            if ($list) {
                return $this->createItem($url, $list, $list['name'], (string)$this->config['pid']);
            }
        } else {
            if (isset($list[0]['children']) && count($list[0]['children']) > 0) {
                return $this->createItem($url, $list[0]['children'][0], $list[0]['name'], (string)$this->config['pid']);
            }
        }
        return null;
    }
}