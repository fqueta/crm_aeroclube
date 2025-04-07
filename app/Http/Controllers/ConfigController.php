<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConfigController extends Controller
{

    // function qoption_alt($config){

	// 	/*

	// 	$cf2 = new config2;

	// 	$config = array('campo'=>'','valor'=>'','option_name'=>'');

	// 	$ret = $cf2->qoption_alt($config);

	// 	*/

	// 	$ret = false;

	// 	$ret['mens'] =  formatMensagem("Erro: Ao salvar entre em contato com o suporte",'danger');

	// 	$config['campo']	= isset($config['campo'])?$config['campo']:'option_value';

	// 	$option_exist = totalReg($GLOBALS['tab0'],"WHERE option_name = '".$config['option_name']."'");

	// 	if($option_exist){

	// 		$url = "UPDATE ".$GLOBALS['tab0']." SET ".$config['campo']." = '".$config['valor']."' WHERE option_name ='".$config['option_name']."'";

	// 	}else{

	// 		$config['insert']['option_name']	= isset($config['option_name'])?$config['option_name']:false;

	// 		$config['insert'][$config['campo']]	= isset($config['valor'])?$config['valor']:false;

	// 		$config['insert']['autoload']		= isset($config['autoload'])?$config['autoload']:'yes';

	// 		$config['insert']['excluido']		= isset($config['excluido'])?$config['excluido']:'n';

	// 		$config['insert']['deletado']		= isset($config['deletado'])?$config['deletado']:'n';

	// 		$config['insert']['obs'] 			= isset($config['obs'])?$config['obs']:false;

	// 		$campos = false;

	// 		$vg = false;

	// 		foreach($config['insert'] As $k=>$v){

	// 			$campos .= "$k='$v',";

	// 		}

	// 		$campos = substr($campos, 0, -1);

	// 		$url = "INSERT INTO ".$GLOBALS['tab0']." SET $campos";



	// 	}



	// 	if(isset($config['option_name'])){

	// 		//if(isset($_GET['teste'])){



	// 				//echo $url;

	// 				//lib_print($config);

	// 				//exit;

	// 		//}

	// 		$alterar = salvarAlterar($url);

	// 		if($alterar){

	// 			$ret['dado_salvo'] =  buscaValorDb($GLOBALS['tab0'],'option_name',$config['option_name'],$config['campo']);

	// 			$ret['mens'] =  formatMensagem('Gravado com sucesso!!','success');

	// 			if(isset($config['opc'])){

	// 				if($config['opc'] == 'qoption_altIntegra'){

	// 					$configIn['redirect'] = '#janelaMens #cont';

	// 					$ret['list'] = conteudoPgIntegracao($configIn);

	// 				}

	// 			}

	// 		}

	// 	}

	// 	return json_encode($ret);

	// }

	// function alt_rapido_qoption($campo=false,$valor=false,$config=false){

	// 	$ret['exec'] = false;

	// 	if($campo&&$valor){

	// 		$ret['mens'] =  formatMensagem("Erro: Ao salvar entre em contato com o suporte",'danger');

	// 		$config['campo']	= isset($config['campo'])?$config['campo']:'option_value';

	// 		$config['valor']	= isset($config['valor'])?$config['valor']:$valor;

	// 		$config['option_name']	= isset($config['option_name'])?$config['option_name']:$campo;

	// 		$option_exist = totalReg($GLOBALS['tab0'],"WHERE option_name = '".$config['option_name']."'");

	// 		if($option_exist){

	// 			$url = "UPDATE ".$GLOBALS['tab0']." SET ".$config['campo']." = '".$config['valor']."' WHERE option_name ='".$config['option_name']."'";

	// 		}else{

	// 			$config['insert']['option_name']	= isset($config['option_name'])?$config['option_name']:false;

	// 			$config['insert'][$config['campo']]	= isset($config['valor'])?$config['valor']:false;

	// 			$config['insert']['autoload']		= isset($config['autoload'])?$config['autoload']:'yes';

	// 			$config['insert']['excluido']		= isset($config['excluido'])?$config['excluido']:'n';

	// 			$config['insert']['deletado']		= isset($config['deletado'])?$config['deletado']:'n';

	// 			$config['insert']['obs'] 			= isset($config['obs'])?$config['obs']:false;

	// 			$campos = false;

	// 			$vg = false;

	// 			foreach($config['insert'] As $k=>$v){

	// 				$campos .= "$k='$v',";

	// 			}

	// 			$campos = substr($campos, 0, -1);

	// 			$url = "INSERT INTO ".$GLOBALS['tab0']." SET $campos";



	// 		}

	// 		if(isset($config['option_name'])){

	// 			$alterar = salvarAlterar($url);

	// 			if($alterar){

	// 				$ret['exec'] = true;



	// 				$ret['dado_salvo'] =  buscaValorDb($GLOBALS['tab0'],'option_name',$config['option_name'],$config['campo']);

	// 				$ret['mens'] =  formatMensagem('Gravado com sucesso!!','success');

	// 			}

	// 		}

	// 	}

	// 	return $ret;

	// }
}
