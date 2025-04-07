<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class PdfBrowser extends Controller
{
    //
    public function gerarPdf()
    {
        $html = view('pdf.meu_pdf', [
            'titulo' => 'Relatório',
            'conteudo' => 'Este é o conteúdo do relatório.',
        ])->render();

        $caminhoArquivo = storage_path('orcamentos/relatorio.pdf');
        // dd($caminhoArquivo);
        Browsershot::html($html)
            ->setPaper('a4') // Define o tamanho da página
            ->setOption('margin-top', '10mm') // Margens opcionais
            ->setOption('margin-bottom', '10mm')
            ->save($caminhoArquivo);

        return response()->download($caminhoArquivo);
    }
}
