<?php

namespace App\Console\Commands\Rabbitmq;

use Illuminate\Console\Command;
use App\Helper\RabbitmqHelper;
use App\Helper\LockHelper;
use App\Helper\LoggerHelper;
use Exception;
use App\Helper\ParseHelper;

class IncOrDecProcess extends Command
{
    use LoggerHelper;
    const MESSAGE_NUMS = 10;//规定每个消费者进程消费的个数，超过需要增加消费者
    /**
     * The name and signature of the console command.
     *  需要定时任务每隔一段时间去sheng
     * @var string
     */
    protected $signature = 'inc_or_dec_process:nums';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '增加或者减少某个队列消费者进程';

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
            $this->logger("trigger workerScale.","rabbitmq/scale");
            $lock = new LockHelper();
            $lock->fileLock('worker_scale', function (){
                try{
                    $appPath = app_path();
                    $c = ParseHelper::parseIni($appPath . '/../scripts/circus.ini');
                    $rl = RabbitmqHelper::getInstance();
                    while (true) {
                        $queues = $rl->getQueues();
                        $this->logger("workerScale loaded queues.", "rabbitmq/scale");
                        foreach ($c as $watch_name => $item) {
                            //circus设置
                            if($watch_name === 'circus'){
                                $this->logger("circus set", "rabbitmq/scale");
                                continue;
                            }
                            //没有设置queue
                            if (!isset($queues[$item['queue_name']])) {
                                $this->logger("workerScale unfound queue----{$item['queue_name']}", "rabbitmq/scale");
                                continue;
                            }
                            $watch_name = explode(':', $watch_name)[1];
                            // 未开自动切片
                            if (empty($item['autoscale'])) {
                                $this->logger("autoscale unopen---{$watch_name}", "rabbitmq/scale");
                                continue;
                            }
                            $qinfo = $queues[$item['queue_name']];
                            

                            //根据当前消息积攒的数量计算需要消费者数据量
                            $aidConsumer = ceil(($qinfo['messages_ready'] + $qinfo['messages_unacknowledged'])/self::MESSAGE_NUMS);
                            $aidConsumer = $aidConsumer == 0 ? 1 : $aidConsumer;
                            //如果需要的消费者数量大于设定的最大值，那就让消费者为最大值，
                            if($aidConsumer>$item['maxprocesses']){
                                $count = $item['maxprocesses']-$qinfo['consumers'];
                            }else{
                                $count = $aidConsumer - $qinfo['consumers'];
                            }
                            // echo $count;die;
                            if($count==0 || ($count<0 && !empty($qinfo['messages_ready']) && !empty($qinfo['messages_unacknowledged']) && empty($item['force_del']))){//不需要增减
                                continue;
                            }
                            $this->logger("workerScale done:", "rabbitmq/scale");

                            //进行增减量
                            $rl->changeConsumers($count>0 , $watch_name, $item['circusctlpath'], abs($count));
                        }
                        sleep(10);// 10s 一轮
                    }
                }catch(Exception $e){
                    $this->logger("workerScale fail:{$e->getMessage()}----{$e->getLine()}", "rabbitmq/scale");
                }
            });
        }catch(Exception $e){
            $this->logger("workerScale fail:{$e->getMessage()}----{$e->getLine()}", "rabbitmq/scale");
        }
        
    }
    /**
     * 保持队列
     */
}
