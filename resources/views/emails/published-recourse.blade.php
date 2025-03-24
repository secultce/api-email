@include('emails/header')

<table role="presentation" class="main">
    <tr>
        <td class="">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <main class="wrapper">
                            <p><b>Prezado(a), espero que esteja bem.</b></p>
                            <p>
                                A Secult vem por meio deste informar que a resposta do seu recurso do(a)
                                <strong>{{ $opportunityName }}</strong>
                                foi publicada.
                                Para visualizar, acesse o link:
                                <span class="apple-link">
                                    <span>
                                        <a href="{{ env('MAPA_URL') }}recursos/agent/{{ $agentId }}">Acessar recurso</a>.
                                    </span>
                                </span>
                            </p>
                            <p>
                                Cordialmente,
                            </p>
                            <p>
                                Secretaria Estadual de Cultural do Ceará.
                            </p>
                        </main>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@include('emails/footer')
