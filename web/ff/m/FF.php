<?php
/**
 * FF MVC PHP 框架 (/index.php/ctrl_class/ctrl_class_method/param/value/...).
 *
 * 支持 URL 常见写法：
 * /test/save/data/123/nothing//data1/2/ （正常伪静态写法，nothing 取值为空字符串）
 * /index.php/test/save/data/123/ (通用写法；采用伪静态时 IIS 不允许这样写，应该去掉 /index.php；Apache 能自动容错)
 * /?c=test&a=save&data=123 （c 和 a 是保留字，只能传控制器类名和方法名）
 * /index.php?c=test&a=save&data=123
 * /test/save/data/123/?other=abc&add=ok&data=ok （? 传值优先级更高，$_GET['data'] == 'ok';）
 * /test/save/data/123?other=abc&add=ok
 * /test/save/nothing//?other=abc&add=ok
 * /test/save/nothing/?other=abc&add=ok
 * /test/save/nothing?other=abc&add=ok
 * /test/save/?data=123&other=abc&nothing=&add=ok
 *
 * if (!-e $request_filename) { rewrite ^(/?)(app[0-9])/(.*)$ /$2/index.php/$3 last; }
 * http://ff.com/app1/test/save/data/123 WEB_ROOT == '/app1/' 独立一套程序，与 app2 不相关（注意 Cookies 同名问题）
 * http://ff.com/app2/test/save/data/123 WEB_ROOT == '/app2/'
 *
 * 支持控制器方式接收 PathInfo 参数，如访问：
 * /news/view/123/more/xxx
 * 控制器方法可接收到 123 作为 ID，参数个数不限（会产生相应无意义的 $_GET 项，请忽略）：
 * class news extends C { function _view($news_id, $more) { $news_id == 123; $more == 'more'; $_GET['123'] == 'more'; $_GET['xxx'] == ''; } }
 *
 * @author Fufu, 2013-07-18
 * @update 2013-09-11 默认不支持 C 对数据库直接操作；M 和 C 调用其他模型或类方法相同：$this->M('mClass')
 * @update 2013-11-05 修改 Url 转换后的地址为站内根路径，配合 get_url(FF::Url()) 生成可外链访问地址
 * @update 2013-12-28 优化代码，实现执行数据库操作时才开始加载数据库驱动
 * @update 2014-06-30 增加 WebService 连接
 * @update 2014-07-02 修改数据库驱动方式，支持同时操作多个数据库，以数据库配置数组下标区分
 * @update 2014-12-29 调整 L 参数顺序，方便类库加载配置文件：$this->L('Upload', $options)->save();
 * @update 2015-12-13 增加 I() 函数，获取各类数据，支持默认值，支持过滤，支持多维数组取值，比 ThinkPHP::Input 更强大
 * @update 2016-08-18 M L WS 各自独立使用静态数组缓存类库加载，效率更高，不采用 & 返回
 * @update 2016-10-18 增加 getDataList()
 * @update 2016-12-12 增加 url 的安全过滤，只支持 http/https 的地址转换和跳转，影响：FF::Url() 和 FF::Go()
 * @update 2017-01-01 FF::Msg 和 FF::Go() 增加 http_response_code 参数
 * @update 2017-02-02 FF::Msg 增加 view 参数
 * @update 2017-03-15 修正 $_SERVER['REQUEST_URI'] 在 IIS 中的 // 问题（IIS 会把连续的 / 自动合并为一个，影响传值）
 * @update 2017-09-11 调用控制器方法时增加 $ff_uri_params 传参；降低 URI 中 PathInfo 解析的键值对附加到 $_GET / $_REQUEST 的优先级
 * @update 2017-09-15 增加视图 V 调用时传值，增加返回视图结果内容
 * @update 2018-06-06 同域名子目录应用优化，框架各自独立，/1/FF/；/2/FF/，定义不同的 WEB_ROOT == '/1/'
 * @update 2018-07-13 优化一些可能出现 Notice 的地方
 * @update 2018-08-02 WS() 增加 $options 参数
 * @update 2018-12-28 getDataList() page_size 未传值时默认查询所有记录
 * @update 2019-03-13 仅同步时间
 */

defined('FF') or die('404');

