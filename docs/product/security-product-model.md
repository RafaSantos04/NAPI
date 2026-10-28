# Modelo de segurança do produto

[← Produto](README.md) · [Modelo de produto](product-model.md) · [Security Lab atual](../modules/security.md)

## Conceitos que não são sinônimos

| Conceito | Alvo | Finalidade | Execução |
|---|---|---|---|
| `SecurityTestRun` | recurso e personas sintéticos do NAPI | ensinar/demonstrar vulnerável × protegido | já existe no Lab |
| `SecurityAssessment` | aplicação real registrada pelo cliente | organizar uma avaliação autorizada | manual/assistida primeiro |
| `SecurityFinding` | problema observado num assessment | acompanhar risco, evidência e correção | novo domínio em P5 |
| `SecurityCheckRun` | alvo autorizado e check automatizável | produzir observação/evidência repetível | futuro, P10 |

`SecurityTestRun` não será generalizado para os outros três. O Lab pode ser
referenciado por categoria do finding (“Aprenda no Lab”), mas não compartilha
runner, tabela de alvo, evidência nem ciclo de vida.

## Fluxo inicial

```text
escopo autorizado + versão OpenAPI
        → assessment manual/assistido
        → finding
        → evidência protegida
        → orientação/remediação
        → reteste
        → relatório
```

Automação só substitui etapas específicas quando houver contrato de check,
isolamento e resultado confiável. Erro técnico nunca deve ser classificado
como “protegido”, preservando a regra já aplicada no Security Lab.

## Fronteiras obrigatórias antes de testar alvos reais

1. **Autorização do alvo:** organização, responsável, escopo permitido,
   ambientes e janela de execução registrados.
2. **Tenant:** Policy em toda leitura/escrita; IDs não bastam para provar
   pertencimento.
3. **Saída de rede:** allowlist, bloqueio de SSRF e de destinos não
   autorizados, DNS/IP revalidados e limites de timeout, concorrência e taxa.
4. **Execução isolada:** checks automatizados em workers separados da
   aplicação web, com credenciais de mínimo privilégio e kill switch.
5. **Segredos:** credenciais de teste cifradas, nunca registradas em logs,
   evidências ou relatórios sem redaction.
6. **Evidência:** storage privado, autorização por objeto, retenção,
   integridade e registro de download/compartilhamento.
7. **Auditoria:** append-only efetivo antes de afirmar rastreabilidade
   comercial (relacionado ao FIND-014).
8. **Lab:** continua desligado por padrão e sem qualquer caminho para dados de
   clientes.

## Limite seguro do MVP

P3 importa arquivos OpenAPI enviados pelo usuário; não precisa buscar URLs
arbitrárias nem resolver referências remotas. P4–P7 suportam avaliações
manuais/assistidas sem tráfego ativo do NAPI contra o alvo. Isso entrega
valor enquanto as fronteiras de automação são projetadas e validadas.

## Evidência e privacidade

Evidências podem conter tokens, dados pessoais e respostas de produção. O
modelo deve favorecer metadados estruturados e anexos privados, com
redaction antes da persistência. Relatórios devem incluir somente cópias
selecionadas; links compartilháveis precisam ser revogáveis, expiráveis e
limitados ao snapshot publicado.

## Relação com categorias

Finding deve usar uma taxonomia estável e extensível (por exemplo, categoria
interna com referências OWASP/CWE opcionais), sem acoplar o workflow a uma
versão específica de uma lista externa. A ligação para um Lab é opcional e
feita por categoria, não por compartilhar models.

