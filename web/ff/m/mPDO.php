<?php
/**
 * DB.PDO 操作模式, 支持 MySQL, MSSQL, SQLite, PGsql, SYBase, Oracle
 *
 * @author Fufu, 2013-07-18
 * @update 2015-03-20 exec 影响行数 -1 表示执行失败
 * @update 2016-07-07 统一采用预处理执行, 避免 gbk 下 chr(0xbf) . chr(0x27) 注入风险
 * @update 2016-07-18 增加事务处理
 * @update 2016-08-18 使用 setAttribute 设置 option, 避免不同类型数据库对特定属性不支持抛出异常
 * @update 2016-10-18 Q(), one() 必定返回 array()
 * @update 2018-06-06 D(), U() 的条件参数绑定优化
 * @update 2018-07-13 优化一些可能出现 Notice 的地方
 * @update 2018-12-28 无法连接数据库时, 不再默认退出
 * @update 2019-06-16 仅同步时间
 */

defined('FF') or die('404');

class mPDO extends M
{
    protected $drive       = 'mPDO';                   // 数据库驱动类型, 当前: PDO 模式
    protected $host        = '127.0.0.1';              // 主机
    protected $port        = 0;                        // 端口, 0 为使用默认端口
    protected $dbtype      = 'mysql';                  // 数据库类型
    protected $dbname      = 'test';                   // 数据库名
    protected $dbuser      = 'root';                   // 登录用户名
    protected $dbpass      = '';                       // 登录密码
    protected $dbfile      = '';                       // SQLite 数据库文件位置
    protected $tbpre       = '';                       // 表名前缀
    protected $charset     = 'utf8';                   // 数据库编码, 'gbk', 'big5', 'utf8', 'utf8mb4'
    protected $lower       = 1;                        // 是否强制字段输出为小写字母
    protected $pconnect    = 0;                        // 是否使用持久连接
    protected $option      = array();                  // 其他参数
    protected $bind_pre    = '?';                      // 预处理语句占位符, 可用 ? 占位, 插入数据必须用 :name
    protected $socket      = '';                       // unix_socket
    protected $dosql       = '';                       // 当前操作的 SQL
    protected $pdo         = null;                     // 当前连接 ID
    protected $sth         = null;                     // PDO 操作实例
    protected $result      = null;                     // 执行结果
    protected $lastid      = 0;                        // 最后插入 ID
    protected $rows        = 0;                        // 影响行数
    protected $trans_times = 0;                        // 事务指令数

