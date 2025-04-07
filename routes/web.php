<?php

use App\Http\Controllers\MatriculasController;
use App\Http\Controllers\PdfGenerateController;
use App\Http\Controllers\PdfSnappy;
use App\Http\Controllers\TesteController;
use App\Http\Controllers\YoutubeController;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/orcamento-pdf/{token}', [PdfGenerateController::class,'gera_orcamento'])->name('orcamento.pdf');
Route::get('/youtube', [YoutubeController::class,'envia'])->name('yt.send');
Route::get('/teste', [TesteController::class,'index'])->name('teste');
Route::get('/contratos/{token}/{type}', [MatriculasController::class,'contratos'])->name('contratos');
Route::get('/pdf-com-imagem', [PdfGenerateController::class, 'gerarPdfComImagemDeFundo'])->name('pdf.image');
