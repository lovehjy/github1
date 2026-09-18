<?php
session_start();

$_SESSION['counter'] = ($_SESSION['counter'] ?? 0) + 1;

header('Content-Type: text/plain; charset=utf-8');

echo 'session_id=' . session_id() . PHP_EOL;
echo 'save_path=' . session_save_path() . PHP_EOL;
echo 'counter=' . $_SESSION['counter'] . PHP_EOL;