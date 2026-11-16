# Cerco de Jericó — Paróquia São José (São José do Barreiro - SP)

Aplicação web para escala e agendamento de horários de adoração ao Santíssimo Sacramento (24 horas por dia, de 15 a 22 de Novembro) e Livro de Intenções.

## Uso Local (XAMPP / PHP)
- Acesse `http://localhost:8080/adoracao-sjb/`
- Os dados são salvos em JSON na pasta `data/` via `api.php`.
- Modo administrador: adicione `?admin=true` à URL para editar a frase, enviar escala no WhatsApp, remover inscritos ou restaurar horários.

## Publicação no Cloudflare Pages
Inclui suporte via `functions/api.js` e `functions/api.php.js` utilizando Cloudflare KV (`ADORACAO_KV`).
