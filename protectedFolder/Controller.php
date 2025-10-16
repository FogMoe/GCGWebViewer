<?php
require_once 'Card.php';
require_once 'UpdateManager.php';
// 生产环境：关闭错误显示，只记录到日志
// 开发环境：可以临时改为 1 来调试
error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);

class Controller {

    private $db;

    // 构造函数，用于初始化数据库连接
    public function __construct() {
        $dbPath = 'protectedFolder/cards.cdb';

        // 自动检查更新（有1小时间隔保护，不会频繁请求）
        // 如果数据库不存在会自动下载，否则按间隔检查更新
        try {
            $updateManager = new UpdateManager();
            $updateManager->checkForUpdates();
        } catch (Exception $e) {
            // 更新检查失败不影响主功能，只记录日志
            error_log("Update check failed: " . $e->getMessage());
        }

        // 添加错误处理，防止数据库损坏或不存在时暴露敏感信息
        try {
            $this->db = new SQLite3($dbPath);
        } catch (Exception $e) {
            // 生产环境：记录错误但不显示给用户
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("数据库连接失败，请联系管理员");
        }
    }
    // 析构函数
    public function __destruct() {
        // 关闭数据库连接
        $this->db->close();
    }

    //根据id获取卡牌
    public function getCardById($id) {
        // 准备一个参数化的 SQL 查询
        $stmt = $this->db->prepare('SELECT texts.id, name, desc, ot, alias, setcode, type, atk, def, level, race, attribute, category FROM texts JOIN datas ON texts.id = datas.id WHERE texts.id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    
        $result = $stmt->execute();
    
        if ($result === false) {
            throw new Exception("Failed to execute SQL statement: " . $this->db->lastErrorMsg());
        }
    
        $cards = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cards[] = $row;
        }
        return $cards;  // 返回所有查询到的卡片数据
    }

