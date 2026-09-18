<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV3\Util;

use Kernel\Component\Singleton;
use Kernel\Exception\HandleException;
use Kernel\Plugin\Entity\Plugin;
use Kernel\Util\Str;

class Http
{
    use Singleton;

    /**
     * @param Plugin $plugin
     * @param string $url
     * @param string $appId
     * @param string $appKey
     * @param array $data
     * @return array
     * @throws HandleException
     * @throws \ReflectionException
     */
    public function post(Plugin $plugin, string $url, string $appId, string $appKey, array $data = []): array
    {
        $data = array_merge($data, ["app_id" => $appId, "app_key" => $appKey]);
        $data['sign'] = Str::generateSignature($data, $appKey);
        try {
            $plugin->log("准备调用API：{$url}，APPID：{$appId}，KEY：{$appKey}，请求数据如下：");
            $plugin->log($data);
            $response = \Kernel\Util\Http::make()->post($url, ["form_params" => $data, "timeout" => 10]);
            $contents = $response->getBody()->getContents();
            $plugin->log("请求结束，API接口返回数据：{$contents}");
            $result = (array)json_decode((string)$contents, true);

            if (!isset($result['code']) || $result['code'] != 200) {
                $plugin->log($result['msg'] ?? "[{$url}]->连接失败#0", true);
                throw new HandleException(isset($result['msg']) ? strip_tags($result['msg']) : "连接失败#0");
            }
            return (array)$result['data'];
        } catch (\Throwable $e) {
            $plugin->log("[{$url}]->" . $e->getMessage(), true);
            throw new HandleException("连接失败#1：{$e->getMessage()}");
        }
    }
}