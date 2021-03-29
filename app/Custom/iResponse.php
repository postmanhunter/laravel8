<?php

namespace App\Custom;

use Illuminate\Http\Exceptions\HttpResponseException;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait iResponse
{


    protected static $errorMessage = "";
    protected static $successMessage = '';

    /**
     * @var int
     * 默认请求状态码
     */
    protected $statusCode = FoundationResponse::HTTP_OK;


    /**
     * @return int
     * 获取当前请求状态码
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }


    /**
     * 返回错误 api 信息 不阻止程序执行
     *
     * @param $data
     * @param array $header
     * @param string $status
     * @return mixed
     */


    public function response($code, $message = null, ...$value)
    {


        /**
         * 当只传入一个参数的情况下
         *
         * 传入整数：表示传入错误码！
         * 例：$this->response(123456);
         * 那么此种类型的返回一定是表示返回出错信息 返回为：
         * {
         *      status:'error',
         *      code:123456,
         *      message:'未定义的错误返回信息,请检查接口!'
         * }
         *
         * 传入字符串：表示传入了错误描述!那么错误码默认为 400000 (不建议这样传入)
         * 如：$this->response('这里是报错信息');
         * {
         *      status:'error',
         *      code:400000,
         *      message:'这里是报错信息'
         * }
         *
         * 如果传数组/对像：则表示操作成功返回
         * 如：
         * {
         *      status:'success',
         *      code:200000,
         *      message:'操作成功!'
         *      data:{***}
         * }
         *
         * 如果传入两个参数情况 表示
         *
         * 如果 第一个参数为 数组或对像，那么就表示操作成功，第一个参数为 data.第二个参数为 操作成功的描述。如
         *
         *
         */


        $iValue = [];
        switch (func_num_args()) {
            case 1:
                $iValue = ['status' => 'error', 'message' => '未定义的错误返回信息,请检查接口!'];

                switch (gettype($code)) {
                    case "integer":
                        $iValue = array_merge($iValue, ['code' => $code]);
                        break;
                    case "string":
                        $iValue = array_merge($iValue, ['code' => 400000, 'message' => $code]);
                        break;
                    default:
                        $iValue = array_merge(['status' => 'success', 'code' => 200000, 'message' => '操作成功!', 'data' => $code]);
                        break;
                }
                break;
            case 2:
                //如果入两个参数   第一个必需为 CODE
                $iValue = ['code' => $code, 'message' => $message];

                if (gettype($code) == "array") {
                    $iValue = array_merge($iValue, ['message' => $message, 'code' => 200000, 'status' => 'success', 'data' => $code]);
                } else {
                    switch (gettype($message)) {
                        case "string":
                            $iValue = array_merge($iValue, ['message' => $message, 'status' => ($code == 200000 ? 'success' : 'error')]);
                            break;
                        case "array":
                            $iValue = array_merge($iValue, ['status' => ($code == 200000 ? 'success' : 'warning'), 'message' => ($code == 200000 ? '操作成功!' : '异常的错误定义!'), 'data' => $message]);
                            break;
                    }
                }


                break;

            case 3:
                //如果入两个参数 第一个必需为 CODE 第二个必为 message
                $iValue = ['code' => $code, 'message' => $message];

                switch (gettype($value[0])) {
                    case "string":
                        $iValue = array_merge($iValue, ['message' => $message, 'status' => ($code == 200000 ? 'success' : 'error')]);
                        break;
                    case "array":
                        $iValue = array_merge($iValue, ['status' => ($code == 200000 ? 'success' : 'warning'), 'message' => ($code == 200000 ? '操作成功!' : '异常的错误定义!'), 'data' => $message]);
                        break;
                }
                break;

        }

        return Response::json($iValue, $this->getStatusCode());
    }


    /**
     * 静态方法
     * 输出报错信息同时阻止程序执行
     *
     * @param int $code
     */
    public static function responseErrorExit($code = 900999, $message = '未指定的系统错误！')
    {
        throw new HttpResponseException(response()->json([
            'status' => $code == 200000 ? 'success' : 'error',
            'code' => $code,
            'message' => $message,
        ]));
    }

    /**
     * 输出错误提示
     * @author tanghao
     * @date 2019-03-10 20:05
     * @param $code
     * @param $error
     * @return mixed
     */
    public function errorJson($code = 1, $error = "error")
    {
        return self::responseErrorExit($code, $error);
    }

    /**
     * 输出成功的json
     * @author tanghao
     * @date 2019-03-10 20:05
     * @param $data
     * @param $message
     * @return mixed
     */
    public function successJson(array $data = [], string $message = "")
    {
        $json = [
            "status" => "success",
            "code" => 200000,
            "message" => $message,
            "data" => $data,
        ];
        empty($data) && empty($message) && $json['message'] = "操作成功！";
        return Response::json($json, $this->getStatusCode());
    }

    /**
     * 输出携带分页类对象 的json
     * @author tanghao
     * @date 2019-03-10 20:09
     * @param $paginate
     * @return mixed
     */
    public function successJsonWithPaginate(\App\Http\Resources\Paginate $paginate)
    {
        return $this->successJson($paginate->toArrayPaginate());
    }

    /**
     *
     * @author tanghao
     * @date 2019-03-31 14:25
     * @param $json
     * @return mixed
     */
    public function responseJson(array $json)
    {
        return Response::json($json, $this->getStatusCode());
    }



}