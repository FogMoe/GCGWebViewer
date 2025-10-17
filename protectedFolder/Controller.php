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

    // 种族中文名称到位掩码的映射表
    private $raceMap = [
        '人类' => 0x1,
        '魔法师族' => 0x2,
        '魔法师' => 0x2,
        '天使族' => 0x4,
        '天使' => 0x4,
        '恶魔族' => 0x8,
        '恶魔' => 0x8,
        '死灵' => 0x10,
        '机械' => 0x20,
        '水族' => 0x40,
        '水' => 0x40,
        '炎族' => 0x80,
        '炎' => 0x80,
        '岩石族' => 0x100,
        '岩石' => 0x100,
        '鸟类' => 0x200,
        '鸟' => 0x200,
        '植物族' => 0x400,
        '植物' => 0x400,
        '节肢类' => 0x800,
        '节肢' => 0x800,
        '极光' => 0x1000,
        '龙族' => 0x2000,
        '龙' => 0x2000,
        '哺乳类' => 0x4000,
        '哺乳' => 0x4000,
        '兽战士族' => 0x8000,
        '兽战士' => 0x8000,
        '兽' => 0x8000,
        '爬行类' => 0x10000,
        '爬行' => 0x10000,
        '鱼族' => 0x20000,
        '鱼' => 0x20000,
        '软体类' => 0x40000,
        '软体' => 0x40000,
        '真菌类' => 0x80000,
        '真菌' => 0x80000,
        '念动力族' => 0x100000,
        '念动力' => 0x100000,
        '幻神兽族' => 0x200000,
        '幻神兽' => 0x200000,
        '创造神族' => 0x400000,
        '创造神' => 0x400000,
        '幻龙族' => 0x800000,
        '幻龙' => 0x800000,
        '电子界族' => 0x1000000,
        '电子界' => 0x1000000,
        '幻想魔族' => 0x2000000,
        '幻想魔' => 0x2000000,
    ];

    // 类型中文名称到位掩码的映射表
    private $typeMap = [
        '单位卡' => 0x1,
        '单位' => 0x1,
        '支援卡' => 0x2,
        '支援' => 0x2,
        '战术卡' => 0x4,
        '战术' => 0x4,
        '通常怪兽' => 0x10,
        '通常' => 0x10,
        '部队' => 0x20,
        '大型' => 0x40,
        '进化' => 0x80,
        '陷阱怪兽' => 0x100,
        '陷阱' => 0x100,
        '灵魂' => 0x200,
        '同盟' => 0x400,
        '二重' => 0x800,
        '调整' => 0x1000,
        '同调' => 0x2000,
        '临时' => 0x4000,
        '快速' => 0x10000,
        '设施' => 0x20000,
        '强化' => 0x40000,
        '区域' => 0x80000,
        '反制' => 0x100000,
        '翻转' => 0x200000,
        '卡通' => 0x400000,
        '超量' => 0x800000,
        '灵摆' => 0x1000000,
        '特殊召唤' => 0x2000000,
        '特召' => 0x2000000,
        '连接' => 0x4000000,
    ];

    // 属性中文名称到位掩码的映射表
    private $attributeMap = [
        '军团' => 0x01,
        '舰队' => 0x02,
        '空间站' => 0x04,
        '星港' => 0x08,
        '指挥官' => 0x10,
        '暗' => 0x20,
        '神' => 0x40,
    ];

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

        // 根据 effect 添加条件（混合搜索：种族、数值、描述）
        if ($effect !== null) {
            $keywords = array_filter(array_map('trim', explode(' ', $effect)));
            if (!empty($keywords)) {
                $effectConditions = [];
                foreach ($keywords as $index => $keyword) {
                    // 为每个关键词创建多种匹配条件（OR关系）
                    $keywordConditions = [];

                    // 1. 检查是否是"补给X"或"X补给"格式（X为数字）
                    if (preg_match('/^补给(\d+)$/u', $keyword, $matches) || preg_match('/^(\d+)补给$/u', $keyword, $matches)) {
                        $levelValue = intval($matches[1]);
                        $levelParam = ':effect_level_supply' . $index;
                        $keywordConditions[] = "datas.level = " . $levelParam;
                        $params[$levelParam] = $levelValue;
                    }

                    // 2. 检查是否是种族名称
                    if (isset($this->raceMap[$keyword])) {
                        $raceMask = $this->raceMap[$keyword];
                        $raceParam = ':effect_race_mask' . $index;
                        $keywordConditions[] = "(datas.race & " . $raceParam . ") = " . $raceParam;
                        $params[$raceParam] = $raceMask;
                    }

                    // 3. 检查是否是类型名称
                    if (isset($this->typeMap[$keyword])) {
                        $typeMask = $this->typeMap[$keyword];
                        $typeParam = ':effect_type_mask' . $index;
                        $keywordConditions[] = "(datas.type & " . $typeParam . ") = " . $typeParam;
                        $params[$typeParam] = $typeMask;
                    }

                    // 4. 检查是否是属性名称
                    if (isset($this->attributeMap[$keyword])) {
                        $attrMask = $this->attributeMap[$keyword];
                        $attrParam = ':effect_attr_mask' . $index;
                        $keywordConditions[] = "(datas.attribute & " . $attrParam . ") = " . $attrParam;
                        $params[$attrParam] = $attrMask;
                    }

                    // 5. 如果是数字，精确匹配等级、攻击力、防御力
                    if (is_numeric($keyword)) {
                        $numValue = intval($keyword);

                        $levelParam = ':effect_level' . $index;
                        $keywordConditions[] = "datas.level = " . $levelParam;
                        $params[$levelParam] = $numValue;

                        $atkParam = ':effect_atk' . $index;
                        $keywordConditions[] = "datas.atk = " . $atkParam;
                        $params[$atkParam] = $numValue;

                        $defParam = ':effect_def' . $index;
                        $keywordConditions[] = "datas.def = " . $defParam;
                        $params[$defParam] = $numValue;
                    }

                    // 6. 描述字段模糊搜索（总是包含）
                    $descParam = ':effect_desc' . $index;
                    $keywordConditions[] = "texts.desc LIKE " . $descParam;
                    $params[$descParam] = '%' . $keyword . '%';

                    // 每个关键词的多种匹配条件用 OR 连接（满足任一即可）
                    if (!empty($keywordConditions)) {
                        $effectConditions[] = '(' . implode(' OR ', $keywordConditions) . ')';
                    }
                }
                // 多个关键词使用 AND 连接（必须同时满足）
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
