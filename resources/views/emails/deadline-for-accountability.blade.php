@include('emails/header')
<!-- CONTENT EMAIL -->
<table role="presentation" class="main">
    <tr>
        <td class="">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <main class="wrapper">
                            <p><b>Prezado, espero que esteja bem.</b></p>
                            <p>
                                A Secult vem por meio deste informar que já se passaram
                                <strong>{{$info->days_current}} ({{$days_current}})</strong>
                                dias da data do pagamento do seu projeto cultural.
                                {{ $complement_text }}
                                <strong>{{ $title_report }}</strong>
                                que deverá ser enviado através da plataforma Mapa Cultural.
                            </p>
                            <p>Fique atento aos prazos!</p>
                            <p>
                                Qualquer dúvida, entre em contato com o(a) fiscal do seu projeto.
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
<!-- FIM CONTENT EMAIL -->

<!-- FOOTER -->
@include('emails/footer')
