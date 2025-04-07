<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Qlib\Qlib;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Support\Facades\Storage;

class PdfGenerateController extends Controller
{
    public function gera_orcamento($token=false,$type='pdf'){
        if($token){
            $orca = new MatriculasController;
            $d = $orca->dm($token);
            if($d){
                //verifica se está assinado
                // $config = $orca->get_matricula_assinado($token);
                // if(@$config['exec'] && @$config['data']){
                //     $ret = @$config;
                // }else{
                //     $ret['save'] = $orca->salva_orcamento_assinado($token,$d[0]);
                //     $config = $orca->get_matricula_assinado($token);
                // }
                $nome = isset($d['Nome']) ? $d['Nome'] : '';
                $code = 'fundo_proposta';
                $id_curso = isset($d['id_curso']) ? $d['id_curso'] : '';
                $tipo_curso = isset($d['tipo_curso']) ? $d['tipo_curso'] : '';
                $fundo = (new SiteController())->short_code($code,['comple'=>" AND tipo_curso='$tipo_curso'"]);
                $paginas = [];
                $dias = isset($dias)?$dias: Qlib::qoption('validade_orcamento');
				if(!$dias){
					$dias = 7;
				}
                $dadosD = explode(' ',$d['atualizado']);
				$validade =  Qlib::CalcularVencimento(Qlib::dataExibe($dadosD[0]),$dias);
                $validade = Qlib::dataExibe($validade);
                $res_orc = (new MatriculasController)->gerar_orcamento($token,'s');
                // dd($res_orc);
                // $dadosCli = '<p align="center" style="font-size:15pt;">

				// 				<b>Cliente:</b> '.$d['Nome'].' '.$d['sobrenome'].'
				// 				<br>
				// 				<b>Telefone:</b> '.$d['telefonezap'].'  '.$d['Tel'].' <br>
				// 				<b>Email:</b> '.$d['Email'].'  <br>
				// 				<b>Data:</b> '.Qlib::dataExibe($d['atualizado']).' <b>Validade:</b> '.$validade.'<br>
				// 			</p>';
                $dadosCli = isset($res_orc['dadosCli']) ? $res_orc['dadosCli'] : '';
                $orcamento = isset($res_orc['table']) ? $res_orc['table'] : '';
                $parcelamento = isset($res_orc['parcelamento']) ? $res_orc['parcelamento'] : '';
                // if(!$orcamento){
                //     $orcamento = isset($res_orc['table2']) ? $res_orc['table2'] : '';
                // }
                if($tipo_curso==4 && isset($res_orc['listMod']['html'])){
                    $orcamento .= $res_orc['listMod']['html'];
                }

                if($type=='pdf'){
                    // dump($type,$fundo,$d);
                    if(is_array($fundo)){
                        //Montar as paginas do PDF
                        foreach ($fundo as $k => $v) {
                            $pagina = ($k+1);
                            $title = '';//'<h1>Title pagina '.$pagina.'.</h1>';
                            $content = '';//'<p>Conteudo da pagina '.$pagina.'.<p>';
                            $padding = '110px 30px 10px 30px';
                            if($k==0){
                                //paigina inicial
                                $padding = '805px 30px 10px 30px';
                                $content = $dadosCli;
                            }
                            if($k==1){
                                // $padding = '805px 30px 10px 30px';
                                $content = $orcamento;
                            }
                            if($k==2){
                                if($tipo_curso!=4){
                                    $title = '<h2>Parcelamento</h2>';
                                }
                                $padding = '120px 30px 10px 30px';
                                $content = $parcelamento;
                            }
                            $paginas[$k] = [
                                'bk_img'=>$v['url'],
                                'title'=>$title,
                                'content'=>$content,
                                'padding'=>$padding,
                                // 'margin'=>'0px',
                            ];
                        }
                    }
                }
                // $pdf = Pdf::loadView('pdf.orcamento',$config);
                // $pdf = Pdf::loadView('pdf.orcamento-bk',$config);
                // // $pdf = Pdf::view('pdf.orcamento');
                // $path = storage_path('/orcamentos/');
                // $filename = 'Orçamento ' . $nome.'.pdf';

                $filename = 'Orçamento ' . $nome;
                $arquivo = isset($res_orc['nome_arquivo']) ? $res_orc['nome_arquivo'] : $filename;
                //,'paginas'=>['bk_img'=>'','title'=>'','content']
                $t_pdf = request()->get('t_pdf')?request()->get('t_pdf') : false;
                $f_exibe = request()->get('f_exibe')?request()->get('f_exibe') : 'navegador';
                if($type == 'pdf'){
                    $ret = $this->gerarPdfComImagemDeFundo(['token'=>$token,'nome_arquivo' => $arquivo,'paginas'=>$paginas,'t_pdf'=>$t_pdf,'f_exibe'=>$f_exibe]);
                }else{
                    $ret['dadosCli'] = $dadosCli;
                }
                return $ret;
            }
        }
    }
    /**
     * Metodo para gerar um arquivo em PDF aparter eu um HTML
     * @param array $config
     * uso $ret = (new PdfGererateController)->gerarPdfComImagemDeFundo(['nome_arquivo' => 'Orçamento','paginas'=>['bk_img'=>'','title'=>'','content']]);
     */

