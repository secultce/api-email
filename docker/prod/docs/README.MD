
### ✨ CRIAR IMAGEM MANUALMENTE

✏️ Deve está na raiz do projeto e rodar o seguinte comando:

 - `docker build --no-cache -t secultceara/api-email:latest -f docker/prod/php-fpm/Dockerfile .`

✏️ Para enviar a imagem criada manualemente para o Docker Hub deve realizar os passos:

 1. Criar login da conta da secultceara com:  `docker login -u {username}`
 2. Depois digitar a senha e após realizar o login com sucesso;
 3. Rodar o comando: `docker push docker.io/secultceara/api-email:latest`