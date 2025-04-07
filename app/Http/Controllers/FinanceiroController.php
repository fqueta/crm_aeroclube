<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceiroController extends Controller
{
    public function __construct(){
        global $categoriaMensalidade;
        $categoriaMensalidade = 15;
    }
    private function tema($entrada=null){
        $combustivel = '';
		if($combustivel=='s'){
			$cols = 5;
			$cols2=4;
			$tm = '<th style="width:20%"><div align="center">COMBUSTIVEL</div></th>';
		}else{
			$cols = 4;
			$cols2=3;
			$tm = '';
		}
		if($entrada){
			$entrada = '<th style="width:20%"><div align="left">ENTRADA</div></th>';
		}else{
			$entrada = '';
		}
		$tema = '<div class="col-md-{tam} tabe-parcelamento">
        			<table class="table">
						<thead >
							<tr>
								<th style="width:100%" colspan="'.$cols.'"><div align="left">{{titulo}} {selecione}</div></th>
							</tr>
							{tr_entrada}
							<tr>
								<th style="width:60%" colspan="'.$cols2.'"><div align="left">{{matricula}}</div></th>
								<th style="width:40%"><div align="right">{{valor}}</div></th>
							</tr>
							<tr>
								'.$entrada.'
								<th style="width:10%"><div align="center">PARCELAS</div></th>
								<th style="width:20%"><div align="center">V. PARCELA</div></th>
								'.$tm.'
								<th style="width:30%"><div align="right">TOTAL</div></th>
							</tr>
						</thead>
						<tbody class="jss526">{{tr}}
						</tbody>
						<tfoot class="jss526">{{footer}}
						</tfoot>
					</table>
					{{obs}}
				</div>';
		return $tema;

	}

	private function tema_pdf($entrada=null){
        $combustivel = '';
		if($combustivel=='s'){
			$cols = 3;
			$cols2=4;
			$tm = '<th style="width:20%"><div align="center">TOTAL</div></th>
                    <th style="width:20%"><div align="center">COMBUSTIVEL</div></th>';
		}else{
			$cols = 2;
			$cols2=3;
			$tm = '<th style="width:20%"><div align="right">TOTAL</div></th>';
		}
		if($entrada){
            $align_parcelas = 'center';
			$entrada = '<th style="width:20%"><div align="left">ENTRADA</div></th>';
		}else{
            $align_parcelas = 'left';
			$entrada = '';
		}
		$tema = '
        	<div syle="width:100%;">
				<table style="width:100%">
					<thead >
						<tr>
							<th style="width:100%;font-size:15px;border-top:1px solid #ddd" colspan="4"><div align="center"><b>{{titulo}}</b></div></th>
						</tr>
						<tr>
							<th style="border-top:1px solid #ddd" colspan="'.$cols.'"><div align="left">{{matricula}} </div></th>
							<th style="border-top:1px solid #ddd"><div align="right"><b>{{valor}}</b></div></th>
						</tr>
						<tr>
							'.$entrada.'
							<th style="border-top:1px solid #ddd"><div align="'.$align_parcelas.'"><b>PARCELAS</b></div></th>
							<th style="border-top:1px solid #ddd"><div align="center"><b>V. PARCELA</b></div></th>
							'.$tm.'
						</tr>
					</thead>
					<tbody class="jss526">{{tr}}
					</tbody>
					<tfoot class="jss526">{{footer}}
					</tfoot>
				</table>
				{{obs}}
		    </div>';
		return $tema;

	}

	private function calcPorcentagem($valor,$porcentagem,$parcelas=0,$opc='db'){



			  $total = NULL;

			  $val = Null;

			  $total_liquido  = NULL;

			  $total_bruto  = NULL;

			  $porc = NULL;

			  if($opc != 'db'){

				//$porcentagem = Qlib::precoDbdase($porcentagem);

				$porcentagem = str_replace(',','.',$porcentagem);

			  }

			  if($porcentagem>0){

				$porc =  ($porcentagem)/(100);

			  }else{

				  $porc = 0;

			  }

			  $total_liquido = ($valor)*($porc)*($parcelas);

			  $total_bruto = ($valor) + ($total_liquido);

			  $total_bruto = ($total_bruto) + ($val);

			  //echo $total_liquido.' = '.$valor.' * '.$porc.' <br>';

			  return round($total_bruto, 2);

	}

	private function parcelador($config=false){

		/*

		$config = array(

			'valor'=>542,2,

			'porcentagem'=>12,00,

			'parcelas'=>5,

		);

		*/

		$valorCjuros = $this->calcPorcentagem($config['valor'],$config['porcentagem'],$config['parcelas'],$opc='nodb');
        //dd($valorCjuros,$config['parcelas']);
        if($valorCjuros>0 && $config['parcelas']!=0){
            $reto = ($valorCjuros) / $config['parcelas'];
        }else{
            $reto = $valorCjuros;
        }

		$config['reto'] = $reto;

		//if(Qlib::isAdmin(1)){

			//lib_print($config);

		//}



		return round($reto,2);

	}

	public function execute($config=false){

		$retor = false;


		$config['footer'] = isset($config['footer']) ? $config['footer'] :false;

		$config['titulo'] = isset($config['titulo']) ? $config['titulo'] :false;
		$local = isset($config['local']) ? $config['local'] :'default';

		$compleSq = isset($config['compleSql']) ? $config['compleSql'] :false;

		$config['key'] = isset($config['key']) ? $config['key'] :'';
		$combustivel = isset($config['combustivel']) ? $config['combustivel'] :NULL;
		$token_matricula = isset($config['token_matricula']) ? $config['token_matricula'] :NULL;
		$somar_cobustivel_total = Qlib::qoption('somar_cobustivel_total')?Qlib::qoption('somar_cobustivel_total'):'n';
        $numePrevTurma = false;
        $ccur = new CursosController;
		$arr_conf_turma = [];
		$entrada_fds = 0;
		$tipoCurso = $ccur->tipo($config['id_curso']);
        $compleSql = false;

		// $labCombustivel = false;
        if($somar_cobustivel_total=='s' && $combustivel){
			//Preços calculado sem Levar em conta desconto.
			$somc = Qlib::precoDbdase($combustivel);
			$config['valor'] = ($config['valor'])+$somc;
		}
		$valor = $config['valor'];
		$tabela_preco = false;
		if(isset($config['tabela_preco'])){
			$tabela_preco = Qlib::buscaValorDb0($GLOBALS['tab50'],'url',$config['tabela_preco'],'id');
		}
		$tam = isset($config['tam']) ? $config['tam'] :12;
        if($tipoCurso==4){
            $config['tema2']  = isset($config['tema2']) ? $config['tema2'] :'
            <tr>
                <td><div align="left">{label_parcela}</div></td>
                <td><div align="center">{parcelaJuros}</div></td>
                <td><div align="right">{totalOrcamento}</div></td>
            </tr>';
        }else{
            $config['tema2']  = isset($config['tema2']) ? $config['tema2'] :'
            <tr>
                <td><div align="left" class="d-flex">{radio_select}&nbsp;<span class="">{entrada}</span></div></td>
                <td><div align="center">{label_parcela}</div></td>
                <td><div align="center">{parcelaJuros}</div></td>
                <td><div align="right">{totalOrcamento}</div></td>
            </tr>';

        }
        $is_pdf = (new MatriculasController)->is_pdf();
		if($is_pdf){
			//<!--<td><div align="right">{totalOrcamento}</div></td>-->
			$styletd = 'style="border-top:1px solid #ddd"';
            $tr_entrada = '<div align="left" class="">{radio_select}&nbsp;<span class="">{entrada}</span></div>';
            if($tipoCurso==4){
                $config['tema2'] = '<tr>
                                        <td '.$styletd.'><div align="left">{label_parcela}</div></td>
                                        <td '.$styletd.'><div align="center">{parcelaJuros}</div></td>
                                        <td '.$styletd.'><div align="right">{totalOrcamento}</div></td>
                                    </tr>';
            }else{
                $config['tema2'] = '<tr>
                                        <td '.$styletd.'>
                                            '.$tr_entrada.'
                                        </td>
                                        <td '.$styletd.'><div align="center">{label_parcela}</div></td>
                                        <td '.$styletd.'><div align="center">{parcelaJuros}</div></td>
                                        <td '.$styletd.'><div align="right">{totalOrcamento}</div></td>
                                    </tr>';
            }
            $config['local_imp'] = 'pdf';
        }
		$config['tema3']  = isset($config['tema3']) ? $config['tema3'] :'<tr><th colspan="3"><div align="left">{label_parcela}</div></th><th><div align="right">{parcelaJuros}</div></th></tr>';

		$config['local_imp'] = isset($config['local_imp']) ? $config['local_imp'] :'painel';
        if($valor && isset($config['id_curso'])){

			if(isset($config['forma_pagamento'])){

				$compleSq .= " AND forma_pagamento='".$config['forma_pagamento']."'";

			}else{

				$compleSq = false;

			}

			if($tabela_preco){

				$compleSq .=" AND tabelas LIKE '%\"".$tabela_preco."\"%'";

			}
			//Plano de formação tipo curso id 4
            if($tipoCurso==4 && $is_pdf){
				$ret = false;
				$verificaPlano = Qlib::verificaPlano(['token_matricula'=>$token_matricula]);
                if(isset($verificaPlano['dadosTabela']['id'])){
                    $ret = $ccur->tabela_parcelamento_cliente($verificaPlano['dadosTabela']['id']);
				}
                // dump($ret);
              	if(!$ret){
                    $ret = isset($verificaPlano['dadosTabela']['obs'])?$verificaPlano['dadosTabela']['obs']:false;
                }else{
                    return $ret;
                }
			}
			if($tipoCurso==4){
			}else{
				if(isset($config['id_turma'])){
					//Verifica previsionamento de turma baseado no id_turma e id_curso salvos
					$numePrevTurma = $ccur->numePrevTurma($config);
					if($numePrevTurma){
						$compleSql = "AND previsao_turma LIKE '%\"$numePrevTurma\"%'";
						if(isset($config['id_turma'])){
							$dt = Qlib::dados_tab('turmas',['campos'=>'*','where'=>"WHERE id='".$config['id_turma']."'"]);
                            $conf_turma = isset($dt[0]['config']) ? $dt[0]['config'] : false;
							if($conf_turma){
								$arr_conf_turma = Qlib::lib_json_array($conf_turma);
								if(isset($arr_conf_turma['fds']['ativo']) && $arr_conf_turma['fds']['ativo']=='s'){
									$entrada_fds = @$arr_conf_turma['fds']['entrada'];
								}
							}
						}
					}
				}
				if(!$numePrevTurma){
					return false;
				}

			}
			$dados = false;
            $sql = false;
            if(Qlib::qoption('adicionar_pevisionamento_parcelamento')=='s'){
                if(isset($config['id_turma']) && $config['id_turma']>0){
                    $sql = "SELECT * FROM parcelamento WHERE  `ativo`='s' AND turmas LIKE '%\"".$config['id_turma']."\"%' AND id_curso='".$config['id_curso']."' AND ".Qlib::compleDelete()." $compleSq ORDER BY `id` ASC";
                    $dados = Qlib::buscaValoresDb($sql);
                }
            }else{
                $compleSql = "";
				if(isset($config['token_matricula'])){
					$id_matricula = Qlib::get_matricula_id_by_token($config['token_matricula']);
					$d_desconto = Qlib::get_matriculameta($id_matricula,'parcelamento_desconto',true);
					if($d_desconto){
						$arr_parcela = Qlib::decodeArray($d_desconto);
						if(isset($arr_parcela['parcelamento']) && is_array($arr_parcela['parcelamento'])){
							$and = " AND ";
							$sqp = "";
							if(count($arr_parcela['parcelamento'])>1){
								foreach ($arr_parcela['parcelamento'] as $kp => $vp) {
									if($kp==0){
										$or = '(';
									}else{
										$or = ' OR';
									}
									$sqp .= "$or id='$vp'";
								}
								if($sqp){
									$compleSql .= "$and $sqp)";
								}
							}else{
								$compleSql .= " AND id='".$arr_parcela['parcelamento'][0]."'";
							}
						}
					}
				}
				if($compleSql){
					$sql = "SELECT * FROM parcelamento WHERE  `ativo`='s' $compleSql ORDER BY `id` ASC";
				}
				//caso não tenha tabela de parcelamento agregada não precisa exibir tabela nenhuma
				// else{
                    // $sql = "SELECT * FROM parcelamento WHERE  `ativo`='s' $compleSql AND id_curso='".$config['id_curso']."' AND ".Qlib::compleDelete()." $compleSq ORDER BY `id` ASC";
				// }
				// if(isAdmin(1)){
				// 	dump($sql);
				// }
            }
            if(!$dados){
                $dados = Qlib::buscaValoresDb($sql);
            }
			$ret = false;

			$tema0 = '{tabe1}{tabe2}';
            $tm_entrada = true;
            if($tipoCurso==4){
                $tm_entrada = false;
            }
			if($config['local_imp'] == 'painel'){

				$tema = isset($config['tema1'])? $config['tema1'] :  $this->tema($tm_entrada);

			}elseif($config['local_imp'] == 'pdf'){

				$tema = isset($config['tema1'])? $config['tema1'] : $this->tema_pdf($tm_entrada);
				// if(is_sandbox()){
				// 	dd($config);
				// }
				$tema0 = '<table style="border: 0px solid #FFF;"><tr><td style="border: 0px solid #FFF;">{tabe1}</td><td>{tabe2}</td></tr></table>';

				$tema_1 = '<table style="border: 0px solid #FFF;"><tr><td style="border: 0px solid #FFF;">{tabe1}</td><td>{tabe2}</td></tr></table><table style="border: 0px solid #FFF;"><tr><td>{tabe3}</td></tr></table>';

			}else{
				$tema = isset($config['tema1'])? $config['tema1'] : '{{tr}}';
			}
			if(Qlib::isAdmin(10)){
				$in_array = [2,3,4,5,6,7];
			}else{
				if($local == 'default' || $local == 'pdf'){
					// area pdf e outras que não seja checkout;
					$in_array = [2,3,4,5,6,7];
				}else{
					$in_array = [2,3,5,7];
				}
			}
			if($dados){
                $matricula = isset($dados[0]['valor']) ? $dados[0]['valor'] : 0;
				$i = 1;
				foreach($dados As $key=>$val){
					$type_aplica = $val['type'] ? $val['type'] : 'valor_curso';
					$radio_select = false;
					if($type_aplica=='avgas'){
						$config['valor'] = @Qlib::precoDbdase($config['combustivel']);
						$config['combustivel'] = 0;
					}
					if($entrada_fds){
						$val['entrada'] = $entrada_fds;
					}

					if(in_array(@$val['forma_pagamento'],$in_array)){

						if($config['key']!=''){
							if($key==$config['key']){
								if(!empty($val['parcelas'])){

									$arr_parcelas = json_decode($val['parcelas'],true);

									if(is_array($arr_parcelas)){

										$tr = false;

										if($val['valor']>0){

											$valor_entrada = $val['valor'];

											$valor = ($config['valor']-$valor_entrada);

											$label_entrada = 'ENTRADA  = '.number_format($valor_entrada,2,',','.').'';

										}elseif($val['entrada']>0){
											$valor_entrada = round(($valor*($val['entrada']/100)),2);
											$valor_entrada = $this->calcEntrada($valor_entrada,$combustivel,$token_matricula);
											$valor_entrada = str_replace(',','.',$valor_entrada);

											$valor = ($config['valor']-$valor_entrada);

											// $label_entrada = 'ENTRADA  = '.number_format($valor_entrada,2,',','.').'';

										}else{

											$valor = $config['valor'];

										}

										$i = 0;

										foreach($arr_parcelas As $k=>$v){

											if($val['entrada']>0){
												if($type_aplica=='valor_curso'){
													$entrada = $this->calcEntrada($val['entrada'],$combustivel,$token_matricula);
												}

												if($v['parcela']>1){

													if(isset($v['valor'])&&!empty($v['valor'])){

														$parcelaJuros = str_replace('R$','',$v['valor']);

														$totalOrcamento = $parcelaJuros *  $v['parcela'];

													}else{

														$juros = str_replace('%','',$v['juros']);

														$confJu = array(

															'valor'=>$valor,

															'porcentagem'=>$juros,

															'parcelas'=>$v['parcela'],

														);



														$label_parcela = $v['parcela'].' X ';

														$parcelaJuros = $this->parcelador($confJu);

														$parcelaJuros = round($parcelaJuros,2);

														$parcelaJuros = number_format($parcelaJuros,'2',',','.');

														$parcelaForm = $v['parcela'].'X'.round($parcelaJuros,2);

														$totalOrcamento = $parcelaJuros *  $v['parcela'];

													}
													$vtota = ($entrada) + ($totalOrcamento);



													$tr .= str_replace('{label_parcela}',$label_parcela,$config['tema2']);

													$tr = str_replace('{parcelaJuros}',$parcelaJuros,$tr);
													$tr = str_replace('{combustivel}',$combustivel,$tr);

													$tr = str_replace('{totalOrcamento}',number_format($totalOrcamento,'2',',','.'),$tr);

													$tr = str_replace('{parcelaForm}',$parcelaForm,$tr);
													$cliente_sele_plano = Qlib::qoption('cliente_sele_plano')?Qlib::qoption('cliente_sele_plano'):'n';
													if((Qlib::isAdmin(5) || $cliente_sele_plano=='s') && $local!='pdf'){

														$radio_select_value = [

															'entrada'=>$entrada,

															'parcelas'=>$v['parcela'],

															'valor'=>$parcelaJuros,

															'total_plano'=>($entrada+($v['parcela']*$parcelaJuros)),

															'categoria'=>$GLOBALS['categoriaMensalidade'],

															'dados_tabela'=>['id'=>$val['id'],'forma_pagamento'=>$val['forma_pagamento'],],

														];

														$value_select = Qlib::encodeArray($radio_select_value);

														if($tipoCurso==4){
															$radio_select = '';
														}else{

															if($type_aplica=='valor_curso'){
																$radio_select = '<label for="radio_select"><input type="radio" value="'.$value_select.'" onclick="cursos_gerarPlanos(\''.$value_select.'\',\''.$token_matricula.'\');" name="radio_select" /></label>';
															}else{
																$radio_select = '';
															}
														}

													}else{

														$radio_select = false;

													}
													if(Qlib::qoption('exibe_btn_pagamento_proposta')=='s' && Qlib::isAdmin(4)){
														$tr = str_replace('{radio_select}',$radio_select,$tr);
													}else{
														$tr = str_replace('{radio_select}',false,$tr);
													}

												}

											}else{
												// if(is_sandbox()){
												// 	lib_print($val);
												// }

												$juros = str_replace('%','',$v['juros']);

												$confJu = array(

													'valor'=>$valor,

													'porcentagem'=>$juros,

													'parcelas'=>$v['parcela'],

												);



												$parcelaJuros = $this->parcelador($confJu);

												$totalOrcamento = $parcelaJuros *  $v['parcela'];

												$label_parcela = $v['parcela'].' X ';

												$parcelaForm = $v['parcela'].'X'.round($parcelaJuros,2);

												$tr .= str_replace('{label_parcela}',$label_parcela,$config['tema2']);

												$tr = str_replace('{parcelaJuros}',number_format($parcelaJuros,'2',',','.'),$tr);
												$tr = str_replace('{combustivel}',$combustivel,$tr);
												$tr = str_replace('{totalOrcamento}',number_format($totalOrcamento,'2',',','.'),$tr);

												$tr = str_replace('{parcelaForm}',$parcelaForm,$tr);
                                                $entrada = isset($entrada)?$entrada:0;
												//$vtota = (@$entrada) + ($parcelaJuros);
												$cliente_sele_plano = Qlib::qoption('cliente_sele_plano')?Qlib::qoption('cliente_sele_plano'):'n';
												if((Qlib::isAdmin(5) || $cliente_sele_plano=='s') && $local!='pdf'){

													$radio_select_value = [

														'entrada'=>@$entrada,

														'parcelas'=>$v['parcela'],

														'valor'=>$parcelaJuros,

														'total_plano'=>(@$entrada+($v['parcela']*$parcelaJuros)),

														'categoria'=>$GLOBALS['categoriaMensalidade'],

														'dados_tabela'=>['id'=>$val['id'],'forma_pagamento'=>$val['forma_pagamento'],],

													];

													$value_select = Qlib::encodeArray($radio_select_value);
													if($tipoCurso==4){
														$radio_select = false;
													}else{
														if($type_aplica=='valor_curso'){
															$radio_select = '<label for="radio_select"><input type="radio" value="'.$value_select.'" onclick="cursos_gerarPlanos(\''.$value_select.'\',\''.$token_matricula.'\');" name="radio_select" /></label>';
														}else{
															$radio_select = false;
														}
													}
												}else{

													$radio_select = false;

												}
												if(Qlib::qoption('exibe_btn_pagamento_proposta')=='s' && Qlib::isAdmin(4)){
													$tr = str_replace('{radio_select}',$radio_select,$tr);
												}else{
													$tr = str_replace('{radio_select}',false,$tr);
												}

											}

										}

										//$titulo = $config['titulo'].' '.buscaValorDb($GLOBALS['lcf_formas_pagamentos'],'id',$val['forma_pagamento'],'nome');

										if(Qlib::isAdmin(2)){

											$titulo = $val['id'].' | '.$val['nome'];

											$tem_titulo = '<a href="{href}">{titulo}</a>';

											$href = url('/').'/config2/iframe?sec=dGFiZWxhX3BhcmNlbGFtZW50bw==&listPos=false&acao=alt&id='.base64_encode($val['id']);

											$titulo = str_replace('{titulo}',$titulo,$tem_titulo);

											$titulo = str_replace('{href}',$href,$titulo);

										}else{

											$titulo = $val['id'].' | '.$val['nome'];

										}

										$ret = str_replace('{{tr}}',$tr,$tema);

										$ret = str_replace('{{valor}}',number_format($config['valor'],'2',',','.'),$ret);

										$ret = str_replace('{{titulo}}',$titulo,$ret);

										$ret = str_replace('{{footer}}',$config['footer'],$ret);

										$ret = str_replace('{tam}',$tam,$ret);

										$ret = str_replace('{{obs}}',$val['obs'],$ret);

									}

								}

							}

						}else{
							if(!empty($val['parcelas'])){
								// if(Qlib::isAdmin(1)){
								// 	lib_print($val);
								// 	// lib_print($config);
								// }


								$arr_parcelas = json_decode($val['parcelas'],true);

								// if(is_adminstrator(1)){

								// 	lib_print($arr_parcelas);

								// }

								$valor_entrada = false;
								if(is_array($arr_parcelas)){

									$tr = false;

									$tr_entrada = false;

									$label_entrada = false;

									$valorTotalOrc = $config['valor'];
									if($val['valor']>0){

										$valor_entrada = $this->calcEntrada($val['valor'],$combustivel,$token_matricula);

										$valor = ($config['valor']-$valor_entrada);

										$label_entrada = 'ENTRADA  = '.number_format($valor_entrada,2,',','.').'';

									}elseif($val['entrada']>0){

										$valor_entrada = round(($config['valor']*($val['entrada']/100)),2);

										$valor_entrada = str_replace(',','.',$valor_entrada);

										$valor = ($config['valor']-$valor_entrada);

										$label_entrada = 'ENTRADA:';
									}else{

										$valor = $config['valor'];

									}

									$radio_select = false;
									foreach($arr_parcelas As $k=>$v){

										if(isset($v['entrada'])&&!empty($v['entrada'])){
											if(isset($v['tipo_entrada']) && $v['tipo_entrada']=='R$' && isset($v['entrada'])){
												$entrada = Qlib::precoDbdase($v['entrada']);
												// $entrada = str_replace('R$','',$entrada);
												// $entrada = str_replace(',','.',$entrada);
												// $entrada = (double)$entrada;
												// if(Qlib::isAdmin(1))
											}else{
												$porceto = str_replace('%','',$v['entrada']);
												$porceto = str_replace(',','.',$porceto);
												$porceto = (double)$porceto;
												// if(Qlib::isAdmin(1))
												// lib_print($v);

												$entrada = $config['valor'] *($porceto/100);
												$entrada = round($entrada,2);
											}
											$entrada = $this->calcEntrada($entrada,$combustivel,$token_matricula);
											$valor = ($config['valor']-$entrada);

										}else{

											$entrada = $this->calcEntrada($valor_entrada,$combustivel,$token_matricula);

										}


										if($val['entrada']!=0){

											$valorTotalOrc = $config['valor'];


											if((int)$v['parcela']>1){

												if(isset($v['valor'])&&!empty($v['valor'])){

													$parcelaJuros = str_replace('R$','',$v['valor']);

													$totalOrcamento = Qlib::precoDbdase($parcelaJuros) *  $v['parcela'];

												}else{

													$juros = str_replace('%','',$v['juros']);

													$confJu = array(

														'valor'=>$valor,

														'porcentagem'=>$juros,

														'parcelas'=>$v['parcela'],

													);



													$parcelaJuros = $this->parcelador($confJu);

													$totalOrcamento = ($parcelaJuros *  $v['parcela'])+($entrada);



													$parcelaJuros = round($parcelaJuros,2);

													$parcelaJuros = number_format($parcelaJuros,'2',',','.');

												}

												$label_parcela = $v['parcela'].' X ';

												$parcelaForm = $v['parcela'].'X'.$parcelaJuros;

												$tr .= str_replace('{label_parcela}',$label_parcela,$config['tema2']);

												$tr = str_replace('{parcelaJuros}',$parcelaJuros,$tr);
												$tr = str_replace('{combustivel}',$combustivel,$tr);
												$tr = str_replace('{entrada}',number_format($entrada,'2',',','.'),$tr);

												$tr = str_replace('{totalOrcamento}',number_format($totalOrcamento,'2',',','.'),$tr);

												$tr = str_replace('{parcelaForm}',$parcelaForm,$tr);
												$total_plano = $entrada+($v['parcela']*(double)Qlib::precoDbdase($parcelaJuros));

												$cliente_sele_plano = Qlib::qoption('cliente_sele_plano')?Qlib::qoption('cliente_sele_plano'):'n';
												if((Qlib::isAdmin(5) || $cliente_sele_plano=='s') && $local!='pdf'){

													$radio_select_value = [

														'entrada'=>$entrada,

														'parcelas'=>$v['parcela'],

														'valor'=>$parcelaJuros,

														'total_plano'=>$total_plano,

														'categoria'=>$GLOBALS['categoriaMensalidade'],

														'dados_tabela'=>['id'=>$val['id'],'forma_pagamento'=>$val['forma_pagamento'],],

													];

													$value_select = Qlib::encodeArray($radio_select_value);
													if($tipoCurso==4){
														$radio_select = false;
													}else{
														if($type_aplica=='valor_curso'){
															$radio_select = '<label for="radio_select"><input type="radio" value="'.$value_select.'" onclick="cursos_gerarPlanos(\''.$value_select.'\',\''.$token_matricula.'\');" name="radio_select" /></label>';
														}else{
															$radio_select = false;
														}
													}
												}else{

													$radio_select = false;

												}
												if(Qlib::qoption('exibe_btn_pagamento_proposta')=='s' && Qlib::isAdmin(4)){
													$tr = str_replace('{radio_select}',$radio_select,$tr);
												}else{
													$tr = str_replace('{radio_select}',false,$tr);
												}
											}

										}else{

											if($tipoCurso==4){
												$entrada = 0;
												$matricula = isset($matricula)?$matricula:0;
												if($matricula){
													// $matricula = 'Matrícula: '. valor_moeda($matricula,'').' Total ';
													$matricula =  Qlib::valor_moeda($matricula,'');
												}
											}
											if(isset($v['valor'])&&!empty($v['valor'])){

												$parcelaJuros = str_replace('R$','',$v['valor']);

												$parcelaJuros = Qlib::precoDbdase($parcelaJuros);

												$totalOrcamento = $parcelaJuros *  $v['parcela'];

												$parcelaForm = $v['parcela'].'X'.round($parcelaJuros,2);

												$parcelaJuros = number_format($parcelaJuros,'2',',','.');

											}else{

												$juros = str_replace('%','',$v['juros']);

												$confJu = array(

													'valor'=>$valor,

													'porcentagem'=>$juros,

													'parcelas'=>$v['parcela'],

												);

												$parcelaJuros = $this->parcelador($confJu);

												// $totalOrcamento = ($parcelaJuros *  $v['parcela'])+($entrada);
												$totalOrcamento = ($parcelaJuros *  $v['parcela']);

												$parcelaForm = $v['parcela'].'X'.round($parcelaJuros,2);

												$parcelaJuros = number_format($parcelaJuros,'2',',','.');

											}


											$label_parcela = $v['parcela'].' X ';
											$tr .= str_replace('{label_parcela}',$label_parcela,$config['tema2']);

											$tr = str_replace('{parcelaJuros}',$parcelaJuros,$tr);
											$tr = str_replace('{combustivel}',$combustivel,$tr);
											$tr = str_replace('{entrada}',number_format($entrada,'2',',','.'),$tr);

											$vtota = ($entrada) + ($totalOrcamento);
											// if(Qlib::isAdmin(1)){
											// 	echo '$vtota = ($entrada) + ($totalOrcamento)' . " $vtota = ($entrada) + ($totalOrcamento)<br>";
											// }
											//$tr = str_replace('{totalOrcamento}',number_format($totalOrcamento,'2',',','.'),$tr);

											$tr = str_replace('{totalOrcamento}',number_format($vtota,'2',',','.'),$tr);

											$tr = str_replace('{parcelaForm}',$parcelaForm,$tr);

											$cliente_sele_plano = Qlib::qoption('cliente_sele_plano')?Qlib::qoption('cliente_sele_plano'):'n';


											if((Qlib::isAdmin(5) || $cliente_sele_plano=='s') && $local!='pdf'){

												$radio_select_value = [

													'entrada'=>$entrada,

													'parcelas'=>$v['parcela'],

													'valor'=>$parcelaJuros,

													'total_plano'=>($entrada+($v['parcela']*$v['parcela'])),

													'categoria'=>$GLOBALS['categoriaMensalidade'],

													'dados_tabela'=>['id'=>$val['id'],'forma_pagamento'=>$val['forma_pagamento'],],

												];

												$value_select = Qlib::encodeArray($radio_select_value);
												if($tipoCurso==4){
													$radio_select = '';
												}else{
													if($type_aplica=='valor_curso'){
														$radio_select = '<label for="radio_select"><input type="radio" value="'.$value_select.'" onclick="cursos_gerarPlanos(\''.$value_select.'\',\''.$token_matricula.'\');" name="radio_select" /></label>';
													}else{
														$radio_select = '';
													}

												}
											}else{

												$radio_select = false;

											}
											// if(Qlib::isAdmin(1)){
											// 			var_dump(Qlib::qoption('exibe_btn_pagamento_proposta'));
											// 		}
											if(Qlib::qoption('exibe_btn_pagamento_proposta')=='s' && Qlib::isAdmin(4)){
												$tr = str_replace('{radio_select}',$radio_select,$tr);
											}else{
												$tr = str_replace('{radio_select}',false,$tr);
											}

										}

									}
									$selecione = false;
									if($tipoCurso==4){
										// $tema = str_replace('{{titulo}}','<div align="right"><label class="btn btn-default pull-left" for="tabela-'.$val['id'].'"><input type="radio" onclick="cursos_gerarPlanosFormacao(this,\''.@$config['token_matricula'].'\');" name="tabela-parcelamento" id="tabela-'.$val['id'].'" value="'.$val['id'].'"/> Selecionar</label> {titulo}</div>',$tema);
										$selecione = '<label class="btn btn-default pull-left" style="float: right !important;" for="tabela-'.$val['id'].'"><input type="radio" onclick="cursos_gerarPlanosFormacao(this,\''.@$config['token_matricula'].'\');" name="tabela-parcelamento" id="tabela-'.$val['id'].'" value="'.$val['id'].'"/> Selecionar</label>';
									}
									$ret = str_replace('{{tr}}',$tr,$tema);
									//$ret = str_replace('{{valor}}',number_format($valor,'2',',','.'),$ret);

									//$titulo = $config['titulo'].' '.buscaValorDb($GLOBALS['lcf_formas_pagamentos'],'id',$val['forma_pagamento'],'nome');

									// if(Qlib::isAdmin(2)&&Url::getURL(0)=='cursos'){
									if(Qlib::isAdmin(2)){

												//$titulo = $val['id'].' | '.$val['nome'].' '.buscaValorDb($GLOBALS['lcf_formas_pagamentos'],'id',$val['forma_pagamento'],'nome');

												$titulo = $val['id'].' | '.$val['nome'];

												$tem_titulo = '<a href="{href}">{titulo}</a>';

												$href = 'javascript:abrirjanelaPadrao(\''.url('/').'/config2/iframe?sec=dGFiZWxhX3BhcmNlbGFtZW50bw==&listPos=false&acao=alt&id='.base64_encode($val['id']).'\');';

												$titulo = str_replace('{titulo}',$titulo,$tem_titulo);

												$titulo = str_replace('{href}',$href,$titulo);



									}else{

										$titulo = $val['id'].' | '.$val['nome'];

									}
									$matricula = isset($matricula)?$matricula:false;

									//$titulo = $val['id'].' | '.$config['titulo'].' '.buscaValorDb($GLOBALS['lcf_formas_pagamentos'],'id',$val['forma_pagamento'],'nome');
									// $vlr = $matricula. number_format($valorTotalOrc,2,',','.');
                                    $vlr = 'Total: '.Qlib::valor_moeda($valorTotalOrc);
                                    $ret = str_replace('{{valor}}',$vlr,$ret);
									if($tipoCurso==4){
										$ret = str_replace('{{matricula}}','Matrícula: '.$matricula,$ret);
									}else{
										$ret = str_replace('{{matricula}}','',$ret);
									}
									$ret = str_replace('{{valor}}',$vlr,$ret);

									$ret = str_replace('{tr_entrada}',$tr_entrada,$ret);
									$ret = str_replace('{selecione}',$selecione,$ret);

									$ret = str_replace('{{titulo}}',$titulo.' <b class="text-danger">'.Qlib::buscaValorDb0('lcf_formas_pagamentos','id',$val['forma_pagamento'],'nome').'</b>',$ret);
									//$ret = str_replace('{titulo}',$titulo.' <b class="text-danger">'.buscaValorDb($GLOBALS['lcf_formas_pagamentos'],'id',$val['forma_pagamento'],'nome').'</b>',$ret);

									$ret = str_replace('{{footer}}',$config['footer'],$ret);

									$ret = str_replace('{tam}',$tam,$ret);

									$ret = str_replace('{{obs}}',$val['obs'],$ret);

								}

							}

						}
						// if(isset($_GET['fq'])){
						// 	// lib_print($in_array);
						// 	// lib_print($config);
						// 	lib_print(Qlib::qoption('exibe_btn_pagamento_proposta'));
		 					//  lib_print($radio_select);
						// }
						$totalTabelas = count($dados);
						if($config['local_imp'] == 'pdf' && $totalTabelas ==3){

							$re[$i] = $ret;

							$i++;

							if($i>3){

								$i=1;

								$retor .= str_replace('{tabe1}',$re[1],$tema_1);

								$retor = str_replace('{tabe2}',$re[2],$retor);

								$retor = str_replace('{tabe3}',$re[3],$retor);

							}

						}elseif($config['local_imp'] == 'pdf'&&count($dados)>3){

							$re[$i] = $ret;

							$i++;

							if($i>2){

								$i=1;

								$retor .= str_replace('{tabe1}',$re[1],$tema0);

								$retor = str_replace('{tabe2}',$re[2],$retor);

							}

						}else{

							$retor .= $ret;

						}
					}
				}

			}else{

				$parcelas = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'parcelas');

				if(!$parcelas){

					$parcelas = 1;

				}
				if($parcelas){
				$tr = false;
				foreach(range(1, $parcelas) As $k=>$v){
					//$juros = str_replace('%','',$v['juros']);
					$juros = 0;
					$confJu = array(
						'valor'=>$valor,
						'porcentagem'=>$juros,
						'parcelas'=>$v,
					);
					$parcelaJuros = $this->parcelador($confJu);
					$totalOrcamento = $parcelaJuros *  $v;
					$label_parcela = $v.' X ';
					$parcelaForm = $v.'X'.round($parcelaJuros,2);
					$tr .= str_replace('{label_parcela}',$label_parcela,$config['tema2']);
					$tr = str_replace('{parcelaJuros}',number_format($parcelaJuros,'2',',','.'),$tr);
					$tr = str_replace('{combustivel}',$combustivel,$tr);
					$tr = str_replace('{totalOrcamento}',number_format($totalOrcamento,'2',',','.'),$tr);
					$tr = str_replace('{parcelaForm}',$parcelaForm,$tr);
				}
				//echo $tr;exit;
				$ret = str_replace('{{tr}}',$tr,$tema);
				$ret = str_replace('{{valor}}',number_format($valor,'2',',','.'),$ret);
				$ret = str_replace('{{titulo}}',$config['titulo'],$ret);
				$ret = str_replace('{{footer}}',$config['footer'],$ret);
				$ret = str_replace('{tam}',$tam,$ret);
				$val['obs'] = false;
				$ret = str_replace('{{obs}}',$val['obs'],$ret);

				}

			}

		}
		return $retor;
	}
	/**PARA CALCULAR O VALOR DE SOMADO AO COMBUSTIVEL */
	public function calcEntrada($valor=null,$combustivel=null,$token_matricula=null){
		$entrada = $valor;
		$somar_cobustivel_total = Qlib::qoption('somar_cobustivel_total')?Qlib::qoption('somar_cobustivel_total'):'n';
		// $ddorc = dados_tab($GLOBALS['tab12'],'orc',"WHERE token = '".$token_matricula."'");
		// if(isset($ddorc[0]['orc']) && !empty($ddorc[0]['orc'])){
		// 	$orc = lib_json_array($ddorc[0]['orc']);
		// 	if(isset($orc['sele_pag_combustivel']) && $orc['sele_pag_combustivel']=='antecipado'){
		// 		$somar_cobustivel_total = 's';
		// 	}
		// }
		if($combustivel && $somar_cobustivel_total=='s'){
			$entrada += Qlib::precoDbdase($combustivel);
		}
		return $entrada;
	}
}
