<?php
declare (strict_types=1);

namespace App\Plugin\Epay\Handle;

use Kernel\Context\Interface\Response;
use Kernel\Exception\HandleException;
use Kernel\Exception\JSONException;
use Kernel\Util\Decimal;
use Kernel\Util\Http;
use Kernel\Util\UserAgent;

class Pay extends \Kernel\Plugin\Abstract\Pay
{

    /**
     * @return \Kernel\Plugin\Entity\Pay
     * @throws HandleException
     * @throws JSONException
     * @throws \ReflectionException
     */
    public function create(): \Kernel\Plugin\Entity\Pay
    {
        if (!$this->config['url'] ||
            !$this->config['pid'] ||
            !isset($this->config['version']) ||
            ($this->config['version'] == 1 && (!$this->config['private_key'] || !$this->config['platform_public_key'])) ||
            ($this->config['version'] == 0 && !$this->config['key'])) {
            throw new JSONException("重要参数缺失，请检查插件配置文件！");
        }

        $title = isset($this->config['order_title']) ? str_replace('${trade_no}', $this->order->trade_no, $this->config['order_title']) : "商品订单号:{$this->order->trade_no}";

        $param = [
            'pid' => $this->config['pid'],
            'name' => $title,
            'type' => $this->code,
            'money' => $this->amount,
            'out_trade_no' => $this->order->trade_no,
            'notify_url' => $this->asyncUrl,
            'return_url' => $this->syncUrl,
            'sitename' => $this->order->trade_no,
            'clientip' => $this->clientIp,
            'device' => UserAgent::isMobile($this->request->header("UserAgent")) ? 'mobile' : 'pc'
        ];

        $url = trim($this->config['url'], "/");

        if ($this->config['version'] == 1) {
            $param['method'] = 'jump';
            $param['timestamp'] = time();
            $param['sign'] = $this->rsa($param, $this->config['private_key']);
            $param['sign_type'] = "RSA";

            $url .= $this->config['mapi'] == 1 ? "/api/pay/create" : "/api/pay/submit";

            $code = 0;

        } elseif ($this->config['version'] == 0) {
            $param['sign'] = $this->sign($param, $this->config['key']);
            $param['sign_type'] = "MD5";

            $url .= $this->config['mapi'] == 1 ? "/mapi.php" : "/submit.php";

            $code = 1;
        } else {
            throw new JSONException("支付接口出错，下单失败！");
        }

        $pay = new \Kernel\Plugin\Entity\Pay();
        $timeout = isset($this->config['timeout']) && $this->config['timeout'] > 300 ? $this->config['timeout'] : 300;
        $pay->setTimeout((int)$timeout);

        $types = [
            'alipay' => \Kernel\Plugin\Const\Pay::RENDER_COMMON_ALIPAY_VIEW,
            'wxpay' => \Kernel\Plugin\Const\Pay::RENDER_COMMON_WECHAT_VIEW,
            'qqpay' => \Kernel\Plugin\Const\Pay::RENDER_COMMON_QQ_VIEW
        ];

        if ($this->config['mapi'] == 1) {
            try {
                $response = Http::make()->post($url, [
                    "form_params" => $param
                ]);

                $json = json_decode($response->getBody()->getContents(), true);

                if (empty($json)) {
                    $this->plugin->log("订单号：{$this->order->trade_no}，下单失败；订单返回参数存在伪造风险！！！");
                    throw new HandleException("下单失败#0");
                }

                if (!isset($json['code']) || $json['code'] != $code) {
                    throw new HandleException($json['msg'] ?? "下单失败#1");
                }

                if ($this->config['version'] == 1) {
                    if (!isset($json['pay_info'])) {
                        throw new HandleException("下单失败#2");
                    }
                    $pay->setPayUrl($json['pay_info']);
                    $pay->setRenderMode(\Kernel\Plugin\Const\Pay::RENDER_JUMP);
                    return $pay;
                } elseif ($this->config['version'] == 0) {
                    if (isset($json['qrcode'])) {
                        $pay->setPayUrl($json['qrcode']);
                        $pay->setRenderMode($types[$this->code]);
                        return $pay;
                    } elseif (isset($json['payurl'])) {
                        $pay->setPayUrl($json['payurl']);
                        $pay->setRenderMode(\Kernel\Plugin\Const\Pay::RENDER_JUMP);
                        return $pay;
                    }
                }
            } catch (\Throwable $e) {
                $this->plugin->log("商户ID：{$this->config['pid']}，MAPI请求出错：" . $e->getMessage(), true);
                $this->plugin->log($json);
                throw new JSONException("支付接口出错，请查看插件日志");
            }
        }

        $this->plugin->log("订单号：{$this->order->trade_no}，下单成功；下单金额：{$this->amount}");

        $pay->setPayUrl($url);
        $pay->setRenderMode(\Kernel\Plugin\Const\Pay::RENDER_FORM_POST_SUBMIT);
        $pay->setOption($param);
        return $pay;
    }


