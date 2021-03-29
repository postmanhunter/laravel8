<?php

namespace App\Models;

// use App\Custom\iModel;
use App\Custom\iRequest;
use App\Http\Resources\Paginate;
use Illuminate\Database\Eloquent\Model as LaravelModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Constraint\IsFalse;

class Model extends LaravelModel
{

    // use SoftDeletes;
    /**
     * 搜索关键词
     * Like
     * Equal
     * @var
     */
    //默认不分表
    protected $subtable = false;

    //如果分表，默认以天分表
    protected $type = 'day';


    protected $searchKeywords;

    public static $searchKey;


    /**
     * 执行模型是否自动维护时间戳.
     *
     * @var bool
     */
    public $timestamps = false;

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';
    const DELETED_AT = 'delete_time';


    protected $hidden = [
        "create_time",
        "update_time",
        "delete_time"
    ];




    /**
     * Model constructor.
     * @param array $attributes
     */
    public function __construct($instance,array $attributes = [])
    {
        parent::__construct($attributes);

        if($instance->subtable){
            //分表了,设置当前的模型表
            
            if($instance->type=='month'){
                //按月分表
                $month = '_'.date("m");
                $instance->table = sprintf($instance->table,$month);
            }else if($instance->type=='day'){
                 
                //按日分表
                $day = '_'.date("d");
                $instance->table = sprintf($instance->table,$day);
            }
        }else{
            $instance->table = sprintf($instance->table,'');
        }

        //处理全民推广和共赢推广的表名自动获取
        $this->setPromoterTable();
    }

    /**
     * 模型中日期字段的存储格式
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 指定   Create_Time 返回格式
     *
     * @param $date
     * @return mixed
     */

    public function getCreateTimeAttribute($date)
    {
        return $date > 0 ? date('Y-m-d H:i:s', $date) : "";
    }

    /**
     * update_time
     *
     * @param $date
     * @return mixed
     */
    public function getUpdateTimeAttribute($date)
    {
        return $date > 0 ? date('Y-m-d H:i:s', $date) : "";
    }

    public function getDeletedAtAttribute($date)
    {
        return date('Y-m-d H:i:s', $date);
    }

    private static $instances;

