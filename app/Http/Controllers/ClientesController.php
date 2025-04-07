<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;

class ClientesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public $tab;
    public function __construct()
    {
        $this->tab = 'clientes';
    }
    /**
     * adicion ou atualiza um cliente
     * @param array $dados array com os campos e valores que serão gravados no bando dedados
     * @param string $where com as string SQL de condição para atualização do registro no banco de dados
     */
    public function add_update($dados=[],$where=''){
        $tab = $this->tab;
        //tornar unico pelo email por padrão
        $where = $where ? $where : false;
        if(empty($where) && isset($dados['Email']) && !empty($dados['Email'])){
            $where = "WHERE Email='".$dados['Email']."'";
        }
        $ret = Qlib::update_tab($tab,$dados,$where);
        return $ret;
    }
    /**
     * adicion ou atualiza um lead
     * @param array $dados array com os campos e valores que serão gravados no bando dedados
     * @param string $where com as string SQL de condição para atualização do registro no banco de dados
     */
    public function add_lead_update($dados=[],$where=''){
        $tab = 'capta_lead';
        //tornar unico pelo email por padrão
        $where = $where ? $where : false;
        if(empty($where) && isset($dados['email']) && !empty($dados['email'])){
            $where = "WHERE email='".$dados['email']."'";
        }
        $ret = Qlib::update_tab($tab,$dados,$where);
        return $ret;
    }
    /**Para cover um lead em cliente e vice versa
     * @param array $config = ['id'=>285,'type'=>'lc']    type = lc é leads em cliente $type = cl é cliente em lead
     */
    static public function convert_LeadCliente($config = null)

	{

		//$type = lc é leads em cliente $type = cl é cliente em lead

		//$ret = (new ClientesController)->convert_LeadCliente(['id'=>285,'type'=>'lc']);

		$ret['exec']=false;

		$id = isset($config['id'])?$config['id']:false;

		$type = isset($config['type'])?$config['type']:false;
		$campo_bus = isset($config['campo_bus'])?$config['campo_bus']:'id';
		$campo_bus = isset($config['campo_bus'])?$config['campo_bus']:'id';
    	if($id){
    		$arr_convert = [
    			'nome'=>'Nome',
    			'email'=>'Email',
    			'celular'=>'telefonezap',
    			'obs'=>'tag',
    			'token'=>'token',
    		];
    		$tab_l = $GLOBALS['tab88'];
    		$tab_c = $GLOBALS['tab15'];
    		if($type=='lc'){
    			$lead = Qlib::dados_tab($tab_l,['campos'=>'*','where'=>"WHERE $campo_bus='$id'"]);
    			$ret['lead'] = $lead;
    			if($lead){
    				$dadosForm =  $lead[0];
    				$cond_valid = isset($config['cond_valid'])?$config['cond_valid']:"WHERE token='".$dadosForm['token']."'";
    				$type_alt = 1;
    				$tabUser = $tab_c;
    				foreach ($arr_convert as $k => $v) {
    					if($k=='nome'){
    						$n = explode(' ',trim($dadosForm['nome']));
    						if(isset($n[1])){
    							$dadosForm['Nome'] = $n[0];
    							$dadosForm['sobrenome'] = trim(str_replace($n[0],'',$dadosForm['nome']));
    						}else{
    							$dadosForm['Nome'] = $n[0];
    						}
    					}else{
    						$dadosForm[$v] = $dadosForm[$k];
    					}
    				}
    				$ac = 'cad';
    				unset($dadosForm['data'],$dadosForm['id']);
    				$dadosForm['conf'] = 's';
    				$dadosForm['EscolhaDoc'] = 'CPF';
    				$dadosForm['excluido'] = 'n';
    				$dadosForm['deletado'] = 'n';
    				$dadosForm['reg_excluido'] = '';
    				$dadosForm['reg_deletado'] = '';
    				$config2 = array(
    					'tab'=>$tabUser,
    					'valida'=>true,
    					'condicao_validar'=>$cond_valid,
    					'sqlAux'=>false,
    					'ac'=>$ac,
    					'type_alt'=>$type_alt,
    					'dadosForm' => $dadosForm
    				);
    				$ret['config2'] = $config2;
    				//return $ret;
    				// $ret['cad_cliente'] = lib_json_array(lib_salvarFormulario($config2));
    				$ret['cad_cliente'] = Qlib::update_tab($tabUser,$cond_valid,$dadosForm);
    				if(isset($ret['cad_cliente']['exec'])){
    					$ret['exec'] = $ret['cad_cliente']['exec'];
    					if($ret['exec']){
    						$ret['exclui'] = Qlib::excluirUm([
    							'tab'=>$tab_l,
    							'campo_id'=>'id',
    							'id'=>$id,
    							'nomePost'=>'Lead promovido a cliente',
    							'campo_bus'=>'id',
    						]);
    					}
    				}
    			}
    		}
    		if($type=='cl'){
    			$cliente = Qlib::dados_tab($tab_c,['campos'=>'*','where'=>"WHERE $campo_bus='$id'"]);
    			$ret['cliente'] = $cliente;
    			if($cliente){
    				$dadosForm =  $cliente[0];
    				$cond_valid = "WHERE token='".$dadosForm['token']."'";
    				$type_alt = 1;
    				$tabUser = $tab_l;
    				foreach ($arr_convert as $k => $v) {
    					if($k=='nome'){
    						$dadosForm['nome'] = $dadosForm['Nome'].' '.$dadosForm['sobrenome'];
    						//$dadosForm['nome'] = str_replace('  ',' ',$dadosForm['nome']);
    					}else{
    						$dadosForm[$k] = $dadosForm[$v];
    					}
    				}
    				//return $dadosForm;
    				$ac = 'cad';
    				unset($dadosForm['data'],$dadosForm['id']);
    				$dadosForm['conf'] = 's';
    				$dadosForm['excluido'] = 'n';
    				$dadosForm['deletado'] = 'n';
    				$dadosForm['reg_excluido'] = '';
    				$dadosForm['reg_deletado'] = '';
    				$config2 = array(
    					'tab'=>$tabUser,
    					'valida'=>true,
    					'condicao_validar'=>$cond_valid,
    					'sqlAux'=>false,
    					'ac'=>$ac,
    					'type_alt'=>$type_alt,
    					'dadosForm' => $dadosForm
    				);
    				$ret['config2'] = $config2;
    				$ret['cad_lead'] = Qlib::update_tab($tabUser,$dadosForm,$cond_valid);
    				if(isset($ret['cad_lead']['exec'])){
    					$ret['exec'] = $ret['cad_lead']['exec'];
    					if($ret['exec']){
    						$ret['exclui'] = Qlib::excluirUm([
    							'tab'=>$tab_c,
    							'campo_id'=>'id',
    							'id'=>$id,
    							'nomePost'=>'Cliente rebeixado a lead',
    							'campo_bus'=>'id',
    						]);
    					}
    				}
    			}
    		}
    	}
    	return $ret;

	}
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
        $ret['exec'] = false;

        return $ret;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
}
