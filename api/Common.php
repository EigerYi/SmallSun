<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/controllers/api/Apibase.php';

class Common extends Apibase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function uploadImg()
    {
        $param = $_POST;
        if (empty($param['base64'])) {
            $this->returnError('请上传64流');
        }
        if (empty($param['name'])) {
            $param['name'] = 'xx.png';
        }
        if (empty($param['dir'])) {
            $param['dir'] = 'image';
        }
        //获取图片后缀
        $file_ext = strrchr($param['name'], '.');
        //保存文件夹路径
        $cur_path = $param['dir'] . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR;
        $savePath = 'public' . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $cur_path;
        if (!is_dir($savePath)) {
            $mkres = @mkdir($savePath, 0777, TRUE);
        }
        //文件名字
        $file_name = date('YmdHis') . rand(100, 999) . $file_ext;
        //获取绝对路径
        $file_path = dirname($_SERVER['SCRIPT_FILENAME']) . DIRECTORY_SEPARATOR . $savePath . $file_name;
        if (!$this->base64_to_img($param['base64'], $file_path)) {
            writelog(["上传失败" => $file_path]);
        }
        $http_url = 'http://' . $_SERVER['SERVER_NAME'] . DIRECTORY_SEPARATOR . $savePath . $file_name;
        $http_url = str_replace("\\", "/", $http_url);
        $res = array("url" => $http_url, "real_path" => $file_path, "file_ext" => $file_ext);
        $this->returnData($res);
    }

    /**
     * 将图片编码写入图片文件
     * @param type $base64_string
     * @param type $output_file
     * @return type
     */
    function base64_to_img($base64_string, $output_file)
    {
        $ifp = fopen($output_file, "wb");
        fwrite($ifp, base64_decode($base64_string));
        fclose($ifp);
        return ($output_file);
    }
}
