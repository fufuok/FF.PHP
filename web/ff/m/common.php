<?php
/**
 * 框架运行环境预处理及框架函数定义
 *
 * @author Fufu, 2013-07-18
 * @update 2016-08-08 时区设置用标准地区字符串，mk_date 增加 +-12 时区偏移
 * @update 2016-08-18 增加 get_md5 和 get_xor 算法强度
 * @update 2016-10-18 增加 get_where
 * @update 2016-12-12 增加 safe_url 和 http_response_code
 * @update 2017-02-14 SESSION 驱动为文件时，启动、回收、生命周期 的实现方式调整
 * @update 2017-03-15 规范自定义函数的注释，增强 Cookie 操作函数
 * @update 2017-09-11 filter 增加 filter_key 参数
 * @update 2017-10-10 I() 默认返回原始值，不再默认执行 htmlspecialchars 过滤
 * @update 2018-06-06 优化未开启 SESSION 时 I() 的取值
 * @update 2018-07-13 优化一些可能出现 Notice 的地方
 * @update 2019-03-13 仅同步时间
 */

defined('FF') or die('404');

// 错误显示级别
$ff_cfg = I('f.DebugPHP');
$ff_cfg = $ff_cfg ? ($ff_cfg == 2 ? E_ALL : E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE) : 0;
error_reporting($ff_cfg);

// 设置时区
($ff_cfg = I('f.TimeZones')) && date_default_timezone_set($ff_cfg);

// 网页编码，覆盖方法：header('content-type: text/html; charset=ISO-8859-1'); 注意字符串中用小写 charset
($ff_cfg = I('f.CharSet')) && ini_set('default_charset', $ff_cfg);

// 检查并建立数据文件夹
mk_dirs(PHP);
mk_dirs(CACHE);
mk_dirs(ATTACH);

// 自定义 SESSION 文件夹、SESSION 初始化、SESSION 回收
$ff_sess_handler = I('f.Session.handler');
$ff_sess_save_path = I('f.Session.save_path');
$ff_sess_expire = I('f.Session.expire', 0, 'get_id');
$ff_sess_path = I('f.Session.path');
$ff_sess_domain = I('f.Session.domain');
$ff_sess_secure = I('f.Session.secure');
$ff_sess_httponly = I('f.Session.httponly');

if ($ff_sess_handler && $ff_sess_save_path) {
    // SESSION 初始化
    ini_set('session.save_handler', $ff_sess_handler);
    ini_set('session.save_path', $ff_sess_save_path);
    // 生命周期为 0 时，默认为 365 天
    ini_set('session.gc_maxlifetime', $ff_sess_expire ? $ff_sess_expire : 31536000);
    session_set_cookie_params($ff_sess_expire, $ff_sess_path, $ff_sess_domain, $ff_sess_secure, $ff_sess_httponly);
}

// 启动 Session
if (I('f.Session.autostart')) {
    if ($ff_sess_handler == 'files'
        && ($ff_sess_pos = strpos($ff_sess_save_path, ';'))
        && ($ff_sess_root = rtrim(trim(substr($ff_sess_save_path, $ff_sess_pos + 1), DS)))
    ) {
        // SESSION 分级目录，目录深度大于 2 时请自行建目录，php.ini: session.save_path = "2;/path"
        // session_save_path('2;' . rtrim(SESSION, DS));
        if (!is_dir($ff_sess_root . DS . 'z' . DS . 'z' . DS)) {
            $str = '0123456789abcdefghijklmnopqrstuvwxyz';
            for ($i = 0; $i < 36; $i++) {
                for ($j = 0; $j < 36; $j++) {
                    mk_dirs($ff_sess_root . DS . $str{$i} . DS . $str{$j});
                }
            }
        }
        // 自定义 SESSION 回收机制，1/100 的机率触发清理
        if (mt_rand(0, 99) == 0) {
            // 取两位随机字符组合目录
            $dir = strtolower(random(2));
            $dir = $ff_sess_root . DS . $dir{0} . DS . $dir{1} . DS;
            // 遍历该目录，处理其中的 SESSION 文件
            if (is_dir($dir) && ($dirs = opendir($dir))) {
                while (false !== ($filename = readdir($dirs))) {
                    if ($filename{0} == '.') {
                        continue;
                    }
                    // 删除已过期的 SESSION 文件
                    $mtime = filemtime($dir . $filename);
                    $mtime && ($mtime + $ff_sess_expire) < time() && @unlink($dir . $filename);
                }
                closedir($dirs);
            }
        }
    }

    session_start();

    // 生命周期可以为 0 (浏览器关闭时失效)
    set_cookie(
        session_name(),
        session_id(),
        $ff_sess_expire,
        $ff_sess_path,
        $ff_sess_domain,
        $ff_sess_secure,
        $ff_sess_httponly
    );

    // SESSION 访问时间戳
    $_SESSION['__TIME__'] = time();
}

/** 以下几个函数为框架服务，可全站使用，需要保留 */