     public function gerarPdfComImagemDeFundo($config=[])
    {
        $t_pdf = isset($config['t_pdf']) ? $config['t_pdf'] : true;
        $arquivo_tipo = isset($config['arquivo_tipo']) ? $config['arquivo_tipo'] : 'orcamentos';
        $token = isset($config['token']) ? $config['token'] : uniqid();
        $f_exibe = isset($config['f_exibe']) ? $config['f_exibe'] : 'navegador'; // navegador ou download

        $nome_arquivo = isset($config['nome_arquivo']) ? $config['nome_arquivo'] : 'Pdf_com_imagem';
        $paginas = isset($config['paginas']) ? $config['paginas'] : [
             [
                 'bk_img'=>'https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/5e3d812dd5612/6542b60fd4295.png',
                 'title'=>'<h2>Página1</h2>',
                 'content'=>'<p>
                 Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas pretium tempus libero sit amet venenatis. Phasellus ultrices ut ipsum sed tincidunt. Phasellus auctor nibh ut sem tempus accumsan. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Integer velit tellus, placerat condimentum consequat et, sodales dictum purus. Aenean quis velit faucibus, molestie augue sit amet, suscipit arcu. Aenean rutrum ante in tortor iaculis dignissim. Donec in turpis et augue fringilla molestie. Nunc non dictum augue. Suspendisse potenti. Morbi aliquam dignissim erat, eget blandit mauris rutrum ac. Vivamus ut pulvinar diam.
                 </p>
                 <p>',
                 'padding'=>'100px 30px 10px 30px',
                 'margin'=>'0px',
             ],
             [
                 'bk_img'=>'https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/668ef8112510b/66fadef37341b.png',
                 'title'=>'<h2>Página2</h2>',
                 'content'=>'<p>Conteúdo da página2</p>',
                 'margin'=>'0px',
             ],
             [
                 'bk_img'=>'https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/668ef8112510b/66fadef448e00.png',
                 'title'=>'<h2>Página3</h2>',
                 'content'=>'<p>Conteúdo da página3</p>',
                 'padding'=>'20px 20px 10px 10px',
                 'margin'=>'0px',
             ],
         ];
         $dados = [
             'style_content'=>'padding:20px;text-align:justify;',
             'paginas' =>$paginas,
         ];
         $html = view('pdf.pdf_com_imagem',$dados)->render();
         if($t_pdf=='false'){
            return $html;
         }
         // Gerar o PDF
         $pdf = SnappyPdf::loadHTML($html)
             ->setPaper('a4') // Define o tamanho do papel
             ->setOption('margin-top', 0)
             ->setOption('margin-bottom', 0)
             ->setOption('margin-left', 0)
             ->setOption('margin-right', 0)
             ->setOption('enable-local-file-access', true);
            //  ->setOption('ignore-certificate-errors', true); // Necessário para imagens locais

         // $pdf->setOption('header-html', view('header')->render());
        if($f_exibe=='download'){
            //faz download
            return $pdf->download($nome_arquivo.'.pdf');
        }elseif($f_exibe=='server'){
            $fileName = $arquivo_tipo.'/'.$token.'/proposta.pdf';
            //grava statico no servidor
            $pdfbin = $pdf->output();
            Storage::put($fileName, $pdfbin);

        // Retornar uma mensagem ou caminho do arquivo salvo
            return response()->json(['message' => 'PDF salvo com sucesso!', 'path' => $fileName]);
        }else{
            //grava statico no navegador
            return $pdf->inline($nome_arquivo.'.pdf');
        }
        // return $pdf->output('pdf_com_imagem_n.pdf');
    }
    public function convert_html($config=[]){
        $f_exibe = isset($config['f_exibe']) ? $config['f_exibe'] : 'pdf';
        $html = isset($config['html']) ? $config['html'] : '';
        $nome_aquivo_savo = isset($config['nome_aquivo_savo']) ? $config['nome_aquivo_savo'] : '';
        $titulo = isset($config['titulo']) ? $config['titulo'] : '';
        $token = isset($config['token']) ? $config['token'] : '';
        $pasta = isset($config['pasta']) ? $config['pasta'] : '';
        $id_matricula = isset($config['id_matricula']) ? $config['id_matricula'] : null;
        $short_code = isset($config['short_code']) ? $config['short_code'] : false;
        // $nome_aquivo_savo='arquivo',$titulo='Arquivo'
        $ret['exec'] = '';
        $html = view('pdf.template_default', ['titulo'=>$titulo,'conteudo'=>trim($html)])->render();
        $pdf = SnappyPdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('margin-top', '10mm')
                ->setOption('margin-bottom', '10mm');
        if($f_exibe=='pdf'){
            return $pdf->stream($nome_aquivo_savo.'.pdf');
        }elseif($f_exibe=='server' && $token){
            try {
                $fileName = $pasta.'/'.$token.'/'.Qlib::createSlug($nome_aquivo_savo).'.pdf';
                //grava statico no servidor
                $pdfbin = $pdf->output();
                $ret['ger_arquivo'] = Storage::put($fileName, $pdfbin);
                if (Storage::exists($fileName) && $short_code && $id_matricula) {
                    $url = Storage::url($fileName);
                    $ret['salvo'] = Qlib::update_matriculameta($id_matricula,$short_code.'_pdf',$url);
                    $ret['url'] = $url;
                    if($ret['salvo']){
                        $ret['exec'] = true;
                    }
                }else{

                }
            } catch (\Throwable $th) {
                $ret['error'] = $th->getMessage();
            }
        }
        if(!$token){
            $ret['mens'] = 'Token de contrato inválido';
        }
        return $ret;
    }
}
