<?php
namespace App\Helper;
class ParseHelper{
    const NOTE = ';';//注释
    /**
     * 解析ini文件
     */
    public static function parseIni($file_path){
        $fn = fopen($file_path,"r");
        $arr = [];
        $key = '';//记录water
        while(! feof($fn))  {
            $row = fgets($fn);
            //空行或者注释行
            $row = trim($row,"\r\n");

            if(empty($row) || substr($row,0,1)===self::NOTE){
                continue;
            }
            //标题
            $flag = substr($row,0,8);
            if($flag === '[watcher' || $flag === '[circus]'){
                $key = substr($row,1,-1);
                $arr[trim($key)] = [];
                continue;
            }
            list($k,$v) = explode('=',$row);
            $arr[trim($key)] = array_merge($arr[trim($key)],[trim($k) => trim($v)]);
        }
        return $arr;
    }
}