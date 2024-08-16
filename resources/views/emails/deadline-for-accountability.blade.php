<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestação de contas</title>
</head>

<body>
    <main>
        <h3>Olá, {{ $info->agent_name }}.</h3>

        <p>O prazo para o envio do seu {{ $info->notification_type }} {{ $info->notification_msg }}.</p>
    </main>
</body>

</html>
