@include('emails/header')

<table role="presentation" class="main">
    <tr>
        <td class="">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <main class="wrapper">
                            <p><b>Olá, espero que esteja bem.</b></p>
                            <p>
                                Com grande satisfação, informamos que sua participação no
                                <strong>{{ $opportunityName }}</strong>
                                alcançou a próxima etapa!.
                            </p>
                            <p>
                                Agora, você pode acompanhar todas as novidades e requisitos dessa nova fase diretamente
                                no seu painel no Mapa Cultural do Ceará. Para sua comodidade, também disponibilizamos
                                um atalho que o(a) levará diretamente à sua inscrição:
                                <span class="apple-link">
                                    <span>
                                        <a href="{{ env('MAPA_URL') }}inscricao/{{ $registration }}">Acessar Inscrição</a>.
                                    </span>
                                </span>
                            </p>
                            <p>
                                Parabéns pelo avanço! {{$owner}}
                            </p>
                            <p>

                            </p>
                        </main>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@include('emails/footer')
