<?php
define('BASE_PATH', __DIR__ . '/');
require BASE_PATH . 'vendor/autoload.php';
require BASE_PATH . 'kernel/Helper.php';
header('Content-Type: text/plain; charset=utf-8');
echo 'PHP=' . PHP_VERSION . PHP_EOL;
echo 'sg12=' . (extension_loaded('sg12') ? 'yes' : 'no') . PHP_EOL;
echo 'Plugin.php=' . (file_exists(BASE_PATH . 'kernel/Plugin.php') ? 'yes' : 'no') . PHP_EOL;
echo 'STORE_STATUS=' . (file_exists(BASE_PATH . 'kernel/Plugin.php') ? 'yes' : 'no') . PHP_EOL;
if (file_exists(BASE_PATH . 'kernel/Plugin.php')) {
    try {
        require BASE_PATH . 'kernel/Plugin.php';
        echo 'loaded Plugin.php=yes' . PHP_EOL;
    } catch (Throwable $e) {
        echo 'load Plugin.php error=' . $e->getMessage() . PHP_EOL;
    }
}
echo 'plugin_download=' . (function_exists('_plugin_download') ? 'yes' : 'no') . PHP_EOL;
echo 'plugin_get_hwid=' . (function_exists('_plugin_get_hwid') ? _plugin_get_hwid() : 'no') . PHP_EOL;
echo 'BASE_APP_SERVER=' . (defined('BASE_APP_SERVER') ? BASE_APP_SERVER : 'undef') . PHP_EOL;
$store = config('store');
echo 'store_server=' . ($store['server'] ?? '?') . PHP_EOL;
echo 'app_version=' . (config('app')['version'] ?? '?') . PHP_EOL;
if (function_exists('_plugin_download')) {
    $r = @_plugin_download(999999);
    echo 'download_test=' . var_export($r, true) . PHP_EOL;
    echo 'acg_err=' . ($GLOBALS['__acg_err'] ?? 'empty') . PHP_EOL;
}
