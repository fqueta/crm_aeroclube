<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\OrcamentoController;
use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RdstationController extends Controller
{
    public $url_padrao;
    public $token_api;
    public $version;
    public $origem_padrao;
    public function __construct(){
        $this->version = 'v1';
        $this->url_padrao = 'https://crm.rdstation.com/api/'.$this->version;
        $this->token_api = Qlib::qoption('token_usuario_rd');
        $this->origem_padrao = 'crm_zapguru';
    }
    /**
     * Verifica se o campos origem da negociação e a origem padrão da API ou seja igual a propriedade $this->origem_padrao.
     * @param array $rd um array contento a querystring da webhook do rdstation
     * @param bool $ret verdadeiro ou falso
     */
    public function is_defeult_origin($rd){
        $ret = false;
        if(is_string($rd)){
            $rd = Qlib::lib_json_array($rd);
        }
        if(isset($rd['deal_custom_fields']) && ($campos = $rd['deal_custom_fields'])){
            foreach ($campos as $k => $v) {
                if($v['label'] == 'Origem'){
                    if($v['value'] == $this->origem_padrao){
                        $ret = true;
                    }
                }
            }
        }
        return $ret;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id,$endpoint=null)
    {
        $url = $this->url_padrao.'/'.$endpoint.'?token='.$this->token_api;
        $url = str_replace('{id}',$id,$url);
        $response = Http::accept('application/json')->get($url);
        $ret['exec'] = false;
        if($response){
            $ret['exec'] = true;
            $ret['json'] = $response;
            $ret['url'] = $url;
            $ret['data'] = Qlib::lib_json_array($response);
        }
        return $ret;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    /**
     * para postagem de conteudo na api rd
     * @param string $endpoint
     * @param string $data é o body
     */
    public function post($endpoint=null,$data=[])
    {
        $url = $this->url_padrao.'/'.$endpoint.'?token='.$this->token_api;
        // $url = str_replace('{id}',$id,$url);
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post($url,$data);
        $ret['exec'] = false;
        if($response){
            $ret['exec'] = true;
            $ret['json'] = $response;
            $ret['data'] = Qlib::lib_json_array($response);
        }
        return $ret;
    }
    /**
     * Atualiza o cliente com a requisição do contato do RD station
     * @param array $config Array da consulta da API do Rd
     */
    public function atualiza_cliente($config=[]){

    }
    /**
     * Extrai o id de post webhook
     * @param array $config  essa é a carga de dados de uma webhook
     */
    public function get_id_by_webhook($config=[]){
        $event = isset($config['event_name']) ? $config['event_name'] : false;
        $id = null;
        if(!$event){
            return null;
        }
        if($event=='crm_deal_created'){
            $id = isset($config['document']['id']) ? $config['document']['id'] : null;
        }
        return $id;
    }
    /**
     * Extrai o id de post webhook
     * @param array $config  essa é a carga de dados de uma webhook
     */
    public function get_event_by_webhook($config=[]){
        $event = isset($config['event_name']) ? $config['event_name'] : false;
        return $event;
    }
    /**
     * Extrai o id de post webhook
     * @param array $config  essa é a carga de dados de uma webhook
     */
    public function get_deal_id($config=[]){
        $id = isset($config['document']['id']) ? $config['document']['id'] : null;
        return $id;
    }
    /**
     * Extrai o id de post webhook
     * @param array $config  essa é a carga de dados de uma webhook
     */
    public function get_user_id($config=[]){
        $id = isset($config['document']['user']['id']) ? $config['document']['user']['id'] : null;
        return $id;
    }
    /**
     * Extrai o id de post webhook
     * @param array $config  essa é a carga de dados de uma webhook
     */
    public function get_user_email($config=[]){
        $email = isset($config['document']['user']['email']) ? $config['document']['user']['email'] : null;
        return $email;
    }
    /**
     * Metodo para executar o webhook
     */
    public function webhook(){
        $ret['exec'] = false;
		@header("Content-Type: application/json");
		$json = file_get_contents('php://input');
        $d = [];
        if($json){
            $d = Qlib::lib_json_array($json);
            $event = $this->get_event_by_webhook($d);
            if($event=='crm_deal_created'){
                $id = $this->get_id_by_webhook($d);
                $dados_contato = $this->show($id,'/deals/{id}/contacts');
                $data = isset($dados_contato['data']['contacts']) ? $dados_contato['data']['contacts'] : false;
                if(is_array($data)){
                    $nomeCompleto = isset($data[0]['name']) ? $data[0]['name'] : '';
                    if (str_word_count($nomeCompleto) >= 2) {
                        // echo "Tem nome e sobrenome.";
                        $data[0]['name'] = $nomeCompleto;
                    } else {
                        // echo "Nome incompleto.";
                        $data[0]['name'] = isset($d['document']['name']) ? $d['document']['name'] : '';
                    }
                    $ret = $this->salvar_orcamento($data,$d,true);

                }else{
                    $ret['mens'] = 'Cliente não encontrado na base do RD';
                    $ret['dados_contato_RD'] = $dados_contato;
                }
            }
            // $save = Qlib::saveEditJson($d,'webhook_rd.json');
            Log::info('Webhook rdstation '.$event.':', $d);
        }
        // $ret['exec'] = false;
        return $ret;
    }
    /**
     * grava cadastro do cliente e faz a ponte para o cadastro do zaguru e a criação do orçamento da integração entre o Rd station e o crm e zapguru
     * @param array $conf_contato array com a requisição contendo os dados do contato do RD station
     * @param array $conf_neg array com a postatem da webhook contendo os dados da neçãociaão do Rd station
     * @return array $ret
     */
    public function salvar_orcamento($conf_contato=[],$conf_neg=[],$chat_inic=true){
        //salvar o cliente
        $config = isset($conf_contato[0])? $conf_contato[0] : [];
        $nome = isset($config['name']) ? $config['name'] : '';
        $email = isset($config['emails'][0]['email']) ? $config['emails'][0]['email'] : '';
        $telefonezap = isset($config['phones'][0]['phone']) ? $config['phones'][0]['phone'] : '';
        $telefonezap = str_replace('+', '', $telefonezap);
        $telefonezap = str_replace(' ', '', $telefonezap);
        $telefonezap = str_replace('(', '', $telefonezap);
        $telefonezap = str_replace(')', '', $telefonezap);
        $telefonezap = str_replace('-', '', $telefonezap);
        // return $config;
        // $data = [
            //     'Nome' => $nome,
            //     'Email' => $email,
            //     'telefonezap' => $telefonezap,
            //     'rdstation' => $config['id'],
            //     'rd_ultimo_negocio' => Qlib::lib_array_json($conf_neg),
            //     'token' => uniqid(),
            //     'EscolhaDoc' => 'CPF',
            // ];
            //antes de salver verifica se o campo personalizao está marcado como proveniente dessa integração
        $ret['exec'] = false;
        $user_idrd = $this->get_user_id($conf_neg);
        //pega id do usuario do rd gravado no cadastro de usuario.
        $user_email = $this->get_user_email($conf_neg);
        $user_idguru = '';
        if($user_email){
            $duser = Qlib::get_user_data("WHERE email='$user_email'");
            if(isset($duser['config']) && !empty($duser['config'])){
                $arr_con = Qlib::lib_json_array($duser['config']);
                $user_idguru = isset($arr_con['id_guru']) ? $arr_con['id_guru'] : '';
            }
        }
        $user_idguru = $user_idguru?$user_idguru: $this->user_guru_by_rd($user_idrd);
        if($this->is_defeult_origin($conf_neg)){
            //se for igual atualiza
            $data = [
                'rdstation' => $config['id'],
                'rd_ultimo_negocio' => Qlib::lib_array_json($conf_neg),
                'atualizado' => Qlib::dataLocalDb(),
            ];
            //dps ver a possibilidade a atualizar a tabela clientes tambem
            //Atualizar o campos rdstation e rd_ultimo_negocio com os valores equivalentes..
            $sc = (new ClientesController)->add_lead_update($data,"WHERE email = '$email'");

            $ret['update_lead'] = $sc;;
        }else{
            $data = [
                'nome' => $nome,
                'email' => $email,
                'celular' => $telefonezap,
                'rdstation' => $config['id'],
                'rd_ultimo_negocio' => Qlib::lib_array_json($conf_neg),
                'token' => uniqid(),
                'tag_origem' => 'rdstation',
                'excluido' => 'n',
                'deletado' => 'n',
                'atualizado' => Qlib::dataLocalDb(),
                // 'EscolhaDoc' => 'CPF',
            ];
            // $sc = (new ClientesController)->add_update($data);
            $sc = (new ClientesController)->add_lead_update($data);
            // return $sc;
            $ret['cad_cliente'] = $sc;
            $id_cliente = isset($sc['idCad']) ? $sc['idCad'] : null;
            //Criar chat zapguru
            $zg = new ZapguruController;
            if(isset($sc['exec']) && $telefonezap){
                $ret['exec'] = true;
                // return $ret;
                if($chat_inic){
                    //quanto adicionar o chatguru tem que retornar uma webhook do zapguru
                    $dialog_id = '679a438a9d7c8affe47e29b5'; //id do dialogo do chatguru para disparar uma webhook apos a inclusão do chat
                    $ret['criar_chat'] = $zg->criar_chat(array('telefonezap'=>$telefonezap,'cadastrados'=>true,'tab'=>'capta_lead','dialog_id'=>$dialog_id,'user_id'=>$user_idguru));
                }
            }
        }

        //criar orçamento...
        // if($id_cliente){
        //     $config_orc = [
        //         'id_cliente'=>$id_cliente,
        //     ];
        //     $ret = (new OrcamentoController)->add_update($config_orc);
        // }
        return $ret;
    }
    /**
     * Retorna o Id do guru quando informamos um ID o RD
     * @param int $id_rd Id do rdstation
     * @param bool $array caso não tenha um valor associoado determina se vai retornar um array ou um false;
     */
    public function user_guru_by_rd($id_rd=false,$array=false){
        //RD=>GURU
        $arr = [
            '678947e873759800146e7e00'=>'6798eeeae31fa81ff8b36e13', //Gabriela Fernandes gabriela@aero
            '6772e4b208ac280020350f38'=>'6245a60983df9a08527d0468', //Luiz Sanches luiza@aero
            '6772e80dce3f06001e7f2800'=>'65aeae656e862e1fbd58d9ff', //Jessica Fabri jessica@aero
        ];
        if($id_rd){
            if(isset($arr[$id_rd])){
                return $arr[$id_rd];
            }else{
                if($array){
                    return $arr;
                }else{
                    return false;
                }
            }
        }else{
            return $arr;
        }
    }
    /**
     * Retorna o array com dos dados de contato de uma negociação valida
     * @param string $deal_id id da negociação
     */
    public function get_contact($deal_id){
        $dados_contato = $this->show($deal_id,'/deals/{id}/contacts');
        return $dados_contato;
    }
    /**
     * Metodo para Criar uma anotação apartir de um cadastro de cliente
     * @param string $id_cliente
     * @param string $text texto da anotação
     */

    public function anota_por_cliente($id_cliente,$text,$tab='clientes'){
        $dc = Qlib::dados_tab($tab,['where'=>"WHERE id='$id_cliente'"]);
        $ret['exec'] = false;
        $rd = isset($dc[0]['rd_ultimo_negocio']) ? $dc[0]['rd_ultimo_negocio'] : false;
        if(!is_array($rd)){
            $rd = isset($dc['rd_ultimo_negocio']) ? $dc['rd_ultimo_negocio'] : false;
        }
        if(is_array($rd)){
            // return $this->get_deal_id($rd);
            $ret = $this->criar_anotacao([
                'text' => $text,
                'deal_id' => $this->get_deal_id($rd),
                'user_id' => $this->get_user_id($rd),
            ]);
        }else{
            $ret['mens'] = 'Negociação não encontrada na base de dados';
        }
        return $ret;
    }
    /**
     * Criar uma anotação
     * @param string $id_cliente
     */
    public function criar_anotacao($config=[]){
        $user_id = isset($config['user_id']) ? $config['user_id'] : null;
        $deal_id = isset($config['deal_id']) ? $config['deal_id'] : null;
        $text = isset($config['text']) ? $config['text'] : null;
        $endpoint = 'activities';
        $ret['exec'] = false;
        if($user_id && $deal_id && $text){
            $ret = $this->post($endpoint,[
                'activity'=>[
                    'user_id' => $user_id,
                    'deal_id' => $deal_id,
                    'text' => $text,
                ]
            ]);
        }
        return $ret;
    }
}
