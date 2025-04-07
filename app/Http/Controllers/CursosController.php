<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Qlib\Qlib;
use Illuminate\Http\Request;

class CursosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $tab;
    public function __construct()
    {
        global $tab10;
        $this->tab = 'cursos';
        $tab10 = $this->tab;
    }
    public function index()
    {
        $d = Curso::where('excluido','=','n')->where('deletado','=','n')->OrderBy('id','ASC')->get();
        $ret['exec'] = false;
        $ret['dados'] = [];
        if($d->count() > 0){
            $ret['exec'] = true;
            $dados = [];
            foreach ($d as $k => $v) {
                $dados[$k] = $v->getOriginal();
            }
            $ret['dados'] = $d;
        }
        return $ret;
    }
    /**
	 * Metodo para verificar se o curso é um recheck
	 */
	public function is_recheck($id_curso){
		$ret = false;
        global $tab10;
		if($id_curso){
			$conf = Qlib::buscaValorDb0($tab10,'id',$id_curso,'config');
			if($conf){
				$arr_con = Qlib::lib_json_array($conf);
				if(isset($arr_con['adc']['recheck']) && $arr_con['adc']['recheck'] == 's'){
					$ret = true;
				}
			}
		}
		return $ret;
	}
    public function tipo($id_curso = null)
	{

		if($id_curso){
			$ret = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$id_curso,'tipo');
		}else{
			$ret = false;
		}
		return $ret;
	}
    /**
     * Recupera o numero da previsão de turma para a gerar do orçamento
     */
    public function numePrevTurma($config=false)
	{
		$ret = false;
		$id_turma = isset($config['id_turma'])?$config['id_turma']:false;
		$id_curso = isset($config['id_curso'])?$config['id_curso']:false;
		if(!$id_curso && $id_turma){
			$id_curso = Qlib::buscaValorDb0($GLOBALS['tab11'],'id',$id_turma,'id_curso');
		}
		if($id_turma && $id_curso){
			$turmaDisponivel = $this->turmaDisponivel($id_curso);
			// if(isAdmin(1)){
			// 	lib_print($turmaDisponivel);
			// }
			if(isset($turmaDisponivel['turmas'])&&is_array($turmaDisponivel['turmas'])){
				foreach ($turmaDisponivel['turmas'] as $k => $v) {
					if(isset($v['id'])&&$v['id']==$id_turma){
						$ret = @$v['prev_turma'];
					}
				}
				//lib_print($turmaDisponivel['turmas']);
			}
		}
		//$ret = $id_turma;
		return $ret;
	}
    /**
     * Cria um array com as turmas disponivel para serem exibidos na area de adminnstração
     */

    public function turmaDisponivel($id_curso=false,$dataLimit=false,$diasAntes=10){
        global $tab11;
		$ret['disponivel'] = true;

		$ret['turmas'] = false;

		$ret['todasTurmas'] = false;

		if($id_curso){

			$diasAntes = Qlib::qoption('dias_turma_valida') ? Qlib::qoption('dias_turma_valida'):$diasAntes;

			$hoje = $dataLimit ? $dataLimit : date('Y-m-d');
			$configCurso = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$id_curso,'config');

			if($configCurso && !empty($configCurso)){

				$ret['configCurso'] = $configCurso;

				$arr_confCurso = json_decode($configCurso,true);

				$ret['arr_confCurso'] = $arr_confCurso;

				if(is_array($arr_confCurso)){

						//$hoje = dtBanco(CalcularDiasAnteriores(date('d/m/Y'),$diasAntes,$formato = 'd/m/Y'));
						$hoje = date('Y-m-d');
						// $where = " WHERE MONTH(inicio) >= '".date('m')."' AND  YEAR(inicio) >= '".date('Y')."' AND id_curso='".$id_curso."' AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";
						$where = " WHERE inicio >= '".$hoje."' AND id_curso='".$id_curso."' AND ativo='s' AND ".Qlib::compleDelete()." ORDER BY inicio ASC";
						// $where = " WHERE id_curso='".$id_curso."' AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";

						$turmaDisponivel = Qlib::dados_tab('turmas',['campos'=>'nome,id,inicio,fim,max_alunos,id_curso,config','where'=>$where]);

						$ret['todasTurmas'] = $turmaDisponivel;
						if(Qlib::isAdmin(1)){
							$ret['where'] = $where;
						}
						if($turmaDisponivel){
							$i=1;
							foreach($turmaDisponivel As $key=>$val){

								$turmaOcupada = Qlib::totalReg($GLOBALS['tab12'],"WHERE id_turma='".$val['id']."' AND status > '1' AND status < 5  AND ".Qlib::compleDelete());

								$turmaOcupada =$turmaOcupada?$turmaOcupada:0;

								if($turmaOcupada<$val['max_alunos']){

									$vagas = ($val['max_alunos']) - ($turmaOcupada);

									$val['vagas'] = $vagas;

									$val['turmaOcupada'] = $turmaOcupada;

									$ret['disponivel'] = true;

									$val['inicio'] = Qlib::dataExibe($val['inicio']);

									$val['fim'] = Qlib::dataExibe($val['fim']);
									$val['id_curso'] = $val['id_curso'];
									$val['prev_turma'] = $i;//para o recurso de previsionamento de turmas.
									if(isset($val['config'])){
										$val['config'] = Qlib::lib_json_array($val['config']);
										if(isset($val['config']['fds']['ativo']) && $val['config']['fds']['ativo']=='s'){
											//nesse caso pula
										}else{
											$i++;
										}
									}else{
										$i++;
									}
									//$ret['turmas'][$key] = $val;

									$ret['turmas'][$key] = $val;

									//break;

								}else{

									$ret['disponivel'] = false;

								}

							}

							$ret['turmaDisponivel'] = $turmaDisponivel;

						}else{

							$ret['disponivel'] = false;

						}

				}else{
					if(is_array($arr_confCurso)&& !empty($arr_confCurso['turma'])){

						$dados = QLib::dados_tab($GLOBALS['tab11'],['campos'=>'nome,id,inicio,fim,max_alunos','where'=>" WHERE id = '".$arr_confCurso['turma']."' AND ".Qlib::compleDelete()]);

						$ret['todasTurmas'] = $dados;

						if($dados){

							$val['inicio'] = Qlib::dataExibe($dados[0]['inicio']);

							$val['fim'] = Qlib::dataExibe($dados[0]['fim']);

							$val['id'] = $dados[0]['id'];

							$ret['turma'][0] = $val;

						}else{

							$ret['disponivel'] = false;

						}

					}else{

						$hoje = Qlib::dtBanco(Qlib::CalcularDiasAnteriores(date('d/m/Y'),$diasAntes,$formato = 'd/m/Y'));

						$where = " WHERE inicio >= '".$hoje."' AND id_curso='".$id_curso."' AND ativo='s' AND ".Qlib::compleDelete()." ORDER BY inicio ASC";

						//echo $where;exit;

						$turmaDisponivel = Qlib::dados_tab($GLOBALS['tab11'],['campos'=>'nome,id,inicio,fim,max_alunos','where'=>$where]);

						$ret['todasTurmas'] = $turmaDisponivel;



						if(isset($arr_confCurso['exigir_turma'])&&$arr_confCurso['exigir_turma']=='s')

							$ret['disponivel'] = false;

					}

				}

			}

		}

		//print_r($ret);exit;
		if(isset($_GET['fp']))
			dump($ret);
		return $ret;

	}
    /**
     * Cria um array com as turmas disponivel para serem exibidos na area de adminnstração
     */
	public function turmaDisponivelAdmin($id_curso=false,$dataLimit=false){

		$ret['disponivel'] = true;

		$ret['turmas'] = false;

		$ret['todasTurmas'] = false;
		if($id_curso){

			$diasAntes = queta_option('dias_turma_valida') ? queta_option('dias_turma_valida'):10;

			$hoje = $dataLimit ? $dataLimit : date('Y-m-d');

			$configCurso = buscaValorDb($GLOBALS['tab10'],'id',$id_curso,'config');

			if($configCurso && !empty($configCurso)){

				$ret['configCurso'] = $configCurso;

				$arr_confCurso = json_decode($configCurso,true);

				$ret['arr_confCurso'] = $arr_confCurso;
				$tipo_curso = cursos::tipo($id_curso);
				// if(isAdmin(1)){
				// 	dd($arr_confCurso);
				// }
				// if(is_array($arr_confCurso) && isset($arr_confCurso['exigir_turma'])&&$arr_confCurso['exigir_turma']=='s'){
				if(is_array($arr_confCurso)){

						$hoje = dtBanco(CalcularDiasAnteriores(date('d/m/Y'),$diasAntes,$formato = 'd/m/Y'));
						//$where = " WHERE inicio >= '".$hoje."' AND id_curso='".$id_curso."' AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";
						if($tipo_curso==4){
							//para o planos de formação incluir turmas dos ids 66,69,67,72,107,75,127,126
							// $arr_ids = [66,69,67,72,107,75,127,126];
							$arr_ids = [66,69,67,72,107,75,97,127,126,128];
							$or_ids = '(';
							foreach ($arr_ids as $key => $value) {
								$or = '';
								if($key>0){
									$or = ' OR';
								}								$or_ids .= "$or id_curso='".$value."'";

							}
							$or_ids .= ')';
							$where = " WHERE $or_ids AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";
						}elseif($id_curso==116 || $id_curso==129){
							//para o planos de formação incluir turmas dos ids 69,71,72,75,85
							$arr_ids = [69,71,72,75,85];
							$or_ids = '(';
							foreach ($arr_ids as $key => $value) {
								$or = '';
								if($key>0){
									$or = ' OR';
								}
								$or_ids .= "$or id_curso='".$value."'";

							}
							$or_ids .= ')';
							$where = " WHERE $or_ids AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";
						}else{
							$where = " WHERE id_curso='".$id_curso."' AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";
						}
						// if(isAdmin(1)){
						// 	// lib_print()
						// 	echo $where.'<br>';;
						// }
						$turmaDisponivel = Qlib::dados_tab($GLOBALS['tab11'],['campos'=>'nome,id,inicio,fim,max_alunos','where'=>$where]);
						// dd($turmaDisponivel);
						$ret['todasTurmas'] = $turmaDisponivel;

						if($turmaDisponivel){

							foreach($turmaDisponivel As $key=>$val){

								$turmaOcupada = Qlib::totalReg($GLOBALS['tab12'],"WHERE id_turma='".$val['id']."' AND status > '1' AND status < 5  AND ".Qlib::compleDelete());

								$turmaOcupada =$turmaOcupada?$turmaOcupada:0;

								$ret['todasTurmas'][$val['id']] = $val;
								$val['id_curso'] = $id_curso;

								// if(isAdmin(3)){

										//if($turmaOcupada<$val['max_alunos']){

											$vagas = ($val['max_alunos']) - ($turmaOcupada);

											$val['vagas'] = $vagas;

											$val['turmaOcupada'] = $turmaOcupada;

											$ret['disponivel'] = true;

											$val['inicio'] = dataExibe($val['inicio']);

											$val['fim'] = dataExibe($val['fim']);

											$ret['turmas'][$key] = $val;

											// $ret['turmas'][$key] = $val;

											//break;

										//}else{

											//$ret['disponivel'] = false;

										//}

								// }else{

								// 	if($turmaOcupada<$val['max_alunos']){

								// 		$vagas = ($val['max_alunos']) - ($turmaOcupada);

								// 		$val['vagas'] = $vagas;

								// 		$val['turmaOcupada'] = $turmaOcupada;

								// 		$ret['disponivel'] = true;

								// 		$val['inicio'] = dataExibe($val['inicio']);

								// 		$val['fim'] = dataExibe($val['fim']);

								// 		$ret['turmas'][$key] = $val;

								// 		// $ret['turmas'][$key] = $val;

								// 		//break;

								// 	}else{

								// 		$ret['disponivel'] = false;

								// 	}

								// }

							}

							$ret['turmaDisponivel'] = $turmaDisponivel;

						}else{

							$ret['disponivel'] = false;

						}
						// if(isAdmin(1)){
						// 	echo $where;
						// 	lib_print($ret);
						// }

				}else{

					if(is_array($arr_confCurso)&& !empty($arr_confCurso['turma'])){

						$dados = dados_tab($GLOBALS['tab11'],'nome,id,inicio,fim,max_alunos'," WHERE id = '".$arr_confCurso['turma']."' AND ".compleDelete());

						$ret['todasTurmas'] = $dados;

						if($dados){

							$val['inicio'] = dataExibe($dados[0]['inicio']);

							$val['fim'] = dataExibe($dados[0]['fim']);

							$val['id'] = $dados[0]['id'];

							$ret['turma'][0] = $val;

						}else{

							$ret['disponivel'] = false;

						}

					}else{

						$hoje = dtBanco(CalcularDiasAnteriores(date('d/m/Y'),$diasAntes,$formato = 'd/m/Y'));

						$where = " WHERE inicio >= '".$hoje."' AND id_curso='".$id_curso."' AND ativo='s' AND ".compleDelete()." ORDER BY inicio ASC";

						//echo $where;exit;

						$turmaDisponivel = dados_tab($GLOBALS['tab11'],'nome,id,inicio,fim,max_alunos',$where);

						$ret['todasTurmas'] = $turmaDisponivel;



						if(isset($arr_confCurso['exigir_turma'])&&$arr_confCurso['exigir_turma']=='s')

							$ret['disponivel'] = false;

					}

				}

			}

		}




		//print_r($ret);exit;

		return $ret;

	}

	/**
	 * tabela de parcelamento do cliente
	 */
	public function tabela_parcelamento_cliente($id_tabela = null,$ctax='tx2'){
		$ret = false;
		if($id_tabela){
			$dadosTabela = Qlib::dados_tab('parcelamento',['comple_sql'=>"WHERE id='".$id_tabela."'"]);
			if($dadosTabela){
				// $ret['dadosTabela'] = $dadosTabela;
				$obs = $dadosTabela[0]['obs'];
				if(isset($dadosTabela[0]['config'])){
					$arr_t_c=Qlib::lib_json_array($dadosTabela[0]['config']);
					$tm1 = '
					<table class="table table-striped" style="width:100%">
					<thead>
						<tr>
							<th style="width:50%"><h3>Mensalidades</h3></th>
							<th style="width:50%">&nbsp;</th>
						</tr>
					</thead>
					<tbody>
					{li}
					</tbody>
					</table>';
					if((new MatriculasController)->is_pdf()){
						$tm2 = '
						<tr>
							<td style="width:50%">{title}</td>
							<td style="width:50%"><div align="rigth">{value}</div></td>
						</tr>';
					}else{
						$tm2 = '
						<tr>
							<td>{title}</td>
							<td class="text-right">{value}</td>
						</tr>';
					}
					$li=false;
					if(isset($arr_t_c[$ctax]) && is_array($arr_t_c)){
						foreach ($arr_t_c[$ctax] as $key => $va) {
							$li .= str_replace('{title}',$va['name_label'],$tm2);
							$li = str_replace('{value}',$va['name_valor'],$li);
						}
					}
					$ret = str_replace('{li}',$li,$tm1);
				}
			}
		}
		return $ret;

	}
    public function short_codes_Plano($token_matricula=false,$tema=false){
		$ret = false;
		if($token_matricula && $tema){
			$ret = $tema;
			$p = $this->verificaPlano(['token_matricula' => $token_matricula]);
			if(isset($p['exec'])){
				$m = isset($p['dadosMatricula'][0])?$p['dadosMatricula'][0]:[]; //dados do orçamento
				$t = isset($p['dadosTabela'])?$p['dadosTabela']:[]; //dados da tabela de financiamento
				$pe = isset($p['dadosPlano'][0])?$p['dadosPlano'][0]:[]; //dados da Pano de financiamento escolhido
				$json_contrato = isset($m['contrato'])?$m['contrato']:[]; //dados do contrato
				$arr_contrato = Qlib::lib_json_array($json_contrato);
				$taxa_juros = false;
				$taxa_iof = isset($t['iof']) ? $t['iof'] :0;
				$parcelas = @$pe['parcelas'];
				if(isset($t['parcelas']) && !empty($t['parcelas'])){
					$arr_parcel = Qlib::lib_json_array($t['parcelas']);
					if(is_array($arr_parcel)){
						foreach ($arr_parcel as $kpar => $vpar) {
							if($vpar['parcela']==$parcelas){
								$taxa_juros = @$vpar['juros']; //parei aqui
							}
						}
					}
				}
				if($taxa_juros){
					$taxa_juros = str_replace('%','',$taxa_juros);
				}
				$valor_total_credito = $m['total'];
				// if($valor_total_credito){
				// 	$valor_total_credito = ;
				// }
				$valor_financiado = $valor_total_credito;
				$custo_efetivo_mes = (double)$taxa_iof+(double)str_replace(',','.',$taxa_juros);
				$custo_efetivo_ano = $custo_efetivo_mes * 12;
				$valor_tarifas = 0;
				$vencimento_primeira_parcela = @$pe['data_pri_cob'];
                if(is_null($vencimento_primeira_parcela)){
                    $vencimento_ultima_parcela = null;
                }else{
                    $vencimento_ultima_parcela = Qlib::CalcularVencimentoMes(Qlib::dataExibe($vencimento_primeira_parcela),$parcelas,'d/m/Y',false);
                }
				$tem_fiador = 'Não';
				if(isset($m['fiador']) && !empty($m['fiador'])){
					$tem_fiador = 'Sim';
				}
				// dd($pe);
				if(isset($m['orc']) && isset($m['token']) && !empty($m['orc'])){
					// $orc = gerarOrcamento($m['token']);
					$arr_orc = Qlib::lib_json_array($m['orc']);
					if(isset($arr_orc['taxas2']) && is_array($arr_orc['taxas2'])){
						foreach ($arr_orc['taxas2'] as $ktx => $vtx) {
							if(isset($vtx['name_valor']) && !empty($vtx['name_valor'])){
								$valor_tarifas += (double)str_replace(',','.',$vtx['name_valor']);
							}
						}
					}
				}
				// dd($arr_contrato);
				// dd($p);
				$arr = [
					'data_contrato_aceito'=>Qlib::dataExibe(@$arr_contrato['data_aceito_contrato']),
					'valor_total_credito'=>number_format($valor_total_credito,2,',','.'),
					'parcelas'=>$parcelas,
					'valor_parcelas'=>@$pe['valor'],
					'taxa_juros'=>$taxa_juros.'%',
					'taxa_iof'=>$taxa_iof.'%',
					'custo_efetivo_mes'=>$custo_efetivo_mes.'%',
					'custo_efetivo_ano'=>$custo_efetivo_ano.'%',
					'total_tarifas'=>number_format($valor_tarifas,2,',','.'),
					'valor_tarifas'=>number_format($valor_tarifas,2,',','.'),
					'valor_financiado'=>number_format($valor_financiado,2,',','.'),
					'vencimento_primeira_parcela'=>Qlib::dataExibe($vencimento_primeira_parcela),
					'vencimento_ultima_parcela'=>$vencimento_ultima_parcela,
					'tem_fiador'=>$tem_fiador,
				];
				foreach ($arr as $k => $v) {
					if(!empty($v)) {
						$ret = str_replace('{'.$k.'}', $v,$ret);
					}
				}
			}
		}
		return $ret;
	}
	public function verificaPlano($config=false){

		$ret['exec'] = false;

		if(isset($config['token_matricula'])){

			$compleSql = isset($config['compleSql']) ? $config['compleSql'] : false;

			// $dadosPlano = dados_tab('lcf_planos As p','p.*,m.id_curso',"
			// JOIN ".$GLOBALS['tab12']." As m ON m.token=p.token_matricula
			// WHERE token_matricula='".$config['token_matricula']."' $compleSql");
			$dadosPlano = Qlib::dados_tab('lcf_planos',['campos'=>'*','where'=>"WHERE token_matricula='".$config['token_matricula']."' $compleSql"]);

			if($dadosPlano){
				$ret['exec'] = true;
				if(isset($dadosPlano[0]['config'])){
					$dadosPlano[0]['config'] = Qlib::lib_json_array($dadosPlano[0]['config']);
					if(isset($dadosPlano[0]['config']['id'])){
						$dt = Qlib::dados_tab('parcelamento',['campos'=>'*','where'=>"WHERE id='".$dadosPlano[0]['config']['id']."'"]);
						if($dt){
							$ret['dadosTabela'] = $dt[0];
						}
					}
				}
				$ret['dadosPlano'] = $dadosPlano;
			}
			$ret['dadosMatricula'] = false;
			$dadosMatricula = (new MatriculasController)->dm($config['token_matricula']);
			if($dadosMatricula){
				$ret['dadosMatricula'][0] = $dadosMatricula;
			}



		}

		return $ret;

	}
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
