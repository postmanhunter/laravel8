<?php

namespace App\Console\Commands\Rabbitmq;

use Illuminate\Console\Command;

class ExecuteConsume extends Command
{
    protected $signature = 'ExecuteConsume {--path=} {--action=} {--class=} {--force} ';

    protected $description = '执行一个消费者，例：php artisan ExecuteConsume --path=Sample --action=up';

    public function handle()
    {
        $path = $this->option('path');
        $action = $this->option('action');
        $class_name = $this->option('class');
        if ($path && $action && $class_name) {
            $full_path = app_path("MqConsume/" . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path));
            try {
                if(!is_dir($full_path)){
                    throw new \Exception('错误的路径'.$full_path);
                }
                $this->info("开始执行{$full_path}");
               
                $class = '\App\MqConsume\\' . ucfirst(str_replace('/', '\\', $path)) . '\\' . $class_name;
            
                $obj = new $class();
                if ($obj->$action()) {
                    $this->info("{$file}执行成功");
                } else {
                    $this->info("{$file}执行失败" . $obj->getLastErrorMsg());
                }
                
                $this->info("全部执行完毕");
            } catch (\Exception $exception) {
                report($exception);
                $this->info('执行失败' . $exception->getMessage());
            }
        }
    }
}
