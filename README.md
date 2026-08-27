# Rastreioo - Sistema de Rastreamento e Pagamento

Sistema de consulta de CPF via Receita Federal com integração de pagamento via PIX (GestãoPay).

## Estrutura do Projeto

```
├── /api/                    # Funções serverless (Vercel)
│   ├── consulta.php        # API de consulta de CPF
│   └── pagamento.php       # API de pagamento
├── /pagamento/             # Sistema de pagamento (legado, migrando para /api)
├── /assets/                # Imagens, CSS, JS
├── index.html              # Página inicial
├── consulta.html           # Página de consulta
├── index3.html             # Página de confirmação de dados
├── vercel.json            # Configuração Vercel
└── .env.example           # Exemplo de variáveis de ambiente
```

## Deploy na Vercel

### 1. Clonar o repositório

```bash
git clone https://github.com/cyntiaaraujo8419/rastreioo.git
cd rastreioo
```

### 2. Conectar ao Vercel

```bash
vercel link
# ou via dashboard: vercel.com/import
```

### 3. Configurar variáveis de ambiente

Na dashboard do Vercel, adicione as variáveis em **Settings > Environment Variables**:

- `EXPRESSAY_API_KEY` - Chave ExpressPay
- `FUSIONPAY_API_KEY` - Chave FusionPay
- `WINTECH_PUBLIC_KEY` - Chave pública Wintech
- `WINTECH_SECRET_KEY` - Chave secreta Wintech
- `GESTAOPAY_PUBLIC_KEY` - Chave pública GestãoPay
- `GESTAOPAY_SECRET_KEY` - Chave secreta GestãoPay

### 4. Deploy

```bash
vercel deploy --prod
```

Ou fazer push para `main` (auto-deploy configurado).

## Local Development

### 1. Instalar dependências

```bash
# Se usar PHP local
php -S localhost:8000
```

### 2. Criar .env.local

```bash
cp .env.example .env.local
# Edite .env.local com suas credenciais de teste
```

### 3. Acessar

```
http://localhost:8000
```

## APIs

### GET /api/consulta

Consulta dados de CPF na Receita Federal.

**Request:**
```
GET /api/consulta?cpf=12345678901&utm_source=test
```

**Response:**
```json
{
  "status": 200,
  "success": true,
  "redirect": "index3.html?cpf=...",
  "dados": {
    "cpf": "12345678901",
    "nome": "João Silva",
    "data_nascimento": "01/01/1990",
    "sexo": "M"
  }
}
```

### POST /api/pagamento?act=wintech_new

Gera um PIX para pagamento.

**Request:**
```json
{
  "name": "João Silva",
  "cpf": "12345678901",
  "email": "joao@example.com",
  "telefone": "11999999999",
  "valor": 6161
}
```

**Response:**
```json
{
  "pixQrCode": "00020126360014br.gov.bcb.brcode...",
  "pixCode": "00020126360014br.gov.bcb.brcode...",
  "orderId": "pedido_xxx",
  "provider": "gestaopay"
}
```

## Segurança

⚠️ **Importante:**

1. **Nunca** coloque chaves no código
2. Use `.env.local` para desenvolvimento
3. Configure variáveis no Vercel Dashboard para produção
4. Arquivo `.gitignore` já protege arquivos sensíveis

## Troubleshooting

### PHP não executa

Certifique-se de que `vercel.json` está correto e as funções estão em `/api/`.

### Erro 500 em /api/consulta

Verifique se `consulta_receita_lib.php` está no caminho correto.

### Credenciais não reconhecidas

Verifique as variáveis de ambiente no Vercel Dashboard.

## Próximos passos

- [ ] Migrar armazenamento de pedidos para banco de dados (Supabase/MongoDB)
- [ ] Adicionar testes unitários
- [ ] Implementar webhook de status de pagamento
- [ ] Adicionar autenticação

---

**Desenvolvido para: Rastreioo**
**Data**: 2026-08-27
