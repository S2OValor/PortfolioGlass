<span>
    <?= '実行時間: ', round((microtime(1) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4), ' ms / メモリ使用量: ', number_format(memory_get_usage() / 1048576, 3), ' MB'; ?>
</span>

<span>
    <a href="./page/index.php" hx-get="./page/index.php" hx-target="#mainCont" hx-select="#mainCont" hx-select-oob="#pageBreadcrumb" hx-swap="innerHTML" hx-push-url="false">
        © <?= date('Y'); ?> KS25 Portfolio
    </a>
</span>