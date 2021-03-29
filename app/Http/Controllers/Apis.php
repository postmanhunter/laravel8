<?php

namespace App\Http\Controllers;


use App\Custom\iCommon;
use App\Custom\iRequest;
use App\Custom\iResponse;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Helper\LoggerHelper;
use App\Helper\RedisHelper;
class Apis extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, iResponse, ValidatesRequests, iCommon ,LoggerHelper ,RedisHelper;


    protected $defaultModel;

    public function __construct(Request $request)
    {

        $this->redis = RedisHelper::getInstance();
    }


    /**
     * 权限验证
     *
     * @param Request $request
     */
    protected function CheckAuthorize(Request $request)
    {

        /**
         * 当前请求路由标识
         * 即 权限名称
         */
        $routeTag = strtoupper(str_replace("/", "_", $request->path()));

        /**
         * 当前操作与请求
         * 用户信息
         */
        $adminUser = $request->user('api');

        /**
         * 检测 1
         * 检测用户是否有此权限
         */

        if (false === $adminUser->can($routeTag)) {
            /**
             * 无权限
             */
            throw new \Illuminate\Auth\Access\AuthorizationException('无权限访问数据', 440033);
        }




        IF (!\App\Models\Administrator\Permission::where('name', $name)->count()) {

            \App\Models\Administrator\Permission::create([
                'guard_name' => 'api',
                'name' => $name,
                'route_path' => $request->path(),
                'route_name' => $request->route()->getName(),
                'action_name' => $request->route()->getActionName()
            ]);
        }



    }


    /**
     * Load Helper
     *
     * This function loads the specified helper file.
     *
     * @param mixed
     * @return    void
     */
    public function helper($helpers = array())
    {
        $arrFiles = is_array($helpers) ?: explode(",", $helpers);

        foreach ($arrFiles as $key => $value) {
            $ext_helper = public_path("helpers/" . $value) . "_helper.php";
            if (file_exists($ext_helper)) {
                include_once($ext_helper);
            }
        }
        return true;
    }


    /**
     * 将谷歌验证 加入通用方法
     *
     * 对谷歌验证码进行验证
     * 如验证不正常 直接抛出错误
     */
    public function authorize($verify, $google_code)
    {

        $checkGoogle = iRequest::CheckGoogleAuthenticator($verify, $google_code);
        if (false === $checkGoogle) {
            return false;
        }
        return $checkGoogle;

    }
}
