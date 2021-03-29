<?php

namespace App\Console\Commands\Rabbitmq;

use Illuminate\Console\Command;
use App\Helper\RabbitmqHelper;
class KeepAlive extends Command
{
    /**
     * The name and signature of the console command.
     *  需要定时任务每隔一段时间去sheng
     * @var string
     */
    protected $signature = 'keepalive:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'keep queue alive';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *  队列保活
     * @return int
     */
    public function handle()
    {
        try{
            $root = base_path();
        
            $c = parse_ini_file($root. '/scripts/circus.ini', true);
            $rabbitmq_lite = RabbitmqHelper::getInstance();
            foreach ($c as $item) {
                if (!isset($item['keepalive']) || $item['keepalive'] != 1) {
                    continue;
                }

                for ($i = 0; $i <= $item['numprocesses']; $i++) {
  
                    $rabbitmq_lite->push(['__keep_alive__' => '1'], $item['queue_name']);
                    
                }
            }
        }catch(\Exception $e){
            echo $e->getMessage();
        }
        
    }
    /**
     * 保持队列
     */
}
