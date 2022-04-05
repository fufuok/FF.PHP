<?php
/**
 * test
 *
 * @author Fufu 2016-08-16
 */

defined('FF') or die('404');

class mTest extends M
{
    public function __construct()
    {
        parent::__construct();
    }

    public function createTable($sql)
    {
        $sql = "CREATE TABLE __B__task (id INTEGER NOT NULL PRIMARY KEY, user TEXT,  vip INTEGER);";
        return $this->db()->exec($sql);
    }

    public function insertData()
    {
        $id_one = $this->db()->I("__B__task", "user, vip", "'fufu', 1");
        $id_more = $this->db()->I("__B__task", [['user' => 'fufu', 'vip' => 1], ['user' => 'test', 'vip' => 9]]);
        return [$id_one, $id_more];

    }

    public function updateData()
    {
        $set = ["vip" => 8];
        $where = "id = 2";
        return $this->db()->U("__B__task", $set, $where);
    }

    public function selectData()
    {
        $param = ['table' => "__B__task", 'order_by' => "id DESC"];
        $where = ['user' => 'fufu'];
        return $this->getDataList($param, $where);
    }

    public function deleteData()
    {
        return $this->db()->D("__B__task", "vip = ?", 9);
    }

    public function queryData($vip = 1)
    {
        $sql = "SELECT * FROM __B__task WHERE vip=:vip";
        return $this->db()->Q($sql, ["vip" => $vip]);
    }
}
