<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        .font-large {
            font-weight: 500;
            font-size: 16px;
        }
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
    <p>
        <h3>Olá, {{$nameUser}}</h3>
    </p>
    <p>
        Informamos a você que uma diligência foi aberta pelo nosso avaliador em relação ao seu projeto/proposta.
    </p>
    <p>
        O avaliador identificou alguns pontos que necessitam de esclarecimentos adicionais ou informações complementares.
    </p>
    <p>
        Para  ir até à diligência e respondê-la, clique no botão abaixo e depois clique na aba Diligência:
    </p>
    <p>
        <button  class="btn-answer">
            <a href="https://mapacultural.secult.ce.gov.br/inscricao/{{$number}}"
            >Abrir minha inscrição</a>
        </button>
    </p>
    <p>
    Pedimos que responda às solicitações em até {{$days}} dias úteis para dar continuidade ao processo de avaliação.

        Em caso de dúvidas ou assistência adicional, entre em contato conosco
        <a href="https://tawk.to/chat/5f35c9424c7806354da63dc9/default" target="_blank"> chat </a> ou
        pelo e-mail definido no edital.
    </p>
</body>
</html>
