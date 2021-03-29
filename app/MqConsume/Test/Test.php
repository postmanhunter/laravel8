<?php
namespace App\MqConsume\Test;
use App\Helper\RabbitmqHelper;
use App\Models\Test\TestModel;

class Test{
    # php /usr/share/nginx/html/www/test/laravel8/artisan ExecuteConsume --path=Test --action=up --class=Test
    public function up(){
        $queue_name = 'test_model';

        RabbitmqHelper::getInstance()->listen($queue_name,function($id){
            $column1 = 'consume1';
            TestModel::updateOne($id,$column1);
            sleep(50);
            $column2 = 'consume2';
            TestModel::updateOne($id,$column2);
        },false,function(){
            //这里可以重新访问数据库和redis，保活mysql以及redis的socket连接，因为mysql和redis的socket连接默认8小时没有连接，服务端会主动断开客户端的连接
        });
    }
}