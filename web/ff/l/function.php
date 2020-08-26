<?php
/**
 * 通用函数定义
 * 以 chk get is htm do 等为前缀，动词_名词方式命名
 *
 * @author Fufu, 2013-07-18
 */

// 取得 整数（类型），返回整数（正负）或 0，0155，0xFF
function get_int($int)
{
    return is_int($int) ? $int : 0;
}

// 取得 整数，返回整数（正负）或 0，-1
function get_intval($int)
{
    return is_numeric($int) ? intval($int) : 0;
}

// 取传递值
function get_fr($key)
{
    return isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : '');
}

// 取 COOKIE 或 SESSION 值
function get_cs($key)
{
    return isset($_SESSION[$key]) ? $_SESSION[$key] : (isset($_COOKIE[$key]) ? $_COOKIE[$key] : '');
}

// 强制转向到主域名访问
function fix_host()
{
    global $g_host;

    $wwwhost = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST']
        : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    $wwwuri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    if ($wwwhost != $g_host) {
        @header('HTTP/1.1 301 Moved Permanently');
        @header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $g_host . $wwwuri . '');
        exit();
    }
}

// gbk 转换为 utf-8
function utf8($data)
{
    return get_chars($data, 'gbk', 'utf-8');
}

// utf-8 转换为 gbk
function gbk($data)
{
    return get_chars($data, 'utf-8', 'gbk');
}

// 自动转换字符集 支持数组转换
function get_chars($txt, $from = 'gbk', $to = 'utf-8')
{
    $from = strtoupper($from) == 'UTF8' ? 'utf-8' : $from;
    $to = strtoupper($to) == 'UTF8' ? 'utf-8' : $to;

    if (strtoupper($from) === strtoupper($to) || empty($txt) || (is_scalar($txt) && !is_string($txt))) {
        // 如果编码相同或者非字符串则不转换
        return $txt;
    }

    if (is_string($txt)) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($txt, $to, $from);
        } elseif (function_exists('iconv')) {
            return iconv($from, $to . '//IGNORE', $txt);
        } else {
            return $txt;
        }
    } elseif (is_array($txt)) {
        foreach ($txt as $key => $val) {
            $_key = get_chars($key, $from, $to);
            $txt[$_key] = get_chars($val, $from, $to);
            if ($key != $_key) {
                unset($txt[$key]);
            }
        }
        return $txt;
    } else {
        return $txt;
    }
}

// 整个文件夹拷贝
function xcopy($s, $d, $z)
{
    if (!is_dir($s)) {
        return 0;
    }

    if (!is_dir($d)) {
        if (!mkdir($d, 0777)) {
            return 0;
        }
    }

    $h = dir($s);
    while ($e = $h->read()) {
        if ($e != "." && $e != "..") {
            if (is_dir($s . DS . $e)) {
                $z && xcopy($s . DS . $e, $d . DS . $e, $z);
            } else {
                copy($s . DS . $e, $d . DS . $e);
            }
        }
    }

    return 1;
}

// 取ip段
function get_ip_x($ip = '', $n = 3)
{
    $ip || $ip = get_ip();
    $add_ip = array('', '.*', '.*.*', '.*.*.*');
    $n < 4 && strpos($ip, '.') && $ip = implode('.', array_slice(explode('.', $ip), 0, $n)) . $add_ip[4 - $n];

    return $ip;
}

// 当前服务器 IP，返回 IP 或 空串
function get_server_ip()
{
    isset($_SERVER) && (($ip = $_SERVER['SERVER_ADDR']) || ($ip = $_SERVER['LOCAL_ADDR']))
    || ($ip = getenv('SERVER_ADDR'));
    $ip || $ip = gethostbyname($_SERVER['SERVER_NAME']);

    return fip($ip);
}

// 信息弹窗，历史留存，建议使用 layer 等弹窗方式
function msg($str, $url = 'javascript:history.back();')
{
    ob_end_clean();
    echo '<scr' . 'ipt type="text/javasc' . 'ript">ale' . 'rt("' . $str . '");window.self.location="' .
         trim($url) . '";</' . 'script>';
    exit();
}

// 页面跳转
function go_url($url = '/', $status = 0, $html = 1)
{
    ob_end_clean();
    $url = safe_url($url);
    if ($html || headers_sent()) {
        echo '<title>Redirecting..</title><me' . 'ta http-equiv="refresh" content="0;url=' . $url . '">';
    } else {
        $status && http_response_code($status);
        header('Location: ' . $url);
    }

    exit();
}

// 转换数组为 SQL 中使用的逗号分隔条件 IN(1,2,3); $with = 1 前后是否包含 $pre
function get_in($arr, $with = 0, $need = 1, $pre = ',')
{
    $ret = implode($pre, get_array($arr, $need, $pre));
    $with = $with ? $pre : '';
    $ret = $ret || $ret === 0 || $ret === '0' ? $with . $ret . $with : '';

    return $ret;
}

// 用于权限检测是否有包含：
// chk_strpos('2,4,5', 5) == stripos(',,2,4,5,,', ',5,');
// chk_strpos('2,4,5', array(3,5)) == array_intersect(array(2,4,5), array(3,5));
function chk_in($str, $find = '', $pre = ',')
{
    return $find ?
        (is_array($find)
            ? array_intersect(get_array($str), $find)
            : stripos($pre . $pre . $str . $pre . $pre, $pre . $find . $pre))
        : 0;
}

// 取得验证码值并重置验证码
function get_chkcode()
{
    $chkcode = isset($_SESSION['ssChkCode']) ? $_SESSION['ssChkCode'] : '';
    $_SESSION['ssChkCode'] = '';
    $_SESSION['ssChkCode_last'] = '';

    return $chkcode;
}

// 两个日期差值，返回秒数
function get_date_diff($date1 = 0, $date2 = 0)
{
    return ($date1 = get_time($date1)) && ($date2 = get_time($date2)) ? ($date2 - $date1) : 0;
}

// 将秒数转为天、时分秒
function get_strftime($time, $date2 = -1, $day_str = '天, ')
{
    $day = 0;

    // 有第二个参数表示要计算日期差值
    $date2 >= 0 && $time = get_date_diff($time, $date2);
    if ($time >= 86400) {
        $day = intval($time / 86400);
        $time = $time % 86400;
    }

    return ($day ? ($day . $day_str) : '') . gmstrftime('%H:%M:%S', $time);
}

