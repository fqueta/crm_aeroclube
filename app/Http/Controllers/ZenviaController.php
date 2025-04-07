<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ZenviaController extends Controller
{
    /**
     * Recebe os dados da webwook
     */
    public function salvar_eventos(Request $request){
        $dados = $request->all();
        $json = Qlib::lib_array_json($dados);
        try {
            Storage::disk('local')->put('zenvia.txt',$json);
            $ret = $this->gravar($dados);
            return response()->json($ret);
        } catch (\Throwable $th) {
            $mens = $th->getMessage();
            return response()->json(['exec'=>false,'mens'=>'Erro ao gravar '.$mens]);
        }

    }
    /**
     * localiza e grava os dados na tabela de envetos de atendimentos do CRM
     */
    public function gravar($dados=[]){
        $ret['exec'] = false;
        if(isset($dados['id'])){
            $tab = 'eventos_atendimento';
            //verificar se o evento foi iniciodo
            $ev = DB::table($tab)->where('zenvia_id',$dados['id'])->get();
            // dump($dados);
            // dd($ev);
            if($ev->count()>0){
                //se envotrou agora é so salvar no campo config o json dos dados
                // dump($dados);
                $ret['savar'] = DB::table($tab)->where('zenvia_id',$dados['id'])->update(['config' => Qlib::lib_array_json($dados),'finalizado'=>'s']);
                if($ret['savar']){
                    $ev = DB::table($tab)->where('zenvia_id',$dados['id'])->get();
                    if($ev)
                    $ret['exec'] = true;
                }
            }
            $ret['ev'] = $ev;
        }
        return $ret;
    }
}