/**
 * COOKIE 设置函数，立即生效
 * 自动化的加密和前缀意义不大，特别是配合前端时
 * 如果非要使用前缀区分各场景下的 Cookie，那么使用数组吧（在 Cookie 中表现为二维数组）
 *
 * 普通用法：
 * set_cookie('name', 'value');                         // $_COOKIE['name'] == 'value';
 * set_cookie(array('key' => 'val'));                   // $_COOKIE['key'] == 'val';
 * set_cookie('name', 'value', 3600);                   // 1 小时生命周期
 * 设置一个数组值，不支持空下标 array('' => 'val')：
 * set_cookie('pre', array('a' => 'aa', 123, 'b'));     // $_COOKIE['pre']['a'] == 'aa';
 * 修改某个数组项：
 * set_cookie('pre', array('a' => 'bb'));               // $_COOKIE['pre'] == array('a' => 'bb', 0 => 123, 1 => 'b');
 * 获取数组项：
 * set_cookie('name');                                  // 'value';
 * set_cookie('pre', 'a');                              // 'bb';
 * set_cookie('pre.0');                                 // 123;
 * set_cookie('pre', 1);                                // 'b';
 * 删除某个数组项：
 * set_cookie('pre', array('a' => null));               // $_COOKIE['pre'] == array(0 => 123, 1 => 'b');
 * del_cookie('pre', 'a');
 * del_cookie('pre', 0);                                // $_COOKIE['pre'] == array(1 => 'b');
 * 删除某个数组所有项：
 * set_cookie(array('pre' => null));
 * set_cookie('pre', null);                             // isset($_COOKIE['pre']) === false
 * del_cookie('pre');
 * del_cookie('key');
 * 删除整个 COOKIE
 * set_cookie(null);                                    // $_COOKIE = array();
 * del_cookie(null);
 * 判断是否有值
 * get_cookie('has') === null
 *
 * @param  mixed  $name     Cookie 名称
 * @param  mixed  $value    值
 * @param  int    $expire   生命周期（秒），默认 0
 * @param  string $path     作用路径，默认 /
 * @param  string $domain   作用域名
 * @param  bool   $secure   设置为 true 表示 cookie 仅在使用 安全 链接时可用
 * @param  bool   $httponly 设置为 true 表示 PHP 发送 cookie 的时候会使用 httponly 标记
 * @return bool             默认返回 true
 */
function set_cookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
{
    $expire = $expire && is_numeric($expire) ? time() + $expire : 0;
    $null = time() - 86500;

    if ('' === $name) {
        return false;
    } elseif (null === $name) {
        // 名称为 null 时，删除所有 COOKIE
        foreach ($_COOKIE as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    setcookie($key . '[' . $k . ']', '', $null, $path, $domain, $secure, $httponly);
                }
            } else {
                setcookie($key, '', $null, $path, $domain, $secure, $httponly);
            }
        }
        $_COOKIE = array();
    } elseif (is_array($name)) {
        // 数组形式的 COOKIE 值
        foreach ($name as $k => $v) {
            if (null === $v) {
                // 某个数组项值为 null 时，删除该项
                setcookie($k, '', $null, $path, $domain, $secure, $httponly);
                unset($_COOKIE[$k]);
            } else {
                if (is_array($v)) {
                    // 数组形式的 COOKIE 值，$k 为组名
                    foreach ($v as $kk => $vv) {
                        if (null === $vv) {
                            // 某个数组项值为 null 时，删除该项
                            setcookie($k . '[' . $kk . ']', '', $null, $path, $domain, $secure, $httponly);
                            unset($_COOKIE[$k][$kk]);
                        } else {
                            // 设置 COOKIE
                            setcookie($k . '[' . $kk . ']', $vv, $expire, $path, $domain, $secure, $httponly);
                            $_COOKIE[$k][$kk] = $vv;
                        }
                    }
                } else {
                    // 设置 COOKIE
                    setcookie($k, $v, $expire, $path, $domain, $secure, $httponly);
                    $_COOKIE[$k] = $v;
                }
            }
        }
    } else {
        // 值为 null 时，删除该项
        if (null === $value) {
            // 如果该项为数组，逐项删除
            if (!empty($_COOKIE[$name]) && is_array($_COOKIE[$name])) {
                foreach ($_COOKIE[$name] as $k => $v) {
                    setcookie($name . '[' . $k . ']', '', $null, $path, $domain, $secure, $httponly);
                }
            } else {
                setcookie($name, '', $null, $path, $domain, $secure, $httponly);
            }
            unset($_COOKIE[$name]);
        } elseif (is_array($value)) {
            // 数组形式的 COOKIE 值，有指定组名
            foreach ($value as $k => $v) {
                if (null === $v) {
                    // 某个数组项值为 null 时，删除该项
                    setcookie($name . '[' . $k . ']', '', $null, $path, $domain, $secure, $httponly);
                    unset($_COOKIE[$name][$k]);
                } else {
                    // 设置 COOKIE
                    setcookie($name . '[' . $k . ']', $v, $expire, $path, $domain, $secure, $httponly);
                    $_COOKIE[$name][$k] = $v;
                }
            }
        } else {
            // 设置 COOKIE
            setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
            $_COOKIE[$name] = $value;
        }
    }

    return true;
}

/**
 * 获取 COOKIE
 *
 * @param  string $name Cookie 名称
 * @param  mixed  $item 数组下标：'a' 0
 * @return mixed        返回值
 */
function get_cookie($name, $item = '')
{
    return I('c.' . $name . ($item === '' || $item === null ? '' : '.' . $item), null);
}