// 取昨天
function get_yesterday($date = 1, $re_time = 1)
{
    $time = get_time($date);
    $time -= 86400;

    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取今天
function get_today($date = 1, $re_time = 1)
{
    $time = get_time($date);

    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取明天
function get_tomorrow($date = 1, $re_time = 1)
{
    $time = get_time($date);
    $time += 86400;

    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得周一
function get_monday($date = 1, $re_time = 1)
{
    $time = get_time($date);
    $time -= ((mk_date('w', $time) == 0 ? 7 : mk_date('w', $time)) - 1) * 86400;

    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得周日
function get_sunday($date = 1, $re_time = 1)
{
    $time = get_time($date);
    $time += (7 - (mk_date('w', $time) == 0 ? 7 : mk_date('w', $time))) * 86400;

    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得上周一
function get_last_monday($date = 1, $re_time = 1)
{
    $time = strtotime(get_monday($date, 0) . ' -7 day');
    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得下周一
function get_next_monday($date = 1, $re_time = 1)
{
    $time = get_monday($date, 1) + 604800;
    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得当月第一天
function get_first_day($date = 1, $re_time = 1)
{
    $date = mk_date('Y-m-01', get_time($date));
    return $re_time ? get_time($date) : $date;
}

// 取得上月第一天
function get_last_month($date = 1, $re_time = 1, $num = -1)
{
    $time = strtotime(get_first_day($date, 0) . " {$num} month");
    return $re_time ? $time : mk_date('Y-m-01', $time);
}

// 取得当月最后一天
function get_last_day($date = 1, $re_time = 1)
{
    $time = strtotime(get_first_day($date, 0) . ' +1 month') - 1;
    return $re_time ? $time : mk_date('Y-m-d', $time);
}

// 取得下月第一天
function get_next_month($date = 1, $re_time = 1)
{
    $time = strtotime(get_first_day($date, 0) . ' +1 month');
    return $re_time ? $time : mk_date('Y-m-01', $time);
}

// 取日期范围
function get_range_date($day1 = -7, $day2 = 0, $re_time = 0)
{
    $time2 = is_numeric($day2) ? strtotime('+' . $day2 . 'day') : get_time($day2);
    $time1 = is_numeric($day1) ? $time2 + $day1 * 86400 : get_time($day1);

    return $re_time ? array($time1, $time2) : mk_date('Y-m-d', $time1) . ' - ' . mk_date('Y-m-d', $time2);
}

// 转换日期显示格式
function new_date($format, $date)
{
    return mk_date($format, get_time($date));
}

// 年月日 周 时分
function get_ymdw()
{
    $week = array('日', '一', '二', '三', '四', '五', '六');

    return str_replace('@', '周' . $week[date('w', get_time(get_ymd()))], mk_date('Y-m-d @ H:i'));
}

// 得到常用年月日格式
function get_ymd($time = '')
{
    return mk_date('Y-m-d', $time);
}

// 根据日期得到 unix 时间戳
function get_time($time)
{
    return is_number($time) ? ($time == 1 ? time() : $time) : is_date($time, 2);
}

// 检查是否是标准日期，不要动默认值，可忽略时间或秒
// 默认返回标准日期时间 - 连接，$notime = 1，返回标准日期部分，不带时间, $notime = 2，返回 time 的时间戳
function is_date($time, $notime = 0)
{
    $ret = '';

    if (!empty($time)) {
        // 去除结尾的 .000 MSSQL DateTime
        ($i = strpos($time, '.')) && $time = substr($time, 0, $i);
        // 去除T/Z
        $time = str_replace(array('T', 'Z'), array(' ', ''), $time);
        // 替换日期和时间之前多个空格的情况，2017-08-19 12:22:30，2017/08/19 12:22:30，2017年08月19日 12:22:30，2017/08/19
        $a = array('/年/', '/月/', '/日/', '/时/', '/分/', '/秒/');
        $b = array('-', '-', ' ', ':', ':', '');
        $time = preg_replace($a, $b, $time);
        $time = preg_replace('/\s+/', ' ', trim($time));
        $pat = '/^[1-2]\d{3}(\/|\-)(0?[1-9]|10|11|12)(\\1)([1-2]?[0-9]|0[1-9]|30|31)' .
               '(|\s+(0?\d|1\d|2[0-3])(:(0?\d|[1-5]\d)){1,2})$/';
        if (preg_match($pat, $time, $matches)) {
            // 取得日期部分，校验真实性及闰年月
            $date = explode(' ', $time, 2);
            $date = explode($matches[1], $date[0]);
            list($year, $month, $day) = $date;
            if (checkdate($month, $day, $year)) {
                switch ($notime) {
                    case 1:
                        $ret = implode('-', $date);
                        break;
                    case 2:
                        $ret = strtotime($time);
                        break;
                    default:
                        // 短横线连接
                        $ret = str_replace('/', '-', $time);
                        break;
                }
            }
        }
    }

    return $notime == 2 ? get_id($ret) : $ret;
}

/**
 * 生成无符号日期格式
 * 固定 14 位长度字符串, e.g. 20140625170515
 *
 * @param  int $time 时间戳
 * @return string
 */
function mk_send_time($time = 0)
{
    return $time ? (string)date('YmdHis', $time) : '';
}

/**
 * 返回无符号日期的 unix 时间戳
 *
 * @param  string $time 14 位 mk_send_time() 格式时间
 * @return int
 */
function get_send_time($time)
{
    if (is_number($time) && strlen($time) == 14) {
        $time = substr($time, 0, 4) . '-' . substr($time, 4, 2) . '-' . substr($time, 6, 2) . ' ' .
                substr($time, 8, 2) . ':' . substr($time, 10, 2) . ':' . substr($time, 12, 2);
        $time = strtotime($time);
    } else {
        $time = 0;
    }

    return $time;
}

// 根据年月取得当前季度的月份列表，可传年、月，日期，时间戳，为空时取当前日期
function get_qr($date = '', $month = 0)
{
    $ret = array();

    if ($month) {
        $year = get_id($date);
        $month = get_id($month);
        if (!$year || !$month || !is_date($year . '-' . $month . '-01')) {
            return $ret;
        }
    } else {
        $date || $date = time();
        if ($date = get_time($date)) {
            $year = mk_date('Y', $date);
            $month = mk_date('n', $date);
        } else {
            return $ret;
        }
    }

    // 年、月正常时取值
    $ret[0][0] = $ret[1][0] = $ret[2][0] = $ret[3][0] = $year;
    $ret[0][1] = ceil($month / 3);
    $ret[1][1] = $ret[0][1] * 3 - 3 + 1;
    $ret[2][1] = $ret[1][1] + 1;
    $ret[3][1] = $ret[1][1] + 2;

    // 数组结构
    // array(0 => array(年, 季度), 1 => array(年, 当季第一个月月份), ...)

    return $ret;
}

// 检查 Email 格式
function is_email($email)
{
    // return strlen($email) > 6 && preg_match('/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/', $email);
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// 检查是否是 utf-8 编码
function is_utf8($str)
{
    return preg_match('%^(?:
                    [\x09\x0A\x0D\x20-\x7E] # ASCII
                    | [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
                    | \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
                    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
                    | \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
                    | \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
                    | [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
                    | \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
                    )*$%xs', $str);
}

// 文件下载处理
function do_download($file = '', $new_name = '', $is_stream = 0)
{
    ob_get_length() > 0 && @ob_end_clean();

    if ($is_stream) {
        $down = $file;
        $name = empty($new_name) ? mk_filename() : $new_name;
        $size = strlen($down);
    } else {
        if (is_array($file)) {
            $down = realpath(WEB . $file['attach']);
            $name = $file['title'] && $file['type']
                ? $file['title'] . '.' . $file['type']
                : (empty($new_name) ? basename($down) : $new_name);
        } else {
            $down = $file;
            $name = empty($new_name) ? basename($down) : $new_name;
            if (!chk_is_file($down)) {
                header('HTTP/1.1 404 Not Found');
                header('Status:404 Not Found');
                exit();
            }
        }
        $size = filesize($down);
    }

    header('Content-Description: File Transfer');
    //header('Content-type: ' . $ext);
    header('Content-Type: application/octet-stream');
    header('Last-Modified: ' . mk_date('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: pre-check=0, post-check=0, max-age=0');
    header('Content-Transfer-Encoding: binary');
    header('Content-Encoding: none');
    header('Pragma: public');
    strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') && $name = rawurlencode($name);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-length: ' . sprintf('%u', $size));

    if ($is_stream) {
        echo $down;
    } else {
        readfile($down);
    }

    exit();
}

// 是否 CLI 模式
function is_cli()
{
    return PHP_SAPI == 'cli';
}

// 是否 https://，非空并且不为 off（ISAPI with IIS） 则是。
function is_https()
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off';
}

// 是否 AJAX 提交
function is_ajax()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// 是否 Pjax
function is_pjax()
{
    return isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true';
}

// 用户头像（本地为 jpg）
function get_photo($photo = '', $type = 0, $ext = '.jpg')
{
    $arr = array('', '_big', '_middle', '_small');
    $photo && ($pos = strpos($photo, '?')) && $photo = substr($photo, 0, $pos);
    $ext = mk_ltrim($ext, '.', '.');

    if (!$photo) {
        $photo = '/v/images/photo.gif';
    } elseif (!strpos('.' . $photo, '/')) {
        // 本地图片，不带路径的补全
        $photo = ATTACH_URL . 'photo/' . mk_rtrim($photo, $ext, $arr[$type] . $ext);
    } elseif (!stripos('.' . $photo, 'http://') && $photo != '/v/images/photo.gif') {
        // 本地带路径的图片
        $photo = mk_rtrim($photo, $ext, $arr[$type] . $ext);
    }

    return $photo;
}

// 取图片缩略图地址
function get_small_pic($pic, $small = 's')
{
    ($tmp = strrchr($pic, '.')) && $pic = str_replace($tmp, $small . $tmp, $pic);
    return $pic;
}

// 取得规范的 username
function get_username($username)
{
    $username = (!empty($username) && chk_username($username)) ? mk_html($username) : '';
    return $username;
}

// 检查 username 是否规范
function chk_username($username)
{
    $strlen = strlen($username);
    //if(chk_badstr($username) || !preg_match("/^[a-zA-Z0-9_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]+$/", $username)){
    if (chk_badstr($username) || 20 < $strlen || $strlen < 2) {
        return false;
    } else {
        return true;
    }
}

// 检查字符串是否有非法字符
function chk_badstr($str)
{
    return $str !== get_wd($str);
}

// 自定义 htmlspecialchars
function new_hsc($str)
{
    return preg_replace(
        '/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/',
        '&\\1',
        str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $str)
    );
}

// 返回页码列表
function get_pages_nav($total, $page_size = 20, $page = 1, $url = '', $before = 3, $after = 3)
{
    global $config;

    // p = page; n = totle 地址栏简洁
    $pat = empty($config['PathInfo']) ? array('/p=\d*/i','/n=\d*/i') : array('/\/p\/\d*/i', '/\/n\/\d*/i');
    $url || $url = preg_replace($pat, '', get_uri());
    $url = $config['PathInfo']
        ? (rtrim($url, '/') . '/n/' . $total . '/p/{p}/')
        : ((strpos($url, '?') === false ? '?' : (rtrim($url, '&') . '&')) . 'n=' . $total . '&p={p}');
    $page = min($pages = ceil($total / $page_size), ($page ? $page : 1));

    $html = '';
    $page > 1 && $html .= '<a href="' . str_replace('{p}', 1, $url) . '">1</a> ';

    if ($page <= $before) {
        for ($i = 1; $i < $page; $i++) {
            $html .= '<a href="' . str_replace('{p}', $i, $url) . '">' . $i . '</a> ';
        }
    } else {
        for ($i = $page - $before; $i < $page; $i++) {
            $html .= '<a href="' . str_replace('{p}', $i, $url) . '">' . $i . '</a> ';
        }
    }

    $html .= '<a class="the_page_nav" href="' . str_replace('{p}', $page, $url) . '">' . $page . '</a> ';

    if ($pages >= $page + $after) {
        for ($i = $page + 1; $i <= $page + $after; $i++) {
            $html .= '<a href="' . str_replace('{p}', $i, $url) . '">' . $i . '</a> ';
        }
    } else {
        for ($i = $page + 1; $i <= $pages; $i++) {
            $html .= '<a href="' . str_replace('{p}', $i, $url) . '">' . $i . '</a> ';
        }
    }

    $page < $pages && $html .= '<a href="' . str_replace('{p}', $pages, $url) . '">' . $pages . '</a>';

    return $html;
}

// 返回页码列表
function get_pages($total, $page_size = 20, $page = 1, $url = '', $sort = false)
{
    global $config;

    // p = page; n = totle 地址栏简洁
    $pat = empty($config['PathInfo']) ? array('/p=\d*/i','/n=\d*/i') : array('/\/p\/\d*/i', '/\/n\/\d*/i');
    $url || $url = preg_replace($pat, '', get_uri());
    $url = $config['PathInfo']
        ? (rtrim($url, '/') . '/n/' . $total . '/p/{p}/')
        : ((strpos($url, '?') === false ? '?' : (rtrim($url, '&') . '&')) . 'n=' . $total . '&p={p}');
    $page = min($lastpg = ceil($total / $page_size), ($page ? $page : 1));
    $prepg = $page - 1;
    $nextpg = $page + 1;
    $pagenav = $sort ? '' : '<span>共<strong>' . $total . '</strong>条信息</span>';
    $pagenav .= $page > 1
        ? ' <a href="' . str_replace('{p}', 1, $url) . '">首页</a> ' .
          '<a href="' . str_replace('{p}', $prepg, $url) . '">上页</a> '
        : ' <span class="cgray">首页</span> <span class="cgray">上页</span> ';
    $pagenav .= $nextpg > $lastpg
        ? ' <span class="cgray">下页</span> <span class="cgray">末页</span> '
        : ' <a href="' . str_replace('{p}', $nextpg, $url) . '">下页</a> ' .
          '<a href="' . str_replace('{p}', $lastpg, $url) . '">末页</a> ';
    $pagenav .= '<span>第<strong>' . $page . '/' . $lastpg . '</strong>页</span>';

    return $pagenav;
}

// 返回页码列表
function get_pagelist($total, $page_size = 20, $page = 1, $url = '', $sort = false)
{
    global $config;

    // p = page; n = totle 地址栏简洁
    $pat = empty($config['PathInfo']) ? array('/p=\d*/i','/n=\d*/i') : array('/\/p\/\d*/i', '/\/n\/\d*/i');
    $url || $url = preg_replace($pat, '', get_uri());
    $url = $config['PathInfo']
        ? (rtrim($url, '/') . '/n/' . $total . '/p/{p}/')
        : ((strpos($url, '?') === false ? '?' : (rtrim($url, '&') . '&')) . 'n=' . $total . '&p={p}');
    $page = min($lastpg = ceil($total / $page_size), ($page ? $page : 1));
    $prepg = $page - 1;
    $nextpg = $page + 1;
    $pagenav = '<div class="btn-group mt5 mb5">';
    $pagenav .= $sort ? '' : '<button>共' . $total . '条信息</button>';
    $pagenav .= $page > 1
        ? ' <button><a href="' . str_replace('{p}', 1, $url) . '">首页</a></button> ' .
          '<button><a href="' . str_replace('{p}', $prepg, $url) . '">上页</a></button> '
        : ' <button>首页</button> <button>上页</button> ';
    $pagenav .= $nextpg > $lastpg
        ? ' <button>下页</button> <button>末页</button> '
        : ' <button><a href="' . str_replace('{p}', $nextpg, $url) . '">下页</a></button> ' .
          '<button><a href="' . str_replace('{p}', $lastpg, $url) . '">末页</a></button> ';
    $pagenav .= '<button>第' . $page . '/' . $lastpg . '页</button>';
    $pagenav .= '</div>';

    return str_replace('<button>', '<button type="button" class="btn btn-default btn-sm to-grid">', $pagenav);
}

// 返回页码列表(ajax)
function get_pages_ajax($total, $page_size = 20, $page = 1, $url = '', $sort = false)
{
    // url = javascript:$.newsList('{p}');
    $page = min($lastpg = ceil($total / $page_size), ($page ? $page : 1));
    $prepg = $page - 1;
    $nextpg = $page + 1;
    $pagenav = $sort ? '' : '<span>共<strong>' . $total . '</strong>条信息</span>';
    $pagenav .= $page > 1
        ? ' <a href="' . str_replace('{p}', 1, $url) . '">首页</a> ' .
          '<a href="' . str_replace('{p}', $prepg, $url) . '">上页</a> '
        : ' <span class="cgray">首页</span> <span class="cgray">上页</span> ';
    $pagenav .= $nextpg > $lastpg
        ? '<span class="cgray">下页</span> <span class="cgray">末页</span> '
        : '<a href="' . str_replace('{p}', $nextpg, $url) . '">下页</a> ' .
          '<a href="' . str_replace('{p}', $lastpg, $url) . '">末页</a> ';
    $pagenav .= '<span>第<strong>' . $page . '/' . $lastpg . '</strong>页</span>';

    return $pagenav;
}

// 缩略图处理，绝对缩略大小
function img_resize($simg, $w, $h, $dimg = '', $dext = 0, $dels = 1, $quality = 90, $img_data = '')
{
    if (!file_exists($simg) && !$img_data) {
        return 1;
    }
    if ($dimg == '') {
        $dimg = $simg;
    }
    $delimg = $dels ? $simg : '';
    $imginfo = $img_data ? @getimagesizefromstring($img_data) : @getimagesize($simg);
    if (!is_array($imginfo)) {
        return 2;
    }
    list($sw, $sh, $st) = $imginfo;
    if ($img_data) {
        $sim = @imagecreatefromstring($img_data);
    } else {
        switch ($st) {
            case 1:
                $sim = @imagecreatefromgif($simg);
                break;
            case 2:
                $sim = @imagecreatefromjpeg($simg);
                break;
            case 3:
                $sim = @imagecreatefrompng($simg);
                break;
            case 6:
                if (is_file($l = L . 'BMP.php')) {
                    if (!class_exists('BMP')) {
                        include($l);
                    }
                    $sim = BMP:: toGD($simg);
                    break;
                } else {
                    return 3;
                }
            // no break
            default:
                return 3;
            // 1 = GIF，2 = JPG，3 = PNG，4 = SWF，5 = PSD，6 = BMP，
            // 7 = TIFF(intel byte order)，8 = TIFF(motorola byte order)，9 = JPC，10 = JP2，
            // 11 = JPX，12 = JB2，13 = SWC，14 = IFF，15 = WBMP，16 = XBM
        }
    }
    if (!$sim) {
        return 4;
    }
    $ow = $sw;
    $oh = $sh;
    if ($w <= 0 && $h <= 0) {
        $w = $sw;
        $h = $sh;
    } elseif ($w <= 0) {
        $w = ($sw / $sh) * $h;
    } elseif ($h <= 0) {
        $h = ($sh / $sw) * $w;
    } elseif ($w > 0 && $h > 0) {
        $ws = $sw / $w;
        $wh = $sh / $h;
        if ($ws > $wh) {
            $sw = $wh * $w;
        } else {
            $sh = $ws * $h;
        }
    }
    $dx = ($wx = $ow - $sw) > 0 ? floor($wx / 2) : 0;
    $dy = ($hx = $oh - $sh) > 0 ? floor($hx / 2) : 0;
    $dim = @imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dim, 255, 255, 255);
    imagefill($dim, 0, 0, $white);
    @imagecopyresampled($dim, $sim, 0, 0, $dx, 0, $w, $h, $sw, $sh);
    // 输出文件类型
    $exts = array(1 => 'gif', 2 => 'jpg', 3 => 'png');
    $d_ext = get_fileext($dimg);
    if ((($st != $dext && $dext > 0) || ($dext > 0 && !in_array($d_ext, $exts))) && in_array($dext, array(1, 2, 3))) {
        $dimg = mk_rtrim($dimg, $d_ext, $exts[$dext]);
        $st = $dext;
    }
    // 禁止生成 BMP 目标图片，强制转为 JPG
    if ($st == 6) {
        $dimg = mk_rtrim($dimg, $d_ext, 'jpg');
        $st = 2;
    }
    // 如果需要删除源文件
    if ($delimg) {
        del_file($delimg);
    }
    switch ($st) {
        case 1:
            @imagegif($dim, $dimg);
            break;
        case 2:
            @imagejpeg($dim, $dimg, $quality);
            break;
        case 3:
            @imagepng($dim, $dimg);
            break;
        default:
            return 3;
    }
    @imagedestroy($dim);

    // imagedestroy($sim);
    return 9;
}

// 缩略图，等比大小
function img_resizes($simg, $w, $h, $dimg = '', $dext = 0, $dels = 1, $quality = 90, $img_data = '')
{
    if (!file_exists($simg) && !$img_data) {
        return 1;
    }
    if ($dimg == '') {
        $dimg = $simg;
    }
    $delimg = $dels ? $simg : '';
    $imginfo = $img_data ? @getimagesizefromstring($img_data) : @getimagesize($simg);
    if (!is_array($imginfo)) {
        return 2;
    }
    list($ow, $oh, $ot) = $imginfo;
    if ($img_data) {
        $sim = @imagecreatefromstring($img_data);
    } else {
        switch ($ot) {
            case 1:
                $sim = @imagecreatefromgif($simg);
                break;
            case 2:
                $sim = @imagecreatefromjpeg($simg);
                break;
            case 3:
                $sim = @imagecreatefrompng($simg);
                break;
            case 6:
                if (is_file($l = L . 'BMP.php')) {
                    if (!class_exists('BMP')) {
                        include($l);
                    }
                    $sim = BMP:: toGD($simg);
                    break;
                } else {
                    return 3;
                }
            // no break
            default:
                return 3;
            // 1 = GIF，2 = JPG，3 = PNG，4 = SWF，5 = PSD，6 = BMP，
            // 7 = TIFF(intel byte order)，8 = TIFF(motorola byte order)，9 = JPC，10 = JP2，
            // 11 = JPX，12 = JB2，13 = SWC，14 = IFF，15 = WBMP，16 = XBM
        }
    }
    if (!$sim) {
        return 4;
    }
    $tw = $ow;
    $th = $oh;
    if ($w > 0 && $h > 0) {
        if ($ow / $oh >= $w / $h) {
            if ($ow > $w) {
                $tw = $w;
                $th = $w * $oh / $ow;
            }
        } else {
            if ($oh > $h) {
                $tw = $h * $ow / $oh;
                $th = $h;
            }
        }
    } else {
        if ($w <= 0 && $h <= 0) {
            $tw = $ow;
            $th = $oh;
        } else {
            if ($w <= 0 && $oh > $h && $h > 0) {
                $tw = $h * $ow / $oh;
                $th = $h;
            }
            if ($h <= 0 && $ow > $w && $w > 0) {
                $tw = $w;
                $th = $w * $oh / $ow;
            }
        }
    }
    $dim = @imagecreatetruecolor($tw, $th);
    @imagecopyresampled($dim, $sim, 0, 0, 0, 0, $tw, $th, $ow, $oh);
    // 输出文件类型
    $exts = array(1 => 'gif', 2 => 'jpg', 3 => 'png');
    $d_ext = get_fileext($dimg);
    if ((($ot != $dext && $dext > 0) || ($dext > 0 && !in_array($d_ext, $exts))) && in_array($dext, array(1, 2, 3))) {
        $dimg = mk_rtrim($dimg, $d_ext, $exts[$dext]);
        $ot = $dext;
    }
    // 禁止生成 BMP 目标图片，强制转为 JPG
    if ($ot == 6) {
        $dimg = mk_rtrim($dimg, $d_ext, 'jpg');
        $ot = 2;
    }
    // 如果需要删除源文件
    if ($delimg) {
        del_file($delimg);
    }
    switch ($ot) {
        case 1:
            @imagegif($dim, $dimg);
            break;
        case 2:
            @imagejpeg($dim, $dimg, $quality);
            break;
        case 3:
            @imagepng($dim, $dimg);
            break;
        default:
            return 3;
    }
    @imagedestroy($dim);

    // imagedestroy($sim);
    return 9;
}

// 模拟 strstr() 的第三个参数，返回 $str 中，$search 之前的数据，strstr() 是返回 $str 中 $search 及之后的数据
function strstr_b($str, $search, $need = 1)
{
    $arr = explode($search, $str, 2);
    $str = reset($arr);

    return $need == 0 ? $str : (empty($str) ? '' : $str . $search);
}

// strstr_b 的从右查找版本
function strstr_rb($str, $search, $need = 1)
{
    if ($str) {
        $arr = explode($search, $str);
        if (count($arr) > 2) {
            array_pop($arr);
            $str = implode($search, $arr);
        } else {
            $str = reset($arr);
        }
        $str = $need == 0 ? $str : (empty($str) ? '' : $str . $search);
    }

    return $str;
}

// 字符串个数，utf8(5) = nvarchar(5)
function str_len($str, $charset = 'utf-8')
{
    if (function_exists('mb_strlen')) {
        $ret = mb_strlen($str, $charset);
    } elseif ($charset == 'utf-8') {
        // 将字符串分解为单元
        $ret = preg_match_all('/./us', $str, $match);
    } else {
        // 不考虑了
        $ret = strlen($str);
    }

    return $ret;
}

// 截取字符串
function get_strs($str, $length, $suffix = '..', $start = 0, $charset = 'utf-8')
{
    if (function_exists('mb_strimwidth')) {
        $new = mb_strimwidth($str, 0, $length * 2, $suffix, $charset);
    } else {
        if (function_exists('mb_substr')) {
            $new = mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $new = iconv_substr($str, $start, $length, $charset);
            false === $new && $new = $str;
        } else {
            $re['utf-8'] = '/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/';
            $re['gb2312'] = '/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/';
            $re['gbk'] = '/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/';
            $re['big5'] = '/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/';
            preg_match_all($re[$charset], $str, $match);
            $new = implode('', array_slice($match[0], $start, $length));
        }

        $suffix && $str != $new && $new .= $suffix;
    }

    return $new;
}

// 截取字符串，推荐使用 (utf8)
function get_str($str, $length, $suffix = '..')
{
    $new = $next_str = '';
    $i = $n = $last_i = 0;
    $str_length = strlen($str);                 // 字符串的字节数

    while (($n < $length) and ($i <= $str_length)) {
        $temp_str = substr($str, $i, 1);
        $ascnum = ord($temp_str);               // 得到字符串中第 $i 位字符的 ascii 码

        if ($ascnum >= 224) {                   // 如果 ASCII 位高于 224
            $new .= substr($str, $i, 3);        // 根据 UTF-8 编码规范，将 3 个连续的字符计为单个字符
            $last_i = -3;                       // 记录最后一个字符的长度
            $i = $i + 3;                        // 实际 Byte 计为 3
            $n++;                               // 字串长度计 1
        } elseif ($ascnum >= 192) {             // 如果 ASCII 位高与 192
            $new .= substr($str, $i, 2);        // 根据 UTF-8 编码规范，将 2 个连续的字符计为单个字符
            $last_i = -2;                       // 记录最后一个字符的长度
            $i = $i + 2;                        // 实际 Byte 计为 2
            $n++;                               // 字串长度计 1
        } elseif ($ascnum >= 65 && $ascnum <= 90) { // 如果是大写字母，
            $new .= substr($str, $i, 1);
            $last_i = -1;                       // 记录最后一个字符的长度
            $i = $i + 1;                        // 实际的 Byte 数仍计 1 个
            $n++;                               // 但考虑整体美观，大写字母计成一个高位字符
        } else {                                // 其他情况下，包括小写字母和半角标点符号，
            $new .= substr($str, $i, 1);
            $last_i = -1;                       // 记录最后一个字符的长度
            $i = $i + 1;                        // 实际的 Byte 数计 1 个
            $n = $n + 0.5;                      // 小写字母和半角标点等与半个高位字符宽
        }

        $next_str = substr($str, $i, 3);
    }

    // 超过长度时去掉最后一个字符并加上省略字符
    $suffix && $next_str && $new = substr($new, 0, $last_i) . $suffix;

    return $new;
}

// 获取文件 MIME 类型
function get_file_mime_type($file, $encoding = true)
{
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME);
        $mime = @finfo_file($finfo, $file);
        finfo_close($finfo);
    } else {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $mime = @mime_content_type($file);
        } else {
            $file = escapeshellarg($file);
            $mime = @exec("file --mime -b {$file}");
        }
    }
    if (!$mime) {
        return '';
    }
    if ($encoding) {
        return $mime;
    }

    return substr($mime, 0, strpos($mime, '; '));
}

// 取文件扩展名
function get_fileext($name)
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

// 生成唯一 ID
function mk_uniqid($pre = '', $md5 = 0)
{
    return $pre . ($md5 ? md5(uniqid(mt_rand(0, 999999), true) . random(8)) : uniqid()) . strtolower(random(3));
}

// 生成 GUID
function mk_guid($trim = true)
{
    // Windows
    if (function_exists('com_create_guid') === true) {
        return $trim === true ? trim(com_create_guid(), '{}') : com_create_guid();
    }

    // OSX/Linux
    if (function_exists('openssl_random_pseudo_bytes') === true) {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
        $guidv4 = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $trim ? $guidv4 : '{' . $guidv4 . '}';
    }

    // Other
    mt_srand((double)microtime() * 10000);
    $charid = strtolower(md5(uniqid(rand(), true)));
    $hyphen = chr(45);                  // "-"
    $lbrace = $trim ? '' : chr(123);    // "{"
    $rbrace = $trim ? '' : chr(125);    // "}"
    $guidv4 = $lbrace .
        substr($charid,  0,  8) . $hyphen .
        substr($charid,  8,  4) . $hyphen .
        substr($charid, 12,  4) . $hyphen .
        substr($charid, 16,  4) . $hyphen .
        substr($charid, 20, 12) .
        $rbrace;

    return $guidv4;
}

// 文件名生成，前缀避免多台服务器生成文件重复，md5 唯一性更强
function mk_filename($pre = '', $md5 = 0)
{
    //return date("ymdHis") . mt_rand(10000, 99999);
    return mk_uniqid($pre, $md5);
}

// HTML转js代码
function htm2js($str, $isjs = 1)
{
    $str = addslashes(str_replace(array("\r", "\n", "\t"), array('', '', ''), $str));
    return $isjs ? 'document.write("' . $str . '");' : $str;
}

// 取得纯文本，清除空格和所有HTML代码
function get_txt($str)
{
    return empty($str) ? '' : str_replace('　', '', str_ireplace('&nbsp;', '', preg_replace('/\s+/', '', frall($str))));
}

// 为 HTML 的 head 说明取得相应的文本
function get_des($str, $len = 76, $end = '。')
{
    return empty($str) ? '' : get_str(get_txt($str), $len, $end);
}

// 验证并返回掩码 1 - 32
function get_mask($mask, $err = 0)
{
    return preg_match('/^\d{1,2}$/', $mask = fr($mask)) && ($ret = get_id($mask)) >= 0
        ? ($ret > 32 ? $err : $ret)
        : $err;
}

// 检查并返回 IP 对，array(IP1, IP2, 0正确，1错误)
function get_ip2($ip = '')
{
    $ret = array('', '', 1);
    $ip = str_replace('-', '/', trim($ip));
    ($ipv4 = get_array($ip, 0, '/')) && ($ret[0] = fip($ipv4[0])) && ($ret[1] = fip($ipv4[1])) && $ret[2] = 0;

    return $ret;
}

// 检查并返回 IP 对，3 个 IP 必须全对，array(IP1, IP2, IP2, 0正确，1错误)
function get_ip3($ip = '')
{
    $ret = array('', '', '', 1);
    $ip = str_replace('-', '/', trim($ip));
    $ipv4 = get_array($ip, 0, '/');
    $ipv4 && ($ret[0] = fip($ipv4[0])) && ($ret[1] = fip($ipv4[1])) && ($ret[2] = fip($ipv4[2])) && $ret[3] = 0;

    return $ret;
}

// 校验 IP 段 8.8.8.8  192.168.1.0/24 $with_mask: 1 掩码有则校验；2 必校验；0 必不校验
// filter_var('8.8.8.8', FILTER_VALIDATE_IP)
function fipx($ip, $with_mask = 1)
{
    $with_mask = $with_mask ? '(\/(1\d|2\d|3[0-2]|[1-9]))' . ($with_mask == 1 ? '?' : '') : '';
    $pat = '/^((1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)\.){3}(1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)' . $with_mask . '$/';
    $ip = fr($ip);

    return preg_match($pat, $ip) ? $ip : '';
}

// 完全验证或提取并返回 ipv4，是否返回空串，是否完全匹配（0 是为提取），是否允许前导 0
function fip($ip, $re_null = 1, $match = 1, $allow_0 = 0)
{
    $allow_0 = $allow_0 ? '0*' : '';
    $pat = '/(' . $allow_0 . '(1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)\.){3}' .
           $allow_0 . '(1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)/';
    $ip = fr($ip);

    return preg_match($pat, $ip, $matches) && (!$match || $matches[0] == $ip)
        ? $matches[0]
        : ($re_null ? '' : '0.0.0.0');
}

// 提取并返回 ipv4 数组，是否允许前导 0
function fips($ip, $allow_0 = 0)
{
    $allow_0 = $allow_0 ? '0*' : '';
    $pat = '/(' . $allow_0 . '(1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)\.){3}' .
           $allow_0 . '(1\d\d|2[0-4]\d|25[0-5]|[1-9]\d|\d)/';
    $ip = fr($ip);

    return preg_match_all($pat, $ip, $matches) ? $matches[0] : array();
}

// 解析 IP
function ip_parse($ip, $mask = 32)
{
    if ($ip = trim($ip)) {
        // 兼容掩码位写在一起的情况
        strpos($ip, '/') && list($ip, $mask) = explode('/', $ip);
        $ip = fip($ip);
        $mask = get_mask($mask);
    }

    if (!($ip && $mask)) {
        return array('ip_ok' => 0);
    }

    $ret = array('ip' => $ip, 'mask' => $mask);
    $ret['ip_long'] = get_ip2long($ip);
    $ret['ip_mask_long'] = 0xFFFFFFFF << (32 - $mask) & 0xFFFFFFFF;
    $ret['ip_start_long'] = $ret['ip_long'] & $ret['ip_mask_long'];
    $ret['ip_end_long'] = $ret['ip_long'] | (~$ret['ip_mask_long']) & 0xFFFFFFFF;
    $ret['ip_start'] = long2ip($ret['ip_start_long']);
    $ret['ip_end'] = long2ip($ret['ip_end_long']);
    $ret['ip_mask'] = long2ip($ret['ip_mask_long']);

    // 解决负数问题
    $ret['ip_start_long'] = get_ip2long($ret['ip_start']);
    $ret['ip_end_long'] = get_ip2long($ret['ip_end']);
    $ret['ip_mask_long'] = get_ip2long($ret['ip_mask']);

    if ($ret['ip_start_long'] == $ret['ip_end_long']) {
        // 单个 IP
        $ret['ip_start_ok'] = $ret['ip_start'];
        $ret['ip_end_ok'] = $ret['ip_end'];
        $ret['ip_start_ok_long'] = $ret['ip_start_long'];
        $ret['ip_end_ok_long'] = $ret['ip_end_long'];
    } else {
        $ret['ip_start_ok'] = long2ip($ret['ip_start_long'] + 1);
        $ret['ip_end_ok'] = long2ip($ret['ip_end_long'] - 1);
        $ret['ip_start_ok_long'] = get_ip2long($ret['ip_start_ok']);
        $ret['ip_end_ok_long'] = get_ip2long($ret['ip_end_ok']);
    }

    // 检查输入 IP 是否与起始 IP 一致
    $ret['ip_ok'] = $ret['ip_long'] === $ret['ip_start_long'] ? 1 : 0;

    return $ret;
}

// my_ip 是否包含在 ip 范围内
function ip_in($ip, $my_ip, $mask = 32)
{
    if (($my_ip = fip($my_ip)) && ($ip = trim($ip))) {
        // 兼容掩码位写在一起的情况
        strpos($ip, '/') && list($ip, $mask) = explode('/', $ip);
        $ip = fip($ip);
        $mask = get_mask($mask);
    }

    if (!($my_ip && $ip && $mask)) {
        return 0;
    }

    $mask = 32 - $mask;

    return ip2long($ip) >> $mask == ip2long($my_ip) >> $mask;
}

// 清除所有代码，仅保留文本，多个空白转为一个，去除换行
function cls_all($str)
{
    $farr = array('/\r+\n+/', '/\s+/', '/\s(?=\s)/');
    $tarr = array('', ' ', '$1');

    return trim(preg_replace($farr, $tarr, cls_htmls($str)));
}

// 去除特殊符号的 fr
function frall($str)
{
    $str = cls_all($str);
    return rm_html($str);
}

// cls_all 的别名，用于过滤表单数据，要保留单引号等特殊字符可直接用 cls_all() 过滤
function fr($str)
{
    $str = cls_all($str);
    return mk_html($str);
}

// cls_msg 基础上提取生成超链接，用于过滤表单多行文本数据
function fmsg($msg)
{
    return mk_a(str_replace('&amp;', '&', cls_msg($msg)), 1, 'amsg');
}

// 用于 textarea 编辑的 fmsg
function un_fmsg($msg)
{
    return $msg ? br2nl(cls_a($msg, 1)) : '';
}

// 留言框内容过滤，保留换行
function cls_msg($msg)
{
    $msg = cls_htmls($msg);
    $msg = nl2br(mk_html($msg));

    return preg_replace(array('/\s(?=\s)/', '/\s*(<br\s*\/?\s*>\s*){2,}/im'), array('$1', '$1'), $msg);
}

// 替换回车换行符，$nls = 0 连续换行仅保留一个 /(\r\n)+|\n+|\r+/
function cls_nl($str, $s = '', $nls = 1)
{
    return $s && !$nls
        ? preg_replace('/(\r\n)+|\r*\n+|\n*\r+/', $s, $str)
        : str_replace(array("\r\n", "\n", "\r"), $s, $str);
}

// 转换回车为换行符
function br2nl($str, $br = PHP_EOL)
{
    return preg_replace('/<br\\s*?\/??>\s?/i', $br, $str);
}

// 清理 HTML 代码
function cls_html($str)
{
    $farr = array(
        '/\sclass\s*=\s*("|\')\w+(\\1)/i',
        '/<scr' . 'ipt([^>]*?)>(.*?)<\/script([^>]*?)>/is',
        '/<i?fr' . 'ame([^>]*?)>(.*?)<\/i?frame([^>]*?)>/is',
        '/<sty' . 'le([^>]*?)>(.*?)<\/style([^>]*?)>/is',
        '/<\!--(.*?)-->/is'
    );

    return preg_replace($farr, '', $str);
}

// 清理 HTML 代码及标签
function cls_htmls($str)
{
    return strip_tags(cls_a(cls_html($str), 1));
}

// 清除HTML代码中的链接
function cls_a($str, $txt = '')
{
    return $str ? preg_replace('/\s*<a[^>]*?>(.*?)<\/a[^>]*?>\s*/i', $txt ? ' $1 ' : '', $str) : $str;
}

// 清除文本中的 http/https 链接文本
function cls_url($str, $txt = '')
{
    return $str ? preg_replace('/https?:\/\/[\w\/\?\.\-@:&#%;=]+/i', $txt, $str) : '';
}

// 替换字符串链接文本为超链接，$cn = 1 允许中文网址
function mk_a($str, $target = 1, $class = '', $cn = 0)
{
    if ($str) {
        $target = $target ? ' target="_blank"' : '';
        $class = $class ? ' class="' . $class . '"' : '';
        $regcn = $cn ? '\x{4e00}-\x{9fa5}' : '';
        $reg = '/((https?|ftps?):\/\/(\w+:\w+@)?[' . $regcn . '\w\-]+\.[' . $regcn . '\w\-]+(\.([' .
               $regcn . '\w\-])+)*(:\d+)?[' . $regcn . '\w\/\?\.\-@&#%;=]*)/' . ($regcn ? 'u' : '') . 'i';
        $str = preg_replace($reg, '<a' . $target . $class . ' href="$1">$1</a>', $str);
    }

    return $str;
}

/**
 * 清洗字符串
 * 只保留中英文, 数字, 下划线
 *
 * @param  string $str 待清洗的字符串
 * @param  string $txt 非法字符替换为指定字符
 * @param  string $add 附加正则规则
 * @return mixed
 */
function cls_char($str = '', $txt = '', $add = '')
{
    return preg_replace('/[^\x{4e00}-\x{9fa5}\w' . $add . ']+/u', $txt, $str);
}

// 只保留中英文、数字、下划线、IP和中括号
function cls_ping($str = '', $txt = ' ')
{
    return cls_char($str, $txt, '\.\[\]');
}

// 文件是否存在
function chk_is_file($file = '')
{
    clearstatcache();

    return $file ? is_file($file) : false;
}

// 删除文件夹，支持多层路径
function rm_dirs($dirname, $keepdir = 0)
{
    $dirname = str_replace(array("\n", "\r", '..'), array('', '', ''), $dirname);
    $dirname = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $dirname), DS);

    if (!is_dir($dirname)) {
        return 0;
    }

    $h = opendir($dirname);

    while (($file = readdir($h)) !== false) {
        if ($file != '.' && $file != '..') {
            $dir = $dirname . DS . $file;
            is_dir($dir) ? rm_dirs($dir) : unlink($dir);
        }
    }

    closedir($h);

    return $keepdir ? 1 : (@rmdir($dirname) ? 1 : 0);
}

// 取得当前页地址，域名+文件名
function get_page()
{
    return get_http() . get_uri();
}

// 取得当前页域名地址部分
function get_http()
{
    return (is_https() ? 'https' : 'http') . "://" . get_host();
}

// 取得主机头域名，1 自动，0 不返回端口，2 始终返回端口
function get_host($port = 1)
{
    $host = isset($_SERVER['HTTP_HOST'])
        ? $_SERVER['HTTP_HOST']
        : (!empty($_SERVER['SERVER_NAME'])
            ? ($_SERVER["SERVER_PORT"] != "80"
                ? $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"]
                : $_SERVER["SERVER_NAME"])
            : getenv("SERVER_NAME"));

    if ($host && $port != 1) {
        $host_port = explode(':', $host);
        if ($port == 0) {
            return $host_port[0];
        } else {
            return $host_port[0] ? $host : $host . ':80';
        }
    }

    return $host;
}

// 取得完整URI
function get_uri()
{
    if (isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
    } else {
        $uri = $_SERVER['PHP_SELF'];
        if (isset($_SERVER['argv'])) {
            !empty($_SERVER['argv'][0]) && $uri .= '?' . $_SERVER['argv'][0];
        } else {
            !empty($_SERVER['QUERY_STRING']) && $uri .= '?' . $_SERVER['QUERY_STRING'];
        }
    }

    return $uri;
}

// 判断是否($abc=1)全是字母或($abc=0)字母开头($upper == 1 大写 / 2 小写)
function is_abc($str = '', $abc = 0, $upper = 0)
{
    $add = $abc ? '$' : '';
    $reg = '/^[a-z]+' . $add . '/';
    $upper == 0 && $reg .= 'is';
    $upper == 1 && $reg = '/^[A-Z]+' . $add . '/';

    return preg_match($reg, $str);
}

// 数组转下拉选择框：echo get_select('cid', unserialize(gethtm_php('class2')), 0, 13);
function get_select($name = '', $arr = array(), $cid = 0, $first = 0, $add = '', $subadd = '', $select = 1)
{
    $txt = '';
    if (is_array($arr)) {
        foreach ($arr as $key => $value) {
            if (!empty($value)) { // 空值跳过
                if (!is_numeric($key) || (is_numeric($key) && get_id($key) >= $first)) { // 下标为数字并设置了起始下标时
                    $selected = $key == $cid ? ' selected' : '';
                    $class = '';
                    switch ($subadd) {
                        case 'color':
                            $class = ' style="background:' . $key . '"';
                            break;
                        case 'class':
                            $class = ' class="' . $key . '"';
                            break;
                    }
                    $txt .= '<option value="' . $key . '"' . $selected . $class . '>' . $value . '</option>';
                }
            }
        }
    }
    !$add && $add = ' id="' . $name . '"';

    return $select ? '<select name="' . $name . '" size="1"' . $add . '>' . $txt . '</select>' : $txt;
}

// 数组转单选框：echo get_radio('cid', unserialize(gethtm_php('class2')), 0, 13);
function get_radio($name = '', $arr = array(), $cid = 0, $first = 0, $add = '', $hide = 0)
{
    $txt = '';

    if (is_array($arr)) {
        // 选择隐藏选择框且未指定附加样式时，避免启用 ffradio 时闪烁
        if ($hide && !$add) {
            $class_1 = ' class="none"';
            $class_2 = ' class="fradio"';
        } else {
            $class_1 = $class_2 = '';
        }

        $i = 0;

        foreach ($arr as $key => $value) {
            // 空值跳过
            if (!empty($value)) {
                // 下标为数字并设置了起始下标时
                if (!is_numeric($key) || (is_numeric($key) && get_id($key) >= $first)) {
                    $valid = isset($valid) ? '' : ' datatype="*"';
                    // 传值 cid 为 null 时默认选中第一项
                    $checked = ($key == $cid || ($i == 0 && $cid === null)) ? ' checked="checked"' : '';
                    $txt .= '<input' . $valid . ' name="' . $name . '" id="' . $name . '_' . $key . '"' . $add .
                            ' type="radio"' . $checked . ' value="' . $key . '"' . $class_1 .
                            '> <label' . $class_2 . ' for="' . $name . '_' . $key . '">' . $value . '</label> ';
                    $i++;
                }
            }
        }
    }

    return $txt;
}

// 数组转复选框：echo get_checkbox('cid', unserialize(gethtm_php('class2')), 0, 13);
function get_checkbox($name = '', $arr = array(), $cid = 0, $first = 0, $add = '', $is_valid = 1, $hide = 0)
{
    $txt = '';

    if (is_array($arr)) {
        // 选择隐藏选择框且未指定附加样式时，避免启用 ffradio 时闪烁
        if ($hide && !$add) {
            $class_1 = ' class="none"';
            $class_2 = ' class="fcheckbox"';
        } else {
            $class_1 = $class_2 = '';
        }

        $i = 0;
        foreach ($arr as $key => $value) {
            // 空值跳过
            if (!empty($value)) {
                // 下标为数字并设置了起始下标时
                if (!is_numeric($key) || (is_numeric($key) && get_id($key) >= $first)) {
                    $valid = (!$is_valid || isset($valid)) ? '' : ' datatype="*"';
                    // 传值 cid 为 null 时默认选中第一项
                    $checked = ($key == $cid || ($i == 0 && $cid === null)) ? ' checked="checked"' : '';
                    $txt .= '<input' . $valid . ' name="' . $name . '[]" id="' . $name . '_' . $key . '"' . $add .
                            ' type="checkbox"' . $checked . ' value="' . $key . '"' . $class_1 .
                            '> <label' . $class_2 . ' for="' . $name . '_' . $key . '">' . $value . '</label> ';
                    $i++;
                }
            }
        }
    }

    return $txt;
}

// 数组转 ul li
function get_ul_list(
    $data = array(),
    $param = array(),
    $field = array('news_id', 'subject', 'post_date'),
    $add_class = '',
    $close_field = ''
) {
    $ret = '';

    if ($data && is_array($data)) {
        // 日期格式
        $format = '';
        if ($param['format']) {
            if (is_string($param['format'])) {
                $format = $param['format'];
            } else {
                switch ($param['format']) {
                    case 1:
                        $format = 'Y-m-d';
                        break;
                    case 2:
                        $format = 'Y-m-d H:i';
                        break;
                    default:
                        $format = 'Y-m-d H:i:s';
                        break;
                }
            }
        }

        foreach ($data as $d) {
            // 是否显示关闭样式
            $close_class = $close_field && $d[$close_field] == 0 ? ' class="isclose"' : '';
            $span = $format ? ('<span>' . new_date($format, $d[$field[2]]) . '</span>') : '';
            $subject = $param['len'] ? get_str($d[$field[1]], $param['len']) : $d[$field[1]];
            $href = $param['href']
                ? str_replace('{id}', $d[$field[0]], $param['href'])
                : ('/view.php?d=' . $d[$field[0]]);
            $target = $param['target'] ? ('target="' . $param['target'] . '"') : '';
            $ret .= '<li' . $close_class . '>' . $span . '<a href="' . $href . '" id="ul_list_' . $d[$field[0]] .
                    '" ' . $target . ' title="' . get_txt($d[$field[1]]) . '">' . $subject . '</a></li>';
        }
    }

    return $ret ? ('<ul' . $add_class . '>' . $ret . '</ul>') : '';
}

// GET 请求
function get_request($url, $referer = '', $timeout = 30, $agent = '')
{
    $ret = mk_request(
        $url,
        'get',
        array(),
        $timeout,
        array('referer' => $referer ? $referer : get_page(), 'agent' => $agent)
    );
    return $ret;
}

// POST 请求，type: '' / 'json'
function post_request($url, $data = array(), $referer = '', $timeout = 30, $type = '', $agent = '')
{
    $ret = mk_request(
        $url,
        $type ? $type : 'post',
        $data,
        $timeout,
        array('referer' => $referer ? $referer : get_page(), 'agent' => $agent)
    );
    return $ret;
}

// 获取页面内容，抓取，目前主要用 HTTP 类完成
function get_html($url, $referer = '', $timeout = 30)
{
    return get_request($url, $referer, $timeout);
}

/**
 * 强制返回数组
 * 常用于 array() += array()
 * 字符串以 $pre 分隔成数组
 * null / false / true / 1 / 0 / 123 等非字符串时会转为空数组 []
 *
 * @param  mixed  $data 待处理的数据
 * @param  string $pre  数组分隔符
 * @return array
 */
function get_arr($data = '', $pre = ',')
{
    return is_array($data) || is_object($data)
        ? (array)$data
        : (is_string($data) && $data !== '' ? explode($pre, $data) : array());
}

/**
 * 强制转为数组
 * 常用于数据清洗并返回数组
 * 字符串以 $pre 分隔成数组, 删除数组的空值, 清除值的前后空格, 并返回新数组
 * null: []
 * false: []
 * true: [0 => 1]
 * 1: [0 => 1]
 * 0: [0 => 0] / []
 * 123: [0 => 123]
 * '123,0': [0 => 123, 1 => 0] / [0 => 123]
 *
 * @param  mixed  $data      待处理的数据
 * @param  int    $need_0    1 保留 0 值(默认值, 勿改), 0 不保留
 * @param  string $pre       数组分隔符
 * @param  int    $sub_array 1 递归子数组, 0 仅处理上层数组
 * @return array
 */
function get_array($data = '', $need_0 = 1, $pre = ',', $sub_array = 0)
{
    // 数字也转数组
    $data = is_array($data) || is_object($data) ? (array)$data : (($arr = explode($pre, $data)) ? $arr : array());
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            // 多维数组
            $sub_array && $data[$k] = get_array($v, $need_0, $pre, $sub_array);
        } else {
            $v = trim($v);
            if ($v || ($need_0 && ($v === 0 || $v === '0'))) {
                $data[$k] = $v;
            } else {
                unset($data[$k]);
            }
        }
    }

    return $data;
}

