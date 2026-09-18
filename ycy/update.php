<?php
declare(strict_types=1);

namespace Version372;

use Illuminate\Database\Capsule\Manager;

/**
 * 3.7.2 升级：店铺共享记住上游协议代次。
 *
 * 注意执行时机：App::update() 是「先跑本文件，再 copyDirectory 覆盖程序文件」，
 * 所以本文件运行时新代码还没落地——不能引用本次新增的类与常量，
 * 全程只用原生 DB 操作，且每一步都必须能重复执行（失败重跑不出错）。
 *
 * 本次没有新表，只有一列：
 * - shared.protocol：这家上游是哪一代协议。`/shared/commodity/item` 的入参与返回形状
 *   在 3.1.2 变过，`stock`/`draft`/`valuation` 三个端点也是那之后才有的。不记住的话，
 *   每次都得先打一发新端点再吃 404，商品详情每访问一次就多一趟无用往返。
 *   0=未探明，1=3.1.2 及以后，2=3.1.1 及更老。
 *
 *   默认 0，与升级前行为逐字节一致：第一次请求照旧探测，探明后写回这一列，之后直奔正确的路。
 *
 * 代码里另有 App\Util\Schema::ensureSharedProtocol() 在店铺共享的入口自愈补列
 * （老库缺列时读到 null，按未探明处理，不会 500）。这里先建好，可以让升级后的
 * 首个请求少一次 DDL；两处的列定义必须保持一致，改任何一边都要对照另一边。
 */
class Update
{
    public function handle(): void
    {
        $this->addSharedProtocolColumn();
    }

    /**
     * 店铺共享的上游协议代次列。列定义与 App\Util\Schema::ensureSharedProtocol()
     * 保持一致（那边是 Blueprint 生成，这里手写 DDL）。
     */
    private function addSharedProtocolColumn(): void
    {
        $this->addColumnIfMissing(
            'shared',
            'protocol',
            "ALTER TABLE `%s` ADD COLUMN `protocol` tinyint UNSIGNED NOT NULL DEFAULT 0 "
            . "COMMENT '上游协议代次：0=未探明，1=3.1.2+，2=3.1.1及更老' AFTER `currency_rate`"
        );
    }

    /**
     * 加列的通用入口：表不存在或列已存在都直接跳过，保证可重复执行。
     */
    private function addColumnIfMissing(string $table, string $column, string $ddlTemplate): void
    {
        try {
            $schema = Manager::schema();
            if (!$schema->hasTable($table) || $schema->hasColumn($table, $column)) {
                return;
            }
            $prefix = (string)Manager::connection()->getTablePrefix();
            Manager::statement(sprintf($ddlTemplate, $prefix . $table));
        } catch (\Throwable $e) {
        }
    }
}