/**
 * 网站总进程对象，分发路由、加载控制器、方法
 * 通用加载模型和类库方法，M 模型及 C 控制器及派生类可引用，相同类库只会加载一次：
 * M('mClass')  加载模型类库，并赋值到：$this->m[] = new mClass
 * L('Class')   加载自定义类，并赋值到：$this->l[] = new Class
 * WS($wsdl)    加载 WebService，并赋值到：$this->ws[] = new Class.
 *
 * @param string $m  模型对象集
 * @param string $l  类库对象集
 * @param array  $d  视图 d 的额外数据集，方便在派生 C 类中为视图增加变量
 * @param array  $ws WebService 连接
 */
class FF
{
    public $m  = array();
    public $l  = array();
    public $d  = array();
    public $ws = array();

    public static function Run()
    {
        // 解码 URI 并修正 $_SERVER['REQUEST_URI'] 在 IIS 中的 // 被自动合并的问题
        empty($_SERVER['UNENCODED_URL']) || $_SERVER['REQUEST_URI'] = $_SERVER['UNENCODED_URL'];
        $ff_uri = urldecode($_SERVER['REQUEST_URI']);

        // 去除 WEB_ROOT 部分，兼容子目录中运行独立应用：
        // /app1/index.php/c/a/value => index.php/c/a/value;
        // /app2/c/a/?f => c/a/?f
        stripos($ff_uri, WEB_ROOT) === 0 && $ff_uri = substr($ff_uri, strlen(WEB_ROOT));

        // 去除主页地址部分：index.php
        $ff_index = I('f.Index');
        $ff_index && stripos($ff_uri, $ff_index) === 0 && $ff_uri = substr($ff_uri, strlen($ff_index));

        // 去除 URI 首尾 /
        $ff_uri = trim($ff_uri, '/');

        // 以 / 分割 URI 的 PathInfo 部分，排除 ? 传值的地址部分
        $ff_pos = strpos($ff_uri, '?');
        $ff_uri_info = $ff_pos === 0
            ? array()
            : explode('/', $ff_pos ? rtrim(substr($ff_uri, 0, $ff_pos), '/') : $ff_uri);

        // 取得 ff_c_name（控制器类和方法均为小写字母）
        $ff_c_name = strtolower(empty($ff_uri_info[0]) ? I('g.' . I('f.CtrlName', 'c'), 'index') : $ff_uri_info[0]);

        // 载入 ff_c_name 控制器文件
        if (is_file($ff_c_file = C . $ff_c_name . '.php')) {
            include $ff_c_file;
        } else {
            FF::Msg('404', 404);
        }

        // 检查当前控制器是否存在，默认为 index
        class_exists($ff_c_name) || (($ff_c_name = 'index') && class_exists($ff_c_name)) || FF::Msg('404', 404);

        // 类的所有方法名
        $ff_c_methods = (array)get_class_methods($ff_c_name);

        // 方法加下划线前缀，且只能有一个下划线
        $ff_a_name = strtolower(empty($ff_uri_info[1]) ? I('g.' . I('f.ActionName', 'a'), 'index') : $ff_uri_info[1]);
        $ff_a_name = '_' . ltrim($ff_a_name, '_');

        // 检查方法是否存在，默认为 _index
        in_array($ff_a_name, $ff_c_methods)
        || (($ff_a_name = '_index') && in_array($ff_a_name, $ff_c_methods))
        || FF::Msg('404', 404);

        // 将 URI 解析为键值对
        $ff_uri_data = $ff_uri_params = array();

        if (($j = count($ff_uri_info)) > 2) {
            for ($i = 2; $i < $j; ++$i) {
                strpos($ff_uri_info[$i], '?') === 0
                || $ff_uri_data[$ff_uri_info[$i]] = isset($ff_uri_info[++$i]) ? $ff_uri_info[$i] : '';
            }

            if ($ff_uri_data) {
                // 整合 URI 键值对，低优先级（PHP.ini 建议：request_order = "GP"）
                $_GET += $ff_uri_data;
                $_REQUEST += $ff_uri_data;
            }

            $ff_uri_params = array_slice($ff_uri_info, 2);

            /*
             * /index.php/test/index/get1/val1/get2//get3/val3/?get3=real_val3&more=1
             *
             * print_r($_GET);
             * Array
             * (
             *     [get3] => real_val3
             *     [more] => 1
             *     [get1] => val1
             *     [get2] =>
             * )
             *
             * print_r($ff_uri_params); // 控制器方法中对应的 func_get_args();
             * Array
             * (
             *     [0] => get1
             *     [1] => val1
             *     [2] => get2
             *     [3] =>
             *     [4] => get3
             *     [5] => val3
             * )
             *
             *
             * /index.php?c=test&a=index&get3=real_val3&more=1
             *
             * print_r($_GET);
             * Array
             * (
             *     [c] => test
             *     [a] => index
             *     [get3] => real_val3
             *     [more] => 1
             * )
             *
             * print_r($ff_uri_params); // 控制器方法中对应的 func_get_args();
             * Array
             * (
             * )
             */
        }

        // 实例化控制器
        $ff = new $ff_c_name();

        // 调用控制器方法，? 传值不会作为调用参数，可正常使用 $_GET 获取
        call_user_func_array(array(&$ff, $ff_a_name), $ff_uri_params);

        // 调试模式时输出
        if (I('f.DebugPHP')) {
            echo mk_debug('FF', $ff);
        }

        // 释放资源
        $ff = null;
    }

