# FF PHP MVC 微框架

自用轻量级框架, 适用自由开发的小型项目, 目前经手的项目基本都用他(今年会更一版)  
性能好, 兼容性好(PHP 5.2 ~ 7.2)  
SESSION, COOKIES, PDO, 大量自定义函数可选用  

支持 URL 常见写法:

- `/test/save/data/123/nothing//data1/2/` (正常伪静态写法, nothing 取值为空字符串)
- `/index.php/test/save/data/123/` (通用写法；采用伪静态时 IIS 不允许这样写, 应该去掉 /index.php；Apache 能自动容错)
- `/?c=test&a=save&data=123` (c 和 a 是保留字, 只能传控制器类名和方法名)
- `/index.php?c=test&a=save&data=123`
- `/test/save/data/123/?other=abc&add=ok&data=ok` (? 传值优先级更高, $_GET['data'] == 'ok';)
- `/test/save/data/123?other=abc&add=ok`
- `/test/save/nothing//?other=abc&add=ok`
- `/test/save/nothing/?other=abc&add=ok`
- `/test/save/nothing?other=abc&add=ok`
- `/test/save/?data=123&other=abc&nothing=&add=ok`

```
if (!-e $request_filename) {
    rewrite ^(/?)(app[0-9])/(.*)$ /$2/index.php/$3 last;
}
```

支持控制器方式接收 PathInfo 参数, 如访问: `/news/view/123/more/xxx`

控制器方法可接收到 123 作为 ID, 参数个数不限(会产生相应无意义的 $_GET 项, 请忽略): 

```
class news extends C
{
    function _view($news_id, $more)
    {
        $news_id == 123;
        $more == 'more';
        $_GET['123'] == 'more';
        $_GET['xxx'] == '';
    }
}
```

## 目录和文件

- `l` Lib, Class, 自定义类库代码及功能函数定义集
- `m` Model, 模型类库代码及系统公共函数定义集
- `v` View, 表现层, 前端文件及 JS、CSS 等, 置于 www 目录下
- `c` Controller, 逻辑控制, 流程处理代码文件夹
- `d` Data, Cache, 临时数据、缓存文件、Session 等存放文件夹, 需要读写权限
- `d` Attach, 附件文件, 用户 CSS 文件等, 需要读写权限, 置于 www 目录下

**index.php 为入口文件, 置于 www 目录下**

## 框架分离

框架文件与项目文件(www 目录下的文件)可以放在同一文件夹, 也可分别放到不同的文件夹, 当文件夹不同时, 只需要修改这几处的加载路径: 

`www/index.php`  
修改框架入口文件路径, 如: include('../ff/global.php');  
框架文件在上级文件夹的 ff 文件夹中.

`ff/global.php`  
修改框架环境配置文件中 WEB 的定义, 如: define('WEB', dirname(ROOT) . DS . 'www' . DS);  
项目文件在上级文件夹的 www 文件夹中.

    http://ff.com/app1/test/save/data/123 
    WEB_ROOT == '/app1/' 独立一套程序, 与 app2 不相关(但需要注意 Cookies 同名问题)
    http://ff.com/app2/test/save/data/123 
    WEB_ROOT == '/app2/'

**当框架文件与项目文件放在一起时, 目录非常清晰, 两个 d 目录共用.**

## 使用入门

- `www/index.php`     入口文件, 暴露在 www 目录下(网站 root 目录)
- `ff/global.php`     全局公共配置文件, 预处理环境及自定义函数加载, 路径和数据库配置等
- `ff/m/FF.php`       框架文件, 路由分发, 类库加载, 数据连接, 输入控制等
- `ff/m/common.php`   框架运行环境初始化, 错误级别、网页编码、时区、SESSION、必须函数的定义等
- `ff/m/mPDO.php`     (可选)数据库操作
- `ff/c/index.php`    控制器默认主页
- `ff/l/function.php` (可选)常用自定义函数
- `www/v/index.php`   (可选)视图默认主页

### Hello Word!

```php
<?php
// 文件: c/index.php
defined('FF') or die('404');

class index extends C
{
    function __construct()
    {
        parent::__construct();
    }

    function _index()
    {
        echo 'Hello Word!';
    }
}
```

### 常用操作

