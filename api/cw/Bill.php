<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/controllers/api/Apibase.php';

/**
 * TODO Bill财务接口
 */
class Bill extends Apibase
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('cw/cw_bill', 'model');
    }

    /**
     * 根据月份查询账单
     */
    public function get_month()
    {
        $param = $this->check_param([
            'month' => ['月份', 'max_length[100]'],
            'order' => ['排序', 'max_length[100]'],
        ], [], 'get');
        $this->addMonthBill();
        $month = $this->getBeginEndMonth($param['month']);
        $order = empty($param['order']) ? 'id desc' : $param['order'];
        $list = $this->model->get_all('', "id between {$month[0]} and {$month[1]}", $order);
        foreach ($list as &$v) {
            $v['time'] = date('Y-m-d', $v['id']);
        }
        $select = [
            "sum(goods) as goods",
            "sum(postage) as postage",
            "sum(brush) as brush",
            "sum(activity) as activity",
            "sum(packing) as packing",
            "sum(parts) as parts",
            "sum(remark) as remark",
            "sum(total) as total",
            "sum(sale) as sale",
        ];
        $total = $this->model->get_one($select, "id between {$month[0]} and {$month[1]}");
        $total['update_at'] = '盈利：' . ($total['sale'] - $total['total']);
        $total['time'] = '总计';
        $total['id'] = -1;
        $list = array_merge(['-1' => $total], $list);
        $this->returnData($list);
    }

    /**
     * 新增账单
     */
    public function add_bill()
    {
        $param = $this->check_param([
            'title' => ['标题', 'required', 'max_length[200]'],
            'item_id' => ['类目', 'required', 'integer'],
            'money' => ['金额', 'required', 'numeric', 'max_length[100]'],
            'bill_id' => ['日期', 'required'],
            'content' => ['内容', 'max_length[300]'],
            'pics' => ['图片', 'max_length[2000]'],
        ], [], 'post');
        $this->load->model('cw/cw_item');
        $this->load->model('cw/cw_detail');
        $param['bill_id'] = strtotime($param['bill_id']);
        $param['create_at'] = date('Y-m-d H:i:s');
        $itemInfo = $this->cw_item->get_one('name,col,type', ['iid' => $param['item_id']]);
        if ($itemInfo['type'] == 1) {
            $sql = "update cw_bill set {$itemInfo['col']}={$itemInfo['col']}+{$param['money']},total=total+{$param['money']} where id = {$param['bill_id']}";
        } else {
            $sql = "update cw_bill set {$itemInfo['col']}={$itemInfo['col']}+{$param['money']} where id = {$param['bill_id']}";
        }
        $this->db->trans_start();
        $this->db->query($sql);
        $this->cw_detail->add($param);
        $this->db->trans_complete();
        $this->returnData();
    }

    /**
     * 删除账单
     */
    public function del_bill()
    {
        $param = $this->check_param([
            'id' => ['ID', 'required', 'integer'],
        ], [], 'get');
        $this->load->model('cw/cw_detail');
        $this->load->model('cw/cw_item');
        $info = $this->cw_detail->info('', $param);
        if ($info['type'] == 1) {
            $sql = "update cw_bill set {$info['col']}={$info['col']}-{$info['money']},total=total-{$info['money']} where id = {$info['bill_id']}";
        } else {
            $sql = "update cw_bill set {$info['col']}={$info['col']}-{$info['money']} where id = {$info['bill_id']}";
        }
        $this->db->query($sql);
        $this->cw_detail->del($param);
        $this->returnData();
    }

    /**
     * 获取类目列表
     */
    public function get_item_list()
    {
        $this->load->model('cw/cw_item');
        $list = $this->cw_item->get_all('iid,name', '', 'sort desc');
        $this->returnData($list);
    }

    /**
     * 获取账单流水
     */
    public function get_bill_detail()
    {
        $param = $this->check_param([
            'begin' => ['开始时间', 'max_length[200]'],
            'end' => ['结束时间', 'max_length[200]'],
            'item_id' => ['类目ID', 'integer']
        ], [], 'get');
        $today = date('Y-m-d');
        $searchTime = [
            'begin' => strtotime($today),
            'end' => strtotime($today . ' 23:59:59'),
        ];
        if (!empty($param['begin'])) {
            $searchTime['begin'] = strtotime($param['begin']);
        }
        if (!empty($param['end'])) {
            $searchTime['end'] = strtotime($param['end']);
        }
        $this->load->model('cw/cw_detail');
        $where = "bill_id between {$searchTime['begin']} and {$searchTime['end']}";
        if (!empty($param['item_id'])) {
            $where .= " and item_id = {$param['item_id']}";
        }
        $list = $this->cw_detail->f7('', $where, 'id desc');
        if (is_array($list)) {
            foreach ($list as &$v) {
                $v['time'] = date('Y-m-d', $v['bill_id']);
                $v['pics'] = explode(',', $v['pics']);
            }
        }
        $this->returnData($list);
    }

    /*------------------------------------------------ 私有方法 --------------------------------------------------------------*/

    /**
     * 添加账单
     */
    private function addMonthBill()
    {
        $max = $this->model->get_one('max(id) as max');
        $today = strtotime(date('Y-m-d'));
        if (empty($max['max'])) {
            $max = $today;
            $this->model->add(['id' => $today, 'update_at' => date('Y-m-d H:i:s')]);
        } else {
            $max = $max['max'];
            if ($today > $max) {
                $howDay = ceil(($today - $max) / (24 * 60 * 60));
                $_addData = [];
                for ($i = 1; $i <= $howDay; $i++) {
                    $_addData[] = ['id' => $max + 24 * 60 * 60 * $i, 'update_at' => date('Y-m-d H:i:s')];
                }
                $this->model->add_batch($_addData);
            }
        }
    }

    /**
     * @param $month    月份 字符串 Y-m-d
     * @param int $type 1-时间戳 2-字符串
     * @return array
     */
    private function getBeginEndMonth($month, $type = 1)
    {
        $month = empty($month) ? date('Y-m-d') : $month;
        $begin = date('Y-m-01', strtotime($month));
        $end = date('Y-m-d', strtotime("$begin +1 month -1 day"));
        if ($type === 2) {
            return [$begin, $end];
        }
        $begin = strtotime($begin);
        $end = strtotime($end);
        return [$begin, $end];
    }

}