// 根据流程ID号返回流程表单模板文件路径
function get_form_file($id = 0, $form = '', $sub_id = 0, $type = 'process')
{
    $type = V . 'form' . DS . $type . DS . $id . $form . ($sub_id ? '_' . $sub_id : '') . '.php';
    return is_file($type) ? $type : '';
}

// 根据表单属性处理数据
function mk_post($options, $value)
{
    $ret = array('value' => null, 'error' => '');
    if (is_array($options)) {
        switch ($options['value_type']) {
            case 'content':
                $ret['value'] = fmsg($value);
                break;
            case 'text':
                $ret['value'] = fr($value);
                break;
            case 'int':
                $ret['value'] = get_id($value);
                break;
            case 'money':
                $ret['value'] = number_format($value, 2, '.', '');
                break;
        }
    }

    return $ret;
}

// 返回附件列表表单，用于编辑
function get_attach_input($attach_list = '', $if_echo = 1)
{
    $ret = '';
    $attach_list = get_array($attach_list);
    foreach ($attach_list as $v) {
        $ret .= '<li class="attachli"><a class="remove">移除</a> <a class="png" href="' . $v['attach'] .
                '" target="_blank" title="点击下载或查看">' . $v['title'] . '</a><input name="attach[]" value="' .
                $v['attach'] . '" type="hidden" /><input name="attach_title[]" value="' . $v['title'] .
                '" type="hidden" /><input name="attach_id[]" value="' . $v['attach_id'] . '" type="hidden" /></li>';
    }

    if ($if_echo) {
        echo $ret . PHP_EOL;
    }

    return $ret;
}

