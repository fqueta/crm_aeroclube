<?php

namespace App\Http\Controllers;

use App\Qlib\Qlib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public $tab;
    public function __construct()
    {
        $this->tab = 'conteudo_site';
    }
    /**
     * Metodos para reproduzir um short code baseado na tabela conteudo_site
     * @param string $code o codigo sortcode
     * @param array $return array | string
     */
    public function short_code($code=false,$conf=false,$btn_edit=true){
        // $config = array(
        //     // 'arquivo_html'=>'app/teste/index.html',
        //     'comple'=>"AND short_code = '".$code."'"
        // );

        $ret = false;
        $link_site = url('/');
        if($code){
            $comple = isset($conf['comple']) ? $conf['comple'] : ' AND '.Qlib::compleDelete();
            $sql = "SELECT * FROM ".$this->tab." WHERE ativo='s' AND short_code = '".$code."' $comple";
            if(isset($_GET['fp'])){
                echo $sql;
            }
            $ds = DB::select($sql);

            if(count($ds)){
                //pega o primeiro registro encontrado
                $dados = (array)$ds[0];
                for($i=0;$i<count($dados);$i++){

                    $image_link = $this->dadosImagemModGal('arquivo',"id_produto='".$dados['token']."'");

                    // $return['conteudo'][$i] = $dados[$i];

                    $dados['img_url'] = isset($image_link) ? $image_link : [];

                    // $dados['img_title'] = isset($image_link) ? $image_link : [];

                }
                //foreach($dados['dados'] As $key=>$val){
                    $legenda = false;

                    /*if(is_adminstrator(1)){

                        $legenda = $dados['short_code'].'<br>';

                        $ret .= $legenda;

                    }*/
                    //if(is_adminstrator(1)){
                        //print_r($dados);
                    //}
                    if($dados['tipo_conteudo'] == 1 || $dados['tipo_conteudo'] == 4 || $dados['tipo_conteudo'] == 13){

                        ///e artigo
                        // $ret = decodeTranslate($dados['obs'],'site');
                        $ret = $dados['obs'];

                    }elseif($dados['tipo_conteudo'] == 5){

                        $ret = $dados['img_url'];

                    }elseif($dados['tipo_conteudo'] == 2){

                        $ret = '<img class="tipo_conteudo2" src="'.$dados['img_url'].'"/>';

                    }elseif($dados['tipo_conteudo'] == 3){
                        if(isset($conf['tema1'])&&!empty($conf['tema1'])&&isset($dados['img_url'])){
                            $ret = str_replace('{img_url}',$dados['img_url'],$conf['tema1']);
                            $ret = str_replace('{alt}',$dados['img_title0'],$ret);
                            $ret = str_replace('{img_legenda}',$dados['img_title'],$ret);
                        }else{
                            $ret = str_replace('aeroclubejf','crm.aeroclubejf',$dados['img_url']);
                        }
                        if(Qlib::isAdmin(3) && $btn_edit){
                            $url = $link_site.'/site/iframe?sec=Y290ZXVkby1zaXRl&list=false&regi_pg=40&pag=0&acao=alt&id='.base64_encode($dados['id']);
                            $admin = '<div class="col-md-12 text-center padding-none mt-1"><a href="javascript:void(0);" class="btn btn-outline-secondary" title="Editar a galeria '.$dados['nome'].'" data-toggle="tooltip" onclick="abrirjanelaPadrao(\''.$url.'\');"><i class="fa fa-pencil"></i></a></div>';
                            //$admin = str_replace('|ret|',$ret,$tema);
                        }else{
                            $admin = '';
                        }
                        $ret = str_replace('{{painel_admin_short_code}}',$admin,$ret);
                    }elseif($dados['tipo_conteudo'] == 12){

                        $ret = $dados['link_redirect'];

                    }elseif($dados['tipo_conteudo'] == 15){

                        $ret = $dados['obs'];

                    }elseif($dados['tipo_conteudo'] == 19){
                        if(isset($conf['tema1'])&&!empty($conf['tema1'])&&isset($conf['tema2'])&&!empty($conf['tema2'])&&isset($dados['img_url'])){
                            $codigo = false;
                            if(is_array($dados['img_url'])){
                                $li = false;
                                $li_carrocel = false;
                                foreach($dados['img_url'] As $k=>$val){
                                    $li .= str_replace('{img}',$val['url'],$conf['tema2']);
                                    $li = str_replace('{img_alt}',$val['title'],$li);
                                    $li = str_replace('{img_title}',$val['title2'],$li);
                                    $li = str_replace('{id}',($k+1),$li);
                                    $li = str_replace('{img_description}',$val['title3'],$li);
                                    if($k==0){
                                        $active = 'active';
                                    }else{
                                        $active = false;
                                    }
                                    $li = str_replace('{active}',$active,$li);
                                    $li_carrocel .= str_replace('{active}',$active,$conf['tema3']);
                                    $li_carrocel = str_replace('{id_carousel}',$dados['short_code'],$li_carrocel);
                                    $li_carrocel = str_replace('{id}',$k,$li_carrocel);
                                }
                                $ret = str_replace('{img_galeria_lightbox}',$li,$conf['tema1']);
                                $ret = str_replace('{id_carousel}',$dados['short_code'],$ret);
                                $ret = str_replace('{li_carrocel}',$li_carrocel,$ret);
                                if(Qlib::isAdmin(3) && $btn_edit){
                                    $url = $link_site.'/site/iframe?sec=Y290ZXVkby1zaXRl&list=false&regi_pg=40&pag=0&acao=alt&id='.base64_encode($dados['id']);
                                    $admin = '<div class="col-md-12 text-center padding-none mt-1 hidden-print"><a href="javascript:void(0);" class="btn btn-outline-secondary" title="Editar a galeria '.$dados['nome'].'" data-toggle="tooltip" onclick="abrirjanelaPadrao(\''.$url.'\');"><i class="fa fa-pencil"></i></a></div>';
                                    //$admin = str_replace('|ret|',$ret,$tema);
                                }else{
                                    $admin = '';
                                }
                                $ret = str_replace('{{painel_admin_short_code}}',$admin,$ret);
                            }
                        }else{
                            $ret = $dados['img_url'];
                        }
                        //$ret = $dados['img_url'];

                    }else{
                        $ret = $dados['obs'];
                    }
                    if(Qlib::isAdmin(3) && ($dados['tipo_conteudo'] == 1 || $dados['tipo_conteudo'] == 2 || $dados['tipo_conteudo'] == 15) && $btn_edit){
                        $url = $link_site.'/site/iframe?sec=Y290ZXVkby1zaXRl&list=false&regi_pg=40&pag=0&acao=alt&id='.base64_encode($dados['id']);
                        $tema = '';
                        // if(is_pdf()){
                        //     $tema = '<a class="btn btn-primary hidden-print" href="javascript:void(0);" onclick="abrirjanelaPadrao(\''.$url.'\');">|ret|</a>';
                        // }else{
                        //     $tema = '<span style="position:absolute;top:-30px;right:10px" class="hidden-print"><a class="btn btn-primary" href="javascript:void(0);" onclick="abrirjanelaPadrao(\''.$url.'\');"><i class="fa fa-pencil"></i> Editar</a></span>|ret|';
                        // }
                        $ret = str_replace('|ret|',$ret,$tema);
                    }

                //}

            }

        }

        return $ret;

    }
    /**
     * Mostar uma galeria de imagens
     */
    public function dadosImagemModGal($tabela,$condicao,$campoRetorno='endereco',$opc=1){
        if($opc==1){
            $tabela = 'imagem_'.$tabela;
        }else{
            $tabela = $tabela;
        }
        $url = "SELECT * FROM $tabela WHERE $condicao ORDER BY ordem ASC";
        $td_img = DB::select($url);
        // $img_result = false;
        $urlima = array();
        if(count($td_img)){
            for($i=0;$i<count($td_img);$i++){
                $td_img[$i] = (array)$td_img[$i];
                $linkImg = $td_img[$i][$campoRetorno];
                $linkImg = str_replace('aeroclubejf.','crm.aeroclubejf.',$linkImg);
                $urlima[$i]['url'] = $linkImg;
                $urlima[$i]['title'] = $td_img[$i]['title'];
                $urlima[$i]['title2'] = $td_img[$i]['title2'];
                $urlima[$i]['title3'] = $td_img[$i]['title3'];
                $urlima[$i]['title4'] = $td_img[$i]['title4'];
                $urlima[$i]['ordem'] = $td_img[$i]['ordem'];
            }
            return $urlima;
        }else{
            return false;
        }
    }
}
