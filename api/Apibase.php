<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 所有API的基类
 */
class Apibase extends CI_Controller
{

    public $model;
    public $server = NULL;
    public $loginData = [];

    function __construct()
    {
        parent::__construct();
    }

    /**
     * 统一API参数检验方法
     * 调用示例 check_param(array('money' => array('required', 'integer', 'greater_than_equal_to[1]', 'less_than_equal_to[200]')));
     * @param   array $arr
     * @return  boolean
     */
    public function check_param($arr, $data = array(), $method = 'get')
    {
        /**
         * 设置要验证的请求数据
         */
        if (!empty($arr)) {
            $key_arr = array();
            $rule_arr = array();
            $tmp_arr = [];
            foreach ($arr as $key => $value) {
                $tmp_arr[$key] = array_shift($value);
                $temp_arr = explode(",", $key);
                if (!is_array($value)) {
                    $value = explode("|", $value);
                }
                $key_arr = array_merge($key_arr, $temp_arr);
                if (!empty($temp_arr)) {
                    foreach ($temp_arr as $temp_value) {
                        if (!empty($rule_arr[$temp_value])) {
                            $rule_arr[$temp_value] = array_merge($rule_arr[$temp_value], $value);
                        } else {
                            $rule_arr[$temp_value] = $value;
                        }
                    }
                }
            }
            $key_arr = array_unique($key_arr);
            if (!empty($rule_arr)) {
                foreach ($rule_arr as $rule_key => $rule_value) {
                    $rule_arr[$rule_key] = array_unique($rule_value);
                }
            }
        }
        if ($method === 'post' || $method === 'POST') {
            $request_data = $this->input->post($key_arr);
        } else {
            $request_data = $this->input->get($key_arr);
        }
        if ('get_post' == $method) {
            $request_data = [];
            foreach ($key_arr as $one_key) {
                $request_data[$one_key] = $this->input->get_post($one_key);
            }
        }
        $this->form_validation->set_data($request_data);
        /**
         * 设置验证规则
         */
        if (!empty($rule_arr)) {
            foreach ($rule_arr as $rule_key => $rule_value) {
                $this->form_validation->set_rules($rule_key, '', $rule_value, array('required' => '%s 不能为空;'
                , 'numeric' => '%s 必须是数字;'
                , 'integer' => '%s 必须是数字;'
                , 'regex_match' => '%s 格式有误;'
                , 'greater_than' => '%s 有误;'
                , 'max_length' => '%s 超过长度;'
                , 'min_length' => '%s 长度不够;'
                ));
            }
        }
        /**
         * 开始验证
         */
        if (!$this->form_validation->run()) {
            //验证失败处理逻辑
            $errmsg = validation_errors(' ', ' ');
            if (!empty($tmp_arr)) {
                foreach ($tmp_arr as $arr_key => $arr_value) {
                    if ($arr_value) {
                        $errmsg = str_replace($arr_key, $tmp_arr[$arr_key], $errmsg);
                    }
                }
            }
            $this->returnError($errmsg . "请检查是否正确", 400);
            return FALSE;
        }
        return $request_data;
    }

    /**
     * api返回数据格式
     * @param type $data
     * @param type $msg
     * @param type $code
     */
    public function returnData($data = [], $msg = '', $code = 200)
    {
        echo json_encode(['code' => $code . '', 'msg' => $msg, 'data' => $data]);
        exit;
    }

    /**
     * 获取数据失败提示2(上一个方法提供数据给Android有时候会返回异常数据)
     * @param type $msg
     * @param type $code
     */
    public function returnError($msg = '', $code = 400)
    {
        echo json_encode(['code' => $code . '', 'msg' => $msg, 'data' => []]);
        exit;
    }

    /**
     * 输入表名 获取所有字段
     */
    public function getCols($table)
    {
        $rescolumns = $this->db->query("SHOW FULL COLUMNS FROM {$table}");
        $row = $rescolumns->result_array();
        $select = [];
        foreach ($row as $i => $v) {
            $select[] = "{$table}.{$v['Field']} as '{$table}.{$v['Field']}'";
        }
        return $select;
    }

    /**
     * 统一获取数据列表，子类不要复写
     */
    public function info()
    {
        $select = $this->input->get_post('select');
        $filter = $this->input->get_post('filter');
        $order = $this->input->get_post('order');
        $info = $this->model->info($select, $filter, $order);
        $info = $this->afterInfo($info);
        $this->returnData($info);
    }

    public function afterInfo($info)
    {
        return $info;
    }

    public function grid()
    {
        $select = $this->input->get_post('select'); //查询字段
        $page_size = $this->input->get_post('limit'); //每页显示多少条
        $page = $this->input->get_post('page'); //第几页
        $filter = $this->input->get_post('filter'); //查询条件
        $order = $this->input->get_post('order'); //排序
        $grid = $this->model->grid($select, $filter, $page, $page_size, $sidx, $sord, $order);
        $this->returnData($grid);
    }

    public function f7()
    {
        $select = $this->input->get_post('select'); //查询字段
        $filter = $this->input->get_post('filter'); //查询条件
        $order = $this->input->get_post('order'); //排序
        $grid = $this->model->f7($select, $filter, $order);
        $this->returnData($grid);
    }

    /**
     * 获取列表数据
     * @param type $postData
     * @param type $isCount
     * @return type
     */
    public function getGrid($postData, $isCount)
    {
        $select = empty($postData['select']) ? NULL : $postData['select'];
        $page_size = empty($postData['limit']) ? 10000 : $postData['limit'];
        $page = empty($postData['page']) ? 1 : $postData['page'];
        $sidx = empty($postData['sidx']) ? NULL : $postData['sidx'];
        $sord = empty($postData['sord']) ? NULL : $postData['sord'];
        $filter = empty($postData['filter']) ? NULL : $postData['filter'];
        $order = empty($postData['order']) ? NULL : $postData['order'];
        $grid = $this->model->grid($select, $filter, $page, $page_size, $sidx, $sord, $order, $isCount);
        return $grid;
    }