// 返回附件列表，$user 是否显示附件上传者
function get_attach_list($attach_data, $tpl = '', $user = 1)
{
    $ret = '';
    $tpl == '' && $tpl = '<li><a class="png" href="{$attach}" title="点击下载或查看" target="_blank">{$title}</a></li>';

    if ($attach_data && is_array($attach_data)) {
        foreach ($attach_data as $v) {
            $user_info = $user ? '<span class="author">' . $v['realname'] . '<span> - ' : '';
            $str = $tpl;
            $str = str_replace('{$attach}', FF::Url('file/download/aid/' . $v['attach_id'] . '/'), $str);
            $str = str_replace('{$title}', $user_info . $v['title'], $str);
            $ret .= $str;
        }
    }

    return $ret ? $ret : '<li>无</li>';
}

/**
 * 员工工号补 0
 *
 * e.g. get_job(114) == '0114'
 *
 * @param  int $job_number 工号数字
 * @return string
 */
function get_job($job_number = 0)
{
    return str_pad($job_number, 4, '0', 0);
}

/**
 * 检查是否是工号
 *
 * @param  string $job_number 工号数字
 * @return bool
 */
function chk_job($job_number = '0')
{
    return preg_match('/^\d{4}$/', $job_number);
}

// 数字转人民币大写, $len 限定最长位数（含两位小数和小数点），$fix 1 定长为 $len；0 以 $num 为准
function mk_rmb($num, $len = 0, $fix = 1)
{
    $rmb = $pre = '';
    (!$len || $len > 15) && $len = 15;  // 仟亿

    if (is_numeric($num = trim($num))) {
        $pre = $num[0] == '-' ? '负' : '';
        if (($num_len = strlen($num = str_replace(array('.', '-'), '', sprintf("%.2f", $num)))) <= ($len = $len - 1)) {
            $zh_num = array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
            $zh_yuan = array('分', '角', '元', '拾', '佰', '仟', '万', '拾', '佰', '仟', '亿', '拾', '佰', '仟');

            // 避免负零元
            $num == '-0.00' && $pre = '';

            if ($num_arr = array_reverse(str_split($num))) {
                $fix || $len = $num_len;
                for ($i = 0; $i < $len; $i++) {
                    $rmb = $zh_num[get_id($num_arr[$i])] . $zh_yuan[$i] . $rmb;
                }
            }
        }
    }

    return $pre . $rmb;
}