    /**
     *
     * @param
     * @return static
     * @author tanghao
     * @date 2019-03-07 14:36
     */
    public static function instance()
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[__CLASS__] = new static();
        }
        return self::$instances[__CLASS__];
    }

    /**
     *
     * @param
     * @return static
     * @author tanghao
     * @date 2019-03-07 14:38
     */
    public static function forceInstance()
    {
        return new static();
    }

    /**
     * 获取 $this->connection . $this->table
     * @param
     * @return string
     * @author tanghao
     * @date 2019-03-12 09:03
     */
    public static function getTableName()
    {
        $tName = self::instance()->getTable();
        $cName = self::getConnName();
        return $cName ? "{$cName}.{$tName}" : $tName;
    }

    /**
     * $this->table
     * @param
     * @return mixed
     * @author tanghao
     * @date 2019-03-24 15:52
     */
    public static function getOnlyTableName()
    {
        return self::instance()->getTable();
    }

    /**
     * 获取 $this->connection
     * @param
     * @return string
     * @author tanghao
     * @date 2019-03-12 09:13
     */
    public static function getConnName()
    {
        return self::instance()->getConnectionName();
    }

    /**
     *
     * @return string connName.tableName
     */
    public static function getConnAndTableName()
    {
        return self::getConnName() . "." . self::getOnlyTableName();
    }

    /**
     * @return string database.table
     */
    public static function getDbAndTableName()
    {
        $conn = self::getConnName();
        $db = config("database.connections.{$conn}.database");
        return $db . "." . self::getOnlyTableName();
    }

    /**
     * 根据主键id更新，字段合规性需要自己校验
     * @param array $data 更新的数据
     * @param bool $returnInfo 是否需要查询该行信息并返回对象
     * @param bool $withTrashed 当表不存在 delete_time 字段时，或者不要求该字段为null时，传入true，不限制该字段
     * @return mixed
     * @throws \RuntimeException
     * @author tanghao
     * @date 2019-03-07 16:15
     */
    public function updateByPk(array $data, $returnInfo = false, bool $withTrashed = false)
    {
        if (!isset($data[$this->getPk()])) {
            throw new \RuntimeException('请传入参数:' . $this->getPk(), 900100);
        }
        $id = $data[$this->getPk()];
        unset($data[$this->getPk()]);
        if (!is_numeric($id) || (int)$id != $id) {
            throw new \RuntimeException("错误的{$this->getPk()}[{$id}]", 900101);
        }
//        $result = $this->where($this->getPk(), $id)->update($data);
        $model = $this->where($this->getPk(), $id);
        if ($withTrashed) {
            $model->withTrashed();
        }
        $result = $model->update($data);
        if ($returnInfo && $result) {
            $result = self::find($id);
        }
        return $result;
    }

    /**
     * 获取数据库中的字段
     *
     * @param null $table
     * @return mixed
     */
    public function getTableFields($table = null)
    {
        return Schema::getColumnListing($table ?: $this->table);
    }


    public function getPk()
    {
        return $this->primaryKey;
    }

    /**
     * 通过id集合获取列表集合
     * @param array $ids
     * @param array $fields
     * @param string $sort "id desc"  "name asc"
     * @return mixed
     * @author tanghao
     * @date 2019-03-13 09:48
     */
    public static function getListByIds(array $ids, array $fields = ["*"], string $sort = "id desc")
    {
        if (empty($ids)) {
            return [];
        }
        $sort_arr = explode(" ", $sort);
        $sort_field = $sort_arr[0];
        $sort_type = isset($sort_arr[1]) && in_array(strtolower($sort_arr[1]), ["desc", "asc"])
            ? strtolower($sort_arr[1]) : "desc";
        return self::whereIn("id", $ids)->select($fields)->orderBy($sort_field, $sort_type)->get()->toArray();
    }

    /**
     * 自定义的where 条件数组支持
     * @param mixed $model 执行查询的model实例，where whereIn 等方法返回的实例
     * @param array $where
     *支持的格式：
     *      ["id"=>1]           ==>     $model->where("id", 1)
     *      ["id:in" => [1,2]]  ==>     $model->whereIn("id", [1,2])
     *      ["time:>=", $time]  ==>     $mode->where("time", ">=", $time)
     *      ["status:!=", 2]    ==>     $model->where("status", "!=", 2)
     *      ["name:like" => "jack"] ==> $model->where("name", "like", "jack")
     * @return mixed
     * @author tanghao
     * @date 2019-03-16 19:21
     */
    public static function iDefinedWhere(&$model, array $where)
    {
        foreach ($where as $key => $value) {
            $pos = strpos($key, ":");
            if ($pos > 0 && $pos < strlen($key)) {
                $field = substr($key, 0, $pos);
                $compare = substr($key, $pos + 1);
                switch (strtolower($compare)) {
                    case "in":
                        $model->whereIn($field, $value);
                        break;
                    case "not in":
                        $model->whereNotIn($field, $value);
                        break;
                    default:
                        $model->where($field, $compare, $value);
                }
            } else {
                $model->where($key, $value);
            }
        }
        return $model;
    }


    /**
     * 静态获取指定数据 解析id专用
     * 2分钟缓存
     * @param string $valueField
     * @param string $keyField
     * @return mixed
     */
    public static function getKeyValue($valueField = 'name', $keyField = "id")
    {
        $model = new static();
        $key = strtoupper(implode("_", [__FUNCTION__, $model->getTableName(), $valueField, $keyField]));
        if (\Illuminate\Support\Facades\Cache::has($key)) {
            return \Illuminate\Support\Facades\Cache::get($key);
        }
        $voField = $model->withTrashed()->pluck($valueField, $keyField)->toArray();
        \Illuminate\Support\Facades\Cache::add($key, $voField, 2);
        return $voField;
    }

    /**
     * 根据指定条件 直接获取指定字段的值
     * 针对单条记录
     * @param $where
     * @param $field
     * @return mixed
     * @author tanghao
     * @date 2019-03-22 20:39
     */
    public static function getFieldValueWithTrashed(array $where, string $field)
    {
        $model = self::withTrashed();
        return self::iDefinedWhere($model, $where)->value($field);
    }

    /**
     * 根据指定条件 获取指定数据单元数据
     * @param array $where
     * @param array $field
     * @return array
     * @author tanghao
     * @date 2019-03-24 09:22
     */
    public static function getRowArrayWithTrashed(array $where, array $field)
    {
        $model = self::withTrashed();
        self::iDefinedWhere($model, $where)->select($field);
        $row = $model->first();
        return $row ? $row->toArray() : [];
    }

    /**
     * 根据指定条件 获取数据集合
     * @param array $where
     * @param array $field
     * @return mixed
     * @author tanghao
     * @date 2019-03-24 09:42
     */
    public static function getRowsListArrayWithTrashed(array $where, array $field = ["*"])
    {
        $model = self::withTrashed();
        return self::iDefinedWhere($model, $where)->select($field)->get()->toArray();
    }

    /**
     * 根据主键 软删除  ===》 更改指定字段值
     * @param Request $request
     * @param string $deleteField
     * @param $deleteValue
     * @return array
     */
    public function softDeleteByPk(Request $request, string $deleteField, $deleteValue)
    {
        $field = $this->getPk();
        $pk = $request->$field;
        if (empty($pk) || empty(self::find($pk))) {
            self::throwNotExistsAttributeValueError($field, $pk);
        }
        return [
            "count" => self::where($field, $pk)->update([$deleteField => $deleteValue]),
            "id" => $pk,
        ];
    }

    /**
     * 根据主键 软删除  ===》 更改指定字段值
     * @param Request $request
     * @param string $deleteField
     * @param $deleteValue
     * @return array
     */
    public function softDeleteByPkWithTrashed(Request $request, string $deleteField, $deleteValue)
    {
        $field = $this->getPk();
        $pk = $request->$field;
        if (!is_numeric($pk) || empty($pk) || empty(self::withTrashed()->find($pk))) {
            self::throwNotExistsAttributeValueError($field, $pk);
        }
        return [
            "count" => self::where($field, $pk)->withTrashed()->update([$deleteField => $deleteValue]),
            "id" => $pk,
        ];
    }

    /**
     * 根据主键值 更新字段  设置单字段值
     * @param Request $request
     * @param string $field
     * @param $value
     * @return mixed
     */
    public function setFieldByPkWithTrashed(Request $request, string $field, $value)
    {
        return $this->setFieldsByPkWithTrashed($request, [$field => $value]);
    }

    /**
     *
     * @param $request
     * @param $status
     * @return mixed
     * @author tanghao
     * @date 2019-03-29 10:03
     */
    public function setStatusByPkWithTrashed(Request $request, int $status)
    {
        return $this->setFieldByPkWithTrashed($request, "status", $status);
    }

    /**
     *
     * @param $id
     * @param $status
     * @return mixed
     * @author tanghao
     * @date 2019-03-29 11:03
     */
    public function setStatusByIdWithTrashed(int $id, int $status)
    {
        return $this->setFieldsWithTrashed(['id' => $id], ['status' => $status]);
    }

    /**
     * 根据主键值 更新字段
     * 设置多字段值
     * @param Request $request
     * @param $data
     * @return mixed
     */
    public function setFieldsByPkWithTrashed(Request $request, array $data)
    {
        $pk = $this->getPk();
        $pkVal = $request->$pk;
        if (!is_numeric($pkVal) || empty($pkVal) || empty($model = self::withTrashed()->find($pkVal))) {
            self::throwNotExistsAttributeValueError($pk, $pkVal);
        }
        return $model->update($data);
    }

    /**
     * 通用的字段更新
     * @param $where
     * @param $data
     * @return mixed
     * @author tanghao
     * @date 2019-03-29 09:54
     */
    public function setFieldsWithTrashed(array $where, array $data)
    {
        $model = self::withTrashed();
        return self::iDefinedWhere($model, $where)->update($data);
    }

    /**
     * 根据主键强制删除
     * @param $pkVal
     * @return array
     * @author tanghao
     */
    public function forceDeleteByPk($pkVal)
    {
        $pk = $this->getPk();
        if (is_object($pkVal) || is_array($pkVal) || empty($pkVal)) {
            self::throwParamError($pk);
        }
        return [
            $pk => $pkVal,
            "count" => self::where($pk, $pkVal)->forceDelete(),
        ];
    }

    /**
     * 根据主键强制删除
     * @param $pkVal
     * @return array
     * @author tanghao
     */
    public function forceDeleteByPkWithTrashed($pkVal)
    {
        $pk = $this->getPk();
        if (is_object($pkVal) || is_array($pkVal) || empty($pkVal)) {
            self::throwParamError($pk);
        }
        return [
            $pk => $pkVal,
            "count" => self::withTrashed()->where($pk, $pkVal)->forceDelete(),
        ];
    }

    /**
     * 全民推广 共赢推广 获取表名
     * @param
     * @return mixed
     * @author tanghao
     * @date 2019-04-02 12:57
     */
    public function getPromoterTableName()
    {
        //在crontab等使用时，是没有路由的
        $promoterType = \Route::current() && \Route::input("promoterType") ? \Route::input("promoterType") : "all";
        return str_replace("promoter_all_", "promoter_{$promoterType}_", $this->table);
    }

    /**
     * 全民推广，构造函数中，主动调用该方法，设置表名
     * @param
     * @return $this
     * @author tanghao
     * @date 2019-04-02 13:45
     */
    public function setPromoterTable()
    {
        if ($this->isPromoterTable) {
            $this->table = $this->getPromoterTableName();
        }
        return $this;
    }

    /**
     * 强制性切换推广类型，用于某些时候的数据查询，比如在全民推广中需要校验userid 是否是共赢推广员
     * @param string $type [all, win]
     * @return $this
     * @author tanghao
     * @date 2019-04-02 19:09
     */
    public function setForcePromoterTable(string $type = "all")
    {
        $this->setTable($this->getForcePromoterTableName($type));
        return $this;
    }

    /**
     * 强制性的获取推广员类型的表名
     * @param string $type
     * @return string
     */
    public function getForcePromoterTableName(string $type = "all")
    {
        return str_replace(["promoter_all_", "promoter_win_"], "promoter_{$type}_", $this->table);
    }

    /**
     * 获取时间戳字段的时间格式数据
     * @param string $attr
     * @param string $format
     * @return string
     */
    public function getDateAttr(string $attr, string $format = "Y-m-d H:i:s")
    {
        return $this->$attr ? date($format, $this->$attr) : "";
    }


    /** =================================== db transaction func ===================================== */
    /** @var \Illuminate\Database\ConnectionInterface */
    public static $dbConn;

    public function iBeginTransaction(bool $newConn = false)
    {
        if (self::$dbConn === null || $newConn === true) {
            self::$dbConn = DB::connection(static::getConnName());
        }
        self::$dbConn->beginTransaction();
        return $this;
    }

    /**
     *
     * @param
     * @return mixed
     * @author tanghao
     * @date 2019-04-03 19:26
     */
    public function iCommit()
    {
        self::$dbConn->commit();
        return $this;
    }

    /**
     *
     * @param
     * @return mixed
     * @author tanghao
     * @date 2019-04-03 19:27
     */
    public function iRollBack()
    {
        self::$dbConn->rollBack();
        return $this;
    }
    /** =================================== db transaction func end ===================================== */

    /** =================================== 针对按月分表的数据进行分表读写 start ===================================== */
    /**
     * 获取分表表名
     * 对涉及到分表的model，根据 分表日期获取表名，默认获取当前月份的
     * @param string $tableDate
     * @return mixed
     * @author tanghao
     * @date 2019-04-04 19:02
     */
    public function getWithDateTableName(string $tableDate = "")
    {
        empty($tableDate) && $tableDate = date("Ym");
        $table = self::getOnlyTableName();
        //$table 使用了占位符  user_2019_02  ==> $table = "user_%s"
        if (strpos($table, "%s") !== false) {
            $table = sprintf($table, $tableDate);
        } else {
            //原先部分表名没使用占位符 user_2019_02  ==>  $table = "user_"
            $table = self::getOnlyTableName() . $tableDate;
        }
        return $table;
    }

    /**
     * 设置分表表名，读写必用
     * 对涉及到分表的model，设置其分表表名，默认设置为当前月份的
     * @param string $tableDate
     * @return $this
     * @author tanghao
     * @date 2019-04-04 19:02
     */
    public function setWithDateTableName(string $tableDate = "")
    {
        $this->setTable($this->getWithDateTableName($tableDate));
        return $this;
    }

    /**
     * 针对按月分表的数据，进行跨表查询，支持分页
     * 暂时不支持连表，后续根据需要考虑是否扩展
     *
     * @param int $startTime 分表的时间字段，开始时间
     * @param int $endTime 结束时间
     * @param string $timeField 分表字段
     * @param string $timeFieldValueType 时间范围字段的格式，
     *      "timestamp"  db的时间字段类型为int， 传入指定的字符串 "timestamp"
     *      "Y-m-d H:i:s" db的时间字段类型为时间格式, 传入具体的格式字符串 "Y-m-d H:i:s"
     * @param array $where 查询条件，支持类型参考  self::iDefinedWhere()
     * @param array $fields
     * @param bool $isWithTrashed 是否需要使用 withTrashed()方法，也就是表是否包含 delete_time 字段
     * @param string|null $order 排序字段，默认 $timeField 倒序
     * @param string $orderType
     * @param int $page 页码
     * @param int $pageSize
     *
     * @param string $tableDateFormat 分表的时间格式 table_2019_01 传入 "Y_m"
     * @param string $subTableDate 按月分表=>month, 按天分表=>day
     * @param mixed $groupBy $model->groupBy() 方法支持的数据
     *
     * sum求和的查询 使用下面的方法  distinctCountUnionSubTable()
     * @return array
     * @author tanghao
     * @date 2019-04-04 19:56
     */
    public function getUnionMonthSubTablePageList(
        int $startTime,
        int $endTime,
        string $timeField,
        string $timeFieldValueType = "timestamp",
        array $where = [],
        array $fields = ["*"],
        bool $isWithTrashed = true,
        string $order = null,
        string $orderType = "DESC",
        int $page = 1,
        int $pageSize = 30,
        string $tableDateFormat = "Ym",
        string $subTableDate = "month",
        $groupBy = null
    )
    {
        $offset = ($page - 1) * $pageSize;
        !in_array(strtolower($orderType), ['desc', "asc"]) && $orderType = "DESC";

        $startTimeVal = $startTime;
        $endTimeVal = $endTime;
        if ($timeFieldValueType !== "timestamp") {//时间字段为时间格式
            $startTimeVal = date($timeFieldValueType, $startTime);
            $endTimeVal = date($timeFieldValueType, $endTime);
        }

        $model = null;
        //按月分表还是按天分表
        !in_array($subTableDate, ["month", "day"]) && $subTableDate = "month";
        $fDay = $subTableDate === "day" ? "d" : "01";
        for ($time = $startTime; $time <= $endTime; $time = strtotime(date("Y-m-{$fDay} H:i:s", $time) . " +1 {$subTableDate}")) {
            //判断表是否存在
            $tableName = static::getWithDateTableName(date($tableDateFormat, $time));
            if (!\Schema::connection(static::getConnName())->hasTable($tableName)) {
                continue;
            }

            if ($model === null) {
                $model = static::forceInstance()
                    ->setTable($tableName)
                    ->select($fields)
                    ->whereBetween($timeField, [$startTimeVal, $endTimeVal]);
                $isWithTrashed && $model->withTrashed();
                self::iDefinedWhere($model, $where);
            } else {
                $unionModel = static::forceInstance()
                    ->setTable($tableName)
                    ->select($fields)
                    ->whereBetween($timeField, [$startTimeVal, $endTimeVal]);
                $isWithTrashed && $unionModel->withTrashed();
                self::iDefinedWhere($unionModel, $where);
                $model->union($unionModel);
            }
        }

        $list = [];
        $total = 0;
        if ($model !== null) {
            //求列表
            $total = DB::connection(static::getConnName())
                ->table(DB::raw("(" . $model->toSql() . ") as count_table"))
                ->select(DB::raw("count(1) as total"))
                ->setBindings($model->getBindings())
                ->first()->total;
            if (!empty($order)) {
                $model->orderBy($order, $orderType);
            }
            !empty($groupBy) && $model->groupBy($groupBy);
            $list = $model->offset($offset)->limit($pageSize)->get()->toArray();
        }
        return ["count" => $total, "list" => $list,];
    }


    /**
     * 通用的垮分表 求和 统计 查询
     * ### 求和
     * ### 统计计数
     *
     * 支持排重统计数据，子查询里面不能统计，不然无法做到真正排重
     *
     *
     * @param int $startTime
     * @param int $endTime
     *
     * @param string|array $parentFields
     *          外层总查询的查询字段，求和，统计都在这里
     *
     *          支持数组
     *              demo3: ["sum(a) as a", "count(b) as b"]
     *          也支持 DB::raw() 的对象
     *              demo4: ["sum(a) as a", DB::raw("sum(b) as b"), DB::raw("count(distinct(c)) as c")]
     * @param string $timeField
     * @param string $timeFieldValueType
     *          分表字段类型   timestamp 时间戳
     *                      Ymd  Y-m-d H:i:s   其他时间格式
     * @param array $where
     * @param array $subFields
     *          子查询的查询字段 $model->select() 支持的都支持
     * @param bool $isWithTrashed
     * @param string $tableDateFormat
     *          分表的分表结构  Y_m  Ym  Y_m_d   Ymd
     * @param string $subTableDate
     *          分表类型  dy  month
     * @param string $parentGroupBy
     *      外层查询的分组，直接写 sql语句的格式即可
     * @param mixed $subGroupBy
     *      子查询的分组，支持 $model->groupBy() 的格式
     * @param string $parentOrderBY 外层查询的排序 sql的格式  a desc,b asc
     *          demo1 : a desc
     *          demo2 : a desc,b desc
     * @param string $parentLimit 外层查询的limit  sql 的格式
     *          demo1 : 0,30
     *          demo2 : 30
     * @return array
     */
    public function distinctCountUnionSubTable(
        int $startTime,
        int $endTime,
        array $parentFields,
        string $timeField,
        string $timeFieldValueType = "timestamp",
        array $where = [],
        array $subFields = ["*"],
        bool $isWithTrashed = true,
        string $tableDateFormat = "Ym",
        string $subTableDate = "month",
        string $parentGroupBy = "",
        string $subGroupBy = "",
        string $parentOrderBY = "",
        string $parentLimit = ""
    )
    {

        $startTimeVal = $startTime;
        $endTimeVal = $endTime;
        if ($timeFieldValueType !== "timestamp") {//时间字段为时间格式
            $startTimeVal = date($timeFieldValueType, $startTime);
            $endTimeVal = date($timeFieldValueType, $endTime);
        }

        $model = null;
        //按月分表还是按天分表
        !in_array($subTableDate, ["month", "day"]) && $subTableDate = "month";
        $fDay = $subTableDate === "day" ? "d" : "01";
        for ($time = $startTime; $time <= $endTime; $time = strtotime(date("Y-m-{$fDay} H:i:s", $time) . " +1 {$subTableDate}")) {
            //判断表是否存在
            $tableName = static::getWithDateTableName(date($tableDateFormat, $time));
            if (!\Schema::connection(static::getConnName())->hasTable($tableName)) {
                continue;
            }

            if ($model === null) {
                $model = static::forceInstance()
                    ->setTable($tableName)
                    ->select($subFields)
                    ->whereBetween($timeField, [$startTimeVal, $endTimeVal]);
                $isWithTrashed && $model->withTrashed();
                !empty($subGroupBy) && $model->groupBy($subGroupBy);
                self::iDefinedWhere($model, $where);
            } else {
                $unionModel = static::forceInstance()
                    ->setTable($tableName)
                    ->select($subFields)
                    ->whereBetween($timeField, [$startTimeVal, $endTimeVal]);
                $isWithTrashed && $unionModel->withTrashed();
                self::iDefinedWhere($unionModel, $where);
                $model->union($unionModel);
            }
        }

        $sumData = [];
        $total = 0;
        if ($model !== null) {
            //求和
            //原先连表，会对每张表求和，生成多条记录，作为子查询再求和
            $pField = implode(",", $parentFields);
            $sql = "select $pField from (" . $model->toSql() . ") as union_table";
            !empty($parentGroupBy) && $sql .= " group by $parentGroupBy";
            !empty($parentOrderBY) && $sql .= " order by $parentOrderBY";
            !empty($parentLimit) && $sql .= " limit $parentLimit";

            $res = DB::connection(static::getConnName())->select($sql, $model->getBindings());
            //对象转数组
            $sumData = json_decode(json_encode($res), true);

            //有分组，则返回集合
            if (empty($parentGroupBy) && empty($subGroupBy)) {
                //没有分组，不论求和还是统计，只返回一条记录
                $sumData = array_shift($sumData);
            }

            //有分页就加统计
            if (!empty($parentLimit)) {
                $sql = "select count(1) as count from (".$model->toSql().") as union_table";
                $res = DB::connection(static::getConnName())->select($sql, $model->getBindings());
                $res = json_decode(json_encode($res), true);
                $total = $res[0]["count"] ?? 0;
            }

        } elseif (empty($parentGroupBy) && empty($subGroupBy)) {
            //表不存在 且没有分组查询，求和和统计，只查一条记录，给默认值
            foreach ($parentFields as $field) {
                if ($field instanceof \Illuminate\Database\Query\Expression) {
                    $field = $field->getValue();
                }
                $field = str_replace(" as ", " AS ", $field);
                if ($pos = strpos($field, " AS ")) {
                    $field = substr($field, $pos + 4);
                }
                $sumData[$field] = 0;
            }
        }
        return ["sumData" => $sumData, "total" => $total];
    }


    /** =================================== 针对按月分表的数据进行分表读写 end ===================================== */

    /**
     * 判断表是否存在
     * @param string $conn_name
     * @param string $table_name
     * @return bool
     */
    public static function hasTable(string $conn_name, string $table_name)
    {
        return \Schema::connection($conn_name)->hasTable($table_name);
    }

    //判断表是否存在
    public function existsTable()
    {
        return self::hasTable($this->connection, $this->table);
    }

    /**
     * @param iRequest $request
     *   处理分页的 page  size  order_type  3个字段
     * @param array $fields
     * @param array $where
     * @param string $sortField
     * @param $isWithTrashed
     * @return Paginate
     */
    public function getCommonPaginator(
        iRequest $request,
        array $fields = ["*"],
        array $where = [],
        string $sortField = "",
        bool $isWithTrashed = false
    )
    {
        empty($sortField) && $sortField = static::instance()->getPk();
        $model = static::select($fields);
        $isWithTrashed && $model->withTrashed();
        self::iDefinedWhere($model, $where);
        $paginator = $model->orderBy($sortField, $request->getSortType("desc"))
            ->paginate($request->getPageSize(), ["*"], "page", $request->getPage());
        return Paginate::instance($paginator);
    }

    /**
     * 针对日期分表的表，设置表名
     * ## model 的表名结构设置为 user_%s ,使用占位符占位
     * @param $date
     * @return $this
     * @author tangtang
     */
    public function setDateTable(string $date)
    {
//        return $this->setTable(sprintf($this->table, $date));
        return $this->setWithDateTableName($date);
    }


}