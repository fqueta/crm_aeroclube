<!DOCTYPE html>
<html lang="pt-br">
<head>
    <style>
        /* Estilo geral para remover margens e usar o layout completo */
        @page {
            margin: 0cm;
        }

        body {
            margin: 0cm;
            padding: 0cm;
            font-family: Arial, sans-serif;
        }

        .pagina {
            position: relative;
            width: 100%;
            height: 100vh; /* Altura total da página */
            page-break-after: always; /* Quebra de página após cada página */
        }

        /* Estilo específico para cada página */
        .pagina-1 {
            background-image: url('https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/5e3d812dd5612/6542b60fd4295.png'); /* URL da imagem da página 1 */
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
            /* color: white; */
            text-align: center;
            padding: 20px;
        }

    </style>
</head>
<body>
    <!-- Página 1 -->
    <div class="pagina pagina-1">
        <div class="conteudo">
            <h1>Página 1</h1>
            <p>Conteúdo da primeira página.</p>
            <img src="https://crm.aeroclubejf.com.br/enviaImg/uploads/ead/5e3d812dd5612/6542b60fd4295.png" alt=""/>
        </div>
    </div>

    <!-- Página 2 -->
    <div class="pagina pagina-2">
        <div class="conteudo">
            <h1>Página 2</h1>
            <p>Conteúdo da segunda página.</p>
        </div>
    </div>

    <!-- Página 3 -->
    <div class="pagina pagina-3">
        <div class="conteudo">
            <h1>Página 3</h1>
            <p>Conteúdo da terceira página.</p>
        </div>
    </div>
</body>
</html>
