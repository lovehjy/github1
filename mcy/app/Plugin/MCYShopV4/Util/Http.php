<?php
declare (strict_types=1);

namespace App\Plugin\MCYShopV4\Util;

use Kernel\Component\Singleton;
use Kernel\Exception\ServiceException;
use Kernel\Util\Str;

class Http
{
    use Singleton;


    /**
     * @param array $config
     * @param string $uri
     * @param array $data
     * @return array
     * @throws ServiceException
     */
    public function request(array $config, string $uri, array $data = []): array
    {
        try {
            $response = \Kernel\Util\Http::make()->post(trim($config['url']) . $uri, [
                "headers" => [
                    "Api-Id" => $config['pid'],
                    "Api-Signature" => Str::generateSignature($data, $config['key'] ?? "")
                ],
                "form_params" => $data
            ]);


            $contents = json_decode($response->getBody()->getContents() ?: "", true) ?: [];

            if (!isset($contents['code'])) {
                throw new ServiceException("连接失败#1");
            }

            if ($contents['code'] != 200) {
                throw new ServiceException($contents['msg'] ?? "连接失败#2");
            }

            return $contents['data'] ?? [];
        } catch (\Throwable $e) {
            throw new ServiceException("连接失败#0");
        }
    }
}