/**
 * 删除 COOKIE
 *
 * @param  mixed  $name     Cookie 名称，名称 === null 时清除所有 Cookie
 * @param  mixed  $item     数组下标：'a' 0
 * @param  string $path     作用路径，默认 /
 * @param  string $domain   作用域名
 * @param  bool   $secure   设置为 true 表示 cookie 仅在使用 安全 链接时可用
 * @param  bool   $httponly 设置为 true 表示 PHP 发送 cookie 的时候会使用 httponly 标记
 * @return void             无返回值
 */
function del_cookie($name, $item = '', $path = '/', $domain = '', $secure = false, $httponly = false)
{
    $value = $item === '' || $item === null ? null : array($item => null);
    set_cookie($name, $value, -86500, $path, $domain, $secure, $httponly);
}

/**
 * 设置 SESSION，与 set_cookie 用法相同，只是 Cookie 最大只支持二维数组，Session 无限制
 *
 * 普通用法：
 * set_session('name', 'value');                        // $_SESSION['name'] == 'value';
 * set_session(array('key' => 'val'));                  // $_SESSION['key'] == 'val';
 * 设置一个数组值，不支持空下标 array('' => 'val')：
 * set_session('pre', array('a' => 'aa', 123, 'b'));    // $_SESSION['pre']['a'] == 'aa';
 * 修改某个数组项：
 * set_session('pre', array('a' => 'bb'));              // $_SESSION['pre'] == array('a' => 'bb', 0 => 123, 1 => 'b');
 * 获取数组项：
 * get_session('name');                                 // 'value';
 * get_session('pre', 'a');                             // 'bb';
 * get_session('pre.0');                                // 123;
 * get_session('pre', 1);                               // 'b';
 * 删除某个数组项：
 * set_session('pre', array('a' => null));              // $_SESSION['pre'] == array(0 => 123, 1 => 'b');
 * del_session('pre', 'a');
 * del_session('pre', 0);                               // $_SESSION['pre'] == array(1 => 'b');
 * 删除某个数组所有项：
 * set_session(array('pre' => null));
 * set_session('pre', null);                            // isset($_SESSION['pre']) === false
 * del_session('pre');
 * del_session('key');
 * 删除整个 COOKIE
 * set_session(null);                                   // $_SESSION = array('__TIME__' => time());
 * del_session(null);
 * 判断是否有值
 * get_session('has') === null
 *
 * @param  mixed $name  下标名称，$name === null 清除 SESSION，is_array($name) 时将按 $name 下标写入数据
 * @param  mixed $value 要写入 SESSION 的数据，$value === null 时删除该项
 * @return bool         默认返回 true
 */
function set_session($name, $value = '')
{
    if ('' === $name) {
        return false;
    } elseif (null === $name) {
        $_SESSION = array('__TIME__' => time());
    } elseif (is_array($name)) {
        // 数组写入 SESSION
        foreach ($name as $k => $v) {
            if (null === $v) {
                // 某个数组项值为 null 时，删除该项
                unset($_SESSION[$k]);
            } else {
                // 设置 SESSION
                $_SESSION[$k] = $v;
            }
        }
    } else {
        // 值为 null 时，删除该项
        if (null === $value) {
            unset($_SESSION[$name]);
        } elseif (is_array($value)) {
            // 数组写入 SESSION，有指定组名
            foreach ($value as $k => $v) {
                if (null === $v) {
                    // 某个数组项值为 null 时，删除该项
                    unset($_SESSION[$name][$k]);
                } else {
                    // 设置 SESSION
                    $_SESSION[$name][$k] = $v;
                }
            }
        } else {
            // 普通键值写入 SESSION
            $_SESSION[$name] = $value;
        }
    }

    return true;
}

/**
 * 获取 SESSION
 *
 * @param  string $name SESSION 名称
 * @param  mixed  $item 数组下标：'a' 0
 * @return mixed        返回值
 */
function get_session($name, $item = '')
{
    return I('s.' . $name . ($item === '' || $item === null ? '' : '.' . $item), null);
}

/**
 * 删除 SESSION
 *
 * @param  mixed $name SESSION 名称，名称 === null 时清除所有 SESSION
 * @param  mixed $item 数组下标：'a' 0
 * @return void        无返回值
 */
function del_session($name, $item = '')
{
    $value = $item === '' || $item === null ? null : array($item => null);
    set_session($name, $value);
}

/**
 * 取得整数，常用于获取 ID
 *
 * @param  mixed $n 字符串或数字，0123 = 123，首尾空白不影响
 * @return int      返回 0 或转换后的整数
 */
function get_id($n)
{
    return isset($n) ? (is_number($n = trim($n)) ? intval(strval($n)) : 0) : 0;
}

/**
 * 返回数字字符串
 *
 * @param  mixed $n 字符串或数字，0123 = 0123，首尾空白不影响
 * @return string   返回 0 或数字字符串
 */
function get_number($n)
{
    return isset($n) ? (is_number($n = trim($n)) ? $n : '0') : '0';
}

/**
 * 判断是否为纯数字 (区别于：is_numeric, ctype_digit)
 *
 * @param  mixed $n 字符串或数字
 * @return bool     返回 true / false
 */
function is_number($n)
{
    return isset($n) && preg_match('/^\d+$/', $n);
}