    //根据Name获取卡牌
    public function getCardByName($name) {
        // 准备一个参数化的 SQL 查询
        $stmt = $this->db->prepare('SELECT texts.id, name, desc, ot, alias, setcode, type, atk, def, level, race, attribute, category FROM texts JOIN datas ON texts.id = datas.id WHERE texts.name LIKE :name');
        
        // 绑定参数时使用 LIKE 语法，'%' 是通配符，匹配任意字符序列
        $stmt->bindValue(':name', '%' . $name . '%', SQLITE3_TEXT);
        
        $result = $stmt->execute();
        
        if ($result === false) {
            throw new Exception("Failed to execute SQL statement: " . $this->db->lastErrorMsg());
        }
        
        $cards = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cards[] = $row;
        }
        return $cards;  // 返回所有查询到的卡片数据
    }


    //获取某页卡
    public function getCardsByPage($page, $pageSize) {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT texts.id, name, desc, ot, alias, setcode, type, atk, def, level, race, attribute, category FROM texts JOIN datas ON texts.id = datas.id LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
    
        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement: " . $this->db->lastErrorMsg());
        }
    
        // 在SQLite3中绑定参数的方式
        $stmt->bindValue(':limit', intval($pageSize), SQLITE3_INTEGER);
        $stmt->bindValue(':offset', intval($offset), SQLITE3_INTEGER);
    
        $result = $stmt->execute();
    
        if ($result === false) {
            throw new Exception("Failed to execute SQL statement: " . $this->db->lastErrorMsg());
        }
    
        $cards = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cards[] = $row;
        }
        return $cards;  // 返回所有查询到的卡片数据
    }


    //根据id或名字查找卡片并分页
    public function getCardsPageByIdOrName($id = null, $name = null, $effect = null, $page = 1, $pageSize = 10) {
        // 初始化查询基础部分
        $sql = "SELECT texts.id, name, desc, ot, alias, setcode, type, atk, def, level, race, attribute, category FROM texts JOIN datas ON texts.id = datas.id";

        // 用于存储查询条件的数组
        $whereConditions = [];
        $params = [];

        // ID/名称条件组（OR关系）
        $idNameConditions = [];

        // 根据 id 添加条件
        if ($id !== null) {
            if (is_numeric($id)){
                $idNameConditions[] = "texts.id = :id";
                $params[':id'] = $id;
            }
        }

        // 根据 name 添加条件
        if ($name !== null) {
            $idNameConditions[] = "texts.name LIKE :name";
            $params[':name'] = '%' . $name . '%';
        }

        // 如果有 ID 或名称条件，将它们组合（OR关系）
        if (!empty($idNameConditions)) {
            $whereConditions[] = '(' . implode(' OR ', $idNameConditions) . ')';
        }

        // 根据 effect 添加条件（支持空格分隔多个关键词）
        if ($effect !== null) {
            $keywords = array_filter(array_map('trim', explode(' ', $effect)));
            if (!empty($keywords)) {
                $effectConditions = [];
                foreach ($keywords as $index => $keyword) {
                    $paramName = ':effect' . $index;
                    $effectConditions[] = "texts.desc LIKE " . $paramName;
                    $params[$paramName] = '%' . $keyword . '%';
                }
                // 多个效果关键词使用 AND 连接（必须同时包含）
                if (!empty($effectConditions)) {
                    $whereConditions[] = '(' . implode(' AND ', $effectConditions) . ')';
                }
            }
        }

        // 如果存在搜索条件，将它们用 AND 连接
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // 添加分页条件
        $offset = ($page - 1) * $pageSize;
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $pageSize;
        $params[':offset'] = $offset;

        // 准备 SQL 语句
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement: " . $this->db->lastErrorMsg());
        }

        // 绑定所有参数
        foreach ($params as $key => &$value) {
            $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }

        // 执行查询
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Failed to execute SQL statement: " . $this->db->lastErrorMsg());
        }

        // 收集结果
        $cards = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cards[] = $row;
        }

        return $cards;  // 返回所有查询到的卡片数据
    }

    // 获取卡片总数的方法
    public function getTotalCardCount() {
        $sql = "SELECT COUNT(*) as count FROM datas";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);  // 使用关联数组模式
        return $row['count'];  // 返回计数结果
    }

    // 根据搜索条件获取卡片总数
    public function getCardCountByIdOrName($id = null, $name = null, $effect = null) {
        // 初始化查询基础部分
        $sql = "SELECT COUNT(*) as count FROM texts JOIN datas ON texts.id = datas.id";

        // 用于存储查询条件的数组
        $whereConditions = [];
        $params = [];

        // ID/名称条件组（OR关系）
        $idNameConditions = [];

        // 根据 id 添加条件
        if ($id !== null) {
            if (is_numeric($id)){
                $idNameConditions[] = "texts.id = :id";
                $params[':id'] = $id;
            }
        }

        // 根据 name 添加条件
        if ($name !== null) {
            $idNameConditions[] = "texts.name LIKE :name";
            $params[':name'] = '%' . $name . '%';
        }

        // 如果有 ID 或名称条件，将它们组合（OR关系）
        if (!empty($idNameConditions)) {
            $whereConditions[] = '(' . implode(' OR ', $idNameConditions) . ')';
        }

        // 根据 effect 添加条件（支持空格分隔多个关键词）
        if ($effect !== null) {
            $keywords = array_filter(array_map('trim', explode(' ', $effect)));
            if (!empty($keywords)) {
                $effectConditions = [];
                foreach ($keywords as $index => $keyword) {
                    $paramName = ':effect' . $index;
                    $effectConditions[] = "texts.desc LIKE " . $paramName;
                    $params[$paramName] = '%' . $keyword . '%';
                }
                // 多个效果关键词使用 AND 连接（必须同时包含）
                if (!empty($effectConditions)) {
                    $whereConditions[] = '(' . implode(' AND ', $effectConditions) . ')';
                }
            }
        }

        // 如果存在搜索条件，将它们用 AND 连接
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // 准备 SQL 语句
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement: " . $this->db->lastErrorMsg());
        }

        // 绑定所有参数
        foreach ($params as $key => &$value) {
            $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }

        // 执行查询
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Failed to execute SQL statement: " . $this->db->lastErrorMsg());
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['count'];  // 返回计数结果
    }
}
?>