// 货币千分位
function mk_money($num, $len = 2, $dot = '.', $pre = ',')
{
    return is_numeric($num = trim($num)) ? number_format($num, $len, $dot, $pre) : 0;
}

// 获取浮点数: 数字字符串, 小数位数(不要动默认值)
function get_num($n, $dec = null)
{
    return is_numeric($n = trim($n))
        ? (is_null($dec) ? floatval($n) : (float)number_format($n, $dec, '.', ''))
        : floatval(0);
}

// 用于表单, get_num 的别名, 默认为 2 位小数(不要动默认值)
function fnum($n, $dec = 2)
{
    return get_num($n, $dec);
}

// 获取正整数或 0，用于表单取 ID
function fid($num)
{
    return get_id($num);
}

// 获取正负整数或 0，用于表单取 ID
function fint($num)
{
    return get_intval($num);
}

// 调试信息写入 debug.php 文件
function debug_file($str = array(), $file = 'debug')
{
    $str = is_array($str) || is_object($str) ? print_r($str, 1) : $str;
    mk_file(PHP . 'debug' . DS . $file . '.php', '<?php exit(); ?>' . PHP_EOL . $str);
}

// 调试, 打印数组
function debug_pre($str = array(), $exit = 0, $type = 0)
{
    echo '<br><pre style="clear:both;display:block;font:12px/1.5 Consolas,Inconsolata,Monaco,Courier;color:#333;' .
         'word-wrap:break-word;border:1px solid #ddd;border-left-width:5px;padding:10px;background:#fefff2;">';
    $type ? var_dump($str) : ((($str = print_r($str, 1)) || 1) && print(mk_html($str)));
    echo "</pre>";
    $exit && exit();
}

// 执行时间（微秒）和内存使用情况：debug_time('begin'); debug_time('end'); debug_time('begin', 'end', 6, 'm');
function debug_time($a, $b = '', $c = 4, $d = 'm')
{
    static $_debug_time = array();

    if (is_float($b)) {
        // 记录传入的时间
        $_debug_time['time'][$a] = $b;
    } elseif ($b) {
        // 统计时间和内存使用
        isset($_debug_time['time'][$b]) || $_debug_time['time'][$b] = microtime(true);
        if (!isset($_debug_time['memory'][$b]) && $d == 'm' && function_exists('memory_get_usage')) {
            $_debug_time['memory'][$b] = memory_get_usage();
            $_debug_time['pear'][$b] = memory_get_peak_usage();
        }

        $ret = '';
        if ($d == 'm') {
            $size_memory = $_debug_time['memory'][$b] - $_debug_time['memory'][$a];
            $size_pear = $_debug_time['pear'][$b] - $_debug_time['pear'][$a];
            $size_unit = array('B', 'KB', 'MB', 'GB', 'TB');
            $pos_memory = $pos_pear = 0;

            while ($size_memory >= 1024) {
                $size_memory /= 1024;
                $pos_memory++;
            }

            while ($size_pear >= 1024) {
                $size_pear /= 1024;
                $pos_pear++;
            }

            $ret = round($size_memory, $c) . $size_unit[$pos_memory] . ' / ' .
                   round($size_pear, $c) . $size_unit[$pos_pear];
        }

        return number_format(($_debug_time['time'][$b] - $_debug_time['time'][$a]), $c) . 's' .
               ($ret ? ' | ' . $ret : '');
    } else {
        // 记录当前时间和内存使用
        $_debug_time['time'][$a] = microtime(true);
        if ($d == 'm' && function_exists('memory_get_usage')) {
            $_debug_time['memory'][$a] = memory_get_usage();
            $_debug_time['pear'][$a] = memory_get_peak_usage();
        }
    }

    return null;
}

// 取大文件前 n 行：get_file_head(D . 'alarm.log.txt', 10, 'utf8')；$nl = 1 保留空行
function get_file_head($file = '', $num = 10, $nl = 1, $func = '')
{
    if (!is_file($file)) {
        return array();
    }

    $num = intval($num);
    $num || $num = 10;
    $fp = fopen($file, 'r');
    $ret = array();

    while (!feof($fp) && $num > 0) {
        $row = fgets($fp);
        if ($row !== false) {
            $row = rtrim($row);
            if ($nl || $row !== '') {
                $ret[] = $func ? $func($row) : $row;
                $num--;
            }
        }
    }

    fclose($fp);

    return $ret;
}

// 取大文件最后 n 行：get_file_tail(D . 'alarm.log.txt', 10, 0, 0, 'utf8')；$nl = 1 保留空行；array_reverse 反转数组
function get_file_tail($file = '', $num = 10, $nl = 1, $reverse = 1, $func = '')
{
    if (!is_file($file)) {
        return array();
    }

    $num = intval($num);
    $num || $num = 10;
    $fp = fopen($file, "r");
    $pos = -2;
    $bof = false;
    $ret = array();

    while ($num > 0) {
        $str = '';
        while ($str != "\n") {
            if (fseek($fp, $pos, SEEK_END) == -1) {
                $bof = true;
                break;
            }
            $str = fgetc($fp);
            $pos--;
        }

        $bof && rewind($fp);
        $row = fgets($fp);

        if ($row !== false) {
            $row = rtrim($row);
            if ($nl || $row !== '') {
                $ret[] = $func ? $func($row) : $row;
                $num--;
            }
        }

        if ($bof) {
            break;
        }
    }

    fclose($fp);

    return $reverse ? array_reverse($ret) : $ret;
}

/**
 * 读取文件
 *
 * @param  string $file 文件路径
 * @return null|string
 */
function get_file($file)
{
    return is_file($file) ? @file_get_contents($file) : null;
}

/**
 * 写入文件
 *
 * @param  string $file  文件路径
 * @param  string $str   待写入的内容
 * @param  int    $flags 写入模式, FILE_APPEND 追加
 * @return int           写入的字节数
 */
function mk_file($file, $str, $flags = 0)
{
    // 文件夹不存在时，建立文件夹
    return $file && mk_dirs(dirname($file)) ? @file_put_contents($file, $str, $flags) : 0;
}

/**
 * 删除文件
 *
 * @param  string $file 待删除的文件
 * @return bool
 */
function del_file($file)
{
    return $file && is_file($file) ? @unlink($file) : false;
}

/**
 * 生成字符串, 序列化并打包
 *
 * @param  mixed $str 待打包的内容
 * @return string
 */
function mk_str($str = null)
{
    return base64_encode(serialize($str));
}

/**
 * 还原字符串, 解包并反序列化
 *
 * @param  string $str 待解包的内容
 * @return string
 */
function un_str($str = null)
{
    return unserialize(base64_decode($str));
}

// 缓存到文件并可加密，$time +秒数 / 日期格式 / 时间戳 / max == 1000天，默认为当前时间
function mk_cache($file, $word, $path = '', $xor = 1, $time = 0)
{
    // 缓存时间，使用时用于判断（两种模式：缓存到期时间 / 缓存文件生成时间）与当时时间差
    $now = time();

    if ($time) {
        // max == 最多缓存 1000 天
        ((is_number($time) && $time <= 86400000) || ($time == 'max' && ($time = 86400000))) && $time += $now;
    } else {
        $time = $now;
    }

    $time = mk_send_time($time);

    // 数组、对象、字符串等先编码内容，内容为浮点数时，如：1.67，需要注意
    $word = '<?php exit();//' . $time .
            ($xor ? '1' . get_xor($word, '', 0, $time) : '0' . base64_encode(serialize($word)));

    // 缓存文件路径
    if ($path) {
        // 补全路径
        $pos = strpos($path = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $path), DS, DS), CACHE);
        ($pos === false || $pos !== 0) && $path = mk_ltrim($path, DS, CACHE);
    } else {
        $path = CACHE;
    }

    return mk_file($path . $file . '.php', $word);
}

// 取得加密缓存文件
function get_cache($file, $path = '', $re_array = 0)
{
    // 缓存文件路径
    if ($path) {
        // 补全路径
        $pos = strpos($path = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $path), DS, DS), CACHE);
        ($pos === false || $pos !== 0) && $path = mk_ltrim($path, DS, CACHE);
    } else {
        $path = CACHE;
    }

    // 完整文件路径
    $file = $path . $file . '.php';

    // 读取文本
    $time = '';

    if ($word = get_file($file)) {
        $time = substr($word, 15, 14);  // 缓存时间
        $xor = substr($word, 29, 1);    // 加密标识
        $word = substr($word, 30);      // 缓存内容
        $word = $xor ? get_xor($word, '', 1, $time) : unserialize(base64_decode($word));
    }

    // 返回值强制数组
    ($re_array == 1 || $re_array == 3) && !is_array($word) && $word = array();

    return $re_array == 2 || $re_array == 3 ? array($word, get_send_time($time)) : $word;
}

// 删除缓存文件
function del_cache($file, $path = '')
{
    // 缓存文件路径
    if ($path) {
        // 补全路径
        $pos = strpos($path = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $path), DS, DS), CACHE);
        ($pos === false || $pos !== 0) && $path = mk_ltrim($path, DS, CACHE);
    } else {
        $path = CACHE;
    }

    return del_file($path . $file . '.php');
}

// 单引号特殊处理，缓存到文件变量时使用
function mk_quote($str = '')
{
    global $config;
    return str_replace("'", $config['StrPre'] . "‘’" . $config['StrPre'], $str);
}

// 单引号特殊处理（反向），缓存文件转变量时使用
function un_quote($str = '')
{
    global $config;
    return str_replace($config['StrPre'] . "‘’" . $config['StrPre'], "'", $str);
}

// 密码加密算法
function mk_pwd($password)
{
    global $config;
    return get_md5($config['PwdPre'] . $password . '^.');
}

// 用户登录状态码
function mk_logined($str)
{
    return mk_pwd('*?' . $str);
}

// 用户登录状态缓存文件名
function mk_md5_file($last_login)
{
    return get_md5($last_login, 8);
}

// 信息缓存文件夹，勿修改默认值
function get_cache_path($str, $folder = 'user', $with_str = 1)
{
    $str = get_md5('%&' . $str . $folder);

    // 补全路径
    $pos = strpos($folder = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $folder), DS, DS), CACHE);
    ($pos === false || $pos !== 0) && $folder = mk_ltrim($folder, DS, CACHE);

    return $folder . $str[1] . DS . $str[3] . DS . $str[7] . DS . ($with_str ? $str . DS : '');
}

// 类库数据缓存配置信息，返回目录和文件名，便于清除该方法生成的所有缓存文件
function get_cache_base($base_str, $base_dir = '__M__')
{
    // 通常是类::方法名
    $base_name = get_md5('$+' . $base_str . $base_dir);

    // 补全路径
    $pos = strpos($base_path = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $base_dir), DS, DS), CACHE);
    ($pos === false || $pos !== 0) && $base_path = mk_ltrim($base_path, DS, CACHE);

    // 清除该目录即可清除所有缓存
    $base_path .= $base_name[2] . DS . $base_name[5] . DS . $base_name[9] . DS . $base_name . DS;

    return array(
        'base_name' => $base_name,
        'base_path' => $base_path
    );
}

// 返回当前用户id
function get_user_id($user_id = 0)
{
    return get_id($user_id ? $user_id : I('s.user.user_id', 0));
}

// 返回当前用户工号
function get_job_number($job_number = 0)
{
    return get_id($job_number ? $job_number : I('s.user.job_number', 0));
}

