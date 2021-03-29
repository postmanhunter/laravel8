<?php

namespace App\Custom;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
class iRequest extends FormRequest
{



    protected static $methodPrefix = 'check_';


    /**
     * 在子类中添加常用的错误提示，
     * 类似于 id.exists  username.unique 等
     * @var array
     */
    protected $extMessAttr = [

    ];

    /**
     * 给 messages() 方法增加验证属性的错误提示
     * 在 check_xxx() 方法中 使用  $this->setMessAttr("username","unique","600300:用户名%s已存在");
     * @author tanghao
     * @date 2019-04-01 10:49
     * @param string $attr 校验的字段 比如：id  username  phone
     * @param string $validFunc 校验方法，比如： unique exists
     * @param string $mess 错误提示，格式：    "600500:哎呀，你输入不对呀"
     * @param string $attrCn 字段描述
     * @param int $code 错误码
     * demo1: unique  $this->addMessAttr("username", "unique", "", "登陆账户")  ==> 'username.unique' => '600490:登陆账户[ronger]已存在'
     * demo2: exists  $this->addMessAttr("higher_id", "exists", "%s:%s[%s]不存在", "上级用户ID")  ==> 'higher_id.exists' => '600490:上级用户ID[9336411]不存在'
     * @return $this
     */
    protected function addMessAttr(string $attr, string $validFunc, string $mess = "", string $attrCn = "", int $code = 600490)
    {
        $attrVal = $this->getAttributeValue($attr);
        $attrCn = $this->getAttrCn($attr);
        if (empty($mess)) {
            if ($validFunc === "exists") {
                $mess = "{$code}:{$attrCn}[{$attrVal}]不存在";
            } elseif ($validFunc === "unique") {
                $mess = "{$code}:{$attrCn}[{$attrVal}]已存在";
            } else {
                $mess = sprintf("%s:%s[%s]错误", $code, $attr, $attrVal);
            }
        } else {
            //自定义占位描述，需要3个占位符
            if (strpos($mess, "%s") !== false) {
                $mess = sprintf($mess, $code, $attrCn, $attrVal);
            }
        }
        $this->extMessAttr["{$attr}.{$validFunc}"] = $mess;
        return $this;
    }

    # ===================== ===================== ===================== =====================
    /**
     * 自动校验的字段字典 基础字典
     * 处理自动校验的字段的错误提示 给予用户的字段 给汉语提示
     * @author  tangtang
     * 如果 $attrDictionary 中同时存在相同的key  会使用 $attrDictionary 中的
     * @var array
     */
    private $baseAttrDictionary = [
        "id" => "ID",
        "user_id" => "用户ID",
        "player_id" => "玩家ID",
        "platform_id" => "平台ID",
        "channel_id" => "渠道商ID",
        "package_id" => "渠道包ID",
        "start_time" => "开始时间",
        "end_time" => "结束时间",

    ];

    /**
     * 给子类使用的字典 在子类中进行覆盖
     * @var array
     * [ "字段名" => "字典注释", ]
     */
    protected $attrDictionary = [];

    /**
     * 单个参数字典
     * @param string $attr
     * @param string $attrCn
     * @return $this
     */
    protected function addAttrDictionary(string $attr, string $attrCn)
    {
        $this->attrDictionary[$attr] = $attrCn;
        return $this;
    }

    protected function getAttrCn(string $attr)
    {
        if (isset($this->attrDictionary[$attr])) {
            return $this->attrDictionary[$attr];
        } elseif (isset($this->baseAttrDictionary[$attr])) {
            return $this->baseAttrDictionary[$attr];
        } else {
            return $attr;
        }
    }

    /**
     * 批量参数字典
     * @param array $dictionary
     * @return $this
     */
    protected function addAttrDictionaries(array $dictionary)
    {
        $this->attrDictionary = array_merge($this->attrDictionary, $dictionary);
        return $this;
    }