    /**
     * 显示消息并中止页面.
     *
     * 调用：FF::Msg('错误：请检查！')
     *
     * @param string $msg  消息内容
     * @param int    $code 状态码：404, 500
     * @param string $view 消息视图页，默认为：msg.php
     */
    public static function Msg($msg = '', $code = 0, $view = 'msg')
    {
        $code && http_response_code($code);
        if (is_file(V . $view . '.php')) {
            $v = new V();
            $v->view($view, array('msg' => $msg));
            die();
        } else {
            die($msg);
        }
    }

    /**
     * 站内页面跳转.
     *
     * 调用：FF::Go('C/a/param/value')
     *
     * @param string $url  站内网址，如：user/info/id/123
     * @param int    $code 状态码：301 302
     */
    public static function Go($url, $code = 0)
    {
        $url = FF::Url($url);
        if (headers_sent()) {
            echo '<title>Redirecting..</title><meta http-equiv="refresh" content="0;url=' . $url . '">';
        } else {
            $code && http_response_code($code);
            header('Location: ' . $url);
        }

        exit();
    }

    /**
     * 站内路径转换.
     *
     * 调用：FF::Url('C/a/param/value')
     *
     * @param  string $url 站内网址，如：user/info/id/123
     * @return string      返回可访问的站内网址
     */
    public static function Url($url)
    {
        if ($url = safe_url($url)) {
            if (I('f.PathInfo')) {
                // 地址最后统一补齐 /
                $url = trim($url, '/') . '/';
                // 未开启伪静态时加上 index.php
                I('f.UrlRewrite') || $url = I('f.Index') . '/' . $url;
            } else {
                // 普通地址模式
                $urls = explode('/', $url);
                $j = count($urls);
                $url = I('f.Index');
                for ($i = 0; $i < $j; ++$i) {
                    if ($i == 0) {
                        $url = '?' . I('f.CtrlName', 'c') . '=' . $urls[0];
                    } elseif ($i == 1) {
                        $url .= '&' . I('f.ActionName', 'a') . '=' . $urls[1];
                    } else {
                        // 参数值
                        $url .= ((fmod($i, 2) == 0) ? '&' : '=') . $urls[$i];
                    }
                }
            }
        }

        return WEB_ROOT . ltrim($url, '/');
    }

    /**
     * 加载模型文件.
     *
     * 调用：$this->M('mUser')->doLogin();
     *
     * @param  string $file       文件名，不要带扩展名和路径，固定为 .php 文件
     * @param  array  $options    参数
     * @param  string $class_name 模型类名(默认为文件名)
     * @return object             模型对象
     */
    public function M($file, $options = array(), $class_name = null)
    {
        static $obj_m = array();

        $class_name || $class_name = $file;
        $class_key = $class_name . ($options ? md5(serialize($options)) : '');

        if (isset($obj_m[$class_key])) {
            return $obj_m[$class_key];
        }

        if (is_file($file = M . $file . '.php')) {
            class_exists($class_name) || include $file;
            $obj_m[$class_key] = $this->m[$class_key] = $options ? new $class_name($options) : new $class_name();

            return $obj_m[$class_key];
        }

        return null;
    }