// 分页 SQL 语句处理
function mk_page_sql(
    $page_size = 0,
    $totle = 0,
    $page = 0,
    $where = '',
    $order_by = '',
    $fields = '*',
    $table = '',
    $add_sql = '',
    $dbtype = ''
) {
    global $config;

    $sql = '';
    if ($table) {
        $where && stripos(mk_ltrim($where), 'WHERE') !== 0 && $where = "WHERE " . $where;
        // 排序
        $order_by = $order_by ? ("ORDER BY " . $order_by) : "";
        // 默认 SQL
        $sql = "SELECT {$fields} FROM {$table} {$add_sql} {$where} {$order_by}";
        // 是否需要分页
        if ($page_size > 0 && $totle > 0) {
            // 当前页处理
            $page = get_id($page);
            $page = min(ceil($totle / $page_size), ($page ? $page : 1));

            // 根据 SQL 类型生成语句
            !$dbtype && $dbtype = $config['Filter'];
            switch ($dbtype) {
                case 'mssql':
                    // mssql 2005+
                    if ($page > 1) {
                        // 分页查询
                        $sql
                            = "SELECT *
                                FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY T_TMP) T_RN
                                      FROM (SELECT TOP " . ($page_size * $page) . " {$fields}, T_TMP = 0
                                            FROM {$table} {$add_sql} {$where} {$order_by}) T_OK) T_TT
                                WHERE T_TT.T_RN > " . ($page_size * ($page - 1));
                    } else {
                        $sql = str_replace("SELECT", "SELECT TOP {$page_size}", $sql);
                    }
                    break;
                case 'oracle':
                    if ($page > 1) {
                        $sql
                            = "SELECT *
                                FROM (SELECT T_BB.*, ROWNUM T_R
                                      FROM (SELECT {$fields}
                                            FROM {$table} {$add_sql} {$where} {$order_by}) T_BB
                                      WHERE ROWNUM <= " . ($page_size * $page) . ") T_OK
                                WHERE T_R > " . ($page_size * ($page - 1));
                    } else {
                        if ($order_by) {
                            $sql
                                = "SELECT *
                                    FROM (SELECT {$fields}
                                          FROM {$table} {$add_sql} {$where} {$order_by}) T_OK
                                    WHERE ROWNUM <= " . $page_size;
                        } else {
                            $where && $where = "(" . mk_ltrim($where, 'WHERE ') . ")";
                            $where = ($where ? $where . " AND" : "") . " ROWNUM <= " . $page_size;
                            $sql
                                = "SELECT {$fields}
                                    FROM {$table} {$add_sql}
                                    WHERE {$where}";
                        }
                    }
                    break;
                case 'mysql':
                    // mysql 5+
                    $sql .= $page > 1 ?
                        " LIMIT " . ($page - 1) * $page_size . ', ' . $page_size :
                        " LIMIT " . $page_size;
                    break;
            }
        }
    }

    return $sql;
}

// 取得链接地址，带 http 的外链地址，内链用 FF::Url()
function get_url($url = '', $http = 0)
{
    return mk_ltrim(safe_url($url), WEB_ROOT, $http ? get_http() : WEB_HTTP);
}

// 检查并返回密码，8-18位任意字符，必须包含字母和数字 /^(?![0-9]+$)(?![a-zA-Z]+$)[a-zA-Z0-9]{8,18}$/
function chk_pwd($pwd = '')
{
    return preg_match('/^(?=.*?[A-Za-z])(?=.*?\d).{8,18}$/', $pwd, $ok) ? $ok[0] : '';
}

// 检查是否为身份证号
function chk_idcard($idcard)
{
    return preg_match('/^\d{17}(\d|x)$/i', $idcard);
}

// 取得身份证号
function get_idcard($idcard)
{
    $idcard = trim($idcard);
    return chk_idcard($idcard) ? strtoupper($idcard) : '';
}

// 检查是否为QQ号
function chk_qq($qq)
{
    return preg_match('/^[1-9]\d{4,10}$/', $qq);
}

// 取得QQ号
function get_qq($qq)
{
    return chk_qq($qq) ? $qq : 0;
}

// 检查是否为手机号 '/^(0|86|17951)?(13\d|15[012356789]|17[0678]|18\d|14[57])\d{8}$/'
function chk_mobile($mobile)
{
    return preg_match('/^1\d{10}$/', $mobile);
}

// 取得手机号
function get_mobile($mobile)
{
    return chk_mobile($mobile = trim($mobile)) ? $mobile : '';
}

// 多个手机号参数整理，返回数组或逗号分隔的字符串
function get_mobiles($mobile, $re_string = 0)
{
    $ret = get_array(filter(get_array($mobile, 0), 'get_mobile'));
    return $re_string ? implode(',', $ret) : $ret;
}

// 数据树
function get_tree($rows, $id = 'news_class_id', $pid = 'parent_news_class_id', $child = 'child', $root = 0)
{
    $tree = array();

    if (is_array($rows)) {
        $array = array();
        foreach ($rows as $key => $item) {
            $array[$item[$id]] = &$rows[$key];
        }
        foreach ($rows as $key => $item) {
            $parent_id = $item[$pid];
            if ($root == $parent_id) {
                $tree[] = &$rows[$key];
            } else {
                if (isset($array[$parent_id])) {
                    $parent = &$array[$parent_id];
                    $parent[$child][] = &$rows[$key];
                }
            }
        }
    }

    return $tree;
}

// 数组转下拉选择框（带层级）
function get_select_tree(
    $name = '',
    $arr = array(),
    $arr_key = 'news_class_id',
    $arr_value = 'news_class',
    $cid = 0,
    $add = '',
    $subadd = '',
    $value_pre = '',
    $with_select = 1
) {
    $txt = '';
    if (is_array($arr)) {
        foreach ($arr as $value) {
            if (!empty($value)) {
                // 空值跳过
                if (is_array($value) && $value['child']) {
                    $selected = $value[$arr_key] == $cid ? ' selected' : '';
                    $txt .= '<option value="' . $value[$arr_key] . '"' . $selected . $subadd . '>' .
                            $value_pre . $value[$arr_value] . '</option>';
                    // 处理子级项
                    $value_pre = $value_pre ? $value_pre . ' - ' : ' - ';
                    $txt .= get_select_tree('', $value['child'], $arr_key, $arr_value, $cid, '', '', $value_pre, 0);
                    $value_pre = '';
                } else {
                    $selected = $value[$arr_key] == $cid ? ' selected' : '';
                    $txt .= '<option value="' . $value[$arr_key] . '"' . $selected . $subadd . '>' .
                            $value_pre . $value[$arr_value] . '</option>';
                }
            }
        }
    }

    if ($with_select) {
        $add || $add = ' id="' . $name . '"';
        return '<select name="' . $name . '" size="1"' . $add . '>' . $txt . '</select>';
    } else {
        return $txt;
    }
}

// 返回 MSG 带图标
function get_msg($msg = '', $icon = 'ok')
{
    $icons = array(
        'info' => 13683,
        'warn' => 13544,
        'query' => 13545,
        'ban' => 13757,
        'no' => 13700,
        'add' => 13701,
        'err' => 13702,
        'ok' => 13703,
        'attach' => 13708,
        'list' => 13714,
        'box' => 13712,
        'boxlist' => 13706,
        'tag' => 13640,
        'tip' => 13543,
        'user' => 13662,
        'search' => 13661,
        'home' => 13660,
        'save' => 13666,
        'share' => 13664,
        'text' => 13665,
        'set' => 13679,
        'del' => 13680,
        'more' => 13682,
        'fav' => 13685,
        'love' => 13686,
        'rmb' => 13697,
        'edit' => 13696,
        'right' => 13726,
        'left' => 13727,
        'up' => 13728,
        'down' => 13729,
        'exit' => 13741,
        'update' => 13740,
        'enter' => 13760,
        'photo' => 13778
    );
    $icon = empty($icons[$icon]) ? reset($icons) : $icons[$icon];

    return '<i><em>&#' . $icon . ';</em>' . $msg . '</i>';
}

// 令牌码
function get_token($token_pwd, $add = '', $type = 'hour')
{
    $token = '';
    if ($token_pwd) {
        // 当时时间，+-1 小时，$add : '+1' / '-1' / ''
        $time = $add && $type ? $add . ' ' . $type : 'now';
        $time = strtotime($time);
        /*
        // 月、日、时为不带前导 0 的整数
        $year   = get_id(mk_date('Y', $time));
        $month  = get_id(mk_date('n', $time));
        $day    = get_id(mk_date('j', $time));
        $hour   = get_id(mk_date('G', $time));

        // 加密算法：
        // md5(O 加 ((小时 / 月)取两位小数 + 年) 加 日 加 (奇数小时 %，偶数小时 $) 加 md5(小时 加 _Ff 加 密码)的前5位)
        $token = md5('O' . (round($hour / $month, 2) + $year) . $day . ($hour % 2 ? '%' : '$') . substr(md5($hour . '_Ff' . $token_pwd), 0, 5));
        */
        // 加密算法：
        // md5(m.yH.d + lt__u + 双方配置的相同 pwd 密钥 + lt__i)
        $token = md5(mk_date('m.yH.d', $time) . $token_pwd);
    }

    return $token;
}

// 时间变成多少分钟前
function get_long_time($date)
{
    $curr = time();
    $time = get_time($date);
    $tmp = $curr - $time;
    if ($tmp < 60) {
        $ret = $tmp . '秒前';
    } elseif ($tmp < 3600) {
        $ret = floor($tmp / 60) . '分钟前';
    } elseif ($tmp < 86400) {
        $ret = floor($tmp / 3600) . '小时前';
    } elseif ($tmp < 259200) {
        //3天内
        $ret = floor($tmp / 86400) . '天前';
    } else {
        $ret = mk_date('m月d日', $time);
    }
    echo $ret;
}

// 取 C# 时间
function get_cdate($date = '', $his = 1)
{
    return str_replace('T', ' ', substr($date, 0, $his ? 19 : 10));
}

// 通过时间得到相应的频率，小时为基数
function get_cnt($date1, $date2, $cnt = 5, $num = 3600)
{
    $ret = 0;

    // 20140723163500
    $date1 = get_send_time($date1 . '00');
    $date2 = get_send_time($date2 . '00');
    if ($date1 && $date2 && $date2 >= $date1) {
        $ret = ceil(($date2 - $date1) / $num) * $cnt;
    }

    return $ret;
}

// 三维数组按某个值排序，1 降序，0 升序
function array_sort($array, $sort, $order_by = 1)
{
    $tmp = $ret = array();
    foreach ($array as $k => $v) {
        $tmp[$k] = $v[$sort];
    }

    $order_by ? arsort($tmp) : asort($tmp);

    foreach ($tmp as $k => $v) {
        $ret[$k] = $array[$k];
    }

    unset($tmp, $array);

    return $ret;
}

// 空白值转为 NULL
function mk_null($data = array())
{
    foreach ($data as $k => $v) {
        '' === $v && $data[$k] = null;
    }

    return $data;
}

// 不区分大小写的 in_array()
function in_array_i($value, $array)
{
    return in_array(strtolower($value), array_map('strtolower', $array));
}

// 著名的防 XSS 函数 @author kallahar@kallahar.com
function remove_xss($val)
{
    // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
    // this prevents some character re-spacing such as <java\0script>
    // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
    $val = preg_replace('/([\x00-\x08]|[\x0b-\x0c]|[\x0e-\x19])/', '', $val);

    // straight replacements, the user should never need these since they're normal characters
    // this prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29>
    $search = 'abcdefghijklmnopqrstuvwxyz';
    $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $search .= '1234567890!@#$%^&*()';
    $search .= '~`";:?+/={}[]-_|\'\\';

    for ($i = 0; $i < strlen($search); $i++) {
        // ;? matches the ;, which is optional
        // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

        // &#x0040 @ search for the hex values
        $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // with a ;
        // &#00064 @ 0{0,7} matches '0' zero to seven times
        $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // with a ;
    }

    // now the only remaining whitespace attacks are \t, \n, and \r
    $ra1 = array(
        'javascript',
        'vbscript',
        'expression',
        'applet',
        'meta',
        'xml',
        'blink',
        'link',
        'style',
        'script',
        'embed',
        'object',
        'iframe',
        'frame',
        'frameset',
        'ilayer',
        'layer',
        'bgsound',
        'title',
        'base'
    );
    $ra2 = array(
        'onabort',
        'onactivate',
        'onafterprint',
        'onafterupdate',
        'onbeforeactivate',
        'onbeforecopy',
        'onbeforecut',
        'onbeforedeactivate',
        'onbeforeeditfocus',
        'onbeforepaste',
        'onbeforeprint',
        'onbeforeunload',
        'onbeforeupdate',
        'onblur',
        'onbounce',
        'oncellchange',
        'onchange',
        'onclick',
        'oncontextmenu',
        'oncontrolselect',
        'oncopy',
        'oncut',
        'ondataavailable',
        'ondatasetchanged',
        'ondatasetcomplete',
        'ondblclick',
        'ondeactivate',
        'ondrag',
        'ondragend',
        'ondragenter',
        'ondragleave',
        'ondragover',
        'ondragstart',
        'ondrop',
        'onerror',
        'onerrorupdate',
        'onfilterchange',
        'onfinish',
        'onfocus',
        'onfocusin',
        'onfocusout',
        'onhelp',
        'onkeydown',
        'onkeypress',
        'onkeyup',
        'onlayoutcomplete',
        'onload',
        'onlosecapture',
        'onmousedown',
        'onmouseenter',
        'onmouseleave',
        'onmousemove',
        'onmouseout',
        'onmouseover',
        'onmouseup',
        'onmousewheel',
        'onmove',
        'onmoveend',
        'onmovestart',
        'onpaste',
        'onpropertychange',
        'onreadystatechange',
        'onreset',
        'onresize',
        'onresizeend',
        'onresizestart',
        'onrowenter',
        'onrowexit',
        'onrowsdelete',
        'onrowsinserted',
        'onscroll',
        'onselect',
        'onselectionchange',
        'onselectstart',
        'onstart',
        'onstop',
        'onsubmit',
        'onunload'
    );
    $ra = array_merge($ra1, $ra2);

    $found = true; // keep replacing as long as the previous round replaced something

    while ($found == true) {
        $val_before = $val;
        for ($i = 0; $i < sizeof($ra); $i++) {
            $pattern = '/';
            for ($j = 0; $j < strlen($ra[$i]); $j++) {
                if ($j > 0) {
                    $pattern .= '(';
                    $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                    $pattern .= '|';
                    $pattern .= '|(&#0{0,8}([9|10|13]);)';
                    $pattern .= ')*';
                }
                $pattern .= $ra[$i][$j];
            }

            $pattern .= '/i';
            $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
            $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags

            if ($val_before == $val) {
                // no replacements were made, so exit the loop
                $found = false;
            }
        }
    }

    return $val;
}

