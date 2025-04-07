<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Qlib\Qlib;
use App\Http\Controllers\api\ZapsingController;
use App\Jobs\GeraPdfContratoJoub;
use App\Jobs\SendZapsingJoub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MatriculasController extends Controller
{
    public $table;
    public $campo_contrato_financeiro;
    public function __construct()
    {
        $this->table = 'matriculas';
        global $tab10,$tab11,$tab12,$tab15,$tab54;
        $tab10 = 'cursos';
        $tab11 = 'turmas';
        $tab12 = 'matriculas';
        $tab15 = 'clientes';
        $tab54 = 'aeronaves';
        $this->campo_contrato_financeiro = 'contrato_financiamento_horas';
    }
    public function index(Request $request)
    {
        // dd($request->get('status'));
        $d = DB::table($this->table)->select('matriculas.*','clientes.Nome','clientes.sobrenome','clientes.Email')
        ->join('clientes', 'clientes.id','=','matriculas.id_cliente')
        ->where('matriculas.excluido','=','n')->where('matriculas.deletado','=','n')->orderBy('matriculas.id','asc');
        $limit = 25;
        if($request->has('limit')){
            $limit = $request->get('limit');
        }
        if($request->has('status')){
            if($request->get('status')=='todos_matriculados'){
                $d = $d->where('matriculas.status', '!=',1);
            }else{
                $d = $d->where('matriculas.status', '=',$request->get('status'));
            }
        }
        if($request->has('token_externo')){
            $tkex = $request->get('token_externo');
            if($tkex=='null'){
                $d = $d->whereNull('matriculas.token_externo');
            }elseif(is_null($tkex)){
                $d = $d->whereNotNull('matriculas.token_externo');
            }else{
                $d = $d->where('matriculas.token_externo', '=',$request->get('token_externo'));
            }
        }
        if($request->has('id_cliente')){
            $d = $d->where('matriculas.id_cliente', '=',$request->get('id_cliente'));
        }
        if($limit=='todos'){
            $d = $d->get();
        }else{
            $d = $d->paginate($limit);
        }
        $exibe_contrato = $request->has('contrato') ? $request->has('contrato') : 's';
        $ret['exec'] = false;
        $ret['status'] = 404;
        $ret['total'] = 0;
        $ret['data'] = [];
        if($d->count() > 0){
            if($exibe_contrato=='s'){
                foreach ($d as $k => $v) {
                    if($nc=$this->numero_contrato($v->id)){
                        $d[$k]->numero_contrato = $nc;
                    }
                }
            }
            $ret['total'] = $d->count();
            $ret['data'] = $d;
            $ret['exec'] = true;
            $ret['status'] = 200;
        }
        return $ret;
    }
    /**
     * Metodo para exibir o numero do contrato
     * @param int $id_matricula
     */
    public function numero_contrato($id_matricula=false){
        $ret = false;
        if($id_matricula){
            //uso $ret = (new CursosController)->numero_contrato($id_matricula);
            $ret = false;
            if($id_matricula){
                $json_contrato = Qlib::buscaValorDb0('matriculas','id',$id_matricula,'contrato');
                $arr_contrato = Qlib::lib_json_array($json_contrato);
                if(isset($arr_contrato['data_aceito_contrato']) && !empty($arr_contrato['data_aceito_contrato'])){
                    $arrd = explode('-',$arr_contrato['data_aceito_contrato']);
                    if(isset($arrd[1])){
                        $ret = $id_matricula.'.'.$arrd[1].'.'.$arrd[0];
                    }
                }

            }
            return $ret;
        }
    }
    /**
     * Metodos para salvar um orçamento assinado para ser exibido dps no painel do CRM.
     * @param string $token_matricula,array $dm= dados da matricula
     */
    public function salva_orcamento_assinado($token=false,$dm=false){
        $ret['exec'] = false;
        $campo_meta1 = 'assinado';
        $campo_meta2 = 'contrato_assinado';
        // $campo_meta3 = 'total_assinado';
        if($token && !$dm){
            $dm = Matricula::where('token',$token)->get();
            if($dm->count() > 0){
                $dm = $dm[0];
            }
        }
        $ret['dm'] = $dm;
        if(isset($dm['id']) && isset($dm['contrato']) && isset($dm['orc'])  && isset($dm['total'])){
            $ret['s1'] = Qlib::update_matriculameta($dm['id'],$campo_meta1,'s');
            $ret['s2'] = Qlib::update_matriculameta($dm['id'],$campo_meta2,Qlib::lib_array_json([
                'orc'=>$dm['orc'],
                'totais'=>@$dm['totais'],
                'subtotal'=>@$dm['subtotal'],
                'total'=>@$dm['total'],
                'cliente_id'=>@$dm['id_cliente'],
                'porcentagem_comissao'=>@$dm['porcentagem_comissao'],
                'comissao'=>@$dm['valor_comissao'],
            ]));
            if($ret['s1'] && $ret['s2']){
                $ret['exec'] = true;
            }
        }
        // $ret['dm'] = $dm;
        return $ret;
    }
    /**
     * Metodo para retornar um array com os dados do contrato assinado
     */
    public function get_matricula_assinado($token=false){
        $matricula_id = Qlib::get_matricula_id_by_token($token);
        //verifica se está assinado
        $ret['exec'] = false;
        $ret['data'] = [];
        if(!$matricula_id){
            return $ret;
        };
        $campo_meta1 = 'assinado';
        $campo_meta2 = 'contrato_assinado';
        $ver = Qlib::get_matriculameta($matricula_id,$campo_meta1,true);
        if($ver=='s'){
            $data = Qlib::get_matriculameta($matricula_id,$campo_meta2,true);
            if($data){
                $ret['exec'] = true;
                $dm = $this->dm($token);
                // if(!$dm){

                // }
                $ret['dm'] = $dm;
                $ret['data'] = Qlib::lib_json_array($data);
                $aer = DB::table('aeronaves')->where('excluido', '=','n')->where('deletado', '=','n')->get();
                $aeronaves_arr = [];
                if(count($aer)!=0){
                    foreach ($aer as $ka => $va) {
                        $aeronaves_arr[$va->id] = $va->nome;
                    }
                }
                $ret['aeronaves'] = $aeronaves_arr;
            }
        }
        return $ret;
    }
    public function link_orcamento($token){
        return Qlib::qoption('dominio').'/solicitar-orcamento/proposta/'.$token;
    }
    public function link_assinatura($token){
        return Qlib::qoption('dominio').'/solicitar-orcamento/proposta/'.$token.'/f/1';
    }
    /**
     * Dados de um orçamento ou dados da matricula
     * @param string $token
     * @param array $ret
     */
    public function dm($token){
        $dm = Matricula::select('matriculas.*',
        'clientes.Nome','clientes.sobrenome','clientes.telefonezap','clientes.Tel','clientes.Email','clientes.Cpf as cpf_aluno',
        'clientes.nacionalidade',
        'clientes.Endereco',
        'clientes.Numero',
        'clientes.Bairro',
        'clientes.Cidade',
        'clientes.Uf',
        'clientes.Cep As cep',
        'clientes.Compl',
        'clientes.Ident As identidade',
        'clientes.estado_civil',
        'clientes.profissao',
        'cursos.tipo as tipo_curso','cursos.config','cursos.modulos as modulos_curso','cursos.parcelas as parcelas_curso','cursos.valor_parcela as valor_parcela_curso','cursos.nome as nome_curso','cursos.titulo as titulo_curso','cursos.inscricao as inscricao_curso','cursos.valor as valor_curso','cursos.token as token_curso')
        ->join('clientes','matriculas.id_cliente','=','clientes.id')
        ->join('cursos','matriculas.id_curso','=','cursos.id')
        ->where('matriculas.token',$token)
        ->get();
        if($dm->count() > 0){
            $dm = $dm->toArray();
            $dm = $dm[0];
            $link_orcamento = $this->link_orcamento($dm['token']);
            $link_assinatura = $this->link_assinatura($dm['token']);
            if(isset($dm['contrato']) && is_string($dm['contrato'])){
                if(json_validate($dm['contrato'])){
                    $dm['contrato'] = Qlib::lib_json_array($dm['contrato']);
                }
            }
			$dm['link_orcamento'] = $link_orcamento;
			$dm['link_assinatura'] = $link_assinatura;
			$dm['numero_contrato'] = $this->numero_contrato($dm['id']);
            $dm['nome_completo'] = str_replace($dm['sobrenome'],'',$dm['Nome']) .' '.trim($dm['sobrenome']);
			// $dm['consultor'] = $dm['seguido_por'];
			$link_guru = isset($dm['zapguru']) ? $dm['zapguru'] : false;
			if(is_string($link_guru)){
				$arr_link = Qlib::lib_json_array($link_guru);
				$link_guru = isset($arr_link['link_chat']) ? $arr_link['link_chat'] : '';
			}
			$dm['link_guru'] = $link_guru;
            $dm['valor_orcamento'] = $dm['total'];
            if(isset($dm['desconto']) && $dm['desconto'] > 0){
                $dm['valor_orcamento'] = $dm['subtotal']-$dm['desconto'];
            }

        }else{
            return false;
        }
        $ret = $dm;
        return $ret;
    }
    // public function gerenciarPromocao($totalOrcamento,$id_curso){
    //     global $tab73;
    //     $id_curso = addslashes('"'.$id_curso.'"');

    //     $sql = "SELECT * FROM `".$GLOBALS['tab73']."` WHERE `id_produto` LIKE '%".$id_curso."%' AND `ativo`='s' AND `inicio` <= '".date('Y-m-d H:m:i')."' AND `fim` >= '".date('Y-m-d H:m:i')."' AND `quantidade` > '0'";
    //     if(isset($_GET['f']))
    //         echo $sql;
    //     // $dados = Qlib::buscaValoresDb($sql);
    //     $dados = false;

    //     $ret['precoInicial'] = $totalOrcamento;

    //     $ret['precoInicial_html'] = '<span class="preco-custo">R$ '.number_format($totalOrcamento,'2',',','.').'</span>';

    //     $ret['precoFinal_html'] = false;

    //     //print_r($dados);

    //     if($dados){

    //         $ret['precoInicial'] = $totalOrcamento;

    //         $ret['tipo_reducao'] = $dados['tipo_reducao'];

    //         $ret['valor_reducao'] = $dados['valor'];

    //         $ret['id_cupom'] = $dados['id'];

    //         if($dados['tipo_reducao'] == 'valor'){

    //             $ret['precoFinal'] = ($ret['precoInicial']) - ($dados['valor']);

    //             $ret['precoInicial_html'] = '<span class="preco-custo riscado">De: R$ '.number_format($ret['precoInicial'],'2',',','.').'</span><br>';

    //             $ret['precoFinal_html'] = '<span class="preco-custo">Por: R$ '.number_format($ret['precoFinal'],'2',',','.').'</span>';

    //         }elseif($dados['tipo_reducao'] == 'porcentagem'){

    //             $porce = ($ret['precoInicial']) * ($dados['valor'] / 100) ;

    //             $ret['valorPorcentagem'] = $porce;

    //             $ret['precoFinal'] = ($ret['precoInicial']) - ($porce);

    //             $ret['precoInicial_html'] = '<span class="preco-custo riscado">De: R$ '.number_format($ret['precoInicial'],'2',',','.').'</span><br>';

    //             $ret['precoFinal_html'] = '<span class="preco-custo">Por: R$ '.number_format($ret['precoFinal'],'2',',','.').'</span>';

    //         }

    //     }else{

    //             $ret['precoFinal'] = $ret['precoInicial'];

    //             //$ret['precoInicial_html'] = '<span class="preco-custo riscado">De: R$ '.number_format($ret['precoInicial'],'2',',','.').'</span><br>';

    //             //$ret['precoFinal_html'] = '<span class="preco-custo">Por: R$ '.number_format($ret['precoFinal'],'2',',','.').'</span>';

    //     }

    //     return $ret;

    // }
    public function tag_apresentacao_orcamento($dados){
        $dadosD = explode(' ',$dados['atualizado']);
        $dias = isset($dias)?$dias: 7;
        $validade = Qlib::CalcularVencimento(Qlib::dataExibe($dadosD[0]),$dias);
        $nome_completo = isset($dados['nome_completo']) ? $dados['nome_completo'] : $dados['Nome'].' '.$dados['sobrenome'];
        $ret = '
                <p align="center" style="font-size:15pt;">
                    <b>Cliente:</b> '.$nome_completo.'  <b>N°: </b> '.$dados['id'].'
                    <br>
                    <b>Telefone:</b> '.$dados['telefonezap'].'  '.$dados['Tel'].' <br>
                    <b>Email:</b> '.$dados['Email'].'  <br>
                    <b>Data:</b> '.Qlib::dataExibe($dados['atualizado']).' <b>Validade:</b> '.Qlib::dataExibe($validade).'<br>
                </p>';
        return $ret;
    }
    /**
     * Metodo para gerar um orçamento atualizado
     * @param string $tokenOrc token do orçamento
     * @param string $exibir_parcelamento 's' para sim 'n' para não
     */
    public function gerar_orcamento($tokenOrc,$exibir_parcelamento=false){
        global $tab10,$tab12,$tab15,$tab50;
        $tab10 = 'cursos';
        $tab15 = 'clientes';
        $tab12 = 'matriculas';
        $tab50 = 'tabela_nomes';
        if($tokenOrc){
            $mensComb = false;
            $tab12 = 'matriculas';
			$is_signed = $this->verificaDataAssinatura(['campo_bus'=>'token','token'=>$tokenOrc]);
			$arr_tabelas = Qlib::sql_array("SELECT * FROM $tab50 WHERE ativo = 's' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'nome','url');
			$dados = $this->dm($tokenOrc);

			$dias = isset($dias)?$dias: Qlib::qoption('validade_orcamento');
            if($dados){
				$dadosOrc = false;
				$tipo_curso = $dados['tipo_curso'];
				$valor_combustivel = 0;
                $btn_aceito_aceitar = '';
                if(isset($dados['config']) && !empty($dados['config'])){
                    $dados['config'] = Qlib::lib_json_array($dados['config']);
                }
                // $aceito_proposta = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$_GET['tk'],'contrato');
				// $arr_aceito = Qlib::lib_json_array($aceito_proposta);
				if($is_signed){
                    // $men = 'Proposta aceita em '.Qlib::dataExibe(@$arr_aceito['data_aceito_contrato']).' Ip: '.$arr_aceito['ip'].'';
					$men = 'Proposta aceita em '.Qlib::dataExibe($is_signed).'';
					$btn_a = '<span style="color:#b94a48">'.__($men).'</span>';
				}else{
                    $btn_aceito_proposta = (new SiteController)->short_code('btn_aceito_proposta',false,false);
                    $link = 'https://crm.aeroclubejf.com.br/solicitar-orcamento/proposta/'.$tokenOrc;
					$btn_a = '<a href="'.$link.'" target="_BLANK" style="display:block;height: 65px; width:250px"><span style="display:none;">cliente aqui</span><img src="'.@$btn_aceito_proposta[0]['url'].'" style="width:250px;cursor:pointer"/></a>';
				}
				$btn_aceito_aceitar = '<div align="center">'.$btn_a.'</div>';
				if(!empty($dados['orc'])){
                        if(is_array($dados['orc'])){
                           $dadosOrc = $dados['orc'];
                        }else{
                           $dadosOrc = json_decode($dados['orc'],true);
                        }
					//$dadosOrc['desconto_porcento'] = Qlib::buscaValorDb0($GLOBALS['tab50'],'');
					$dados['sele_valores'] = @$dadosOrc['sele_valores'];
					$arr_config_tabela = array();
					// lib_print($dadosOrc['tipo']);
                    if(isset($dadosOrc['sele_valores']) && !empty($dadosOrc['sele_valores'])){
						$ret['tabela_preco'] = $dadosOrc['sele_valores'];
						$configTabela = Qlib::buscaValorDb0($tab50,'url',$dadosOrc['sele_valores'],'config');
						if(!empty($configTabela)){
                            $arr_config_tabela = json_decode($configTabela,true);
                            $tipo_desconto = isset($arr_config_tabela['desconto']['tipo']) ? $arr_config_tabela['desconto']['tipo'] : '';
                            $valor_desconto = isset($arr_config_tabela['desconto']['valor']) ? $arr_config_tabela['desconto']['valor'] : '';
							// if(isset($arr_config_tabela['desconto']['valor']) && !empty($arr_config_tabela['desconto']['valor']) && $tipo_desconto=='porcentagem'){
                            //     $dadosOrc['desconto_porcento'] = $valor_desconto;
							// }
                            if($tipo_desconto=='porcentagem' && !empty($valor_desconto)){
                                $dadosOrc['desconto_porcento'] = $valor_desconto;
                            }
                            if($tipo_desconto=='valor' && !empty($valor_desconto)){
                                $dadosOrc['desconto'] = $valor_desconto;
                            }
                            if(isset($arr_config_tabela['validade']['dias'])&&!empty($arr_config_tabela['validade']['dias'])){
                                $dias = $arr_config_tabela['validade']['dias'];
							}
                            // dump($arr_config_tabela,$configTabela);
						}
					}
					$dados['modulos'] = @$dadosOrc['modulos'];
					$dados['taxas'] = @$dadosOrc['taxas'];
					$dados['combustivel'] = isset($dadosOrc['combustivel'])?$dadosOrc['combustivel']:false;
					$dados['desconto_porcento'] = @$dadosOrc['desconto_porcento'];
					if(isset($dadosOrc['desconto']) && !empty($dadosOrc['desconto'])){
						$dados['desconto'] = $dadosOrc['desconto'];
						$dados['desconto'] = Qlib::precoDbdase(@$dados['desconto']);
					}
					$dados['entrada'] = @$dadosOrc['entrada'];
					$dados['entrada'] = Qlib::precoDbdase($dados['entrada']);
				}
				$ret['nome_arquivo'] = 'Proposta '.$dados['id'];
				if($dados['tipo_curso']==2){
					$configMet=$dados;
					$configMet['email'] = $dados['Email'];
					// metricasOrcamento($configMet);//para salvar as estatisticas do orçamento;
					$arr_wid = array('5%','50%','25%','10%','10%');
					if(!isset($dados['sele_valores'])){
						$ret['table'] = Qlib::formatMensagem0('Erro: Tabela não selecionada!!','danger',100000);
						return $ret;
					}
					$label_sele_valores = isset($arr_tabelas[$dados['sele_valores']])?$arr_tabelas[$dados['sele_valores']]:false;
					$arr_wid2 = array('5%','20%','70%','10%');
					if(isset($dados['Nome']) && isset($dados['nome_curso'])){
						$ret['id_curso'] = $dados['id_curso'];
						$dadosD = explode(' ',$dados['atualizado']);
						// $valdata = explode('-',$dadosD[0]);
						$ret['nome_arquivo'] = 'Proposta '.$dados['id']. ' '.$dados['Nome'].' '.$dados['nome_curso'];
						//$validade = ultimoDiaMes($valdata[1],$valdata[0]).'/'.$valdata[1].'/'.$valdata[0];
						if(!$dias){
                            $dias = 7;
                        }
                        $dadosD = explode(' ',$dados['atualizado']);
                        $validade =  Qlib::CalcularVencimento(Qlib::dataExibe($dadosD[0]),$dias);
                        $validade = Qlib::dataExibe($validade);
                        $dadosCli = $this->tag_apresentacao_orcamento($dados);
                        if($this->is_pdf()){
                            $dadosCli .= $btn_aceito_aceitar;
                        }
						$ret['validade'] = $validade;
						// $ret['dadosCli'] = '<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>';
						$ret['dadosCli'] = $dadosCli;
						$ret['desconto'] = $dados['desconto'];
						if(isset($dados['desconto']) && $dados['desconto']>0){
							$espacoTable = false;
						}else{
							//$espacoTable = '<p></p>';
							$espacoTable = false;
						}
						if(isset($dados['modulos']) && !empty($dados['modulos'])){
							if(is_array($dados['modulos'])){
								$arr_modu = $dados['modulos'];
							}else{
								$arr_modu = json_decode($dados['modulos'],true);
							}
							$ret['vencido'] = false;
							if(strtotime(Qlib::dtBanco($validade))<strtotime(date('Y-m-d'))){
								//$ret['table'] = 'Orçamento válido até '.$validade.'';
								$ret['table'] = '<div class="col-md-12 mt-3 mb-3" style="color:#dc3545;font-size:20px;text-align:center"><b >SEU ORÇAMENTO EXPIROU, SOLICITE AO SEU CONSULTOR UM ORÇAMENTO ATUALIZADO</b></div>';
								$ret['table_adm'] = $ret['table'];
								$ret['totalCurso'] = NULL;
								$ret['vencido'] = true;
								//if(Qlib::isAdmin(2)){
								//}else{
									return $ret;
								//}
							}
							$tema = '
							<p class="apresentacao" style="">Prezado(a) <strong>'.$dados['Nome'].'</strong>,<br>
								Temos o prazer em lhe apresentar nossa proposta comercial<br>Curso: <strong>'.$dados['titulo_curso'].'</strong></p>
							<table id="table1" class="table"  cellspacing="0" >

												<thead >

													<tr>

														<th style="width:'.$arr_wid[0].'"><div align="center">ITEM</div></th>

														<th style="width:'.$arr_wid[1].'"><div align="center">CRONOGRAMA</div></th>

														<th style="width:'.$arr_wid[2].'"><div align="center">AERONAVE</div></th>

														<th style="width:'.$arr_wid[3].'"><div align="center">HORAS</div></th>

														<th style="width:'.$arr_wid[4].'"><div align="right">VALOR</div></th>

													</tr>

												</thead>

												<tbody class="jss526">{{table}}

												</tbody>

												<tfoot class="jss526">{{footer}}

												</tfoot>

							</table>
							<br><br>
							<table id="table2" class="table" cellspacing="0" style="">
								<thead >
									<tr>
										<th style="width:'.$arr_wid2[0].'"><div align="center">ITEM</div></th>
										<th style="width:85%"><div align="center">DESCRIÇÃO</div></th>
										<th style="width:'.$arr_wid2[3].'"><div align="right">TOTAL</div></th>
									</tr>
								</thead>
								<tbody class="jss526">{{table2}}
								</tbody>
							</table>'.$espacoTable.'
							<table cellspacing="0" class="table">
									<tbody class="jss526">
                                        {{table3}}
									</tbody>
							</table>
							<p style="font-family:arial;font-size:9pt;text-align:right;display:none">*'.$label_sele_valores.'</p>
							';
							$tema_admn = '
							<div class="col-md-12">
								<div class="table-responsive padding-none tabe-1">
									<table id="table-admin" class="table table-striped table-hover">
										<thead >
											<tr class="th-1">
												<th style="width:100%" colspan="5"><div align="center">&nbsp;</div></th>
											</tr>
											<tr>
												<th style="width:'.$arr_wid[0].'"><div align="center">ITEM</div></th>
												<th style="width:'.$arr_wid[1].'"><div align="center">CRONOGRAMA</div></th>
												<th style="width:'.$arr_wid[2].'"><div align="center">AERONAVE</div></th>
												<th style="width:'.$arr_wid[3].'"><div align="center">HORAS/AULA</div></th>
												<th style="width:'.$arr_wid[4].'"><div align="right">TOTAL</div></th>
											</tr>
										</thead>
										<tbody class="jss526">{{table}}
										</tbody>
										<tfoot class="jss526">{{footer}}
										</tfoot>
									</table>
								</div>
								<br>
								<div class="table-responsive padding-none tabe-2">
									<table id="table3" class="table" cellspacing="0"  style="border-spacing:6px 12px;padding:10px 4px 10px 4px">
										<thead >
											<tr>
												<th style="width:'.$arr_wid2[0].'"><div align="center">ITEM</div></th>
												<th  style="width:'.$arr_wid2[2].'"><div align="center">DESCRIÇÃO</div></th>
												<th style="width:'.$arr_wid2[3].'"><div align="right">TOTAL</div></th>
											</tr>
										</thead>
										<tbody class="jss526">{{table2}}
										</tbody>
									</table>'.$espacoTable.'
								</div>
								<div class="table-responsive padding-none tabe-3">
									<table class="table" >
										<tbody class="">
                                            <tr>{{table3}}</tr>
										</tbody>
									</table>
									<p style="font-family:arial;font-size:9pt;text-align:right">*'.$label_sele_valores.'</p>
								</div>
							</div>
							<!--<div class="row">
							{link_proposta}
							</div>-->
							';
							$tr = false;
							$tr2 = false;
							$tr3 = NULL;
							$tr_adm = false;
							$tr2_adm = false;
							$tr3_adm = NULL;
							$i = 1;
							$i2 = 1;
							$totalHoras = 0;
							$totalCurso = NULL;
							$total_com_desconto = NULL;
							$descontoFooter = NULL;
							if(is_array($arr_modu)){
								$ret['total'] = NULL;
								$ret['total_com_desconto'] = NULL;
								$salvaTotais = [];
								$arrTotais = false;
								if(isset($dados['totais']) && QLib::isJson($dados['totais'])){
									$arrTotais = Qlib::lib_json_array($dados['totais']);
								}
								$_GET['id_turma'] = @$dados['id_turma'];
								$arr_totais = [];
								// if(Qlib::isAdmin(1)){
									// echo $is_signed;
									$arr_totais = Qlib::lib_json_array($dados['totais']);
								// }
                                      $ret['custo'] = 0;
								foreach($dados['modulos'] AS $kei=>$valo){
									$valo['id_curso'] = @$dados['id_curso'];
									$tota = $this->calcPrecModulos($valo,$dados['sele_valores'],$arr_modu);
									$total = @$tota['padrao']; //usa so valor da hora padrão
									$total_com_desconto = @$tota['valor']; //usa o valor das horas das respectiva tabelas
									if(Qlib::isAdmin(10)){
									}else {
										if($arrTotais && is_array($arrTotais)){
											$total = isset($arrTotais[$kei]) ? $arrTotais[$kei] : 0; ;
										}
									}
									if($is_signed){
										$total = @$arr_totais[$kei];
									}
									$ret['total'] += (double)$total;
									$ret['total_com_desconto'] += (double)$total_com_desconto;
									$salvaTotais[$kei] = @$tota['valor'];
									if(isset($tota['custo'])){
										$custo = @$tota['custo'];
										$ret['custo'] += $custo;
									}
									$valo['horas'] = isset($valo['horas'])?$valo['horas']:0;
									$valo['horas'] = (int)$valo['horas'];
									$totalHoras += @$valo['horas'];
                                  	$tr .= '<tr id="lin_'.$kei.'">
                                  				<td style="width:'.$arr_wid[0].'"><div align="center">'.$i.'</div></td>
                                  				<td style="width:'.$arr_wid[1].'"><div align="left">'.@$valo['titulo'].'</div></td>
                                  				<td style="width:'.$arr_wid[2].'"><div align="center">'.Qlib::buscaValorDb0($GLOBALS['tab54'],'id',@$valo['aviao'],'nome').'</div></td>
                                  				<td style="width:'.$arr_wid[3].'"><div align="center">'.@$valo['horas'].'</div></td>
                                  				<td style="width:'.$arr_wid[4].'"><div align="right"> '.@number_format($total,'2',',','.').'</div></td>
                                  			</tr>
                                  	';
                                  	$tr_adm .= '<tr id="lin_'.$kei.'">
                                  				<td><div align="center">'.$i.'</div></td>
                                  				<td><div align="left">'.@$valo['titulo'].'</div></td>
                                  				<td><div align="center"> '.Qlib::buscaValorDb0($GLOBALS['tab54'],'id',@$valo['aviao'],'nome').'</div></td>
                                  				<td><div align="center">'.$valo['horas'].'</div></td>
                                  				<td><div align="right"> '.@number_format($total,'2',',','.').'</div></td>
                                  			</tr>
                                  	';
                                  	$i++;
                                }
								$ret['salvaTotais'] = $salvaTotais;
								$subtotal1 = $ret['total'];
								$subtotal1comDesconto = $ret['total_com_desconto'];
								/*Desconto*/
								$footer = '';
								$totalCurso = $subtotal1;
								//precisamos verificar se o total padrão é maior que o valor
								// if($subtotal1>$subtotal1comDesconto){
								// 	$descontoFooter = NULL;
								// 	$footer .= '
								// 	<tr>
								// 		<td colspan="3"><div align="right"> Subtotal</div></td>
								// 		<td><div align="center"><b>'.$totalHoras.'</b></div></td>
								// 		<td><div align="right"><b>'.number_format($totalCurso,'2',',','.').'</b></div></td>
								// 	</tr>';
								// 	//verificar qual o valor da diferença por isso é o desconto aplicado em cima do valor padrão
								// 	$desconto0 = (double)$subtotal1 - (double)$subtotal1comDesconto;
								// 	if($desconto0>0){
								// 		$descontoFooter .= '
								// 		<tr class="vermelho">
								// 			<td colspan="4">
								// 				<div align="right"><strong>DESCONTO</strong></div>
								// 			</td>
								// 			<td>
								// 				<div align="right"><b> '.number_format($desconto0,'2',',','.').'</b></div>
								// 			</td>
								// 		</tr>';
								// 		$totalCurso = ($totalCurso) - $desconto0;
								// 	}
								// 	$descontoFooter .= '<tr class="verde"><td colspan="4" class="total-curso"><div align="right"><strong>Total do curso:</strong></div></td><td class="total-curso"><div align="right"><b> '.number_format($totalCurso,'2',',','.').'</b></div></td></tr>';
								// 	$subtotal1 = $totalCurso;
								// }
                                $desconto_especial = Qlib::get_matriculameta($dados["id"],'desconto_especial');

								$desconto_turma = Qlib::get_matriculameta($dados["id"],'desconto');
                                if($desconto_turma){
                                    $desconto_turma = Qlib::precoBanco($desconto_turma);
                                    // $desconto_turma = number_format($desconto_turma,',','.');
                                }
                                if((isset($dados['desconto']) && $dados['desconto'] >0) || (isset($dados['entrada']) && $dados['entrada'] >0) || (isset($dados['desconto_porcento']) && $dados['desconto_porcento']>0) || $desconto_turma || $desconto_especial){
									// $totalCurso = $ret['total'];
									if(!$footer){
										$footer = '
										<tr>
											<td colspan="3"><div align="right"> Subtotal</div></td>
											<td><div align="center"><b>'.$totalHoras.'</b></div></td>
											<td><div align="right"><b>'.number_format($totalCurso,'2',',','.').'</b></div></td>
										</tr>';
									}
									if(isset($dados['desconto'])&&$dados['desconto']>0){

										$dados['desconto'] = (double)$dados['desconto'];
										$totalCurso = ($totalCurso) - $dados['desconto'];
										//$totalOrcamento = ($totalCurso) - ($dados['desconto']);
										$totalOrcamento = ($totalCurso);
										$ret['desconto'] = $dados['desconto'];
										$descontoFooter .= '
										<tr class="vermelho">
											<td colspan="4">
												<div align="right"><strong>Desconto do mês</strong></div>
											</td>
											<td>
												<div align="right"><b> '.number_format($dados['desconto'],'2',',','.').'</b></div>
											</td>
										</tr>';
									}
                                    if($desconto_especial){
                                        $desconto_especial = str_replace('R$','',$desconto_especial);
                                        $desconto_especial = trim($desconto_especial);
                                        $desconto_especial = Qlib::precoBanco($desconto_especial);
                                        $dados['desconto_especial'] = (double)$desconto_especial;
                                        $totalCurso = ($totalCurso) - $dados['desconto_especial'];
                                        //$totalOrcamento = ($totalCurso) - ($dados['desconto_especial']);
                                        $totalOrcamento = ($totalCurso);
                                        $ret['desconto_especial'] = $dados['desconto_especial'];
                                        $descontoFooter .= '
                                        <tr class="vermelho">
                                            <td colspan="4">
                                                <div align="right"><strong>Desconto Especial</strong></div>
                                            </td>
                                            <td>
                                                <div align="right"><b> '.number_format($dados['desconto_especial'],'2',',','.').'</b></div>
                                            </td>
                                        </tr>
                                        ';
                                    }
									if(isset($dados['desconto_porcento'])&& $dados['desconto_porcento']>0){
                                        $dp = Qlib::precoDbdase($dados['desconto_porcento']);
                                        $valor_descPor = ($dp*$subtotal1)/100;
										$valRoud = (round($valor_descPor,2));
										$totalCurso = ($totalCurso) - $valRoud;
										$ret['desconto'] += $valRoud;
										$totalOrcamento = $totalCurso;
										$descontoFooter .= '
										<tr class="vermelho">
											<td colspan="4">
												<div align="right"><strong>Desconto do mês ('.$dados['desconto_porcento'].'%) </strong></div>
											</td>
											<td>
												<div align="right"><b> '.number_format($valor_descPor,'2',',','.').'</b></div>
											</td>
										</tr>';
									}
                                    // dump($dados);
									if($desconto_turma && $desconto_turma>0){
                                        $id_matricula = isset($dados['id']) ? $dados['id'] : null;
                                        $tipo = 'v';
                                        $nome_desconto = 'Desconto do mês';
                                        if($id_matricula){
                                            $d_desconto = Qlib::get_matriculameta($id_matricula,'d_desconto');
                                            if($d_desconto){
                                                $arr_desconto = Qlib::decodeArray($d_desconto);
                                                $tipo = isset($arr_desconto['tipo']) ? $arr_desconto['tipo'] : $tipo;
                                                // $taxas = isset($arr_desconto['taxas']) ? $arr_desconto['taxas'] : $tipo;

                                                if($tipo == 'v'){
                                                    $nome_desconto = @$arr_desconto['nome'];
                                                }
                                            }
                                            // dump($desconto_turma, $nome_desconto);
                                        }
                                        if($tipo=='v'){
                                            $valor_descPor = (double)$desconto_turma;
                                        }else{
                                            $valor_descPor = ((double)$desconto_turma*$totalCurso)/100;
                                            $nome_desconto .= ' ('.$desconto_turma.'%)';
                                        }
                                        $valRoud = (round($valor_descPor,2));
                                        $totalCurso = ($totalCurso) - $valRoud;
										$ret['desconto'] += $valRoud;
										$totalOrcamento = $totalCurso;
										$descontoFooter .= '
										<tr class="vermelho">
											<td colspan="4">
												<div align="right"><strong>'.$nome_desconto.'</strong></div>
											</td>
											<td>
												<div align="right"><b> '.number_format($valor_descPor,'2',',','.').'</b></div>
											</td>
										</tr>';
									}
									if(isset($dados['entrada'])&&$dados['entrada']>0){
										$dados['entrada'] = (double)$dados['entrada'];
										$totalCurso = ($totalCurso) - $dados['entrada'];
										$totalOrcamento = ($totalCurso);
										$descontoFooter .= '<tr><td colspan="4"><div align="right">Entrada</div></td><td><div align="right"> - '.number_format($dados['entrada'],'2',',','.').'</div></td></tr>';
									}
									$descontoFooter .= '<tr class="verde"><td colspan="4" class="total-curso"><div align="right"><strong>Total do curso:</strong></div></td><td class="total-curso"><div align="right"><b> '.number_format($totalCurso,'2',',','.').'</b></div></td></tr>';
									$subtotal1 = $totalCurso;
								}
								$ret['subtotal'] = $subtotal1;
								/*Fim desconto*/
								$taxasHtml = false;
								$taxasValor = 0;
								$subtotal2 = $subtotal1;
                                if($dados['status']==1){
									$subtotal2 = $subtotal1+$dados['inscricao_curso'];
									$totalOrcamento = $subtotal2;
									$labelSub = 'Curso + Matrícula';
									$tr2 .= '
										<tr id="matri">
											<td style="width:'.$arr_wid2[0].'"><div align="center">'.$i2.'</div></td>
											<td style="width:85%"><div align="left"> Matrícula</div></td>
											<td style="width:'.$arr_wid2[3].'"><div align="right"> '.number_format($dados['inscricao_curso'],'2',',','.').'</div></td>
										</tr>';
									$tr2 .= '
										<tr id="matri">
											<td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
											<td style="width:85%"><div align="right"> <b>'.$labelSub.'</b></div></td>
											<td style="width:'.$arr_wid2[3].'"><div align="right"> <b>'.number_format($subtotal2,'2',',','.').'</b></div></td>
										</tr>';
									$tr2_adm .= '
									<tr id="matri">
										<td style="width:'.$arr_wid2[0].'"><div align="center">'.$i2.'</div></td>
										<td style="width:85%"><div align="left"> Matrícula</div></td>
										<td style="width:'.$arr_wid2[3].'"><div align="right"> '.number_format($dados['inscricao_curso'],'2',',','.').'</div></td>
									</tr>';
									$tr2_adm .= '
									<tr id="matri">
										<th style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></th>
										<th style="width:85%"><div align="right"> '.$labelSub.'</div></th>
										<th style="width:'.$arr_wid2[3].'"><div align="right">'.number_format($subtotal2,'2',',','.').'</div></th>
									</tr>';
								}
								$taxasHtml = false;
								$taxasValor = 0;
								$combustivelHtml = false;
								$mens_taxa = '<br><span>*valor de taxas não incluso</span>';
                                if(!empty($dados['taxas'])){
									if(is_array($dados['taxas'])){
										$arr_taxas = $dados['taxas'];
									}else{
										$arr_taxas = json_decode($dados['taxas'],true);
									}
									if(is_array($arr_taxas)){
										$label = false;
										$i2++;
										foreach($arr_taxas As $ket=>$valt){
											$valt = Qlib::precoDbdase($valt);
											$taxasValor += (double)$valt;
											if($ket =='checador'){
												$label = 'Taxas de Checador';
											}elseif($ket =='anac'){
												$label = 'Taxas ANAC';
											}elseif($ket =='envio_de_processo'){
												$label = 'Taxa de Envio de Processo ';
											}elseif($ket =='noturno'){
												$label = 'Taxas de Noturno';
											}else{
												$label = $ket;
											}
											if($valt >0){
                                                $taxasHtml .=
                                                '<tr id="matri">
													<td style="width:'.$arr_wid2[0].'"><div align="center">'.$i2.'</div></td>
													<td style="width:85%"><div align="left">'.$label.'</div></td>
													<td style="width:'.$arr_wid2[3].'"><div align="right"> '.number_format($valt,'2',',','.').'</div></td>
												</tr>';
												$i2++;
											}
										}
									}
								}
								if(!empty($dados['orc']) && ($arr_tx2=Qlib::lib_json_array($dados['orc']))){
									if(isset($arr_tx2['taxas2'])&&is_array($arr_tx2['taxas2'])){
										$i2++;
                                        foreach ($arr_tx2['taxas2'] as $kt => $vt) {
											$valt = Qlib::precoDbdase($vt['name_valor']);
                                           $taxasValor += (double)$valt;
											$label = isset($vt['name_label'])?$vt['name_label']:'N/I';
											if($valt && !is_null($valt)){
												if(is_string($valt)){
                                                    $valt = (double)$valt;
                                                }
                                                $v_exibe = number_format($valt,'2',',','.');
											}else{
												$v_exibe = '0,00';
											}
											$taxasHtml .=
                                            '<tr id="matri">
												<td style="width:'.$arr_wid2[0].'"><div align="center">'.$i2.'</div></td>
												<td style="width:85%"><div align="left">'.$label.'</div></td>
												<td style="width:'.$arr_wid2[3].'"><div align="right"> '.$v_exibe.'</div></td>
											</tr>';
											$i2++;
										}
									}
								}
								if($taxasHtml){
									$mens_taxa = false;
                                }
								//$taxasHtml = $taxasHtml ? $taxasHtml : '<span>*valor de taxas não incluso</span>';
								$tr2 .=		$taxasHtml;
								$tr3_adm .= $taxasHtml;
							}
							/*
							if($dados['status']==1){
								$totalOrcamento = ($ret['total']) + ($dados['inscricao_curso']);
							}else{
								$totalOrcamento = ($ret['total']);
							}*/
							///Incluir matricula
							$incluir_matricula_parcelamento = Qlib::qoption('incluir_matricula_parcelamento')?Qlib::qoption('incluir_matricula_parcelamento'):'n';
							if($incluir_matricula_parcelamento=='s')
								$ret['total'] = ($ret['total']) + ($dados['inscricao_curso']);
							$totalCurso = isset($totalCurso) ? $totalCurso:$ret['total'];
							//fim incluir matricula
							if($taxasValor>0){
                                $val_t = Qlib::precoDbdase($taxasValor);
								if(Qlib::qoption('somar_taxas_orcamento')=='s'){
									if($tipo_curso==2){
										$totalOrcamento += $val_t;
									}
									$subtotal2 += $val_t;
								}
								$taxasValorMatri = ($val_t);
								/*if(isset($dados['inscricao_curso'])){
									$taxasValorMatri = ($taxasValor)+($dados['inscricao_curso']);
								}*/
                                $valor_desconto_taxa = 0;
                                $title_desconto_taxa1 = __('Desconto nas taxas');

                                if($val_t>0){
                                    //temos as taxas
                                    $tipo_desconto_taxa = isset($dados['config']['tipo_desconto_taxa']) ? $dados['config']['tipo_desconto_taxa'] : '';
                                    $desconto_taxa = isset($dados['config']['desconto_taxa']) ? $dados['config']['desconto_taxa'] : 0;
                                    $desconto_taxa = Qlib::precoDbdase($desconto_taxa);
                                    if(is_string($desconto_taxa)){
                                        $desconto_taxa = (double)$desconto_taxa;
                                    }
                                    if($tipo_desconto_taxa=='p'){
                                        $title_desconto_taxa1 = __('Desconto nas taxas').' ('.$desconto_taxa.'%)';
                                        $valor_desconto_taxa = $val_t * $desconto_taxa/100;
                                    }
                                    if($tipo_desconto_taxa=='v' && $desconto_taxa!=0){
                                        $valor_desconto_taxa = $val_t - $desconto_taxa;
                                    }
                                }
                                $laber_taxas = __('Total de taxas Não inclusas no orçamento');

								$tr3 .= '
									<tr id="matri" class="total">
										<td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
										<td style="width:85%"><div align="right"> <strong style="color:#F00;">'.$laber_taxas.'</strong></div></td>
										<td style="width:'.$arr_wid2[3].'"><div align="right" style="color:#F00;"> <b>'.number_format($taxasValorMatri,'2',',','.').'</b></div></td>
									</tr>';
                                    if($valor_desconto_taxa>0){
                                        $title_desconto_taxa2 = $laber_taxas;
                                        $val_t = $val_t-$valor_desconto_taxa;
                                        $tr3 .= '
                                            <tr class="">
                                                <td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
                                                <td style="width:85%"><div align="right"> <strong style="">'.$title_desconto_taxa1.'</strong></div></td>
                                                <td style="width:'.$arr_wid2[3].'"><div align="right" style=""> <b>'.number_format($val_t,'2',',','.').'</b></div></td>
                                            </tr>';
                                        $tr3 .= '
                                            <tr class="vermelho">
                                                <td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
                                                <td style="width:85%"><div align="right"> <strong style="">'.$title_desconto_taxa2.'</strong></div></td>
                                                <td style="width:'.$arr_wid2[3].'"><div align="right" style=""> <b>'.number_format($valor_desconto_taxa,'2',',','.').'</b></div></td>
                                            </tr>';


                                    }
									$lbCurm = 'Curso + Matrícula';
									if(Qlib::qoption('somar_taxas_orcamento')=='s'){
										$lbCurm .= ' + Taxas';
									}
									$tr3 .= '
									<tr id="matri" class="total">
										<td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
										<td style="width:85%"><div align="right"> <b>'.$lbCurm.'</b></div></td>
										<td style="width:'.$arr_wid2[3].'"><div align="right"> <b>'.number_format($subtotal2,'2',',','.').'</b></div></td>
									</tr>';
								if(Qlib::qoption('somar_taxas_orcamento')=='s'){
									$laber_taxas = 'Total de taxas (A vista)';
								}
								$tr3_adm .='<tr class="vermelho">
												<td colspan="2" style="width:100%"><div align="right"><strong>'.$laber_taxas.':</strong></div></td>
												<td colspan="" style="width:100%"><div align="right"><b>'.number_format($taxasValorMatri,'2',',','.').'</b></div></td>
											</tr>';
                                if($valor_desconto_taxa>0){
                                    $tr3_adm .='<tr class="vermelho">
                                        <td colspan="2" style="width:100%"><div align="right"><strong>'.$title_desconto_taxa1.':</strong></div></td>
                                        <td colspan="" style="width:100%"><div align="right"><b>'.number_format($val_t,'2',',','.').'</b></div></td>
                                    </tr>';
                                    $tr3_adm .='<tr class="vermelho">
                                        <td colspan="2" style="width:100%"><div align="right"><strong>'.$title_desconto_taxa2.':</strong></div></td>
                                        <td colspan="" style="width:100%"><div align="right"><b>'.number_format($valor_desconto_taxa,'2',',','.').'</b></div></td>
                                    </tr>';
                                }
								$tr3_adm .='<tr id="">
												<td colspan="2" style="width:100%"><div align="right"><strong>'.$lbCurm.'</strong></div></td>
												<td colspan="" style="width:100%"><div align="right"><b>'.number_format($subtotal2,'2',',','.').'</b></div></td>
											</tr>';
								$ret['total_taxas'] = @$taxasValorMatri;
							}
							//Combustivel
							$totalOrcamento = isset($totalOrcamento)?$totalOrcamento:$ret['total'];
							// $ret['precoCurso'] = gerenciarPromocao($totalOrcamento,$dados['id_curso']);
							$ret['precoCurso'] = $totalOrcamento;
							$sc = $this->simuladorCombustivel($dados['token'],$dados);
							if($sc['valor']){
								$dados['combustivel'] = number_format($sc['valor'],2,',','.');
								$dados['valor_litro'] = number_format($sc['valor_litro'],2,',','.');
								$somar_cobustivel_orcamento = Qlib::qoption('somar_cobustivel_orcamento')?Qlib::qoption('somar_cobustivel_orcamento'):'s';
								if($somar_cobustivel_orcamento=='s'){
									$somar_cobustivel_total = Qlib::qoption('somar_cobustivel_total')?Qlib::qoption('somar_cobustivel_total'):'n';
									if(isset($dadosOrc['sele_pag_combustivel'])&&$dadosOrc['sele_pag_combustivel']=='antecipado'){
										$somar_cobustivel_total = 's';
									}
									if($somar_cobustivel_total=='s') {
										$totalOrcamento = $totalOrcamento + $sc['valor'];
									}
									$lbCurm = 'Gasto estimado de combustível:';
									if($sc['valor_litro']){
										//$lbCurm .= ' <small style="font-weight:500">Litro - R$ '.$sc['valor_litro'].'</small> Total:';
										$label_sele_valores .= '<br>* '.$sc['valor_litro'].' Preço por litro ';
										// if(Qlib::isAdmin(1));
										// dd($label_sele_valores);
									}
									if($somar_cobustivel_total == 's'){
										$tr3 .= '
										<tr id="matri" class="total">
											<td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
											<td style="width:85%"><div align="right"> <b>'.$lbCurm.'</b></div></td>
											<td style="width:'.$arr_wid2[3].'"><div align="right"> <b>'.$dados['combustivel'].'</b></div></td>
										</tr>';
										$tr3_adm .='
											<tr id="">
												<td colspan="2" style="width:100%"><div align="right"><strong>'.$lbCurm.'</strong></div></td>
												<td colspan="" style="width:100%"><div align="right"><b>'.$dados['combustivel'].'</b><input type="hidden" value="'.Qlib::precoDbdase($sc['valor']).'" name="combustivel" /></div></td>
											</tr>';
									}
								}
							}
							$linkComprar = Qlib::qoption('dominio').'/area-do-aluno/meus-pedidos/p/'.$dados['id'];
							if($dados['id_turma']>0){
								$linkComprar .= '/'.base64_encode($dados['id_turma']);
							}
							//<!--<a href="'.$linkComprar.'" target="_BLANK" style="padding:5px;">Comprar</a>-->
							$tr3 .= '
								<tr id="matri" class="total verde">
									<td style="width:'.$arr_wid2[0].'"><div align="center">&nbsp;</div></td>
									<td style="width:85%"><div align="right"> <strong class="color-price1">TOTAL DA PROPOSTA A VISTA:</strong></div></td>
									<td style="width:'.$arr_wid2[3].'"><div align="right"> <b>'.number_format($totalOrcamento,'2',',','.').'</b></div></td>
								</tr>
							';
							$tr3_adm .= '<td colspan="2" width="85%"><div align="center"><strong class="verde">TOTAL DA PROPOSTA A VISTA:</strong></div></td><td><div align="right"> <span class="verde"><b>'.number_format($totalOrcamento,'2',',','.').'</b></span></div></td>';
							if(@$dados['combustivel']){
							    $valor_combustivel = Qlib::precoDbdase($dados['combustivel']);
								$info_cobustivel = (new SiteController)->short_code('info_cobustivel',false,@$_GET['edit']);
								$temaComb = '
								<div align="center" style="">
									'.$info_cobustivel.'
								</div>
								';
								$label = __('Combustível estimado gasto em TODO curso prático. (O pagamento do combustível será realizado a cada voo. O valor pode variar de acordo com o preço do combustível.)');
								$combustivelHtml = str_replace('{item}',$i2,$temaComb);
								$combustivelHtml = str_replace('{label}',$label,$combustivelHtml);
								$combustivelHtml = str_replace('{width0}',$arr_wid2[0],$combustivelHtml);
								$combustivelHtml = str_replace('{width1}',$arr_wid2[1],$combustivelHtml);
								$combustivelHtml = str_replace('{width3}',$arr_wid2[3],$combustivelHtml);
								$combustivelHtml = str_replace('{valor_combustivel}','<span style="color:#F00;">'.$dados['combustivel'].'</span>',$combustivelHtml);
								$combustivelHtml = str_replace('{valor_litro}',@$dados['valor_litro'],$combustivelHtml);
								//$totalOrcamento = $subtotal2 + $valor_combustivel;
								//$tr3 .= 	$combustivelHtml;
								//$tr2 .= 	$combustivelHtml;
								//$tr3_adm .= $combustivelHtml;
								$mensComb = $combustivelHtml;
							}else{
								$totalOrcamento = $subtotal2;
							}
							$ret['totalOrcamento'] = $totalOrcamento;
							$incluir_taxas_parcelamento = Qlib::qoption('incluir_taxas_parcelamento')?Qlib::qoption('incluir_taxas_parcelamento'):'n';
							if($incluir_taxas_parcelamento=='s'){
								$ret['totalCurso'] = $subtotal2;
							}else{
								$ret['totalCurso'] = $totalCurso;
							}
							// if(Qlib::isAdmin(1)){
							// 	echo $totalCurso.'<br>';
							// 	echo $incluir_matricula_parcelamento.'<br>';
							// 	dd($ret);
							// }
							$ret['table'] = str_replace('{{table}}',$tr,$tema);
							/*$footer = '
							<tr>
								<td colspan="3"><div align="right">Subtotal</div></td>
								<td><div align="center">'.$totalHoras.'</div></td>
								<td><div align="right"> '.number_format($subtotal1,'2',',','.').'</div></td>
							</tr>';*/
							$footer = isset($footer)?$footer:'
							<tr>
								<td colspan="3"><div align="right">Subtotal</div></td>
								<td><div align="center"><b>'.$totalHoras.'</b></div></td>
								<td><div align="right"> <b>'.number_format($subtotal1,'2',',','.').'</b></div></td>
							</tr>';
							$footer .= $descontoFooter;
							$ret['table'] = str_replace('{{footer}}',$footer,$ret['table']);
							$ret['table'] = str_replace('{{table2}}',$tr2,$ret['table']);
							$ret['table'] = str_replace('{{table3}}',$tr3,$ret['table']);
							$ret['table_adm'] = str_replace('{{table}}',$tr_adm,$tema_admn);
							$ret['table_adm'] = str_replace('{{footer}}',$footer,$ret['table_adm']);
							$ret['table_adm'] = str_replace('{{table2}}',$tr2_adm,$ret['table_adm']);
							$ret['table_adm'] = str_replace('{{table3}}',$tr3_adm,$ret['table_adm']);
							$url_prop = Qlib::qoption('dominio_site').'/area-do-aluno/meus-pedidos/p/'.$dados['id'];
							// $link_proposta = queta_formfield4('input-group-text', 12, 'link_proposta-', $url_prop, '', @$val['event'], @$val['clrw'], @$val['obs'], 'Link da proposta', '','','sm');
							$link_proposta = $url_prop;
							$ret['table_adm'] = str_replace('{link_proposta}',$link_proposta,$ret['table_adm']);
							$ret['table'] .= $mensComb.$mens_taxa;
							$ret['table_adm'] .= $mensComb.$mens_taxa;
						}
					}else{
						$ret['table'] = Qlib::formatMensagem0('Erro: Cliente ou curso não encontrado(s)!!','danger',10000);
					}
				}elseif($dados['tipo_curso']==1 || $dados['tipo_curso']==3 || $dados['tipo_curso']==4){
					$arr_wid2 = array('5%','80%','15%');
					if(isset($dados['Nome']) && isset($dados['nome_curso'])){
						$ret['id_curso'] = $dados['id_curso'];
						$dadosD = explode(' ',$dados['atualizado']);
						$valdata = explode('-',$dadosD[0]);
                              $valor_curso = $dados['valor_curso'];
						if($is_signed){
							$valor_curso = isset($dados['subtotal']) ? $dados['subtotal'] : @$dados['valor_curso'];
						}
						$totalOrcamento = $valor_curso;
						$espacoTable = false;
						$ret['nome_arquivo'] = 'Proposta '.$dados['id']. ' '.$dados['Nome'].' '.$dados['nome_curso'];
						//$validade = ultimoDiaMes($valdata[1],$valdata[0]).'/'.$valdata[1].'/'.$valdata[0];
						$dias = isset($dias)?$dias: 7;
						$validade = Qlib::CalcularVencimento(Qlib::dataExibe($dadosD[0]),$dias);
						$ret['validade'] = Qlib::dataExibe($validade);
						$ret['total'] = $totalOrcamento;
						$tema = '
							<p class="apresentacao" style="font-family:helvetica;font-size:13pt;">Prezado(a) <strong>'.$dados['Nome'].'</strong>,<br>
							Temos o prazer em lhe apresentar nossa proposta comercial<br>Curso: <strong>'.$dados['titulo_curso'].'</strong></p>
							<br>
							<table id="table4" cellspacing="0" class="table">
								<thead >
									<tr>
										<th style="width:'.$arr_wid2[0].'"><div align="left">ITEM</div></th>
										<th style="width:'.$arr_wid2[1].'"><div align="center">DESCRIÇÃO</div></th>
										<th style="width:'.$arr_wid2[2].'"><div align="right">TOTAL</div></th>
									</tr>
								</thead>
								<tbody class="jss526">{{table2}}
								</tbody>
								<tfoot class="jss526">{{footer}}
								</tfoot>
							</table>'.$espacoTable.'
							';
						$tema2 = '
							<tr>
								<td style="width:'.$arr_wid2[0].'"><div align="left">{num}</div></td>
								<td style="width:'.$arr_wid2[1].'"><div align="center">{descricao}</div></td>
								<td style="width:'.$arr_wid2[2].'"><div align="right">{valor}</div></td>
							</tr>
						';
						$ret['nome_arquivo'] = 'Proposta '.$dados['id']. ' '.$dados['Nome'].' '.$dados['nome_curso'];
						//$validade = ultimoDiaMes($valdata[1],$valdata[0]).'/'.$valdata[1].'/'.$valdata[0];
						$dadosD = explode(' ',$dados['atualizado']);
                        $dias = isset($dias)?$dias: 7;
                        $validade = Qlib::CalcularVencimento(Qlib::dataExibe($dadosD[0]),$dias);
                        $dadosCli = $this->tag_apresentacao_orcamento($dados);
                        if($this->is_pdf()){
                            $dadosCli .= $btn_aceito_aceitar;
                        }
						$ret['dadosCli'] =  $dadosCli;
						$i=1;
                        // if($dados['tipo_curso']==4){
                            $ret['totalCurso'] = isset($dados['valor_orcamento']) ? $dados['valor_orcamento'] : $dados['valor_curso'];
                        // }else{

                        // }
						$tr = str_replace('{num}',$i,$tema2);
						$tr = str_replace('{descricao}','Curso '.$dados['titulo_curso'],$tr);
						// $tr = str_replace('{valor}',number_format($dados['valor_curso'],2,',','.'),$tr);
						$tr = str_replace('{valor}',number_format($ret['totalCurso'],2,',','.'),$tr);
						$i++;
						if(isset($dados['inscricao_curso'])&&$dados['inscricao_curso']>0){
							$tr .= str_replace('{num}',$i,$tema2);
							$tr = str_replace('{descricao}','Matrícula '.$dados['titulo_curso'],$tr);
							$tr = str_replace('{valor}',number_format($dados['inscricao_curso'],2,',','.'),$tr);
							$totalOrcamento +=  $dados['inscricao_curso'];
						}
						if(isset($dados['desconto']) && $dados['desconto']>0){
							$desconto = number_format($dados['desconto'],2,',','.');
							$tr .= str_replace('{num}',$i,$tema2);
							$tr = str_replace('{descricao}','Desconto ',$tr);
							$tr = str_replace('{valor}','<span style="color:#F00">- '.$desconto.'</span>',$tr);
							$espacoTable = false;
							$totalOrcamento = $dados['total'] ? $dados['total']:$dados['valor_curso'];
						}else{
							//$espacoTable = '<p></p>';
							$espacoTable = false;
						}
						if($dados['tipo_curso']==4){
                            if($dados['valor_parcela_curso']==0){
                                $totalOrcamento = '1X R$ '.number_format($dados['inscricao_curso'],2,',','.').' + 1 X R$ '.number_format($dados['valor'],2,',','.');
                            }else{
                                $totalOrcamento = '1X R$ '.number_format($dados['inscricao_curso'],2,',','.').' + '.$dados['parcelas_curso'].'X'.number_format($dados['valor_parcela_curso'],2,',','.');
                            }
							$ret['totalOrcamento'] = $totalOrcamento;
							$totGeral = $totalOrcamento;
						}else{
							$ret['totalOrcamento'] = $totalOrcamento;
							$totGeral = 'R$'.number_format($totalOrcamento,'2',',','.');
						}
                        $valorParcelado = false;
						if(isset($dados['parcelas_curso'])&&$dados['parcelas_curso']>0){
							if($dados['tipo_curso']==4){
								$valorParcelado = '';
							}else{
								// $valorParcelado = round($ret['totalOrcamento']/$dados['parcelas_curso'],2);
								// $valorParcelado = ' ou '.$dados['parcelas_curso'].' X '.number_format($valorParcelado,2,',','.').' no cartão';
								$valorParcelado = '';
							}
						}
						$tema = '<style>
                                       .color-price1{
                                           color: #062d4a !important;
                                       }
                                   </style>'.$tema;
						$ret['table'] = str_replace('{{table}}',$tr,$tema);
						$ret['table2'] = str_replace('{{table}}',$tr,$tema);
						$listMod['html'] = false;
						if($dados['tipo_curso']==4){
							$dadosCu[0]['token_matricula'] = isset($dados['token'])?$dados['token']:false;
							$listMod = $this->get_modulos_cursos($dados);
							if(isset($listMod['total_taxas'])){
								$ret['totalOrcamento'] = (double)$ret['totalOrcamento']+(double)$listMod['total_taxas'];
								$ret['listMod'] = $listMod;
							}
							$tabela_parcelamento = $ret['totalOrcamento'];
							if(isset($dados['token'])){
								$resumo = Qlib::infoPagCurso([
									'token'=>$dados['token'],
								]);
                                if(isset($resumo['tabela_parcelamento']) && !empty($resumo['tabela_parcelamento'])){
									$tabela_parcelamento =  $resumo['tabela_parcelamento'];
									// $ret['table'] = $listMod['html'].$resumo['tabela_parcelamento'];
									if($this->is_pdf()){
										$ret['table'] = $listMod['html'].'<br>';
									}else{
										$ret['table'] = $listMod['html'].'<div class="col-12 obs-plano">'.@$resumo['tabela_parcelamento_cliente'].'</div>';
									}
								}else{
									//se não tiver tabela de parcelamento na exime orçamento
									$ret['table'] = false;
								}
							}
							$footer = '<tr><td colspan="3">'.$tabela_parcelamento.'</td></tr>';
						}else{
							$footer = '<tr><td colspan="1"><div align="right"><b>Total</b></div></td><td colspan="2"><div align="right"><b>'.$totGeral.' '.$valorParcelado.'</b></div></td></tr>';
						}
						$ret['table'] = str_replace('{{footer}}',$footer,$ret['table']);
						$ret['table'] = str_replace('{{table2}}',$tr,$ret['table']);
						$ret['table2'] = str_replace('{{footer}}',$footer,$ret['table2']);
						$ret['table2'] = str_replace('{{table2}}',$tr,$ret['table2']);
						//$ret['table'] = str_replace('{{table3}}',$tr3,$ret['table']);
					}
				}
            }else{
                $ret['table'] = Qlib::formatMensagem0('Erro: Orçamento não encontrado!!','danger',10000);
			}
            //Adcionar as tabelas de parcelamentos
            $ret['parcelamento'] = '';
            if(isset($ret['totalCurso']) && $ret['totalCurso']> 0 && ($exibir_parcelamento=='s')){
                $configPar['valor'] 	= $ret['totalCurso'];
                $configPar['titulo'] 	= 'PAGAMENTO PARCELADO';
                $configPar['tam'] 		= 6;
                $configPar['id_curso'] 	= isset($dados['id_curso']) ? $dados['id_curso'] : null;
                $configPar['id_turma'] 	= isset($dados['id_turma']) ? $dados['id_turma'] : null;
                $configPar['token_matricula'] 	= $dados['token'];
                if(isset($dados['sele_valores'])){
                    $configPar['tabela_preco'] 	= $dados['sele_valores'];
                }
                $parcelamentoT = new FinanceiroController;
                $parcelamento = '<div class="col-sm-12 padding-none planos-parcelamentos">'.$parcelamentoT->execute($configPar).'</div>';
                $ret['parcelamento'] = $parcelamento;
            }

			$ret['dados'] = @$dados;
			// $dados = (new CursosController)->dadosMatricula(@$dados['token']);
			// if($dados){
			// 	$ret['dados_gravados'] = @$dados;
			// }
            return $ret;
	    }
    }
    public function get_modulos_cursos($config=false,$orc=false){
        $ret['exec'] = false;
        $ret['html'] = false;
        $ret['total_taxas'] = 0;
        $id_curso = isset($config['id']) ? $config['id'] : false;
        $token_matricula = isset($config['token']) ? $config['token'] : false; //html ou pdf
        $orc = isset($config['orc']) ? $config['orc'] : false; //html ou pdf
        $config['modulos'] = isset($config['modulos_curso']) ? $config['modulos_curso'] : false; //html ou pdf
        $is_pdf = $this->is_pdf();
        $arr_orc=[];
        $id_matricula = null;
        if($token_matricula && !$orc){
            if(!$orc){
                $d_or =  $this->dm($token_matricula);//dados od orçamento
            }
            $orc = isset($d_or['orc'])?$d_or['orc']: false;
            $id_matricula = isset($d_or[0]['id'])?$d_or[0]['id']: null;
            if($orc){
                $arr_orc = Qlib::lib_json_array($orc);
            }
        }else{
            $id_matricula = isset($orc['id'])?$orc['id']: null;
            $arr_orc = $orc;
        }
        if(!isset($config['modulos']) && $id_curso){
            // $dm = Qlib::dados_tab($GLOBALS['tab10'],'*',"WHERE id = '$id_curso'");
            $dm = Qlib::dados_tab($GLOBALS['tab10'],[
                'id'=>$id_curso
            ]);
            if($dm){
                $config['modulos'] = $dm[0]['modulos'];
                $config['config'] = $dm[0]['config'];
            }
        }
        $client = false;
        if(isset($config['modulos']) && $config['modulos']!='[]'){
            if(is_array($config['modulos'])){
                $arr_mod = $config['modulos'];
            }else{
                $arr_mod = Qlib::lib_json_array($config['modulos']);
            }
            $arr_mod_save = false;
            if(isset($arr_orc['modulos'])){
                $arr_mod_save = $arr_orc['modulos'];
            }
            if(!Qlib::is_admin_area() && isset($arr_orc['modulos'])){
                $arr_mod = $arr_orc['modulos'];
                $client = true;
            }else{
                if($is_pdf && isset($arr_orc['modulos'])){
                    $arr_mod = $arr_orc['modulos'];
                    $client = true;
                }
            }

            if($is_pdf){
                $tm1 = '
                    <div class="">
                        <table class="table table-striped get_modulos_cursos">
                            <thead>
                                <tr>
                                    <th colspan="5" class="text-left"><h3>Detalhamento</h3></th>
                                </tr>
                                <tr>
                                    <th style="width:13%"><div align="left"><b>Descrição</b></div></th>
                                    <th style="width:53%"><div align="center">Etapa</div></th>
                                    <th style="width:10%"><div align="center">Horas T.</div></th>
                                    <th style="width:10%"><div align="center">Horas P.</div></th>
                                    <th style="width:15%"><div align="right"><b>Valor</b></div></th>
                                </tr>
                            </thead>
                            <tbody>
                                {tr}
                            </tbody>
                            {tr_foot}
                        </table>
                        <br>
				        {table_taxas_1}
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th>
                                        Total do Orçamento
                                    </th>
                                    <th class="text-right">
                                        <div align="right">
                                            {total}
                                        </div>
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                        <br><br>
                    </div>
                    ';
                $tm2 = '
                    <tr>
                        <td>{descricao}</td>
                        <td>{curso}</td>
                        <td><div align="center">{carga}</div></td>
                        <td><div align="center">{carga_pratica}</div></td>
                        <td><div align="right">{valor}</div></td>
                    </tr>';
            }else{
                $tm1 = '
                   <div class="">
                        <table class="table table-striped get_modulos_cursos">
                            <thead>
                                <tr>
                                    <th colspan="5" class="text-left"><h3>Detalhamento</h3></th>
                                </tr>
                                <tr>
                                    <th style="width:13%"><div align="left"><b>Descrição</b></div></th>
                                    <th style="width:53%"><b>Etapa</b></th>
                                    <th style="width:10%" class="text-center">Horas T.</th>
                                    <th style="width:10%" class="text-center">Horas P.</th>
                                    <th style="width:15%"><div align="right"><b>Valor</b></div></th>
                                </tr>
                            </thead>
                            <tbody>
                                {tr}
                            </tbody>
                            {tr_foot}
                        </table>
                        &nbsp;&nbsp;
                    </div>
                    ';
                $tm2 = '
                    <tr>
                        <td>{descricao}</td>
                        <td>{curso}</td>
                        <td><div align="center">{carga}</div></td>
                        <td><div align="center">{carga_pratica}</div></td>
                        <td><div align="right">{valor}</div></td>
                    </tr>';
            }

            $tr=false;
            if(is_array($arr_mod)){
                $valor_total = 0;
                foreach ($arr_mod as $id => $v) {
                    if($client){
                        $siga = isset($v['sele'])?$v['sele']:false;
                    }else{
                        $siga = true;
                        if($is_pdf){
                            $siga = isset($v['sele'])?$v['sele']:false;
                        }
                        // dump($is_pdf);
                    }
                    if((isset($v['curso_id']) || isset($v['curso'])) && $v['titulo']!='' && $siga){
                        // if(Qlib::isAdmin(1)){
                        $tipo = $v['tipo'];
                        if($client){
                            $curso = @$v['curso'];
                        }else{
                            $curso = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',@$v['curso_id'],'titulo');
                        }
                        $titulo = $v['titulo'];
                        if(Qlib::isAdmin(6) && Qlib::is_admin_area() && !$is_pdf){
                            if($arr_mod_save){
                                if(isset($arr_mod_save[$id]['sele'])){
                                    $checkbox = 'checked';
                                    $disabled_titulo = false;
                                    $disabled_limite = false;
                                    $disabled_curso = false;
                                    $disabled_tipo = false;
                                    $valor_total = isset($v['valor']) ? $v['valor'] : 0;
                                }else{
                                    $checkbox = '';
                                    $disabled_titulo = 'disabled';
                                    $disabled_limite = 'disabled';
                                    $disabled_curso = 'disabled';
                                    $disabled_tipo = 'disabled';
                                }
                            }else{
                                $checkbox = 'checked';
                                $disabled_titulo = false;
                                $disabled_limite = false;
                                $disabled_curso = false;
                                $disabled_tipo = false;
                            }
                            $titulo = '<div class="col-sm-1">
                                            <input  onclick="orcamentos_selectModuloPlano(this);" type="checkbox" '.$checkbox.' data-id="'.$id.'" name="dados[orc][modulos]['.$id.'][sele]"/>
                                        </div>
                                        <div class="col-sm-11">
                                            <input type="hidden" '.$disabled_titulo.' name="dados[orc][modulos]['.$id.'][titulo]" value="'.@$v['titulo'].'">
                                            <input type="hidden" '.$disabled_limite.' name="dados[orc][modulos]['.$id.'][limite]" value="'.@$v['limite'].'">
                                            <input type="hidden" '.$disabled_curso.' name="dados[orc][modulos]['.$id.'][curso]" value="'.$curso.'">&nbsp;'.$v['titulo'].'
                                            <input type="hidden" '.$disabled_tipo.' name="dados[orc][modulos]['.$id.'][tipo]" value="'.$tipo.'">&nbsp;'.$v['titulo'].'
                                        </div>';
                        }else{
                            if($arr_mod_save){
                                if(isset($arr_mod_save[$id]['sele'])){
                                    $valor_total = isset($v['valor']) ? $v['valor'] : 0;
                                }
                            }
                        }
                        $valor = isset($v['valor'])? $v['valor'] : 0;
                        // $valor_total = isset($v['valor']) ? $v['valor'] : $valor;
                        if(isset($_GET['teste'])){
                            dump($v);
                        }
                        $limite_pratico = isset($v['limite_pratico']) ? $v['limite_pratico'] : false;
                        $tr .= str_replace('{descricao}',$titulo,$tm2);
                        $tr = str_replace('{carga}',$v['limite'],$tr);
                        $tr = str_replace('{carga_pratica}',$limite_pratico,$tr);
                        $tr = str_replace('{curso}',$curso,$tr);
                        $tr = str_replace('{tipo}',$tipo,$tr);
                        $tr = str_replace('{valor}',$valor,$tr);
                    }
                }
            }
            $dtx = $this->get_taxas_curso($config);
            $ret['html'] = str_replace('{tr}',$tr,$tm1);
            // if($this->is_pdf()){
            //     $ret['html'] .= '<br>'.$dtx['html'];
            // }else{
            //     $ret['html'] .= $dtx['html'];
            // }
            // $ret['total_taxas'] = $dtx['total'];
            if($this->is_pdf()){
                $ret['html'] = str_replace('{table_taxas_1}',$dtx['html'],$ret['html']);
            }else{
                $ret['html'] = str_replace('{table_taxas_1}',$dtx['html'],$ret['html']);
            }

            $total_taxas = Qlib::precoDbdase($dtx['total']);
            $total_taxas = (double)$total_taxas;
            $ret['total_taxas'] = $total_taxas;
            $subtotal = Qlib::precoDbdase($valor_total);
            $subtotal = (double)$subtotal;
            $desconto = null;
            $subtotal_ = $subtotal;
            $ret['subtotal'] = $subtotal_;
            $label_desconto = 'Desconto';
            if($id_matricula){
                $desconto = Qlib::get_matriculameta($id_matricula,'desconto',true);
                if($desconto){
                    $id_matricula = Qlib::get_matricula_id_by_token($token_matricula);
                    $d_desconto = Qlib::get_matriculameta($id_matricula,'parcelamento_desconto',true);
                    $arr_parcela = Qlib::decodeArray($d_desconto);
                    $label_desconto = isset($arr_parcela['nome']) ? $arr_parcela['nome'] : "Desconto";
                    $subtotal = $subtotal - (double)$desconto;
                }
            }
            $total_curso = $subtotal+$ret['total_taxas'];
            $ret['total_curso'] = $total_curso;
            $ret['desconto'] = $desconto;
            $colspan = '4';
            if($desconto){
                $tr_foot = '
                <tfoot>
                    <tr class="vermelho text-danger">
                        <td colspan="'.$colspan.'">
                            '.$label_desconto.'
                        </td>
                        <td class="text-right">
                            <div align="right">
                                - '.Qlib::valor_moeda($desconto,'R$ ').'
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="'.$colspan.'">
                            Subtotal
                        </th>
                        <th class="text-right">
                            <div align="right">
                                '.Qlib::valor_moeda($subtotal,'R$ ').'
                            </div>
                        </th>
                    </tr>
                </tfoot>
                ';
            }else{
                $tr_foot = '
                <tfoot>
                    <tr>
                        <th colspan="'.$colspan.'">
                            Subtotal
                        </th>
                        <th>
                            <div align="right">
                                '.Qlib::valor_moeda($subtotal,'R$').'
                            </div>
                        </th>
                    </tr>
                </tfoot>
                ';
            }

            $ret['html'] = str_replace('{tr_foot}',$tr_foot,$ret['html']);
            $ret['html'] = str_replace('{total}',Qlib::valor_moeda($total_curso,'R$'),$ret['html']);
        }
        return $ret;
    }
    /**
     * Metodo para verificar se a requisição é de uma pagina de pdf válida
     */
    public function is_pdf(){
        $route_name = request()->route()->getName();
        if($route_name=='orcamento.pdf'){
            $ret = true;
        }else{
            $ret = false;
        }
        return $ret;
    }
    /**
     * Metodo para exibir um table com as taxas dos cursos especificos*
    */
    function get_taxas_curso($config=false){
        $ret['exec'] = false;
        $ret['html'] = false;
        $ret['total'] = 0;
        $id_curso = isset($config['id']) ? $config['id'] : false;
        $tmsomaTaxa = '
        <tfoot>
            <tr>
                <th colspan=""><b>Total das taxas</b></th>
                <th><div align="right"><b>{total}</b></div></th>
            </tr>
        </tfoot>';
        $tmsomaTaxa = false;
        if($this->is_pdf()){
            $tm1 = '<table class="table" style="">
                        <thead>
                        <tr>
                            <th colspan="2" class="text-left"><h3>Taxas</h3></th>
                        </tr>
                        <!--<tr>
                            <th style="width:90%"><div align="left"><b>Descrição</b></div></th>
                            <th style="width:10%"><div align="right"><b>Valor</b></div></th>
                        </tr>-->
                        </thead>
                        <tbody>
                            {tr}
                        </tbody>
                        '.$tmsomaTaxa.'
                    </table>&nbsp;&nbsp;';
        $tm2 = '<tr>
                    <td style="width:90%">{descricao}</td>
                    <td style="width:10%"><div align="right">{valor}</div></td>
                </tr>';
        }else{
            $tm1 = '<table class="table table-striped">
                        <thead>
                        <tr>
                            <th colspan="3" class="text-left"><h3>Taxas</h3></th>
                        </tr>
                        <!--<tr>
                            <th style="width:90%"><div align="left"><b>Descrição</b></div></th>
                            <th style="width:10%"><div align="right"><b>Valor</b></div></th>
                        </tr>-->
                        </thead>
                        <tbody>
                            {tr}
                        </tbody>
                        '.$tmsomaTaxa.'
                    </table>&nbsp;&nbsp;';
        $tm2 = '<tr>
                    <td style="width:90%">{descricao}</td>
                    <td style="width:10%"><div align="right">{valor}</div></td>
                </tr>';
        }
        if(!isset($config['config']) && $id_curso){
            $dm = Qlib::dados_tab('cursos',['id' => $id_curso]);
            if($dm){
                $config['config'] = $dm['config'];
            }
        }
        if(is_array($config['config'])){
            $arr_config = $config['config'];
        }else{
            $arr_config = Qlib::lib_json_array($config['config']);
        }
        $total = NULL;
        $tr = false;
        if(isset($arr_config['tx2']) && is_array($arr_config['tx2'])){
            foreach ($arr_config['tx2'] as $k => $v) {
                if(!empty($v['name_label'])){
                    $descricao = $v['name_label'];
                    if($v['name_valor']){
                        $valor = Qlib::precoDbdase($v['name_valor']);

                        $total+=$valor;
                    }else{
                        $valor = 0;
                    }
                    if($valor){
                        $val = number_format($valor,2,',','.');
                    }else{
                        $val = 'Gratuito';
                    }
                    $tr .= str_replace('{descricao}',$descricao,$tm2);
                    $tr = str_replace('{valor}',$val,$tr);
                    // $tr = str_replace('{tipo}',$tipo,$tr);
                }
            }
            $ret['total'] = (double)$total;
        }
        if(!$tr){
            $tm1 = '';
        }
        $ret['html'] = str_replace('{tr}',$tr,$tm1);
        $ret['html'] = str_replace('{total}',number_format($total,'2',',','.'),$ret['html']);
        return $ret;
    }

    public function verificaDataAssinatura($config,$type_return='bool'){
		$campo_bus = isset($config['campo_bus'])?$config['campo_bus'] : 'token';
		$token = isset($config['token'])?$config['token'] : 'token';
		$contrato = isset($config['contrato'])?$config['contrato'] : false;
		if($contrato){
			$dt = $contrato;
		}else{
			$dt = Qlib::buscaValorDb0('matriculas',$campo_bus,$token,'contrato');
		}
		if($dt){
			$arr = Qlib::lib_json_array($dt);
			$dataContrato = isset($arr['data_aceito_contrato']) ? $arr['data_aceito_contrato']:false;
			// if(Qlib::isAdmin(1)){
			// 	lib_print($arr);
			// 	lib_print($dataContrato);
			// }
		}else{
			$dataContrato = false;
		}
        if($type_return=='array'){
            return $arr;
        }else{
            return $dataContrato;
        }
	}
    public function pacotesAeronaves($id_aviao=false){
        global $tab54;
        $tab54 = 'aeronaves';
        $config = Qlib::buscaValorDb0($tab54,'id',$id_aviao,'pacotes');
        $hora_padrao = Qlib::buscaValorDb0($tab54,'id',$id_aviao,'hora_rescisao');

        $ret = false;

        if($config){
            $arr_con = Qlib::lib_json_array($config);
            // if(Qlib::isAdmin(1)){
            // 	lib_print($arr_con);
            // }
            if(is_array($arr_con)){

                $fin = new CotacaoDolarController;

                $cota = $fin->cotacaoDolar();

                foreach ($arr_con as $k => $va) {

                    if(isset($va['moeda']) && $va['moeda']=='USD'){

                        if(isset($va['horas_livre_dolar']) && $va['horas_livre_dolar'] && isset($cota['cotacao']['valor']) && $cota['cotacao']['valor']){

                            $vlDolcar = str_replace('U$','',Qlib::precoDbdase($va['horas_livre_dolar']));

                            $vlDolcar = (double)$vlDolcar;

                            $to = $cota['cotacao']['valor']*$vlDolcar;
                            if($to>0){
                                $arr_con[$k]['horas_livre'] = 'R$ '.number_format($to,2,',','.');
                            }

                        }

                    }
                    $arr_con[$k]['hora_padrao'] = $hora_padrao;

                }

                // if(Qlib::isAdmin(1)){
                // 	lib_print($arr_con);
                // }


            }



            $ret = $arr_con;

        }

        return $ret;

    }

    public function somaHoraAviao($arrPedido=false,$aviao=false){

        //para somar as horas por aviao no pedido

        $ret = false;

        if($arrPedido){

            if(is_array($arrPedido)){

                $horas =  NULL;
                foreach($arrPedido As $key=>$val){
                    $val['aviao'] = isset($val['aviao'])?$val['aviao']:0;
                    if(isset($val['horas']) && ($aviao == $val['aviao'])){
                        $val['horas'] = (int)$val['horas'];
                        // if(is_sandbox()){
                        // 	dd($val['horas']);
                        // }
                        $horas += @$val['horas'];

                    }

                }

                $ret = $horas;

            }

        }

        return $ret;

    }

    public function somaHoraAviao2($arrPedido=false,$aviao=false){

        //para somar as horas por aviao no pedido

        $ret = false;

        if($arrPedido){

            if(is_array($arrPedido)){

                $horas =  NULL;

                foreach($arrPedido As $key=>$val){

                    if(in_array($aviao, $val['aviao'])){

                        $horas += $val['limite'];

                    }

                }

                $ret = $horas;

            }

        }

        return $ret;

    }
    /**
     * Metodo para calcular os preco atraves dos modulos selecionados num orçamento
     */
    public function calcPrecModulos($config=false,$sele_valores=false,$todosModulos=false){
        global $tab50;
        $ret['padrao'] = 0;
        $ret['custo'] = 0;
        $ret['custo'] = 0;

        if(isset($config['aviao'])&&!empty($config['aviao'])){
            $arr_pacotoes = $this->pacotesAeronaves($config['aviao']);
            $arr_tabelas = Qlib::sql_array("SELECT * FROM ".$tab50." WHERE ativo = 's' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'url2','url');
            $id_curso = isset($config['id_curso']) ? $config['id_curso'] : 0;
            if($id_curso){
                //verificar se esse curso é recheck
                $is_recheck = (new CursosController)->is_recheck($id_curso);
            }
            $valor = NULL;
            $padrao = NULL;
            $custo = NULL;
            if(is_array($arr_pacotoes)){
                foreach($arr_pacotoes As $kei=>$val){

                    if($todosModulos){
                        $horas = $this->somaHoraAviao($todosModulos,$config['aviao']);
                    }else{
                        $horas = $config['horas'];
                    }
                    // if($horas > 0 && $horas >= @$val['limite']){ //apartir de >=
                    if($horas > 0 && $horas >= @$val['limite']){ //apartir de >=
                        $valor = @$val[$arr_tabelas[$sele_valores]];
                        $custo += @$val['custo_real'];
                        $padrao = @$val['hora_padrao'];
                        if($is_recheck){
                            //Para os cursos de recheck mamter o valor da tabela com valor padrão
                            $padrao = $valor;

                        }
                    }

                }
            }
            // if(Qlib::isAdmin(1)){
            // 	lib_print($config);
            // 	dd($arr_pacotoes);
            // }
            if($valor){

                $valor = str_replace('R$','',$valor);
                $valor1 = Qlib::precoDbdase($valor);
                $valor = (double)$valor1;
                $valor = ((int)$config['horas']) * (Qlib::precoDbdase($valor));
                $ret['valor'] = $valor;
            }
            if($custo){
                $custo = str_replace('R$','',$custo);
                $custo = (double)$custo;
                $custo = ((int)$config['horas']) * (Qlib::precoDbdase($custo));
                $ret['custo'] = Qlib::precoDbdase($custo);
            }
            if($padrao){
                $padrao = str_replace('R$','',$padrao);
                $padrao = Qlib::precoDbdase($padrao);
                $padrao = (double)$padrao;
                // if(Qlib::isAdmin(1)){
                // 	lib_print($padrao);
                // }
                $padrao = ((int)$config['horas']) * (Qlib::precoDbdase($padrao));
                $ret['padrao'] = Qlib::precoDbdase($padrao);
            }
        }else{

            $ret = 'Avião não selecionado';

        }
        return $ret;

    }

    public function calcPrecModulos2($config=false,$ajax='',$sele_valores=false,$todosModulos=false){

        $ret = false;

        if($config){

            if($ajax =='n'){

                $id_av = $config['aviao'][0];

            }else{

                $id_av = $ajax;

            }

            $arr_pacotoes = $this->pacotesAeronaves($id_av);

            $valor = NULL;

            $arr_tabelas = Qlib::sql_array("SELECT * FROM ".$GLOBALS['tab50']." WHERE ativo = 's' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'url2','url');
            if(is_array($arr_pacotoes)){
                foreach($arr_pacotoes As $kei=>$val){

                    if($todosModulos){

                        $horas = somaHoraAviao2($todosModulos,$id_av);

                    }else{

                        $horas = $config['limite'];

                    }

                    if($horas > 0 && $horas >= $val['limite']){ //apartir de >=

                        /*if($sele_valores == 'tabela-hora-padao'){

                            $valor = $val ['horas_c_comb'];

                        }

                        if($sele_valores == 'valores_s_comb'){

                            $valor = $val ['horas_s_comb'];

                        }

                        if($sele_valores == 'valores_fumec'){

                            $valor = isset($val ['horas_fumec']) ? $val ['horas_fumec'] : false;

                        }

                        if($sele_valores == 'valores_livre'){

                            $valor = isset($val ['horas_livre']) ? $val ['horas_livre'] : false;

                        }*/

                        $valor = $val[$arr_tabelas[$sele_valores]];

                    }

                }
            }

            if($valor){

                $valor = str_replace('R$','',$valor);

                //$valor = Qlib::precoDbdase($valor); //teste

                $valor = ($config['limite']) * (Qlib::precoDbdase($valor));

                $ret = $valor;

            }

        }

        return $ret;

    }

    public function calcPrecModulos3($config=false,$sele_valores=false,$todosModulos=false){

        $ret['valor'] = 0;
        $ret['custo'] = 0;
        if(isset($config['aviao'])&&!empty($config['aviao'])){
            $arr_pacotoes = $this->pacotesAeronaves($config['aviao']);
            $arr_tabelas = Qlib::sql_array("SELECT * FROM ".$GLOBALS['tab50']." WHERE ativo = 's' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'url2','url');

            $valor = NULL;
            $custo = NULL;
            $id_turma = isset($_GET['id_turma']) ? $_GET['id_turma'] : 0;
            $numePrevTurma = (new CursosController)->numePrevTurma(['id_turma'=>$id_turma]);
            dd($numePrevTurma);
            foreach($arr_pacotoes As $kei=>$val){
                if($todosModulos){
                    $horas = somaHoraAviao($todosModulos,$config['aviao']);
                }else{
                    $horas = $config['horas'];
                }
                if(isset($val['turma'])){
                    if($val['turma']==$numePrevTurma){
                        if($horas > 0 && $horas >= @$val['limite']){ //apartir de >=
                            $valor = @$val[$arr_tabelas[$sele_valores]];
                            $custo += @$val['custo_real'];
                        }
                    }
                // }else{
                // 	if(@$val[$arr_tabelas[$sele_valores]]){

                // 		echo @$val[$arr_tabelas[$sele_valores]];
                // 		lib_print($val);
                // 	}
                // 	if($horas > 0 && $horas >= @$val['limite']){ //apartir de >=
                // 		$valor = @$val[$arr_tabelas[$sele_valores]];
                // 		$custo += @$val['custo_real'];
                // 	}
                }

            }

            if($valor){
                $valor = str_replace('R$','',$valor);
                $valor = ($config['horas']) * (precoDbdase($valor));
                $ret['valor'] = $valor;
            }
            if($custo){
                $custo = str_replace('R$','',$custo);
                $custo = ($config['horas']) * (precoDbdase($custo));
                $ret['custo'] = Qlib::precoDbdase($custo);
            }
        }else{

            $ret = 'Avião não selecionado';

        }
        // if(Qlib::isAdmin(1)){
        // 	// lib_print($arr_pacotoes);
        // 	lib_print($ret);
        // 	// lib_print($arr_pacotoes);
        // }
        return $ret;

    }
    /**
     * Metodo para gerar uma simução do valor do comustivel no orçamento
     */
    public function simuladorCombustivel($token = null,$dados=false)
	{

		$ret['exec'] = false;
		$ret['valor'] = 0;
		$ret['valor_litro'] = null;
		$ret['tipo_pagamento'] = '';
		$ret['color_tipo_pagamento'] = '';

		if($token){

			if(!$dados){

				$dados = $this->dm($token);

			}
			if(!isset($dados['modulos']) && isset($dados['orc'])){
				$arr_mod = Qlib::lib_json_array($dados['orc']);
				if(isset($arr_mod['modulos'])){
					$dados['modulos'] = $arr_mod['modulos'];
				}
			}

			if(isset($dados['modulos']) && is_array($dados['modulos'])){

				$arr_mod = $dados['modulos'];

				$previsao_consumo = NULL;
				$preco_litro = null;
				foreach ($arr_mod as $k => $v) {
					$v['aviao'] = isset($v['aviao'])?$v['aviao']:0;
					$dAviao = Qlib::buscaValorDb0($GLOBALS['tab54'],'id',$v['aviao'],'config');

					if($dAviao){

						$arr_dAv = Qlib::lib_json_array($dAviao);

						if(isset($arr_dAv['combustivel']['consumo_hora']) && isset($arr_dAv['combustivel']['preco_litro']) && isset($arr_dAv['combustivel']['ativar']) && $arr_dAv['combustivel']['ativar']=='s'){

							$p_litro = Qlib::qoption('preco_litro')?Qlib::qoption('preco_litro'): $arr_dAv['combustivel']['preco_litro'];
							$preco_litro = Qlib::precoDbdase($p_litro);
							$consumo = ((int)$arr_dAv['combustivel']['consumo_hora'] * (int)$v['horas']); //
							$previsao_consumo += ($preco_litro * $consumo);
						}

					}

				}

				if($previsao_consumo){

					$ret['valor'] = $previsao_consumo;
					$ret['valor_litro'] = $preco_litro;
					$ret['tipo_pagamento'] = $this->pagamento_combustivel($token,@$dados['orc']);
					$ret['exec'] = true;
					if($ret['tipo_pagamento']=='antecipado'){
						$ret['color_tipo_pagamento'] = 'text-success';
					}else{
						$ret['color_tipo_pagamento'] = 'text-danger';
					}
					// if(Qlib::isAdmin(1)){
					// 	dd($ret);
					// }
				}



			}

		}
		return $ret;
	}
    /**
	 * Retora o numero de horas de um orçamento
	 * @param string $id_matricula
	 * @return integer $ret
	 */
	public function horas_orcamento($id_matricula){
		$ret = 0;
		if($id_matricula){
			$json_orc = Qlib::buscaValorDb0($GLOBALS['tab12'],'id',$id_matricula,'orc');
			if($json_orc){
				$arr = Qlib::lib_json_array($json_orc);
				//veriricar o tipo de curso se for plando de formação vai buscar na tabela de eventos as horas relacionadas
				if(isset($arr['modulos'])){
					foreach ($arr['modulos'] as $k => $v) {
					   $ret += (int)@$v['horas'];
					}
				}
			}
		}
		return $ret;
	}
    /**
     * Metodo para criar um orçamento
     */
    public function salvarMatricula($config=false){

        $ret = false;


        //Exemplo

        /*

        $config = array('id_cliente'=>'','id_curso'=>'','status'=>'');

        $ret = salvarMatricula($config);

        */

        if($config){
            /*Fim Configurações automaticas*/
            // if(Qlib::isAdmin(1)){
            // 	echo "statat: $statusAtual <br> situ  $situacaoAtual";
            // 	echo "<br>form situ:".$config['situacao'];
            // 	dd($config);
            // 	// $config['situacao'] = 'a';
            // }
            $token = isset($config['token']) ? $config['token'] : false;
            $statusAtual = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$config['token'],'status');
            $situacaoAtual = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$config['token'],'situacao');
            $config['situacao'] = isset($config['situacao'])?$config['situacao']:$situacaoAtual;
            //verifica se o contrato está assinado
            $is_signed = $this->verificaDataAssinatura(['campo_bus'=>'token','token'=>@$config['token']]);
            if(isset($config['origem']) && $config['origem']=='atendimento_flow'){
                if(isset($config['id']) && !$token){
                    $dm = $this->dm($token); //dados_tab($GLOBALS['tab12'],'token,situacao,id_cliente,id_curso',"WHERE id='".$config['id']."'");
                    if($dm){
                        foreach ($dm as $k1 => $v1) {
                            $config[$k1] = $v1;
                        }
                        //$config['token'] = Qlib::buscaValorDb0($GLOBALS['tab12'],'id',$config['id'],'token');
                    }

                }
            }

            $config['token'] 	= isset($config['token'])	?$config['token']	:uniqid();

            $config['conf'] 	= isset($config['conf'])	?$config['conf']	:'s';

            $local			 	= isset($config['local'])	?$config['local']	:false;


            $cond_valid = isset($config['cond_valid'])?$config['cond_valid'] : "WHERE `id_cliente` = '".$config['id_cliente']."' AND id_curso='".$config['id_curso']."' AND ".Qlib::compleDelete();
            $tipo_curso = 0;

            if(isset($config['id_curso'])){

                $cursoRecorrente = $this->cursoRecorrente($config['id_curso']);
                if($cursoRecorrente){
                    //Vamos verificar se ja tem horas compradas para esse curso que está no status != 5 (Curso concluido)
                    $tem_proposta = $this->tem_proposta($config['id_curso'],$config['id_cliente'],$config['token']);
                    // if(is_sandbox()){
                    // 	lib_print($tem_proposta);
                    // }
                    if($tem_proposta['exec']){
                        //Se tiver bloquear o processo de salvamento
                        return Qlib::lib_array_json($tem_proposta);
                    }
                    $cond_valid = "WHERE `token` = '".$config['token']."' AND ".Qlib::compleDelete();
                }
                $tipo_curso = (new CursosController)->tipo($config['id_curso']);
            }

            $type_alt = isset($config['type_alt'])? $config['type_alt'] : 2;

            $tabUser = $GLOBALS['tab12'];

            /*Inicio Configurações automaticas*/

            $config['aluno']			 = isset($config['aluno']) ? $config['aluno'] : Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$config['id_cliente'],'Nome').' '.Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$config['id_cliente'],'sobrenome');

            $config['responsavel'] = isset($config['responsavel']) ? $config['responsavel'] : Qlib::buscaValorDb0($GLOBALS['tab16'],'id',@$config['id_responsavel'],'Nome');

            if(isset($config['dados']['orc'])){

                $config['orc'] = $config['dados']['orc'];
            }

            // $ead = new temaEAD;

            if(isset($config['tag'])){

                //Os pontos são calculados mediate tag

                $config['pontos'] = $this->pontuaTags($config);

            }

            if(isset($config['situacao'])&&$config['situacao']=='n'){

                //situação = n indica que ainda não recebeu atendimento mais se chegou ate aqui é porque de alguma forma esta em andamento = a

                $config['situacao'] = 'a';

            }


            if($statusAtual==1&&$config['situacao']=='2'){

                $config['situacao'] = 'g';

                //$config['data_matricula'] = $GLOBALS['dataLocal'];

                $config['data_matricula'] = date('d/m/Y');

                $config['data_contrato'] = $GLOBALS['dtBanco'];

            }elseif($config['status']==8){
                //Rescição de contrato para isso é necessario que tenha o contrato assinado
                if(Qlib::isAdmin(3)){
                    $mensagem = Qlib::formatMensagemInfo('Não é possível salvar este status para clientes sem <b>CONTRATO ASSINADO</b>','danger');
                    if(!isset($config['id']) || !isset($config['id'])){
                        $ret['exec'] = false;
                        $ret['mens'] = $mensagem;
                        $ret['mensa'] = $mensagem;
                        return Qlib::lib_array_json($ret);
                    }
                    $numero_contrato = $this->numero_contrato($config['id']);
                    // dd($numero_contrato);
                    if(!$numero_contrato){
                        $ret['exec'] = false;
                        $ret['mensa'] = $mensagem;
                        $ret['mens'] = $mensagem;
                        return Qlib::lib_array_json($ret);
                    }
                    // lib_print($config);//exit;
                }
            }elseif($statusAtual>'1'&&$config['status']==1){


                $config['situacao'] = 'a';

                $config['data_matricula'] = '00/00/0000';

                $config['data_contrato'] = '0000-00-00';
            // removido a instrução de que se o status for 1 ele voltar para a situaçção de atendimento solicitação da luiza em 15/02/2024
            }elseif($situacaoAtual!='g'&&isset($config['situacao'])&&$config['situacao']=='g'){

                $config['situacao'] = 'g';

                //$config['data_matricula'] = $GLOBALS['dataLocal'];

                $config['data_matricula'] = date('d/m/Y');
                // $config['data_contrato'] = $GLOBALS['dtBanco'];
                $config['data_contrato'] = $is_signed;
                $config['status']=2;
                //print_r($config);

                /*Gravar a proposta de orçamento fixo*/

                $modulos = $this->gerarOrcamento($config['token']);

                if($modulos){

                    $config['proposta'] = Qlib::encodeArray($modulos);

                }

            }elseif($situacaoAtual!='p'&&isset($config['situacao'])&&$config['situacao']=='p'){

                $config['data_matricula'] = date('d/m/Y');

                $config['data_contrato'] = $GLOBALS['dtBanco'];

                /*Gravar a proposta de orçamento fixo*/

                $modulos = $this->gerarOrcamento($config['token']);

                if($modulos){

                    $config['proposta'] = Qlib::encodeArray($modulos);

                }

            }
            if(isset($config['total'])){
                $config['total'] = Qlib::precoDbdase($config['total']);
            }
            $config2 = array(

                        'tab'=>$tabUser,

                        'valida'=>true,

                        'condicao_validar'=>$cond_valid,

                        'ac'=>$config['ac'],

                        'sqlAux'=>false,

                        'type_alt'=>$type_alt,

                        'dadosForm' => $config

            );
            // if(Qlib::isAdmin(1)){
            // 	lib_print($config2);
            // 	lib_print($config);
            // 	return $ret;
            // }
            $config['salv_historico'] = isset($config['salv_historico']) ? $config['salv_historico'] :true;

            if($config['salv_historico']){

                $config_historico = array('ac'=>$config['ac'],'post'=>$config,'tab'=>$tabUser,'status'=>@$config['status']);

                $config2['sqlAux'] = $this->sqlSalvarHistorico_matricula($config_historico);

            }
            $tipo_curso = 2;
            // if(Qlib::isAdmin(10)){
            //     $tipo_curso = Cursos::tipo($config['id_curso']);
            // }
            if($is_signed){
                //se está assinado remover a atualização de orçamento
                unset($config2['dadosForm']['orc']);
                if($tipo_curso==4){
                    $total_salvo = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$config['token'],'total');

                    if($total_salvo!='0.00'){
                        unset($config2['dadosForm']['inscricao'],$config2['dadosForm']['subtotal'],$config2['dadosForm']['desconto'],$config2['dadosForm']['total'],$config2['dadosForm']['proposta']);
                    }

                }
                if($config['situacao']=='g'){
                    if(!isset($config['meta']['ganhos_plano']) && isset($config['id'])){
                        $ret['remover_meta'] = Qlib::delete_matriculameta($config['id'],'ganhos_plano');
                    }
                    // $config['meta']['ganhos_plano'] = isset($config['meta']['ganhos_plano'])?$config['meta']['ganhos_plano']:'';
                }
            }
            $ret = Qlib::update_tab($tabUser,$config2['dadosForm'],$cond_valid,true);
            // $ret = json_decode(lib_salvarFormulario($config2),true);

            // if(is_sandbox()){
            // 	// lib_print($config2);
            // 	lib_print($ret);
            // }
            if(isset($config['meta'])){
                $ret['meta'] = $this->sava_meta_fields($config);

                // echo $situacaoAtual.' '.$config['situacao'];
            }
            if(Qlib::isAdmin(10)){
                if(isset($config['meta']['desconto']) && empty($config['meta']['desconto']) && isset($config['id']) && !empty($config['id'])){
                    /**remove desconto meta */
                    $ret['remover_meta_desconto'] = Qlib::delete_matriculameta($config['id'],'desconto');
                    $ret['remover_meta_desconto'] = Qlib::delete_matriculameta($config['id'],'d_desconto');
                }else{
                    //verificar se existe algum desconto salvo...
                    $existe_desconto = Qlib::get_matriculameta($config['id'],'desconto');
                    // if(is_sandbox()){
                    // 	dd($existe_desconto);
                    // }
                    if(!isset($config['meta']['desconto']) && $existe_desconto){
                        //se tem desconto salvo mais não tem mais um post com o meta campos que grava ou atualiza ele nesse caso tem que ser removido
                        $ret['remover_meta_desconto'] = Qlib::delete_matriculameta($config['id'],'desconto');
                        $ret['remover_meta_desconto'] = Qlib::delete_matriculameta($config['id'],'d_desconto');
                    }
                }

            }
            // salvar evento de ganho
            if($config['situacao']=='g'){
                if($tipo_curso==4){
                    $ignora_parcelamento = false;
                }else{
                    $ignora_parcelamento = false;
                }
                $ret['reg_ganho'] = $this->reg_ganho($config['token'],$ignora_parcelamento);
            }

            if(isset($config['token']) && isset($config['rescisao']['enviar_leilao']) && $config['rescisao']['enviar_leilao']=='s'){
                if(isset($config['token'])){
                    $ret['envia_rescisao_leilao'] = $this->envia_rescisao_leilao($config['token']);
                }

            }
            /*inicio salvar valor do negocio (matricula)*/

            if(isset($config['token'])&& !empty($config['token']) && $ret['exec'] && $tipo_curso!=4){


                if(isset($config['orc']['modulos'])&& !empty($config['orc']['modulos'])){

                    if($config['ac']=='cad'){

                        $gerarOrcamento = $this->gerarOrcamento($config['token']);

                        if(isset($gerarOrcamento['totalCurso'])&&isset($gerarOrcamento['totalOrcamento']) && !$is_signed){

                            $total = $gerarOrcamento['totalOrcamento'];

                            $subtotal = $gerarOrcamento['totalCurso'];

                            $porcentagem_comissao = Qlib::qoption('comissao');

                            if(isset($config['orc']['sele_valores'])){

                                    $dadosTab = Qlib::buscaValorDb0($GLOBALS['tab50'],'url',$config['orc']['sele_valores'],'config');

                                    if($dadosTab){

                                        $arr_conf = json_decode($dadosTab,true);

                                        if(isset($arr_conf['comissao'])){

                                            $porcentagem_comissao = str_replace(',','.',$arr_conf['comissao']);

                                        }

                                    }

                            }

                            $valor_comissao = ((double)$subtotal)*((double)$porcentagem_comissao/100);

                            $valor_comissao = round($valor_comissao,2);

                            $sqlAt = "UPDATE ".$GLOBALS['tab12']." SET total = '".$total."',subtotal = '".$subtotal."',porcentagem_comissao = '".$porcentagem_comissao."',valor_comissao = '".$valor_comissao."' WHERE token = '".$config['token']."'";

                            $ret['atualizaTotalOrcamento'] = DB::statement($sqlAt);

                        }

                        //if(Qlib::isAdmin(1)){

                            //lib_print($ret);exit;

                        //}

                    }

                    if($config['ac']=='alt' && isset($config['situacao'])&&$config['situacao']!='g'){

                        $gerarOrcamento = $this->gerarOrcamento($config['token']);

                        $ret['valorAtual'] = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$config['token'],'total');

                        //if(isset($gerarOrcamento['totalOrcamento'])&& $gerarOrcamento['totalOrcamento']!=$ret['valorAtual']){

                        if(isset($gerarOrcamento['totalOrcamento'])&&isset($gerarOrcamento['totalCurso'])){

                            $total = $gerarOrcamento['totalOrcamento'];

                            $subtotal = $gerarOrcamento['totalCurso'];

                            $porcentagem_comissao = Qlib::qoption('comissao');


                            if(isset($config['orc']['sele_valores'])){

                                    $dadosTab = Qlib::buscaValorDb0($GLOBALS['tab50'],'url',$config['orc']['sele_valores'],'config');

                                    if($dadosTab){

                                        $arr_conf = json_decode($dadosTab,true);

                                        if(isset($arr_conf['comissao'])){

                                            $porcentagem_comissao = str_replace(',','.',$arr_conf['comissao']);

                                        }

                                    }

                            }
                            if(Qlib::isAdmin(10)){
                                $vav = $this->verificaAutorizacaoVenda($config['token']);
                                if($vav){
                                    $porcentagem_comissao=0;
                                }

                            }
                            $valor_comissao = ((double)$subtotal)*((double)$porcentagem_comissao/100);

                            $valor_comissao = Qlib::precoDbdase(round($valor_comissao,2));
                            $compleSalv = false;
                            if(isset($gerarOrcamento['salvaTotais']) && is_array($gerarOrcamento['salvaTotais'])){
                                $compleSalv = ",totais='".Qlib::lib_array_json($gerarOrcamento['salvaTotais'])."'";
                                // lib_print($gerarOrcamento['salvaTotais']);
                            }
                            if(!$is_signed){
                                $sqlAt = "UPDATE IGNORE ".$GLOBALS['tab12']." SET total = '". Qlib::precoDbdase($total)."',subtotal = '".Qlib::precoDbdase($subtotal)."',porcentagem_comissao = '".$porcentagem_comissao."',valor_comissao = '".$valor_comissao."'$compleSalv WHERE token = '".$config['token']."'";
                                $ret['atualizaTotalOrcamento'] = DB::statement($sqlAt);
                            }

                            // if(Qlib::isAdmin(1)){

                            // 	echo $sqlAt;
                            // 	lib_print($gerarOrcamento);

                            // }

                        }

                        //if(Qlib::isAdmin(1)){

                            //lib_print($ret);exit;

                        //}

                    }

                }else{

                    $dadosCurso = Qlib::dados_tab($GLOBALS['tab10'],['campos'=>'*','where'=>"WHERE id = '".$config['id_curso']."'"]);

                    $porcentagem_comissao = false;

                    if(isset($config['id_curso'])&&$config['id_curso']>0  && $config['status']==1){

                        if($dadosCurso){

                            $valorCurso = (isset($config['total'])&&$config['total']!=0)? $config['total'] : $dadosCurso[0]['valor'];

                            $inscricao = $dadosCurso[0]['inscricao'];

                            $categoria = $dadosCurso[0]['categoria'];

                            $configProduto = $dadosCurso[0]['config'];

                            $arr_config = Qlib::lib_json_array($configProduto);

                            if(isset($arr_config['comissao']) && !empty($arr_config['comissao'])){

                                $porcentagem_comissao = str_replace(',','.',$arr_config['comissao']);

                            }

                            //$comissao = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'categoria');

                        }else{

                            $valorCurso =(isset($config['total'])&&$config['total']!=0)? $config['total'] : Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'valor');

                            $inscricao = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'inscricao');

                            $categoria = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'categoria');

                        //$comissao = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$config['id_curso'],'categoria');

                        }

                        $total = (double)$valorCurso+(double)$inscricao;
                        $desconto = isset($config['desconto']) ? $config['desconto']: 0;
                        $porcentagem_comissao = $porcentagem_comissao ? $porcentagem_comissao: Qlib::qoption('comissao');

                        $valor_comissao = ($total)*($porcentagem_comissao/100);

                        $valor_comissao = round($valor_comissao,2);

                        if($config['ac']=='cad'){

                            if($categoria!='cursos_presencias'){

                                $valor_comissao = $valor_comissao?$valor_comissao: 0;

                                //$porcentagem_comissao = 0;

                            }

                            if(isset($total) && !$is_signed){

                                $sqlAt = "UPDATE IGNORE ".$GLOBALS['tab12']." SET total = '".$total."',porcentagem_comissao = '".$porcentagem_comissao."',valor_comissao = '".$valor_comissao."' WHERE token = '".$config['token']."'";

                                $ret['atualizaTotalOrcamento'] = DB::statement($sqlAt);

                            }

                        }

                        if($config['ac']=='alt'){
                            // if($categoria=='cursos_presencias'){

                            // 	$gerarOrcamento = gerarOrcamento($config['token']);

                            // 	$total = @$gerarOrcamento['totalOrcamento'];

                            // 	$valor_comissao = ($total)*($porcentagem_comissao/100);

                            // 	$valor_comissao = round($valor_comissao,2);

                            // }else{

                                $gerarOrcamento = $this->gerarOrcamento($config['token']);
                                //CURSOS TIPO 1=EAD TIPO 4=PLANO DE FORMAÇÃO

                                if($tipo_curso==1 || $tipo_curso==4){
                                    $total = @$gerarOrcamento['total'];
                                }else{
                                    $total = @$gerarOrcamento['totalOrcamento'];
                                }
                                // if(Qlib::isAdmin(1)){
                                // 	echo $tipo_curso;
                                // 	dd($gerarOrcamento);
                                // }

                                $total = (double)$total;
                                if($desconto){
                                    $total = ($total - Qlib::precoDbdase($desconto));
                                    // echo $total;
                                    // dd($gerarOrcamento);
                                }

                                $valor_comissao = ($total)*($porcentagem_comissao/100);

                                $valor_comissao = round($valor_comissao,2);

                                //$porcentagem_comissao = 0;

                                //$valor_comissao = 0;


                            //}
                            $valor_comissao = str_replace(',','.',$valor_comissao);

                            $ret['valorAtual'] = Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$config['token'],'total');

                            if(!$is_signed){
                                $sqlAt = "UPDATE IGNORE ".$GLOBALS['tab12']." SET total = '".$total."',porcentagem_comissao = '".$porcentagem_comissao."',valor_comissao = '".$valor_comissao."' WHERE token = '".$config['token']."'";
                                $ret['atualizaTotalOrcamento'] = DB::statement($sqlAt);
                                // if(Qlib::isAdmin(1)){
                                // 	lib_print($ret);
                                // }
                                // if(Qlib::isAdmin(1)){
                                // 	dd($gerarOrcamento);
                                // }
                            }

                        }

                    }

                }

                if(isset($config['bt_press']) && $config['bt_press']=='continuar'&&isset($ret['idCad'])){

                    $configLi = array('id'=>$ret['idCad']);

                    $atendimento_flow = new atendimento_flow;

                    $ret['listarTarefas'] = $atendimento_flow->listAtendimento($configLi);

                }

            }

            /*fim salvar valor do negocio (matricula)*/



            //if(isset($_GET['bt_press']) && $_GET['bt_press']=='finalizar'){

                if(isset($config['token_atendimento'])&&isset($config['inic_atendimento']))

                $ret2 = regDuracaoAtendimento($config['token_atendimento'],$config['inic_atendimento']);

                //if($ret2['exec']){

                    if(isset($ret['salvar']['mess'])&&$ret['salvar']['mess']=='enc'){

                        $curso = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$ret['dataSalv']['id_curso'],'nome');

                        $ret['mensa'] = Qlib::formatMensagem0('Uma proposta para <b>'.$ret['dataSalv']['aluno'].'</b> do curso <b>'.$curso.'</b> já foi encontrada!','warning',450000);

                    }

                    $ret['regDuracaoAtendimento'] = @$ret2['exec'];

                    if(isset($config['cliente'])){

                        $formulario = new formularios;

                        $ret['salvarCliente'] = json_decode($formulario->salvar($config['cliente']));

                        //unset($config['cliente']);

                    }

                    $ret = json_encode($ret);

                //}

            //}

        }

        return $ret;

    }
    /**
     * Metodo para verificar se uma venda está autorizada pelo diretor
     */
    public function verificaAutorizacaoVenda($tk_matricula=false,$config=false){
		$ret = false;
		if($tk_matricula){
			$tag_sys = 'autorizado_diretor';
			if(!empty($config)){
				$dm = $config;
			}else{
				$dm = Cursos::dadosMatricula($tk_matricula);
			}
			if(isset($dm[0]['tag_sys']) && !empty($dm[0]['tag_sys'])){
				$arr_t = lib_json_array($dm[0]['tag_sys']);
				if(is_array($arr_t)){
					if(in_array($tag_sys,$arr_t)){
						$ret = true;
					}
				}
			}
		}
		return $ret;
	}
	public function liberarVenda($config=false){
		$ret['exec'] = false;
		$ret['config'] = $config;
		//busacar a senha
		$senha = isset($config['senha']) ? $config['senha'] : false;
		$token_matricula = isset($config['token_matricula']) ? $config['token_matricula'] : false;
		$id_diretor = 14;
		$senha_db = buscaValorDb_SERVER('usuarios_sistemas','id',$id_diretor,'senha');
		if($senha_db && $senha){
			//comprar a senha
			$dadosMatricula = cursos::dadosMatricula($token_matricula);
			$vav = $this->verificaAutorizacaoVenda($token_matricula,$dadosMatricula);
			// die(var_dump($vav));
			if($vav){
				$ret['exec'] = true;
				return $ret;
			}
			if($dadosMatricula && ($dm=$dadosMatricula[0])){
				$id_matricula = $dm['id'];
				$psv = password_verify($senha,$senha_db);
				$ret['exec'] = $psv;
				//salvar a liberação da senha no orçamento
				$id_matricula = buscaValorDb($GLOBALS['tab12'],'token',$token_matricula,'id');
				if($psv && $id_matricula){
					$evento = 'Autorização de venda abaixo do custo';
					$tag_sys = 'autorizado_diretor';
					$regEventos = $this->regEventos($id_matricula,$evento,['dados'=>$dm],$tag_sys);
					$ret['regEventos'] = $regEventos;
					if($regEventos['exec']){
						$dados_gravados = cursos::dadosMatricula($token_matricula);
						if($dados_gravados){
							$ret['dados_gravados'] = $dados_gravados[0];
						}
					}
				}else{

				}
			}
		}
		return $ret;
	}

    /**
	 * Metodo para disparar post do painel rescisão ao salvar uma rescisão
	 * @param string $token_matricula
	 */
	public function envia_rescisao_leilao($token_matricula=false){
		//uso $ret = cursos::envia_rescisao_leilao($token_matricula);
		$ret = false;
		if(!$token_matricula){
			return $ret;
		}
		$curl = curl_init();
		// $url_webwook = 'https://leiloair.com.br/api/webhook/contratos';
		$url_webwook = 'https://repasses.aeroclubejf.com.br/api/webhook/contratos';
		$dados = $this->dm($token_matricula);
		if(!$dados){
			return $ret;
		}
		unset($dados['proposta']);
		$json_dados = Qlib::lib_array_json($dados[0]);
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url_webwook ,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>$json_dados,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		return  $response;

	}
    /**
     * Verifica se um curso é recorrete
     */
    public function cursoRecorrente($id_curso=false,$id_cliente=false){

        $ret = false;

        if($id_curso){

            $config = Qlib::buscaValorDb0($GLOBALS['tab10'],'id',$id_curso,'config');

            if($config){

                $arr_config = Qlib::lib_json_array($config);

                if(isset($arr_config['adc']['recorrente'])&&$arr_config['adc']['recorrente']=='s'){

                    $ret = true;

                }

            }

        }

        return $ret;

    }
    /**
     * Metodo para verificar se um cliente ja tem alguma proposta em um status
     * @param int $id_curso,int $id_cliente
     * @return array $ret
     */
    public function tem_proposta($id_curso,$id_cliente,$token_matricula){
        $ret['exec'] = false;
        if(!$token_matricula){
            return $ret;
        }
        $d = Qlib::dados_tab($GLOBALS['tab12'],['campos'=>'id,token,status','where'=>"WHERE id_curso='$id_curso' AND status!='5' AND id_cliente='$id_cliente' AND ".Qlib::compleDelete()]);
        if($d){
            // $d = $d[0];
            if($token_matricula == $d['token']){
                //Se encontrou o mesmo pode retornar
                return $ret;
            }
            $arr_status_mat = sql_array("SELECT * FROM ".$GLOBALS['tab24']." ORDER BY nome ASC",'nome','id');
            $link = Qlib::$RAIZ.'/cursos?sec=bWF0cmljdWxhcw==&list=false&acao=alt&id='.base64_encode($d['id']);
            $mensagem = Qlib::formatMensagemInfo('Não é possível salvar, foi encontrado uma proposta no status <b>'.$arr_status_mat[$d['status']].'</b> ele precisa concluir para poder prosseguir.. <br> Por favor verifique se o status está correto antes de fazer uma nova venda ou gerar orçamento para este cliente<br><a href="'.$link.'" target="_BLANK" class="btn btn-default">Acessar agora</a>','danger');
            $ret['exec'] = true;
            $ret['mens'] = $mensagem;
            $ret['mensa'] = $mensagem;
        }
        return $ret;
    }
    /**
     * cria uma string sql para salver um historico de matricula
     */
    public function sqlSalvarHistorico_matricula($config=false){

        $ret = false;

        //$config = array('ac'=>'cad','post'=>$post);

        // $suf_in = SUF_SYS;

        $arr_status_mat = sql_array("SELECT * FROM ".$GLOBALS['tab24']." ORDER BY nome ASC",'nome','id');

        if($config['ac'] == 'cad'){

            // if(isset($_SESSION[$suf_in]['nome'.$suf_in])){

            //     $autor = $_SESSION[$suf_in]['nome'.$suf_in].' '.$_SESSION[$suf_in]['sobrenome'.$suf_in];

            // }else{

                $autor = 'Sistema';

            // }

            $historico[0] = array(

                                    'data'=>date('d/m/Y H:m:i'),

                                    'autor'=>$autor,

            );

            $historico[0]['evento'] = isset($config['evento']) ? $config['evento'] : 'Criado';

            $historico[0]['status'] = isset($config['status']) ? $config['status'] : 1;

            $historico[0]['status_estenso'] = $arr_status_mat[$historico[0]['status']];

            if(isset($config['post']['sec'])){

                $historico[0]['sec'] = $config['post']['sec'];

            }

            $ret = ",`historico`='".json_encode($historico,JSON_UNESCAPED_UNICODE)."' ";

            if(isset($config['post']['tag'][0]) && !empty($config['post']['tag'][0])){

                $historico[0]['setor'] = isset($config['post']['setor']) ? $config['post']['setor'] : false;

                $historico[0]['datastr'] = strtotime(date('Y-m-d'));

                $historico[0]['memo'] = isset($config['post']['memo']) ? $config['post']['memo'] : false;

                $historico[0]['confirmar_agenda'] = isset($config['post']['confirmar_agenda']) ? $config['post']['confirmar_agenda'] : false;

                $historico[0]['tags']	 	= $config['post']['tag'];

                $ret .= ",`reg_agendamento`='".json_encode($historico,JSON_UNESCAPED_UNICODE)."' ";

            }

        }

        if($config['ac'] == 'alt'){

            if(isset($config['post']['id'])){

                $histAtual			 = Qlib::buscaValorDb0($config['tab'],'id',$config['post']['id'],'historico');

                $reg_agendamentoAtual = Qlib::buscaValorDb0($config['tab'],'id',$config['post']['id'],'reg_agendamento');

            }

            $historico = array(
                'data'=>date('d/m/Y H:m:i'),
                'autor'=>@$autor,
            );

            $historico['evento'] = isset($config['evento']) ? $config['evento'] : 'Atualizado';

            $historico['status'] = isset($config['status']) ? $config['status'] : 1;

            $historico['status_estenso'] = $arr_status_mat[$historico['status']];

            if(!empty($histAtual)){

                $arr_ = json_decode($histAtual,true);

                if(is_array($arr_)){

                    array_push($arr_, $historico);

                    $ret = ",`historico`='".json_encode($arr_,JSON_UNESCAPED_UNICODE)."' ";

                }

            }else{

                $histAtual = array($historico);

                $ret = ",`historico`='".json_encode($histAtual,JSON_UNESCAPED_UNICODE)."' ";

            }

            if(isset($config['post']['tag'][0]) && !empty($config['post']['tag'][0])){

                if(!empty($reg_agendamentoAtual)){

                    $historico['memo'] 	= isset($config['post']['memo']) ? $config['post']['memo'] : false;

                    $historico['tags']	 	= $config['post']['tag'];

                    $historico['setor']	 	= isset($config['post']['setor'])?$config['post']['setor']:false;

                    $historico['datastr'] 	= strtotime(date('Y-m-d'));

                    $historico['confirmar_agenda']	 	= isset($config['post']['confirmar_agenda'])?$config['post']['confirmar_agenda']:'n';

                    $arr_ = json_decode($reg_agendamentoAtual,true);

                    if(is_array($arr_)){

                        array_push($arr_, $historico);

                        $ret .= ",`reg_agendamento`='".json_encode($arr_,JSON_UNESCAPED_UNICODE)."' ";

                    }

                }else{

                    $historico['datastr'] 	= strtotime(date('Y-m-d'));

                    $historico['memo']  = isset($config['post']['memo']) ? $config['post']['memo'] : false;

                    $historico['tags']	 = $config['post']['tag'];

                    $reg_agendamentoAtual = array($historico);

                    $ret .= ",`reg_agendamento`='".json_encode($reg_agendamentoAtual,JSON_UNESCAPED_UNICODE)."' ";

                }

            }



        }

        return $ret;

    }
    /**
     * Gerar pontuação atraves de tag
     */
    public function pontuaTags($config=false){

		$ret = 0;

		if(isset($config['tag'])){

			if(is_array($config['tag'])){

				foreach($config['tag'] As $k=>$val){

					$dadosConfigTag = Qlib::buscaValorDb0($GLOBALS['tab20'],'token',$val,'config');

					if($dadosConfigTag){

						$arr = json_decode($dadosConfigTag,true);

						if(isset($arr['pontos']) && !empty($arr['pontos'])){

							$ret += $arr['pontos'];

						}

					}

				}

			}

		}

		return $ret;

	}
    /**
	 * Metodo para registrar os eventos de ganho nas propostas. para ser usado dentro da função de salvarMatricula sempre que for assionado ele analisa e cria um envento de ganho para relatorios
	 * @param string $tm = tokem da matrucula
	 * @return array $ret
	 */
	public function reg_ganho($tm=false,$ignora_parcelamento=false,$valor=false,$data_situacao=false){
		//dados do matricula
		$ret['exec'] = false;
		if($tm){
			$dm = $this->dm($tm);
			//qual tipo de curso
			if($dm){
				$dm = $dm[0];
				$tab=$GLOBALS['tab40'];
				if($dm['situacao']=='g'){

					$type_alt = 1;
					$tipo = 'r'; //r=relatorio
					$ac = 'cad';
					$valor = $valor ? $valor : $dm['total'];
					$data_situacao = $data_situacao ? $data_situacao : $dm['data_situacao'];
					$autor = $dm['autor'] ?$dm['autor']: 0;//$_SESSION[SUF_SYS]['id'.SUF_SYS];
					$nome_autor = 0;//$_SESSION[SUF_SYS]['nome'.SUF_SYS];
					$memo = 'Proposta <b>Ganha</b> em '.Qlib::dataExibe($dm['data_situacao']);
					// $memo = 'Proposta Ganha';
					$tipo_curso = $dm['tipo_curso'];
					if($tipo_curso==4){
						// dd($dm['tipo_curso']);
						$ganhos_gravados = Qlib::get_matriculameta($dm['id'],'ganhos_plano',true);
						if($ganhos_gravados){
							$ganhos_gravados = Qlib::lib_json_array($ganhos_gravados);
							if(is_array($ganhos_gravados)){


								$sql_remove = "DELETE FROM $tab WHERE situacao='g' AND total IS NULL AND id_matricula='".$dm['id']."'";
								// $deleRepetido = salvarAlterar($sql_remove);
								$deleRepetido = DB::statement($sql_remove);

								foreach ($ganhos_gravados as $kg => $vg) {
									$cond_valid = "WHERE token_atendimento='".$vg['token']."' AND situacao='g' AND ".Qlib::compleDelete();
									$data_situacao = $vg['data_ganho'];
									$valor = Qlib::precoDbdase($vg['valor']);

									$horas = $vg['horas'];
									$config2 = array(
										'tab'=>$tab,
										'valida'=>true,
										'condicao_validar'=>$cond_valid,
										'ac'=>$ac,
										'sqlAux'=>false,
										'campoEncId'=>'token_atendimento',
										'type_alt'=>$type_alt,
										'dadosForm' => [
											'id_matricula'=>$dm['id'],
											'situacao'=>$dm['situacao'],
											'id_cliente'=>$dm['id_cliente'],
											'id_curso'=>$dm['id_curso'],
											'seguido_por'=>$dm['seguido_por'],
											'id_turma'=>$dm['id_turma'],
											'tag'=>['anotacoes'],
											'data_situacao'=>$data_situacao,
											'total'=>$valor,
											'horas'=>$horas,
											'finalizado'=>'s',
											'token'=>uniqid(),
											'token_atendimento'=>$vg['token'],
											'conf'=>'s',
											'tipo'=>$tipo, //relatorio
											'autor'=>$autor, //relatorio
											'memo'=>$memo, //relatorio
											'conversao'=>'s', //relatorio
										]
									);
									// $ret[$kg]['save'] = Qlib::lib_json_array(lib_salvarFormulario($config2));
                                    $ret[$kg]['save'] = Qlib::update_tab($tab,$config2['dadosForm'],$cond_valid);;
									$ret[$kg]['config2'] = $config2;
									if(isset($ret['save'][$kg]) && $ret['save'][$kg]['exec']){
										$ret['exec'] = true;
									}
								}
							}
						}
					}else{
						$cond_valid = "WHERE id_matricula='".$dm['id']."' AND situacao='g' AND ".compleDelete();
						//verifica se ja tem registro duplicado de ganhos..
						$ganhos_salvos = Qlib::dados_tab($tab,['campos'=>'*','where'=>$cond_valid]);
						if($ganhos_salvos){
							if(count($ganhos_salvos)>1){
								//se tiver remove todos para salvar novo
								$ret['del'] = DB::statement("DELETE FROM $tab  $cond_valid");
							}
							// lib_print($ganhos_salvos);
						}
						$infoPag = Qlib::infoPagCurso([
							'token'=>$tm,
						]);
						// if(isset($infoPag['valores']['forma_pagamento']) && !empty($infoPag['valores']['forma_pagamento'])){
						// 	$forma_pagamento = $infoPag['valores']['forma_pagamento'];
						// }
						// $parcelas = false;
						// if(isset($infoPag['valores']['parcelas']) && !empty($infoPag['valores']['parcelas'])){
						// 	$parcelas = $infoPag['valores']['parcelas'].'X ';
						// }
						// if(isset($infoPag['valores']['total']) && !empty($infoPag['valores']['total'])){
						// 	$totalProposta = $parcelas. '<b>'. valor_moeda($infoPag['valores']['total'],'R$ ').'</b>';
						// }
						if(!$ignora_parcelamento){
							//verificar se o valor final foi alterado por um plano de parcelamento
							if(isset($infoPag['valores']['total_parcelado']) && !empty($infoPag['valores']['total_parcelado'])){
								$valor = $infoPag['valores']['total_parcelado'];
							}
						}
						$config2 = array(
							'tab'=>$tab,
							'valida'=>true,
							'condicao_validar'=>$cond_valid,
							'ac'=>$ac,
							'sqlAux'=>false,
							'type_alt'=>$type_alt,
							'dadosForm' => [
								'id_matricula'=>$dm['id'],
								'situacao'=>$dm['situacao'],
								'id_cliente'=>$dm['id_cliente'],
								'id_curso'=>$dm['id_curso'],
								'seguido_por'=>$dm['seguido_por'],
								'id_turma'=>$dm['id_turma'],
								'tag'=>['anotacoes'],
								'data_situacao'=>$data_situacao,
								'total'=>$valor,
								'finalizado'=>'s',
								'token'=>uniqid(),
								'conf'=>'s',
								'tipo'=>$tipo, //relatorio
								'autor'=>$autor, //relatorio
								'memo'=>$memo, //relatorio
								'conversao'=>'s', //relatorio
							]
						);
						$ret['config2'] = $config2;
						// $ret = Qlib::lib_json_array(lib_salvarFormulario($config2));
                        $ret = Qlib::update_tab($tab,$config2['dadosForm'],$cond_valid);;

					}
				}
				$ret['dm'] = $dm;

			}
		}
		return $ret;
	}
	/**
	 * Metodo para sincronizar os ganho da tabela matriculas com a tabela eventos_atendimento
	 * @return array $ret
	 */
	public function sinc_ganhos(){
		global $tab12;
		$ganhos = dados_tab($tab12,'*',"WHERE situacao='g' AND ".compleDelete());
		$ret['exec'] = false;
		$ret['d'] = false;
		if($ganhos){
			// dd($ganhos);
			foreach ($ganhos as $key => $v) {
				$ret['d'][$key] = self::reg_ganho($v['token']);
			}
		}
		return $ret;
	}
	/**
	 * Metodo para veridicar a forma de pagamento de comustivel escolhido
	 * uso $ret = (new Orcamentos)->pagamento_combustivel($token,$org);
	 */
	public function pagamento_combustivel($token,$orc=false){
		$ret = false;
		if(!$orc && $token){
            $dm = $this->dm($token);
            if(isset($dm['orc'])){
                $orc = $dm['orc'];
            }
		}
		if($orc){
			$arr_orc = Qlib::lib_json_array($orc);
			if(isset($arr_orc['sele_pag_combustivel'])){
				$ret = $arr_orc['sele_pag_combustivel'];
			}
		}
		return $ret;
	}

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
        $d = DB::table($this->table)->find($id);

        if(is_null($d)){
            $ret['exec'] = false;
            $ret['status'] = 404;
            $ret['data'] = [];
            return response()->json($ret);
        }else{
            $ret['exec'] = true;
            $ret['status'] = 200;
            $ret['data'] = $d;
            return response()->json($ret);
        }
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
        $d = $request->all();
        $ret['exec'] = false;
        $ret['status'] = 500;
        $ret['message'] = 'Error updating';
        if($d){
            $ret['exec'] = DB::table($this->table)->where('id',$id)->update($d);
            if($ret['exec']){
                $ret['status'] = 200;
                $ret['message'] = 'updated successfully';
                $ret['data'] = DB::table($this->table)->find($id);
            }
        }
        return response()->json($ret);
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
    /**
     * Gera um contrato de qualquer proposta válida
     * @param string $token é o token do proposta
     * @param string $type é o tipo de pagina que será exibida htm|pdf do contrato.
     */
    public function contratos($token,$type=''){
        $opc = request()->get('opc');
        $dm = $this->dm($token);
        $ret = '';
        $nome_arquivo = '';
        $contrato = '';
        if($opc && $dm){
            $ger_cont = false;
            if($opc=='termo_concordancia' || $opc=='termo_escola_voo'){
                $ger_cont = $this->termo_concordancia(['token'=>$token,'type'=>$opc],$dm);
            }else{
                // $ger_cont = $this->contratoAero(['token'=>$token],$dm,$opc);
                $lista_contratos = $this->lista_contratos($dm['id_curso']);
                if(is_array($lista_contratos)){
                    foreach ($lista_contratos as $ka => $va) {
                        if(isset($va['short_code']) && $va['short_code'] == $opc && isset($va['obs']) && ($contrato = $va['obs'])){
                            $ger_cont = $this->contrato_matricula($token,$dm,$contrato);
                        }
                    }
                }
            }
            // dd($ger_cont);
            $nome_arquivo = isset($ger_cont['nome_arquivo']) ? $ger_cont['nome_arquivo'] : '';
            if(isset($ger_cont['contrato']) && !empty($ger_cont['contrato'])){
                $contrato = $ger_cont['contrato'];
            }else{
                $contrato = isset($ger_cont) ? $ger_cont : 'Erro ao gerar contrato';
            }
            if($ger_cont){
                $dados = [
                    'html'=>$contrato,
                    'nome_aquivo_savo'=>$nome_arquivo,
                    'titulo'=>$nome_arquivo,
                    'id_matricula'=>$dm['id'],
                    'token'=>$dm['token'],
                    'short_code'=>$opc,
                    'pasta'=>'contratos',
                ];
                // dd($dados);
                $dados['f_exibe'] = $type;
                if(($type=='pdf' || $type=='server') && $contrato){
                    if($type=='server'){
                        $exec = isset($ger_cont['exec']) ? $ger_cont['exec'] : false;
                        if($exec){
                            return (new PdfGenerateController )->convert_html($dados);
                        }else{
                            return $ger_cont;
                        }
                    }else{
                        return (new PdfGenerateController )->convert_html($dados);
                    }
                }
                $ret = $contrato;
            }
        }
        return $ret;
    }
    /**
     * verifica se uma aluno está matriculado
     */
    public function verificaAlunoMatricula($id_aluno,$local=false,$responsavel=false,$token_matricula=false){

        $ret['liberado'] = false;
        $mens=false;
        if($id_aluno){
            $arr_campos = Qlib::qoption('campos_cad_cliente');
            if($arr_campos){
                $arr_campos = json_decode($arr_campos,true);
            }else{
                $arr_campos = array(
                    array('campo'=>'Nome','label'=>'Nome','required'=>'required'),
                    array('campo'=>'Endereco','label'=>'Endereço','required'=>'required'),
                    array('campo'=>'Numero','label'=>'Numero','required'=>'required'),
                    array('campo'=>'Bairro','label'=>'Bairro','required'=>'required'),
                    array('campo'=>'Cidade','label'=>'Cidade','required'=>'required'),
                    array('campo'=>'Celular','label'=>'Celular','required'=>'required'),
                    array('campo'=>'Cpf','label'=>'CPF','required'=>'required'),
                    array('campo'=>'Ident','label'=>'Identidade','required'=>'required'),
                    array('campo'=>'DtNasc2','label'=>'Data de Nascimento','required'=>'required'),
                );
            }
            //echo json_encode($arr_campos);
            $dadosCliente = Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab15']." WHERE id='".$id_aluno."' AND ".Qlib::compleDelete());
            if($dadosCliente){
                if(is_array($arr_campos)){
                    foreach($arr_campos As $kei=>$vl){
                        $ret[$vl['campo']] =  $dadosCliente[0][$vl['campo']];
                        if($vl['campo']=='DtNasc2'){
                            if($dadosCliente[0][$vl['campo']] == '0000-00-00'){
                                $mens.= 'O Campo <b>Data de nascimento</b> é obrigatório no cadastro do aluno<br>';
                            }else{
                                $idade = Qlib::lib_calcIdade($dadosCliente[0][$vl['campo']]);
                                if($idade >=100 || $idade <= 5){
                                    $mens.= 'A idade do cliente é: '.$idade.', portanto é idade inválida, edite a <b>Data de nascimento</b><br>';
                                }elseif($idade < 18 && $responsavel ==0 && $local == 'contrato'){
                                //}elseif($idade < 18 && $responsavel ==0){

                                    $mens.= 'É necessário informar o <b>Responsável</b> deste Aluno, por que é menor de 18 anos.<br>';
                                    if($token_matricula){
                                        $fiador = $this->tem_fiador($token_matricula);
                                        if($fiador){
                                            $ret['liberado'] = true;
                                        }
                                    }
                                }
                            }
                        }else{
                            if(empty($dadosCliente[0][$vl['campo']])){
                                $mens.='O Campo <b>'.$vl['label'].'</b> é obrigatório no cadastro do aluno<br>';
                            }
                        }
                    }
                    if($mens){
                        $url = Qlib::$RAIZ.'/cursos/iframe?sec=Y2FkX2NsaWVudGVz&acao=alt&id='.base64_encode($id_aluno).'&list=false&listSelect=true&local=matricula';
                        if(Qlib::isAdmin(7)){
                            $mens .= '<button class="btn btn-default" type="button" onclick="abrirjanelaPadrao(\''.$url.'\')" title="Editar cliente"><i class="fa fa-pencil"></i> Editar cliente</button>';
                            $ret['mens'] = Qlib::formatMensagem0('<b>Erro ao executar ação</b> '.$mens,'danger',100000);
                        }else{
                            $mens .= '<a class="btn btn-link" href="/area-do-aluno/perfil"  title="Atualize os dados do aluno para fazer o curso"><i class="fa fa-pencil"></i> Atualize os dados do aluno</a>
                            entre em contato com o nosso <a href="/atendimento">suporte </a>
                            ';
                            $ret['mens'] = Qlib::formatMensagem0('<b>Pendências no cadastro</b><br> '.$mens,'danger',100000);
                        }
                    }else{
                        $ret['verificaFinanceiroAluno'] = $this->verificaFinanceiroAluno($id_aluno);
                        if($ret['verificaFinanceiroAluno']['enc']){
                            $ret['mens'] = $ret['verificaFinanceiroAluno']['mens'];
                        }else{
                            $ret['mens'] = Qlib::formatMensagem0('Cliente <b>liberado para matrícula</b> '.$mens,'success',10000);
                            $ret['liberado'] = true;
                        }
                    }
                }
            }
        }
         return $ret;

    }
    /**
     * Verifica o financeiro do aluno
     */
    public function verificaFinanceiroAluno($id_cliente=false,$opc=1){

        $ret['enc'] = false;

        $ret['mens'] = false;

        if($id_cliente){

            if($opc==1){//verifica se tem faturas em atraso

                $ret['mens'] = Qlib::formatMensagem('Nenhuma fatura em atraso','success');

                $antesOntem = Qlib::CalcularDiasAnteriores(date('d/m/Y'),3);

                $sql = "WHERE `id_cliente`='".$id_cliente."' AND `pago`='n' AND `vencimento`<='".Qlib::dtBanco($antesOntem)."'";

                $ret['enc'] = Qlib::totalReg($GLOBALS['lcf_entradas'],$sql);

                if($ret['enc']){

                    $nome = Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$id_cliente,'nome');

                    if(!$nome){

                        $nome = Qlib::buscaValorDb0($GLOBALS['tab16'],'id',$id_cliente,'nome');

                    }else{

                        $sobrenome = Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$id_cliente,'sobrenome');

                        $nome = $nome.' '.$sobrenome;

                    }

                    $ret['mens'] = Qlib::formatMensagem('Cliente '.$nome.' tem '.$ret['enc'].' fatura(s) em atraso','danger',90000);

                }

            }

            if($opc==2){//verifica se existe mensalidades geradas

                $ret['mens'] = Qlib::formatMensagem('Nenhuma fatura encontrada','danger',90000);

                $sql = "WHERE `id_cliente`='".$id_cliente."' AND `categoria`='".$GLOBALS['categoriaMensalidade']."' ";

                $ret['enc'] = Qlib::totalReg($GLOBALS['lcf_entradas'],$sql);

                if($ret['enc']){

                    $nome = Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$id_cliente,'nome');

                    if(!$nome){

                        $nome = Qlib::buscaValorDb0($GLOBALS['tab16'],'id',$id_cliente,'nome');

                    }else{

                        $sobrenome = Qlib::buscaValorDb0($GLOBALS['tab15'],'id',$id_cliente,'sobrenome');

                        $nome = $nome.' '.$sobrenome;

                    }

                    $ret['mens'] = Qlib::formatMensagem('Cliente '.$nome.' tem '.$ret['enc'].' fatura(s)','success',90000);

                }

            }

        }

        return $ret;

    }
     /**
     * renderiza um contrato do aeroclube atualizando o shortcodes com as informções necessárias
     * @param string $tm token da matricula
     * @param arra $dm dados da matricula
     */
    public function contrato_matricula($tm=false,$dm=false,$texto_contrato=''){
        if(!$dm && isset($tm) && !empty($tm)){
            $dm = $this->dm($tm);
        }
        $ret = str_replace('{curso}','{nome_curso}',$texto_contrato);
        $ret = str_replace('{aluno}','{nome_completo}',$ret);
        // dd($dm);
        $clausula_tipo_pagamento = false;
        $tipo_contrato_combustivel = false;
        $data_contrato_aceito = '';
        if(is_array($dm) && $texto_contrato){
            $contrato_firmado_curso = false; //Contrato firmado sob Piloto privado
            foreach($dm as $k=>$v){
                if(!is_array($v)){
                    $ret = str_replace('{'.$k.'}',$v,$ret);
                }
            }
            $endereco = $dm['Endereco'].', '. $dm['Numero'].' '.$dm['Compl'].' - '. $dm['Bairro'].' - '.$dm['Cidade'].' /'.$dm['Uf'];
            $ret = str_replace('{endereco}',$endereco,$ret);
            $st = new SiteController();
            $assinatura = false;
            if($dm['contrato']){
                if(!empty($dm['contrato'])){
                    $arr_contratoAss = Qlib::lib_json_array($dm['contrato']);
                // dd($arr_contratoAss);
                if(isset($arr_contratoAss['declaracao']) && isset($arr_contratoAss['declaracao']) && isset($arr_contratoAss['aceito_termo_concordancia']) && $arr_contratoAss['aceito_termo_concordancia'] =='on'){
                        $assinatura = '<span class="text-success"><b>Termo assinado eletronicamente pelo aluno em '.Qlib::dataExibe(@$arr_contratoAss['aceito_termo_concordancia']).'</b> </span><span style="text-align:right" class="text-success"><b>Ip:</b> <i>'.@$arr_contratoAss['ip'].'</i></span>';
                    }
                }
                $tabela_fechada = false;
                $periodo = isset($config['periodo']) ? $config['periodo'] : '';
                if(isset($dm['orc'])){

                    $arr_orc = Qlib::lib_json_array($dm['orc']);

                    if(isset($arr_orc['sele_valores'])){

                        $tabela_fechada = $arr_orc['sele_valores'];

                    }

                }
                if(!empty($dm['contrato'])){
                    $arr_contratoAss = Qlib::lib_json_array($dm['contrato']);
                    if(isset($dm['orc']) && isset($config['opc']) && $config['opc']=='contratoMatriculaCombustivel'){
                        $arr_orc = Qlib::lib_json_array($dm['orc']);
                        if(isset($arr_orc['sele_pag_combustivel'])){
                            if($arr_orc['sele_pag_combustivel']=='antecipado'){
                                $tipo_contrato_combustivel = 'Pagamento antecipado';
                                $clausula_tipo_pagamento = '<p><strong>Pagamento Antecipado</strong> - Será gerado um crédito em favor do aluno/Contratante, o qual será abatido/deduzido de acordo com cada abastecimento realizado, tomando-se como base o valor de mercado do combustível no momento do abastecimento;</p>';
                            }elseif($arr_orc['sele_pag_combustivel']=='por_voo'){
                                $clausula_tipo_pagamento = '<p><strong>Pagamento a cada voo realizado</strong> - O aluno/Contratante não fará pagamento antecipado de combustível, devendo efetuar o pagamento a cada voo do seu treinamento, tomando-se como base o valor de mercado do combustível no momento do abastecimento.</p>';
                                $tipo_contrato_combustivel = 'Pagamento por voo';
                            }
                        }

                    }
                    if(isset($arr_contratoAss['aceito_contrato']) && $arr_contratoAss['aceito_contrato']=='on'){

                        $assinatura = '<span style="font-size:13px;" class="text-danger"><b>Contrato assinado eletronicamente pelo contratante em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</b> </span><span style="text-align:right" class="text-danger"><b>Ip:</b> <i>'.@$arr_contratoAss['ip'].'</i></span>';
                        if(isset($config['opc']) && $config['opc']=='contratoMatriculaCombustivel'){
                            if($tipo_contrato_combustivel){
                                $contrato_firmado_curso = '<span class="text-danger pull-right">contrato firmado sob <b>'.$tipo_contrato_combustivel.'</b></span>';
                            }

                        }else{
                            if($tabela_fechada)
                                $contrato_firmado_curso = '<span class="text-danger pull-right">contrato firmado sob <b>'.$tabela_fechada.'</b></span>';
                        }
                        $d = explode(' ',$arr_contratoAss['data_aceito_contrato']);

                        $data_contrato_aceito = Qlib::dataExibe(@$d[0]);

                    }
                }
            }
            $id_contatada = Qlib::qoption('id_contatada') ? Qlib::qoption('id_contatada') : 14;
            $id_testemunha1 = Qlib::qoption('id_testemunha1') ? Qlib::qoption('id_testemunha1') : 137;
            $id_testemunha2 = Qlib::qoption('id_testemunha2') ? Qlib::qoption('id_testemunha2') : 95;
            $dcont = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_contatada."'");
            $dtes1 = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_testemunha1."' AND ".Qlib::compleDelete());
            $dtes2 = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_testemunha2."' AND ".Qlib::compleDelete());
            $nome_contratada = '';
            $nome_testemunha1 = '';
            $nome_testemunha2 = '';
            $cpf_testemunha1 = '';
            $cpf_testemunha2 = '';
            $cpf_contratada = '';
            $assinatura_contratada = '';
            $assinatura_testemunha1 = '';
            $assinatura_testemunha2 = '';
            if($dcont){
                // $nome_contratada = $dcont[0]['nome'].' '.$dcont[0]['sobrenome'];
                $nome_contratada = 'AEROCLUBE DE JUIZ DE FORA';
                $cpf_contratada = $dcont[0]['cpf'];
                $assinatura_contratada = '<span style="font-size:13px" class="text-danger">Contrato assinado digitalmete por <b>{nome_contratada}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                $assinatura_contratada = str_replace('{nome_contratada}',$nome_contratada,$assinatura_contratada);
            }
            if($dtes1){
                $nome_testemunha1 = $dtes1[0]['nome'].' '.$dtes1[0]['sobrenome'];
                $cpf_testemunha1 = $dtes1[0]['cpf'];
                $assinatura_testemunha1 = '<span style="font-size:13px" class="text-danger" style="">Contrato assinado digitalmete por <b>{nome_testemunha1}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                $assinatura_testemunha1 = str_replace('{nome_testemunha1}',$nome_testemunha1,$assinatura_testemunha1);
            }
            if($dtes2){
                $nome_testemunha2 = $dtes2[0]['nome'].' '.$dtes2[0]['sobrenome'];
                $cpf_testemunha2 = $dtes2[0]['cpf'];
                $assinatura_testemunha2 = '<span style="font-size:11px" class="text-danger" style="">Contrato assinado digitalmete por <b>{nome_testemunha2}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                $assinatura_testemunha2 = str_replace('{nome_testemunha2}',$nome_testemunha2,$assinatura_testemunha2);
            }
            $sc = $this->simuladorCombustivel($dm['token'],$dm);
            $valor_combustivel = isset($sc['valor']) ? $sc['valor']: null;
            if($valor_combustivel){
                $valor_combustivel = Qlib::valor_moeda($valor_combustivel);
            }
            $data_contrato 			= date('d/m/Y (H:m:i)');
            $ret = str_replace('{data_contrato}',$data_contrato,$ret);
            $ret = str_replace('{valor_combustivel}',$valor_combustivel,$ret);
            $ret = str_replace('{assinatura}',$assinatura,$ret);
            $ret = str_replace('{assinatura_contratada}',$assinatura_contratada,$ret);
            $ret = str_replace('{nome_testemunha1}',$nome_testemunha1,$ret);
            $ret = str_replace('{cpf_contratada}',$cpf_contratada,$ret);
            $ret = str_replace('{cpf_testemunha1}',$cpf_testemunha1,$ret);
            $ret = str_replace('{assinatura_testemunha1}',$assinatura_testemunha1,$ret);
            $ret = str_replace('{assinatura_testemunha2}',$assinatura_testemunha2,$ret);
            $ret = str_replace('{cpf_testemunha2}',$cpf_testemunha2,$ret);
            $ret = str_replace('{nome_testemunha2}',$nome_testemunha2,$ret);
            $ret = str_replace('{clausula_tipo_pagamento}',$clausula_tipo_pagamento,$ret);
            $ret = str_replace('{contrato_firmado_curso}',$contrato_firmado_curso,$ret);
            $ret = str_replace('{exemplo_ilustrativo_comustivel}',$st->short_code('exemplo_ilustrativo_comustivel'),$ret);

        }
        return $ret;
    }
    /**
     * renderiza um contrato do aeroclube
     */
    public function contratoAero($config=false,$dm=false,$short_code='contrato_matricula'){

        $ret['exec'] = false;
        $ret['contrato'] = false;
        $ret['nome_arquivo'] = '';
        $tipo_retorno = isset($config['tipo_retorno']) ? $config['tipo_retorno'] : 1; //retorna false quanto não acha conteudo se estiver na opçao 2 e uma mensgem em html se estiver na opçao 1
        if(!$dm && isset($config['token']) && !empty($config['token'])){
            $dm = $this->dm($config['token']);
        }
        if($config && isset($dm['id_cliente'])){
            // $dadosMatricula 	= $dm;
            $dadosCliente 		= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab15']." WHERE id='".$dm['id_cliente']."'");
            $dadosCurso 		= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab10']." WHERE id='".$dm['id_curso']."'");
            $dadosTurma 		= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab11']." WHERE id='".$dm['id_turma']."'");
            $st = new SiteController();
            if($dadosTurma==false){
                $dadosTurma = [];
            }
            $tr_responsavel = false;
            $aluno 						= false;
            $cpf_aluno 				= false;
            $cpf_contrato 			= false;
            $telefone 					= false;
            $endereco 				= false;
            $numero_matricula 	= false;
            $curso 						= false;
            $inicio 						= false;
            $fim 							= false;
            $horario 					= false;
            $valor_total 				= false;
            $valor_extensso		= false;
            $carga_horaria			= false;
            $data_contrato			= false;
            $data_nascimento			= false;
            $dataMatricula			= false;
            $cep				= false;
            $assinatura				= false;
            $dias						= false;
            $nome 						= false;
            $nome_empresa		= 'Aeroclubejf';//@$_SESSION[SUF_SYS]['dadosConta'.SUF_SYS]['nome'];
            $arr_status_mat = Qlib::sql_array("SELECT * FROM status_matricula WHERE `ativo`='s' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'nome','id');
            $ret['nome_arquivo'] = ucwords(str_replace('_',' ',$short_code)). ' '.$dm['Nome'].' '.$dm['nome_curso'].' '.$dm['id'];
            $tema0 = '

            <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">

                                                            <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">

                                                                        <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar

                                                            </a>

                                                    </div>

            ';


            //if($dadosMatricula && $dadosCliente && $dadosTurma && $dadosCurso){
            if($dm && $dadosCliente && $dadosCurso){
                $config_curso = [];
                if($short_code=='contrato_combustivel'){
                    if(isset($dadosCurso[0]['config'])){
                        $config_curso = Qlib::lib_json_array($dadosCurso[0]['config']);
                    }
                    if(isset($config_curso['adc']['recheck']) && $config_curso['adc']['recheck']=='s'){
                        //não exibir se o curso não estiver marcado como recheck
                        $ret['contrato'] = false;
                        return $ret;
                    }
                }
                $ret['dadosMatricula']=$dm;

                $ret['dadosCurso']=$dadosCurso;

                $ret['dadosTurma']=$dadosTurma;

                $tabela_fechada = false;
                $periodo = isset($config['periodo']) ? $config['periodo'] : '';
                if(isset($dm['orc'])){

                    $arr_orc = Qlib::lib_json_array($dm['orc']);

                    if(isset($arr_orc['sele_valores'])){

                        $tabela_fechada = $arr_orc['sele_valores'];

                    }

                }

                if(Qlib::isAdmin(3)){
                    $ret['verificaAlunoMatricula'] = $this->verificaAlunoMatricula($dadosCliente[0]['id'],'contrato',$dm['id_responsavel']);
                    if(!$ret['verificaAlunoMatricula']['liberado']){

                        $ret['mens'] = $ret['verificaAlunoMatricula']['mens'];
                        $ret['contrato'] = $ret['verificaAlunoMatricula']['mens'];

                        if(Qlib::isAdmin(3)){
                        }else{
                            return $ret;
                        }

                    }

                }

                    //if($dm['status']==1){

                        $ret['mens'] = Qlib::formatMensagemInfo('Não é possível emitir comtrato para clientes com status de <b>'.$arr_status_mat[$dm['status']].'</b>, efetue primeiro a matrícula deste cliente ','danger');

                    //}else{

                                $arr_dias = array(1=>array('dia'=>'Segunda','cor'=>'success'),2=>array('dia'=>'Terça','cor'=>'primary'),3=>array('dia'=>'Quarta','cor'=>'info'),

                                                    4=>array('dia'=>'Quinta','cor'=>'default'),5=>array('dia'=>'sexta','cor'=>'warning'),6=>array('dia'=>'Sábado','cor'=>'danger'));

                                // $tema0 = '<form id="form-status"  method="post">';

                                // $tema0 		.= queta_formfield4("hidden",'1',"tab-", $GLOBALS['tab12'],"","");

                                //     $tema0 		.= queta_formfield4("hidden",'1',"token-", $dm['token'],"","");

                                //     $tema0			.= queta_formfield4("hidden",'1',"evento-", 'Contrato aceito',"","");

                                //     $tema0 		.= queta_formfield4("hidden",'1',"status-", 3,"","");

                                // $tema0 .= '</form>';

                                if(Qlib::isAdmin()){

                                    $tema = '

                                            <div class="row mb-5 pb-4">

                                                    <div class="col-sm-12 mens"></div>

                                                    '.$tema0.'

                                                    <div class="col-sm-12">

                                                        {contrato}

                                                    </div>

                                                    <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">

                                                            <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">

                                                                        <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar

                                                            </a>

                                                            <button type="button" title="imprimir" style="margin-right:5px" class="btn btn-success btn-md" que-btn="imprimir_contrato">

                                                                        <i class="fa fa-print" aria-hidden="true"></i> Imprimir

                                                            </button>

                                                    </div>

                                            </div>

                                            ';

                                }else{

                                    $tema = '

                                            <div class="row">

                                                    <div class="col-sm-12 mens"></div>



                                                    <div class="col-sm-12">

                                                        {contrato}

                                                    </div>

                                                    <!--

                                                    <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">

                                                            <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">

                                                                        <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar

                                                            </a>

                                                            <button type="button" title="imprimir" style="margin-right:5px" class="btn btn-success btn-md" que-btn="imprimir_contrato">

                                                                        <i class="fa fa-print" aria-hidden="true"></i> Imprimir

                                                            </button>

                                                    </div>-->

                                            </div>

                                    ';

                                }

                                $dadosTurma[0]['id'] = isset($dadosTurma[0]['id']) ? $dadosTurma[0]['id'] : false;

                                $dadosTurma[0]['nome'] = isset($dadosTurma[0]['nome']) ? $dadosTurma[0]['nome'] : false;

                                $dadosTurma[0]['inicio'] = isset($dadosTurma[0]['inicio']) ? $dadosTurma[0]['inicio'] : false;

                                $dadosTurma[0]['hora_inicio'] = isset($dadosTurma[0]['hora_inicio']) ? $dadosTurma[0]['hora_inicio'] : false;

                                $dadosTurma[0]['duracao'] = isset($dadosTurma[0]['duracao']) ? $dadosTurma[0]['duracao'] : false;

                                $dadosTurma[0]['unidade_duracao'] = isset($dadosTurma[0]['unidade_duracao']) ? $dadosTurma[0]['unidade_duracao'] : false;

                                $dadosTurma[0]['fim'] = isset($dadosTurma[0]['fim']) ? $dadosTurma[0]['fim'] : false;

                                $dadosTurma[0]['hora_fim'] = isset($dadosTurma[0]['hora_fim']) ? $dadosTurma[0]['hora_fim'] : false;



                                $dadosCliente[0]['profissao'] = isset($dadosCliente[0]['profissao']) ? $dadosCliente[0]['profissao'] :false;

                                $dadosCliente[0]['Ident'] = isset($dadosCliente[0]['Ident']) ? $dadosCliente[0]['Ident'] :false;

                                $dadosCliente[0]['DtNasc2'] = isset($dadosCliente[0]['DtNasc2']) ? $dadosCliente[0]['DtNasc2'] :false;

                                $dadosCliente[0]['Email'] = isset($dadosCliente[0]['Email']) ? $dadosCliente[0]['Email'] :false;

                                $dadosCliente[0]['Cep'] = isset($dadosCliente[0]['Cep']) ? $dadosCliente[0]['Cep'] :false;

                                $dadosCliente[0]['Cpf'] = isset($dadosCliente[0]['Cpf']) ? $dadosCliente[0]['Cpf'] :false;

                                $dadosCliente[0]['Nome'] = isset($dadosCliente[0]['Nome']) ? $dadosCliente[0]['Nome'] :false;

                                $dadosCliente[0]['sobrenome'] = isset($dadosCliente[0]['sobrenome']) ? $dadosCliente[0]['sobrenome'] :false;
                                //CONTRATO DE PRESTAÇÃO DE SERVIÇOS
                                //CONTRATO FINANCEIRO DE HORAS
                                //if(isset($_GET['teste'])){
                                    //echo $short_code;exit;
                                    if($short_code=='contrato_matricula'){
                                        $short_code = 'contrato1';
                                    }
                                    if($short_code=='contrato_financiamento_horas'){
                                        $short_code = 'contrato2';
                                    }

                                    if(Qlib::isAdmin()){
                                        $edit = 'orc';
                                    }else{
                                        $edit = false;
                                    }
                                    $dadoContratoM = $st->short_code($short_code,['comple'=>" AND id_curso='".$dm['id_curso']."' AND ".Qlib::compleDelete()]);
                                    if($short_code=='contrato_combustivel'){
                                        $short_code = 'contrato_combustivel';
                                        $dadoContratoM = $st->short_code($short_code);
                                    }
                                    // dd($short_code,$dadoContratoM);
                                    // if(Qlib::isAdmin()){
                                    // 	// lib_print($dadosCliente);
                                    // 	// lib_print($dadosTurma);
                                    // 	// lib_print($dadosMatricula);
                                    // 	// dd(var_dump($dadoContratoM));
                                    // }
                                    if(!$dadoContratoM){
                                        $ret['contrato'] = false;
                                        $id_curso = isset($dadosCurso[0]['id']) ? $dadosCurso[0]['id'] : false;
                                        $curso = isset($dadosCurso[0]['nome']) ? $dadosCurso[0]['nome'] : false;
                                        if($id_curso && $curso){
                                            $link = Qlib::$RAIZ.'/cursos/?sec=dG9kb3MtY3Vyc29z&list=false&acao=alt&id='.base64_encode($id_curso).'&etp=ZXRwNw==&redirect_base='. base64_encode(Qlib::UrlAtual());
                                            $btn_cad = '<a href="'.$link.'"  class="btn btn-default">Cadastrar contrato</a>';
                                        }else{
                                            $btn_cad = '';
                                        }
                                        $ret['mens'] = Qlib::formatMensagemInfo('O contrato para o curso <b>'.$curso.'</b> não foi encontrado ou modelo não cadastrado '.$btn_cad,'warning');
                                        if($tipo_retorno==2){
                                            $ret['contrato'] = '';
                                        }else{
                                            $ret['contrato'] = $ret['mens'];
                                        }
                                        $ret['exec'] = false;
                                        return $ret;
                                        //$dadoContratoM = short_code($short_code)?short_code($short_code):Qlib::qoption($short_code);
                                    }
                                    // if(isset($_GET['teste'])){
                                    // 	echo $dm['id_curso'];
                                    // 	echo $short_code;
                                    // 	dd($dadoContratoM);
                                    // }


                                $contrato		= str_replace('{contrato}',@$dadoContratoM ,$tema);

                                $responsavel 			= ($dm['id_responsavel'] > 0 ) ? $dm['id_responsavel'] : false;

                                // $aluno	= ucwords($dadosCliente[0]['Nome']) .' '.ucwords($dadosCliente[0]['sobrenome']);
                                $aluno	= $dm['nome_completo'];

                                $cpf_aluno 				= $dadosCliente[0]['Cpf'];

                                $email 				= $dadosCliente[0]['Email'];

                                $cep 					= $dadosCliente[0]['Cep'];

                                $identidade 				= $dadosCliente[0]['Ident'];

                                $data_nascimento 			= Qlib::dataExibe($dadosCliente[0]['DtNasc2']);

                                $telefone 					= $dadosCliente[0]['Celular'].' '. $dadosCliente[0]['Tel'];

                                $nacionalidade 					= $dadosCliente[0]['nacionalidade'];

                                $profissao 					= $dadosCliente[0]['profissao'];

                                $endereco 				= $dadosCliente[0]['Endereco'].', '. $dadosCliente[0]['Numero'].' '.$dadosCliente[0]['Compl'].' - '. $dadosCliente[0]['Bairro'].' - '.$dadosCliente[0]['Cidade'].' /'.$dadosCliente[0]['Uf'];
                                if(is_null($dadosCliente[0]['estado_civil'])){
                                    $estado_civil			= '';
                                }else{
                                    $estado_civil			= ucfirst($dadosCliente[0]['estado_civil']);
                                }

                                $numero_matricula 	= Qlib::zerofill($dm['id'],10);

                                $curso 				= @$dadosTurma[0]['id'] .' '. @$dadosTurma[0]['nome']  ;

                                //$curso						= strtoupper($curso);

                                $inicio 			= Qlib::dataExibe($dadosTurma[0]['inicio']);

                                $fim 				= Qlib::dataExibe($dadosTurma[0]['fim']);

                                $horario 			= $dadosTurma[0]['hora_inicio'] .' - '.$dadosTurma[0]['hora_fim'] ;

                                $arr_valor 			= json_decode($dm['reg_pagamento'],true);

                                $carga_horaria		= $dadosTurma[0]['duracao'] .' '. $dadosTurma[0]['unidade_duracao'];

                                $data_contrato 		= date('d/m/Y (H:m:i)');

                                $nome 						= $aluno;

                                //$endereco			=$dadosClie

                                if(is_array($arr_dias)){

                                    foreach($arr_dias As $key=>$dia){

                                        if(isset($dadosTurma[0]['dia'.$key]) && $dadosTurma[0]['dia'.$key] == 's'){

                                            $dias .= $dia['dia'].'/';

                                        }

                                    }

                                    $dias = substr($dias,0,-1);

                                }



                                if(is_array($arr_valor)){

                                    $arr_valor['valor_total'] = isset($arr_valor['valor_total'])?$arr_valor['valor_total']:0;

                                    $pos = strpos($arr_valor['valor_total'],'.');

                                    if($pos != false){

                                        $arr_valor['valor_total'] = Qlib::precoDbdase($arr_valor['valor_total']);

                                    }

                                    $arr_valor['valor_total'] = (double)$arr_valor['valor_total'];

                                    $valor_total 				= number_format($arr_valor['valor_total'],2,',','.');

                                    $valor_extensso		= Qlib::lib_valorPorExtenso($arr_valor['valor_total']);

                                }

                                if($responsavel){

                                    $dadosResponsavel = Qlib::buscaValoresDb("SELECT *  FROM ".$GLOBALS['tab16']." WHERE id='".$responsavel."'");

                                    if($dadosResponsavel){

                                        $ret['exec'] = true;

                                        $tr_responsavel =

                                            '<table cellspacing="0" cellpadding="0">

                                                <tbody>

                                                    <tr>

                                                        <td>Responsável:</td>

                                                        <td>&nbsp;</td>

                                                        <td colspan="3">'.strtoupper($dadosResponsavel[0]['Nome']).'&nbsp;</td>

                                                        <td>CPF:</td>

                                                        <td>'.$dadosResponsavel[0]['Cpf'].'</td>

                                                    </tr>

                                                </tbody>

                                            </table>';

                                        $nome 				= strtoupper($dadosResponsavel[0]['Nome']);

                                        $cpf_contrato 	= $dadosResponsavel[0]['Cpf'];

                                        $telefone 			= $dadosResponsavel[0]['Celular'].' '. $dadosResponsavel[0]['Tel'];

                                        $endereco 		= $dadosResponsavel[0]['Endereco'].', '. $dadosResponsavel[0]['Numero'].' '.$dadosResponsavel[0]['Compl'].' - '. $dadosResponsavel[0]['Bairro'].' - '.$dadosResponsavel[0]['Cidade'].'/'.$dadosResponsavel[0]['Uf'];

                                    }else{

                                        $ret['mens'] = Qlib::formatMensagem0('Dados do responsável não encontrados','danger',10000);

                                    }

                                }else{

                                    //echo 'não tem responsavel';

                                }

                                $assinatura = '<table>

                                                <tbody><tr><td style="width:500px">&nbsp;</td><td>&nbsp;</td></tr>

                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>

                                                <tr>

                                                    <td>________________________________________</td>

                                                    <td>________________________________________</td>

                                                </tr>

                                                <tr>

                                                    <td>CONTRATANTE</td>

                                                    <td>'.Qlib::qoption('assinatura_contrato').'</td>

                                                </tr>

                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>

                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>

                                                <tr>

                                                    <td>TESTEMUNHAS</td>

                                                    <td>&nbsp;</td>

                                                </tr>



                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>



                                                <tr>

                                                    <td>________________________________________</td>

                                                    <td>________________________________________</td>

                                                </tr>

                                                <tr>

                                                    <td>NOME: </td>

                                                    <td>NOME: </td>

                                                </tr>

                                                <tr>

                                                    <td>CPF: </td>

                                                    <td>CPF: </td>

                                                </tr>

                                            </tbody></table>';

                                $dia_contrato = false;

                                $mes_contrato = false;

                                $ano_contrato = false;

                                $data_contrato_aceito = false;

                                if($dm['contrato']){
                                    $clausula_tipo_pagamento = false;
                                    $tipo_contrato_combustivel = false;
                                    if(!empty($dm['contrato'])){
                                        $arr_contratoAss = Qlib::lib_json_array($dm['contrato']);
                                        if(isset($dm['orc']) && isset($config['opc']) && $config['opc']=='contratoMatriculaCombustivel'){
                                            $arr_orc = Qlib::lib_json_array($dm['orc']);
                                            if(isset($arr_orc['sele_pag_combustivel'])){
                                                if($arr_orc['sele_pag_combustivel']=='antecipado'){
                                                    $tipo_contrato_combustivel = 'Pagamento antecipado';
                                                    $clausula_tipo_pagamento = '<p><strong>Pagamento Antecipado</strong> - Será gerado um crédito em favor do aluno/Contratante, o qual será abatido/deduzido de acordo com cada abastecimento realizado, tomando-se como base o valor de mercado do combustível no momento do abastecimento;</p>';
                                                }elseif($arr_orc['sele_pag_combustivel']=='por_voo'){
                                                    $clausula_tipo_pagamento = '<p><strong>Pagamento a cada voo realizado</strong> - O aluno/Contratante não fará pagamento antecipado de combustível, devendo efetuar o pagamento a cada voo do seu treinamento, tomando-se como base o valor de mercado do combustível no momento do abastecimento.</p>';
                                                    $tipo_contrato_combustivel = 'Pagamento por voo';
                                                }
                                            }

                                        }
                                        if(isset($arr_contratoAss['aceito_contrato']) && $arr_contratoAss['aceito_contrato']=='on'){

                                            $assinatura = '<span class="text-danger"><b>Contrato assinado eletronicamente pelo contratante em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</b> </span><span style="text-align:right" class="text-danger"><b>Ip:</b> <i>'.@$arr_contratoAss['ip'].'</i></span>';
                                            if(isset($config['opc']) && $config['opc']=='contratoMatriculaCombustivel'){
                                                if($tipo_contrato_combustivel){
                                                    $assinatura .= '<span class="text-danger pull-right">contrato firmado sob <b>'.$tipo_contrato_combustivel.'</b></span>';
                                                }

                                            }else{
                                                if($tabela_fechada)
                                                    $assinatura .= '<span class="text-danger pull-right">contrato firmado sob <b>'.$tabela_fechada.'</b></span>';
                                            }
                                            $d = explode(' ',$arr_contratoAss['data_aceito_contrato']);

                                            $data_contrato_aceito = Qlib::dataExibe(@$d[0]);

                                        }

                                    }

                                }
                                $ret['exec'] = true;

                                $numero_contrato = $this->numero_contrato(@$dm['id']);

                                $ret['contrato'] = str_replace('{responsavel}',$responsavel,$contrato);

                                $ret['contrato'] = str_replace('{tr_responsavel}',$tr_responsavel,$ret['contrato']);

                                $ret['contrato'] = str_replace('{aluno}',$aluno,$ret['contrato']);

                                $ret['contrato'] = str_replace('{cpf_aluno}',$cpf_aluno,$ret['contrato']);

                                $ret['contrato'] = str_replace('{identidade}',$identidade,$ret['contrato']);

                                $ret['contrato'] = str_replace('{cpf_contrato}',$cpf_contrato,$ret['contrato']);

                                $ret['contrato'] = str_replace('{telefone}',$telefone,$ret['contrato']);

                                $ret['contrato'] = str_replace('{endereco}',$endereco.'&nbsp;',$ret['contrato']);

                                $ret['contrato'] = str_replace('{numero_matricula}',$numero_matricula,$ret['contrato']);

                                $ret['contrato'] = str_replace('{curso}',$curso,$ret['contrato']);
                                $ret['contrato'] = str_replace('{numero_contrato}',$numero_contrato,$ret['contrato']);

                                $ret['contrato'] = str_replace('{inicio}',$inicio,$ret['contrato']);

                                $ret['contrato'] = str_replace('{fim}',$fim,$ret['contrato']);

                                $ret['contrato'] = str_replace('{email}',$email,$ret['contrato']);

                                $ret['contrato'] = str_replace('{horario}',$horario,$ret['contrato']);

                                $ret['contrato'] = str_replace('{valor_total}',$valor_total,$ret['contrato']);

                                $ret['contrato'] = str_replace('{valor_extensso}',$valor_extensso,$ret['contrato']);
                                $ret['contrato'] = str_replace('{periodo}',$periodo,$ret['contrato']);

                                $ret['contrato'] = str_replace('{data_nascimento}',$data_nascimento,$ret['contrato']);
                                if(isset($clausula_tipo_pagamento) && !empty($clausula_tipo_pagamento)){
                                    $ret['contrato'] = str_replace('{clausula_tipo_pagamento}',$clausula_tipo_pagamento,$ret['contrato']);
                                }else{
                                    $ret['contrato'] = str_replace('{clausula_tipo_pagamento}','',$ret['contrato']);
                                }

                                $ret['contrato'] = str_replace('{estado_civil}',$estado_civil,$ret['contrato']);

                                $ret['contrato'] = str_replace('{carga_horaria}',$carga_horaria,$ret['contrato']);
                                if(isset($nacionalidade) && !empty($nacionalidade)){
                                    $ret['contrato'] = str_replace('{nacionalidade}',@$nacionalidade,$ret['contrato']);
                                }else{
                                    $ret['contrato'] = str_replace('{nacionalidade}','',$ret['contrato']);
                                }

                                $ret['contrato'] = str_replace('{profissao}',$profissao,$ret['contrato']);

                                $ret['contrato'] = str_replace('{dias}',$dias,$ret['contrato']);

                                $ret['contrato'] = str_replace('{assinatura}',$assinatura,$ret['contrato']);

                                $ret['contrato'] = str_replace('{tabela_fechada}',$tabela_fechada,$ret['contrato']);

                                $ret['contrato'] = str_replace('{data_contrato}',$data_contrato,$ret['contrato']);

                                $ret['contrato'] = str_replace('{data_contrato_aceito}',$data_contrato_aceito,$ret['contrato']);
                                $ret['contrato'] = str_replace('{exemplo_ilustrativo_comustivel}',$st->short_code('exemplo_ilustrativo_comustivel'),$ret['contrato']);

                                $ret['contrato'] = str_replace('{nome}',$nome,$ret['contrato']);

                                $ret['contrato'] = str_replace('{cep}',$cep,$ret['contrato']);
                                if(isset($dm['fiador']) && !empty($dm['fiador'])){
                                    $arr_fiador = Qlib::lib_json_array($dm['fiador']);
                                    if(is_array($arr_fiador)){
                                        $ifi = 1;
                                        $arr_sortcode = [
                                            'nome_fiador' => 'Nome',
                                            'nacionalidade_fiador' => 'nascionalidade',
                                            'profissao_fiador' => 'profissao',
                                            'cpf_fiador' => 'Cpf',
                                            'cpf_fiador' => 'Cpf',
                                            'cep_fiador' => 'Cep',
                                            'endereco_fiador' => 'Endereco',
                                            'numero_fiador' => 'Numero',
                                            'complemento_fiador' => 'Compl',
                                            'cidade_fiador' => 'Cidade',
                                            'assinatura_fiador' => 'assinatura_fiador',
                                            'uf_fiador' => 'Uf',
                                            'identidade_fiador' => 'Ident',
                                            'endereco_completo_fiador' => 'endereco_completo',
                                            'estado_civil_fiador' => 'config][estado_civil',
                                        ];
                                        $arr_contrato = Qlib::lib_json_array($dm['contrato']);
                                        // dd($arr_contrato);
                                        foreach ($arr_fiador as $kf => $vf) {
                                            $dfi = dados_tab($GLOBALS['tab16'],'*',"WHERE id='$vf'");
                                            if($dfi){
                                                if(isset($dfi[0]['config']) && !empty($dfi[0]['config'])){
                                                    $dfi[0]['config'] = Qlib::lib_json_array($dfi[0]['config']);
                                                }
                                                foreach ($arr_sortcode as $ksh => $vsh) {
                                                    $ar_vs = explode('][', $vsh);
                                                    $vcamp = isset($dfi[0][$vsh])?$dfi[0][$vsh]:false;
                                                    if(isset($ar_vs[1])){
                                                        if(is_array($dfi[0][$ar_vs[0]])){
                                                            // dd($dfi[0][$ar_vs[0]][$ar_vs[1]]);
                                                            $vcamp = isset($dfi[0][$ar_vs[0]][$ar_vs[1]])?$dfi[0][$ar_vs[0]][$ar_vs[1]]:false;
                                                        }
                                                    }
                                                    if($vsh=='assinatura_fiador'){
                                                        $kfiad = 'contrato_fiador_'.$vf;
                                                        // lib_print($kfiad);
                                                        if(isset($arr_contrato[$kfiad]['aceito']) && $arr_contrato[$kfiad]['aceito']=='on'){
                                                            $arr_as = $arr_contrato['contrato_fiador_'.$vf];
                                                            $vcamp = '<span class="text-danger"><b>Contrato assinado eletronicamente pelo fiador em '.@$arr_as['data'].'</b> </span><span style="text-align:right" class="text-danger"><b>Ip:</b> <i>'.@$arr_as['ip'].'</i></span>';
                                                        }else{
                                                            $vcamp = false;
                                                        }
                                                    }

                                                    if($vsh=='endereco_completo'){
                                                        $vcamp = $dfi[0]['Endereco'].', '.$dfi[0]['Numero'].' '.$dfi[0]['Compl'].' '.$dfi[0]['Bairro'].' '.$dfi[0]['Cidade'].' '.$dfi[0]['Uf'];
                                                    }
                                                    $ret['contrato'] = str_replace('{'.$ksh.$ifi.'}',$vcamp,$ret['contrato']);

                                                }
                                                // lib_print($dfi);
                                            }
                                            $ifi++;
                                        }
                                    }else{


                                    }
                                }
                                // dd($arr_contrato);


                                $id_contatada = Qlib::qoption('id_contatada') ? Qlib::qoption('id_contatada') : 14;
                                $id_testemunha1 = Qlib::qoption('id_testemunha1') ? Qlib::qoption('id_testemunha1') : 137;
                                $id_testemunha2 = Qlib::qoption('id_testemunha2') ? Qlib::qoption('id_testemunha2') : 95;
                                $dcont = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_contatada."'");
                                $dtes1 = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_testemunha1."' AND ".Qlib::compleDelete());
                                $dtes2 = Qlib::dados_tab_SERVER('usuarios_sistemas','*',"WHERE id='".$id_testemunha2."' AND ".Qlib::compleDelete());
                                $nome_contratada = '';
                                $nome_testemunha1 = '';
                                $nome_testemunha2 = '';
                                $cpf_testemunha1 = '';
                                $cpf_testemunha2 = '';
                                $cpf_contratada = '';
                                $assinatura_contratada = '';
                                $assinatura_testemunha1 = '';
                                $assinatura_testemunha2 = '';
                                if($dcont){
                                    // $nome_contratada = $dcont[0]['nome'].' '.$dcont[0]['sobrenome'];
                                    $nome_contratada = 'AEROCLUBE DE JUIZ DE FORA';
                                    $cpf_contratada = $dcont[0]['cpf'];
                                    $assinatura_contratada = '<span class="text-danger">Contrato assinado digitalmete por <b>{nome_contratada}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                                    $assinatura_contratada = str_replace('{nome_contratada}',$nome_contratada,$assinatura_contratada);
                                }
                                if($dtes1){
                                    $nome_testemunha1 = $dtes1[0]['nome'].' '.$dtes1[0]['sobrenome'];
                                    $cpf_testemunha1 = $dtes1[0]['cpf'];
                                    $assinatura_testemunha1 = '<span class="text-danger" style="">Contrato assinado digitalmete por <b>{nome_testemunha1}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                                    $assinatura_testemunha1 = str_replace('{nome_testemunha1}',$nome_testemunha1,$assinatura_testemunha1);
                                }
                                if($dtes2){
                                    $nome_testemunha2 = $dtes2[0]['nome'].' '.$dtes2[0]['sobrenome'];
                                    $cpf_testemunha2 = $dtes2[0]['cpf'];
                                    $assinatura_testemunha2 = '<span class="text-danger" style="">Contrato assinado digitalmete por <b>{nome_testemunha2}</b> na data em '.Qlib::dataExibe(@$arr_contratoAss['data_aceito_contrato']).'</span>';
                                    $assinatura_testemunha2 = str_replace('{nome_testemunha2}',$nome_testemunha2,$assinatura_testemunha2);
                                }

                                if(isset($nome_empresa) && !empty($nome_empresa)){
                                    $ret['contrato'] = str_replace('{nome_empresa}',$nome_empresa,$ret['contrato']);
                                }else{
                                    $ret['contrato'] = str_replace('{nome_empresa}','',$ret['contrato']);
                                }
                                $ret['contrato'] = str_replace('{nome_contratada}',$nome_contratada,$ret['contrato']);
                                $ret['contrato'] = str_replace('{cpf_contratada}',$cpf_contratada,$ret['contrato']);
                                $ret['contrato'] = str_replace('{nome_testemunha1}',$nome_testemunha1,$ret['contrato']);
                                $ret['contrato'] = str_replace('{cpf_testemunha1}',$cpf_testemunha1,$ret['contrato']);
                                $ret['contrato'] = str_replace('{nome_testemunha2}',$nome_testemunha2,$ret['contrato']);
                                $ret['contrato'] = str_replace('{cpf_testemunha2}',$cpf_testemunha2,$ret['contrato']);
                                $ret['contrato'] = str_replace('{assinatura_contratada}',$assinatura_contratada,$ret['contrato']);
                                $ret['contrato'] = str_replace('{assinatura_testemunha1}',$assinatura_testemunha1,$ret['contrato']);
                                $ret['contrato'] = str_replace('{assinatura_testemunha2}',$assinatura_testemunha2,$ret['contrato']);

                                $temaStyle = '<div id="modal-contrato-aluno" class="bloco-texto" style="text-align:justify">{con}</div>';
                                $ret['contrato'] = str_replace('{con}',$ret['contrato'],$temaStyle);
                                $ret['contrato'] = (new CursosController)->short_codes_Plano($dm['token'],$ret['contrato']);

                    //}

            }else{
                $ret['mens'] = Qlib::formatMensagem0('Dados insuficientes para montar o contrato','danger',10000);
            }
        }
        return $ret;
    }
    /**
     * renderiza um termo do aeroclube
     */
    public function termo_concordancia($config=false,$dm=false){

        $ret['exec'] = false;

        $ret['contrato'] = false;
        $token = isset($config['token']) ? $config['token'] : null;
        $type = isset($config['type'])?$config['type']:'termo_concordancia';
        if($token && !$dm){
            $dm = $this->dm($token);
        }
        if($dm){
            // $sql = "SELECT * FROM ".$GLOBALS['tab12']." WHERE `token` = '".base64_decode($config['token'])."'";

            $dadosMatricula[0] 	= $dm;
            $dadosCliente 	= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab15']." WHERE id='".$dadosMatricula[0]['id_cliente']."'");
            $dadosCurso 		= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab10']." WHERE id='".$dadosMatricula[0]['id_curso']."'");
            $dadosTurma 		= Qlib::buscaValoresDb("SELECT * FROM ".$GLOBALS['tab11']." WHERE id='".$dadosMatricula[0]['id_turma']."'");
            $st = new SiteController();
            $tr_responsavel = false;
            $aluno 						= false;
            $cpf_aluno 				= false;
            $cpf_contrato 			= false;
            $telefone 					= false;
            $endereco 				= false;
            $numero_matricula 	= false;
            $curso 						= false;
            $inicio 						= false;
            $fim 							= false;
            $horario 					= false;
            $valor_total 				= false;
            $valor_extensso		= false;
            $carga_horaria			= false;
            $data_contrato			= false;
            $data_nascimento			= false;
            $dataMatricula			= false;
            $cep				= false;
            $assinatura				= false;
            $dias						= false;
            $nome 						= false;
            $nome_empresa		= 'Aeroclubejf';
            $arr_status_mat = Qlib::sql_array("SELECT * FROM status_matricula WHERE `ativo`='s' AND ".Qlib::compleDelete()." ORDER BY nome ASC",'nome','id');
            $tema0 = '
            <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">
                    <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">
                                <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar
                    </a>
            </div>
            ';
            $ret['nome_arquivo'] = ucwords(str_replace('_',' ',$type)). ' '.$dm['Nome'].' '.$dm['nome_curso'].' '.$dm['id'];

            $periodo = isset($config['periodo']) ? $config['periodo'] : '';
            if($periodo){
                $periodo = str_replace('contrato_','',$periodo);
                $periodo = str_replace('_',' ',$periodo);
            }

           if($dadosMatricula && $dadosCliente && $dadosCurso){
                   $config_curso = [];
               if(isset($dadosCurso[0]['config'])){
                   $config_curso = Qlib::lib_json_array($dadosCurso[0]['config']);
               }
               // if($type == 'termo_escola_voo' || $type == 'termo_concordancia'){
               // }
               // if(isset($_GET['fp'])){
               // 	lib_print($config_curso);
               // }
               if(isset($config_curso['adc']['recheck']) && $config_curso['adc']['recheck']=='s'){
                   $ret['contrato'] = false;
                   return $ret;
               }
            //    dd($type);
               $dadoContratoM = $st->short_code($type);


               if($type=='termo_antecipacao_combustivel'){
                   $arr_orc = Qlib::lib_json_array($dadosMatricula[0]['orc']);
                   // if(isAdmin(1))
                   // dd($arr_orc);
                   if(isset($arr_orc['sele_pag_combustivel'])){
                       if($arr_orc['sele_pag_combustivel']!='antecipado'){
                           $ret['contrato'] = false;
                           return $ret;
                       }
                   }
                   if(isset($dadosCurso[0]['tipo']) && $dadosCurso[0]['tipo'] == 4){
                       $ret['contrato'] = false;
                       return $ret;
                   }

                }

                $ret['dadosMatricula']=$dadosMatricula;
                $ret['dadosCurso']=$dadosCurso;
                $ret['dadosTurma']=$dadosTurma;
                if(Qlib::isAdmin(3)){
                    $ret['verificaAlunoMatricula'] = $this->verificaAlunoMatricula($dadosCliente[0]['id'],'contrato',$dadosMatricula[0]['id_responsavel']);
                    if(!$ret['verificaAlunoMatricula']['liberado']){
                            $ret['mens'] = $ret['verificaAlunoMatricula']['mens'];
                        // if(isset($_GET['fq'])){
                        // 	echo $type;
                        // 	lib_print($ret);
                        // 	// lib_print($dadoContratoM);
                        // 	// lib_print($dadosCliente);
                        // 	// lib_print($dadosCurso);
                        // }
                        return $ret;
                    }
                }
                             //if($dadosMatricula[0]['status']==1){
                        $ret['mens'] = Qlib::formatMensagem0('Não é possível emitir comtrato para clientes com status de <b>'.$arr_status_mat[$dadosMatricula[0]['status']].'</b>, efetue primeiro a matrícula deste cliente ','danger',10000);
                    //}else{
                                $arr_dias = array(1=>array('dia'=>'Segunda','cor'=>'success'),2=>array('dia'=>'Terça','cor'=>'primary'),3=>array('dia'=>'Quarta','cor'=>'info'),
                                                    4=>array('dia'=>'Quinta','cor'=>'default'),5=>array('dia'=>'sexta','cor'=>'warning'),6=>array('dia'=>'Sábado','cor'=>'danger'));
                                // $tema0 = '<form id="form-status"  method="post">';
                                // $tema0 		.= queta_formfield4("hidden",'1',"tab-", $GLOBALS['tab12'],"","");
                                //     $tema0 		.= queta_formfield4("hidden",'1',"token-", $dadosMatricula[0]['token'],"","");
                                //     $tema0			.= queta_formfield4("hidden",'1',"evento-", 'Contrato aceito',"","");
                                //     $tema0 		.= queta_formfield4("hidden",'1',"status-", 3,"","");
                                // $tema0 .= '</form>';
                                $tema0 = '';
                                $tema = '<style>h1,h2,h3{text-align:center;}</style>';
                                if(Qlib::isAdmin()){
                                $tema .= '
                                            <div class="row">
                                                    <div class="col-sm-12 mens"></div>
                                                    '.$tema0.'
                                                    <div class="col-sm-12">
                                                        {contrato}
                                                    </div>
                                                    <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">
                                                            <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">
                                                                        <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar
                                                            </a>
                                                            <button type="button" title="imprimir" style="margin-right:5px" class="btn btn-success btn-md" que-btn="imprimir_contrato">
                                                                        <i class="fa fa-print" aria-hidden="true"></i> Imprimir
                                                            </button>
                                                    </div>
                                            </div>
                                            ';
                                }else{
                                    $tema .= '
                                            <div class="row">
                                                    <div class="col-sm-12 mens"></div>
                                                            <div class="col-sm-12">
                                                        {contrato}
                                                    </div>
                                                    <!--
                                                    <div class="col-md-12 div-salvar hidden-print" style="padding-top:0px">
                                                            <a href="javaScript:void(0);" onclick="window.close();" style="margin-right:5px" que-bt="voltar" title="voltar" class="btn btn-danger">
                                                                        <i class="fa fa-chevron-left" aria-hidden="true"></i> Cancelar
                                                            </a>
                                                            <button type="button" title="imprimir" style="margin-right:5px" class="btn btn-success btn-md" que-btn="imprimir_contrato">
                                                                        <i class="fa fa-print" aria-hidden="true"></i> Imprimir
                                                            </button>
                                                    </div>-->
                                            </div>
                                    ';
                                }

                            if(!$dadoContratoM){
                                $ret['contrato'] = false;
                                return $ret;
                            }

                            $contrato				 	= str_replace('{contrato}',$dadoContratoM ,$tema);
                                $responsavel 			= ($dadosMatricula[0]['id_responsavel'] > 0 ) ? $dadosMatricula[0]['id_responsavel'] : false;
                            $aluno = isset($dm['nome_completo']) ? $dm['nome_completo'] : false;
                            if(!$aluno){
                                if(is_null($dadosCliente[0]['sobrenome'])){
                                    $aluno = ucwords($dadosCliente[0]['Nome']) ;
                                }else{
                                    $aluno = ucwords($dadosCliente[0]['Nome']) .' '.ucwords($dadosCliente[0]['sobrenome']);
                                }
                            }
                            $cpf_aluno 			= $dadosCliente[0]['Cpf'];
                            $email 				= $dadosCliente[0]['Email'];
                            $cep 				= $dadosCliente[0]['Cep'];
                            $identidade 		= $dadosCliente[0]['Ident'];
                            $data_nascimento 	= Qlib::dataExibe($dadosCliente[0]['DtNasc2']);
                            $telefone 			= $dadosCliente[0]['Celular'].' '. $dadosCliente[0]['Tel'];
                            $nacionalidade 		= $dadosCliente[0]['nacionalidade'];
                            $profissao 			= $dadosCliente[0]['profissao'];
                            $endereco 			= $dadosCliente[0]['Endereco'].', '. $dadosCliente[0]['Numero'].' '.$dadosCliente[0]['Compl'].' - '. $dadosCliente[0]['Bairro'].' - '.$dadosCliente[0]['Cidade'].' /'.$dadosCliente[0]['Uf'];
                            if(is_null($dadosCliente[0]['estado_civil'])){
                                $dadosCliente[0]['estado_civil'] = '';
                            }
                            $estado_civil			= ucfirst($dadosCliente[0]['estado_civil']);
                            $numero_matricula 	= Qlib::zerofill($dadosMatricula[0]['id'],10);
                            $dadosTurma[0]['id'] = isset($dadosTurma[0]['id'])?$dadosTurma[0]['id']:false;
                            $dadosTurma[0]['nome'] = isset($dadosTurma[0]['nome'])?$dadosTurma[0]['nome']:false;
                            $dadosTurma[0]['inicio'] = isset($dadosTurma[0]['inicio'])?$dadosTurma[0]['inicio']:false;
                            $dadosTurma[0]['fim'] = isset($dadosTurma[0]['fim'])?$dadosTurma[0]['fim']:false;
                            $dadosTurma[0]['hora_inicio'] = isset($dadosTurma[0]['hora_inicio'])?$dadosTurma[0]['hora_inicio']:false;
                            $dadosTurma[0]['hora_fim'] = isset($dadosTurma[0]['hora_fim'])?$dadosTurma[0]['hora_fim']:false;
                            $dadosTurma[0]['duracao'] = isset($dadosTurma[0]['duracao'])?$dadosTurma[0]['duracao']:false;
                            $dadosTurma[0]['unidade_duracao'] = isset($dadosTurma[0]['unidade_duracao'])?$dadosTurma[0]['unidade_duracao']:false;
                            $dadosTurma[0]['dia'] = isset($dadosTurma[0]['dia'])?$dadosTurma[0]['dia']:false;
                                $curso 						= $dadosTurma[0]['id'] .' '. $dadosTurma[0]['nome']  ;
                                //$curso						= strtoupper($curso);
                                $inicio 						= Qlib::dataExibe($dadosTurma[0]['inicio']);
                                $fim 							= Qlib::dataExibe($dadosTurma[0]['fim']);
                                $horario 					= $dadosTurma[0]['hora_inicio'] .' - '.$dadosTurma[0]['hora_fim'] ;
                                $arr_valor 				= json_decode($dadosMatricula[0]['reg_pagamento'],true);
                                $carga_horaria			= $dadosTurma[0]['duracao'] .' '. $dadosTurma[0]['unidade_duracao'];
                                $data_contrato 			= date('d/m/Y (H:m:i)');
                                $nome 						= $aluno;
                                //$endereco			=$dadosClie
                                if(is_array($arr_dias)){
                                    foreach($arr_dias As $key=>$dia){
                                        if(@$dadosTurma[0]['dia'.$key] == 's'){
                                            $dias .= $dia['dia'].'/';
                                        }
                                    }
                                    $dias = substr($dias,0,-1);
                                }
                                        if(is_array($arr_valor) && isset($arr_valor['valor_total'])){
                                    $pos = strpos($arr_valor['valor_total'],'.');
                                    if($pos != false){
                                        $arr_valor['valor_total'] = Qlib::precoDbdase(@$arr_valor['valor_total']);
                                    }
                                    $valor_total 				= number_format(@$arr_valor['valor_total'],2,',','.');
                                    $valor_extensso		= Qlib::lib_valorPorExtenso(@$arr_valor['valor_total']);
                                }
                                if($responsavel){
                                    $dadosResponsavel = Qlib::buscaValoresDb("SELECT *  FROM ".$GLOBALS['tab16']." WHERE id='".$responsavel."'");
                                    if($dadosResponsavel){
                                        $ret['exec'] = true;
                                        $tr_responsavel =
                                            '<table cellspacing="0" cellpadding="0">
                                                <tbody>
                                                    <tr>
                                                        <td>Responsável:</td>
                                                        <td>&nbsp;</td>
                                                        <td colspan="3">'.strtoupper($dadosResponsavel[0]['Nome']).'&nbsp;</td>
                                                        <td>CPF:</td>
                                                        <td>'.$dadosResponsavel[0]['Cpf'].'</td>
                                                    </tr>
                                                </tbody>
                                            </table>';
                                        $nome 				= strtoupper($dadosResponsavel[0]['Nome']);
                                        $cpf_contrato 	= $dadosResponsavel[0]['Cpf'];
                                        $telefone 			= $dadosResponsavel[0]['Celular'].' '. $dadosResponsavel[0]['Tel'];
                                        $endereco 		= $dadosResponsavel[0]['Endereco'].', '. $dadosResponsavel[0]['Numero'].' '.$dadosResponsavel[0]['Compl'].' - '. $dadosResponsavel[0]['Bairro'].' - '.$dadosResponsavel[0]['Cidade'].'/'.$dadosResponsavel[0]['Uf'];
                                    }else{
                                        $ret['mens'] = Qlib::formatMensagem0('Dados do responsável não encontrados','danger',10000);
                                    }
                                }else{
                                    //echo 'não tem responsavel';
                                }
                                $assinatura = '<table>
                                                <tbody><tr><td style="width:500px">&nbsp;</td><td>&nbsp;</td></tr>
                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                                                <tr>
                                                    <td>________________________________________</td>
                                                    <td>________________________________________</td>
                                                </tr>
                                                <tr>
                                                    <td>CONTRATANTE</td>
                                                    <td>'.Qlib::qoption('assinatura_contrato').'</td>
                                                </tr>
                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                                                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                                                <tr>
                                                    <td>TESTEMUNHAS</td>
                                                    <td>&nbsp;</td>
                                                </tr>
                                                        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
                                                        <tr>
                                                    <td>________________________________________</td>
                                                    <td>________________________________________</td>
                                                </tr>
                                                <tr>
                                                    <td>NOME: </td>
                                                    <td>NOME: </td>
                                                </tr>
                                                <tr>
                                                    <td>CPF: </td>
                                                    <td>CPF: </td>
                                                </tr>
                                            </tbody></table>';
                                $dia_contrato = false;
                                $mes_contrato = false;
                                $ano_contrato = false;
                                if($dadosMatricula[0]['contrato']){
                                    if(!empty($dadosMatricula[0]['contrato'])){
                                        $arr_contratoAss = Qlib::lib_json_array($dadosMatricula[0]['contrato']);
                                    // dd($arr_contratoAss);
                                    if(isset($arr_contratoAss['declaracao']) && isset($arr_contratoAss['declaracao']) && isset($arr_contratoAss['aceito_termo_concordancia']) && $arr_contratoAss['aceito_termo_concordancia'] =='on'){
                                            $assinatura = '<span class="text-danger"><b>Termo assinado eletronicamente pelo aluno em '.Qlib::dataExibe(@$arr_contratoAss['aceito_termo_concordancia']).'</b> </span><span style="text-align:right" class="text-danger"><b>Ip:</b> <i>'.@$arr_contratoAss['ip'].'</i></span>';
                                        }
                                    }
                                }
                                $sc = $this->simuladorCombustivel($dadosMatricula[0]['token'],$dadosMatricula[0]);
                            $valor_combustivel = isset($sc['valor']) ? $sc['valor']: null;
                            if($valor_combustivel){
                                $valor_combustivel = Qlib::valor_moeda($valor_combustivel);
                            }
                            $ret['exec'] = true;

                            $ret['contrato'] = str_replace('{responsavel}',$responsavel,$contrato);
                                $ret['contrato'] = str_replace('{tr_responsavel}',$tr_responsavel,$ret['contrato']);
                                if(is_null($aluno)){
                                $aluno = '';
                            }
                            $ret['contrato'] = str_replace('{aluno}',$aluno,$ret['contrato']);
                            if(is_null($cpf_aluno)){
                                $cpf_aluno = '';
                            }
                            $ret['contrato'] = str_replace('{cpf_aluno}',$cpf_aluno,$ret['contrato']);

                            if(is_null($identidade)){
                                $identidade = '';
                            }
                            $ret['contrato'] = str_replace('{identidade}',$identidade,$ret['contrato']);
                            $ret['contrato'] = str_replace('{valor_combustivel}',$valor_combustivel,$ret['contrato']);
                                $ret['contrato'] = str_replace('{cpf_contrato}',$cpf_contrato,$ret['contrato']);
                                $ret['contrato'] = str_replace('{telefone}',$telefone,$ret['contrato']);
                                $ret['contrato'] = str_replace('{endereco}',$endereco.'&nbsp;',$ret['contrato']);
                                $ret['contrato'] = str_replace('{numero_matricula}',$numero_matricula,$ret['contrato']);
                                $ret['contrato'] = str_replace('{curso}',$curso,$ret['contrato']);
                                $ret['contrato'] = str_replace('{inicio}',$inicio,$ret['contrato']);
                                $ret['contrato'] = str_replace('{fim}',$fim,$ret['contrato']);
                                $ret['contrato'] = str_replace('{email}',$email,$ret['contrato']);
                                $ret['contrato'] = str_replace('{horario}',$horario,$ret['contrato']);
                                $ret['contrato'] = str_replace('{valor_total}',$valor_total,$ret['contrato']);
                                $ret['contrato'] = str_replace('{valor_extensso}',$valor_extensso,$ret['contrato']);
                                $ret['contrato'] = str_replace('{data_nascimento}',$data_nascimento,$ret['contrato']);
                                $ret['contrato'] = str_replace('{estado_civil}',$estado_civil,$ret['contrato']);
                                $ret['contrato'] = str_replace('{carga_horaria}',$carga_horaria,$ret['contrato']);
                            if(is_null($nacionalidade)){
                                $nacionalidade='';
                            }
                            $ret['contrato'] = str_replace('{nacionalidade}',@$nacionalidade,$ret['contrato']);
                            if(is_null($profissao)){
                                $profissao='';
                            }

                            $ret['contrato'] = str_replace('{profissao}',$profissao,$ret['contrato']);

                            $ret['contrato'] = str_replace('{dias}',$dias,$ret['contrato']);
                            $ret['contrato'] = str_replace('{periodo}',$periodo,$ret['contrato']);

                            if(is_null($assinatura)){
                                $assinatura='';
                            }
                            $ret['contrato'] = str_replace('{assinatura}',$assinatura,$ret['contrato']);
                                $ret['contrato'] = str_replace('{data_contrato}',$data_contrato,$ret['contrato']);
                                $ret['contrato'] = str_replace('{nome}',$nome,$ret['contrato']);
                            if(is_null($cep)){
                                $cep='';
                            }
                            $ret['contrato'] = str_replace('{cep}',$cep,$ret['contrato']);
                            if(isset($nome_empresa) && !empty($nome_empresa)){
                                $ret['contrato'] = str_replace('{nome_empresa}',$nome_empresa,$ret['contrato']);
                            }else{
                                $ret['contrato'] = str_replace('{nome_empresa}','',$ret['contrato']);
                            }
                                    $temaStyle = '<div id="modal-contrato-aluno" class="bloco-texto" style="text-align:justify">{con}</div>';
                                $ret['contrato'] = str_replace('{con}',$ret['contrato'],$temaStyle);
                            //}
               }else{
                   $ret['mens'] = Qlib::formatMensagem0('Dados insuficientes para montar o contrato','danger',10000);
               }
        }

        return $ret;

    }
    /**
     * Retorna o id da matricula pelo token
     */
    public function get_id_by_token($token){
		return Qlib::buscaValorDb0($GLOBALS['tab12'],'token',$token,'id'," AND ".Qlib::compleDelete());
	}
    /**
     * Retorna o token da matricula pelo id
     */
	public function get_token_by_id($id){
		return Qlib::buscaValorDb0($GLOBALS['tab12'],'id',$id,'token'," AND ".Qlib::compleDelete());
	}

    /**
	 * Valida as resposta fornecidas na assinatura se esta permitido passar para outra tela
	 * @param int $id_matricula,string $campo= tipo de campo
	 * @return array $ret
	 */
	public function valida_respostas_assinatura($id_matricula=false,$campo='id'){
		$ret = false;
		if($id_matricula){
			if($campo=='id'){
				$token = $this->get_token_by_id($id_matricula);
			}elseif($campo=='token'){
				$token = $id_matricula;
			}
			$dm = $this->dm($token);
			// echo $token;
			// lib_print($dm);
			$tipo_curso = isset($dm['tipo_curso']) ? $dm['tipo_curso'] : 0;
			if(isset($dm['config'])){
                // $dm = $dm[0];
				$arr_conf = Qlib::lib_json_array($dm['config']);
				if((isset($dm['id_curso']) && $dm['id_curso']==132) || @$dm['tipo_curso'] == 4 ){
                    //id 132 se refere ao Curso de Mecânico de Manutenção Aeronáutica ou se o tipo de curso for um plano de formação
					return true;
				}
				if(isset($arr_conf['adc']['recheck']) && $arr_conf['adc']['recheck'] =='s' ){
                    //se for recheck retorna verdadeiro para as resposta das assinaturas
					$ret = true;
				}else{
                    $total_reps = 0;
					$resp = Qlib::get_matriculameta($dm['id'],'ciente');
                    // dd($resp,$dm);
					if($resp){
						$arr = Qlib::lib_json_array($resp);
						foreach ($arr as $kr => $vr) {
							$total_reps++;
							if($vr=='s'){
								$ret = true;
							}else{
								$ret = false;
							}
						}
					}
					if($total_reps<5){
						//totol de aceitos tem que ser 4 do contrario retorna falso
						$ret = false;
					}
				}
			}
			if($tipo_curso==1){
				//nesse caso curso é um EAD
				$ret = true;
			}
		}
		return $ret;
	}
	/**
	 * Valida as resposta fornecidas na assinatura do contrato de periodos se esta permitido passar para este periodo
	 * @param int $id_matricula,string $campo= tipo de campo,string $campo_meta é o campo de gravado as resposta
	 * @return array $ret
	 */
	public function valida_respostas_assinatura_periodo($id_matricula=false,$campo='id',$campo_meta='ciente',$tr=5){
		$ret = false;
		if($id_matricula){
			if($campo=='id'){
				$token = $this->get_token_by_id($id_matricula);
			}elseif($campo=='token'){
				$token = $id_matricula;
			}
			$dm = $this->dm($token);
			// echo $token;
			// lib_print($dm);
			return true;
			if(isset($dm[0]['config_curso'])){
				$dm = $dm[0];
				$arr_conf = Qlib::lib_json_array($dm['config']);
				if((isset($dm['id_curso']) && $dm['id_curso']==132) || @$dm['tipo_curso'] == 4 ){
					//id 132 se refere ao Curso de Mecânico de Manutenção Aeronáutica ou se o tipo de curso for um plano de formação
					return true;
				}
				if(isset($arr_conf['adc']['recheck']) && $arr_conf['adc']['recheck'] =='s' ){
					//se for recheck retorna verdadeiro para as resposta das assinaturas
					$ret = true;
				}else{
					$total_reps = 0;
					$resp = Qlib::get_matriculameta($id_matricula,$campo_meta);
					if($resp){
						$arr = Qlib::lib_json_array($resp);
						foreach ($arr as $kr => $vr) {
							$total_reps++;
							if($vr=='s'){
								$ret = true;
							}else{
								$ret = false;
							}
						}
					}
					if($total_reps<$tr){
						//totol de aceitos tem que ser $tr do contrario retorna falso
						$ret = false;
					}
				}
			}
		}
		// dd($total_reps);
		return $ret;
	}
    /**
	 * Salva todas as etapas de aceitação do contrato
	 */
	public function assinar_proposta($config){
		$ret['exec'] = false;
		$ret['config'] = $config;
		$ret['valida']['mens'] = false;
        if(isset($config['pagina']) && $config['pagina']==2){
            //salvar conteudo da página 2
            if(isset($config['token_matricula']) && isset($config['contrato']) && is_array($config['contrato'])){
                //11 o id da etapa 'Proposta aprovada' do flow de atendimento
				// $sql = "UPDATE IGNORE ".$GLOBALS['tab12']." SET contrato='".Qlib::lib_array_json($config['contrato'])."',etapa_atual='11' WHERE token='".$config['token_matricula']."'";
				// $ret['exec'] = salvarAlterar($sql);
				$ret['exec'] = Qlib::update_tab($GLOBALS['tab12'],[
                    'contrato'=> Qlib::lib_array_json($config['contrato']),
                    'etapa_atual'=> 11,
                    'status'=> 1,
                ],"WHERE token='".$config['token_matricula']."'"); //salvarAlterar($sql);
            	if($ret['exec']){
					// $id_matricula = cursos::get_id_by_token($config['token_matricula']);
					//gravar contrato estatico...
					$ret['validar'] = $this->valida_respostas_assinatura($config['token_matricula'],'token');
					if($ret['validar']){
						// $ret['gravar_copia'] = $this->grava_contrato_statico($config['token_matricula']);
                        GeraPdfContratoJoub::dispatch($config['token_matricula']);
                        SendZapsingJoub::dispatch($config['token_matricula'])->delay(now()->addSeconds(5));
            			$ret['nextPage'] = Qlib::qoption('dominio').'/solicitar-orcamento/proposta/'.$config['token_matricula'].'/a';
					}else{
						$ret['exec'] = false;
						$ret['mens'] = 'Erro ao validar as respostas do termo';
					}
				}
			}
			return $ret;
		}
		if(isset($config['Cpf']) && !empty($config['Cpf'])){
			$validaCpf = Qlib::validaCpf($config['Cpf']);
			if($validaCpf){
				$ret['valida']['cpf'] = true;
			}else{
				$ret['valida']['mens'] = 'CPF inválido';
				$ret['valida']['cpf'] = 'error';
				return $ret;
			}
			$tabUser=$GLOBALS['tab15'];
			if(isset($config['Nome']) && !empty($config['Nome'])){
				//verificar se esta com o nome completo

				if(str_word_count($config['Nome'])>1){

						$n = explode(' ',$config['Nome']);

					   if(isset($n[1]) && !empty($n[1])){

						   $config['sobrenome'] = trim(str_replace($n[0],'',$config['Nome']));
						   $config['Nome'] = $n[0];
					   }

				}else{
					$ret['valida']['mens'] = 'Informe o nome completo';
					$ret['valida']['nome'] = 'error';
					return $ret;
				}

				$cond_valid = "WHERE token = '".$config['token']."'";
				$type_alt = 2;
				// $config['conf'] = 's';//confirmação para salvar
				$config['id'] = Qlib::buscaValorDb0($GLOBALS['tab15'],'token',$config['token'],'id');
				// $config2 = array(
				// 	'tab'=>$tabUser,
				// 	'valida'=>true,
				// 	'condicao_validar'=>$cond_valid,
				// 	'sqlAux'=>false,
				// 	'ac'=>'alt',
				// 	'type_alt'=>$type_alt,
				// 	'dadosForm' => $config
				// );
                $dsc = $config;
                // dump($tabUser);
                unset(
                    $dsc['pagina'],
                    $dsc['token_matricula'],
                    $dsc['meta'],
                    $dsc['campo_bus'],
                    $dsc['campo_id']
                );
                // return $dsc;
                $ret = Qlib::update_tab($tabUser,$dsc,$cond_valid);
				// $ret = lib_salvarFormulario($config2);//Declado em Lib/Qlibrary.php
				// $ret = json_decode($ret,true);
				$ret['valida']['mens'] = false;
				unset($ret['salvar']['url1']);
				// dd($ret);
				//verificar se o campo $config['meta está presente'];
				if(isset($config['meta']) && isset($config['token_matricula'])){
					//Gravar dados meta $config['id'] nesse caso é o id da matricula
					$config['id'] = $this->get_id_by_token($config['token_matricula']);
                    // dd($config);
					// lib_print($config);
					$ret['meta'] = $this->sava_meta_fields($config);
				}
				//validar as respostas para passar para proxima tela
				$ret['nextPage'] = '';
				$ret['validar'] = false;
				if(isset($config['token_matricula'])){
					$ret['validar'] = $this->valida_respostas_assinatura($config['token_matricula'],'token');
				}

				if($ret['validar']){
					$ret['nextPage'] = Qlib::qoption('dominio').'/solicitar-orcamento/proposta/'.$config['token_matricula'].'/f/2#ciente';
				}else{
					$ret['exec'] = false;
					$ret['mens'] = 'Erro ao validar as respostas do termo';
					$ret['color'] = 'danger';
				}
				// dd($ret);
		    }
		}
		// dd($ret);
		return $ret;
	}
    /**
	 * Salvar um array de meta campos provedientes de um formulario
	 * @param array $config
	 * @return array
	 */
	public function sava_meta_fields($config){
		$id_matricula = isset($config['id'])?$config['id']:null;
		$meta = isset($config['meta'])?$config['meta']:null;
		$ret['exec'] = false;
    	if($id_matricula && $meta){
			if(!isset($meta['instrutores'])){
				$verf = Qlib::get_matriculameta($id_matricula,'instrutores',true);
				if($verf) {
					$ret['sm']['remove_inst'] = Qlib::update_matriculameta($id_matricula,'instrutores',Qlib::lib_array_json([]));
					if($ret['sm']['remove_inst']){
						$ret['exec'] = true;
					}
				}
			}
			foreach ($meta as $km => $vm) {
				if(is_array($vm)){
					$ret['sm'][$km] = Qlib::update_matriculameta($id_matricula,$km,Qlib::lib_array_json($vm));
				}else{
					$ret['sm'][$km] = Qlib::update_matriculameta($id_matricula,$km,$vm);
				}
				if($ret['sm'][$km]){
					$ret['exec'] = true;
				}
			}
		}
		return $ret;
	}
    /**
     * Enviar um envelope com 1 documento para o zapsing
     * @param string $tm token da matricula
     * @param string $dm dados da matricula para evitar uma nova consulta
     */
    public function enviar_envelope($tm,$dm=false,$url_pdf=''){
        if(!$dm && $tm){
            $dm = $this->dm($tm);
        }
        $zpc = new ZapsingController;;
        $ret['exec'] = false;
        if($dm && $url_pdf){
            $nome = isset($dm['nome_completo']) ? $dm['nome_completo'] : '';
            $email = isset($dm['Email']) ? $dm['Email'] : '';
            $cpf = isset($dm['Cpf']) ? $dm['Cpf'] : '';
            $signers = [
                "name" => $nome,
                "email" => $email,
                "cpf" => $cpf,
                "send_automatic_email" => true,
                "send_automatic_whatsapp" => false,
                "auth_mode" => "CPF", //tokenEmail,assinaturaTela-tokenEmail,tokenSms,assinaturaTela-tokenSms,tokenWhatsapp,assinaturaTela-tokenWhatsapp,CPF,assinaturaTela-cpf,assinaturaTela
                "order_group" => 1,
            ];
            $signers = $zpc->signers_matricula($signers);
            //Criar o nome
            $name = $nome. ' * '.@$dm['nome_curso'].' - '.@$dm['id'];
            $body = [
                "name" => trim($name),// 'Assinatura da proposta',
                "url_pdf" => $url_pdf,
                "external_id" => $tm,
                "folder_path" => '/CRM',
                "signers" =>$signers,
                ];
            //eviar
            $ret = (new ZapsingController)->post([
                // "endpoint" => 'docs',
                "body" => $body,
            ]);
        }
        return $ret;

    }
    /**
     * Metodo para enviar o termo para zapsing
     * @params $tm $token da matricula
     * @usu $ret = (new MatriculaController)->send_to_zapSing('token_matricula');
     */
    public function send_to_zapSing($tm,$dm=false){
        if(!$dm && $tm){
            $dm = $this->dm($tm);
        }
        $ret['exec'] = false;
        $ret['dm'] = $dm;
        $ret['mens'] = 'Matricula de token '.$tm.' não foi encontrada';
        $ret['color'] = 'danger';
        //listar contrato
        if(!$dm){
            return $ret;
        }
        $id = isset($dm['id']) ? $dm['id'] : '';
        if($id){
            $contratos = $this->contatos_estaticos_pdf($id,true,$dm);
        }else{
            $contratos = false;
        }
        $enviar = false;
        if(isset($contratos[0]['meta_value']) && ($link_c = $contratos[0]['meta_value'])){
            //link od ontrato de prestação ou seja o principal contrato
            $enviar = $this->enviar_envelope($tm,$dm,$link_c);
            if($enviar['exec'] == true){
                $ret['exec'] = true;
                //gravar o processamento em campo
                $ret['save_process'] = Qlib::update_matriculameta($id,'enviar_envelope',Qlib::lib_array_json($enviar));
                //removendo o primiero contrato da lista
                $n_cont = array_shift($contratos);
                $token_doc = isset($enviar['response']['token']) ? $enviar['response']['token'] : false;
                if($token_doc && is_array($n_cont)){
                    $ret['anexos'] = $this->enviar_contratos_anexos(false,false,$dm);
                }
            }
        }
        $ret['enviar'] = $enviar;

        //gravar historico do envio do orçamento
        if(isset($ret['exec'])){
            $post_id = isset($dm['id']) ? $dm['id'] : null;
            if($post_id){
                $ret['salv_hist'] = Qlib::update_postmeta($post_id,(new ZapsingController)->campo_processo,Qlib::lib_array_json($ret));
            }
        }
        // Log::info('send_to_zapSing:', $ret);
        return $ret;
    }
    /**
     * gera um array com os link dos contratos
     */
    public function enviar_contratos_anexos($contatos_anexos=false,$tm=false,$dm=false){
        if(!$dm && $tm){
            $dm = (new MatriculasController)->dm($tm);
        }
        $ret['exec'] = false;
        $ret['dm'] = $dm;
        $ret['mens'] = 'Matricula não encontrada';
        $ret['color'] = 'danger';
        //listar contrato
        if(!$dm){
            return $ret;
        }
        $id = isset($dm['id']) ? $dm['id'] : '';
        if($id && !$contatos_anexos){
            $contatos_anexos = $this->contatos_estaticos_pdf($id,false,$dm);
        }else{
            $contatos_anexos = false;
        }
        // dd($contatos_anexos);
        if($contatos_anexos){
            //conseguir o token do contrato principal
            $denv_p = Qlib::get_matriculameta($id,'enviar_envelope');
            $ret['exec'] = false;
            $arr = [];
            if($denv_p){
                $arr = Qlib::lib_json_array($denv_p);
                // dd($arr);
                $token_envelope = isset($arr['response']['token']) ? $arr['response']['token'] : false;
                if($token_envelope && is_array($contatos_anexos)){
                    $zp = new ZapsingController;
                    $lastKey = array_key_last($contatos_anexos); // Obtém a última chave
                    foreach($contatos_anexos As $k=>$v){
                        $link = isset($v['meta_value']) ? $v['meta_value'] : false;
                        if ($k === $lastKey) {
                            // echo "$value (último elemento)\n";
                            $nome_arquivo = isset($v['meta_key']) ? $v['meta_key'] : false;
                        } else {
                            // echo "$value\n";
                            $arr_n = explode('/', $link);
                            $nome_arquivo = str_replace('-',' ',end($arr_n));
                        }
                        $nome = ucwords($nome_arquivo);
                        // dump($token_envelope,$link,$nome);
                        $ret['anexo'][$k] = $zp->enviar_anexo($token_envelope,$link,$nome);
                        if(isset($ret['anexo'][$k]['exec']))
                            $ret['exec'] = true;
                    }

                }
            }
            return $ret;
        }

    }

    /**
     * metodos que retorna o link de todos os contratos estaticos em pdf salvos no servidor de acordo com o id da matricula
     * @param string $id o id da matricula
     * @param bool $todos true para listar todos e false para remover o primeiro item que é o contrato de prestação de serviços que é o principal
     */
    public function contatos_estaticos_pdf($id,$todos=true,$dm=false){
        $dc = Qlib::dados_tab('matriculameta',['where'=>"WHERE matricula_id='$id' AND meta_key LIKE '%_pdf%' ORDER BY id ASC"]);
        if(!$todos && isset($dc[0])){
            unset($dc[0]);
        }
        //incluir o mgr Manual geral de regara para o curso caso tenha
        $id_curso = isset($dm['id_curso']) ? $dm['id_curso'] : false;//token matricula
        $token_curso = Qlib::buscaValorDb0('cursos','id',$id_curso,'token');
        $incluir_mgr_assinatura = Qlib::qoption('incluir_mgr_assinatura');
        if($incluir_mgr_assinatura=='s'){
            $dmgr = Qlib::dados_tab('arquivos_pdf',['where'=>"WHERE id_produto='$token_curso' AND ordem='1'"]);
            if(isset($dmgr[0]['endereco']) && ($link = $dmgr[0]['endereco'])){
                $link = str_replace('https://aeroclubejf','https://crm.aeroclubejf',$link);
                // return $dmgr;
                $title = isset($dmgr[0]['title']) ? $dmgr[0]['title'] : 'Manual de regras';
                $arr = [
                    'meta_key'=> $title,
                    'meta_value'=>$link,
                ];
                array_push($dc,$arr);
                // dump($id_curso,$dmgr);
            }
        }

        return $dc;
    }
	/**
	 * Salva todas as etapas de aceitação do contrato de periodos do plano de formação
	 */
	public function assinar_proposta_periodo($config){
		$ret['exec'] = false;
		$ret['valida']['mens'] = false;
		//salvar conteudo da página 2
		if(isset($config['token_matricula']) && isset($config['meta']) && is_array($config['meta'])){
			//11 o id da etapa 'Proposta aprovada' do flow de atendimento
			$config['id'] = $this->get_id_by_token($config['token_matricula']);
			// $ret['validar'] = $this->valida_respostas_assinatura_periodo($config['token_matricula'],'token');
			$ret['save'] = $this->sava_meta_fields($config);
			if($ret['save']['exec']){
				if(isset($config['arr_periodo'])){
					$ret['exec'] = true;
					//variavel que grava uma strig contendo o codigo que array do periodo proveniente do formulario gerando no metodo $this->formAceitoPropostaPeriodo
					$arr_periodo = Qlib::decodeArray($config['arr_periodo']);
					$token_periodo = isset($arr_periodo['token']) ? $arr_periodo['token'] : '';
					//gravar contrato estatico...
					$ret['gravar_copia'] = $this->grava_contrato_statico_periodo($config['token_matricula'],$token_periodo);
					$ret['nextPage'] = Qlib::qoption('dominio').'/solicitar-orcamento/proposta/'.$config['token_matricula'].'/a/'.$token_periodo;
					//Enviar para zapsing
                    // lib_print($arr_periodo);
					// lib_print($ret);
					// dd($config);
				}
			}else{
				$ret['exec'] = false;
				$ret['mens'] = 'Erro ao validar as respostas do termo';
			}

		}
		return $ret;
	}
    /**
     * para listar todos os contrato de um curso
     */
    public function lista_contratos($id_curso,$tm=false,$dm=false){
        if($tm && !$dm){
            $dm = $this->dm($tm);
        }
        $dtermo = Qlib::dados_tab('conteudo_site',['where'=>"WHERE ativo='s' AND id_curso='".$id_curso."' AND tipo_conteudo='9' AND ".Qlib::compleDelete()." ORDER BY ordenar ASC"]);
        return $dtermo;
    }
    /**
	 * Metodo para gravar os contratos estaticos na assinatura inicial da proposta
	 * @uso $ret = (new MatriculaController)->grava_contrato_statico($token_matricula);
	 * @param
	 */
	public function grava_contrato_statico($token_matricula,$type=1){
		$configCn['token'] = $token_matricula;
		// $shoc1 = 'contrato1';$shoc2 = 'contrato2';
		$shoc1 = 'contrato_matricula';$shoc2 = 'contrato_financiamento_horas';
        $ret['exec']=false;
        $dm = $this->dm($token_matricula);
        $id_curso = isset($dm['id_curso']) ? $dm['id_curso'] : false;
        $id_matricula = isset($dm['id']) ? $dm['id'] : false;
        if(!$id_curso){
            $ret['mens'] = 'Matrícula não encontrada!';
            return $ret;
        }
        if($type==1){
            $lista_contratos = $this->lista_contratos($id_curso);
        }else{
            $lista_contratos = false;
        }
        if(is_array($lista_contratos)){
            foreach ($lista_contratos as $km => $vm) {
                $contrato = isset($vm['obs']) ? $vm['obs'] : '';
                if(!empty($vm['obs'])){
                    $libera = true;
                    if($vm['short_code'] == 'contrato2' || $vm['short_code'] == 'contrato_financiamento_horas'){
                        $verifica_contrato_financeiro = Qlib::get_matriculameta($id_matricula,$this->campo_contrato_financeiro ,true);
                        if($verifica_contrato_financeiro == 's'){
                            $libera = true;
                        }else{
                            $libera = false;
                        }
                    }
                    if($libera){
                        $contrato = $this->contrato_matricula(false,$dm,$contrato);
                        $dados = [
                            'html'=>$contrato,
                            'nome_aquivo_savo'=>$vm['nome'],
                            'titulo'=>$vm['nome'],
                            'id_matricula'=>$dm['id'],
                            'token'=>$dm['token'],
                            'short_code'=>$vm['short_code'],
                            'pasta'=>'contratos',
                            'f_exibe'=>'server',
                        ];
                        // echo $contrato.'<br>';
                        $ret['grav_pdf'][$km] = (new PdfGenerateController )->convert_html($dados);
                        // $salv = Qlib::update_matriculameta($dm['id'],$km,base64_encode($contrato));
                        // converter em pdf
                        // $ret['ds'][$km]=$salv;
                        if($ret['grav_pdf'][$km]['exec']){
                            $ret['exec']=$ret['grav_pdf'][$km]['exec'];
                        }
                    }
                }else{
                    // dump($dm,$km);
                    if(Qlib::delete_matriculameta($dm['id'],$km)){
                        $ret['exec']=true;
                        $ret['ds'][$km]=$vm;
                    }
                    if(Qlib::delete_matriculameta($dm['id'],$km.'_pdf')){
                        $ret['exec']=true;
                        $ret['grav_pdf'][$km]=$vm;
                    }
                }

            }
        }else{
            $configCn['tipo_retorno'] = 2;

            $conteudo = $this->contratoAero($configCn,$dm,$shoc1);//app/cursos
            $contrato_financeiro = $this->contratoAero($configCn,$dm,$shoc2);//app/cursos   //contrato_financiamento_horas
            $contrato_combustivel = $this->contratoAero($configCn,$dm,'contrato_combustivel');//app/cursos   //contrato_financiamento_horas
            // $ger_cont = $this->termo_concordancia(['token'=>$token,'type'=>$opc],$dm);
            $termo_concordancia = $this->termo_concordancia($configCn);//app/cursos   //contrato_financiamento_horas
            $configCn['type'] = 'termo_escola_voo';
            $termo_concordancia_escola_voo = $this->termo_concordancia($configCn);//app/cursos
            $configCn['type'] = 'termo_antecipacao_combustivel';
            $termo_antecipacao_combustivel = $this->termo_concordancia($configCn);//app/cursos   //contrato_financiamento_horas
            // dump($termo_concordancia,$termo_concordancia_escola_voo,$termo_antecipacao_combustivel);
            // dd($conteudo,$contrato_financeiro,$contrato_combustivel);
            $contrato_prestacao = $conteudo;
            $arr_salv = [
                'contrato_prestacao'=>$contrato_prestacao,
                'contrato_combustivel'=>$contrato_combustivel,
                'contrato_financeiro'=>$contrato_financeiro,
                'termo_concordancia'=>$termo_concordancia,
                'termo_concordancia_voo'=>$termo_concordancia_escola_voo,
            ];
            if(isset($termo_antecipacao_combustivel['contrato']) && $termo_antecipacao_combustivel['contrato'] ){
                //gravar contrato de antecipação de combustivel apenas para cursos fora do plano de formação
                if(@$dm['tipo_curso']!=4){
                    $arr_salv['termo_antecipacao_combustivel'] = $termo_antecipacao_combustivel['contrato'];
                }
            }
            if(!$dm){
                $ret['mens'] = 'Matricula não encontrada!';
                return $ret;
            }
            foreach ($arr_salv as $km => $vm) {
                $contrato = isset($vm['contrato']) ? $vm['contrato'] : '';
                if(!empty($vm['contrato'])){
                    $contrato=trim($contrato);
                    $dados = [
                        'html'=>$contrato,
                        'nome_aquivo_savo'=>$vm['nome_arquivo'],
                        'titulo'=>$vm['nome_arquivo'],
                        'id_matricula'=>$dm['id'],
                        'token'=>$dm['token'],
                        'short_code'=>$km,
                        'pasta'=>'contratos',
                        'f_exibe'=>'server',
                    ];
                    $ret['grav_pdf'][$km] = (new PdfGenerateController )->convert_html($dados);
                    $salv = Qlib::update_matriculameta($dm['id'],$km,base64_encode($contrato));
                    //converter em pdf
                    $ret['ds'][$km]=$salv;
                    if($salv){
                        $ret['exec']=true;

                    }
                }else{
                    // dump($dm,$km);
                    if(Qlib::delete_matriculameta($dm['id'],$km)){
                        $ret['exec']=true;
                        $ret['ds'][$km]=$vm;
                    }
                    if(Qlib::delete_matriculameta($dm['id'],$km.'_pdf')){
                        $ret['exec']=true;
                        $ret['grav_pdf'][$km]=$vm;
                    }
                }
            }
        }
		return $ret;
	}
    /**
	 * Metodo gravar os contratos estaticos do periodo do plano de formação
	 * @uso $ret = (new Orcamentos)->grava_contrato_statico_periodo($token_matricula,$token_periodo);
	 * @param string $token_matricula, strim $token_periodo
	 */
	public function grava_contrato_statico_periodo($token_matricula,$token_periodo=false){
		$configCn['token'] = base64_encode($token_matricula);
		$configCn['periodo'] = $token_periodo;
		// $token_periodo = isset($arr_periodo['token'])?$arr_periodo['token']:'';
		$arr_periodo = $this->get_periodo_array($token_matricula,'token',$token_periodo);
		$ret['exec']=false;
		if(!isset($arr_periodo['periodo'])){
			return $ret;
		}
		$link_periodo = isset($arr_periodo['periodo']) ? $arr_periodo['periodo'] : '1° periodo';
		$link_periodo = str_replace(' ','_',$link_periodo);
		$dm = $this->dm($token_matricula);
		if($dm){
			$dm=$dm[0];
		}else{
			return $ret;
		}
		$cont = 'contrato_' . $token_periodo;
		$id_matricula = $this->get_id_by_token($token_matricula);
		$json_contrato = Qlib::get_matriculameta($id_matricula,$cont);
		$arr_cont = Qlib::lib_json_array($json_contrato);
		$arr_salv = [];
		if(isset($arr_cont['aceito'])){
			$arr_salv = $arr_cont['aceito'];
		}
		foreach ($arr_salv as $km => $vm) {
			$vm=trim($vm);
			$meta_key = $km.'_'.$token_periodo;
			if(!empty($vm) && $vm=='on'){
				//acha a chave do contrato
				if($km == 'termo_periodo'){
					$contrato = 'contrato_'.$link_periodo;
				}else{
					$contrato = $km;
				}
				// echo $contrato.'<br> ';
				if($km=='contrato_combustivel'){
					$contr = $this->contratoAero($configCn,$dm,$km);
				}else{
					$configCn['type'] = $contrato;
					$contr = $this->termo_concordancia($configCn,$dm);
				}
				// if(is_sandbox()){
				// 	lib_print(var_dump($vm)) ."<br>";
				// }
				// lib_print($contr);
				if(isset($contr['contrato']) && !empty($contr['contrato']) && ($c=$contr['contrato'])){
					$salv = Qlib::update_matriculameta($dm['id'],$meta_key,base64_encode($c));
					$ret['ds'][$km]=$salv;
					if($salv){
						$ret['exec']=true;

					}
				}
			}
		}
		return $ret;
	}
    /**
	 * Metodo que informa se contrato tem fiador
	 * @param string $token_matricula
	 * @return boolean true|false
	 */
	public function tem_fiador($token_matricula,$returnArray=false){
		$list = Qlib::buscaValorDb($GLOBALS['tab12'],'token',$token_matricula,'fiador');
		$ret = false;
		if($list && $arr = Qlib::lib_json_array($list)){
			if(is_array($arr)){
				if($returnArray){
					$ret = $arr;
				}else{
					$ret = true;
				}
			}
		}
		return $ret;
	}
    /**
     * Metodo para baixar o arquivo assinado de um oraçmento baixar em um diretorio padrão de oraçamento
     * @param string $token
     */
    public function baixar_arquivo($token,$url,$nome_arquivo=false,$slug=false){
        // $url = "https://zapsign.s3.amazonaws.com/sandbox/dev/2024/12/pdf/72d30d89-da1f-4e10-9025-3689b03ef3d4/7a773057-05d3-4843-be1d-0fe6bffdb730.pdf?AWSAccessKeyId=AKIASUFZJ7JCTI2ZRGWX&Signature=oRLj2PALoDs1JEkx%2FHm4TV1ZM%2BQ%3D&Expires=1734026017";
        $num=null;
        $nome_arquivo = $nome_arquivo?$nome_arquivo:'assinado';
        $nome_arquivo = Qlib::createSlug($nome_arquivo);
        $caminhoSalvar = 'pdfs/termos/'.$token.'/'.$nome_arquivo.'.pdf';
        if(Storage::exists($caminhoSalvar)){
            $num='-'.time();
        }
        $caminhoSalvar = 'pdfs/termos/'.$token.'/'.$nome_arquivo.$num.'.pdf';
        $ret = Qlib::download_file($url,$caminhoSalvar);
        $ret['url'] = $url;
        $ret['token'] = $token;
        $post_id = Qlib::get_matricula_id_by_token($token);
        if($ret['exec']){
            $link = Storage::url($caminhoSalvar);
            $ret['link'] = $link;
            if($slug){
                $ret['salv'] = Qlib::update_matriculameta($post_id,$slug,Qlib::lib_array_json(['link'=>$link,'data'=>Qlib::dataLocal()]));
            }
        }
        return $ret;
    }

}
