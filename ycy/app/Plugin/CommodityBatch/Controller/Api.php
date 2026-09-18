<?php
declare(strict_types=1);

namespace App\Plugin\CommodityBatch\Controller;

use App\Controller\Base\API\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use App\Model\Commodity;
use App\Model\ManageLog;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_API)]
class Api extends ManagePlugin
{
    /** 单次最多处理条数，防止手滑全选几万条把库锁死 */
    private const MAX_BATCH_COUNT = 1000;

    /**
     * 可批量调整的价格字段。key=数据库字段，value=展示名。
     * price       商品单价（未登录/游客价）
     * user_price  会员价
     * factory_price 成本价（拿货价）
     */
    private const PRICE_FIELDS = [
        'price' => '商品单价',
        'user_price' => '会员价',
        'factory_price' => '成本价',
    ];

    /**
     * 批量修改价格。每个字段可独立选择调整方式：
     *   mode 0=不改  1=设为固定值  2=按比例%调整  3=按金额加减
     * POST: list[] + 对每个字段传 {field}_mode 与 {field}_value
     * @throws JSONException
     */
    public function batchPrice(): array
    {
        $list = $this->ids($_POST['list'] ?? []);

        $update = [];
        foreach (self::PRICE_FIELDS as $field => $label) {
            $mode = (int)($_POST["{$field}_mode"] ?? 0);
            if ($mode === 0) {
                continue;
            }
            $value = (float)($_POST["{$field}_value"] ?? 0);

            switch ($mode) {
                case 1: // 设为固定值
                    if ($value < 0) {
                        throw new JSONException("{$label}不能低于 0 元");
                    }
                    $update[$field] = round($value, 2);
                    break;
                case 2: // 按比例调整（+10 表示涨10%，-10 表示降10%）
                    $ratio = 1 + $value / 100;
                    if ($ratio < 0) {
                        throw new JSONException("{$label}调整比例不能低于 -100%");
                    }
                    $update[$field] = new \Illuminate\Database\Query\Expression(
                        sprintf("GREATEST(ROUND(`%s` * %.6F, 2), 0)", $field, $ratio)
                    );
                    break;
                case 3: // 按金额加减（+2 表示每个加2元，-2 表示每个减2元）
                    $update[$field] = new \Illuminate\Database\Query\Expression(
                        sprintf("GREATEST(ROUND(`%s` + (%.4F), 2), 0)", $field, $value)
                    );
                    break;
                default:
                    throw new JSONException("未知的调价方式");
            }
        }

        if ($update === []) {
            throw new JSONException("请至少选择一个价格字段进行修改");
        }

        $count = Commodity::query()->whereIn('id', $list)->update($update);
        ManageLog::log($this->getManage(), "[批量修改]商品价格字段：" . implode(',', array_keys($update)));
        return $this->json(200, "批量修改完成，共更新 {$count} 个商品", ['updated' => $count]);
    }

    /**
     * 归一化选中的商品 id 列表。
     * @return int[]
     * @throws JSONException
     */
    private function ids(mixed $list): array
    {
        if (!is_array($list)) {
            throw new JSONException("请至少勾选 1 个商品进行操作");
        }

        $ids = [];
        foreach ($list as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            throw new JSONException("请至少勾选 1 个商品进行操作");
        }
        if (count($ids) > self::MAX_BATCH_COUNT) {
            throw new JSONException("单次最多处理 " . self::MAX_BATCH_COUNT . " 个，请分批操作");
        }
        return $ids;
    }
}