/**
 * 格式化日期
 * 以 GMT 标准加时区得到准确兼容的日期
 * 正常情况下 == date($format, time());
 *
 * @param  string $format    日期转换格式：Y-m-d H:i:s, y-n-j G:i:s
 * @param  mixed  $timestamp 时间戳：1488297600
 * @param  mixed  $offset    时区偏移（+-12），默认为系统时区（+8）
 * @return string            返回日期字符串
 */
function mk_date($format = '', $timestamp = '', $offset = null)
{
    $format || $format = I('f.DateFormat', 'Y-m-d H:i:s');
    $timestamp = ($timestamp ? $timestamp : time()) + (is_null($offset) ? date('Z') : $offset * 3600);

    return gmdate($format, $timestamp);
}

/**
 * 格式化 Debug 信息
 *
 * @param  string $key 要显示的键名
 * @param  mixed  $val 要打印的键值
 * @return string      返回可用于直接显示的 html 注释代码
 */
function mk_debug($key = '', $val = null)
{
    return "\n<!--\n\n" . $key . " => " . str_replace('-->', '//--!!!', print_r($val, 1)) . "\n\n-->";
}

/**
 * 建文件夹，支持多层路径
 *
 * @param  string $dir  目录名或路径：D:\test\fufu\
 * @param  int    $mode 同 chmod()，mode 在 Windows 下被忽略
 * @return bool         返回 true / false
 */
function mk_dirs($dir = '', $mode = 0777)
{
    if ($dir && !is_dir($dir)) {
        mk_dirs(dirname($dir), $mode);
        return @mkdir($dir, $mode);
    }

    return true;
}

/**
 * htmlspecialchars 数组版，Html 字符转实体
 * &  &amp;
 * "  &quot;
 * '  &#039;
 * <  &lt;
 * >  &gt;
 *
 * @param  mixed $data  待处理的数据
 * @param  mixed $flags 处理方式：0 不编码引号；1 编码单引号；2 编码双绰号；3 编码单双引号
 * @return mixed        返回结果
 */
function mk_html(&$data, $flags = 3)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = mk_html($val);
        }
    } else {
        $data = htmlspecialchars($data, $flags);
    }

    return $data;
}

/**
 * 去除 htmlspecialchars 对应的几种符号：& " ' < >
 *
 * @param  mixed $data 待处理的数据
 * @return mixed       返回结果
 */
function rm_html(&$data)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = rm_html($val);
        }
    } else {
        $data = str_replace(array('&', '"', "'", '<', '>'), '', $data);
    }

    return $data;
}

/**
 * htmlspecialchars_decode 数组版，mk_html 反转函数
 *
 * @param  mixed $data  待处理的数据
 * @param  mixed $flags 处理方式：0 不编码引号；1 编码单引号；2 编码双绰号；3 编码单双引号
 * @return mixed        返回结果
 */
function un_html(&$data, $flags = 3)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = un_html($val);
        }
    } else {
        $data = htmlspecialchars_decode($data, $flags);
    }

    return $data;
}

/**
 * md5 加强版
 * 加密字符串，可自由设定一些算法，不可逆，防止 cmd5
 *
 * @param  string $str 待加密的字符串
 * @param  int    $len 返回字符串的长度
 * @return string      返回加密结果
 */
function get_md5($str = '', $len = 32)
{
    $ret = '';
    $str = '{F^%?' . $str . '0>F}';
    $len > 0 && $len = 32 - $len;
    $j = strlen($str);
    $last = ord(substr($str, -1));
    for ($i = 0; $i < $j;) {
        $tmp = substr($str, $i, ++$i);
        $tmp = md5(chr(($last * $i + ord($tmp)) % 95 + 32));
        $i % 3 && $tmp = strrev($tmp);
        $tmp = $i % 4 ? $tmp : strtoupper($tmp);
        $ret .= $tmp = ($i % 2) ? substr($tmp, ($i < 2 ? 0 : 2), 3) : substr($tmp, -4);
    }

    return substr(md5($j % 2 ? $ret : strrev($ret)), $len);
}

/**
 * 异或加密解密，可逆的加密
 *
 * @param  string $str     待处理的字符串
 * @param  string $key     私钥，默认为：$config['PwdPre']
 * @param  int    $type    0 加密，1 解密
 * @param  string $add_key 附加的私钥
 * @return mixed           返回处理结果
 */
function get_xor($str = '', $key = '', $type = 0, $add_key = '')
{
    $ret = '';

    // 密钥字符串
    $key || ($key = I('f.PwdPre')) || $key = 'ff.PHP';
    // 附加密钥，如：文件缓存时的缓存时间等
    $add_key || $add_key = $key . '+Fufu';
    // 加上固定的字符，防止简单 key
    $key = '^fF.' . $key . '*<1/]';
    // 字符串预处理
    $str = $type ? base64_decode($str) : serialize($str);
    // 密钥生成为 64 位带大小写字符串
    $key = strtolower(get_md5($key)) . strtoupper(get_md5($add_key));

    // 逐个字符异或处理
    $str_len = strlen($str);
    for ($i = 0; $i < $str_len; $i++) {
        $j = $i * 7 % 64;
        $ret .= $str{$i} ^ $key{$j};
    }

    return $type ? @unserialize($ret) : base64_encode($ret);
}

