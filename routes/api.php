<?php

use App\Http\Controllers\admin\ZapsingController;
use App\Http\Controllers\api\OrcamentoController;
use App\Http\Controllers\api\RabController;
use App\Http\Controllers\api\WebhookController;
use App\Http\Controllers\api\ZapsingController as ApiZapsingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ddiController;
use App\Http\Controllers\MatriculasController;
use App\Http\Controllers\RdstationController;
use App\Http\Controllers\ZapguruController;
use App\Http\Controllers\ZenviaController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::get('/ddi',[ddiController::class,'index'])->name('index');
Route::resource('ddi','\App\Http\Controllers\DdisController',['parameters' => [
    'ddi' => 'id'
]]);
Route::resource('cursos','\App\Http\Controllers\CursosController',['parameters' => [
    'cursos' => 'id'
]]);
Route::post('/webhook/{slug}',[WebhookController::class,'index']);
Route::prefix('v1')->group(function(){
    Route::post('/login',[AuthController::class,'login']);
    Route::middleware('auth:sanctum')->get('/user', [AuthController::class,'user']);
    Route::get('/matriculas',[MatriculasController::class,'index'])->middleware('auth:sanctum');
    Route::get('/matriculas/{id}',[MatriculasController::class,'show'])->middleware('auth:sanctum');
    Route::put('/matriculas/{id}',[MatriculasController::class,'update'])->middleware('auth:sanctum');
    Route::get('/rab',[RabController::class,'index']);
    Route::get('/orcamentos/{id}',[OrcamentoController::class,'show'])->middleware('auth:sanctum');
    Route::post('/gerar-orcamento',[OrcamentoController::class,'gerar_orcamento'])->middleware('auth:sanctum');
    Route::post('/assinar_proposta/{token}',[OrcamentoController::class,'assinar_proposta'])->middleware('auth:sanctum');
    //painel de assinaturas
    Route::get('/painel/assinaturas/{token}',[ ZapsingController::class,'painel_assinaturas'])->middleware('auth:sanctum');
    // Route::get('/gerar-enviar/{token}',[ApiZapsingController::class,'gerar_doc_envia_zapsing'])->middleware('auth:sanctum');
    Route::get('/gerar-enviar/{token}',[ApiZapsingController::class,'gerar_doc_envia_zapsing']);
});
// Route::post('/tokens/create', function (Request $request) {
//     //$token = $request->user()->createToken($request->token_name);

//     //return ['token' => $token->plainTextToken];
//     $user = User::where('Email' , $username)->where( 'Password' , $password)->where('UserStatus' , config('global.active'))->first();
//     if($user){
//         $success['token'] =  $user->createToken('MyApp')->accessToken;
//         return response()->json(['success' => $success], $this->successStatus);
//     }else{
//         return response()->json(['error'=>'Unauthorised'], 401);
//     }
// });
