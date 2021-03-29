<?php
namespace App\Custom;

trait iCommon
{
    /**
     * 创建文件夹
     */
    public function mkDir($dir)
    {
        //如果文件所在的目录也不存在，则创建
        if(!is_dir($dir))
        {
            mkdir($dir,0777,true);
        }
    }
    /**
     * 创建文件
     */
    public function mkFile($path)
    {
        //先确定文件不存在
        if(!is_file($path))
        {
            //获取文件所在的目录
            $dir = dirname($path);

            //如果文件所在的目录也不存在，则创建
            if(!is_dir($dir))
            {
                mkdir($dir,0777,true);
            }

            //以追加的形式创建一个文件

            $file = fopen($path,'w');

            fclose($file);
        }
    }
}