    /**
     * @return Response
     * @throws JSONException
     * @throws \ReflectionException
     */
    public function async(): Response
    {
        $data = $this->request->post() ?: $this->request->get();

        if (!isset($data['sign']) || !$this->verification($data) ||
            $this->code !== $data['type'] ||
            $this->order->trade_no !== (string)$data['out_trade_no'] ||
            (new Decimal((string)$this->amount, 2))->getAmount() !== (new Decimal($data['money'], 2))->getAmount() ||
            $data['trade_status'] !== "TRADE_SUCCESS") {
            $this->plugin->log($data);
            throw new JSONException("回调参数错误!");
        }

        if (isset($this->config['order_check']) &&
            $this->config['order_check'] == 1 &&
            !$this->query($data['trade_no'])) {
            throw new JSONException("远程订单检查失败!");
        }
        $this->plugin->log("订单号：{$this->order->trade_no}，支付成功；回调金额：{$this->amount}，第三方金额：{$data['money']}");

        $this->successful();
        return $this->response->raw("success");
    }

    /**
     * @param string $tradeNo
     * @return bool
     * @throws \ReflectionException
     */
    private function query(string $tradeNo): bool
    {
        $url = trim($this->config['url'], "/");

        if ($this->config['version'] == 1) {
            $param = [
                'pid' => $this->config['pid'],
                'trade_no' => $tradeNo,
                'out_trade_no' => $this->order->trade_no,
                'timestamp' => time()
            ];

            $param['sign'] = $this->rsa($param, $this->config['private_key']);
            $param['sign_type'] = "RSA";
            try {
                $response = Http::make()->post($url . "/api/pay/query", [
                    "form_params" => $param
                ]);
                $json = json_decode($response->getBody()->getContents(), true);


                if (!$this->verification($json)) {
                    $this->plugin->log("订单号：{$this->order->trade_no}，支付回调失败；订单返回参数存在伪造风险！！！");
                    return false;
                }

                if (isset($json['code']) && (int)$json['code'] === 0 && (int)$json['status'] === 1) {
                    return true;
                }
            } catch (\Throwable $e) {
                $this->plugin->log("商户ID：{$this->config['pid']}，回调时验证订单请求出错：" . $e->getMessage(), true);
                $this->plugin->log($json);
            }
        } elseif ($this->config['version'] == 0) {
            try {
                $queryParams = [
                    'act' => 'order',
                    'pid' => $this->config['pid'],
                    'key' => $this->config['key'],
                    'trade_no' => $tradeNo,
                    'out_trade_no' => $this->order->trade_no
                ];
                $response = Http::make()->get($url . "/api.php?" . http_build_query($queryParams));
                $json = json_decode($response->getBody()->getContents(), true);
                if (isset($json['code']) && (int)$json['code'] === 1 && (int)$json['status'] === 1) {
                    return true;
                }
            } catch (\Throwable $e) {
                $this->plugin->log("商户ID：{$this->config['pid']}，回调时验证订单请求出错：" . $e->getMessage(), true);
            }
        }
        return false;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function verification(array $data): bool
    {
        if ($this->config['version'] == 1) {
            if (empty($data['timestamp']) || abs(time() - $data['timestamp']) > 300) return false;
            return $this->rsaVerify($data, $this->config['platform_public_key']);
        } elseif ($this->config['version'] == 0) {
            $sign = $data['sign'];
            unset($data['sign_type'], $data['sign']);
            return $sign === $this->sign($data, $this->config['key']);
        }

        return false;
    }


    /**
     * @param array $data
     * @param string $key
     * @return string
     */
    private function sign(array $data, string $key): string
    {
        ksort($data);
        $sign = '';
        foreach ($data as $k => $v) {
            $sign .= $k . '=' . $v . '&';
        }
        $sign = trim($sign, '&');

        return md5($sign . $key);
    }


    /**
     * @param array $param
     * @param string $key
     * @return string
     * @throws HandleException
     */
    private function rsa(array $param, string $key): string
    {
        $data = $this->getStr($param);
        $private_key = "-----BEGIN PRIVATE KEY-----\n" .
            wordwrap($key, 64, "\n", true) .
            "\n-----END PRIVATE KEY-----";
        $privateKey = openssl_get_privatekey($private_key);
        if (!$privateKey) {
            throw new HandleException('签名失败，商户私钥错误');
        }
        openssl_sign($data, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }


    /**
     * @param array $params
     * @param string $sign
     * @param string $publicKey
     * @return bool
     */
    private function rsaVerify(array $params, string $publicKey): bool
    {
        $key = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($publicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        $publicKey = openssl_get_publickey($key);
        if (!$publicKey) {
            return false;
        }
        $result = openssl_verify($this->getStr($params), base64_decode($params['sign']), $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }


    /**
     * @param array $param
     * @return string
     */
    private function getStr(array $param): string
    {
        ksort($param);
        $signstr = '';
        foreach ($param as $k => $v) {
            $v = (string)$v;
            if (is_array($v) || $this->isEmpty($v) || $k == 'sign' || $k == 'sign_type') continue;
            $signstr .= '&' . $k . '=' . $v;
        }
        return substr($signstr, 1);
    }

    private function isEmpty($value)
    {
        return $value === null || trim($value) === '';
    }
}