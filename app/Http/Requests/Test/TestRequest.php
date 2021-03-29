<?php
namespace App\Http\Requests\Test;
use App\Custom\iRequest;
class TestRequest extends iRequest{
    public function check_get_name(){
        return [];
    }
}