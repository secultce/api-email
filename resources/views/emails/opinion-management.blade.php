@include('emails/header')

<h3>Olá,  {{$agent}} </h3>
<p>
    Temos uma ótima notícia! As justificativas dos pareceristas para a oportunidade
    <b>{{$opportunity}}</b> já estão disponíveis para você.
</p>
<p>
    Você pode acessá-las diretamente no seu painel administrativo no Mapa Cultural do Ceará.
    Basta fazer login no sistema através do link da sua inscrição abaixo e navegar até a seção
    correspondente para visualizar a justificativa completa.
</p>
<p>
    <b>Link da Inscrição:</b> {{$link}}<br/>
    <b>Inscrição: </b> {{$number}}
</p>
<p>
    É fundamental que você verifique essa informação, pois ela é importante para o acompanhamento do seu processo.
</p>
<p>
    Agradecemos a sua participação e o seu contínuo envolvimento com o Mapa Cultural do Ceará.
</p>
<p>

</p>
@include('emails/footer')
