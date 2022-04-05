<?php
/**
 * test sqlite crud
 *
 * @author Fufu 2016-08-16
 */

defined('FF') or die('404');

class crud extends C
{
    public function __construct()
    {
        parent::__construct();
    }

    public function _index()
    {
        $test = $this->M('mTest');
        $test->createTable();

        $ids = $test->insertData();
        debug_pre($ids);

        $res = $test->updateData();
        debug_pre($res);

        $res = $test->selectData();
        debug_pre($res);

        $res = $test->deleteData();
        debug_pre($res);

        $res = $test->queryData(8);
        debug_pre($res);
    }
}
