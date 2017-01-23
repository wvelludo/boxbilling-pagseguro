# README #

Módulo Boxbilling para pagamento via PagSeguro

# INSTALACAO #

1. Fazer o upload dos arquivos para seu whmcs seguindo a estrutura de diretórios para o diretório de instalação do BoxBilling.
2. Acessar seu BoxBilling e ir em "Configurações > Gateways de Pagamento < Novo Gateway de Pagamento" e fazer a ativação do PagSeguro.
3. Edite o módulo configurando seu e-mail, token e o campo com o CPF/CNPJ.
4. Faça o upload da imagem "pagseguro.gif (Ex: "bb-themes/huraga/assets/img/gateway_logos/")
5. Para que o botão de pagamento seja exibido corretamente altere o arquivo "logos.css" que fica na pasta de seu template (Ex: "bb-themes/huraga/assets/css/logos.css") e adicione o as seguintes linhas:

.logo-PagSeguro{
   background: transparent url("../img/gateway_logos/pagseguro.gif") no-repeat scroll 0% 0%;
    background-size: contain;
    width:209px;
    height: 48px;
    border: 0;
    margin: 10px;
}

