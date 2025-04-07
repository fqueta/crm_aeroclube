<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\admin\AsaasController;
use App\Http\Controllers\admin\ContratosController;
use App\Http\Controllers\admin\ZapsingController;
use App\Http\Controllers\api\ZapsingController as ApiZapsingController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RdstationController;
use App\Http\Controllers\ZapguruController;
use App\Http\Controllers\ZenviaController;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Request $request){
        $seg1 = request()->segment(1);
        $seg2 = request()->segment(2);
        $seg3 = request()->segment(3);
        $ret = false;
        if($seg3=='asaas'){
            $ret = (new AsaasController)->webhook($request->all());
        }elseif($seg3=='zenvia'){
            $ret = (new ZenviaController)->salvar_eventos($request);
        }elseif($seg3=='rd'){
            $ret = (new RdstationController)->webhook($request->all());
        }elseif($seg3=='zapguru'){
            $ret = (new ZapguruController)->webhook($request->all());
        }elseif($seg3=='zapsing'){
            $ret = (new ApiZapsingController)->webhook($request->all());
        }
        return $ret;
        // Route::prefix('webhook')->group(function(){
//     Route::post('/zenvia',[ZenviaController::class,'salvar_eventos']);
//     Route::post('/rd',[RdstationController::class,'webhook']);
//     Route::post('/zapguru',[ZapguruController::class,'webhook']);
// });

    }
}
