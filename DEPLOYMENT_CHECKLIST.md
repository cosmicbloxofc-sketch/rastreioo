# ✅ Deployment Checklist - Rastreioo

## Status: 100% PRONTO PARA PRODUÇÃO

### 🔒 Segurança
- ✅ Todas as API keys removidas do código
- ✅ `.gitignore` configurado para proteger `.env`, logs, e arquivos sensíveis
- ✅ `.env.example` criado com template de variáveis
- ✅ Credenciais usam `getenv()` para ler de environment variables
- ✅ Dependência `_shared/cpf_bloqueio.php` criada e disponível

### 🚀 Infraestrutura Vercel
- ✅ `vercel.json` configurado com PHP 8.3 runtime
- ✅ API functions estruturadas em `/api/`
- ✅ Rewrite rules configuradas para PHP
- ✅ Environment variables mapeadas no config

### 📡 APIs
- ✅ `/api/consulta.php` - Consulta CPF (JSON response)
- ✅ `/api/pagamento.php` - Gera PIX e processa pagamentos
- ✅ Ambas retornam JSON e suportam CORS

### 🔗 Fluxo de UTM
- ✅ `consulta.html` → `consulta.php` captura params
- ✅ `consulta.php` redireciona `index3.html` com query params preservados
- ✅ `index3.html` → `index4.html` passa todos os params
- ✅ `index4.html` → `index5.html` passa todos os params via `window.location.search`
- ✅ `index5.html` form captura params via `appendTrackingToForm()`
- ✅ Form submission inclui inputs hidden com utm_* e tracking params

### 📝 Documentação
- ✅ `README.md` completo com instruções de deploy
- ✅ Exemplos de API com request/response
- ✅ Troubleshooting guide incluído
- ✅ Segurança e best practices documentados

### 🧪 Validação
- ✅ Estrutura de diretórios correta
- ✅ Todos os arquivos PHP estão presentes
- ✅ Bibliotecas compartilhadas (`consulta_receita_lib.php`) disponíveis
- ✅ Assets (CSS, JS, imagens) intactos
- ✅ Scripts de rastreamento carregados corretamente

### 📦 GitHub
- ✅ Repositório: https://github.com/cyntiaaraujo8419/rastreioo
- ✅ Main branch com todos os commits
- ✅ Histórico limpo (sem secrets no git)
- ✅ Pronto para deploy com `vercel link`

---

## 🎯 Próximos Passos (Deploy)

### 1. Configurar Vercel

```bash
# Opção A: Via Dashboard
1. Ir em https://vercel.com/new
2. Selecionar repositório: cyntiaaraujo8419/rastreioo
3. Clicar em "Deploy"

# Opção B: Via CLI
vercel link
vercel deploy --prod
```

### 2. Adicionar Environment Variables

Na Vercel Dashboard → Settings → Environment Variables:

| Chave | Exemplo | Tipo |
|-------|---------|------|
| `EXPRESSAY_API_KEY` | `sk_live_...` | Segredo |
| `FUSIONPAY_API_KEY` | `sk_...` | Segredo |
| `WINTECH_PUBLIC_KEY` | `...` | Segredo |
| `WINTECH_SECRET_KEY` | `...` | Segredo |
| `GESTAOPAY_PUBLIC_KEY` | `gestaopay_live_...` | Segredo |
| `GESTAOPAY_SECRET_KEY` | `sk_live_...` | Segredo |
| `DEBUG` | `0` | Opcional |

### 3. Testar Endpoints

```bash
# Consulta CPF
curl "https://rastreioo-phi.vercel.app/api/consulta?cpf=12345678901&utm_source=test"

# Pagamento (POST)
curl -X POST "https://rastreioo-phi.vercel.app/api/pagamento?act=wintech_new" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "cpf": "12345678901",
    "email": "joao@example.com",
    "telefone": "11999999999"
  }'
```

### 4. Verificar Domínio

- [ ] Apontar DNS para Vercel (se domínio próprio)
- [ ] SSL/TLS automático (Vercel)
- [ ] CDN global ativado

---

## 📊 Monitoramento

Após deploy, verificar:

1. **Logs da Vercel**: https://vercel.com/dashboard
2. **Erros de PHP**: Environment > Logs
3. **Tráfego**: Analytics > Overview
4. **Performance**: Analytics > Web Vitals

---

## 🆘 Troubleshooting Rápido

| Problema | Solução |
|----------|---------|
| 404 no `/api/` | Verificar `vercel.json` e rebuild |
| 500 em `/api/consulta` | Checar env vars e `consulta_receita_lib.php` |
| Credenciais inválidas | Validar chaves em env vars (case-sensitive) |
| UTM não passando | Verificar console do navegador e Network tab |

---

## ✨ Status Final

**Projeto:** ✅ 100% Pronto para Produção  
**Data:** 2026-08-27  
**Versão:** 1.0.0  
**Ambiente:** Vercel + PHP 8.3 + GitHub  

---

**Desenvolvido para:** Rastreioo  
**Mantido por:** Cadu (eudesdaavid@gmail.com)
