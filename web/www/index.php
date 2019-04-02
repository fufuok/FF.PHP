<?php
/**
 * FF MVC 框架，入口页面
 *
 * @author Fufu, 2013-07-18
 */

// 加载全局环境预处理文件，绝对文件路径
include('../ff/global.php');

// 加载 PHP 框架
include(M . 'FF.php');

// 开启线程
FF::Run();