// 输出安全的 html
function safe_html($text, $tags = null)
{
    $text = trim($text);

    // 完全过滤注释
    $text = preg_replace('/<!--?.*-->/', '', $text);

    // 完全过滤动态代码
    $text = preg_replace('/<\?|\?' . '>/', '', $text);

    // 完全过滤js
    $text = preg_replace('/<script?.*\/script>/', '', $text);
    $text = str_replace('[', '&#091;', $text);
    $text = str_replace(']', '&#093;', $text);
    $text = str_replace('|', '&#124;', $text);

    // 过滤换行符
    $text = preg_replace('/\r?\n/', '', $text);

    // br
    $text = preg_replace('/<br(\s\/)?' . '>/i', '[br]', $text);
    $text = preg_replace('/<p(\s\/)?' . '>/i', '[br]', $text);
    $text = preg_replace('/(\[br\]\s*){10,}/i', '[br]', $text);

    // 过滤危险的属性，如：过滤on事件lang js
    while (preg_match('/(<[^><]+)(lang|on|action|background|codebase|dynsrc|lowsrc)[^><]+/i', $text, $mat)) {
        $text = str_replace($mat[0], $mat[1], $text);
    }

    while (preg_match(
        '/(<[^><]+)(window\.|javascript:|js:|about:|file:|document\.|vbs:|cookie)([^><]*)/i',
        $text,
        $mat
    )) {
        $text = str_replace($mat[0], $mat[1] . $mat[3], $text);
    }

    if (empty($tags)) {
        $tags = 'table|td|th|tr|i|b|u|strong|img|p|br|div|strong|em|ul|ol|li|dl|dd|dt|a';
    }

    // 允许的HTML标签
    $text = preg_replace('/<(' . $tags . ')( [^><\[\]]*)>/i', '[\1\2]', $text);
    $text = preg_replace('/<\/(' . $tags . ')>/Ui', '[/\1]', $text);

    // 过滤多余html
    $pat = '/<\/?(html|head|meta|link|base|basefont|body|bgsound|title|style|script|form|iframe|' .
           'frame|frameset|applet|id|ilayer|layer|name|script|style|xml)[^><]*>/i';
    $text = preg_replace($pat, '', $text);

    // 过滤合法的html标签
    while (preg_match('/<([a-z]+)[^><\[\]]*>[^><]*<\/\1>/i', $text, $mat)) {
        $text = str_replace($mat[0], str_replace('>', ']', str_replace('<', '[', $mat[0])), $text);
    }

    // 转换引号
    while (preg_match('/(\[[^\[\]]*=\s*)(\"|\')([^\2=\[\]]+)\2([^\[\]]*\])/i', $text, $mat)) {
        $text = str_replace($mat[0], $mat[1] . '|' . $mat[3] . '|' . $mat[4], $text);
    }

    // 过滤错误的单个引号
    while (preg_match('/\[[^\[\]]*(\"|\')[^\[\]]*\]/i', $text, $mat)) {
        $text = str_replace($mat[0], str_replace($mat[1], '', $mat[0]), $text);
    }

    // 转换其它所有不合法的 < >
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);
    $text = str_replace('"', '&quot;', $text);

    // 反转换
    $text = str_replace('[', '<', $text);
    $text = str_replace(']', '>', $text);
    $text = str_replace('|', '"', $text);

    // 过滤多余空格
    $text = str_replace('  ', ' ', $text);

    return $text;
}

// stdClass Object / SimpleXMLElement Object [json_decode(json_encode($xml),1)] 转 Array
function obj2arr($array)
{
    is_object($array) && $array = $array ? (array)$array : null;
    is_array($array) && $array = array_map(__FUNCTION__, $array);

    return $array;
}

// xml 转 Array
// e.g. xml2arr('<NewDataSet><Table><game_no>0</game_no><id>1</id></Table></NewDataSet>');
function xml2arr($xml)
{
    is_string($xml) && $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($ret = obj2arr($xml)) {
        // 处理单行数据为多行模式
        foreach ($ret as $k => $v) {
            if ($v && is_array($v)) {
                $tmp = reset($v);
                is_array($tmp) || $ret[$k] = array($v);
            }
        }
    }

    //obj2arr(simplexml_load_string('')) === false; get_arr(false) === array();
    return get_arr($ret);
}

// Array 转 Object
function arr2obj($object)
{
    is_array($object) && $object = (object)array_map(__FUNCTION__, $object);
    return $object;
}

// Array 转 xml
function arr2xml($arr = array(), $xml = null, $header = 0, $save = 1)
{
    is_null($xml) && $xml = simplexml_load_string('<?xml version="1.0" encoding="utf-8"?><ds></ds>');

    foreach ($arr as $dt => $rs) {
        $dt_name = is_number($dt) ? 'dt_' . $dt : $dt;
        foreach ($rs as $k => $v) {
            $node_name = is_number($k) ? 'dt_' . $k : $k;
            if (is_array($v)) {
                $x = $xml->addChild($dt_name);
                arr2xml(array($v), $x, 0);
            } else {
                $xml->addChild($node_name, $v);
            }
            // is_number($k) && $x->addAttribute('id', $k);
        }
    }

    $ret = $xml;
    if ($save) {
        $ret = $xml->saveXML();
        $header || $ret = preg_replace('/<\?xml.*\?>\s*/', '', $ret);
    }

    return $ret;
}

// 模板数组，将数组数据转为统一模板的数据行
function arr2tpl($arr = array(), $tpl = array())
{
    $ret = $arr;
    if ($tpl) {
        foreach ($arr as $k => $v) {
            $ret[$k] = $tpl;
            foreach ($v as $kv => $vv) {
                $ret[$k][$kv] = $vv;
            }
        }
    }

    return $ret;
}

// C# Dictionary 转数组
function dict2arr($dict = array())
{
    $ret = array();

    if ($dict = obj2arr($dict)) {
        isset($dict['KeyValueOfstringstring']) && is_array($dict['KeyValueOfstringstring'])
        && $dict = $dict['KeyValueOfstringstring'];
        foreach ($dict as $d) {
            $ret[$d['Key']] = $d['Value'];
        }
    }

    return $ret;
}

// 数组转 C# Dictionary
function arr2dict($arr = array(), $with = 0, $re_obj = 0)
{
    $ret = array('KeyValueOfstringstring' => array());
    foreach ($arr as $k => $v) {
        $ret['KeyValueOfstringstring'][] = array('Key' => $k, 'Value' => $v);
    }
    $with || $ret = $ret['KeyValueOfstringstring'];

    return $re_obj ? arr2obj($ret) : $ret;
}

// 数组转为下拉菜单键值数组, 键和值都可以为多个, 默认以 - 连接
function arr2select($arr, $key, $value, $pre = ' - ')
{
    $ret = array();

    if ($arr && is_array($arr) && $key && $value) {
        foreach ($arr as $d) {
            $ret_key = '';
            if (is_array($key)) {
                foreach ($key as $k) {
                    $ret_key === '' || $ret_key .= $pre;
                    isset($d[$k]) && $ret_key .= $d[$k];
                }
            } else {
                isset($d[$key]) && $ret_key = $d[$key];
            }
            $ret[$ret_key] = '';
            if (is_array($value)) {
                foreach ($value as $kv) {
                    $ret[$ret_key] === '' || $ret[$ret_key] .= $pre;
                    isset($d[$kv]) && $ret[$ret_key] .= $d[$kv];
                }
            } else {
                $ret[$ret_key] = $d[$value];
            }
        }
    }

    return $ret;
}

// 指定替换次数的 str_replace
function str_replace_limit($search, $replace, $subject, $limit = -1)
{
    if (is_array($search)) {
        foreach ($search as $k => $v) {
            $search[$k] = '/' . preg_quote($search[$k], '/') . '/';
        }
    } else {
        $search = '/' . preg_quote($search, '/') . '/';
    }

    return preg_replace($search, $replace, $subject, $limit);
}

// 指定替换次数的 str_ireplace
function str_ireplace_limit($search, $replace, $subject, $limit = -1)
{
    if (is_array($search)) {
        foreach ($search as $k => $v) {
            $search[$k] = '/' . preg_quote($search[$k], '/') . '/i';
        }
    } else {
        $search = '/' . preg_quote($search, '/') . '/i';
    }

    return preg_replace($search, $replace, $subject, $limit);
}

/**
 * 生成字符串, 可安全用于 url 传递
 * 替换 + / =
 *
 * @param  string $str 待处理的字符串
 * @param  int    $xor 0 调用 mk_str(), 1 调用 get_xor()
 * @return string      返回处理结果
 */
function mk_string($str = null, $xor = 0)
{
    $str = $xor ? get_xor($str) : base64_encode(serialize($str));
    return $xor . str_replace(array('+', '/', '='), array('-', '_', '$'), $str);
}

/**
 * 还原字符串
 *
 * @param  string $str 待处理的字符串
 * @return string      返回处理结果
 */
function un_string($str = null)
{
    $ret = '';
    if ($str) {
        $xor = $str[0];
        $str = str_replace(array('-', '_', '$'), array('+', '/', '='), substr($str, 1));
        $ret = $xor ? un_xor($str) : unserialize(base64_decode($str));
    }

    return $ret;
}

// 导出 csv 时转换为 GBK 编码
function gbk_csv($data)
{
    return is_array($data) ? gbk(array_map('for_gbk_csv', $data)) : gbk(for_gbk_csv($data));
}

// 替换英文逗号为下划线：for gbk_csv
function for_gbk_csv($str)
{
    return str_replace(',', '_', $str);
}

// 导出 csv；只输出某些字段：array('GNAME' => array('游戏名称'))；array() 时第一行数据键值为标题
function csv_export($csv_data = array(), $csv_header = array(), $filename = 'csv_export.csv')
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // 打开 PHP 文件句柄，php://output 直接输出到浏览器
    $fp = fopen('php://output', 'a');

    // 列名信息
    $csv_fields = array();

    // 防止空数据
    $csv_data || $csv_data = array(array('null' => ''));

    if ($csv_header && is_array($csv_header)) {
        $first = reset($csv_header);
        if (is_array($first)) {
            $tmp = array();
            foreach ($csv_header as $k => $v) {
                $csv_fields[] = $k;
                $tmp[] = is_array($v) ? $v[0] : $v;
            }
            $csv_header = $tmp;
        }
    } else {
        $first = reset($csv_data);
        $csv_header = array_keys($first);
    }

    // 输出 Excel 列名信息
    $csv_header && fputcsv($fp, gbk_csv($csv_header));

    // 计数器
    $num = 0;

    // 每隔 $limit 行，刷新一下输出 buffer，不要太大，也不要太小
    $limit = 100000;

    // 逐行取出数据
    $count = count($csv_data);

    for ($i = 0; $i < $count; $i++) {
        $num++;
        // 刷新一下输出 buffer，防止由于数据过多造成问题
        if ($limit == $num) {
            ob_flush();
            flush();
            $num = 0;
        }

        // 如果需要按字段名导出
        if ($csv_fields) {
            $put_csv = array();
            foreach ($csv_fields as $field) {
                $put_csv[] = isset($csv_data[$i][$field]) ? $csv_data[$i][$field] : '';
            }
        } else {
            $put_csv = array_values($csv_data[$i]);
        }

        fputcsv($fp, gbk_csv($put_csv));
    }

    fclose($fp);
    exit();
}

/**
 * 致命错误日志
 * register_shutdown_function('log_fatal_err');
 *
 * @param  array  $err      错误类型及消息
 * @param  string $log_file 错误日志文件路径
 */
function log_fatal_err($err = array(), $log_file = '')
{
    $err || $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
        $log_file || $log_file = PHP . 'fatal_err.txt';
        $log_text = date('Ymd_His') . ', ' . json_encode($err) . ', ' . get_page() . PHP_EOL;
        mk_file($log_file, $log_text, FILE_APPEND);
    }
}

// json_encode($str, JSON_UNESCAPED_UNICODE)
function json_encode_unicode($obj = '')
{
    if (version_compare(PHP_VERSION, '5.4.0', '<')) {
        return decodeUnicode(json_encode($obj));
    // 数字会变字符串，true => 1，null => ''，object => null
        //return urldecode(json_encode(filter($obj, 'urlencode', 1)));
    } else {
        return json_encode($obj, JSON_UNESCAPED_UNICODE);
    }
}

// 转换中文编码
function decodeUnicode($str)
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($matches) {
        get_chars(pack('H*', $matches[1]), 'UCS-2BE', 'UTF-8');
    }, $str);
}

// 输出或返回 JSON
function json($data = array(), $code = 200, $unicode = false, $exit = true)
{
    $json = $unicode ? json_encode_unicode($data) : json_encode($data);

    if ($exit) {
        $code && http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        exit($json);
    }

    return $json;
}

// 遍历目录下的文件：scan_dirs(array('/'), '/', 0, array(), 'http://url.cn/', null, 0, 'strtolower');
// $with_md5 1 生成文件的 md5 列表
// $blank 目录（最后要跟系统匹配的斜杠）、文件黑名单、允许的扩展名和禁止的扩展名；$lmf 文件最后修改时间在该秒数内
// $fun 是对目录和文件字符串处理的函数，需要有返回值；
function scan_dirs(
    $scan_dirs,
    $dir_pre = DS,
    $lmf = 0,
    $blank = array(),
    $with_root = null,
    $replace_root = null,
    $with_md5 = 0,
    $fun = ''
) {
    // 支持多个目录，转为数组
    is_array($scan_dirs) && isset($scan_dirs[0]) || $scan_dirs = array($scan_dirs);

    $ret = array('files' => array(), 'dirs' => array(), 'md5' => array());
    $now = time();

    foreach ($scan_dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        // 兼容各操作系统
        $dir = mk_rtrim(preg_replace('/\/+|\\\+/', DS, $dir), DS, DS);

        // 修正返回值中目录的斜杠
        $ok_root_dir = str_replace(DS, $dir_pre, $dir);

        $dirs = array($dir);
        $do_fun = $fun && function_exists($fun);

        // 判断最后修改时间的参数必须是数字秒数或数组中两个时间戳
        $lmf && (is_numeric($lmf) || (is_array($lmf) && is_numeric($lmf[0]) && is_numeric($lmf[1]))) || $lmf = 0;

        do {
            // 弹栈
            $the_dir = array_pop($dirs);

            // 扫描该目录
            $files = array_diff(scandir($the_dir), array('..', '.'));

            // 返回结果集是否替换根目录
            $ok_dir = is_null($replace_root) ? $the_dir : str_replace($replace_root, '', $the_dir);
            $ok_dir = str_replace(DS, $dir_pre, $ok_dir);
            $ok_dir = is_null($with_root) ? $ok_dir : $with_root . str_replace($ok_root_dir, '', $ok_dir);

            // 返回结果是否包含目录
            $ret['dirs'][] = $do_fun ? call_user_func($fun, $ok_dir) : $ok_dir;

            foreach ($files as $f) {
                // 组合当前绝对路径
                $path = $the_dir . $f;
                if (is_dir($path)) {
                    if ($blank['dirs'] && (in_array($f, $blank['dirs']) || in_array($path . DS, $blank['dirs']))) {
                        // 排除黑名单目录（目录名或完整路径）
                        continue;
                    }

                    if ($lmf
                        && ($filemtime = filemtime($path))
                        && ((is_numeric($lmf) && ($filemtime + $lmf) < $now)
                            || (is_array($lmf) && ($lmf[0] > $filemtime || $filemtime > $lmf[1])))
                    ) {
                        // 排除目录修改时间条件，0 不检查，正数+目录修改时间 < time()，数组：time1 > 目录修改时间 || 目录修改时间 > time2
                        continue;
                    }

                    // 如果是目录，压栈，
                    array_push($dirs, $path . DS);
                } elseif (is_file($path)) {
                    // 文件扩展名检查
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if ($blank['ok_ext'] && !in_array($ext, $blank['ok_ext'])) {
                        // 不在扩展名白名单
                        continue;
                    } elseif ($blank['no_ext'] && in_array($ext, $blank['no_ext'])) {
                        // 排除扩展名黑名单
                        continue;
                    }

                    if ($blank['files'] && (in_array($f, $blank['files']) || in_array($path, $blank['files']))) {
                        // 排除黑名单文件（文件名或完整路径）
                        continue;
                    }

                    if ($lmf
                        && ($filemtime = filemtime($path))
                        && ((is_numeric($lmf) && ($filemtime + $lmf) < $now)
                            || (is_array($lmf) && ($lmf[0] > $filemtime || $filemtime > $lmf[1])))
                    ) {
                        // 排除文件修改时间条件，0 不检查，正数+文件修改时间 < time()，数组：time1 > 文件修改时间 || 文件修改时间 > time2
                        continue;
                    }

                    // 文件存入结果集
                    $ok_file = $do_fun ? call_user_func($fun, $ok_dir . $f) : $ok_dir . $f;
                    $ret['files'][] = $ok_file;

                    // 文件 md5
                    $with_md5 && $ret['md5'][$ok_file] = md5_file($path);
                }
            }
        } while ($dirs);
    }

    return $ret;
}

