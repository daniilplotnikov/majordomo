// Загружаем необходимые файлы
include_once("./config.php");
include_once("./lib/perfmonitor.class.php");
include_once("./lib/loader.php");
include_once("./load_settings.php");

// Проверяем, нужно ли загрузить только часть страницы
if (isset($_GET['part_load']) && checkFromCache('reload:'.md5($_SERVER['REQUEST_URI']))) {
    $res = array();
    $res['TITLE'] = '';
    $res['CONTENT'] = '';
    $res['NEED_RELOAD'] = 1;
    echo json_encode($res);
    exit;
}

// Загружаем необходимые классы
include_once(DIR_MODULES . "panel.class.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");

// Создаем экземпляры классов
$session = new session("prj");
$cl = new control_modules();
$app = new panel();

// Восстанавливаем или получаем параметры приложения
if ($md != $app->name) {
    $app->restoreParams();
} else {
    $app->getParams();
}

// Запускаем приложение
$result = $app->run();

// Обрабатываем фильтр, если он указан
if (isset($filterblock) and $filterblock != '') {
    $blockPattern = '/(.*?)/is';
    preg_match($blockPattern, $result, $match);
    $result = $match[1];
}

// Заменяем константы языка на их значения
if (preg_match_all('/&\#060\#LANG_(.+?)\#&\#062/is', $result, $matches)) {
    $total = count($matches[0]);
    for ($i = 0; $i < $total; $i++) {
        if (defined('LANG_' . $matches[1][$i])) {
            $result = str_replace($matches[0][$i], constant('LANG_' . $matches[1][$i]), $result);
        } else {
            echo "'" . $matches[1][$i] . "'=>'',\n";
        }
    }
}

// Заменяем ссылки на админскую панель
$result = str_replace("nf.php", "admin.php", $result);

// Выполняем общую пост-обработку
require(ROOT.'lib/utils/postprocess_general.inc.php');

// Выполняем пост-обработку подписок
require(ROOT.'lib/utils/postprocess_subscriptions.inc.php');

// Производим ускорение процесса, если разрешено
if ((!defined('DISABLE_PANEL_ACCELERATION') || DISABLE_PANEL_ACCELERATION!=1)) {
    $result = str_replace(' href="/admin.php?', ' onclick="return partLoad(this.href);" href="/admin.php?', $result);
}

// Если нужно загрузить только часть страницы
if (isset($_GET['part_load'])) {
    $res = array();
    $res['TITLE'] = '';
    $res['CONTENT'] = '';
    $res['NEED_RELOAD'] = 1;
    $cut_begin = '
';
    $cut_begin_index = mb_strpos($result, $cut_begin);
    $cut_end = '
';
    $cut_end_index = mb_strpos($result, $cut_end);
    if (is_integer($cut_begin_index) && is_integer($cut_end_index)) {
        $cut_begin_index += mb_strlen($cut_begin) + 2;
        $res['CONTENT'] = mb_substr($result, $cut_begin_index, ($cut_end_index - $cut_begin_index));
        $res['NEED_RELOAD'] = 0;
        if (headers_sent() || is_integer(mb_strpos($res['CONTENT'], '$(document).ready')) || is_integer(mb_strpos($res['CONTENT'], '$(function(')) || is_integer(mb_strpos($res['CONTENT'], 'codemirror/'))) {
            $res['CONTENT'] = '';
            $res['NEED_RELOAD'] = 1;
        }
    }
    echo json_encode($res);
    exit;
}

// Выводим результат
echo $result;
