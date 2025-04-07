{{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> --}}
{{-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous"> --}}
@php
// dd($arr_processo);
$assinantes = isset($arr_processo['signers']) ? $arr_processo['signers'] : false;
if(!$assinantes){
    $assinantes = isset($envio['signers']) ? $envio['signers'] : false;
}
$token_zapsing = isset($envio['token']) ? $envio['token'] : false;
$status = isset($envio['status']) ? $envio['status'] : false;
$nome = isset($envio['name']) ? $envio['name'] : false;
if($token_zapsing){
    $link_verificador = 'https://app.zapsign.com.br/verificar/autenticidade?doc='.$token_zapsing;
}else{
    $link_verificador = '';
}
if($status == 'pending'){
    $status = '<span class="badge badge-danger badge-error" style="background-color: #b94a48;">Em curso</span>';
}elseif($status == 'signed'){
    $status = '<span class="badge badge-success">Processo completo</span>';
}else{
    $status = '';
}
// dump($envio);

@endphp
<style>
    .h1-sig{
        font-size: 16px;
        font-weight: bold;
    }
    .imp-link{
        height: 56px !important;
    }

</style>

@if (is_array($assinantes))
    <div class="panel panel-default mt-2">
        <div class="panel-heading">
            <h4>
                {{__('Gerenciamento de assinaturas')}} <span class="pull-right">{!!$status!!}</span>
            </h4>
        </div>
        <div class="panel-body px-0">
            @if ($link_verificador)

            <div class="row mx-0">
                <div class="col-md-12">
                    <a href="{{$link_verificador}}" class="underline" target="_BLANK">Verificar validade e andamento da assinatura no zapsing</a>
                </div>
            </div>
            @endif
            <div class="row mx-0">
                @foreach ($assinantes as $k=>$v )
                {{-- {{dd($v)}} --}}
                    @php
                        $status_sign = $v['status'];
                        $bdg = 'badge-danger badge-error';
                        if($status_sign=='signed'){
                            $bdg = 'badge-success';
                            $status_sign = 'Assinado';
                        }else{
                            $status_sign = 'Aguardando Assinatura';
                        }
                    @endphp
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-6">
                                        <b>Nome: </b> {{@$v['name']}}
                                    </div>
                                    <div class="col-md-6">
                                        <b>Visualizado: </b> {{@$v['times_viewed']}}
                                    </div>
                                    <div class="col-md-12 mb-2">
                                        <b>Status: </b> <span class="badge {{$bdg}}">{{$status_sign}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group">
                                    <span class="input-group-addon">{{__('Link de assinatura:')}}</span>
                                    <input type="text" class="form-control" disabled value="{{$v['sign_url']}}" aria-label="Amount (to the nearest dollar)">
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Ação <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right">
                                          <li><a class="" href="javascript:void(0)" onclick="copyTextToClipboard('{{$v['sign_url']}}')">Copiar</a></li>
                                          <li><a class="" target="_blank" href="{{$v['sign_url']}}">Acessar</a></li>
                                        </ul>
                                      </div>

                                </div>
                                {{-- <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="basic-addon1">{{__('Link de assinatura:')}}</span>
                                      </div>
                                    <input type="text" class="form-control" disabled value="{{$v['sign_url']}}" aria-label="Text input with dropdown button">
                                    <div class="input-group-append">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Ação</button>
                                    <div class="dropdown-menu pull-right">
                                        <a class="dropdown-item" href="javascript:void(0)" onclick="copyTextToClipboard('{{$v['sign_url']}}')">Copiar</a>
                                        <a class="dropdown-item" target="_blank" href="{{$v['sign_url']}}">Acessar</a>
                                    </div>
                                    </div>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        {{-- <div class="panel-footer text-muted">
            Footer
        </div> --}}
    </div>
    @if(isset($arr_links['principal']['nome']) && isset($arr_links['principal']['link']) && ($nome = $arr_links['principal']['nome']))
        <div class="panel panel-default mt-2">
            <div class="panel-heading">
                <h4>
                    {{__('Documentos Assinados')}}
                </h4>
            </div>
            <div class="panel-body">
                @php
                    $link = $arr_links['principal']['link'];
                    $icon = '<i class="fa fa-file-pdf-o fa-2x text-danger" aria-hidden="true"></i>';
                    $i = 1;
                @endphp

                <h5>{{ $nome }}</h5>
                <table class="table table-hover table-striped">
                    <tbody>
                        <tr>
                            <td> 1 </td>
                            <td>{{__('Contrato principal')}}</td>
                            <td class="text-right">
                                <a href="{{$link}}" target="_blank" title="Visualizar: o Contrato principal">{!! $icon !!}</a>
                            </td>
                        </tr>
                        @if (isset($arr_links['extra']) && is_array($arr_links['extra']) && ($extra = $arr_links['extra']))
                            <tr>
                                <td colspan="3" class="text-center">Documentos Anexos</td>
                            </tr>
                            @foreach ($extra as $k=>$v )
                                @php
                                    $i++;
                                @endphp
                                <tr>
                                    <td> {{$i}} </td>
                                    <td>{{$v['nome']}}</td>
                                    <td class="text-right">
                                        <a href="{{$v['link']}}" title="Visualizar: {{$v['nome']}}" target="_blank">{!! $icon !!}</a>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>

            </div>
        </div>
    @endif


@endif
{{-- <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script> --}}