/**
 * 默认解密方法
 *
 * @param  string $str     待解密的字符串
 * @param  string $key     私钥，默认为：$config['PwdPre']
 * @param  string $add_key 附加的私钥
 * @return mixed           返回处理结果
 */
function un_xor($str = '', $key = '', $add_key = '')
{
    return get_xor($str, $key, 1, $add_key);
}

/**
 * 获取客户端 IP (TCP)
 *
 * @param  int $tolong 是否转为数字
 * @param  int $md5    0 返回获取的 IP；1 md5 加密；2 get_md5 加密
 * @return mixed       返回相应的 IP 数据形式
 */
function get_ip($tolong = 0, $md5 = 0)
{
    ($ip = I('ss.REMOTE_ADDR')) || ($ip = getenv('REMOTE_ADDR')) || ($ip = '0.0.0.0');
    $ip == '::1' && $ip = '127.0.0.1';
    $tolong && $ip = get_ip2long($ip);
    $md5 == 1 && $ip = md5($ip);
    $md5 == 2 && $ip = get_md5($ip);

    return $ip;
}

/**
 * 获取客户端‘真实’IP
 *
 * @param  int $tolong 是否转为数字
 * @param  int $md5    0 返回获取的 IP；1 md5 加密；2 get_md5 加密
 * @return mixed       返回相应的 IP 数据形式
 */
function get_client_ip($tolong = 0, $md5 = 0)
{
    $ip = '';
    $func = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
    foreach ($func as $f) {
        $ip = isset($_SERVER[$f]) ? $_SERVER[$f] : getenv($f);
        if ($ip && strcasecmp($ip, 'unknown')) {
            break;
        }
    }
    $pos = strpos($ip, ',');
    $pos && $ip = substr($ip, 0, $pos);
    $ip = $ip == '::1'
        ? '127.0.0.1'
        : (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $ip, $matches) ? $matches[0] : '0.0.0.0');
    $tolong && $ip = get_ip2long($ip);
    $md5 == 1 && $ip = md5($ip);
    $md5 == 2 && $ip = get_md5($ip);

    return $ip;
}

/**
 * ip2long 加强版，防止生成负数
 *
 * @param  string $ip IP
 * @return int    返回数字 IP
 */
function get_ip2long($ip = '')
{
    return bindec(decbin(ip2long($ip)));
}

/**
 * 取随机字符串
 *
 * @param  int $length  结果的长度
 * @param  int $numeric 1 为全数字，默认 0
 * @param  int $special 0 仅字母和数字，1 包含特殊字符，2 仅小写字母
 * @return string       返回字符串
 */
function random($length = 6, $numeric = 0, $special = 0)
{
    if ($numeric) {
        $ret = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
    } else {
        $ret = '';
        if ($special == 2) {
            $chars = 'abcdefghijklmnopqrstuvwxyz';
        } else {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
            $special && $chars .= '!@#$%^&*()[]{}~-_,.?';
        }
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $ret .= $chars{mt_rand(0, $max)};
        }
    }

    return $ret;
}

/**
 * 获取输入参数 支持过滤和默认值
 * 过滤函数可以是数字下标、字符串或数组及混合：'foo,trim'；1；array('foo,trim', 1)
 * 若取得的数据是数组，则每个数据都会执行过滤
 *
 * I('g.'); I('p.'); I('i.'); I('r.'); I('s.'); I('c.'); I('d.'); I('a.'); I('pp.'); I('ss.'); I('gg.');
 * 分别代表取值：
 * $_GET; $_POST; 'php://input'; $_REQUEST; $_SESSION; $_COOKIE; 外部数据来源; 自动; PATH_INFO; $_SERVER; $GLOBALS;
 *
 * I('id', 0, 'get_id');
 * 获取 id 参数，自动判断 REQUEST_METHOD（$_POST > 'php://input' > $_GET）通过 get_id() 函数处理后返回值，无值时返回 0
 *
 * I('p.name/s', '', 'trim,htmlspecialchars');
 * 获取 $_POST['name']，通过 trim() 和 htmlspecialchars() 函数处理后返回值，强制转为字符串，默认为 ''
 *
 * I('g.'); 返回完整 $_GET 并且经过全局配置中的默认过滤
 * I('g.', array(), null); 返回完整 $_GET 默认值为空数组，并且不过滤
 * I('name', '123'); I('name', '123', null); 不过滤返回，缺省值 123
 * I('name', '123', 'trim,1,intval');
 * 字符串下标为函数名，多个过滤以 , 分隔，按先后顺序处理：intval({$config['FilterStr'][1]}(trim(name)));
 * I('name', '123', array('trim,1', 'intval')); 等效上一条
 * I('name', '123', array('trim', 1, 'intval')); 等效上一条
 * I('name', '123', 0); 数字下标，直接取配置中的过滤参数：$config['FilterStr'][0]
 * I('name', '123', '1'); $config['FilterStr'][1]
 * I('name', '123', '01'); 无效函数，会返回默认值，如果是数组则每个值都是默认值
 * I('d.0.0.name.1', '', 0, $data); 支持多维数组取值，过滤数组键值或得到复杂表单值时适用：$data[0][0]['name'][1];
 * I('f.CharSet'); 取 global.php 中 $config 设置项
 *
 * @param  string $name    变量的名称，支持指定类型
 * @param  mixed  $default 默认值
 * @param  mixed  $filter  指定过滤函数：$config['FilterStr'][0]，null 或 '' 时不过滤，默认为 null
 * @param  array  $datas   外部数据源，数组
 * @param  string $pre     分隔符，默认为 .
 * @return mixed           返回处理后的值
 */