    /**
     * 加载类库文件.
     *
     * 调用：$this->L('HttpCI')->mkRequest($url);
     *
     * @param  string $file       文件名，不要带扩展名和路径，固定为 .php 文件
     * @param  array  $options    参数，不能为 resource 类型
     * @param  string $class_name 类名(默认为文件名)
     * @return object             类对象
     */
    public function L($file, $options = array(), $class_name = null)
    {
        static $obj_l = array();

        $class_name || $class_name = $file;
        $class_key = $class_name . ($options ? md5(serialize($options)) : '');

        if (isset($obj_l[$class_key])) {
            return $obj_l[$class_key];
        }

        if (is_file($file = L . $file . '.php')) {
            class_exists($class_name) || include $file;
            $obj_l[$class_key] = $this->l[$class_key] = $options ? new $class_name($options) : new $class_name();

            return $obj_l[$class_key];
        }

        return null;
    }

    /**
     * 连接 WebService.
     *
     * 调用：$this->WS('http://127.0.0.1:8888/serviceforweb.svc?wsdl');
     * 调用：$this->WS(M . 'serviceforweb.xml', $options);
     *
     * @param  string $wsdl    API 地址
     * @param  array  $options 参数，不能为 resource 类型
     * @return object          类对象
     */
    public function WS($wsdl = '', $options = array())
    {
        static $obj_ws = array();

        $key = md5(($wsdl ? serialize($wsdl) : '') . serialize($options));
        
        if (!empty($obj_ws[$key])) {
            return $obj_ws[$key];
        }
        
        if (class_exists('SoapClient')) {
            try {
                $obj_ws[$key] = $this->ws[$key] = new SoapClient($wsdl, $options);
            } catch (SoapFault $e) {
                $obj_ws[$key] = $this->ws[$key] = null;
                $this->d['ws_' . $key] = 'Error: ' . $e->getMessage();
            }
        }

        return $obj_ws[$key];
    }

    /**
     * 获取或加载自定义文件.
     *
     * 获取文件内容：$this->F(V . 'tpl.html');
     * 包含代码文件：$this->F(D . 'config.php');  // $this->L() 用于加载类并 new
     *
     * @param  string $file       文件路径
     * @param  bool   $is_include 0 获取内容，1 包含并运行文件
     * @return mixed              返回 null 或文件内容
     */
    public function F($file, $is_include = false)
    {
        if (is_file($file)) {
            if ($is_include) {
                include $file;
            } else {
                return @file_get_contents($file);
            }
        }

        return null;
    }
}

/**
 * 模型数据层 (M)，单字母变量，仅框架最顶层定义，下同。
 *
 * 基于数据底层操作都需要继承 M 类，通过 db 方法来调用相应数据库驱动派生类的方法执行数据库操作。
 * 如：$this->db(0)->Q($sql); $this->db('name')->one($sql);
 *
 * M 模型派生类，即 m 目录下类库及文件命名为 m 前缀的驼峰命名，方法为首词首字母小写式驼峰命名，并根据语言特点变通命名。
 * 如：class mUser extends M { function setValue() {} }
 *
 * 其他功能类库，即 l 目录下类库及文件均使用无 m 前缀的标准驼峰命名方式。
 * 如：class UploadFile { function chkFileExt() {} }
 *
 * 注：构建 SQL 语句时使用 __B__ 作为表名前缀，SQL 语句执行时自动替换为系统配置中的表名前缀
 *
 * @param array $sql 调试信息，记录 SQL 语句
 */
class M extends FF
{
    public $sql = array();
    public $db  = array();

    public function __construct()
    {
    }

    /**
     * 按需驱动数据库，执行数据库操作时打开数据库连接并保存到公共变量用于重复使用.
     *
     * 调用：$this->db(0); $this->db('name');
     *
     * @param  int $i 数据库配置序号
     * @return object 数据库对象
     */
    public function db($i = 0)
    {
        static $obj_db = array();

        if (!($class_name = I('f.DB.' . $i . '.drive')) || !($options = I('f.DB.' . $i))) {
            return null;
        }

        $class_key = $class_name . md5(serialize($options));

        if (isset($obj_db[$class_key])) {
            return $obj_db[$class_key];
        }

        if (is_file($file = M . $class_name . '.php')) {
            class_exists($class_name) || include $file;
            $obj_db[$class_key] = $this->db[$class_key] = new $class_name($options);

            return $obj_db[$class_key];
        }

        return null;
    }