    /**
     * 把字段字典 校验方法字典 处理成message 错误提示
     * @author  tangtang
     */
    protected function makeDictionaryToMessage()
    {
        //优先使用attrDictionary
        $dictionary = array_merge($this->baseAttrDictionary, $this->attrDictionary);
        $mess = [];
        $iRules = $this->getDefinedRules();
        empty($iRules) && $iRules = [];
        foreach ($iRules as $field => $rValue) {
            $r = is_array($rValue) ? $rValue : explode("|", $rValue);
            foreach ($r as $k => $v) {
                if (is_object($v)) {
                    //Rule 对象的方法，生成新的对象，拥有 __toString 魔术方法
                    if ($v instanceof \Illuminate\Validation\Rules\Exists
                        || $v instanceof \Illuminate\Validation\Rules\Unique
                        || $v instanceof \Illuminate\Validation\Rules\In
                        || $v instanceof \Illuminate\Validation\Rules\NotIn
                    ) {

                    } else {
                        //除了 Rule 类的校验外，还有自定义的校验类  匿名函数校验  均自定义抛异常
                        //匿名函数 在函数内部自己处理错误提示，此处不予处理
                        continue;
                    }
                }
                $func = explode(':', $v)[0];//校验方法
                $messKey = "{$field}.{$func}";//message 方法的key demo:id.required
                $attrName = $dictionary[$field] ?? $field;//字典的描述意思
                $attrValue = $this->getAttributeValue($field);// $this->$field;//参数值
                //错误提示的格式 %s:%s error ==> 400000:id错误
                $messFormat = $this->validateFuncErrorNotice[$func] ?? "";
                $compareValue = !is_object($v) ? last(explode(':', $v)) : "";//限定的值
                //特殊的校验方式，特殊处理
                $this->specialValidateFunc($func,$attrValue, $messFormat,$compareValue, $r);
                //定义的错误格式 %s:%s错误 ===> 400000:昵称错误
                //定义的错误格式 %s:%s[%s]错误 ===> 400000:昵称[xx]错误
                //定义的错误格式 %s:%s[%s]必须>[%s] ===> 400000:等级[xx]必须>10
                if ($messFormat) {
                    $mess[$messKey] = sprintf($messFormat, 400000, $attrName, $attrValue, $compareValue);
                } else {
                    $mess[$messKey] = sprintf("%s:%s不满足条件%s", 400000, $attrName, is_string($v) ? $v : "");
                }
            }
        }

        return $mess;
    }

    /**
     * todo
     * @param $funcName
     * @param $attrValue
     * @param $format
     * @param $compareValue
     */
    private function specialValidateFunc(&$funcName, &$attrValue, &$format, &$compareValue, array $rule)
    {
        switch ($funcName) {
            case "after":   //时间对比
            case "before":   //时间对比
            case "after_or_equal":   //时间对比

                break;
            case "min":
            case "max":
                if ($this->isFieldStringRule($rule)) {
                    $format = $this->validateFuncErrorNotice["length_{$funcName}"];
                }
                break;
        }
    }

    /**
     * 添加表单请求后钩子
     * 如果你想在表单请求「之后」添加钩子，可以使用 withValidator 方法。
     * 这个方法接收一个完整的验证构造器，允许你在验证结果返回之前调用任何方法：
     * @param $validator
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator){
            
        });
    }

    protected $authorized = true;

    public function authorize()
    {
        return $this->authorized;
    }

    /**
     * @param Validator $validator
     * 重新定义 接口类型 错误返回方式
     */

