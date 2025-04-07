<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\ZapsingController;
use App\Jobs\GeraPdfContratoJoub;
use App\Jobs\SendZapsingJoub;
use App\Qlib\Qlib;
use Illuminate\Http\Request;

class TesteController extends Controller
{
    public function index(Request $request){
        $ret['exec'] = false;
        // $ret = (new SiteController())->short_code('fundo_proposta',['compl'=>'']);
        $token = $request->get('token');
        // $ret = (new MatriculasController)->gerar_orcamento($token);
        // $ret = Qlib::qoption('validade_orcamento');
        // $ret = Qlib::dados_tab('cursos',['id' => 97]);
        // $rd = new RdstationController;
        // dd($rd->token_api);

        // $ret = Qlib::saveEditJson($data);
        // $ret = Qlib::update_tab('clientes',$dados,"WHERE Email='".$dados['Email']."'");
        // $zg = new ZapguruController;

		// $ret = $zg->criar_chat(array('telefonezap'=>'5532984748644','cadastrados'=>true));
        // dd(Qlib::qoption('dominio'));
        // $ret = (new ZapguruController)->post('553291648202','dialog_execute',$comple_url='&dialog_id=679a438a9d7c8affe47e29b5');
        // $rdc = new RdstationController;
        // $ret = $rdc->get_contact('67a4f69c968ad00014a6773f');
        // $ret = Qlib::buscaValoresDb_SERVER('SELECT * FROM usuarios_sistemas');
        // $ret = Qlib::dados_tab('cursos',['where' =>'WHERE '.Qlib::compleDelete()." AND id='69'"]);
        // $token_matricula = '66e99d69953c0';
        // $ret = (new MatriculasController)->grava_contrato_statico($token);
        // $json = '{
        //     "token": "679d1019169b2",
        //     "pagina": "1",
        //     "token_matricula": "679d10356bccd",
        //     "Nome": "João Victtor",
        //     "pais": "Brasil",
        //     "DtNasc2": "1986-01-26",
        //     "Cpf": "123.456.789-09",
        //     "canac": "",
        //     "Ident": "v1555",
        //     "Cep": "36035-720",
        //     "Endereco": "Rua Eduardo Sathler",
        //     "Numero": "15",
        //     "Compl": "",
        //     "Bairro": "Serra D\'Água",
        //     "Cidade": "Juiz de Fora",
        //     "Uf": "MG",
        //     "nacionalidade": "Brasileiro",
        //     "profissao": "Programador",
        //     "sexo": "m",
        //     "config": {
        //         "altura": "175",
        //         "peso": "45"
        //     },
        //     "meta": {
        //         "situacao_cadastro": {
        //             "transferido": "Sim",
        //             "cma_em_dia": "Sim",
        //             "cma_class": "1ª Classe",
        //             "banca": "Sim"
        //         },
        //         "ciente": {
        //             "taxa_alojamento": "s",
        //             "hora_seca": "s",
        //             "headset": "s",
        //             "prazo_conclusao": "s",
        //             "altura_peso": "s",
        //             "gs": "s",
        //             "uniforme": "s"
        //         }
        //     },
        //     "campo_bus": "id",
        //     "campo_id": "id"
        // }';
        // $json2 = '{
        //     "token": "679d1019169b2",
        //     "pagina": "2",
        //     "token_matricula": "679d10356bccd",
        //     "contrato": {
        //         "declaracao": "on",
        //         "aceito_contrato": "on",
        //         "aceito_contrato_combustivel": "on",
        //         "aceito_termo_concordancia": "on",
        //         "aceito_termo_concordancia_escola_voo": "on",
        //         "aceito_termo_antecipacao_combustivel": "on",
        //         "data_aceito_contrato": "2025-02-21 13:23:33",
        //         "id_matricula": "7119",
        //         "ip": "172.70.140.46"
        //     },
        //     "campo_bus": "id",
        //     "campo_id": "id"
        // }';

        // // $config = Qlib::lib_json_array($json);
        // $config = Qlib::lib_json_array($json2);

        // $ret = (new MatriculasController)->assinar_proposta($config);

        //zapsing

        // enviar anexo.
        // $body = [
        //     'name'=>'Termo teste',
        //     'url_pdf'=>'https://oficina.aeroclubejf.com.br/storage/pdfs/termo_pdf',
        // ];
        // $endpoint = 'docs/d460cbeb-aba7-421f-a776-6c34cd60d1ae/upload-extra-doc';
        // $ret = (new ZapsingController)->post([
        //     "endpoint" => $endpoint,
        //     "body" => $body,
        // ]);
        // $ret = (new MatriculasController)->send_to_zapSing($token);
        // $ret = (new MatriculasController)->grava_contrato_statico($token);
        // SendZapsingJoub::dispatch($token);
        // GeraPdfContratoJoub::dispatch($token);
        // SendZapsingJoub::dispatch($token)->delay(now()->addSeconds(5));
        // dump(now()->addSeconds(5));
        // $token_envelope = '499519d1-165c-46a1-9a8e-92ad08598974';
        // $url_pdf = 'https://doc.aeroclubejf.com.br/storage/contratos/67bcad6a2228c/termo-concordancia-fernando-programandor-2-piloto-privado-aviao-7202.pdf';
        // $ret = (new ZapsingController)->enviar_anexo($token_envelope,$url_pdf,$nome_arquivo='Termo concordancia');
        // $ret = (new MatriculasController)->link_contratos_anexos($token);
        // $dm = (new MatriculasController)->dm($token);
        // $id = isset($dm['id']) ? $dm['id'] : 0;
        // $ret = (new MatriculasController)->enviar_contratos_anexos(false,false,$dm);
        // dd($token);
        // $ret = (new MatriculasController)->contatos_estaticos_pdf($id,true,$dm);
        // $signed_file = 'https://zapsign.s3.amazonaws.com/sandbox/dev/2025/2/pdf/f787046f-616b-4d0d-9924-769862f0d13f/63d6a30f-b5ac-4c00-ac5d-f230c95ea0ff.pdf?AWSAccessKeyId=AKIASUFZJ7JCTI2ZRGWX&Signature=RGvYrlWIkuwzB1YwQ49RLNkGhM0%3D&Expires=1741026569';
        // $ret = (new MatriculasController)->baixar_arquivo($token, $signed_file);
        // dd(count($ret));
        // $data = '07/02/2025-12:08:30';
        // $ip = '172.71.6.71';
        // $assinatura = '<span class="text-danger"><b>Contrato assinado eletronicamente pelo contratante em '.$data.'</b> </span><span style="text-align:right" class="text-danger"><b>Ip:</b> <i>'.$ip.'</i></span>';
        // $d = Qlib::buscaValorDb0('matriculameta','id',4068,'meta_value');
        // // dd($d);
        // $aluno = 'Elias Vicente Nunes Dias';
        // $cpf = '004.268.021-28';
        // $ret = base64_decode($d);
        // $ret = str_replace('<strong>CONTRATANTE&nbsp;</strong>','<strong>CONTRATANTE&nbsp;</strong><br><br>'.$assinatura,$ret);
        // $ret = str_replace('Aluno (a):','Aluno (a): '.$aluno,$ret);
        // $ds = base64_encode($ret);
        // $ret = Qlib::update_matriculameta(7145,'contrato_prestacao_n',$ds);
        // dd($ret);

        return $ret;
    }
}
