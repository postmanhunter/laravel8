<?php

namespace App\Exceptions;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function report(Throwable $e)
    {
        parent::report($e);
    }
    public function render($request, Throwable $exception){
        try{
            $MySQL = [
                1054 => '非法字段输入,请检查请求参数',
            ];

            /**
             * 自定义错误
             * 路由不存在或方法不存在时
             */


            if ($exception instanceof MethodNotAllowedHttpException) {

                return response()->json(['status' => 'error', 'code' => 999401, 'message' => '请求方式[Method]错误.请尝试切换'], 200);
            }
            /**
             * 404 自定义
             */

            if ($exception instanceof NotFoundHttpException) {
                return response()->json(['status' => 'error', 'code' => 999404, 'message' => '请求方法地址不正确.'], 200);
            }

            if ($exception instanceof AuthorizationException) {
                return response()->json(['status' => 'error', 'code' => 999403, 'message' => $exception->getMessage()], 200);
            }


            //业务逻辑运 行异常类
            if ($exception instanceof \RuntimeException) {
                return response()->json([
                    'status' => 'error',
                    'code' => $exception->getCode() ?: 999400,
                    'message' => $exception->getMessage() ?: "请求出错，请稍后重试",
                ]);
            }


            /**
             * 权限相关
             */

            if ($exception instanceof AuthenticationException) {
                $hasMessage = $exception->getMessage();
                switch ($hasMessage) {
                    case 'Unauthenticated.':
                        $message = '服务端拒绝了本次请求:越权操作！[%s]';
                        break;
                }
                return response()->json(['status' => 'error', 'code' => 000000, 'message' => sprintf($message, $hasMessage)], 200);
            }


            $hasMessage = $exception->getMessage();


            switch ($hasMessage) {
                case "Route [login] not defined.":
                    return response()->json(['status' => 'error', 'code' => 700000, 'message' => '令牌已过期。请重新登入'], 200);
            }



            return response()->json(['status' => 'error', 'code' => 999999,

                'message' => !empty($exception->errorInfo[1]) && isset($MySQL[$exception->errorInfo[1]]) ? $MySQL[$exception->errorInfo[1]] : $exception->getMessage(),'file'=>$exception->getFile(),'line'=>$exception->getLine()], 200);

        }catch(\Exception $e){
            return response()->json(['status'=>$e->getMessage(),'code'=>4000000,'data'=>[]]);
        }
        
        //return parent::render($request, $exception);
    }
}
