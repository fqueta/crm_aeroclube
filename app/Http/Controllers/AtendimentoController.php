<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtendimentoController extends Controller
{
    /**
	 * Lista de atendimentos na tabela de eventos
	 */
	public function listAtendimento($config=false,$tam=12){

		$ret = false;

		$sqlEventos = "SELECT * FROM ".$GLOBALS['tab40']. " WHERE id_matricula='".$config['id']."' AND ".Qlib::compleDelete()." ORDER BY id DESC";

		$sqlUpdFinali = "UPDATE ".$GLOBALS['tab40']." SET finalizado = 's' WHERE finalizado = 'n' AND tag LIKE '%via_site%'";

		$salvaUpd = DB::statement($sqlUpdFinali);

		$dados = Qlib::buscaValoresDb($sqlEventos);

		//if(isset($config['reg_agendamento']) && !empty($config['reg_agendamento'])){
		// lib_print($dados);
		if($dados){
			$token_matricula = (new MatriculasController)->get_token_by_id($config['id']);
			$tema = '<div class="col-sm-12 padding-none"><h4>Histórico</h4><br><div class="row"><div class="col-sm-12">'.$this->btn_video_chamada($token_matricula).'</div></div>{conteudo}</div>';

			$tema1 ='<div class="panel panel-default col_historico" id="{id}"> 

						<div class="panel-heading"><h5>{atividade}<span class="pull-right">{pn_finalizado}</span></h5></div>

						<div class="panel-body">
						<div class="row"><form id="frmAtend{id}" method="{method}">{li}</form></div>
						<div class="row mt-2"><div class="col-xs-12 text-right">{btn_acao2}</div></div>
						</div>

					</div>';

			$tema2 = '<div class="col-xs-12 {campo}"><b>{label}</b> <span>{valor}</span></div>';

			$li = false;
			$aten_zenv = new atendimento_zenvia;
			
			foreach($dados As $key=>$val){
				
				$atividade = false;
				
				$btn_acao2 = false;
				if(!empty($val['tag'])){
					
					$ativ = json_decode($val['tag'],true);
					if(is_array($ativ)){
						foreach($ativ As $k=>$v){
							$dadosTag = dados_tab($GLOBALS['tab20'],'id,nome,config,pai',"WHERE token='".$v."'");
							
							if($dadosTag && $dadosTag[0]['pai']==1){
								// if(isAdmin(1)){
									// lib_print($v);
									// lib_print($val);
									// lib_print($arr_config);
									// lib_print($dadosTag);
								// }
								if(!empty($dadosTag[0]['config'])){
									
									$arr_config = json_decode($dadosTag[0]['config'],true);
									if(isset($arr_config['icon'])){

										$color = false;

										if(isset($arr_config['cor'])){

											$color = '#'.$arr_config['cor'];

										}

										$atividade .= '<i class="'.$arr_config['icon'].'"  style="color:'.$color.'"  title="'.$dadosTag[0]['nome'].'"></i> - ';

									}

								}

								if($v=='video_conferencia'){
									$btn_acao2 = $this->btn_gravacao_chamada_video($val['id'],$val);
								}
								if($v=='via_site'){

									$operador = 'Sistema';
								}else{
									
									$operador = buscaValorDb_SERVER('usuarios_sistemas','id',$val['autor'],'nome');
									
									$n = explode(' ',$operador);
									
									$operador = $n[0];
									if($val['autor']==0){
										$operador = 'Sistema | <small>'.$val['id'].'</small>';
									}
						
								}

								$atividade .= $operador; //;

							}

						}

					}

				}
				if($val['finalizado']=='s'){

					$checkFinaliz = 'checked';

				}else{

					$checkFinaliz = '';

				}
				
				$finalizado = 'n';
				if(isset($val['finalizado']) && $val['finalizado'] =='s'){
					$finalizado = $val['finalizado'];						
				}
					
				if($finalizado=='s'){
					// $pn_finalizado = '<span class="badge badge-success">'.__translate('Finalizado',true).'</span>';
					$pn_finalizado = '<label><input '.$checkFinaliz.' id="checkt_'.$val['id'].'" type="checkbox" name="finalizado" onclick="gerFinalizarTarefa(\''.$val['id'].'\')" value="s" /> Finalizado</label>';
				}else{
					$pn_finalizado = '<span id="btn-finaliz-'.$val['id'].'"><button type="button" class="btn btn-default" onclick="frmEditAtendimento(\''.$val['id'].'\')">'.__translate('Finalizar',true).'</button></span>';
				}

				if(!$atividade)

					$atividade = 'Id: '.$val['id'];

				$data_gravado = str_replace('{label}','Gravação:',$tema2);

				$data_gravado = str_replace('{valor}',dataExibe($val['data']),$data_gravado);

				$data_gravado = str_replace('{campo}','data',$data_gravado);

				

				$data_agendamento = false;

				if($val['data_agendamento']!='0000-00-00'){

					$data_agendamento = str_replace('{label}','Agendamento:',$tema2);

					$data_agendamento = str_replace('{valor}',dataExibe($val['data_agendamento']).' as '.$val['hora_agendamento'],$data_agendamento);

					$data_agendamento = str_replace('{campo}','valor',$data_agendamento);

				}
				
				$memo = str_replace('{label}','',$tema2);
				
				$memo = str_replace('{valor}',$val['memo'],$memo);
				
				$memo = str_replace('{campo}','memo',$memo);
				$resumos_ligacao = $aten_zenv->resumo_chamada($val['config']);
				if($resumos_ligacao){

					$memo .= $resumos_ligacao;
				}

				

				$li .= str_replace('{atividade}',$atividade,$tema1);

				$li = str_replace('{pn_finalizado}',$pn_finalizado,$li);

				$li = str_replace('{method}','post',$li);

				$li = str_replace('{id}',$val['id'],$li);

				$painel2 = '<div class="painel2"><button type="button" class="btn btn-danger btn-xs"  onclick="deleteTarefa(\''.$val['id'].'\')" btn-del-atend><i class="fa fa-trash"></i></button><button type="button" class="btn btn-default btn-xs" onclick="frmEditAtendimento(\''.$val['id'].'\')"><i class="fa fa-pencil"></i></button></div>';

				$painelSubmit = '<div class="col-xs-12 submit text-right" style="display:none"><button class="btn btn-success gravar" type="button">Gravar</button></div>';

				$inputHidden  = '<input type="hidden" name="id" value="'.$val['id'].'">';

				$inputHidden .= '<input type="hidden" name="conf" value="s">';

				$inputHidden .= '<input type="hidden" name="tab" value="'.base64_encode($GLOBALS['tab40']).'">';

				$inputHidden .= '<input type="hidden" name="campo_id" value="id">';

				$inputHidden .= '<input type="hidden" name="token" value="'.@$val['token'].'">';

				$inputHidden .= '<input type="hidden" name="token_atendimento" value="'.@$val['token_atendimento'].'">';

				$inputHidden .= '<input type="hidden" name="campo_bus" value="memo">';
				
				$inputHidden .= '<input type="hidden" name="opc" value="finaliza_agenda">';
				$inputHidden .= '<input type="hidden" name="ac" value="alt">';

				$liConteudo = $data_agendamento.$memo.$data_gravado.$painel2.$painelSubmit.$inputHidden;

				$li = str_replace('{li}',$liConteudo,$li);				
				$li = str_replace('{btn_acao2}',$btn_acao2,$li);
			

			}

			$ret = str_replace('{conteudo}',$li,$tema);

			$ret .= modalBootstrap2('Editar atendimento',$fechar=true,$conteudo='formEditAtendimento',$id='modalEditAtendimento',$tam='modal-lg');

		}

		return $ret;

	} 
	
}
