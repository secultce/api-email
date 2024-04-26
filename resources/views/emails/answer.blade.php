<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        .btn-answer {
            background: #085E55;
            border: 1px solid #000000;
            padding: 0px 16px 0px 16px;
            height: 40px;
            font-size: larger;
            border-radius: 2px;
        }
        .btn-answer > a {
            color: #FFFFFF;
            text-decoration:none
        }
    </style>

    <title>Email</title>
</head>
<body>
<div style="font-family: system-ui; padding: 30px; margin: 15px;">
    <p>
    <h3>Olá!</h3>
    </p>
    <p>
        Recebemos uma resposta a sua diligência no número de inscrição on-{{$number}}
    </p>
    <p>
        Click no botão abaixo para conferir e analisar a resposta. Ao acessar, basta clicar na aba "Diligência".
    </p>

</div>
<div style="padding: 30px;font-family: system-ui;">
    <p>
        <button  class="btn-answer">
            <a href="http://localhost:8088/inscricao/{{$number}}"
            >Conferir resposta</a>
        </button>

    </p>

</div>
</body>
</html>