```php
<?php
// 获取输入参数 支持过滤和默认值(详见: common.php, function I(...))
I('g.'); I('p.'); I('i.'); I('r.'); I('s.'); I('c.'); I('d.'); I('a.'); I('pp.'); I('ss.'); I('gg.');
// 分别代表取值：
$_GET; $_POST; 'php://input'; $_REQUEST; $_SESSION; $_COOKIE; 外部数据来源; 自动; PATH_INFO; $_SERVER; $GLOBALS;

// 获取 id 参数，自动判断 REQUEST_METHOD（$_POST > 'php://input' > $_GET）通过 get_id() 函数处理后返回值，无值时返回 0
I('id', 0, 'get_id');
// 获取 $_POST['name']，通过 trim() 和 htmlspecialchars() 函数处理后返回值，强制转为字符串，默认为 ''
I('p.name/s', '', 'trim,htmlspecialchars');
// 返回完整 $_GET 并且经过全局配置中的默认过滤
I('g.'); 
// 返回完整 $_GET 默认值为空数组，并且不过滤
I('g.', array(), null);
// 不过滤返回，缺省值 123
I('name', '123');
I('name', '123', null);
// 字符串下标为函数名，多个过滤以 , 分隔，按先后顺序处理：intval({$config['FilterStr'][1]}(trim(name)));
I('name', '123', 'trim,1,intval');
I('name', '123', array('trim,1', 'intval'));
I('name', '123', array('trim', 1, 'intval'));
// 数字下标，直接取配置中的过滤参数：$config['FilterStr'][0]
I('name', '123', 0);
// $config['FilterStr'][1]
I('name', '123', '1');
// 无效函数，会返回默认值，如果是数组则每个值都是默认值
I('name', '123', '01x');
// 支持多维数组取值，过滤数组键值或得到复杂表单值时适用：$data[0][0]['name'][1];
I('d.0.0.name.1', '', 0, $data);
// 取 global.php 中 $config 设置项
I('f.CharSet');

// 加载类库 L 方法, l/Upload.php
$options = array(
    'out_dir'   => ATTACH . 'photo',
    'out_name'  => mk_filename(),
    'max_size'  => '8388608',
    'field'     => 'photo',
    'allow_ext' => array('gif', 'jpg', 'png')
);
$photo = $this->L('Upload', $options)->save();
if ($photo['error'] == 0) {
    echo 'ok';
}

// 提交或采集
$html = $this->L('HttpCI')->mkRequest($api_url, $data, 'post');

// 加载模型 M 方法, m/mClient.php
$client_info = $this->M('mClient')->getClient($client_id);

// WebService
$wsdl = $this->WS(SMS_API);

// 选择多条记录
$mssql_data = $this->db(0)->Q("SELECT TOP 2    FROM __B__logs");
$mysql_data = $this->db(1)->Q("SELECT    FROM __B__logs LIMIT 2");
// 选择一条记录(db 下标默认为 0)
$one = $this->db()->one($sql);
// 选择单个字段值
$haha = $this->db(4)->first("SELECT haha FROM __B__test WHERE id = {$id}");
// 默认支持预处理语句查询, 参考: get_where(), 支持 LIKE 等
$where = get_where(array('id' => 1));
$haha = $this->db(4)->first("SELECT haha FROM __B__test WHERE {$where['where']}", $where['binds']);
$haha = $this->db(4)->first("SELECT haha FROM __B__test WHERE id = ?", 1);
// 插入
$id = $this->db(4)->I("__B__test", array('test' => $test, 'VIP' => rand(0,9)));
// 更新
$table = "__B__test";
$data = array('test' => 'update', 'vip' => 10);
$where = "id = 1";
$this->db()->U($table, $data, $where);
// 删除
$this->db()->D($table, $where);

// 框架包含通用表记录查询方法: FF->M::getDataList($param, $where, $pages, $add), 更多参见类库描述
$this->getDataList(array('table' => "__B__user"), array(), array('page_size' => 100));

// 为 v 目录下的视图文件传递变量, 视图可以直接使用 $title;
$this->d['title'] = 'string';
// 调用 v 目录下的视图文件, 显示页面 v/page.php
$this->V('page');
```

## 规范(PSR-2, OOP, 兼顾 PHP 原生及 C 风格)

##### 1、文件: 

