<?php
namespace App\Models\Test;
use App\Models\Model;
class OrderModel extends Model{
    protected $table = 'order';

    public function __construct(){
        parent::__construct($this);
    }
    public static function create($data){
        self::insert($data);
    } 
    public static function getList(){
        return self::get()->toArray();
    } 
}