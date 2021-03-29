<?php

namespace App\Helper;
class RsaHelper{

    //不管是公钥加密还是私钥加密都是全英文117最多，全中文39个最多,如果超过则需要分段加密，与分段进行解密
    //一次性最多117长度的字符加密一次
    const ENCRY_LENGTH = 117;
    //一次性的密码长度都是128位
    const DECRY_LENGTH = 128;

    /**
     * rsa公钥加密
     * @param $str
     * @param $key
     */
    public static function rsaPubEncode($str, $key)
    {
        $crypto='';
        //分段加密
        foreach (str_split($str, self::ENCRY_LENGTH) as $chunk) {
            openssl_public_encrypt($chunk, $encrypted, $key);
            $crypto .= $encrypted;
        }
        return $crypto?base64_encode($crypto):null;
    }
    /**
     * rsa私钥解密
     * @param $str
     * @param $key
     * @return string
     */
    public static function rsaPriDecode($str, $key)
    {
        $crypto = '';
        //分段解密
        foreach (str_split(base64_decode($str), self::DECRY_LENGTH) as $chunk) {
            // echo $chunk.PHP_EOL;
            openssl_private_decrypt($chunk, $encrypted, $key);
            $crypto.= $encrypted;
        }
        return $crypto;
    }
    /**
     * rsa私钥加密
     * @param $str
     * @param $key
     * @return string
     */
    public static function rsaPriEncode($str,$key)
    {
        $crypto='';
        //分段加密
        foreach (str_split($str, self::ENCRY_LENGTH) as $chunk) {
            openssl_private_encrypt($chunk, $encrypted, $key);
            $crypto .= $encrypted;
        }
        return $crypto?base64_encode($crypto):null;
    }
    /**
     * rsa公钥解密
     * @param $str
     * @param $key
     * @return string
     */
    public static function rsaPubDecode($str,$key)
    {
        $crypto = '';
        //分段解密
        foreach (str_split(base64_decode($str), self::DECRY_LENGTH) as $chunk) {
            // echo $chunk.PHP_EOL;
            openssl_public_decrypt($chunk, $encrypted, $key);
            $crypto.= $encrypted;
        }
        return $crypto;
    }
    /**
     * 拼接公钥字符串
     * 公钥固定64位为一行
     * @param $publicStr
     * @return string
     */
    public static function getPublicKey($publicKey)
    {
        //公钥
        $formPublicKey = "-----BEGIN PUBLIC KEY-----\r\n";
        foreach (str_split($publicKey,64) as $str){
            $formPublicKey .= $str . "\r\n";
        }
        $formPublicKey .="-----END PUBLIC KEY-----";

        return $formPublicKey;

    }
    /**
     * 拼接私钥字符串
     * 私钥固定64位为一行
     * @param $privatekey
     * @return string
     */
    public static function getPrivateKey($privateKey)
    {

        $formPrivateKey = "-----BEGIN PRIVATE KEY-----\r\n";
        foreach (str_split($privateKey,64) as $str){
            $formPrivateKey .= $str . "\r\n";
        }
        $formPrivateKey .="-----END PRIVATE KEY-----";

        return $formPrivateKey;
    }
}