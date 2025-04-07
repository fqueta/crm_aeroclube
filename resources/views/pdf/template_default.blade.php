<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <style>
        body{
            margin: 0;
            font-family: "Source Sans Pro",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            text-align: left;
            background-color: #fff;
        }
        .table{
            font-size: 11px;
            width: 100%;
        }
        .table th{
            text-align: left;
        }
        .table td,.table th{
            border-top: 1px solid #dee2e6;
        }

    </style>
</head>
<body>
    {{-- <h1>{{ $titulo }}</h1> --}}
    {!! $conteudo !!}
</body>
</html>
