<?php
declare(strict_types=1);

namespace App\Plugin\DestroyOrder\Controller;

use App\Controller\Base\API\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use App\Model\Card;
use App\Model\ManageLog;
use App\Model\Order;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_API)]
class Api extends ManagePlugin
{
    /**
     * 单次最多处理的条数，防止手滑全选几万条把库锁死
     */
    private const MAX_BATCH_COUNT = 500;

    /**
     * 销毁订单。开启联动后，订单发出去的卡密（card.order_id 指向该订单）一并删除。
     * @return array
     * @throws JSONException
     */
    public function del(): array
    {
        $ids = $this->ids($_POST['list'] ?? []);
        $cascade = $this->cascade();

        $result = DB::transaction(function () use ($ids, $cascade): array {
            //先把卡密捞出来：订单删掉之后就再也找不到它们了
            $cardIds = $cascade ? $this->cardIdsOfOrders($ids) : [];
            $orderCount = Order::query()->whereIn("id", $ids)->delete();

            if ($orderCount === 0) {
                //事务里抛异常会回滚，这里本来也没删掉东西，直接抛
                throw new JSONException("没有销毁任何数据");
            }

            $cardCount = $cardIds === [] ? 0 : Card::query()->whereIn("id", $cardIds)->delete();

            return ['order' => $orderCount, 'card' => $cardCount];
        });

        ManageLog::log(
            $this->getManage(),
            "[订单销毁]销毁订单：{$result['order']}，连带删除卡密：{$result['card']}"
        );

        //提示语保持固定文案：json() 会把 msg 丢给 lang() 翻译，
        //把数量拼进去等于每次都产生一条新词条，白白撑爆翻译表，数量交给前端用 data 拼
        return $this->json(200, '（＾∀＾）订单全部销毁成功', $result);
    }


    /**
     * 销毁卡密。开启联动后，卡密所属订单（card.order_id）一并删除；
     * 同一订单下的其它卡密也会跟着删，避免留下指向已删订单的孤儿卡密。
     * @return array
     * @throws JSONException
     */
    public function delCard(): array
    {
        $ids = $this->ids($_POST['list'] ?? []);
        $cascade = $this->cascade();

        $result = DB::transaction(function () use ($ids, $cascade): array {
            $orderIds = $cascade ? $this->orderIdsOfCards($ids) : [];

            //联动时把同单的兄弟卡密一并纳入，否则它们的 order_id 会指向一个已经不存在的订单
            if ($orderIds !== []) {
                $ids = array_values(array_unique(array_merge($ids, $this->cardIdsOfOrders($orderIds))));
            }

            $cardCount = Card::query()->whereIn("id", $ids)->delete();

            if ($cardCount === 0) {
                throw new JSONException("没有销毁任何数据");
            }

            $orderCount = $orderIds === [] ? 0 : Order::query()->whereIn("id", $orderIds)->delete();

            return ['order' => $orderCount, 'card' => $cardCount];
        });

        ManageLog::log(
            $this->getManage(),
            "[订单销毁]销毁卡密：{$result['card']}，连带删除订单：{$result['order']}"
        );

        return $this->json(200, '（＾∀＾）卡密全部销毁成功', $result);
    }


    /**
     * 这些订单发出去的卡密
     * @param int[] $orderIds
     * @return int[]
     */
    private function cardIdsOfOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return Card::query()
            ->whereIn("order_id", $orderIds)
            ->pluck("id")
            ->map(static fn($id): int => (int)$id)
            ->all();
    }


    /**
     * 这些卡密所属的订单
     * @param int[] $cardIds
     * @return int[]
     */
    private function orderIdsOfCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $orderIds = Card::query()
            ->whereIn("id", $cardIds)
            ->whereNotNull("order_id")
            ->where("order_id", ">", 0)
            ->pluck("order_id")
            ->map(static fn($id): int => (int)$id)
            ->unique()
            ->values()
            ->all();

        //订单可能早被删过，只保留真实存在的，免得日志里报一堆删了 0 条
        return $orderIds === [] ? [] : Order::query()
            ->whereIn("id", $orderIds)
            ->pluck("id")
            ->map(static fn($id): int => (int)$id)
            ->all();
    }


    /**
     * @param mixed $list
     * @return int[]
     * @throws JSONException
     */
    private function ids(mixed $list): array
    {
        if (!is_array($list)) {
            throw new JSONException("你还没有选择数据呢(◡ᴗ◡✿)");
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
            throw new JSONException("你还没有选择数据呢(◡ᴗ◡✿)");
        }

        if (count($ids) > self::MAX_BATCH_COUNT) {
            throw new JSONException("单次最多销毁 " . self::MAX_BATCH_COUNT . " 条，请分批操作");
        }

        return $ids;
    }


    /**
     * 是否联动删除。由销毁按钮的确认框当场勾选决定，不做成插件配置：
     * 这是一次性的危险操作，选择就该跟在这一次操作上，而不是留成常驻开关
     * @return bool
     */
    private function cascade(): bool
    {
        $cascade = $_POST['cascade'] ?? 0;
        return is_scalar($cascade) && (int)$cascade === 1;
    }

}
