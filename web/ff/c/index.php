<?php
/**
 * 主页
 *
 * @author Fufu, 2014-06-25
 */

defined('FF') or die('404');

class index extends C
{
    public function __construct()
    {
        parent::__construct();
    }

    public function _index()
    {
        $this->d['title'] = WEB_NAME;

        $this->V('index');
    }

    // 加密字符串
    public function _get__xor()
    {
        if ($_GET['ff'] == mk_date('1d')) {
            echo '<div style="width:820px;margin:0 auto;display:block;">';
            $_POST['str'] && debug_pre(get_xor($_POST['str']));
            echo '<form method="post" action="/index/get__xor/ff/' . $_GET['ff'] . '/">' .
                 '<textarea rows="3" cols="100" name="str" style="width:820px;margin:0 0 15px;">' .
                 '</textarea><br><input type="submit" value="加密字符串"></form></div>';
        } else {
            FF::Msg('404', 404);
        }
    }
}