function I($name, $default = null, $filter = null, $datas = array(), $pre = '.')
{
    if (empty($name)) {
        return $default;
    }

    // 初始化默认返回值
    $default = isset($default) ? $default : null;

    // 指定修饰符，强制转换的数据类型
    if (strpos($name, '/')) {
        list($name, $type) = explode('/', $name, 2);
    }

    // 分隔符
    empty($pre) && $pre = '.';

    // 指定参数来源
    if ($pos = strpos($name, $pre)) {
        // 第一个 . 之前的值为 $method，之后的值为 $name
        //$method = strstr($name, $pre, true);
        $method = substr($name, 0, $pos);
        $name = ltrim(strstr($name, $pre), $pre);
    } else {
        // 默认为自动判断来源
        $method = 'a';
    }

    // 取值：支持多维数组取值：$name == '0.0.name.1' == $data[0][0]['name'][1];
    switch (strtolower($method)) {
        case 'f':
            // config
            $ret = get_value_i($name, $GLOBALS['config'], $default, $pre);
            break;
        case 'g':
            // get
            $ret = get_value_i($name, $_GET, $default, $pre);
            break;
        case 'p':
            // post
            $ret = get_value_i($name, $_POST, $default, $pre);
            break;
        case 'i':
            // input
            parse_str(file_get_contents('php://input'), $_PUT);
            $ret = get_value_i($name, $_PUT, $default, $pre);
            break;
        case 'r':
            // request
            $ret = get_value_i($name, $_REQUEST, $default, $pre);
            break;
        case 's':
            // session
            $ret = isset($_SESSION) ? get_value_i($name, $_SESSION, $default, $pre) : $default;
            break;
        case 'c':
            // cookie
            $ret = get_value_i($name, $_COOKIE, $default, $pre);
            break;
        case 'd':
            // data，来源是数组则判断 name 索引的值，否则返回来源值(真)或缺省值
            $ret = get_value_i($name, $datas, $default, $pre);
            break;
        case 'a':
            // auto
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $ret = get_value_i($name, $_POST, $default, $pre);
                    break;
                case 'PUT':
                    parse_str(file_get_contents('php://input'), $_PUT);
                    $ret = get_value_i($name, $_PUT, $default, $pre);
                    break;
                default:
                    $ret = get_value_i($name, $_GET, $default, $pre);
                    break;
            }
            break;
        case 'pp':
            // path
            $path = empty($_SERVER['PATH_INFO']) ? array() : explode(URL_PRE, trim($_SERVER['PATH_INFO'], URL_PRE));
            $ret = get_value_i($name, $path, $default, $pre);
            break;
        case 'ss':
            // server
            $ret = get_value_i(strtoupper($name), $_SERVER, $default, $pre);
            break;
        case 'gg':
            // globals: gg.config.Index == $GLOBAL['config']['Index']
            $ret = get_value_i($name, $GLOBALS, $default, $pre);
            break;
        default:
            return $default;
            break;
    }

    if ($ret === $default) {
        return $ret;
    }

    // 执行过滤，null 或 '' 时跳过
    if (isset($filter) && '' !== $filter) {
        $filters = array();

        // 形成过滤数组
        if (is_array($filter)) {
            // 只支持二维数组，重整过滤函数
            foreach ($filter as $d) {
                // 正则
                if (0 === strpos($filter, '/')) {
                    $filters[] = $d;
                } else {
                    // 分割多个过滤函数并合并
                    $tmp = explode(',', $filter);
                    foreach ($tmp as $dd) {
                        $filters[] = $dd;
                    }
                }
            }
        } elseif (0 === strpos($filter, '/')) {
            // 正则
            $filters[] = $filter;
        } else {
            // 以 , 分隔转为数组
            $filters = explode(',', $filter);
        }

        // 开始过滤
        if ($filters) {
            foreach ($filters as $filter) {
                // 1、判断是否正则过滤，2、判断是否自定义函数过滤，3、读取全局配置或使用 PHP Filter 函数过滤
                if (0 === strpos($filter, '/')) {
                    // 执行正则过滤数组版
                    $ret = filter_preg_arr($ret, $filter, $default);
                } elseif (function_exists($filter)) {
                    // 自定义函数过滤
                    $ret = filter($ret, $filter);
                } else {
                    // 取全局配置设定 或 PHP Filter 函数来完成过滤
                    // $filter = 0 : $config['FilterStr'][0]
                    // $filter = 257 : filter_list()->int : int()
                    // $filter = 'validate_email' : filter_var($ret, filter_id('validate_email'))
                    // 优先取全局配置，有效：1，'1'，01；无效：'01'，若配置中有非数字下标也有效
                    if (isset($GLOBALS['config']['FilterStr'][$filter])
                        && ($conf = $GLOBALS['config']['FilterStr'][$filter])
                    ) {
                        // 重组全局配置中的函数（如果多个）
                        $do_filters = explode(',', $conf);
                        foreach ($do_filters as $do_filter) {
                            // 自定义函数过滤
                            $ret = filter($ret, $do_filter);
                        }
                    } else {
                        // filter_var 过滤
                        $ret = filter_var_arr($ret, is_number($filter) ? $filter : filter_id($filter), $default);
                    }
                }
            }
        }
    }

    // 强制数据类型
    if (!empty($type)) {
        switch (strtolower($type)) {
            case 'a': // 数组
                $ret = (array)$ret;
                break;
            case 'o': // 对象
                $ret = (object)$ret;
                break;
            case 'd': // 数字
            case 'i': // 数字
                $ret = (int)$ret;
                break;
            case 'f': // 浮点
                $ret = (float)$ret;
                break;
            case 'b': // 布尔
                $ret = (boolean)$ret;
                break;
            default: // 字符串
                $ret = (string)$ret;
                break;
        }
    }

    return $ret;
}

