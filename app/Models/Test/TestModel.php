<?php
namespace App\Models\Test;
use App\Models\Model;
class TestModel extends Model{
    protected $table = 'name';

    public function __construct(){
        parent::__construct($this);
    }
    public static function index($request){
        for($i=0;$i<$request->count;$i++){
            self::insert(['name'=>'name'.rand(1,10000)]);
        }
        return true;
    }
    public static function getNameList($request){
        $page = isset($request->page)??1;
        return self::paginate($request->page);
    }
    public static function createOne($data){
        return self::insertGetId($data);
    }   
    public static function updateOne($id,$column){
        self::where('id',$id)->increment($column);
    }
}