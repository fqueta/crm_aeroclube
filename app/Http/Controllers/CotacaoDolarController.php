<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;

class CotacaoDolarController extends Controller
{
    /**
     * executa o metodo cotacaoDolar para cotacao do dolar via api
     */
    // public function execute($config=false,$dm=false){

	// 	$ret['exec']=false;

	// 	if(isset($config['campo'])&&isset($config['valor_campo'])){

	// 		$sqlDadosMatricula = " WHERE ".$config['campo']."='".$config['valor_campo']."' AND ".compleDelete();

	// 		$dadosMaticula = dados_tab($GLOBALS['tab12'],'status,id,token,total,reg_pagamento,pagamento_asaas',$sqlDadosMatricula);

	// 		if($dadosMaticula[0]['status'] == 2){

	// 			$ret['dadosMaticula'] = $dadosMaticula;

	// 		}

	// 	}

	// 	return $ret;

	// }
    /**
     * Conecata com a api de cotação do dolar
     */
	public function cotacaoDolar($var = null)

	{

		/**

		 * $fin = new lcf_gerenteFinanceiro;

		 * $ret = $fin->cotacaoDolar();

		 * lib_print($ret);

		 */

		$ret['exec'] = false;

		$urlApi = 'https://economia.awesomeapi.com.br/json/last/USD-BRL';

		// $api = file_get_contents($urlApi);
		$arrContextOptions=array(
			"ssl"=>array(
				"verify_peer"=>false,
				"verify_peer_name"=>false,
			),
		);

		$api = file_get_contents($urlApi, false, stream_context_create($arrContextOptions));

		if(isset($_GET['fq'])){
			dd($api);
		}
		$hoje = date('Y-m-d');
		if($api){
			$infoContacao = false;
			$cache = Qlib::qoption('cotacao_atual');
			if($cache){
				$cache = Qlib::lib_json_array($cache);
			}

			if(isset($cache['data'])&&isset($cache['cota'])){
				$cotacao = $cache['cota'];
				$dt = isset($cotacao['USDBRL']['create_date'])?$cotacao['USDBRL']['create_date']:false;
				if($dt){
					$dtCt = explode(' ',$dt);
					if(isset($dtCt[0]) && strtotime($cache['data'])==strtotime($hoje)){
						$infoContacao = $cotacao;
					}
					if(isset($dtCt[1])){
						$ret['hora_contacao'] = $dtCt[1];
					}
				}
			}
			if($infoContacao){
				//lib_print($infoContacao);

				$ret['mens'] = Qlib::formatMensagem0('cotação ja realizada durante esta sessão','danger');

				$ret['json'] = Qlib::lib_array_json($infoContacao);

				$ret['arr'] = $infoContacao;
				$origem = 'cache';

			}else{

				$ret['exec'] = true;

				$ret['json'] = $api;

				$arr = Qlib::lib_json_array($api);
				$ret['arr'] = $arr;
				$origem = 'api';
				if(isset($arr['USDBRL']['create_date'])){

					$dt = explode(' ',$arr['USDBRL']['create_date']);

					//$_SESSION['cotacao_atual'] = $arr;
					// $cf2 = new config2;

					// $config = array('campo'=>'option_value','valor'=>Qlib::lib_array_json([
					// 	'cota'=>$arr,
					// 	'data'=>$hoje,
					// ]),'option_name'=>'cotacao_atual');
					// $ret['qoption_alt'] = $cf2->qoption_alt($config);
					$ret['qoption_alt'] = Qlib::update_config('cotacao_atual',Qlib::lib_array_json([
						'cota'=>$arr,
						'data'=>$hoje,
					]));

					/*

					if(isset($dt[0])){

						$data = [

							'data'

							]

						}*/

					}

				}

				if(isset($ret['arr']['USDBRL']['bid'])){

					$ret['cotacao']['valor'] = $ret['arr']['USDBRL']['bid'];

				}

				if(isset($ret['arr']['USDBRL']['create_date'])){

					$ret['cotacao']['data'] = Qlib::dataExibe($ret['arr']['USDBRL']['create_date']);
				}
				if(isset($origem)){
					$ret['cotacao']['origem'] = $origem;
				}

		}

		return $ret;

	}
}