    /**
     * 获取表数据（数据库，公用）.
     *
     * 参数默认值：
     * $param = array(
     *     // $config['DB'] 数据库配置下标
     *     'db'         => 0,
     *     // 必填，表名，__B__为当前数据库配置中的表前缀 tbpre，如：__B__user == oa_user
     *     'table'      => "",
     *     'field'      => "*",
     *     // 排序语句："group ASC, user_id DESC"
     *     'order_by'   => "",
     *     // 返回：array(array('field1' => value1), ...)，为真时返回第一条记录的数据数组：array('field1' => value1, ...)
     *     'one'        => 0,
     *     // 为真是返回记录总数，分页时必须为真
     *     'total'      => 0
     * );
     * // 条件为键(键可包含操作符，与字段名以空格分隔)值方式，自动采用预处理语句查询，所有条件均为 AND，特殊需求可采用附加条件参数处理
     * $where = array();
     * $pages = array(
     *     'total'      => 0,       // 第一次获得值时会返回总数，翻页时可传递进去减少一次查询总数的操作
     *     'page_size'  => 0,       // 分页时必填，每页数量
     *     'page'       => 0        // 当前页面，从 1 开始，0 默认会转换为 1
     * );
     * $add = array(
     *     'add_sql'    => "",      // ...FROM... add_sql WHERE... add_where...
     *     'add_where'  => "",
     *     'add_binds'  => array()  // $where['binds'] += $add['add_binds']
     * );
     *
     * 返回默认值：
     * array(); // !$param['total']
     * array(
     *     'total'      => 0,
     *     'page_size'  => 0,
     *     'page'       => 0,
     *     'list'       => array()
     * );
     *
     * 返回表记录，前 100 条：
     * $this->getDataList(array('table' => "__B__user"), array(), array('page_size' => 100));
     *
     * 按条件查询并排序：
     * $this->getDataList(array('table' => "__B__user", 'order_by' => "user_id ASC"),
     *                    array('user_id' => 1, 'user_status' => 1));
     * SELECT * FROM oa_user WHERE user_id = 1 AND user_status = 1 ORDER BY user_id ASC
     *
     * IN 条件查询返回指定字段：
     * $this->getDataList(array('table' => "__B__user", 'field' => "user_id, abc"),
     *                    array('user_id' => array(1, 5), 'user_status' => 1));
     * SELECT user_id, abc FROM oa_user WHERE user_id IN (1,5)
     *
     * 附加条件，自定义条件语句和绑定参数：
     * $add = array(
     *     'add_where' => "AND (subject LIKE :subject OR contents LIKE :contents)"
     *     'add_binds' => array(
     *         'subject'  => '%aaa%',
     *         'contents' => '%bbb%'
     *     )
     * );
     * $this->getDataList(array('table' => '__B__news'), array('cid' => 5), array(), $add);
     *
     * LIKE 查询，与上面效果类似，但会用 AND 连接：
     * $where = array(
     *     'subject like'  => '%aaa%',
     *     'contents like' => '%bbb%'
     * );
     * ...AND subject LIKE '%aaa%' AND contents LIKE '%bbb%'...
     *
     * 附加 SQL：
     * $add = array(
     *     'add_sql' => "INNER JOIN __B__dept ON __B__user.dept_id = __B__dept.dept_id"
     * );
     *
     * 返回总数和数据集但不分页：
     * $this->getDataList(array('db' => 2, 'table' => '__B__task', 'total' => 1));
     *
     * 分页：
     * $this->getDataList(array('db' => 2, 'table' => '__B__task', 'total' => 1), array(),
     *                    array('page_size' => 10, 'page' => 2));
     *
     * 多条件组合，支持：'>', '<', '=', '>=', '<=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'
     * $where = array(
     *     // 默认用 = 连接
     *     'dept_id'        => 1,
     *     'status <>'      => 3,
     *     'age >'          => 18,
     *     // 操作符大小写均可
     *     'intro Like'     => '%linux%',
     *     // 字段与操作符用键的第一个空格为分隔符，BETWEEN 的值必须是 2 个元素的数组
     *     'time between'   => array(1281979000, 1281979999)
     * );
     *
     * @param  array $param 基础参数
     * @param  array $where 绑定条件参数集
     * @param  array $pages 分页参数
     * @param  array $add   附加 SQL 、查询语句和绑定参数，危险参数（注意注入）
     * @return array        返回数据数组：array(total, page_size, page, list) 或只返回 list
     */
    public function getDataList($param = array(), $where = array(), $pages = array(), $add = array())
    {
        $ret = array(
            'total' => isset($pages['total']) ? get_id($pages['total']) : 0,
            'page_size' => isset($pages['page_size']) ? get_id($pages['page_size']) : 0,
            'page' => isset($pages['page']) ? get_id($pages['page']) : 1,
            'list' => array(),
        );

        if (empty($param['table'])) {
            return $ret;
        }

        // 基础参数
        empty($param['field']) && $param['field'] = '*';
        empty($param['db']) && $param['db'] = 0;
        $where = get_where($where);

        // 整合条件
        $where['where'] = 'WHERE ' . $where['where'] . (empty($add['add_where']) ? '' : ' ' . $add['add_where']);
        // 绑定参数
        !empty($add['add_binds']) && is_array($add['add_binds']) && $where['binds'] += $add['add_binds'];
        // 排序
        $param['order_by'] = empty($param['order_by']) ? '' : 'ORDER BY ' . $param['order_by'];
        // 附加 SQL
        $add['add_sql'] = empty($add['add_sql']) ? '' : $add['add_sql'];

        // 标准 SQL
        $from = "{$param['table']} {$add['add_sql']} {$where['where']}";
        $sql = "SELECT {$param['field']} FROM {$from} {$param['order_by']}";

        // 总数
        if (!empty($param['total']) && empty($ret['total'])) {
            $ret['total'] = $this->db($param['db'])->first("SELECT COUNT(*) FROM {$from}", $where['binds']);
        }

        // $ret['page_size'] > 0 分页记录数量，$ret['page'] == 0 && $ret['page_size'] > 0 取前多少条记录
        if ($ret['page_size']) {
            // 数据库类型
            $dbtype = I('f.DB.' . $param['db'] . '.dbtype', I('f.Filter', 'mysql'));
            // 页码
            $ret['page'] = min(ceil($ret['total'] / $ret['page_size']), $ret['page']);
            $ret['page'] > 0 || $ret['page'] = 1;

            // 根据数据库类型生成分页
            switch ($dbtype) {
                case 'mssql':
                    // mssql 2005+
                    if ($ret['page'] > 1) {
                        $sql
                            = 'SELECT *
                                FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY T_TMP) T_RN
                                      FROM (SELECT TOP ' . ($ret['page_size'] * $ret['page']) .
                                      " {$param['field']}, T_TMP = 0
                                            FROM {$from} {$param['order_by']}) T_OK) T_TT
                                WHERE T_TT.T_RN > " . ($ret['page_size'] * ($ret['page'] - 1));
                    } else {
                        $sql = "SELECT TOP {$ret['page_size']} {$param['field']} FROM {$from} {$param['order_by']}";
                    }

                    break;
                case 'oracle':
                    if ($param['order_by']) {
                        if ($ret['page'] > 1) {
                            $sql
                                = "SELECT {$param['field']}
                                    FROM (SELECT T_BB.*, ROWNUM T_R
                                          FROM (SELECT {$param['field']}
                                                FROM {$from} {$param['order_by']}) T_BB
                                          WHERE ROWNUM <= " . ($ret['page_size'] * $ret['page']) . ') T_OK
                                    WHERE T_R > ' . ($ret['page_size'] * ($ret['page'] - 1));
                        } else {
                            $sql
                                = "SELECT *
                                    FROM (SELECT {$param['field']}
                                          FROM {$from} {$param['order_by']}) T_OK
                                    WHERE ROWNUM <= " . $ret['page_size'];
                        }
                    } else {
                        if ($ret['page'] > 1) {
                            $sql
                                = "SELECT {$param['field']}
                                    FROM (SELECT {$param['field']}, ROWNUM T_R
                                          FROM {$from}
                                          WHERE ROWNUM <= " . ($ret['page_size'] * $ret['page']) . ') T_OK
                                    WHERE T_R > ' . ($ret['page_size'] * ($ret['page'] - 1));
                        } else {
                            $sql
                                = "SELECT {$param['field']}
                                    FROM {$from}
                                    WHERE ROWNUM <= " . $ret['page_size'];
                        }
                    }

                    break;
                default:
                    // mysql 5+
                    $sql .= $ret['page'] > 1 ?
                        ' LIMIT ' . ($ret['page'] - 1) * $ret['page_size'] . ', ' . $ret['page_size'] :
                        ' LIMIT ' . $ret['page_size'];

                    break;
            }
        }

        // 从数据库获得
        if (empty($param['one'])) {
            $ret['list'] = $this->db($param['db'])->Q($sql, $where['binds']);
        } else {
            $ret['list'] = $this->db($param['db'])->one($sql, $where['binds']);
        }

        return empty($param['total']) ? $ret['list'] : $ret;
    }
}

