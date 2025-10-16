<?php
// 生产环境错误处理
try {
    include 'protectedFolder/View.php';

    $view = new View();
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = 10; // 每页显示10条记录
    $search = isset($_GET['search']) && trim($_GET['search']) !== '' ? trim($_GET['search']) : null;
    $effect = isset($_GET['effect']) && trim($_GET['effect']) !== '' ? trim($_GET['effect']) : null;

    $cards = $view->getCardsPageViewByIdOrName($search, $search, $effect, $page, $pageSize);
    // 如果有搜索条件，使用搜索结果总数；否则使用全部卡片总数
    if ($search !== null || $effect !== null) {
        $totalCards = $view->getCardCountByIdOrName($search, $search, $effect);
    } else {
        $totalCards = $view->getTotalCardCount();
    }
    $totalPages = ceil($totalCards / $pageSize);
} catch (Exception $e) {
    // 记录错误但不显示详细信息给用户
    error_log("Error in index.php: " . $e->getMessage());
    // 显示友好的错误页面
    http_response_code(500);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统错误</title></head><body><h1>系统暂时不可用</h1><p>我们遇到了一些技术问题，请稍后再试。</p></body></html>');
}

function buildPageUrl($page) {
    $queryParams = $_GET;
    $queryParams['page'] = $page;
    return http_build_query($queryParams);
}

$nextPageUrl = buildPageUrl($page + 1);
$previousPageUrl = buildPageUrl($page - 1);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galaxy Card Game</title>
    <link rel="stylesheet" type="text/css" href="./styles.css">
    <style>
        .cardList{text-align:center;border:2px}
        .card{padding: 8px;border-radius: 10px 10px 10px 10px;border:8px outset rgb(126, 126, 126);text-align:center;display: inline-block;margin: 5px; width: 280px; height: 530px; background-color: rgb(240, 240, 240); }
        .cardPic{text-align:center;height: 275px;object-fit: contain;}
        .cardLin1{text-align:left;border: 3px ridge rgb(163, 163, 163);padding: 5px;margin: -2px;border-radius: 4px 4px 0px 0px;}
        .cardName{font-weight:600;}
        .cardAttribute{float:right;}
        .cardLin2{text-align:left;font-size: 13px;}
        .cardTypeAndLevel{float:right;}
        .cardMiaoshu{resize:none; font-size:15px; height: 170px; width: 100%;margin:0px -1px;padding:0px;}
        .cardLin4{height: 180px;text-align:left;border-bottom: 1px solid black;margin:0px;padding:0px;}
        .cardLin5{text-align:left;}
        .cardLin3{padding-top: 2px;padding-bottom: 2px;}
        .cardAtkAndDef{float:right;}
        .search{
            text-align: center;
        }
        textarea {
            
            -webkit-appearance: none;
            border-radius: 0;
        }
    </style>
</head>
<body>
    <h1  align="center">Galaxy Card Game</h1>
    <div class="search">
        <form method="GET">
            <label for="search">卡片 ID/名称:</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search ?? '') ?>">
            <span class="effect-group">
                <label for="effect">效果:</label>
                <input type="text" id="effect" name="effect" value="<?= htmlspecialchars($effect ?? '') ?>">
            </span>
            <button type="submit">查询</button>
            <button type="button" class="reset-btn" onclick="location.href='index.php'">重置</button>
        </form>
    </div>


    <div class="cardList">
        <?php foreach ($cards as $card): ?>
            <div class="card">
                <div class="cardLin1">
                    <span class="cardName"><?= htmlspecialchars($card['name']) ?></span>
                    <span class="cardAttribute"><?= htmlspecialchars($card['attribute']) ?></span>
                </div>
                <div class="cardLin2">
                    <span class="cardRace"><?= htmlspecialchars($card['race']) ?></span>
                    <span style="visibility: hidden;">字段:<span class="cardZiduan"><?= htmlspecialchars($card['setcode']) ?></span></span>
                    
                    <span class="cardTypeAndLevel">
                        <span class="cardType"><?= htmlspecialchars($card['type']) ?></span>
                        <?php if($card['level']>0): ?><span class="cardLevel"><?= htmlspecialchars($card['level']) ?>补给</span><?php endif;?>
                    </span>
                </div>
                <div class="cardLin3">
                    <img class="cardPic" src="https://ghfast.top/https://raw.githubusercontent.com/FogMoe/galaxycardgame/master/pics/<?= htmlspecialchars($card['id']) ?>.jpg" alt="<?= htmlspecialchars($card['name']) ?>">
                </div>
                <div class="cardLin4">
                    <textarea readonly class="cardMiaoshu"><?= htmlspecialchars($card['desc']) ?></textarea>
                </div>
                <div class="cardLin5">
                    <span class="cardId">ID:<?= htmlspecialchars($card['id']) ?></span>
                    <span class="cardAtkAndDef">ATK:<?= htmlspecialchars($card['atk']) ?>&nbsp;/&nbsp;HP:<?= htmlspecialchars($card['def']) ?></span>
                </div>
            </div>
        <?php endforeach;?>
    </div>
    

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= htmlspecialchars($previousPageUrl) ?>">上一页</a>
        <?php endif; ?>

        <span class="page-jump">
            <input type="number" id="pageInput" min="1" max="<?= $totalPages ?>" placeholder="页码" value="<?= $page ?>">
            <button type="button" id="jumpBtn" onclick="jumpToPage()">跳转</button>
        </span>

        <?php if ($page < $totalPages && count($cards)>=10 ): ?>
            <a href="?<?= htmlspecialchars($nextPageUrl) ?>">下一页</a>
        <?php endif; ?>
    </div>
    <p align="center">总共<?= $totalCards ?> 条数据，当前第 <?= $page ?> 页，共 <?= $totalPages ?> 页</p>

    <script>
    function jumpToPage() {
        const pageInput = document.getElementById('pageInput');
        const targetPage = parseInt(pageInput.value);
        const maxPage = <?= $totalPages ?>;

        if (targetPage && targetPage >= 1 && targetPage <= maxPage) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', targetPage);
            window.location.href = '?' + urlParams.toString();
        } else {
            alert('请输入有效的页码（1-' + maxPage + '）');
        }
    }

    // 支持回车键跳转
    document.getElementById('pageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            jumpToPage();
        }
    });
    </script>
    <h4  align="center"><a href="https://gcg.fog.moe/">点此返回GCG首页</a></h4>
    <footer>
        <!-- <a href="https://beian.miit.gov.cn/" target="_blank">鲁ICP备2022009156号-1</a> -->
        <!-- <br><br> -->
        <a href="https://fog.moe/" target="_blank">&copy; 2025 FOGMOE</a>
    </footer>
</body>
</html>