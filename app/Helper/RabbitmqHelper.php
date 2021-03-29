<?php
namespace App\Helper;
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;
use App\Helper\LoggerHelper;

class RabbitmqHelper
{
    use LoggerHelper;
    protected static $instance = null;

    public static function getInstance()
    {
        if (is_null(RabbitmqHelper::$instance)) {
            RabbitmqHelper::$instance = new RabbitmqHelper(config('rabbitmq.default'));
        }

        return RabbitmqHelper::$instance;
    }
    /**
     * 获取所有队列的等待消息的消息以及当前队列的消费者个数
     */
    function getQueues()
    {
        $rabbitmqConfig = config('rabbitmq.default');
        $rabbitmqHost = $rabbitmqConfig['host'];
        $rabbitmqApiport = $rabbitmqConfig['api_port'];
        $rabbitmqVhost = $rabbitmqConfig['vhost'];
        $rabbitmqUsername = $rabbitmqConfig['username'];
        $rabbitmqPassword = $rabbitmqConfig['password'];
        $url = "http://{$rabbitmqHost}:{$rabbitmqApiport}/api/queues{$rabbitmqVhost}";
        $cmd = "curl -s -u {$rabbitmqUsername}:{$rabbitmqPassword} {$url}";
        $ds = json_decode(`$cmd`, true);
        if (!is_array($ds)) {
            return [];
        }
        $ret = [];
        foreach ($ds as $q) {
            if ($q['state'] != 'running') {
                continue;
            }
            $ret[$q['name']] = [
                'consumers' => $q['consumers'],
                'messages_ready' => $q['messages_ready'],
                'messages_unacknowledged' => $q['messages_unacknowledged']
            ];
        }
        return $ret;
    }
    /**
     * 增减所有队列的消费者进程
     * $is_scale true->增加  false->减少
     * $circusWatcherName  circus的watch的名字
     * $consumers 当前消费者的个数
     * $numprocesses 设置该watcher的消费进程个数
     * $circusctlPath circusctl 命令的路径
     * $count 增加或者减少的消费者个数
     */
    function changeConsumers($is_scale, $circusWatcherName,$circusctlPath, $count = 1)
    {
        try {
            switch ($is_scale) {
                case true: // 增加
                    $cmd = "{$circusctlPath} incr {$circusWatcherName} {$count}";
                    $out = trim(`$cmd`);
                    $this->logger("exe order ------{$cmd}",'rabbitmq/scale');
                    exec($out);
                    break;

                case false: // 减少
                    $cmd = "{$circusctlPath} decr {$circusWatcherName} {$count}";
                    $out = trim(`$cmd`);
                    $this->logger("exe order ------{$cmd}",'rabbitmq/scale');
                    exec($out);
                    break;
            }
        } catch (Exception $e) {
            $this->logger($e->getMessage(),'scale/rabbitmq');
        }
    }

    private  $connection;
    private  $channel;
    private  $config;

    /**
     * RabbitmqHelper constructor.
     * 建立连接
     * @param $config
     */
    function __construct(array $config)
    {
        $this->connection = new AMQPStreamConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost']);
        $this->channel = $this->connection->channel();
        $this->config = $config;
    }
    /**
     * 第一个参数为消费者消费的队列
     * 第二个参数为消费者执行处理业务的地方
     * 第三个参数为先回执在处理业务还是处理完业务在回执，默认ture,先回执在处理业务
     * 第四个参数队列保活的mysql,redis的socket连接
     */
    function listen($queue_name, $fn, $exe_order=true, $fn_keepalive = null)
    {
        if (!is_callable($fn)) {
            return;
        }
        
        $this->queue_declare($queue_name);

        $this->pop($queue_name, function ($msg) use ($fn, $exe_order, $fn_keepalive) {
            if($exe_order){
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                $body = unserialize($msg->body);
                
                if (isset($body['__keep_alive__']) && isset($body['__keep_alive__']) == '1') {
                    if (is_callable($fn_keepalive)) {
                        $fn_keepalive();
                    }
                } else {
                    $fn($body);
                }
            }else{
                
                $body = unserialize($msg->body);
                
                if (isset($body['__keep_alive__']) && isset($body['__keep_alive__']) == '1') {
                    if (is_callable($fn_keepalive)) {
                        $fn_keepalive();
                    }
                } else {
                    $fn($body);
                }
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            }
            
            
        }); 
    }

    /**
     * 消息入队列
     * @param mixed  $msg 消息
     * @param string $queue 队列名称
     */
    function push($msg, $queue)
    {
        $msg = serialize($msg);
        $this->queue_declare($queue);
        $message = new AMQPMessage($msg, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $this->channel->basic_publish($message, '', $queue);
    }

    /**
     * 消息出队列
     * @param string $queue 队列名称
     * @param        $callback 处理消息的回调函数
     */
    function pop($queue, $callback)
    {
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($queue, '', false, false, false, false, $callback);

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
       
    }

    /**
     * 声明队列，不存在会去创建
     */
    public function queue_declare($queue)
    {
        $this->channel->queue_declare($queue, false, true, false, false);
    }

    /**
     * 关闭连接
     */
    function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