    public function __construct($options = array())
    {
        parent::__construct();

        // 附加状态参数
        $commands = array();

        // 处理初始化类时传递的参数
        if ($options && is_array($options)) {
            // 解密字符串
            if ($options['dbxor']) {
                $options['dbname'] = get_xor($options['dbname'], '', 1);
                $options['dbuser'] = get_xor($options['dbuser'], '', 1);
                $options['dbpass'] = get_xor($options['dbpass'], '', 1);
            }
            // 覆盖默认参数
            foreach ($options as $option => $value) {
                $this->{$option} = $value;
            }
        }

        // 数据库类型
        $this->dbtype = strtolower($this->dbtype);
        // 数据库访问端口
        $this->port && is_int($this->port * 1);
        // 初始化字符集
        $set_charset = "SET NAMES {$this->charset}";

        // 连接字符串, 指定 charset
        $dsn = $this->dbtype .
               ':host=' . $this->host . ($this->port ? ';port=' . $this->port : '') .
               ';dbname=' . $this->dbname . ';charset=' . $this->charset;

        // 根据数据库类型, 拼接连接字符串, 设置附加环境参数
        switch ($this->dbtype) {
            case 'mysql':
                // MySQL 使用引用标准标识符
                $commands[] = 'SET SQL_MODE = ANSI_QUOTES';
                $commands[] = $set_charset;
                // unix_socket
                $this->socket && $dsn = 'mysql:unix_socket=' . $this->socket .
                                        ';host=' . $this->host . ';dbname=' . $this->dbname;

                break;
            case 'sybase':
            case 'pgsql':
                $commands[] = $set_charset;
                break;
            case 'mssql':
                $dsn = strpos(PHP_OS, 'WIN') !== false
                    ? 'sqlsrv:server=' . $this->host . ($this->port ? ',' . $this->port : '') .
                      ';database=' . $this->dbname
                    : 'dblib:host=' . $this->host . ($this->port ? ':' . $this->port : '') .
                      ';dbname=' . $this->dbname;
                // 标准引号使用: 标识符可以由双引号分隔, 字符串只能使用由单引号分隔
                $commands[] = 'SET QUOTED_IDENTIFIER ON';
                break;
            case 'oracle':
                $dsn = 'oci:dbname=//' . $this->host . ($this->port ? ':' . $this->port : '') .
                       '/' . $this->dbname . ';charset=' . $this->charset;
                break;
            case 'sqlite':
                $dsn = 'sqlite:' . $this->dbfile;
                $this->dbuser = null;
                $this->dbpass = null;
                break;
            default:
                break;
        }

        // 是否强制列名为小写 y
        $this->lower && ($this->option[PDO::ATTR_CASE] = PDO::CASE_LOWER);

        // 是否使用持久连接, 弃用, 采用静态加载 n
        // $this->pconnect && ($this->option[PDO::ATTR_PERSISTENT] = true);

        // 不转换 NULL 和空字符串 y
        $this->option[PDO::ATTR_ORACLE_NULLS] = PDO::NULL_NATURAL;

        // 禁止取值时将数值转换为字符串 y
        $this->option[PDO::ATTR_STRINGIFY_FETCHES] = false;

        // 优先采用数据库本地预处理而非预处理语句的模拟, MySQL.y, MSSQL.n
        $this->option[PDO::ATTR_EMULATE_PREPARES] = false;

        // 抛出 exceptions 异常 y
        $this->option[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        // 建立连接/*, $this->option*/
        try {
            $this->pdo = new PDO($dsn, $this->dbuser, $this->dbpass);
        } catch (PDOException $e) {
            I('f.DebugSQL') && die('DBServer error: ' . $e->getMessage());
        }

        // 使用 PDO::setAttribute 避免一些非通用的属性导致异常
        foreach ($this->option as $a => $v) {
            try {
                $this->pdo->setAttribute($a, $v);
            } catch (PDOException $error) {
                // 屏蔽异常, 比如 MSSQL 不支持设置 ATTR_EMULATE_PREPARES
            }
        }

        // 执行附加环境参数
        foreach ($commands as $cmd) {
            try {
                $this->pdo->exec($cmd);
            } catch (PDOException $err) {
                // 屏蔽异常, 比如 MSSQL 不支持执行 SET NAMES utf8
            }
        }
    }

    /**
     * PDO::query
     * public function query ($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null) {}
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return object|bool
     */
    public function query($sql, $binds = array(), $types = array())
    {
        // 初始化
        $this->format();

        if (!($this->pdo)) {
            return false;
        }

        try {
            // 预处理语句, 绑定参数
            $this->sth = $this->bind($this->pdo->prepare($sql = $this->mkTable($sql)), $binds, $types);
            // 执行
            $this->result = $this->sth->execute();
            // 执行情况反馈
            $this->response($sql, null, $binds, $types) || ($this->result = false);
        } catch (PDOException $e) {
            $this->response($sql, $e, $binds, $types);

            return false;
        }

        return $this->result;
    }

    /**
     * int PDO::exec ( string $statement )
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return int          返回受影响的行数, -1 出错, >1 行数, 0 无更新
     */
    public function exec($sql, $binds = array(), $types = array())
    {
        $this->rows = 0;

        // 执行 query 相同方法
        $this->query($sql, $binds, $types) && $this->rows = get_id($this->sth->rowCount());
        $this->result === false || $this->result === null && $this->rows = -1;

        return $this->rows;
    }

    /**
     * bool PDO::beginTransaction ( void )
     *
     * @return bool
     */
    public function beginTrans()
    {
        if ($this->pdo && 0 == $this->trans_times) {
            try {
                if ($this->pdo->beginTransaction()) {
                    $this->trans_times++;
                    return true;
                }
            } catch (PDOException $e) {
                $this->response('pdo->beginTransaction()', $e);
            }
        }

        return false;
    }

    /**
     * bool PDO::commit ( void )
     *
     * @return bool
     */
    public function commit()
    {
        if ($this->pdo && 0 < $this->trans_times) {
            try {
                if ($this->pdo->commit()) {
                    $this->trans_times = 0;
                    return true;
                }
            } catch (PDOException $e) {
                $this->response('pdo->commit()', $e);
            }
        }

        return false;
    }

    /**
     * bool PDO::rollBack ( void )
     *
     * @return bool
     */
    public function rollBack()
    {
        if ($this->pdo && 0 < $this->trans_times) {
            try {
                if ($this->pdo->rollBack()) {
                    $this->trans_times = 0;
                    return true;
                }
            } catch (PDOException $e) {
                $this->response('pdo->rollBack()', $e);
            }
        }

        return false;
    }

    /**
     * 从结果集中获取下一行
     * $this->fetch($this->query($sql))
     *
     * $this->db()->query($sql);
     * while ($rs = $this->db()->fetch()) {
     *    print_r($rs);
     * }
     *
     * mixed PDOStatement::fetch ([ int $fetch_style [, int $cursor_orientation = PDO::FETCH_ORI_NEXT ...] )
     * 2: PDO::FETCH_ASSOC: 返回一个索引为结果集列名的数组
     * 3: PDO::FETCH_NUM: 返回一个索引为以0开始的结果集列号的数组
     * 4: PDO::FETCH_BOTH(默认): 返回一个索引为结果集列名和以0开始的列号的数组
     * 5: PDO::FETCH_OBJ: 返回一个属性名对应结果集列名的匿名对象
     *
     * @param int $style 返回的数组风格
     * @return array
     */
    public function fetch($style = PDO::FETCH_ASSOC)
    {
        $ret = array();

        try {
            $ret = $this->sth->fetch($style);
        } catch (PDOException $e) {
            // 调试, 显示错误
            I('f.DebugSQL') && die('ERROR: ' . $e->getMessage() . '<hr>' . mk_html($this->dosql) . '<hr>');
        }

        return $ret ? $ret : array();
    }

    /**
     * 转换结果集为数组
     * $this->fetchAll($this->query($sql))
     *
     * array PDOStatement::fetchAll ([ int $fetch_style [, mixed $fetch_argument [, array $ctor_args = array() ]]] )
     * 2: PDO::FETCH_ASSOC: 返回一个索引为结果集列名的数组
     * 3: PDO::FETCH_NUM: 返回一个索引为以0开始的结果集列号的数组
     * 4: PDO::FETCH_BOTH(默认): 返回一个索引为结果集列名和以0开始的列号的数组
     * 5: PDO::FETCH_OBJ: 返回一个属性名对应结果集列名的匿名对象
     *
     * @param int $style 返回的数组风格
     * @return array
     */
    public function fetchAll($style = PDO::FETCH_ASSOC)
    {
        $ret = array();

        try {
            $ret = $this->sth->fetchAll($style);
            $this->rows = count($ret);
        } catch (PDOException $e) {
            // 调试, 显示错误
            I('f.DebugSQL') && die('ERROR: ' . $e->getMessage() . '<hr>' . mk_html($this->dosql) . '<hr>');
        }

        return $ret;
    }

    /**
     * 执行查询并转换结果集为数组
     * $this->Q($sql)
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @param int    $style 返回的数组风格
     * @return array
     */
    public function Q($sql = '', $binds = array(), $types = array(), $style = PDO::FETCH_ASSOC)
    {
        return $this->query($sql, $binds, $types) ? $this->fetchAll($style) : array();
    }

    /**
     * 执行查询并返回第一条记录到数组
     * $this->one($sql)
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @param int    $style 返回的数组风格
     * @return array
     */
    public function one($sql = '', $binds = array(), $types = array(), $style = PDO::FETCH_ASSOC)
    {
        return $this->query($sql, $binds, $types) ? $this->fetch($style) : array();
    }

    /**
     * 执行查询并返回第一条记录的第一个字段值
     * $this->first($sql)
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return string
     */
    public function first($sql = '', $binds = array(), $types = array())
    {
        $res = $this->one($sql, $binds, $types);
        return reset($res);
    }

    /**
     * 插入数据 INSERT INTO(逐条插入)
     *
     * 支持两种模式, 数据数组 或 字段 + 值(不推荐, 注意过滤字符), 支持插入多条数据, 示例: 
     * $this->db()->I("__B__table", "user, vip", "'fufu', 1");
     * $this->db()->I("__B__table", array('user' => 'fufu', 'vip' => 1));
     * $this->db()->I("__B__table", array(array('user' => 'fufu', 'vip' => 1), array('user' => 'test', 'vip' => 9)));
     *
     * @param string $table 表名
     * @param mixed  $datas 参数数据集 / 字段字符串
     * @param mixed  $types 绑定参数对应的类型, 建议省略 / 值字符串
     * @return mixed        返回 lastInsertId 值或值数组
     */
    public function I($table, $datas, $types = '')
    {
        // 初始化
        $this->format();
        $last_id = $insert = array();

        // 数组模式或兼容模式
        if (is_array($datas)) {
            // 转为多条数据插入模式
            isset($datas[0]) || $datas = array($datas);
            (!is_array($types) || !isset($types[0])) && $types = array($types);
            // 字段集
            if ($fields = array_keys($datas[0])) {
                $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES (:" . implode(',:', $fields) . ")";
                foreach ($datas as $k => $data) {
                    $type = isset($types[$k]) ? $types[$k] : array();
                    // 逐条插入
                    if ($this->exec($sql, $data, $type)) {
                        $last_id[] = $this->lastid = $this->pdo->lastInsertId();
                        $this->rows += 1;
                    }
                }
            }
        } else {
            // 兼容模式, 单记录插入, 上层需要注入防护!!!
            if ($this->exec("INSERT INTO {$table} ({$datas}) VALUES ({$types})")) {
                $last_id[] = $this->lastid = $this->pdo->lastInsertId();
                $this->rows = 1;
            }
        }

        // 返回最后插入的主键 ID 或 ID 集合
        return count($last_id) > 1 ? $last_id : $last_id[0];
    }

    /**
     * 更新数据 UPDATE, 单条
     * $datas 为数组时, WHERE 条件不能用问号占位符, 且 $binds 需要是数组, 并且会优先以 $datas 的数据绑定
     *
     * 支持两种模式, 数据数组 或 字符串, 示例: 
     * $this->db()->U("__B__table", "user = 'fufu', vip = 1", "user_id = 1");
     * $this->db()->U("__B__table", array('user' => 'fufu', 'vip' => 1), "user_id = 1");
     * $this->db()->U("__B__table", array('vip' => 8, 'user' => 'test'), "vip < :vip");
     * $this->db()->U("__B__table", array('vip' => 8, 'user' => 'test'), "vip < 8");
     * $this->db()->U("__B__table", array('vip' => 8, 'user' => 'test'), "vip = :vip", array('vip' => 9)); // vip == 8
     * $this->db()->U("__B__table", array('vip' => 8, 'user' => 'test'), "vip = :vipbind", array('vipbind' => 9)); // ok
     *
     * @param string $table 表名
     * @param string $datas 数据数组或 SET 更新 SQL 串
     * @param string $where 更新条件
     * @param mixed  $binds 绑定参数($datas 优先)
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return int          返回更新影响的行数
     */
    public function U($table, $datas, $where = '1 = 1', $binds = array(), $types = array())
    {
        // 初始化
        $this->format();
        $ret = 0;

        // 数组模式或兼容模式
        if (is_array($datas)) {
            // 字段集
            if ($fields = array_keys($datas)) {
                $set = '';
                $binds = is_array($binds) ? ($datas + $binds) : $datas;
                // 组织预处理语句
                foreach ($fields as $d) {
                    $set .= ",{$d}=:{$d}";
                }
                $set = ltrim($set, ',');
                $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
                $ret = $this->exec($sql, $binds, $types);
            }
        } else {
            // 兼容模式, 上层需要注入防护!!!
            $ret = $this->exec("UPDATE {$table} SET {$datas} WHERE {$where}", $binds, $types);
        }

        return $ret;
    }

    /**
     * 执行删除 DELETE
     *
     * $this->db()->D("__B__table", "vip = ?", 9)
     * $this->db()->D("__B__table", "vip = :vip", array('vip' => 9))
     *
     * @param string $table 表名
     * @param string $where 删除条件
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return int          返回受影响的记录行数
     */
    public function D($table, $where = '1 = 1', $binds = array(), $types = array())
    {
        return $this->exec("DELETE FROM {$table} WHERE {$where}", $binds, $types);
    }

    /**
     * SQL 执行结果及显示调试
     *
     * @param string $sql   已执行的 SQL 语句
     * @param object $err   PDOException
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return bool         返回执行是否成功
     */
    public function response($sql = '', $err = null, $binds = array(), $types = array())
    {
        // 记录当前操作的 SQL
        $this->dosql = $sql . ' __ Binds:' . print_r($binds, 1) . ' __ Types:' . print_r($types, 1);
        $ok = false;
        $errmsg = '';

        // 是否有抛出异常
        if ($err && ($errmsg = $err->getMessage())) {
            // 异常
        } else {
            // 执行结果是否有错误
            ($ok = $this->pdo->errorCode() === '00000') || $errmsg = implode(', ', $this->error());
        }

        // 写入调试 SQL 或显示错误
        if (I('f.DebugSQL')) {
            $this->sql[] = $this->dosql;
            $ok || die('ERROR: ' . $errmsg . '<hr>' . mk_html($this->dosql) . '<hr>');
        }

        return $ok;
    }

    /**
     * 预处理语句
     *
     * @param object $sth   PDOStatement
     * @param mixed  $binds 绑定参数
     * @param mixed  $types 绑定参数对应的类型, 建议省略
     * @return int          返回执行是否成功
     */
    public function bind($sth, $binds = array(), $types = array())
    {
        if ($binds && is_object($sth) && ($sth instanceof PDOStatement)) {
            // 参数转为数组
            is_array($binds) || $binds = array($binds);
            is_array($types) || $types = array($types);

            // 兼容 :name 和 ? 占位
            $is_name = is_int(key($binds)) ? 0 : 1;

            // $sql = "SELECT * FROM TABLE WHERE test = :abc";
            // $binds = array('abc' = 123);
            // $sql = "SELECT * FROM TABLE WHERE test = ?";
            // $binds = array(123);
            // 插入和更新数据因为需要字段, 必须用 :name
            $i = 0;

            foreach ($binds as $k => $v) {
                // 数组自动序列化
                is_array($v) && $v = serialize($v);

                // 避免一下, 直接不允许这两字符
                // $v = str_replace(array(chr(0xbf), chr(0x27)), '', $v);

                $i++;
                $param = $is_name ? ":{$k}" : $i;

                if ($types && isset($types[$k])) {
                    // 参数给定了类型
                    $sth->bindValue($param, $v, $types[$k]);
                } else {
                    // 根据参数类型显示指定
                    if (is_int($v)) {
                        $type = PDO::PARAM_INT;
                    } elseif (is_bool($v)) {
                        $type = PDO::PARAM_BOOL;
                    } elseif (is_null($v)) {
                        $type = PDO::PARAM_NULL;
                    } else {
                        $type = PDO::PARAM_STR;
                    }
                    $sth->bindValue($param, $v, $type);
                }
            }
        }

        return $sth;
    }

    /**
     * 手工实现预处理语句的模拟
     * 宽字节 GBK PHP5.3.6-:  chr(0xbf) . chr(0x27)
     *
     * @param string $sql   SQL 语句
     * @param mixed  $binds 需要绑定的数据
     * @return int          返回执行是否成功
     */
    public function bindstr($sql, $binds = array())
    {
        if (empty($binds) || empty($this->bind_pre) || strpos($sql, $this->bind_pre) === false) {
            return $sql;
        } elseif (!is_array($binds)) {
            // 转为数组
            $binds = array($binds);
            $bind_count = 1;
        } else {
            // 转为数字下标数组
            $binds = array_values($binds);
            $bind_count = count($binds);
        }

        // 占位符长度
        $len_pre = strlen($this->bind_pre);

        // 处理单引号字符串中的 ? 避免被当标记替换成参数内容, 先替换 $sql 中 '*?*' 单引号中的 ? 为空格
        // 注意 sql 中的字符串必须用单引用括起来
        if ($n = preg_match_all("/'[^']*'/i", $sql, $matches)) {
            // 替换 '123?' 为 '123 '
            $tmp = str_replace($this->bind_pre, str_repeat(' ', $len_pre), $matches[0]);
            $tmp = str_replace($matches[0], $tmp, $sql, $n);
        } else {
            $tmp = $sql;
        }

        // 得到真正需要绑定参数的标记个数, 排除了单引号中的标记符
        $n = preg_match_all('/' . preg_quote($this->bind_pre, '/') . '/i', $tmp, $matches, PREG_OFFSET_CAPTURE);
        // 需要绑定的参数数量异常, 原样返回
        if ($bind_count !== $n) {
            return $sql;
        }

        do {
            $n--;
            // 按每个参数的开始位置替换指定长度
            $sql = substr_replace($sql, $this->quote($binds[$n]), $matches[0][$n][1], $len_pre);
        } while ($n !== 0);

        return $sql;
    }

    /**
     * public string PDO::quote ( string $string [, int $parameter_type = PDO::PARAM_STR ] )
     *
     * @param mixed $data   数据
     * @param int   $in_str 0 数组序列化, 保存入库使用, 1 数组转为 (1,2) 为 IN 使用
     * @return object       返回执行结果
     */
    public function quote($data, $in_str = 0)
    {
        // 根据数据类型决定是否需要引号
        if (is_array($data) || is_object($data)) {
            if ($in_str) {
                $data_quote = array();
                // 处理每个元素
                foreach ($data as $d) {
                    $data_quote[] = $this->quote($d);
                }
                // (1,2) ('a','b')
                $ret = '(' . implode(',', $data_quote) . ')';
            } else {
                $ret = $this->quote(serialize($data));
            }
        } elseif (is_bool($data)) {
            $ret = $data ? 1 : 0;
        } elseif ($data === null) {
            $ret = 'null';
        } elseif (is_int($data) || is_float($data)) {
            $ret = $data;
        } else {
            $ret = $this->pdo->quote($data);
        }

        return $ret;
    }

    /**
     * 处理表名前缀
     *
     * @param string $str 表名或 SQL
     * @return string
     */
    public function mkTable($str = '')
    {
        return str_replace('__B__', $this->tbpre, $str);
    }

    /**
     * 返回当前的错误信息
     *
     * @return array
     */
    public function error()
    {
        return $this->pdo->errorInfo();
    }

    /**
     * 返回最后执行结果, 成功或失败
     *
     * @return bool
     */
    public function result()
    {
        return $this->result;
    }

    /**
     * 返回最后执行的 SQL 语句
     *
     * @return string
     */
    public function dosql()
    {
        return $this->dosql;
    }

    /**
     * 返回最后插入的 ID 号
     *
     * @return int
     */
    public function lastid()
    {
        return $this->lastid;
    }

    /**
     * 返回最后执行操作影响的行数
     *
     * @return int
     */
    public function rows()
    {
        return $this->rows;
    }

    /**
     * 初始化
     *
     */
    public function format()
    {
        // 初始化
        $this->result = null;
        $this->rows = 0;
        $this->lastid = 0;
        $this->sth = null;
    }

    /**
     * 返回当前的连接信息
     *
     * @return array
     */
    public function info()
    {
        return array(
            /*'server'   => $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO),*/
            'client' => $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'driver' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            'version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION)/*,
            'connection' => $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)*/
        );
    }
}
