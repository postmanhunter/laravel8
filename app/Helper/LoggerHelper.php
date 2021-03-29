<?php
namespace App\Helper;
use Illuminate\Support\Facades\URL;
trait LoggerHelper{
    public function logger($str,$filePath=''){
        substr($filePath,0,1)==='/' && $filePath = substr($filePath,1);
        substr($filePath,-1,1) ==='/' && $filePath .= 'log'; 
        $pathArray = explode('/',$filePath);
        $count = count($pathArray);
        $lastfilePath = $pathArray[$count-1];
        $fileName = $lastfilePath.date('Y_m_d').'.log';
        $dirArray = array_slice($pathArray,0,$count-1);
        if(count($dirArray)<=0){
            $curDirPath = storage_path().'/logs';
        }else{
            $curDirPath = storage_path().'/logs/'.implode('/',$dirArray);
        }
        
        $curFilePath = $curDirPath.'/'.$fileName;
        //文件夹不存在则创建
        if(!is_dir($curDirPath))
        {
            mkdir($curDirPath,0777,true);
        }
        $url = Url::full();
        $timeArray = explode(" ",microtime());
        $time = date('Y-m-d H:i:s').' '.$timeArray[0];
        $str = "[{$time}] ".$str.' @ '.$url;
        file_put_contents($curFilePath,$str.PHP_EOL,FILE_APPEND);
    }
}