// 增强 rtrim 去除多字符（默认区分大小写）：
// mk_rtrim('/a///', '/', '/'); // 结果：/a/
// mk_rtrim('123_htm_htmmlll_html_html', '_html', '.txt'); // 结果：123_htm_htmmlll.txt
function mk_rtrim($str, $remove = null, $add = '', $case_i = 0)
{
    $str = (string)$str;
    $remove = (string)$remove;

    if (empty($remove)) {
        return rtrim($str) . $add;
    }

    $fun = $case_i ? 'stripos' : 'strpos';
    $len = strlen($remove);
    $offset = strlen($str) - $len;
    while ($offset > 0 && $offset == $fun($str, $remove, $offset)) {
        $str = substr($str, 0, $offset);
        $offset = strlen($str) - $len;
    }

    return rtrim($str) . $add;
}

// 增强 ltrim 去除多字符（默认区分大小写）：
// mk_ltrim('/a/a///aa///b', '/A', '_ok:', 1); // 结果：_ok:///aa///b
function mk_ltrim($str, $remove = null, $pre_add = '', $case_i = 0)
{
    $str = (string)$str;
    $remove = (string)$remove;

    if (empty($remove)) {
        return $pre_add . ltrim($str);
    }

    $fun = $case_i ? 'stripos' : 'strpos';
    $len = strlen($remove);
    while ($fun($str, $remove) === 0) {
        $str = substr($str, $len);
    }

    return $pre_add . ltrim($str);
}

// 替换 XML 中的特殊字符，$str, array($str1, $str2)，$re_array = 1 将值放到数组中，用于导出 Excel 时的表头
function cls_xml_chars($data = '', $re_array = 0)
{
    if (empty($data)) {
        return '';
    }

    $xml_chars = array(
        '_x002B_',
        '_x002C_',
        '_x003C_',
        '_x003E_',
        '_x0022_',
        '_x002A_',
        '_x0025_',
        '_x0026_',
        '_x0028_',
        '_x0029_',
        '_x003D_'
    );
    $xml_str = array('+', ',', '<', '>', '"', '*', '%', '&', '(', ')', '=');

    if (is_array($data)) {
        $ret = array();
        foreach ($data as $k => $v) {
            // 数组时返回值对应数组，一维值 变为 二维键值，多维数组时自行重组结果吧
            $ret[is_array($v) ? $k : $v] = cls_xml_chars($v, $re_array);
        }

        return $ret;
    } else {
        $data = str_replace($xml_chars, $xml_str, $data);
        // XML 中的数字
        $data = preg_replace('/_x003(\d+)_/', '$1', $data);

        return $re_array ? array($data) : $data;
    }
}

// 数组模糊查找
function find_array($array = array(), $txt = '', $is_dict = false)
{
    $ret = array();

    foreach ($array as $k => $v) {
        stripos($is_dict ? $v['Value'] : $v, $txt) === false || $ret[$k] = $v;
    }

    return $ret;
}

// PHP ping 域名或IP，0 不通，> 0 ttl(ms)
function ping($host, $timeout = 1, $count = 1)
{
    $use_exec = false;
    if (function_exists('exec')) {
        $line = @exec('echo FF', $res, $code);
        $use_exec = $line && $code === 0;
    }

    if ($use_exec) {
        $if_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $ping_cmd = 'ping' . ($if_win ? ' -n ' . $count . ' -w 1000 ' : ' -c' . $count . ' -W1 ') . $host;
        $line = @exec($ping_cmd, $res, $code);
        if ($line && $code === 0) {
            preg_match('#/([\d\.]+)/#', $line, $matchs);
            empty($matchs[1]) && preg_match('#=\s*([\d\.]*)\s*ms\s*$#', $line, $matchs);
            $ret = empty($matchs[1]) ? 0 : $matchs[1];
        } else {
            $ret = 0;
        }

        return ceil($ret);
    }

    /* ICMP ping packet with a pre-calculated checksum */
    $package = "\x08\x00\x19\x2f\x00\x00\x00\x00\x70\x69\x6e\x67";
    $socket = @socket_create(AF_INET, SOCK_RAW, 1);

    if ($socket === false) {
        // 10013 以一种访问权限不允许的方式做了一个访问套接字的尝试
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        debug_file($errorcode . ': ' . $errormsg);

        return 0;
    }

    $ret = -1;
    if ($socket
        && (socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0)))
        && (socket_connect($socket, $host, null))
    ) {
        $ts = microtime(true);
        socket_send($socket, $package, strlen($package), 0) && @socket_read($socket, 255) &&
        $ret = microtime(true) - $ts;
        socket_close($socket);
    }

    if ($ret >= 0) {
        $ret = ceil($ret * 1000);
        $ret || $ret = 1;
    } else {
        $ret = 0;
    }

    return $ret;
}

// 加权随机数：$data = array('A' => 2, 'B' => 100);
function random_weight($data = array())
{
    $ret = '';

    if ($data && is_array($data)) {
        $rand = mt_rand(1, array_sum($data));
        $num = 0;
        foreach ($data as $k => $v) {
            $num += $v;
            if ($rand <= $num) {
                $ret = $k;
                break;
            }
        }
    }

    return $ret;
}

/**
 * 发起请求: GET/POST/JSON == mkRequest, 取代 post_request() get_request()
 *
 * JSON 请求认证 API 示例:
 * $add_header = array('Authorization: test', 'Accept: application/json');
 * mkRequest('url.api', 'json', array('d' => 1), 10, array('add_header' => $add_header));
 *
 * @param string $url     请求网址
 * @param string $method  请求方法
 * @param array  $data    请求数据集
 * @param int    $timeout 超时时间
 * @param array  $option  其他选项：referer, agent, (array)add_header 附加请求头
 * @param array  $header  指定要使用的请求头, 高优先级
 * @param bool   $debug   请求调试
 * @return int            返回码
 */
function mk_request(
    $url = '',
    $method = 'get',
    $data = array(),
    $timeout = 30,
    $option = array('agent' => ''),
    $header = array(),
    $debug = false
) {
    if (empty($url)) {
        return '';
    }

    $method = strtolower($method);
    $data = is_string($data) ? $data : ('json' == $method ? json_encode($data) : @http_build_query($data));
    $data && 'get' == $method && $url .= (strpos($url, '?') ? '&' : '?') . $data;
    !empty($header) && is_array($header) || $header = array();

    if (!$header) {
        // POST, JSON 请求头, Akamai 使用 application/json; charset=utf-8 有问题
        if ('post' == $method || 'json' == $method) {
            $header[] = 'post' == $method
                ? 'Content-Type: application/x-www-form-urlencoded'
                : 'Content-Type: application/json';
            $header[] = 'Content-Length: ' . strlen($data);
        }

        // 来访页
        isset($option['referer']) && $header[] = 'Referer: ' . $option['referer'];

        // 浏览器代理（默认使用 Firefox）
        if (isset($option['agent'])) {
            $agent = $option['agent']
                ? $option['agent']
                : 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:98.0) Gecko/20100101 Firefox/98.0';
            $header[] = 'User-Agent: ' . $agent;
        }
    }

    // 附加请求头：JSON：$option['add_header'] = array('Authorization: test', 'Accept: application/json');
    isset($option['add_header']) && is_array($option['add_header'])
    && $header = array_merge($option['add_header'], $header);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ('post' == $method || 'json' == $method) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            // 抓取跳转后的页面
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        }

        if (is_numeric($timeout)) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        if ('https' == strtolower(substr($url, 0, 5))) {
            // 跳过证书检查 peer's certificate
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            // 0 不检查证书，1 检查证书中是否有CN(common name)字段，2 校验当前的域名是否与CN匹配
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $header && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $debug && debug_file(curl_getinfo($ch), 'curl_info');
        $ret = curl_exec($ch);
        curl_close($ch);
    } else {
        $header[] = 'Connection: Close';
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'protocol_version' => '1.1'
            )
        );

        if ('post' == $method || 'json' == $method) {
            $opts['http']['method'] = 'POST';
            $opts['http']['content'] = $data;
        }

        is_numeric($timeout) && $opts['http']['timeout'] = $timeout;
        $opts['http']['header'] = $header;
        $ret = @file_get_contents($url, false, stream_context_create($opts));
    }

    return $ret;
}

/**
 * array_aplice 保留键名
 * http://php.net/manual/zh/function.array-splice.php#111204
 *
 * e.g.::
 *
 *     $fruit = array(
 *         'orange' => 'orange',
 *         'lemon' => 'yellow',
 *         'lime' => 'green',
 *         'grape' => 'purple',
 *         'cherry' => 'red',
 *     );
 *     // Replace lemon and lime with apple
 *     array_splice_assoc($fruit, 'lemon', 'grape', array('ff' => 'red'));
 *     // Replace cherry with strawberry
 *     array_splice_assoc($fruit, 'cherry', 1, array('dd' => 'red'));
 *
 * @param array $input       结果数组
 * @param mixed $offset      偏移值 / 键名
 * @param mixed $length      长度
 * @param array $replacement 替换数组 / 插入数组
 * @param bool  $after       true 表示在 $offset 之后插入
 */
function array_splice_assoc(&$input, $offset, $length, $replacement = array(), $after = false)
{
    $replacement = (array)$replacement;
    $key_indices = array_flip(array_keys($input));
    $offset = isset($input[$offset]) && is_string($offset) ? $key_indices[$offset] : get_intval($offset);
    $length = (isset($input[$length]) && is_string($length) ? $key_indices[$length] : get_intval($length)) - $offset;
    $after && $offset++;
    $input = array_slice($input, 0, $offset, true) + $replacement + array_slice($input, $offset + $length, null, true);
}

/**
 * 得到百分比字段
 * http://php.net/manual/zh/function.array-splice.php#111204
 *
 * e.g.::
 *
 *     $data = array(
 *         'a' => 12,
 *         'b' => 33,
 *         'total' => 88
 *     );
 *     $pct_arr = mk_pct($data, 'total', array('a', 'b'), 2);
 *     //Array([a_pct] => 13.64, [b_pct] => 37.5)
 *     array_splice_assoc($data, 'b', 0, $pct_arr, true);
 *     //array_splice_assoc($data, 'total', 0, $pct_arr);
 *     //Array([a] => 12, [b] => 33, [a_pct] => 13.64, [b_pct] => 37.5, [total] => 88)
 *
 * @param  array  $data   数据源
 * @param  string $base   除数字段名
 * @param  array  $fields 要生成百分比的字段列表
 * @param  int    $pre    小数位数
 * @return array          返回百分比数组
 */
function mk_pct($data = array(), $base = '', $fields = array(), $pre = 2)
{
    if (empty($fields)) {
        return array();
    }

    $ret = array();
    $base = empty($data[$base]) ? 0 : get_intval($data[$base]);
    foreach ((array)$fields as $field) {
        if (empty($base) || empty($data[$field])) {
            // 除数为 0 或不存在
            $ret[$field . '_pct'] = 0;
        } else {
            $ret[$field . '_pct'] = round(get_intval($data[$field]) / $base * 100, $pre);
        }
    }

    return $ret;
}

// 检查是否是域名
function chk_domain($domain = '')
{
    $domain = fr($domain);
    $pat = '/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/';
    return $domain && preg_match($pat, $domain) ? $domain : '';
}

// 检查以 xx 开头
function startswith($haystack = '', $needle = '')
{
    return $haystack && $needle ? strncmp($haystack, $needle, strlen($needle)) === 0 : false;
}

// 检查以 xx 结尾
function endswith($haystack = '', $needle = '')
{
    return $haystack && $needle ? substr_compare($haystack, $needle, -strlen($needle)) === 0 : false;
}

// 检查是否允许访问, 有 SSL 账号信息时返回
function allow_or_die()
{
    $can_access = false;
    $cname = '';

    if (is_https()) {
        // 证书里的用户账号信息, 无证书账号信息禁止访问
        $cname = isset($_SERVER['SSL_CLIENT_S_DN_CN']) ? fr($_SERVER['SSL_CLIENT_S_DN_CN']) : '';
        $cname && $can_access = true;
    } elseif (!constant('ONLY_HTTPS') && $_SERVER["SERVER_PORT"] == constant('HTTP_PORT')) {
        if (!empty($_SESSION['dd_user_id'])) {
            // 允许钉钉使用 http:port 访问
            $can_access = true;
        } elseif ($allow_ip = constant('ALLOW_IP')) {
            // 允许公司 .1 网关等指定 IP 访问
            $allow_ip = explode(',', $allow_ip);
            (in_array($user_ip = get_ip(), $allow_ip) || in_array(get_ip_x($user_ip), $allow_ip))
            && $can_access = true;
        }
    }

    $can_access || FF::Msg('禁止访问', 403);

    return $cname;
}
