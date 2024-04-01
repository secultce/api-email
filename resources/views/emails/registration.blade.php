<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Email</title>
</head>
<body>
    <p>
        <h3>Prezad@,</h3>
    <label for="">{{$nameUser}}</label>
    </p>
    <p>
        Espero que este e-mail o encontre bem.
    </p>
    <p>
        Gostaríamos de informá-l@ que uma diligência foi aberta pelo nosso avaliador em relação ao seu projeto/proposta.
        O avaliador identificou alguns pontos que necessitam de esclarecimentos adicionais ou informações complementares.
    </p>
    <p>
        Para responder à diligência, por favor, clique no link abaixo:
    </p>
    <p>
      <a href="http://localhost:8088/inscricao/{{$number}}">Click aqui</a>
    </p>
    <p>
    Pedimos que responda às questões em até {{$days}} para que possamos dar continuidade ao processo de avaliação.

    Fique à vontade para entrar em contato conosco caso tenha alguma dúvida ou necessite de assistência adicional.
    </p>
</body>
</html>