/**
 * 取值中间函数，详见 I()
 *
 * @param  string $name    要取值的关键字
 * @param  mixed  $data    待取值的数据
 * @param  mixed  $default 缺省值
 * @param  string $pre     关键字分隔符
 * @return mixed           返回最终取值
 */
function get_value_i($name = '', $data = array(), $default = null, $pre = '.')
{
    // $name 为 null 或 '' 时，返回 $data 或 $default
    if (isset($data) && isset($name) && '' !== $name) {
        // 如果来源数据是数组，则按下标取值，支持多维数组
        if (is_array($data)) {
            $indexs = explode($pre, $name);
            // 循环下标，逐级取值，直到未定义或为 null
            foreach ($indexs as $index) {
                if (isset($ret)) {
                    if (isset($ret[$index])) {
                        $ret = $ret[$index];
                    } else {
                        // 有下标无值时返回默认值
                        $ret = $default;
                        break;
                    }
                } else {
                    if (isset($data[$index])) {
                        $ret = $data[$index];
                    } else {
                        // 有下标无值时返回默认值
                        $ret = $default;
                        break;
                    }
                }
            }
        } else {
            $ret = $data;
        }
    } else {
        $ret = isset($data) ? $data : $default;
    }

    return (isset($ret) ? $ret : $default);
}

/**
 * filter_var 数组版，带默认值，区别于 filter_var_array
 * 整个数组用相同参数过滤
 *
 * @param  mixed $data      待过滤的数据
 * @param  int   $filter_id FILTER_DEFAULT(516)
 * @param  mixed $default   缺省值
 * @return mixed            返回结果
 */
function filter_var_arr(&$data, $filter_id = FILTER_DEFAULT, $default = null)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = filter_var_arr($val, $filter_id, $default);
            $data[$key] === false && $data[$key] = $default;
        }
    } else {
        $data = filter_var($data, $filter_id);
        $data === false && $data = $default;
    }

    return $data;
}

/**
 * 正则过滤数组版
 *
 * @param  mixed  $data    待过滤的数据
 * @param  string $reg     正则表达式
 * @param  mixed  $default 缺省值
 * @return mixed           返回结果
 */
function filter_preg_arr(&$data, $reg = '', $default = null)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = filter_preg_arr($val, $reg, $default);
        }
    } else {
        $data = preg_match($reg, $data) ? $data : $default;
    }

    return $data;
}

/**
 * 按方法过滤，$func 方法必须已存在并有返回值（数据类型会变为 $func 返回的数据类型）
 * array_map() 多维数组会返回 null
 *
 * @param  mixed  $data       待过滤的数据
 * @param  string $func       方法名
 * @param  int    $filter_key 是否过滤 key
 * @return mixed              返回结果
 */
function filter(&$data, $func, $filter_key = 0)
{
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            if ($filter_key) {
                unset($data[$key]);
                $key = call_user_func($func, $key);
            }
            $data[$key] = filter($val, $func, $filter_key);
        }
    } else {
        $data = call_user_func($func, $data);
    }

    return $data;
}

/**
 * 构造 where 条件和数据绑定，用于预处理语句
 *
 * @param  array $where   条件数据集
 * @param  int   $re_null 结果为空时是否必定返回一个条件：1 = 1
 * @return array          返回结果数组
 */
