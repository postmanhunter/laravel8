<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class Paginate extends JsonResource
{

    //支持增加其他扩展数据输出
    private $ext;

    /**
     * @param LengthAwarePaginator $paginator
     * @return Paginate
     */
    public static function instance(LengthAwarePaginator $paginator)
    {
        return new Paginate($paginator);
    }

    /**
     * 创建一个空的 Paginate 对象
     * @author tanghao
     * @date 2019-03-29 19:54
     * @param
     * @return Paginate
     */
    public static function emptyInstance()
    {
        return self::instance(self::createEmptyPaginator());
    }

    /**
     * 输出全量数据，不分页
     * @author tanghao
     * @date 2019-03-29 19:58
     * @param $list
     * @return Paginate
     */
    public static function instanceWithListNoPage(array $list)
    {
        $paginator = new LengthAwarePaginator($list, count($list), max(count($list),30), 1);
        return self::instance($paginator);
    }

    /**
     * 支持分页
     * @param array $list
     * @param int $total
     * @param int $pageSize
     * @param int $page
     * @return Paginate
     */
    public static function instanceWithList(array $list, int $total, int $pageSize = 30, int $page = 1)
    {
        $paginator = new LengthAwarePaginator($list, $total, $pageSize, $page);
        return self::instance($paginator);
    }

    /**
     * @param $ext
     * @return $this
     */
    public function setExt($ext)
    {
        $this->ext = $ext;
        return $this;
    }

    public function getExt()
    {
        return $this->ext;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'total' => $this->total(),//总数
            'count' => $this->count(),//当前行数、
            'active' => $this->currentPage(),//当前分页
            'list' => $this->items(),//列表
            "ext" => $this->ext,
        ];
    }

    /**
     * 输出数组格式的分页数据，不依赖request类
     * @author tanghao
     * @date 2019-03-10 20:25
     * @param
     * @return mixed
     */
    public function toArrayPaginate()
    {
        return [
            'total' => $this->total(),//总数
            'count' => $this->count(),//当前行数、
            'active' => $this->currentPage(),//当前分页
            'list' => $this->items(),//列表
            "ext" => $this->ext,
        ];
    }

    /**
     * 把list数组输出为分页数组格式
     * @author tanghao
     * @date 2019-03-29 19:52
     * @param array $list
     * @param mixed $ext
     * @return mixed
     */
    public static function toArrayPaginateV2(array $list, $ext = null)
    {
        return [
            'total' => count($list),//总数
            'count' => count($list),//当前行数、
            'active' => 1,//当前分页
            'list' => $list,//列表
            "ext" => $ext,
        ];
    }

    /**
     * 生成一个没有数组的空的 LengthAwarePaginator 对象
     * 当列表有不满足的条件时，可以用该方法；比如查询一个没有渠道的平台id的渠道集合时
     * @author tanghao
     * @date 2019-03-13 15:37
     * @param array $items
     * @param int $total
     * @param int $size
     * @param int $page
     * @return LengthAwarePaginator
     */
    public static function createEmptyPaginator($items = [], $total = 0, $size = 20, $page = 1)
    {
        return new LengthAwarePaginator($items, $total, $size, $page);
    }

}