/**
 * 视图表现层 (V).
 *
 * V 视图，即 v 目录下的文件命名与 C 控制器派生类及方法对应，如：user_list.php 对应 class user extends C { function _list() {} }
 *
 * @param array $d 视图数据集，在视图文件中可直接使用相应变量，在 C 中可为其赋值
 */
class V
{
    public $d = array();

    public function __construct($d = array())
    {
        // 接收 C 传递的 $this->d
        !empty($d) && is_array($d) && $this->d = $d;
    }

    /**
     * V 的 view 方法，为 C 返回文件内容，最终显示页面.
     *
     * @param  string $file      视图文件名
     * @param  array  $vars      为视图传值
     * @param  bool   $is_return 0 直接显示，1 返回视图结果
     * @return mixed             0 直接显示视图页或 404，1 返回视图内容或返回 null
     */
    public function view($file = '', $vars = array(), $is_return = false)
    {
        /* PS:
        XSS 处理：$this->d 例外，方便视图页面直接调用 HTML 代码类缓存或模板。
        这部分是程序赋值，可根据情况从源头控制，自行 mk_html 或 remove_xss
        接收外部传值请采用 I() 获取和过滤数据
        */

        // 合并传值
        $vars = !empty($vars) && is_array($vars) ? $vars + $this->d : $this->d;

        // 为视图文件转换变量，数字下标加 d_ 前缀
        extract($vars, EXTR_PREFIX_INVALID, 'd');

        if (is_file($file = V . str_replace(DIR, DS, $file) . '.php')) {
            ob_start();
            include $file;
            if (!empty($is_return)) {
                $buffer = ob_get_contents();
                @ob_end_clean();

                return $buffer;
            }
            ob_end_flush();
        } else {
            if (!empty($is_return)) {
                return null;
            }
            FF::Msg('404', 404);
        }

        return null;
    }
}

