<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Orçamento</title>
    <link rel="stylesheet" type="text/css" media="screen" href="https://crm.aeroclubejf.com.br/site/tema/aeroclubejf/aerocurso/css/style.css" />
    <style>
         @page {
            margin: 0cm;
        }

        #overlay {
            background-image: url('https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/5e3d812dd5612/6542b60fd4295.png');
            position:absolute;
            top:0;
            left:0;
            background-position: top left;
            background-repeat: no-repeat;
            background-size: 100%;
            width:100%;
            height:100%;
            padding: 100px 10px 10px 10px;
        }
        body {
            margin: 0cm;
            padding: 0cm;
            font-family: Arial, sans-serif;
        }

        p {
            width: 400px;
            margin: auto;
        }
        .signature {
            margin-top: 4em;
            text-align: right;
        }
        .table{
            font-size: 12px;
            width:93%;
        }
        td{
            padding: 0 0 0 0 !important;
            margin: 0 0 0 0 !important;
        }
        .pagina {
            position: relative;
            width: 100%;
            height: 100vh; /* Altura total da página */
            page-break-after: always; /* Quebra de página após cada página */
        }

        /* Estilo específico para cada página */
        .pagina-1 {
            background-image: url('https://via.placeholder.com/1920x1080'); /* URL da imagem da página 1 */
            background-size: cover;
            background-position: center;
        }

        .pagina-2 {
            background-image: url('https://via.placeholder.com/1080x1920'); /* URL da imagem da página 2 */
            background-size: cover;
            background-position: center;
        }

        .pagina-3 {
            background-image: url('https://via.placeholder.com/800x600'); /* URL da imagem da página 3 */
            background-size: cover;
            background-position: center;
        }

        /* Estilo para o conteúdo sobre a imagem */
        .conteudo {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    @php
        $valores = json_decode($data['totais'],true);
        $total_horas = 0;
    @endphp
    <div class="" id="overlay">
        <div class="row">
            <div class="col-12">
                <b>ID:</b> {{$dm['id']}}<br>
                <b>Nome:</b> {{$dm['Nome']}} {{$dm['sobrenome']}}<br>
                <b>Curso:</b> Piloto Comercial Avião MLTE IFR Prático - T02 ABRIL 2024 - PILOTO COMERCIAL MLTE IFR
            </div>
            <div class="col-12 tabe-1">
                <table class="table table-striped table-hover w-80">
                    <thead>
                        <tr>
                            <th style="width:5%"><div align="center">ITEM</div></th>

                            <th style="width:40%"><div align="center">CRONOGRAMA</div></th>

                            <th style="width:20%"><div align="center">AERONAVE</div></th>

                            <th style="width:5%"><div align="center">HORAS/AULA</div></th>

                            {{-- <th style="width:5%"><div align="right">TOTAL</div></th> --}}
                        </tr>
                    </thead>

                    <tbody class="jss526">
                        @if (isset($data['orc']['modulos']))
                            @foreach ($data['orc']['modulos'] as $k => $v)
                                @php
                                    $total_horas += (int)$v['horas'];
                                @endphp
                                <tr id="lin_0">
                                    <td><div align="center">{{($k+1)}}</div></td>

                                    <td><div align="left">{{@$v['titulo']}}</div></td>

                                    <td><div align="center">{{@$aeronaves[$v['aviao']]}}</div></td>

                                    <td><div align="center">{{@$v['horas']}}</div></td>

                                    {{-- <td><div align="right">{{App\Qlib\Qlib::valor_moeda(@$valores[$k],'R$')}}</div></td> --}}
                                </tr>
                            @endforeach
                        @endif
                    </tbody>

                    <tfoot class="jss526">
                        <tr>
                            <td colspan="2"><div align="right">Subtotal</div></td>

                            <td>
                                <div align="center"><b> {{$total_horas}} </b></div>
                            </td>

                            <td>
                                <div align="right"><b>{{App\Qlib\Qlib::valor_moeda(@$data['subtotal'],'R$')}}</b></div>
                            </td>
                        </tr>

                        <tr class="vermelho">
                            <td colspan="3">
                                <div align="right"><strong>DESCONTO (15,00%) </strong></div>
                            </td>

                            <td>
                                <div align="right"><b> 9.285,00</b></div>
                            </td>
                        </tr>
                        <tr class="verde">
                            <td colspan="3" class="total-curso">
                                <div align="right"><strong>Total do curso:</strong></div>
                            </td>
                            <td class="total-curso">
                                <div align="right"><b> {{App\Qlib\Qlib::valor_moeda(@$data['total'],'R$')}}</b></div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <br />
            <div class="col-12 tabe-2">
                <table id="table3" class="table">
                    <thead>
                        <tr>
                            <th style="width: 5%;"><div align="center">ITEM</div></th>

                            <th style="width: 70%;"><div align="center">DESCRIÇÃO</div></th>

                            <th style="width: 10%;"><div align="right">TOTAL</div></th>
                        </tr>
                    </thead>

                    <tbody class="jss526">
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">1</div></td>

                            <td style="width: 85%;"><div align="left">Matrícula</div></td>

                            <td style="width: 10%;"><div align="right">300,00</div></td>
                        </tr>

                        <tr id="matri">
                            <th style="width: 5%;"><div align="center">&nbsp;</div></th>

                            <th style="width: 85%;"><div align="right">Curso + Matrícula</div></th>

                            <th style="width: 10%;"><div align="right">52.915,00</div></th>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive padding-none tabe-3">
                <table class="table">
                    <tbody class="">
                        <tr></tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">2</div></td>
                            <td style="width: 85%;"><div align="left">Taxa de Examinador Credenciado ANAC</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">3</div></td>
                            <td style="width: 85%;"><div align="left">Taxa ANAC</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">4</div></td>
                            <td style="width: 85%;"><div align="left">Ground School</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">5</div></td>
                            <td style="width: 85%;"><div align="left">Despesas do Treinamento de Voo Noturno</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">6</div></td>
                            <td style="width: 85%;"><div align="left">Envio de Processo</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="matri">
                            <td style="width: 5%;"><div align="center">7</div></td>
                            <td style="width: 85%;"><div align="left">Manuais e Programas Impressos</div></td>
                            <td style="width: 10%;"><div align="right">0,00</div></td>
                        </tr>
                        <tr id="">
                            <td colspan="2" style="width: 100%;">
                                <div align="right"><strong>Gasto estimado de combustível:</strong></div>
                            </td>
                            <td colspan="" style="width: 100%;">
                                <div align="right"><b>26.950,00</b></div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" width="85%">
                                <div align="center"><strong class="verde">TOTAL DA PROPOSTA A VISTA:</strong></div>
                            </td>
                            <td>
                                <div align="right">
                                    <span class="verde"><b>79.865,00</b></span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="font-family: arial; font-size: 9pt; text-align: right;">*Tabela Piloto Comercial</p>
            </div>
        </div>
    </div>
</body>
</html>
