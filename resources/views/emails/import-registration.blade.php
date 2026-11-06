@include('emails/header')

<table role="presentation" class="main">
    <tr>
        <td>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <main class="wrapper">
                            <p><b>Olá, espero que esteja bem.</b></p>
                            <p>
                                Com grande satisfação, informamos que sua participação no
                                <strong>{{ $opportunity_name }}</strong> alcançou a próxima etapa!
                            </p>
                            <p>
                                Agora, você pode acompanhar todas as novidades e requisitos dessa nova fase
                                diretamente no seu painel no Mapa Cultural do Ceará.
                                Para sua comodidade, disponibilizamos um atalho para sua inscrição:
                                <a href="{{ rtrim(config('app.mapa_url', ''), '/') }}/inscricao/{{ $registration }}">
                                    Acessar Inscrição
                                </a>.
                            </p>
                            <p>Parabéns pelo avanço, <b>{{ $agent_name }}</b>!</p>
                        </main>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@include('emails/footer')
