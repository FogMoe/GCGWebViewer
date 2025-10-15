<?php
include 'Controller.php'; // 确保包含了修改后的Controller类
class View{

    private $controller;

    // 构造函数，用于初始化数据库连接
    public function __construct() {$this->controller = new Controller();}

    //转换成可读性更好的数据
    public function toView($cards){
        foreach ($cards as &$card): 
            if($card['atk']===-2):$card['atk']='?';endif;
            if($card['def']===-2):$card['def']='?';endif;

            //如果怪兽星大于99意味着要变成十六进制后取最后2位
            if($card['level']>99):
                $card['level']=(int)dechex($card['level'])%100;
            endif;

            //类型
            $types = [
                0x1       => '单位卡',
                0x2       => '支援卡',
                0x4       => '战术卡',
                0x10      => '通常怪兽',
                0x20      => '部队',
                0x40      => '大型',
                0x80      => '进化',
                0x100     => '陷阱怪兽',
                0x200     => '灵魂',
                0x400     => '同盟',
                0x800     => '二重',
                0x1000    => '调整',
                0x2000    => '同调',
                0x4000    => '临时',
                0x10000   => '快速',
                0x20000   => '设施',
                0x40000   => '强化',
                0x80000   => '区域',
                0x100000  => '反制',
                0x200000  => '翻转',
                0x400000  => '卡通',
                0x800000  => '超量',
                0x1000000 => '灵摆',
                0x2000000 => '特殊召唤',
                0x4000000 => '连接',
            ];
            $result = [];
            foreach ($types as $bit => $name) {
                if (($card['type'] & $bit) == $bit) {
                    $result[] = $name;
                }
            }
            $card['type']=implode("|", $result);


            //种族
            $race = [
                0x1        => '人类 ',
                0x2        => '魔法师族 ',
                0x4        => '天使族 ',
                0x8        => '恶魔族 ',
                0x10       => '死灵 ',
                0x20       => '机械族 ',
                0x40       => '水族 ',
                0x80       => '炎族 ',
                0x100      => '岩石族 ',
                0x200      => '鸟类 ',
                0x400      => '植物族 ',
                0x800      => '节肢类 ',
                0x1000     => '极光 ',
                0x2000     => '龙族 ',
                0x4000     => '哺乳类 ',
                0x8000     => '兽战士族 ',
                0x10000    => '爬行类 ',
                0x20000    => '鱼族 ',
                0x40000    => '软体类 ',
                0x80000    => '真菌类 ',
                0x100000   => '念动力族 ',
                0x200000   => '幻神兽族 ',
                0x400000   => '创造神族 ',
                0x800000   => '幻龙族 ',
                0x1000000  => '电子界族 ',
                0x2000000  => '幻想魔族 ',
            ];
            $result = [];
            foreach ($race as $bit => $name) {
                if (($card['race'] & $bit) == $bit) {
                    $result[] = $name;
                }
            }
            $card['race']=implode(" ", $result);


            //属性attribute
            $attribute = [
                0x01 => '军团',
                0x02 => '舰队',
                0x04 => '空间站',
                0x08 => '星港',
                0x10 => '指挥官',
                0x20 => '暗',
                0x40 => '神'
            ];
            $result = [];
            foreach ($attribute as $bit => $name) {
                if (($card['attribute'] & $bit) == $bit) {
                    $result[] = $name;
                }
            }
            $card['attribute']=implode(", ", $result);



        endforeach;
        unset($card);

        return $cards;
    }

    //根据id获取卡牌
    public function getCardViewById($id) {return $this->toView($this->controller->getCardById($id));}

    //根据name获取卡牌
    public function getCardViewByName($name) {return $this->toView($this->controller->getCardByName($name));}
    
    //获取某页卡
    public function getCardsViewByPage($page, $pageSize) {return $this->toView($this->controller->getCardsByPage($page, $pageSize));}

    public function getCardsPageViewByIdOrName($id = null, $name = null, $page = 1, $pageSize = 10){
        return $this->toView($this->controller->getCardsPageByIdOrName($id, $name, $page, $pageSize));
    }

    // 获取卡片总数的方法
    public function getTotalCardCount() {return $this->controller->getTotalCardCount();}



}
$v=new View();

/*foreach ($v->getCardsPageViewByIdOrName($id = '龙', $name = '龙', $page = 1, $pageSize = 10) as $c) {
    echo $c['name'];
}*/
?>