    public function failedValidation(Validator $validator)
    {
        /**
         * 当前只返回第一条错误信息
         */
        $error = $validator->errors()->first();
        if (($pos = strpos($error, ":")) !== false) {
            $firstError[0] = substr($error, 0, $pos);
            $firstError[1] = substr($error, $pos + 1);
        } else {
            $firstError[] = $error;
        }
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'code' => intval($firstError[0]),
            'message' => isset($firstError[1]) ? $firstError[1] : '未定义的错误类型,请检查Request配置',
        ]));
    }

    public function getRulesTag()
    {
        $url = $this->url();
        return substr($url, strripos($url, "/") + 1);
    }

    //request 类里自定义的校验规则，避免出现多次调用
    public static $definedRules;

    public function getDefinedRules()
    {
        if (self::$definedRules !== null) {
            return self::$definedRules;
        }
        $rules = [];
        if (method_exists($this, self::$methodPrefix . $this->getRulesTag())) {
            $rules = call_user_func_array([$this, (self::$methodPrefix . $this->getRulesTag())], []);
            self::$definedRules = $rules;
        }
        return $rules;
    }

    /**
     * 通用操作
     * 向下级子类检测 是否存在指定方法
     *
     *
     * @return array|mixed
     */
    public function rules()
    {
        return $this->getDefinedRules();
    }

    /**
     * 校验方法字典 不合规的错误提示
     * @author tangtang
     * @var array
     */
    private $validateFuncErrorNotice = [
        "required" => "%s:%s不能为空",
        "string" => "%s:%s只能是字符串",
        "integer" => "%s:%s只能是整数",
        "numeric" => "%s:%s只能是数字",
        "array" => "%s:%s只能是数组",
        "url" => "%s:%s不是合规的http地址",
        //时间校验
        "date" => "%s:%s只能是时间格式",
        "date_format" => "%s:%s时间格式错误",
        "after" => "%s:%s[%s]必须 > %s ",
        "after_or_equal" => "%s:%s[%s]必须 >= %s",
        "before" => "%s:%s[%s]必须 < %s",
        "before_or_equal" => "%s:%s[%s]必须 <= %s",
        //要是对比值生效 最少需要4个占位符  %s(错误码):%s(字段名)[%s(字段值)xxx%s(对比值)xxx]
        "min" => "%s:%s[%s]必须>=%s",
        "max" => "%s:%s[%s]必须<=%s",
        "length_min" => "%s:%s[%s]不能少于%s个字符",
        "length_max" => "%s:%s[%s]不能超过%s个字符",
        "in" => "%s:%s[%s]只能在%s中",
        "not_in" => "%s:%s[%s]不能在%s中",
        "exists" => "%s:%s[%s]不存在",
        "unique" => "%s:%s[%s]已存在",
        //匿名函数校验
        "callback" => "%s,%s[%s]不满足条件",

    ];

    /**
     *
     * @param string $funcName
     * @param string $format
     * @return $this
     */
    protected function setValidateFuncName(string $funcName, string $format)
    {
        $this->validateFuncErrorNotice[$funcName] = $format;
        return $this;
    }


    /**
     * 统一 临时性处理错误信息
     * 注:此方法为临时使用 开中过程中使用
     *
     * @return array
     */
    public function messages()
    {

        //补入字段字典 校验方法字典 自动把常见的错误类型提示加入
        $messageArr = $this->makeDictionaryToMessage();

        //补入 $this->addMessAttr() 方法加入的错误提示
        $messageArr = array_merge($messageArr, $this->extMessAttr);

        return $messageArr;
    }

    /**
     * message 里给指定字段值错误提示时，有些array object 等类型无法给明确错误值，做通用显示处理
     * @author tanghao
     * @date 2019-03-12 09:34
     * @param $attribute
     * @return mixed
     */
    public function getAttributeValue($attribute)
    {
        if (is_array($this->$attribute) || is_object($this->$attribute)) {
            return null;
        }
        return $this->$attribute;
    }

    /**
     * 把request对象的属性由 时间格式 处理成 时间戳 ，支持默认值
     * 根据时间格式，获取时间戳数据，没传入这返回默认值
     * @author tanghao
     * @date 2019-03-12 09:34
     * @param string $attribute 参数名
     * @param int $beforeDays 如果没传入，取默认多少天之前的数据
     * @param bool $isDayEnd false ==》 取当天开始时间 Y-m-d 00:00:0 /  true 取当天结束时间 Y-m-d 23:59:59
     *  demo: 入参  $this->start_time = "2019-03-17"
     *  调用 $this->getTimestampByDateFormat("start_time", -1, false)
     *  则  $this->start_time = strtotime("2019-03-17 00:00:00")
     * @return $this
     */
    public function setAttributeTimestampByDateFormat(string $attribute, int $beforeDays = 0, bool $isDayEnd = false)
    {
        $date_format = "Y-m-d " . ($isDayEnd ? "23:59:59" : "00:00:00");
        if ($this->$attribute && is_string($this->$attribute) && strtotime($this->$attribute) > 0) {
            $this->$attribute = strtotime(date($date_format, strtotime($this->$attribute)));
        } elseif (empty($this->$attribute)) {
            $time = $beforeDays > 0 ? strtotime("-{$beforeDays} day") : time();
            $this->$attribute = strtotime(date($date_format, $time));
        }
        return $this;
    }

    /**
     * 开始时间和结束时间 比较校验
     * @author tanghao
     * @date 2019-03-17 09:59
     * @param string $start_time_attr
     * @param string $end_time_attr
     * @return $this
     * @throws \RuntimeException
     */
    public function validateStartTimeAndEndTime(string $start_time_attr, string $end_time_attr)
    {
        $start_time = is_string($this->$start_time_attr) ? strtotime($this->$start_time_attr) : $this->$start_time_attr;
        $end_time = is_string($this->$end_time_attr) ? strtotime($this->$end_time_attr) : $this->$end_time_attr;
        if ($start_time > $end_time) {
            self::throwStartTimeGtEndTimeError();
        }
        return $this;
    }

    /**
     * 金额的最小金额和最大金额 比较校验
     * @author tanghao
     * @date 2019-03-17 09:59
     * @param string $min_attr
     * @param string $max_attr
     * @return $this
     * @throws \RuntimeException
     */
    public function validateMoneyMinAndMoneyMax(string $min_attr, string $max_attr)
    {
        if ($this->$max_attr > 0 && $this->$min_attr > $this->$max_attr) {
            self::throwMoneyMinGtMoneyMaxError();
        }
        return $this;
    }

    /**
     * 校验2个数字大小  $minField <= $maxField 或者 $minField < $maxField
     * @param string $minField
     * @param string $maxField
     * @param bool $allowEq
     * @return mixed
     */
    public function validateMinLtMax(string $minField, string $maxField, bool $allowEq = true)
    {
        if ($allowEq) {
            //允许 <=
            if ($this->$minField > $this->$maxField) {
                $minCn = $this->getAttrCn($minField);
                $maxCn = $this->getAttrCn($maxField);
                self::throwLogicError("{$minCn}必须 <= {$maxCn}");
            }
        } else {
            //允许 <
            if ($this->$minField >= $this->$maxField) {
                $minCn = $this->getAttrCn($minField);
                $maxCn = $this->getAttrCn($maxField);
                self::throwLogicError("{$minCn}必须 < {$maxCn}");
            }
        }
    }

    #################################### 分页 搜索  排序 start  ########################################

    /**
     * 被允许的排序字段
     * @var array
     */
    protected $allowSortFields = [];

    /**
     * @param array $fields
     * @return array
     */
    public function setAllowSortFields(array $fields)
    {
        $this->allowSortFields = array_unique(array_merge($this->allowSortFields, $fields));
        return $this->allowSortFields;
    }

    /**
     * @param string $default
     * @return mixed|string
     */
    public function getSortField(string $default = "id")
    {
        return in_array($this->sort_field, $this->allowSortFields) ? $this->sort_field : $default;
    }

    public function getSortType(string $default = "DESC")
    {
        return in_array($this->sort_type, ["asc", "ASC", "desc", "DESC"]) ? $this->sort_type : $default;
    }

    public function getPage()
    {
        return is_numeric($this->page) ? ($this->page == -1 ? -1 : max((int)$this->page, 1)) : 1;
    }

    public function getPageSize()
    {
        return ($this->getPage() == -1) ? 5000 : (is_numeric($this->size) && $this->size > 0 ? (int)$this->size : 30);
    }

    public function getOffset()
    {
        if (($page = $this->getPage()) == -1) {
            $offset = 0;
        } else {
            $offset = ($page - 1) * $this->getPageSize();
        }
        return $offset;
    }

    /**
     * 分页的规则校验
     */
    public function pageRule()
    {
        return [
            "page" => "bail|nullable|integer",
            "size" => "bail|nullable|integer",
            "sort_type" => "bail|nullable|string|in:asc,ASC,desc,DESC",
        ];
    }

    #################################### 分页 搜索  排序 end  ########################################

    private $stringRule = ["string","url","date","alpha","alpha_dash"];

    //校验字段限制规则是否是限制为string
    private function isFieldStringRule(array $rule)
    {
        foreach ($this->stringRule as $item) {
            if (in_array($item, $rule)) return true;
        }
        return false;
    }

    /**
     * 根据rules校验规则，获取经过校验的字段参数值集合
     * 不传的字段 或者传空字符的字段 都不在本函数的返回结果中
     * 目前考虑的校验规则不够严谨，最好在 chekc_xxx 校验规则方法里，对每个字段强制性加上 参数值类型的校验规则
     * 比如：integer string array numeric
     * @author tanghao
     * @date 2019-04-03 14:21
     * @param
     * @return mixed
     */
    public function getParamsByRules()
    {
        $rules = $this->rules();
        $post = $this->all();
        $params = [];
        foreach ($rules as $field => $rule) {
            if (!isset($post[$field])) {
                continue;
            }
            is_string($rule) && $rule = explode("|", $rule);
            //array rules
            if (is_array($rule)) {
                if (in_array("integer", $rule)) {//integer value
                    $params[$field] = (int)$post[$field];
                } elseif ( $this->isFieldStringRule($rule)) {//string value
                    $params[$field] = trim($post[$field]);
                } else {
                    $params[$field] = $post[$field];
                }
                continue;
            }
        }
        return $params;
    }




}