    /**
     * 获取随机数
     */
    public function get_num($pre)
    {
        return $pre . date('ymds') . rand(100, 999);
    }

    public function _export($objPHPExcel, $colModel, $grid)
    {
        $table = $this->model->_table;
        for ($i = 0, $t = 0; $i < count($colModel); $i++) {
            $label = $colModel[$i]['label'];
            $name = $colModel[$i]['name'];
            $hidden = empty($colModel[$i]['hidden']) ? FALSE : $colModel[$i]['hidden'];
            $key = empty($colModel[$i]['key']) ? FALSE : $colModel[$i]['key'];
            $sorttype = empty($colModel[$i]['sorttype']) ? 'string' : $colModel[$i]['sorttype'];
            $width = empty($colModel[$i]['width']) ? 15 : $colModel[$i]['width'] / 8;
            $isEdit = empty($colModel[$i]['editable']) ? FALSE : $colModel[$i]['editable'];
            if ($isEdit && !empty($name) && strstr($name, $table . ".") == $name) {
                $isEdit = TRUE;
            } else {
                $isEdit = FALSE;
            }
            if ($hidden && !$key) {
                continue;
            }
            if ($key) {
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($t, 1, $label)->getStyleByColumnAndRow($t, 1)->getFont()->setBold(true)->getColor()->setRGB('FF0000');
            } else if ($isEdit) {
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($t, 1, $label)->getStyleByColumnAndRow($t, 1)->getFont()->setBold(true)->getColor()->setRGB('00FF00');
            } else {
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($t, 1, $label)->getStyleByColumnAndRow($t, 1)->getFont()->setBold(true);
            }
            $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(20);
            $objPHPExcel->getActiveSheet()->getColumnDimensionByColumn($t)->setWidth($width);
            for ($j = 0; $j < count($grid); $j++) {
                $item = $grid[$j];
                $value = $item[$name];
                if ($sorttype === 'image' && !empty($value)) {
                    $url = $this->saveImage("statics/uploads/export_excel_images/", $value);
                    if (!$url || !isImage($url)) {
                        $objPHPExcel->getActiveSheet()->setCellValueExplicitByColumnAndRow($t, $j + 2, $value, PHPExcel_Cell_DataType::TYPE_STRING);
                        continue;
                    }
                    $img = new PHPExcel_Worksheet_Drawing();
                    $img->setName($name);
                    $img->setDescription($name);
                    $img->setPath($url);
                    $img->setWidth(40); //写入图片宽度
                    $img->setHeight(40); //写入图片高度
                    $img->setOffsetX(1); //写入图片在指定格中的X坐标值
                    $img->setOffsetY(1); //写入图片在指定格中的Y坐标值
                    $img->setRotation(1); //设置旋转角度
                    $img->getShadow()->setVisible(true);
                    $img->getShadow()->setDirection(50);
                    $columnLetter = PHPExcel_Cell::stringFromColumnIndex($t);
                    $coordinate = $columnLetter . ($j + 2);
                    $img->setCoordinates($coordinate); //设置图片所在表格位置
                    $img->setWorksheet($objPHPExcel->getActiveSheet()); //把图片写到当前的表格中
                } else if ($sorttype === 'datetime' && !empty($value)) {
                    $value = date("Y-m-d H:m:s", $value);
                    $objPHPExcel->getActiveSheet()->setCellValueExplicitByColumnAndRow($t, $j + 2, $value, PHPExcel_Cell_DataType::TYPE_STRING);
                } else if ($sorttype === 'number') {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicitByColumnAndRow($t, $j + 2, $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                } else if ($sorttype === 'checkbox') {
                    $value = $colModel[$i]['checkbox'][$value];
                    $objPHPExcel->getActiveSheet()->setCellValueExplicitByColumnAndRow($t, $j + 2, $value, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicitByColumnAndRow($t, $j + 2, $value, PHPExcel_Cell_DataType::TYPE_STRING);
                }
            }
            $t++;
        }
    }

    /**
     * 获取关注人
     */
    public function getRelationStaff()
    {
        $uid = $this->loginData['id'];
        $where = "1=1 and find_in_set({$uid},relation_staff_id)";
        $this->load->model('htm/htm_task_staff');
        $staffContractIds = $this->htm_task_staff->get_all('distinct(contract_id)', $where);
        if (empty($staffContractIds)) {
            $this->returnData2();
        }
        foreach ($staffContractIds as $v) {
            $contracsId[] = $v['contract_id'];
        }
        $contracsId = implode(',', $contracsId);
        return $contracsId;
    }

    /**
     * 获取百度地图坐标
     * @param $data
     * @return mixed
     */
    public function getBaidu($data)
    {
        $res = getBaiduAddress($data['address']);
        if ($res['code'] != 200) {
            writelog(['msg' => "{$data['address']}地址错误", 'data' => $res]);
            $this->returnError("{$data['address']}地址错误");
        }
        $result = json_decode($res['result'], true);
        if ($result['status'] != 0) {
            writelog(['msg' => "{$data['address']}地址错误", 'data' => $result]);
            $this->returnError("{$data['address']}地址错误");
        }
        $data['location_lng'] = $result['result']['location']['lng'];
        $data['location_lat'] = $result['result']['location']['lat'];
        return $data;
    }

}