/**
 * 控制器逻辑层 (C).
 *
 * V($file)                               调用视图 V::view() 方法显示结果。
 * F($file)                               file_get_contents 方法，读取文件内容
 *
 * C 派生类及类文件以小写命名，方法前加 _前缀，并尽量简洁，避免大小写在不同系统中的差异，地址栏传递 a c 也更短。
 * 示例：class user extends C { function _list() {} }
 *
 * 数据交互：
 * 实际操作中只需要关心 C 的下列变量，C 将自动收集和分配 V 和 M 的变量
 * 在 C 中有四种方式传值给视图使用（$this->v 为视图数据数组）：
 * $this->d['key'] = $val;                视图中可以直接使用 $key
 *                                        数字类下标使用时加 d_ 前缀：$this->d[123] = $val; 视图调用：$d_123 = $val;
 * $this->abc = $val;                     C 类中未定义的变量，视图中也可直接使用 $abc
 * $this->setValue('name', 'value');      视图中可直接使用 $name 值为 'value'
 * $this->setArray(array());              视图中可直接使用 array 的 key 为变量名的变量
 */
class C extends FF
{
    public function __construct()
    {
    }

    /**
     * 视图对象赋值
     *
     * @param string $name  名称
     * @param string $value 值
     */
    public function setValue($name, $value = null)
    {
        $this->d[$name] = $value;
    }

    /**
     * 模板数组赋值
     *
     * @param array $array 数组数据
     */
    public function setArray($array)
    {
        !empty($array) && is_array($array) && $this->d = $array + $this->d;
    }

    /**
     * 调用 View V 方法处理数据并显示视图.
     *
     * @param  string $file      视图文件名
     * @param  array  $vars      为视图传值
     * @param  bool   $is_return 0 直接显示，1 返回视图结果
     * @return mixed             0 直接显示视图页或 404，1 返回视图内容或返回 null
     */
    public function V($file = null, $vars = array(), $is_return = false)
    {
        // 实例化视图
        $v = new V($this->d);

        // 调用视图 V 的 view 方法，返回内容或直接显示
        return $v->view($file, $vars, $is_return);
    }
}
