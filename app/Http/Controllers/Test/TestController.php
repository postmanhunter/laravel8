<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Apis;
use App\Http\Requests\Test\TestRequest;
use App\Models\Test\TestModel;
use App\Models\Test\OrderModel;
use App\Http\Resources\Paginate;
use App\Helper\RabbitmqHelper;
use App\Helper\LockHelper;
use App\Helper\RsaHelper;

class TestController extends Apis
{
    const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC1mgbM1p0FfIZ2GS7rasqJ1hYy
HNXfVQ5T/zD20j3ioyvRQWD+D/A6EWCcNzm9z15F9yxmXBCg/mfOAaOWtRDgoLdt
8qh2J5f88iQom7MJ7PaB4tHc8LyMe+Z/0oOZp4lFpc0E3LgYOExlyBJoiql35A8j
g4cKbQUYLFEjaxERMwIDAQAB
-----END PUBLIC KEY-----";
    const PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----
MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBALWaBszWnQV8hnYZ
LutqyonWFjIc1d9VDlP/MPbSPeKjK9FBYP4P8DoRYJw3Ob3PXkX3LGZcEKD+Z84B
o5a1EOCgt23yqHYnl/zyJCibswns9oHi0dzwvIx75n/Sg5mniUWlzQTcuBg4TGXI
EmiKqXfkDyODhwptBRgsUSNrEREzAgMBAAECgYB301EHWdiz5Q2n4UgHSCiqOFve
5w3r1eilXe4F/oWdBIOGCHiiSwv8lLjFet8bsjeHPOfMBpVmVVdTI9u4NnMSV0Wu
Lx8Bkpi5aObG7okjMzyeAJh9yIBA101YDFPcD4LPQGFb3FOwDSXwciGgYhWT2ama
pEeOuIE0iM8njr+HWQJBAOg/0Qf4FfAjPUxIJv/NenHV1FHfjp1gXgjpCzaRWRGM
DbHCHKaORETmUDP8ZDIDuFnGmeEOxiZ8SQ8QpZhhs10CQQDILEcf80w3FlX9RgRu
VG6zFDcEKkoazMRAmP8TJAsbqIitgHWyQYF92cOfAIq+nUVnDPBZEO01j+aofK2l
RZ3PAkBIUW7Oc3KpVt/EfAcgyiPhhHrbj6hB2vsM/TwPnszESP8Openz9wLNDYZV
2bZ9WGk0E0JhMQ+Edljthvp5a5rFAkAivLRXEhSe1qxzeGwabWKMhyyI94HGptRD
1YkmXDHlSdj2Kv3BwmZjXZ/5/tEVBRvfJzqqaiqQCfngMUq9DJi3AkA3UmkMCyfw
0PK8r4wqhXvso4rOUT70fpKVimojaeMzqxWpZ2tWaE6wX5jDfgpqK347VQ+XkI2W
7CZssl4WdZ5X
-----END PRIVATE KEY-----";
    public function index(TestRequest $request)
    {
        if (TestModel::index($request)) {
            return $this->response([]);
        } else {
            return $this->response(400000, '操作失败');
        }
    }
    public function publish()
    {
        for ($i=0;$i<100;$i++) {
            $data = ['publish'=>'order'.random_int(1, 10000)];
            $id = TestModel::createOne($data);
            RabbitmqHelper::getInstance()->push($id, 'test_model');
        }
    }

    public function excel()
    {
        dd(RabbitmqHelper::getInstance()->getQueues());
    }
    public function getNameList(TestRequest $request)
    {
        return new Paginate(TestModel::getNameList($request));
    }
    public function test(TestRequest $request)
    {
        $str = '不管是公钥加密还是私钥加密都是全英文117最多，全中文39个最多,如果超过则需要分段加密，与分段进行解密';
       
        $entry = RsaHelper::rsaPubEncode($str,self::PUBLIC_KEY);
        $data1 = RsaHelper::rsaPriDecode($entry,self::PRIVATE_KEY);
        dd($entry,$data1);
    }
    public function getOrderArray($file)
    {
        $file_path = storage_path($file);
        $fn = fopen($file_path, "r");
        $query_result = [];
        while (! feof($fn)) {
            $row = fgets($fn);
            
            $query_result[] = trim($row);
        }
        if (is_resource($fn)) {
            fclose($fn);
        }
        
        
        return $query_result;
    }
    
    public function findQuery($file, $orderId)
    {
        $file_path = storage_path($file);
        $fn = fopen($file_path, "r");
        $query_result = [];
        while (! feof($fn)) {
            $row = fgets($fn);
            
            //查单返回
            $query = "queryCallbackOrder [Yuzhou]";
            if (strpos($row, $query) !== false && strpos($row, $orderId) !== false) {
                $pattern = '/{.*}/';
                $return  = preg_match($pattern, $row, $query_result);
                fclose($fn);
                break;
            }
        }
        if (is_resource($fn)) {
            fclose($fn);
        }
        
        $result = array_key_exists(0, $query_result) ? $query_result[0] : '';
        return $result;
    }
    public function findCallback($file, $orderId)
    {
        $file_path = storage_path($file);
        $fn = fopen($file_path, "r");
        $query_result = [];
        while (! feof($fn)) {
            $row = fgets($fn);
            
            //查单返回
            $query = "[Yuzhou]--query [success] [{$orderId}]";
            if (strpos($row, $query) !== false) {
                $pattern = '/{.*}/';
                $return  = preg_match($pattern, $row, $query_result);
                fclose($fn);
                break;
            }
        }
        if (is_resource($fn)) {
            fclose($fn);
        }
        
        $result = array_key_exists(0, $query_result) ? $query_result[0] : '';
        return $result;
    }
}
