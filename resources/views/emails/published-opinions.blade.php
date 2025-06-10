@include('emails/header')

<table role="presentation" class="main">
    <tr>
        <td>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <main class="wrapper">
                            <p><b>Prezado(a), espero que esteja bem.</b></p>
                            <p>
                                A Secult vem por meio deste informar que os pareceres referentes ao projeto inscrito na oportunidade
                                <strong>{{ $opportunity['name'] }}</strong>
                                com número de inscrição
                                <strong>{{ $registration['number'] }}</strong>
                                foram publicados.
                                <br>
                                Para visualizar, acesse o link a seguir da sua
                                <span class="apple-link">
                                    <span>
                                        <a href="{{ $registration['url'] }}"> página de inscrição</a>.
                                    </span>
                                </span>
                                <code><pre>{{ $registration['url'] }}</pre></code>
                            </p>
                            <p>
                                Cordialmente,
                            </p>
                            <p>
                                Secretaria Estadual da Cultura do Ceará.
                            </p>
                        </main>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@include('emails/footer')
