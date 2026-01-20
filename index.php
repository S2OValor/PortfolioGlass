<?php
require_once 'lib/tidy/html-minify.php';
// サイトルート index.php の物理パス（後で全部これ基準に作る）
$GLOBALS['SITE_INDEX_PATH'] = __FILE__;
// サイトルートの物理ディレクトリ
$GLOBALS['SITE_ROOT_DIR']   = __DIR__;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php require_once 'lib/page/meta.php'; ?>
	<title>Portfolio - KS25</title>
    <?php require_once 'lib/page/link.php'; ?>

    <!-- Page Base Style -->
    <link rel="stylesheet" href="./style/base.css?<?php echo time(); ?>">
</head>
<body>
    <?php require_once 'lib/page/noscript.php'; ?>

    <div id="stage" class="uk-flex uk-flex-column uk-flex-middle uk-flex-center uk-cover-container">
        <video src="./video/bgv.mp4" poster="./video/bgvpost.png" autoplay loop muted playsinline uk-cover></video>

        <!-- ヘッダー部分：開始 -->
        <header class="no-select uk-width-1-1 uk-flex uk-flex-middle">
            <?php require_once 'partials/header.php'; ?>
        </header>
        <!-- ヘッダー部分：終了 -->


        <!-- ナビ部分：開始 -->
        <nav class="no-select uk-width-1-1 uk-flex uk-flex-middle">
            <?php require_once 'partials/nav.php'; ?>
        </nav>
        <!-- ナビ部分：終了 -->


        <!-- メイン部分：開始 -->
        <main class="uk-width-1-1 uk-flex">
            <div id="mainCont">
                <?php require_once 'page/index.php'; ?>
            </div>
        </main>
        <!-- メイン部分：終了 -->


        <!-- フッター部分：開始 -->
        <footer class="uk-flex uk-width-1-1 uk-flex-column uk-flex-middle uk-flex-center">
            <?php require_once 'partials/footer.php'; ?>
        </footer>
        <!-- フッター部分：終了 -->
    </div>

    <!-- モダル部分：開始 -->
    <aside id="modal-overflow" uk-modal>
        <div class="uk-modal-dialog uk-margin-auto-vertical">
            <!-- モダル：ヘッダー -->
            <?php require_once 'lib/modal/modal-head.php'; ?>

            <!-- モダル：ボディー -->
            <div id="modal-body" class="uk-modal-body" uk-overflow-auto>
                <?php require_once 'lib/modal/modal-body.php'; ?>
            </div>

            <!-- モダル：フッター -->
            <?php require_once 'lib/modal/modal-foot.php'; ?>
        </div>
    </aside>
    <!-- モダル部分：終了 -->


    <!-- HTMX Repository -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/htmx/2.0.7/htmx.min.js" integrity="sha512-IisGoumHahmfNIhP4wUV3OhgQZaaDBuD6IG4XlyjT77IUkwreZL3T3afO4xXuDanSalZ57Un+UlAbarQjNZCTQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Uikit Repository -->
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit.min.js" integrity="sha512-g9wkFlti+bZT3YNTbVcMumimOS+hJSfbBEnKKP+e307qqQ3Ye4Bx7p/xUJ8yNRMotwudcofKL60ck1BGxk1t6Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit-icons.min.js" integrity="sha512-fyzBJExpV4/Aprql1Gm4X0g3Qtmyev/D8KFVkuYYLD4ixhkVwTrSm/3rvYWWKTFtxN0H5/xTBQYxqOgL8CL5Rw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Chart.js Repository -->
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.min.js" integrity="sha512-n/G+dROKbKL3GVngGWmWfwK0yPctjZQM752diVYnXZtD/48agpUKLIn0xDQL9ydZ91x6BiOmTIFwWjjFi2kEFg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- PDF 出力用 WebAssembly -->
    <script type="module" src="./lib/pdfout/pdfout.js"></script>

    <!-- Page Base Script -->
    <script defer src="./script/base.js?<?php echo time(); ?>"></script>
</body>
</html>