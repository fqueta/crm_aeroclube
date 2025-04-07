<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AsaasController extends Controller
{
	public $Api_key;
	public $url;
    public $campo_cad_asaas;
    public $campo_meta_status;
    public $campo_meta_resumo;
	public function __construct(){
		$this->credenciais();
        $this->campo_meta_status = Qlib::qoption('meta_pago') ? Qlib::qoption('meta_pago') : 'pago';
        $this->campo_meta_resumo = Qlib::qoption('meta_resumo_pagamento') ? Qlib::qoption('meta_resumo_pagamento') : 'resumo_pagamento';
    }
	public function credenciais(){
		$this->Api_key = Qlib::qoption('token-asaas');
		$this->url = Qlib::qoption('url-asaas');//Produção : https://www.asaas.com
		$this->campo_cad_asaas = 'id_asaas';//campo para salvar id do cliente no asaas
    }
	public function webhook(){
		$ret['exec'] = false;
		@header("Content-Type: application/json");
		$json = file_get_contents('php://input');

			//echo $json = '{"event":"PAYMENT_CONFIRMED","payment":{"object":"payment","id":"pay_166785911972","dateCreated":"2020-06-02","customer":"cus_000013982310","installment":"ins_000001578134","value":25,"netValue":23.96,"originalValue":null,"interestValue":null,"description":"Parcela 1 de 10. Livox na Pr\u00e1tica","billingType":"CREDIT_CARD","confirmedDate":"2020-06-02","creditCard":{"creditCardNumber":"4616","creditCardBrand":"MASTERCARD"},"status":"CONFIRMED","dueDate":"2020-06-02","originalDueDate":"2020-06-02","paymentDate":null,"clientPaymentDate":"2020-06-02","invoiceUrl":"https://www.asaas.com/i/166785911972","invoiceNumber":"32282255","externalReference":"5ed6d121ecdec","deleted":false,"anticipated":false,"creditDate":null,"estimatedCreditDate":"2020-07-02","bankSlipUrl":null,"lastInvoiceViewedDate":"2020-06-02T22:22:45Z","lastBankSlipViewedDate":null,"postalService":false}}';
			if(!empty($json)){
                $arr_resp = json_decode($json,true);
                $payk='id';
				if(isset($arr_resp['event']) && $arr_resp['event']=='PAYMENT_RECEIVED' && isset($arr_resp['payment']['externalReference']) && ($token=$arr_resp['payment']['externalReference'])){
                    $post_id = Qlib::get_id_by_token($token);

                    $ret['salvarResumo'] = Qlib::update_postmeta($post_id,$this->campo_meta_resumo,$json);
                    $ret['salvarStatus'] = Qlib::update_postmeta($post_id,$this->campo_meta_status,'s');
                    if($ret['salvarResumo'] && $ret['salvarStatus']){
                        $ret['exec'] = true;
                    }
                    // return $post_id;
                }
                return $ret;
            }
            // if(!empty($json)){
			// 	$arr_resp = json_decode($json,true);
			// 	return $arr_resp;
            //     $payk='id';
			// 	$dadosFatura = dados_tab($GLOBALS['lcf_entradas'],'token,categoria,id,parcela,ref_compra',"WHERE token='".$arr_resp['payment'][$payk]."'");
			// 	if(!$dadosFatura){
			// 		$payk='externalReference';
			// 		$dadosFatura = dados_tab($GLOBALS['lcf_entradas'],'token,categoria,id,parcela,ref_compra',"WHERE token='".$arr_resp['payment'][$payk]."'");
			// 	}
			// 	if($arr_resp['event']=='PAYMENT_RECEIVED' && $arr_resp['payment'][$payk]){
			// 		$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".precoDbdase($arr_resp['payment']['netValue'])."', `pago`='s',
			// 		`data_pagamento`= '".$arr_resp['payment']['paymentDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."'
			// 		WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 		// dd($ret['sql']);
			// 			// dd($ret['sql']);
			// 		$ret['exec'] = salvarAlterar($ret['sql']);
			// 		if($ret['exec']){
			// 			// dd($arr_resp);
			// 			if($dadosFatura){
			// 				$ret['categoriaMatricula'] = $GLOBALS['categoriaMatricula'];
			// 				$ret['categoriaMensalidade'] = $GLOBALS['categoriaMensalidade'];
			// 				$ret['dadosF'] = $dadosFatura;
			// 				$categoria = $dadosFatura[0]['categoria'];
			// 				$tokenMatricula = $dadosFatura[0]['ref_compra'];
			// 				if(($categoria == $GLOBALS['categoriaMatricula'] || $categoria == $GLOBALS['categoriaMensalidade']) && !empty($tokenMatricula)){
			// 					$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 					if($statusAtual == 1){
			// 						$status = 2;
			// 					}else{
			// 						$status = $statusAtual;
			// 					}
			// 					$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'pagamento_boleto');
			// 					//ENVIAR EMAIL COM CONFIRMAÇÃO DE PAGAMENTO E ACESSO AO CONTEUDO
			// 					$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email

			// 				}else{
			// 					if($arr_resp['payment']['billingType']=='BOLETO' || $arr_resp['payment']['billingType']=='PIX'){
			// 						if($dadosFatura[0]['parcela']==1){
			// 							$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 							if($statusAtual == 1){
			// 								$status = 3;
			// 							}else{
			// 								$status = $statusAtual;
			// 							}
			// 							$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'pagamento_boleto');
			// 							//ENVIAR EMAIL COM CONFIRMAÇÃO DE PAGAMENTO E ACESSO AO CONTEUDO
			// 							$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email
			// 						}
			// 					}
			// 				}
			// 			}else{
			// 				echo 'Erro: Fatura não encontrada';
			// 			}
			// 			echo json_encode($ret);exit;
			// 		}
			// 	}elseif(($arr_resp['event']=='RECEIVED_IN_CASH' || $arr_resp['event']=='PAYMENT_RECEIVED')&& $arr_resp['payment'][$payk]){
			// 		$arr_resp['payment']['netValue'] = str_replace(',','.',$arr_resp['payment']['netValue']);
			// 		$arr_resp['payment']['value'] = str_replace(',','.',$arr_resp['payment']['value']);

			// 		$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".$arr_resp['payment']['netValue']."', `pago`='s',
			// 		`data_pagamento`= '".$arr_resp['payment']['paymentDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."'
			// 			WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 		$ret['exec'] = salvarAlterar($ret['sql']);
			// 		if($ret['exec']){
			// 			// $dadosFatura = dados_tab($GLOBALS['lcf_entradas'],'token,categoria,id,parcela,ref_compra',"WHERE token='".$arr_resp['payment'][$payk]."'");
			// 			if($dadosFatura){
			// 				$categoria = $dadosFatura[0]['categoria'];
			// 				$tokenMatricula = $dadosFatura[0]['ref_compra'];
			// 				if($categoria == $GLOBALS['categoriaMatricula'] && !empty($tokenMatricula)){
			// 					$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 					if($statusAtual == 1){
			// 						$status = 2;
			// 					}else{
			// 						$status = $statusAtual;
			// 					}
			// 					$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'via_site');
			// 					//ENVIAR EMAIL COM CONFIRMAÇÃO DE PAGAMENTO E ACESSO AO CONTEUDO
			// 					$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email
			// 				}else{
			// 					if($arr_resp['payment']['billingType']=='BOLETO'){
			// 						if($dadosFatura[0]['parcela']==1){
			// 							$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 							if($statusAtual == 1){
			// 								$status = 3;
			// 							}else{
			// 								$status = $statusAtual;
			// 							}
			// 							$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'via_site');
			// 							//ENVIAR EMAIL COM CONFIRMAÇÃO DE PAGAMENTO E ACESSO AO CONTEUDO
			// 							$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email
			// 						}
			// 					}
			// 				}
			// 			}else{
			// 				echo 'Erro: Fatura não encontrada';
			// 			}
			// 			echo json_encode($ret);exit;
			// 		}
			// 	}elseif($arr_resp['event']=='PAYMENT_CONFIRMED' && $arr_resp['payment'][$payk]){
			// 		$arr_resp['payment']['netValue'] = str_replace(',','.',$arr_resp['payment']['netValue']);
			// 		$arr_resp['payment']['value'] = str_replace(',','.',$arr_resp['payment']['value']);
			// 		if(isset($arr_resp['payment']['billingType'])){
			// 			if($arr_resp['payment']['billingType']=='CREDIT_CARD'){
			// 				$forma_pagamento = 3;
			// 				$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".$arr_resp['payment']['netValue']."', `valor`='".$arr_resp['payment']['value']."', `pago`='s',`data_pagamento`= '".$arr_resp['payment']['confirmedDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."' WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 			}elseif($arr_resp['payment']['billingType']=='BOLETO'){
			// 				$forma_pagamento = 3;
			// 				$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".$arr_resp['payment']['netValue']."', `valor`='".$arr_resp['payment']['value']."', `pago`='s',`data_pagamento`= '".$arr_resp['payment']['paymentDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."' WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 			}else{
			// 				$forma_pagamento = 3;
			// 				$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".$arr_resp['payment']['netValue']."', `valor`='".$arr_resp['payment']['value']."', `pago`='s',`data_pagamento`= '".$arr_resp['payment']['paymentDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."' WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 			}
			// 		}else{
			// 			$forma_pagamento = 3;
			// 			$ret['sql'] = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `valor_pago`='".$arr_resp['payment']['netValue']."', `valor`='".$arr_resp['payment']['value']."', `pago`='s',`data_pagamento`= '".$arr_resp['payment']['paymentDate']."',reg_asaas = '".json_encode($arr_resp['payment'],JSON_UNESCAPED_UNICODE)."' WHERE `token`='".$arr_resp['payment'][$payk]."'";
			// 		}
			// 		$ret['resposta'] =  'pagamento recebido';
			// 		$ret['exec'] = salvarAlterar($ret['sql']);
			// 		if($ret['exec']){
			// 			//$dadosFatura = dados_tab($GLOBALS['lcf_entradas'],'token,categoria,id,parcela,ref_compra',"WHERE token='".$arr_resp['payment'][$payk]."'");
			// 			if($dadosFatura){
			// 				$categoria = $dadosFatura[0]['categoria'];
			// 				$tokenMatricula = $dadosFatura[0]['ref_compra'];
			// 				if($categoria == $GLOBALS['categoriaMatricula'] && !empty($tokenMatricula)){
			// 					$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 					if($statusAtual == 1){
			// 						$status = 2;
			// 					}else{
			// 						$status = $statusAtual;
			// 					}
			// 					$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'via_site');
			// 					//ENVIAR EMAIL COM CONFIRMAÇÃO DE PAGAMENTO E ACESSO AO CONTEUDO
			// 					$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email
			// 				}else{
			// 					if($arr_resp['payment']['billingType']=='BOLETO'){
			// 						if($dadosFatura[0]['parcela']==1){
			// 							$statusAtual = buscaValorDb($GLOBALS['tab12'],'token',$tokenMatricula,'status');
			// 							if($statusAtual == 1){
			// 								$status = 3;
			// 							}else{
			// 								$status = $statusAtual;
			// 							}
			// 							$ret['sqlMatricula'] = registrarMatriculaSYS($tokenMatricula,$status,'via_site');
			// 							$ret['emailLinkCurso'] = emailLinkCurso(['valor'=>$tokenMatricula]); //app/email
			// 						}
			// 					}
			// 				}
			// 			}else{
			// 				$ret['mens'] = 'Erro: Fatura não encontrada';
			// 			}
			// 			$ret['resEAD']='chegou';
			// 			echo json_encode($ret);exit;
			// 		}

			// 	}
			// }

			echo json_encode($ret);
	}
	public function cunsultarClienteEmail($confi=false){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/customers?email=".$confi['email']."&cpfCnpj=&externalReference=&offset=&limit=");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "access_token: ".$this->Api_key.""
			));

			$response = curl_exec($ch);
			curl_close($ch);
			return json_decode($response,true);
	}
	public function cunsultarClienteDocumento($confi=false){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/customers?cpfCnpj=".$confi['cpfCnpj']."&externalReference=&offset=&limit=");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "access_token: ".$this->Api_key.""
			));

			$response = curl_exec($ch);
			curl_close($ch);
			return json_decode($response,true);
	}
	public function cunsultarCliente($confi=false){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/customers/".$confi['id_cliente_asaas']."");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Content-Type: application/json",
			  "access_token: ".$this->Api_key.""
			));

			$response = curl_exec($ch);
			curl_close($ch);

			var_dump($response);
	}
	public function localizarPagamento($id_pagamento){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/payments?installment=".$id_pagamento."&limit=12");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Content-Type: application/json",
			  "access_token: ".$this->Api_key.""
			));

			$response = curl_exec($ch);
			curl_close($ch);

			return json_decode($response,true);
	}
    /**
     * Metodo para integrar os pagamentos faz as requisições com a api para forma de pagamento cartão pix boleto
     * @param array
     * @return array
     */
	public function integraCompraAsaas($confi=false){
        //uso
        // $conf=[
        //     'compra' => [
        //         'token' =>'64b0769e47002',
        //         'id_cliente' =>6,
        //         'valor' =>'',
        //         'forma_pagamento' =>'cred_card',
        //         'descricao' =>'Pagamento de leilao 14',
        //     ],
        //     'cartao' => [
        //         'valor' =>'02X25.36',
        //         'nome_no_cartao' =>'Jose Onifarsio',
        //         'numero_cartao' =>'000000000000000000001',
        //         'validade_mes' =>'05',
        //         'validade_ano' =>'2025',
        //         'codigo_seguranca' =>'123',
        //     ],
        //     // 'responsavel' => [],
        // ];
        //$ret = (new AsaasController)->integraCompraAsaas($conf);
		// dd($confi);
        $ret['exec'] = false;
		$dadosCompra = false;
		$ret['mens'] = Qlib::formatMensagemInfo('Erro ao efetuar pagamento!','danger',40000);
		$confiv = array();
		$dadosCliCad=false;
        $token = isset($confi['compra']['token']) ? $confi['compra']['token'] : false;
        $forma_pagamento = isset($confi['compra']['forma_pagamento']) ? $confi['compra']['forma_pagamento'] : false;
        $valor = isset($confi['compra']['valor'])?$confi['compra']['valor']:0; //valor total da compra
        // if($valor>5000){
        //     //condição para teste enquando não liberar valores maiores no assas
        //     $valor = 1000.52;
        // }
        $id_asaas = false;
		$description = isset($confi['compra']['descricao'])?$confi['compra']['descricao']: Qlib::buscaValorDb0('posts','token',$token,'post_title');
		//$confi['cartao']['valor'] = 10X52,30 total da compra e a parcela tbm
        $user_id = isset($confi['compra']['id_cliente']) ? $confi['compra']['id_cliente'] : Auth::id();
		if($token){
            if(isset($forma_pagamento)){
                if($user_id){
                    $dadosCliCad = $this->cadastrarCliente(['id_cliente'=>$user_id],true);
                    if(isset($dadosCliCad['cad_asaas']['id'])){
                        $id_asaas = $dadosCliCad['cad_asaas']['id'];
                    }
                }
                if(isset($dadosCliCad['cad_asaas']['errors'][0]['description']) && ($mes=$dadosCliCad['cad_asaas']['errors'][0]['description'])){
                    $ret['mens'] = Qlib::formatMensagemInfo($mes,'danger');
                    $ret['cad_asaas']=$dadosCliCad['cad_asaas'];
                    return $ret;
                }
                if($forma_pagamento=='cred_card' && $id_asaas){
                    if(isset($dadosCliCad['exec'])){
                        $confiv['customer'] = isset($confi['customer']) ? $confi['customer'] : $id_asaas; //formato: uniqid
                        $confiv['dueDate']	= isset($confi['dueDate']) ? $confi['dueDate'] 	: date('Y-m-d');
                        $confiv['value']	= isset($confi['value']) ? $confi['value'] 	: $valor;
                        $pagamento = explode("X",$confi['cartao']['valor']);
                        $confiv['installmentCount']	= isset($confi['installmentCount']) ? $confi['installmentCount'] : (int)$pagamento[0];
                        $installment = number_format((double)$pagamento[1],2,'.','');
                        $confiv['installmentValue']	= isset($confi['installmentValue']) ? $confi['installmentValue'] : Qlib::precoDbdase($installment);
                        //$description = 'Compra do curso '.buscaValorDb($GLOBALS['tab10'],'id',$dadosCompra[0]['id_curso'],'nome');
                        $confiv['description']= isset($confi['description']) ? $confi['description']											 	: $description;
                        $confiv['externalReference']= isset($confi['externalReference']) ? $confi['externalReference'] : $token;
                        $confiv['creditCard']['holderName']	= isset($confi['creditCard']['holderName']) ? $confi['creditCard']['holderName'] 	: $confi['cartao']['nome_no_cartao'];
                        $confiv['creditCard']['number']	= isset($confi['creditCard']['number']) ? $confi['creditCard']['number'] 					: str_replace(' ','',$confi['cartao']['numero_cartao']);
                        $confiv['creditCard']['expiryMonth']= isset($confi['creditCard']['expiryMonth']) ? $confi['creditCard']['expiryMonth']	: $confi['cartao']['validade_mes'];
                        $confiv['creditCard']['expiryYear']	= isset($confi['creditCard']['expiryYear']) ? $confi['creditCard']['expiryYear']			: $confi['cartao']['validade_ano'];
                        $confiv['creditCard']['ccv']= isset($confi['creditCard']['ccv']) ? $confi['creditCard']['ccv']								: $confi['cartao']['codigo_seguranca'];
                        $confiv['creditCardHolderInfo']['name']	= isset($confi['creditCardHolderInfo']['name']) ? $confi['creditCardHolderInfo']['name']: $confi['cartao']['nome_no_cartao'];
                        if(isset($confi['cartao']['dono']) && $confi['cartao']['dono'] =='outro' && isset($confi['responsavel'])){
                            $confiv['creditCardHolderInfo']['email']= isset($confi['creditCardHolderInfo']['email']) ? $confi['creditCardHolderInfo']['email']	: $confi['responsavel']['Email'];
                            $confiv['creditCardHolderInfo']['cpfCnpj']	= isset($confi['creditCardHolderInfo']['cpfCnpj']) ? $confi['creditCardHolderInfo']['cpfCnpj']	: str_replace('.','',$confi['responsavel']['Cpf']);
                            $confiv['creditCardHolderInfo']['addressNumber']= isset($confi['creditCardHolderInfo']['addressNumber']) ? $confi['creditCardHolderInfo']['addressNumber']	: $confi['responsavel']['Numero'];
                            $confiv['creditCardHolderInfo']['addressComplement'] = isset($confi['creditCardHolderInfo']['addressComplement']) ? $confi['creditCardHolderInfo']['addressComplement']	: $confi['responsavel']['Compl'];
                            $confiv['creditCardHolderInfo']['phone'] = isset($confi['creditCardHolderInfo']['phone']) ? $confi['creditCardHolderInfo']['phone']	: $dadosCliCad['cad_asaas']['phone'];
                            $confiv['creditCardHolderInfo']['postalCode'] = isset($confi['creditCardHolderInfo']['postalCode']) ? $confi['creditCardHolderInfo']['postalCode']	: $confi['responsavel']['Cep'];
                            $celular = str_replace('(','',$confi['responsavel']['Celular']);
                            $celular = str_replace(')','',$celular);
                            $celular = str_replace('-','',$celular);
                            $confiv['creditCardHolderInfo']['mobilePhone'] = isset($confi['creditCardHolderInfo']['mobilePhone']) ? $confi['creditCardHolderInfo']['mobilePhone']	: $celular;
                        }else{
                            $confiv['creditCardHolderInfo']['email']= isset($confi['creditCardHolderInfo']['email']) ? $confi['creditCardHolderInfo']['email']	: $dadosCliCad['cad_asaas']['email'];
                            $confiv['creditCardHolderInfo']['cpfCnpj']= isset($confi['creditCardHolderInfo']['cpfCnpj']) ? $confi['creditCardHolderInfo']['cpfCnpj']	: $dadosCliCad['cad_asaas']['cpfCnpj'];
                            $confiv['creditCardHolderInfo']['addressNumber']= isset($confi['creditCardHolderInfo']['addressNumber']) ? $confi['creditCardHolderInfo']['addressNumber']	: $dadosCliCad['cad_asaas']['addressNumber'];
                            $confiv['creditCardHolderInfo']['addressComplement'] = isset($confi['creditCardHolderInfo']['addressComplement']) ? $confi['creditCardHolderInfo']['addressComplement']	: $dadosCliCad['cad_asaas']['complement'];
                            $confiv['creditCardHolderInfo']['phone'] = isset($confi['creditCardHolderInfo']['phone']) ? $confi['creditCardHolderInfo']['phone']	: $dadosCliCad['cad_asaas']['phone'];
                            $confiv['creditCardHolderInfo']['postalCode'] = isset($confi['creditCardHolderInfo']['postalCode']) ? $confi['creditCardHolderInfo']['postalCode']	: $dadosCliCad['cad_asaas']['postalCode'];
                            $confiv['creditCardHolderInfo']['mobilePhone'] = isset($confi['creditCardHolderInfo']['mobilePhone']) ? $confi['creditCardHolderInfo']['mobilePhone']	: $dadosCliCad['cad_asaas']['mobilePhone'];
                        }
                        $confiv['creditCardHolderInfo']['cpfCnpj'] = str_replace('.','',$confiv['creditCardHolderInfo']['cpfCnpj']);
                        $confiv['creditCardHolderInfo']['cpfCnpj'] = str_replace('-','',$confiv['creditCardHolderInfo']['cpfCnpj']);
                        $confiv['creditCardHolderInfo']['postalCode'] = str_replace('-','',$confiv['creditCardHolderInfo']['postalCode']);
                        $criarCobrancaCartao = $this->criarCobrancaCartao($confiv);
                        $ret['criarCobrancaCartao'] = $criarCobrancaCartao;
                        $ret['confiv'] = $confiv;

                        // dd($ret);
                        if(isset($ret['criarCobrancaCartao']['asaas']['id'])){
                            $ret['exec'] = true;
                            $ret['token'] = $token;
                            $ret['mens'] = Qlib::formatMensagemInfo('Pagamento Efetuado com sucesso!','success');
                            $resPagamento = json_encode($ret['criarCobrancaCartao']['asaas']);
                            $post_id = Qlib::get_id_by_token($token);

                            $ret['salvarResumo'] = Qlib::update_postmeta($post_id,$this->campo_meta_resumo,$resPagamento);
                            $ret['salvarStatus'] = Qlib::update_postmeta($post_id,$this->campo_meta_status,'s');
                        }elseif(isset($ret['criarCobrancaCartao']['asaas']['errors'][0]['description']) && ($mes=$ret['criarCobrancaCartao']['asaas']['errors'][0]['description'])){
                            $ret['mens'] = Qlib::formatMensagemInfo($mes,'danger');
                        }
                    }else{
                        $ret['mens'] = Qlib::formatMensagemInfo('Forma de pagamento não selecionada!','danger');
                    }
                }
                if(($forma_pagamento=='boleto' || $forma_pagamento== 'pix') && $id_asaas){
                    $fp = 'BOLETO';
                    if($forma_pagamento=='pix'){
                        $filderPayCallback = 'criarCobrancaPix';
                        $fp = 'PIX';
                        $vencimento = date('d/m/Y');
                    }else{
                        $prazo_boleto = Qlib::qoption('prazo_boleto') ? Qlib::qoption('prazo_boleto') : 1;
                        $vencimento = isset($confi['compra']['Vencimento']) ? $confi['compra']['Vencimento'] : Qlib::CalcularVencimento2(date('d/m/Y'),$prazo_boleto);
                        $filderPayCallback = 'criarCobrancaBoleto';
                    }
                    if(isset($dadosCliCad['exec'])){
                        $confic['customer'] 		= isset($confi['customer']) ? $confi['customer'] 	: $id_asaas; //formato: uniqid
                        $confic['billingType']	= isset($confi['billingType']) ? $confi['billingType'] 	: $fp;
                        $confic['dueDate']	 = isset($confi['dueDate']) ? $confi['dueDate']	: Qlib::dtBanco($vencimento);
                        $confic['value']	= isset($confi['compra']['valor']) ? $confi['compra']['valor']	: 0;
                        // if(!$confic['value']){
                        //     $vl = (double)$dadosCurso[0]['valor'];$ins=(double)$dadosCurso[0]['inscricao'];
                        //     $confic['value'] =  Qlib::precoDbdase($vl+ $ins);
                        //     // Qlib::lib_print($dadosCurso[0]);
                        //     // dd($confic);
                        // }
                        //$confic['installmentCount']= isset($confi['installmentCount']) ? $confi['installmentCount'] 							: false;
                        //$confic['installmentValue']= isset($confi['installmentValue']) ? $confi['installmentValue'] 								: false;
                        // $description = false;
                        // $categoriaCurso = buscaValorDb($GLOBALS['tab10'],'id',$dadosCompra[0]['id_curso'],'categoria');
                        // if($categoriaCurso != 'cursos_online'){
                        //     $description = 'Matrícula ';
                        // }
                        // $description .= buscaValorDb($GLOBALS['tab10'],'id',$dadosCompra[0]['id_curso'],'nome');
                        $confic['description'] = isset($confi['description']) ? $confi['description']: $description;
                        //$confic['externalReference']										= isset($confi['externalReference']) ? $confi['externalReference'] 						: $confi['compra']['token'];
                        $confic['externalReference']= $token;
                        $confic['fine'] = isset($confi['multa']) ? $confi['multa'] : array('value'=>0); //Informações de multa para pagamento após o vencimento
                        $confic['interest'] = isset($confi['juros']) ? $confi['juros'] : array('value'=>0,'dueDateLimitDays'=>0); //Informações de multa para pagamento após o vencimento
                        $confic['value'] = number_format((double)$confic['value'],2,'.','');
                        // dd($confic);
                        if($confi['compra']['forma_pagamento']=='pix'){
                            $ret[$filderPayCallback] = $this->cobrancaPix($confic);
                        }else{
                            $ret[$filderPayCallback] = json_decode($this->criarCobrancaBoleto($confic),true);
                        }
                        if(isset($ret[$filderPayCallback]['object']) || (@$ret[$filderPayCallback]['exec'])){
                            $ret['exec'] = true;
                            $ret['token'] = $token;
                            $ret['mens'] = Qlib::formatMensagemInfo('Pagamento Efetuado com sucesso!','success');
                            $resPagamento = json_encode($ret[$filderPayCallback]);
                            // $resPagamento = json_encode($ret['criarCobrancaCartao']['asaas']);
                            $post_id = Qlib::get_id_by_token($token);
                            $ret['salvarResumo'] = Qlib::update_postmeta($post_id,$this->campo_meta_resumo,$resPagamento);
                            $ret['salvarStatus'] = Qlib::update_postmeta($post_id,$this->campo_meta_status,'a');

                            // $urlUpd = "UPDATE ".$GLOBALS['tab12']." SET `pagamento_asaas`='".$resPagamento."' WHERE token='".$dadosCompra[0]['token']."'";
                            // if(isAdmin(1) || isset($_GET['fq'])){
                            //     $ret['urlUpd'] = $urlUpd;
                            // }
                            // $ret['salvarResumo'] = salvarAlterar($urlUpd);
                            // $ret['urlUpd'] = salvarAlterar($urlUpd);
                        }elseif(isset($ret[$filderPayCallback]['errors'][0]['description'])){
                            $ret['mens'] = Qlib::formatMensagemInfo($ret[$filderPayCallback]['errors'][0]['description'],'danger');
                        }
                        $ret['confic'] = $confic;
                    }
                }
            }
			// $confi['token_compra'] = trim($token);
			// $sqlCompra = "SELECT * FROM ".$GLOBALS['tab12']. " WHERE `token`='".$confi['token_compra']."' AND ".compleDelete();
			// $dadosCompra = buscaValoresDb($sqlCompra);
			// if($dadosCompra){
			// 	$dadosCurso = dados_tab($GLOBALS['tab10'],'*',"WHERE id='".$dadosCompra[0]['id_curso']."'");
			// 	if(isset($dadosCompra[0]['id_cliente']) && $dadosCurso){
			// 		$dadosCliCad = $this->cadastrarCliente($dadosCompra[0]);
			// 		$ret['dadosCliCad'] = $dadosCliCad;

			// 	}
			// }
		}
		if(Qlib::qoption('debug_front')=='s'){
			$ret['confi'] = $confi;
			// $ret['sqlCompra'] = $sqlCompra;
			$ret['dadosCompra'] = $dadosCompra;
			$ret['dadosCliCad'] = $dadosCliCad;
			//$ret['confiv'] = $confiv;
		}
		return $ret;
	}
	public function credCardTest(){
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,  $this->url."/api/v3/payments");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);

		curl_setopt($ch, CURLOPT_POST, TRUE);

		curl_setopt($ch, CURLOPT_POSTFIELDS, "{
		  \"customer\": \"cus_000001608111\",
		  \"billingType\": \"CREDIT_CARD\",
		  \"dueDate\": \"2019-08-08\",
		  \"value\": 100,
		  \"description\": \"Pedido 056984\",
		  \"externalReference\": \"056984\",
		  \"creditCard\": {
			\"holderName\": \"marcelo h almeida\",
			\"number\": \"5162306219378829\",
			\"expiryMonth\": \"05\",
			\"expiryYear\": \"2021\",
			\"ccv\": \"318\"
		  },
		  \"creditCardHolderInfo\": {
			\"name\": \"Marcelo Henrique Almeida\",
			\"email\": \"marcelo.almeida@gmail.com\",
			\"cpfCnpj\": \"24971563792\",
			\"postalCode\": \"89223-005\",
			\"addressNumber\": \"277\",
			\"addressComplement\": null,
			\"phone\": \"4738010919\",
			\"mobilePhone\": \"47998781877\"
		  }
		}");

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  "Content-Type: application/json",
		  "access_token: ".$this->Api_key
		));

		$response = curl_exec($ch);
		curl_close($ch);

		return json_decode($response,true);
	}
	public function criarCobrancaCartao($confi=false){
			$confic['customer'] = isset($confi['customer']) ? $confi['customer'] 													: 'cus_000000331531'; //formato: uniqid
			$confic['billingType'] = 'CREDIT_CARD';
			$confic['dueDate'] = isset($confi['dueDate']) ? $confi['dueDate'] 														: uniqid();
			$confic['value'] = isset($confi['value']) ? $confi['value'] 																: 0;
			$confic['installmentCount'] = isset($confi['installmentCount']) ? $confi['installmentCount'] 							: false;
			$confic['installmentValue'] = isset($confi['installmentValue']) ? $confi['installmentValue'] 								: false;
			$confic['description'] = isset($confi['description']) ? $confi['description']											 	: false;
			$confic['externalReference'] = isset($confi['externalReference']) ? $confi['externalReference'] 						: false;
			$confic['creditCard']['holderName'] = isset($confi['creditCard']['holderName']) ? $confi['creditCard']['holderName'] 	: false;
			$confic['creditCard']['number'] = isset($confi['creditCard']['number']) ? $confi['creditCard']['number'] 					: false;
			$confic['creditCard']['expiryMonth'] = isset($confi['creditCard']['expiryMonth']) ? $confi['creditCard']['expiryMonth']	: false;
			$confic['creditCard']['expiryYear'] = isset($confi['creditCard']['expiryYear']) ? $confi['creditCard']['expiryYear']	: false;
			$confic['creditCard']['ccv'] = isset($confi['creditCard']['ccv']) ? $confi['creditCard']['ccv']	: false;
			$confic['creditCardHolderInfo']['name'] = isset($confi['creditCardHolderInfo']['name']) ? $confi['creditCardHolderInfo']['name']	: false;
			$confic['creditCardHolderInfo']['email'] = isset($confi['creditCardHolderInfo']['email']) ? $confi['creditCardHolderInfo']['email']	: false;
			$confic['creditCardHolderInfo']['postalCode'] = isset($confi['creditCardHolderInfo']['postalCode']) ? $confi['creditCardHolderInfo']['postalCode']	: null;
			//$confic['creditCardHolderInfo']['postalCode'] 				= '89223-005';
			$confic['creditCardHolderInfo']['cpfCnpj'] = isset($confi['creditCardHolderInfo']['cpfCnpj']) ? $confi['creditCardHolderInfo']['cpfCnpj']	: false;
			$confic['creditCardHolderInfo']['addressNumber'] = isset($confi['creditCardHolderInfo']['addressNumber']) ? $confi['creditCardHolderInfo']['addressNumber']	: null;
			$confic['creditCardHolderInfo']['addressComplement'] = isset($confi['creditCardHolderInfo']['addressComplement']) ? $confi['creditCardHolderInfo']['addressComplement']	: null;
			$confic['creditCardHolderInfo']['phone'] = isset($confi['creditCardHolderInfo']['phone']) ? $confi['creditCardHolderInfo']['phone']	: $confi['creditCardHolderInfo']['mobilePhone'];
			$confic['creditCardHolderInfo']['mobilePhone'] = isset($confi['creditCardHolderInfo']['mobilePhone']) ? $confi['creditCardHolderInfo']['mobilePhone']	: null;

			$json_confic = json_encode($confic,JSON_UNESCAPED_UNICODE);//exit;
			$ret['confic'] = $confic;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/payments");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json_confic);
			/*curl_setopt($ch, CURLOPT_POSTFIELDS, "{
				  \"customer\": \"cus_000001608111\",
				  \"billingType\": \"CREDIT_CARD\",
				  \"dueDate\": \"2019-08-08\",
				  \"value\": 1295,
				  \"installmentCount\": 2,
				  \"installmentValue\": 647.5,
				  \"description\": \"Pedido 056984\",
				  \"externalReference\": \"056984\",
				  \"creditCard\": {
					\"holderName\": \"marcelo h almeida\",
					\"number\": \"5162306219378829\",
					\"expiryMonth\": \"05\",
					\"expiryYear\": \"2021\",
					\"ccv\": \"318\"
				  },
				  \"creditCardHolderInfo\": {
					\"name\": \"Marcelo Henrique Almeida\",
					\"email\": \"marcelo.almeida@gmail.com\",
					\"cpfCnpj\": \"24971563792\",
					\"postalCode\": \"89223-005\",
					\"addressNumber\": \"277\",
					\"addressComplement\": null,
					\"phone\": \"4738010919\",
					\"mobilePhone\": \"47998781877\"
				  }
				}");*/

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Content-Type: application/json",
			  "access_token: ".$this->Api_key.""
			));

			$response = curl_exec($ch);
			curl_close($ch);

			$ret['asaas'] = json_decode($response,true);
			return $ret;
	}
	public function receberEmDinheiro($confi=false){
		$ret['exec'] = false;
		if(isset($confi['id_fatura'])){
			$reg_asaas = buscaValorDb($GLOBALS['lcf_entradas'],'id',$confi['id_fatura'],'reg_asaas');
			if(!empty($reg_asaas)){
				$reg_asaas = json_decode($reg_asaas,true);
			}else{
				$ret['mens'] = formatMensagem('Dados na fatura não encontrados','danger',40000);
			}
			if(is_array($reg_asaas)){
				$confic['paymentDate']		= isset($confi['data_pagamento']) ? $confi['data_pagamento'] 	: date('Y-d-m');
				$confic['value']					= isset($confi['valor_pago']) ? $confi['valor_pago'] 			: 1;
				$confic['notifyCustomer']	= isset($confi['notifyCustomer']) ? $confi['notifyCustomer'] : false;
				//id da fatura do lcf_entradas
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/payments/".$reg_asaas['id']."/receiveInCash");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HEADER, FALSE);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($confic,JSON_UNESCAPED_UNICODE));
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				  "Content-Type: application/json",
				  "access_token: ".$this->Api_key.""
				));
				$response = curl_exec($ch);
				curl_close($ch);
				$ret['pagar'] = json_decode($response,true);
				if(!isset($ret['pagar']['errors'])){
					$ret['exec'] = salvarAlterar("UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `reg_asaas`='' WHERE id='".$confi['id_fatura']."' ");
				}
			}else{
				$ret['mens'] = formatMensagem('Erro ao converter dados','danger',40000);
			}
		}else{
			$ret['mens'] = formatMensagem('Fatura não informada','danger',40000);
		}
		return $ret;
	}
	public function removerCobranca($confi=false){
		$ret['exec'] = false;
		if(is_adminstrator(1)){
				$ret['confi'] = $confi;
		}
		if(isset($confi['id_fatura'])){
			$reg_asaas = buscaValorDb($GLOBALS['lcf_entradas'],'id',$confi['id_fatura'],'reg_asaas');
			if(is_adminstrator(1)){
				$ret['reg_asaas'] = $reg_asaas;
			}
			if(!empty($reg_asaas)){
				$reg_asaas = json_decode($reg_asaas,true);
			}else{
				$ret['mens'] = formatMensagem('Dados na fatura não encontrados','danger',40000);
			}
			if(is_array($reg_asaas)){
				//id da fatura do lcf_entradas
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/payments/".$reg_asaas['id']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HEADER, FALSE);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				  "Content-Type: application/json",
				  "access_token: ".$this->Api_key.""
				));

				$response = curl_exec($ch);
				curl_close($ch);
				$ret['deletar'] = json_encode($response);
				if(isset($ret['deletar']['deleted']))
					$ret['exec'] = true;
				//$ret['atualizar_regAsaas'] = salvarAlterar("UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `reg_asaas`='' WHERE id='".$confi['id_fatura']."' ");
			}else{
				$ret['mens'] = formatMensagem('Erro ao converter dados','danger',40000);
			}
		}else{
			$ret['mens'] = formatMensagem('Fatura não informada','danger',40000);
		}
		return $ret;
	}
	public function criarPagamentoLCF($confi=false){
			$ret['exec'] = false;
			$ret['mens'] = false;
			$confi['type'] = !empty($confi['type']) ? $confi['type'] : 1; //controla a quantidade de geração 1 ou todas
			if($confi['type']=='todas' && isset($confi['token_matricula'])){
				$compleSql = "ref_compra = '".$confi['token_matricula']."'  AND pago ='n' AND reg_asaas=''";
			}
			if($confi['type']==1){
				$compleSql = "id = '".$confi['id']."' AND pago ='n'";
			}
			if($confi['id']){
				$sqlCob = "SELECT * FROM ".$GLOBALS['lcf_entradas']." WHERE $compleSql";
				$dadosCob = buscaValoresDb($sqlCob);
				//echo $confi['type'];
				//Qlib::lib_print($dadosCob);exit;
				if($dadosCob){
					foreach($dadosCob As $key=>$val){
							if($val['id_cliente']>0){
								$id_asaas = buscaValorDb($GLOBALS['tab15'],'id',$val['id_cliente'],'id_asaas');
								if(empty($id_asaas)){
									$ret['cadastrarCliente'] = $this->cadastrarCliente($val);
									//print_r($ret['cadastrarCliente']);
									//exit;
								}
								$sqlCliente = "SELECT * FROM ".$GLOBALS['tab15']." WHERE id = '".$val['id_cliente']."' ";
								$dadosCli = buscaValoresDb($sqlCliente);
								$ret['sqlCliente']=$sqlCliente;
								if(!empty($val['reg_asaas'])){
										$reg_asaas = json_decode($val['reg_asaas'],true);
										if(isset($ret['reg_asaas']['errors'][0])){
											//if($ret['reg_asaas']['errors'][0]['code']=='invalid_dueDate'){
												$ret['limpar_regAsaas'] = salvarAlterar("UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `reg_asaas`='' WHERE id='".$val['id']."' ");
											//}
										}
										if(isset($reg_asaas['id'])){
											$confic['id'] = $reg_asaas['id'];
										}
								}
								if($dadosCli && $val['pago']=='n'){
										$confic['customer'] 													= isset($confi['customer']) ? $confi['customer'] 													: $dadosCli[0]['id_asaas']; //formato: uniqid
										$confic['billingType']			 										= isset($confi['billingType']) ? $confi['billingType'] 												: 'BOLETO';
										$confic['dueDate']			 											= isset($confi['dueDate']) ? $confi['dueDate'] 														: $val['vencimento'];
										$confic['value']				 											= isset($confi['value']) ? $confi['value'] 																: $val['valor'];
										//$confic['installmentCount']											= isset($confi['installmentCount']) ? $confi['installmentCount'] 							: false;
										//$confic['installmentValue']											= isset($confi['installmentValue']) ? $confi['installmentValue'] 								: false;
										$confic['description']													= isset($confi['description']) ? $confi['description']											 	: strip_tags($val['descricao'].' '.$val['obs']);
										$confic['externalReference']										= isset($confi['externalReference']) ? $confi['externalReference'] 						: $val['token'];
										$confic['fine'] = isset($confi['multa']) ? $confi['multa'] : array('value'=>0); //Informações de multa para pagamento após o vencimento
										$confic['interest'] = isset($confi['juros']) ? $confi['juros'] : array('value'=>0,'dueDateLimitDays'=>0); //Informações de multa para pagamento após o vencimento
										$response = $this->criarCobrancaBoleto($confic);

										$ret['geraBoleto'] = json_decode($response,true);
										if(isset($ret['geraBoleto']['id'])){
											$sqlUpd = "UPDATE IGNORE ".$GLOBALS['lcf_entradas']." SET `reg_asaas`='".$response."',`forma_pagameto`='3',token='".$ret['geraBoleto']['id']."' WHERE id='".$val['id']."' ";
											$ret['sqlUpd'] = $sqlUpd;
											$ret['salv_reg'] = salvarAlterar($sqlUpd);
											$btn_boleto = '<a href="'.$ret['geraBoleto']['bankSlipUrl'].'" target="_BLANK" class="btn btn-primary"><i class="fa fa-barcode"></i> Imprimir boleto</a>';;
											$compleMes = 'para imprimir o boleto é só abrir a fatura ou clique no botão ao lado '.$btn_boleto;
											$ret['mens'] .= formatMensagem('Boleto da fatura '.$val['id'].' Gerado com sucesso, '.$compleMes,'success',9000000);
											$ret['exec'] = true;
										}
										$ret['confic'] = $confic;
								}
							}
					}
				}else{
					$ret['mens'] = formatMensagem('Erro cobrança não encontrada','danger',40000);
				}
				if(is_adminstrator(1)){
					$ret['sqlCob']=$sqlCob;
					$ret['dadosCob']=$dadosCob;
					//$ret['dadosCli']=$dadosCli;
					//$ret['sqlCliente']=$sqlCliente;
					//$ret['sqlCliente']=$sqlCliente;
				}
			}
			return $ret;
	}
	public function reg_asaas($config=false){
		$ret['exec']=false;
		//echo $config['reg_asaas'].'<br>';
		if(!empty($config['reg_asaas'])){
			$config['reg_asaas'] = json_decode($config['reg_asaas'],true);
			if(is_array($config['reg_asaas'])){
				$type = isset($config['type']) ? $config['type'] : false;
				if($type=='status'){
					foreach($config['reg_asaas'] As $key=>$val){
							$ret['html'] = $config['reg_asaas'][$key];
							$ret['code'] = $config['reg_asaas'][$key];
					}
				}else{
					$ret['code'] = $config['reg_asaas'];
				}
			}
		}
		return $ret;
	}
	public function criarCobrancaBoleto($confic=false){
			/**Modelo de array para requesição
			$confic['customer'] 													= isset($confi['customer']) ? $confi['customer'] 													: $dadosCli[0]['id_asaas']; //formato: uniqid
			$confic['billingType']			 										= isset($confi['billingType']) ? $confi['billingType'] 												: 'BOLETO';
			$confic['dueDate']			 											= isset($confi['dueDate']) ? $confi['dueDate'] 														: $dadosCob[0]['vencimento'];
			$confic['value']				 											= isset($confi['value']) ? $confi['value'] 																: $dadosCob[0]['valor'];
			//$confic['installmentCount']											= isset($confi['installmentCount']) ? $confi['installmentCount'] 							: false;
			//$confic['installmentValue']											= isset($confi['installmentValue']) ? $confi['installmentValue'] 								: false;
			$confic['description']													= isset($confi['description']) ? $confi['description']											 	: strip_tags($dadosCob[0]['descricao']);
			$confic['externalReference']										= isset($confi['externalReference']) ? $confi['externalReference'] 						: $dadosCob[0]['token'];
			$confic['fine'] = isset($confi['multa']) ? $confi['multa'] : array('value'=>0); //Informações de multa para pagamento após o vencimento
			$confic['interest'] = isset($confi['juros']) ? $confi['juros'] : array('value'=>0,'dueDateLimitDays'=>0); //Informações de multa para pagamento após o vencimento
			**/
			if(isset($confic['id'])){
				$url = $this->url."/api/v3/payments/".$confic['id'];
				unset($confic['id']);
			}else{
				$url = $this->url."/api/v3/payments";
			}
			//echo $url;
			//exit;
			$response = false;
			if(isset($confic)){
				$jsondados = json_encode($confic,JSON_UNESCAPED_UNICODE);
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url );
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HEADER, FALSE);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondados);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					"Content-Type: application/json",
					"access_token:  ".$this->Api_key.""
				));
				$response = curl_exec($ch);
				curl_close($ch);
			}
			return $response;
	}
	public function cobrancaPix($confic=false){
		// dd($co)
		if(isset($confic['id'])){
			$url = $this->url."/api/v3/payments/".$confic['id'];
			unset($confic['id']);
		}else{
			$url = $this->url."/api/v3/payments";
		}
		$ret['exec'] = false;
		if(isset($confic)){
			$jsondados = json_encode($confic,JSON_UNESCAPED_UNICODE);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondados);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				"access_token:  ".$this->Api_key.""
			));
			$response = curl_exec($ch);
			curl_close($ch);
			$arr_resp = Qlib::lib_json_array($response);
			$ret['exec'] = false;
			$ret = $arr_resp;
			if(isset($arr_resp['object'])){
				$ret['exec'] = true;
			}
			if(isset($arr_resp['id'])){
				$ret['qrcode'] = $this->get_pix_qrcode($arr_resp['id']);
			}
		}
		return $ret;
	}
	/**Metodo para recuperar o qr code do cobrança pix */
	public function get_pix_qrcode($payment_id=false){
		$ret = false;
		if($payment_id){
			$url = $this->url."/api/v3/payments/{id}/pixQrCode";
			$url = str_replace('{id}',$payment_id,$url);

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => array(
					'access_token: '.$this->Api_key,
					"Content-Type: application/json",
				),
			));

			$response = curl_exec($curl);

			curl_close($curl);
			$ret = Qlib::lib_json_array($response);

		}
		return $ret;
	}
    /**
     * Mentodo para envidar o cadastro para o asaas
     * @param array $confi, float $confere=true para atualizar o cadastro no asaas
     */
	public function cadastrarCliente($confi=false,$confere=false){
			$ret = false;
			$ret['exec'] = false;
			//print_r($confi);
			if(isset($confi['id_cliente']) && !empty($confi['id_cliente'])){
					$dadosCli = User::Find($confi['id_cliente']);
					if($dadosCli){
                        $dadosCli = $dadosCli->toArray();
                        $id_cliente = $dadosCli['id'];
						$id_asaas = Qlib::get_usermeta($id_cliente,$this->campo_cad_asaas,true);
                        $ret['dadosCli'] = $dadosCli;
						$schemaAsaas = $this->schemaCustomerAsaas(false,$dadosCli);
                        $ret['schemaAsaas'] = $schemaAsaas;
                        $ret['id_asaas'] = $id_asaas;
						if((!$id_asaas) || ($id_asaas && empty($id_asaas))){
							$url = $this->url."/api/v3/customers";
							$ch = curl_init();

							curl_setopt($ch, CURLOPT_URL, $url );
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
							curl_setopt($ch, CURLOPT_HEADER, FALSE);

							curl_setopt($ch, CURLOPT_POST, TRUE);
							curl_setopt($ch, CURLOPT_POSTFIELDS, $schemaAsaas);

							curl_setopt($ch, CURLOPT_HTTPHEADER, array(
							  "Content-Type: application/json",
							  "access_token: ".$this->Api_key.""
							));

							$response = curl_exec($ch);
							curl_close($ch);
							$ret['cad_asaas'] = json_decode($response,true);
							if(isset($ret['cad_asaas']['id']) && !empty($ret['cad_asaas']['id'])){
                                $id_asaas = $ret['cad_asaas']['id'];
								$ret['exec'] = Qlib::update_usermeta($id_cliente,$this->campo_cad_asaas,$id_asaas);
							}
						}else{
							if($confere && $schemaAsaas){
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/customers/".$id_asaas);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
								curl_setopt($ch, CURLOPT_HEADER, FALSE);
								curl_setopt($ch, CURLOPT_POST, TRUE);
								curl_setopt($ch, CURLOPT_POSTFIELDS, $schemaAsaas);
								curl_setopt($ch, CURLOPT_HTTPHEADER, array(
								  "Content-Type: application/json",
								  "access_token: ".$this->Api_key.""
								));
								$response = curl_exec($ch);
								curl_close($ch);
								$ret['exec'] = true;
								$ret['cad_asaas'] = json_decode($response,true);
							}else{
								$ret['exec'] = true;
								$ret['cad_asaas'] = json_decode($schemaAsaas,true);
								$ret['cad_asaas']['id'] = $id_asaas;
							}
						}
					}
			}
			return $ret;
	}
	public function deletarCliente($id_cliente=false){
		$ret['exec'] = false;
		// $sql = "SELECT * FROM ".$GLOBALS['tab15']. " WHERE `id`= '".$id_cliente."' ";
		$dadosCli = User::Find($id_cliente);
		if($dadosCli){
			$schemaAsaas = $this->schemaCustomerAsaas(false,$dadosCli);
            $id_asaas = Qlib::get_usermeta($id_cliente,$this->campo_cad_asaas);
			if(!empty($id_asaas)){
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->url."/api/v3/customers/".$dadosCli[0]['id_asaas']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HEADER, FALSE);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_ENCODING, TRUE);
				curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
				curl_setopt($ch, CURLOPT_TIMEOUT, 0);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
				curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  				curl_setopt($ch, CURLOPT_POSTFIELDS, $schemaAsaas);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				  "Content-Type: application/json",
				  "access_token: ".$this->Api_key.""
				));
				$response = curl_exec($ch);
				curl_close($ch);
				$ret['resp_asaas'] = json_decode($response,true);
				if($ret['resp_asaas']['deleted']){
					$ret['exec'] = true;
				}
			}
		}
		return $ret;
	}
	public function schemaCustomerAsaas($id_cliente=false,$dadosCli=false,$json=true){
		$ret=false;
		if($id_cliente && !$dadosCli){
			$dadosCli = User::Find($id_cliente);
            if($dadosCli){
                $dadosCli = $dadosCli->toArray();
            }
        }
		if($dadosCli){
            $dadoEmpresa = Qlib::get_company_data();
            // dd($dadosCli);
            //dd($dadoEmpresa);
			// if($dadoEmpresa){
			// 	$dadosCli['endereco'] = isset($dadosCli['config']['endereco']) ? $dadosCli['config']['endereco'] : $dadoEmpresa['endereco'];
			// 	$dadosCli[0]['Numero'] = isset($dadosCli['config']['numero']) ? $dadosCli['config']['numero'] : $dadoEmpresa['numero'];
			// 	$dadosCli[0]['Compl'] = isset($dadosCli['config']['compl']) ? $dadosCli['config']['compl'] : $dadoEmpresa['compl'];
			// 	$dadosCli[0]['Bairro'] = isset($dadosCli['config']['bairro']) ? $dadosCli['config']['bairro'] : $dadoEmpresa['bairro'];
			// 	$dadosCli[0]['Cep'] = isset($dadosCli['config']['cep']) ? $dadosCli['config']['cep'] : $dadoEmpresa['cep'];
			// }
			$celular = str_replace('(','',@$dadosCli['config']['celular']);
			$celular = str_replace(')','',$celular);
			$celular = str_replace('-','',$celular);

			$telefone = str_replace('(','',@$dadosCli['config']['Tel']);
			$telefone = str_replace(')','',$telefone);
			$telefone = str_replace('-','',$telefone);

			$cep = str_replace('.','',@$dadosCli['config']['cep']);
			$cep = str_replace('-','',$cep);

			$pos = strpos($dadosCli['email'],',');
			if($pos===false){
				$em = explode(',',$dadosCli['email']);
				$email = $em[0];
				$emailAdicional = str_replace($em[0].',','',$dadosCli['email']);
			}else{
				$email = $dadosCli['email'];
				$emailAdicional = '';
			}

			$ret =
			[
				"name"=> $dadosCli['name'],
				"email"=> $email,
				"phone"=> $telefone,
				"mobilePhone"=> $celular,
				"cpfCnpj"=> $dadosCli['cpf'],
				"postalCode"=> $cep,
				"address"=> @$dadosCli['config']['endereco'],
				"addressNumber"=> @$dadosCli['config']['numero'],
				"complement"=> @$dadosCli['config']['compl'],
				"province"=> @$dadosCli['config']['bairro'],
				"externalReference"=> $dadosCli['id'],
				"notificationDisabled"=> false,
				"additionalEmails"=> $emailAdicional,
				"municipalInscription"=> "",
				"stateInscription"=> "",
			];
			if($json){
				$ret = Qlib::lib_array_json($ret);
			}
		}
		return $ret;
	}
	public function decodeEvent($event=false){
		$ret = false;
		if($event){
			$arrEvent = array(
				'PAYMENT_CREATED'=>'Geração de nova cobrança.',
				'PAYMENT_UPDATED'=>'Alteração no vencimento ou valor de cobrança existente.',
				'PAYMENT_CONFIRMED'=>'Cobrança confirmada (pagamento efetuado, porém o saldo ainda não foi disponibilizado).',
				'PAYMENT_RECEIVED'=>'Cobrança recebida.',
				'PAYMENT_OVERDUE'=>'Cobrança vencida.',
				'PAYMENT_DELETED'=>'Cobrança removida.',
				'PAYMENT_REFUNDED'=>'Cobrança extornada.',
				'PAYMENT_CHARGEBACK_REQUESTED'=>'Recebido chargeback.',
				'PAYMENT_CHARGEBACK_DISPUTE'=>'Em disputa de chargeback (caso sejam apresentados documentos para contestação).',
				'PAYMENT_AWAITING_CHARGEBACK_REVERSAL'=>'Disputa vencida, aguardando repasse da adquirente.',
			);
			$ret = $arrEvent[$event];
		}
		return $ret;
	}
	public function decodeStatus($status=false){
		$ret = false;
		if($status){
			$arrStatus = array(
				'PENDING'=>'Aguardando pagamento',
				'RECEIVED'=>'Recebida (saldo já creditado na conta)',
				'CONFIRMED'=>'Pagamento confirmado (saldo ainda não creditado)',
				'OVERDUE'=>'Vencida',
				'REFUNDED'=>'Estornada',
				'RECEIVED_IN_CASH'=>'Recebida em dinheiro (não gera saldo na conta)',
				'REFUND_REQUESTED'=>'Estorno Solicitado',
				'CHARGEBACK_REQUESTED'=>'Recebido chargeback',
				'CHARGEBACK_DISPUTE'=>'Em disputa de chargeback (caso sejam apresentados documentos para contestação)',
				'AWAITING_CHARGEBACK_REVERSAL'=>'Disputa vencida, aguardando repasse da adquirente',
			);
			$ret = $arrStatus[$status];
		}
		return $ret;
	}
}
