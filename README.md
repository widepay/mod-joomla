#  Módulo VirtueMart - Joomla para Wide Pay
Módulo desenvolvido para integração entre o sistema Joomla e Wide Pay. Com o módulo é possível gerar cobrança para pagamento e liquidação automática pelo Wide Pay após o recebimento.

* **Versão atual:** 1.0.0
* **Versão VirtueMart Testada:** 3.0
* **Acesso Wide Pay**: [Abrir Link](https://www.widepay.com/acessar)
* **API Wide Pay**: [Abrir Link](https://widepay.github.io/api/index.html)
* **Módulos Wide Pay**: [Abrir Link](https://widepay.github.io/api/modulos.html)

# Instalação Plugin

1. Para a instalação do plugin realize o download pelo link: https://github.com/widepay/mod-joomla
2. Após o download concluído, procure o arquivo: wide-pay.zip
3. Acesse o menu de módulos no Joomla (Extensions -> Manage -> Install), clique em "Upload Package File". Selecione o arquivo *wide-pay.zip*.
4. Logo após o upload, será exibido uma mensagem de sucesso com 2 links. Clique no link para habilitar. e no segundo para configurar.

# Configuração do Plugin
Lembre-se que para esta etapa, o plugin deve estar instalado e ativado no Joomla.

|Campo|Obrigatório|Descrição|
|--- |--- |--- |
|Titulo|**Sim**|Nome que será exibido na tela de pagamento|]
|ID da Carteira Wide Pay |**Sim** |Preencha este campo com o ID da carteira que deseja receber os pagamentos do sistema. O ID de sua carteira estará presente neste link: https://www.widepay.com/conta/configuracoes/carteiras|
|Token da Carteira Wide Pay|**Sim**|Preencha com o token referente a sua carteira escolhida no campo acima. Clique no botão: "Integrações" na página do Wide Pay, será exibido o Token|
|Acréscimo de Dias no Vencimento|Não|Número em dias para o vencimento do Boleto.|
|Configuração de Multa|Não|Configuração de multa após o vencimento. Valor em porcentagem|
|Configuração de Juros|Não|Configuração de juros após o vencimento. Valor em porcentagem|
|Forma de Recebimento|Não|Selecione entre Boleto, Cartão|
|Status Aguardando Pagamento|Não|Status do sistema para aguardando pagamento|
|Status Pago|Não|Status do sistema para fatura paga|