- 纯小写字母命名, 如: `userfile.php`
- `v` 目录文件以 `c` 目录控制器的类和方法 `{$ctrl_name}{$_action}.php` 对应命名, 如: `login_in.php`
- `l` 目录类文件名与类名一致, 标准驼峰模式, 如:   
    ```
    class UploadFile
    {
        function chkFileExt() {}
    }
    ```
特殊: 

- `C` 控制器派生类, 即 c 目录下的类库及类文件以小写命名, 方法前加 _前缀, 并尽量简洁, 避免大小写在不同系统中的差异, 便于地址栏传递 a c 及参数.如:   
    ```
    class user extends C
    {
        function _list() {}
    }
    ```
- `M` 模型派生类, 即 m 目录下自定义功能模型类库及类文件采用 m 前缀命名, 避免与 c 中的类名重复且保留类的驼峰模式, 数据库驱动类根据语言特点变通.如:   
    ```
    class mUser extends M
    {
        function setValue() {}
    }
    ```

##### 2、变量: 

- 变量用纯小写字母, 以_连接, 如: `$news_id`
- 特殊变量: `__B__` 为表名前缀, 如:   
    `$this->db(1)->I("__B__table", "user, vip", "'fufu', 1");`
- 单字母变量只能在循环中临时变量或用于常用几个既定变量:  
    - `$c == class 动作` == C 控制器文件
    - `$a == function 操作` == C::function() 控制器方法
    - `$d = id`
    - `$p == page`
    - `$w == Search Word`
- 类中私有变量以_开头, 全局变量只用两个: `$config[]` 或 `$GLOBALS[]`
- 类中变量可按需要加上 `c_` `v_` 前缀以表示使用范围
- `true` `false` `null` 值都采用小写

##### 3、常量: 

纯大写, 以_连接, 单字母常量为重要特殊常量(详见: global.php)

```
define('DS', DIRECTORY_SEPARATOR)
defined('FF') or exit();
```

##### 4、路由: 

- 普通: `/index.php?c=ctrl_class&a=ctrl_class_method&d=id&p=page&w=Search`
- PathInfo 模式: `/index.php/ctrl_class/ctrl_class_method/d/id/p/page/w/Search`
- 混合模式: `/index.php/ctrl_class/ctrl_class_method/d/id/?p=page&w=Search`
- 伪静态模式(默认): 去除 PathInfo 模式中的 /index.php

支持控制器方式接收 PathInfo 参数, 如访问:   
`/index.php/news/view/123`  
控制器方法可接收到 123 作为 ID:   
```
class news extends C 
{
    function _view($news_id) 
    {
        $news_id == 123;
        $_GET['123'] == I('g.news_id') == '';
    } 
}
```

##### 5、函数: 

纯小写命名, 多单词以_连接, 正常情况下以动词+名词, 如: `get_ip()`, `chk_username()`

##### 6、类: 

标准驼峰命名, 单词首字母大写, 无连接符, 变量定义禁用 var, 必须以 public protected private 来定义, 尽量少用 public 变量

##### 7、方法: 

标准驼峰命名, 首单词小写, 特殊单字线方法大写, 如:   

```
function doAction()
```

方法中的变量与普通变量命名一致, 私有方法以 _ 开头, 如:   

```
private function _doSomething()
```

##### 8、空格: 

- 运算符前后必须加空格
- if for 等语法关键字后加空格, 如: `if (!$a) {}`
- 多参数间加空格, 如: `get_info($user, $id);`
- . 连接符前后加空格, 如: `$a = $b . '123';`
- 函数名后不能加空格, 如: `strlen($str);`
- 纯 PHP 代码文件结尾不加 ?> 并在最后空出一行.

##### 9、括号: 

- 双引号中引用变量加花括号, 如: `$user = "user_{$id}"`
- SQL 语句统一用双引号, 如: `$sql = "SELECT FROM __B__user WHERE user_id = '{$id}'"`  
    注: 为了兼容 MySQL 和 MSSQL, 普通 SQL 语句中字段名和表名不加 `` 或 [], SQL 关键词大写
- if 等条件后必须补齐花括号, 如:  
    ```
    if (true) {
        msg('ok');
    }
    ```
- 类、方法、函数花括号独占一行顶格.
- 除 SQL 语句外, 能用单引号则用单引号.