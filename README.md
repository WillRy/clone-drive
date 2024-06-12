# Cloud de arquivos

Aplicação similar ao google drive feita com Laravel 11 e Vue 3.

Possui funcionalidades como:
- Criação de pastas multinível
- Upload de arquivos e pastas
- Deletar e baixar arquivos/pastas
- Pesquisar por arquivos/pastas
- Compartilhar arquivos/pastas com outros usuários
- Upload de arquivos para bucket

![Pagina inicial](./midia/fcloud-home.png)


## Executar projeto

```shell
docker compose up -d

npm install

npm run dev
```

## Autenticar

**usuario**: admin@admin.com
**senha**: password


## s3 bucket 

O ambiente docker possui o "minio" para simular um ambiente s3, acessível na URL:

http://localhost:9000
