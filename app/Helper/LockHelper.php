<?php
namespace App\Helper;
use App\Helper\LoggerHelper;
use Exception;

class LockHelper{
    use LoggerHelper ;
    /**
     * 文件锁
     */
    public function fileLock($key, $fn)
    {
        try{
            $lock_file = "/tmp/process.lock.{$key}";
            $lock_file_handle = fopen($lock_file, 'w');
            if ($lock_file_handle === false) {
                $str = "read [{$lock_file}] fail";
                $this->logger($str,'lock/file');
                return false;
            }

            if (!flock($lock_file_handle, LOCK_EX + LOCK_NB)) {
                $str = "lock [{$lock_file}] fail";
                $this->logger($str,'lock/file');
                return false;
            }

           
            $fn();
            flock($lock_file_handle, LOCK_UN);
            return true;
        }catch (Exception $e) { 
            $this->logger($e->getMessage(),'lock/file');
        }
        
    }
}