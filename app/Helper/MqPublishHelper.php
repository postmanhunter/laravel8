<?php
namespace App\Helper;
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
class MqPublishHelper{
    private $connect = '';
    private $channel = '';
    public function __construct(){
        $RabbitmqConfig = config('rabbitmq.default');
        $this->connect = new AMQPStreamConnection($RabbitmqConfig['host'],$RabbitmqConfig['port'],$RabbitmqConfig['username'],$RabbitmqConfig['password'],$RabbitmqConfig['vhost']);
        $this->channel = $this->connect->channel();
    }

    public function queue($data,$queue_name){

        #第三个参数为true为队列持久化
        $this->channel->queue_declare($queue_name, false, true, false, false);
        #第二个参数是消息持久化
        $msg = new AMQPMessage(serialize($data),
                                array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
                            );
        #第一个参数要发送的消息的对象;第二个参数指定交换机;第三个参数为routing_key,即交换机怎么找到队列
        $this->channel->basic_publish($msg, '',$queue_name);
        
        return true;
    }
    public function close(){
        $this->channel->close();
        $this->connect->close();
    }
}