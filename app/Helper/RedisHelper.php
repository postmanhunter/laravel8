<?php
namespace App\Helper;
use Redis;
trait RedisHelper{
    public static function getInstance(){
        $host = env('REDIS_HOST');
        $port = env('REDIS_PORT');
        $auth = env('REDIS_PASSWORD');
        $redis = new Redis();
        $redis->connect($host,$port);
        if($auth){
            $redis->auth($auth);
        }
        return $redis;
    }
}