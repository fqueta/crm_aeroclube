<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header {
            text-align: center;
            font-size: 24px;
        }

        .content {
            margin-top: 20px;
            font-size: 16px;
        }
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .page {
            page-break-after: always;
            width: 100%;
            height: 100vh;
            position: relative;
        }

        .page:nth-child(1) {
            background: url('https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/5e3d812dd5612/6542b60fd4295.png') no-repeat center center;
            background-size: cover;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="content">
            <h1>Página 1</h1>
            {{ $titulo }}
            <p>Conteúdo da primeira página.</p>
            {{ $conteudo }}
        </div>
    </div>
    <div class="page">
        <div class="content">
            <h1>Página 2</h1>
            <p>Conteúdo da segunda página.</p>
        </div>
    </div>
</body>
</html>
