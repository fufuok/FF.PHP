<?php
/**
 * 全局公共系统配置文件, 预处理环境及自定义函数加载
 * 框架外的插件可调用系统环境而不加载框架
 *
 * 项目所在目录, 即 WEB 根路径(WEB): 当项目文件与框架放在一起时, 与(ROOT)相同; 当框架放置于其他目录时需要设置(WEB): 
 * WEB => 框架目录下的 www 文件夹, 上传附件, CSS, 图片等基于该文件夹路径, WEB 下可直接访问到资源
 *
 * @author Fufu, 2013-08-09
 */

// 全局路径及常量定义
define('FF', 1);
define('DS', DIRECTORY_SEPARATOR);
define('URL_PRE', '/');                          // PATHINFO 模式下, 各参数之间的分割符号
define('DIR', '/');                              // 目录标识
define('ROOT', dirname(__FILE__) . DS);          // 框架根目录
define('WEB', dirname(ROOT) . DS . 'www' . DS);  // ** 项目根目录(相对于框架的相对路径), WEB 下
define('M', ROOT . 'm' . DS);                    // 模型目录
define('C', ROOT . 'c' . DS);                    // 控制器, 系统配置目录
define('L', ROOT . 'l' . DS);                    // 自定义类库目录
define('D', ROOT . 'd' . DS);                    // 缓存数据文件目录
define('V', WEB . 'v' . DS);                     // 项目模板, 资源目录, WEB 下
define('A', WEB . 'd' . DS);                     // 项目附件文件夹, WEB 下
define('CACHE', D. 'cache' . DS);                // 数据目录下的缓存目录
define('PHP', D . 'php' . DS);                   // 数据目录下的 PHP 缓存目录
define('ATTACH', A . 'attach' . DS);             // 项目附件保存路径
define('ATTACH_URL', '/d/attach/');              // 项目附件文件根地址
define('CSS', A . 'css' . DS);                   // 项目附件目录下的用户 CSS 缓存目录
define('CSS_URL', '/d/css/');                    // 项目用户 CSS 根地址
define('IS_HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off');
define('HOST', (IS_HTTPS ? 'http://' : 'https://') . $_SERVER['HTTP_HOST']);
define('HTTP', HOST . str_ireplace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/');

// Cookies SameSite 设置, None, Lax, Strict, 有值时必定启用 secure = true
define('SAME_SITE', IS_HTTPS ? 'None' : '');

// 项目基础配置
define('WEB_NAME', 'FF');
define('WEB_COMPANY', 'Fufu');
define('WEB_ROOT', '/');                         // 站内访问路径
define('WEB_HTTP', 'http://git.ff/');            // 项目访问地址

// 框架基本配置(必须)
$config['PathInfo']   = 1;             // 是否开启 index.php/Ctrl/Action/param/value 模式
$config['UrlRewrite'] = 1;             // 是否开启伪静态, 需要配置 .htaccess
$config['XSS']        = 1;             // 是否开启 XSS 防范
$config['DebugPHP']   = 2;             // PHP 运行报错信息, 0 屏蔽; 1 ~E_NOTICE; 2 E_ALL
$config['DebugSQL']   = 1;             // 是否开启源码调试SQL语句
$config['CharSet']    = 'utf-8';       // 设置网页编码, 'gbk', 'big5', 'utf-8'
$config['Filter']     = 'mysql';       // 多数据库时可设置为空, 入库时自行处理
$config['CtrlName']   = 'c';           // 自定义控制器名称, 如: index.php?c=index
$config['ActionName'] = 'a';           // 自定义方法名称, 如: index.php?c=index&a=index_action
$config['Index']      = 'index.php';   // 默认主页: index.php

// SESSION
$config['Session'] = array(
    'autostart' => PHP_SAPI !== 'cli',      // 是否自动调用 session_start(), 必须设置
    'handler'   => 'files',                 // memcached, memcache, redis, files, handler && save_path 空时用 php.ini 配置
    'save_path' => '2;' . D . 'session',    // 'ip:11211', 'tcp://ip:11211', '2;' . SESSION, 'tcp://ip:6379?auth=x'
    'expire'    => 1440,                    // session.cookie_lifetime, session.gc_maxlifetime, 为 0 时默认 31536000 秒
    'path'      => '/',                     // session.cookie_path
    'domain'    => '',                      // session.cookie_domain 同域名共享时填写 '.xy.com'
    'secure'    => IS_HTTPS,                // session.cookie_secure, true 表示 cookie 仅在使用 安全 链接时可用
    'httponly'  => false,                   // session.cookie_httponly, true 表示 PHP 发送 cookie 的时候会使用 httponly 标记
);

// 杂项配置
$config['TimeZones']  = 'Asia/Shanghai';                               // 时区 PRC
$config['DateFormat'] = 'Y-m-d H:i';                                   // 默认时间显示格式
$config['CookiePre']  = 'FF.App_PHP^';                                 // Cookie 前缀
$config['PwdPre']     = 'FF.8$8_PHP^';                                 // MD5密码前缀
$config['StrPre']     = '|^`^|';                                       // 多段字符串分隔符
$config['StrSplit']   = '|^^``^^|';                                    // 多段字符串分隔符
$config['WordPre']    = '<div class="newpageword">newpageword</div>';  // 大段文本分隔符，用于长文章内分页
$config['SuperAdmin'] = '-1';                                          // 超级管理员

// 预置的一些过滤函数, 配合 I() 使用, index < 257 避免与 filter_list 冲突, 值必须为已存在的函数名称, 不能为正则或数字
// null, 返回原始数据
// 0, htmlspecialchars (缺省)
// 1, fr(), 用于取得 subject, realname 等普通文本框
// 2, fmsg(), 用于取得 message, 留言, 审批等多行文本框, 仅保留换行和链接
// 3, fid(), 同 get_id(), 取 ID 等正整数或 0
// 4, fnum(), 同 get_num(), 取浮点数或整数, 可
// 更多扩展..
$config['FilterStr'] = array('htmlspecialchars', 'fr', 'fmsg', 'fid', 'fnum');

// 数据库配置
$config['DB'] = array(
    array(
        'drive'    => 'mPDO',       // 数据库驱动类型, 推荐 PDO
        'host'     => '',           // 主机
        'port'     => 0,            // 端口
        'dbtype'   => 'sqlite',     // 数据库类型, 涉及过滤方式
        'dbname'   => '',           // 数据库名
        'dbuser'   => '',           // 用户名
        'dbpass'   => '',           // 密码
        'dbfile'   => ':memory:',   // SQLite 数据库文件位置
        'tbpre'    => 'tb_',        // 表名前缀
        'charset'  => 'utf8',       // 数据库编码, 'gbk', 'big5', 'utf8'
        'lower'    => 1,            // 是否强制字段输出为小写字母
        'pconnect' => 1,            // 是否使用持久连接
        'dbxor'    => 0             // 数据库, 用户名, 密码是否加密
    ),
    /**
    array(
        'drive' => 'mPDO',
        'host' => 'localhost',
        'socket' => '',
        'port' => 3306,
        'dbtype' => 'mysql',
        'dbname' => 'SSkEX0AkVxk3U0FGFF0=',
        'dbuser' => 'SSkHX0AwXik3FAk=',
        'dbpass' => 'SSkFX0AkVzImRUYQDQ==',
        'dbfile' => '',
        'tbpre' => 'sdyz_',
        'charset' => 'utf8mb4',
        'lower' => 1,
        'pconnect' => 1,
        'dbxor' => 1
    ),
    array(
        'drive' => 'mPDO',
        'host' => 'localhost',
        'port' => 0,
        'dbtype' => 'mysql',
        'dbname' => 'ff_test',
        'dbuser' => 'root',
        'dbpass' => 'fftest',
        'dbfile' => '',
        'tbpre' => 'sdyz_',
        'charset' => 'utf8',
        'lower' => 1,
        'pconnect' => 0,
        'dbxor' => 0
    ),
    array(
        'drive' => 'mPDO',
        'host' => '10.1.1.1',
        'port' => 10521,
        'dbtype' => 'oracle',
        'dbname' => 'orac',
        'dbuser' => 'oracus',
        'dbpass' => 'oracpass',
        'dbfile' => '',
        'tbpre' => '',
        'charset' => 'utf8',
        'lower' => 1,
        'pconnect' => 1,
        'dbxor' => 0
    ),
    array(
        'drive' => 'mPDO',
        'host' => '',
        'port' => 0,
        'dbtype' => 'sqlite',
        'dbname' => '',
        'dbuser' => '',
        'dbpass' => '',
        'dbfile' => D . 'fftest.db',
        'tbpre' => 'tb_',
        'charset' => 'utf8',
        'lower' => 1,
        'pconnect' => 1,
        'dbxor' => 0
    ),
    array(
        'drive' => 'mPDO',
        'host' => 'whg.gotoip.net',
        'port' => 0,
        'dbtype' => 'mysql',
        'dbname' => 'xxxx',
        'dbuser' => 'xxxx',
        'dbpass' => 'xxxx',
        'dbfile' => '',
        'tbpre' => 'whg_',
        'charset' => 'utf8',
        'lower' => 1,
        'pconnect' => 1,
        'dbxor' => 0
    ),
    array(
        'drive' => 'mPDO',
        'host' => 'localhost',
        'port' => 0,
        'dbtype' => 'mssql',
        'dbname' => 'fftest',
        'dbuser' => 'xx',
        'dbpass' => 'xxxxxx',
        'dbfile' => '',
        'tbpre' => '',
        'charset' => 'utf8',
        'lower' => 1,
        'pconnect' => 1,
        'dbxor' => 0
    ),*/
);

// 公共环境处理及基础函数定义(必须)
include(M . 'common.php');

/** 加载自定义类, 函数等 */
include(L . 'function.php');

/** 致命错误日志 */
register_shutdown_function('log_fatal_err');