function get_where($where = array(), $re_null = 0)
{
    $ret = array(
        'where' => "",
        'binds' => array()
    );

    if (isset($where) && is_array($where)) {
        $j = 5000;
        $ops = array('>', '<', '=', '>=', '<=', '<>', '!=',
                     'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN');

        foreach ($where as $k => $v) {
            // 字段名处理：array('id >' => 10)，$k = 'id'; $op = '>';
            $tmp = explode(' ', trim($k), 2);
            $k = isset($tmp[0]) ? get_wd($tmp[0]) : '';

            // 字段不能为空
            if ($k === '') {
                continue;
            }

            // 操作符
            $op = isset($tmp[1]) ? strtoupper(trim($tmp[1])) : '=';
            $op = in_array($op, $ops) ? $op : '=';

            // 是否为 IN 或 BETWEEN，值为非数组时不允许
            $is_ib = 0;
            (strpos('.' . $op, 'IN') && ($is_ib = 1)) || (strpos('.' . $op, 'BETWEEN') && ($is_ib = 2));

            // 兼容联接表查询中表别名数据绑定：TU.user_id = 1 >> array('f_TU_user_id__5000' => 1)
            // 字段名长度限制：Oracle 30，MySQL 64，Access 64，MSSQL 128，db2 128，不影响这里绑定，不要用 -，不过 SQL 语句长度有限制
            $k_str = 'f_' . preg_replace('/\W/', '_', $k) . '__' . $j;

            if (is_array($v)) {
                if ($is_ib == 2 && count($v) == 2) {
                    // BETWEEN：array('time BETWEEN' => array('2016-10-10', '2016-12-01'));
                    $ret['where'] .= " AND {$k} {$op} :{$k_str}_a AND :{$k_str}_b";
                    $ret['binds'][$k_str . '_a'] = $v[0];
                    $ret['binds'][$k_str . '_b'] = $v[1];
                } else {
                    // 数组默认使用 IN
                    strpos($op = str_replace('BETWEEN', 'IN', $op), 'IN') !== false || $op = 'IN';

                    // IN 绑定特殊处理
                    $in_where = '';

                    // 按类型生成 get_in : IN (1,'2',null,Array,0.76)
                    foreach ($v as $i => $d) {
                        // 采用特殊的字段名绑定
                        $in_key = $k_str . '_' . $i;
                        $ret['binds'][$in_key] = $d;
                        $in_where .= ':' . $in_key . ',';
                    }

                    $ret['where'] .= " AND {$k} {$op} (" . rtrim($in_where, ',') . ")";

                    // 该方法只有第1个,前的数值起作用，弃用
                    //$ret['where'] .= " AND {$k} IN (:{$k})";
                    //$ret['binds'][$k] = implode(',', $v);
                }
            } else {
                // 非数组时不允许 IN 和 BETWEEN
                $is_ib && $op = '=';
                $ret['where'] .= " AND {$k} {$op} :{$k_str}";
                $ret['binds'][$k_str] = $v;
            }

            $j++;
        }
    }

    // 是否必定返回一个条件
    $re_null && !$ret['where'] || $ret['where'] = "1 = 1" . $ret['where'];

    return $ret;
}

/**
 * 根据 parse_url 的结果重新组合 url
 *
 * @param  string $params url 参数
 * @return string         返回 url
 */
function mk_url($params = '')
{
    return (isset($params['scheme']) ? $params['scheme'] . '://' : '') .
           (isset($params['user'])
               ? $params['user'] . (isset($params['pass']) ? ':' . $params['pass'] : '') . '@'
               : '') .
           (isset($params['host']) ? $params['host'] : '') .
           (isset($params['port']) ? ':' . $params['port'] : '') .
           (isset($params['path']) ? $params['path'] : '') .
           (isset($params['query']) ? '?' . $params['query'] : '') .
           (isset($params['fragment']) ? '#' . $params['fragment'] : '');
}

/**
 * 过滤 url 中的非法字符串
 *
 * @param  string $url url 字符串
 * @return string          返回过滤后的结果
 */
function safe_url($url = '')
{
    // 针对 location 的 xss 过滤, 因为其特殊性无法使用 remove_xss 函数
    // fix issue 66
    $params = parse_url(str_replace(array("\r", "\n", "\t", ' '), '', $url));

    // 禁止非法的协议跳转，如：javascript
    if (isset($params['scheme']) && !in_array($params['scheme'], array('http', 'https'))) {
        return '/';
    }

    // 过滤解析串
    $params = array_map('safe_url_xss', $params);

    return mk_url($params);
}

/**
 * 将 url 中的非法 xss 去掉时的数组回调过滤函数
 *
 * @param  string $str 传入字符串
 * @return string      返回处理后的结果
 */
function safe_url_xss($str = '')
{
    // CRLF (carriage return/line feed, 0d 0a)
    $str = str_replace(array('%0d', '%0a'), '', strip_tags($str));
    // 清除函数开头和结尾
    $str = preg_replace(array('/\(\s*("|\')/i', '/("|\')\s*\)/i',), '', $str);
    return $str;
}

/**
 * 返回安全的搜索参数，防止注入问题（建议使用 PDO）
 *
 * @param  string $str 传入字符串
 * @return string      返回处理后的结果
 */
function get_wd($str = '')
{
    return str_replace(array(
        '%20',
        '%27',
        '%2527',
        '`',
        '*',
        '"',
        "'",
        '..',
        '&',
        '#',
        '+',
        '=',
        ';',
        '%',
        '[',
        ']',
        '(',
        ')',
        '<',
        '>',
        '{',
        '}',
        '!',
        '\\'
    ), '', preg_replace('/\s+/', '', $str));
}

/**
 * 发送 Http 状态
 *
 * @param  int $code 状态码：404
 * @return $code     返回设置前的状态码
 */
if (!function_exists('http_response_code')) {
    function http_response_code($code = null)
    {
        $http_status = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
            509 => 'Bandwidth Limit Exceeded'
        );
        if ($code !== null) {
            empty($http_status[$code]) && exit('Unknown http status code "' . htmlentities($code) . '"');
            $GLOBALS['http_response_code'] = $code;
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
            header($protocol . ' ' . $code . ' ' . $http_status[$code]);
            // 确保FastCGI模式下正常
            header('Status:' . $code . ' ' . $http_status[$code]);
        } else {
            $code = isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200;
        }

        return $code;
    }
}
