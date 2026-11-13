<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="NAPI é uma plataforma em construção para organizar avaliações autorizadas de segurança de APIs, findings, evidências, correções e retestes.">
        <meta name="theme-color" content="#0d0e14">
        <meta property="og:type" content="website">
        <meta property="og:title" content="NAPI — Segurança de APIs com evidência">
        <meta property="og:description" content="Do inventário ao reteste: uma plataforma para tornar revisões de segurança de APIs claras, rastreáveis e acionáveis.">

        <title>NAPI — Segurança de APIs com evidência</title>

        <link rel="icon" href="{{ asset('napi-mark.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    </head>
    <body>
        <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

        <header class="site-header">
            <div class="site-container header-inner">
                <a class="brand" href="#topo" aria-label="NAPI — início">
                    <img src="{{ asset('napi-mark.svg') }}" width="36" height="36" alt="">
                    <span class="brand-copy">
                        <strong>NAPI</strong>
                        <small>Security review</small>
                    </span>
                </a>

                <nav class="site-nav" aria-label="Navegação principal">
                    <a href="#produto">Produto</a>
                    <a href="#fluxo">Como funciona</a>
                    <a href="#roadmap">Roadmap</a>
                </nav>

                <a class="button button-compact button-secondary" href="{{ route('admin.home') }}">
                    Área administrativa
                    <svg aria-hidden="true" viewBox="0 0 20 20">
                        <path d="m7.5 4.5 5.5 5.5-5.5 5.5M3 10h10" />
                    </svg>
                </a>
            </div>
        </header>

        <main id="conteudo">
            <section class="hero" id="topo" aria-labelledby="hero-title">
                <div class="site-container hero-grid">
                    <div class="hero-copy">
                        <p class="eyebrow"><span></span> API security com contexto e rastreabilidade</p>
                        <h1 id="hero-title">Segurança de APIs, do finding à <em>correção comprovada.</em></h1>
                        <p class="hero-lead">
                            O NAPI está sendo construído para ajudar software houses e equipes de desenvolvimento a organizar avaliações autorizadas, explicar riscos e acompanhar cada correção até o reteste.
                        </p>

                        <div class="hero-actions">
                            <a class="button button-primary" href="#produto">
                                Explorar o NAPI
                                <svg aria-hidden="true" viewBox="0 0 20 20">
                                    <path d="m7.5 4.5 5.5 5.5-5.5 5.5M3 10h10" />
                                </svg>
                            </a>
                            <a class="button button-secondary" href="https://github.com/RafaSantos04" target="_blank" rel="noreferrer">
                                Falar com o desenvolvedor
                            </a>
                        </div>

                        <ul class="hero-signals" aria-label="Princípios do produto">
                            <li><span aria-hidden="true">✓</span> Avaliações autorizadas</li>
                            <li><span aria-hidden="true">✓</span> Backend-first</li>
                            <li><span aria-hidden="true">✓</span> Construção aberta</li>
                        </ul>
                    </div>

                    <div class="product-visual" aria-label="Representação conceitual do fluxo futuro do NAPI">
                        <div class="visual-toolbar">
                            <div class="visual-brand">
                                <img src="{{ asset('napi-mark.svg') }}" width="24" height="24" alt="">
                                <span>NAPI / SECURITY REVIEW</span>
                            </div>
                            <span class="concept-badge">Visão do produto</span>
                        </div>

                        <div class="visual-context">
                            <span>WORKSPACE</span>
                            <strong>API de cliente autorizada</strong>
                            <span class="context-status"><i></i> Escopo registrado</span>
                        </div>

                        <div class="visual-main">
                            <div class="flow-map">
                                <p class="visual-label">Fluxo pretendido</p>
                                <ol>
                                    <li class="is-available">
                                        <span>01</span>
                                        <div><strong>Inventário</strong><small>Contrato e endpoints</small></div>
                                        <i aria-hidden="true">→</i>
                                    </li>
                                    <li>
                                        <span>02</span>
                                        <div><strong>Assessment</strong><small>Revisão assistida</small></div>
                                        <i aria-hidden="true">→</i>
                                    </li>
                                    <li>
                                        <span>03</span>
                                        <div><strong>Finding</strong><small>Risco e evidência</small></div>
                                        <i aria-hidden="true">→</i>
                                    </li>
                                    <li>
                                        <span>04</span>
                                        <div><strong>Reteste</strong><small>Correção comprovada</small></div>
                                    </li>
                                </ol>
                            </div>

                            <div class="finding-preview">
                                <div class="preview-heading">
                                    <div>
                                        <p class="visual-label">Exemplo conceitual</p>
                                        <strong>Finding estruturado</strong>
                                    </div>
                                    <span class="severity">ALTO</span>
                                </div>
                                <div class="endpoint"><span>GET</span> /api/v1/resources/{id}</div>
                                <div class="preview-grid">
                                    <p><span>Impacto</span>Explicação orientada à decisão</p>
                                    <p><span>Evidência</span>Registro privado e rastreável</p>
                                    <p><span>Correção</span>Recomendação prática</p>
                                    <p><span>Reteste</span>Histórico sem sobrescrever</p>
                                </div>
                            </div>
                        </div>

                        <div class="visual-footer">
                            <span><i></i> IAM em camadas</span>
                            <span><i></i> Isolamento por organização</span>
                            <span><i></i> Auditoria</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="foundation" aria-labelledby="foundation-title">
                <div class="site-container foundation-inner">
                    <p id="foundation-title">Fundação já disponível</p>
                    <ul>
                        <li><span>01</span> IAM e permissões</li>
                        <li><span>02</span> Isolamento multi-tenant</li>
                        <li><span>03</span> Trilha de auditoria</li>
                        <li><span>04</span> Security Lab controlado</li>
                    </ul>
                </div>
            </section>

            <section class="section" id="produto" aria-labelledby="product-title">
                <div class="site-container">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow"><span></span> O que é o NAPI</p>
                            <h2 id="product-title">Uma revisão de segurança que não termina no PDF.</h2>
                        </div>
                        <p>
                            O objetivo é reunir o trabalho que hoje fica espalhado entre planilhas, capturas de tela e documentos, preservando contexto, autoria e histórico.
                        </p>
                    </div>

                    <div class="value-grid">
                        <article class="value-card">
                            <span class="card-index">01 / ORGANIZAR</span>
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M4 6.5h16v12H4zM8 3.5h8M8 10h8M8 14h5" />
                            </svg>
                            <h3>Organize o escopo</h3>
                            <p>Conecte organização, projeto, aplicação, ambiente e contrato da API antes de iniciar qualquer avaliação.</p>
                        </article>

                        <article class="value-card">
                            <span class="card-index">02 / EXPLICAR</span>
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 3 20 6v5.5c0 5.2-3.2 8.5-8 9.5-4.8-1-8-4.3-8-9.5V6l8-3ZM9.5 12l1.7 1.7 3.6-4" />
                            </svg>
                            <h3>Transforme risco em ação</h3>
                            <p>Estruture finding, impacto, evidência e recomendação para quem precisa decidir e para quem precisa corrigir.</p>
                        </article>

                        <article class="value-card">
                            <span class="card-index">03 / COMPROVAR</span>
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M5 4h11l3 3v13H5zM15 4v4h4M8 12h8M8 16h6" />
                            </svg>
                            <h3>Preserve a evolução</h3>
                            <p>Mantenha evidências, remediações e retestes no mesmo histórico até uma entrega compreensível para o cliente.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section principle-section" aria-labelledby="principle-title">
                <div class="site-container principle-grid">
                    <div class="principle-copy">
                        <p class="eyebrow"><span></span> Segurança responsável</p>
                        <h2 id="principle-title">Sem promessas de caixa-preta. Com escopo, contexto e evidência.</h2>
                        <p>
                            O primeiro produto é um workspace de Security Review manual e assistido. Automação só entra quando avaliações reais mostrarem quais checks são repetíveis, seguros e valiosos.
                        </p>
                        <a class="text-link" href="https://github.com/RafaSantos04/NAPI" target="_blank" rel="noreferrer">
                            Acompanhar o projeto no GitHub
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>

                    <div class="principle-terminal" aria-label="Princípios de execução do NAPI">
                        <div class="terminal-bar">
                            <span></span><span></span><span></span>
                            <strong>napi://principles</strong>
                        </div>
                        <dl>
                            <div>
                                <dt>01 / AUTORIZAÇÃO</dt>
                                <dd>Alvo e escopo registrados antes da execução.</dd>
                                <span>REQUIRED</span>
                            </div>
                            <div>
                                <dt>02 / ISOLAMENTO</dt>
                                <dd>Dados de cada organização protegidos por contexto.</dd>
                                <span>ENFORCED</span>
                            </div>
                            <div>
                                <dt>03 / RASTREABILIDADE</dt>
                                <dd>Decisões e mudanças com autoria e histórico.</dd>
                                <span>AUDITED</span>
                            </div>
                            <div>
                                <dt>04 / TRANSPARÊNCIA</dt>
                                <dd>Resultado explicado sem prometer cobertura total.</dd>
                                <span>EXPLICIT</span>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="section" id="fluxo" aria-labelledby="workflow-title">
                <div class="site-container">
                    <div class="section-heading workflow-heading">
                        <div>
                            <p class="eyebrow"><span></span> Como o produto evolui</p>
                            <h2 id="workflow-title">Uma cadeia verificável, construída por etapas.</h2>
                        </div>
                        <p>O roadmap é backend-first: cada domínio é estabilizado antes da experiência SaaS em Vue consumir a API.</p>
                    </div>

                    <ol class="workflow">
                        <li class="workflow-item is-done">
                            <div class="workflow-number">01</div>
                            <div class="workflow-content">
                                <span class="status status-done">Disponível</span>
                                <h3>Fundação e contexto</h3>
                                <p>IAM, organizações, projetos, aplicações, ambientes, autorização em camadas e auditoria.</p>
                            </div>
                        </li>
                        <li class="workflow-item is-active">
                            <div class="workflow-number">02</div>
                            <div class="workflow-content">
                                <span class="status status-active">Em desenvolvimento</span>
                                <h3>Inventário OpenAPI</h3>
                                <p>Importação segura, versionamento do contrato e catálogo consultável de endpoints.</p>
                            </div>
                        </li>
                        <li class="workflow-item">
                            <div class="workflow-number">03</div>
                            <div class="workflow-content">
                                <span class="status">Planejado</span>
                                <h3>Assessments e findings</h3>
                                <p>Checklist, workflow, impacto, recomendação e evidência privada para cada problema.</p>
                            </div>
                        </li>
                        <li class="workflow-item">
                            <div class="workflow-number">04</div>
                            <div class="workflow-content">
                                <span class="status">Planejado</span>
                                <h3>Retestes e relatórios</h3>
                                <p>Histórico before/after, resultado rastreável e entrega profissional para o cliente.</p>
                            </div>
                        </li>
                        <li class="workflow-item">
                            <div class="workflow-number">05</div>
                            <div class="workflow-content">
                                <span class="status">Futuro</span>
                                <h3>Workspace SaaS em Vue</h3>
                                <p>Experiência do cliente sobre um contrato de API estável, sem duplicar regras de domínio.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="section roadmap-section" id="roadmap" aria-labelledby="roadmap-title">
                <div class="site-container roadmap-grid">
                    <div>
                        <p class="eyebrow"><span></span> Construção aberta</p>
                        <h2 id="roadmap-title">O NAPI está ganhando forma — com as decisões visíveis.</h2>
                        <p class="roadmap-copy">
                            Arquitetura, threat model, ADRs, fases e critérios de segurança vivem no próprio repositório. Isso permite acompanhar não só o que está pronto, mas por que cada decisão foi tomada.
                        </p>
                    </div>
                    <div class="roadmap-links">
                        <a href="https://github.com/RafaSantos04/NAPI" target="_blank" rel="noreferrer">
                            <span>
                                <small>CÓDIGO E DOCUMENTAÇÃO</small>
                                <strong>Explorar o repositório</strong>
                            </span>
                            <i aria-hidden="true">↗</i>
                        </a>
                        <a href="{{ route('admin.home') }}">
                            <span>
                                <small>ACESSO RESTRITO</small>
                                <strong>Entrar na área administrativa</strong>
                            </span>
                            <i aria-hidden="true">→</i>
                        </a>
                    </div>
                </div>
            </section>

            <section class="contact-section" aria-labelledby="contact-title">
                <div class="site-container contact-card">
                    <div>
                        <p class="eyebrow"><span></span> Conversa aberta</p>
                        <h2 id="contact-title">Tem uma API para revisar ou uma ideia para o projeto?</h2>
                        <p>Estou construindo o NAPI e quero conversar com software houses, times SaaS e pessoas que lidam com segurança de APIs na prática.</p>
                    </div>
                    <a class="button button-primary" href="https://github.com/RafaSantos04" target="_blank" rel="noreferrer">
                        Entrar em contato
                        <svg aria-hidden="true" viewBox="0 0 20 20">
                            <path d="m7.5 4.5 5.5 5.5-5.5 5.5M3 10h10" />
                        </svg>
                    </a>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div class="site-container footer-inner">
                <a class="brand footer-brand" href="#topo">
                    <img src="{{ asset('napi-mark.svg') }}" width="30" height="30" alt="">
                    <span class="brand-copy"><strong>NAPI</strong><small>Security review</small></span>
                </a>
                <p>Segurança de APIs com contexto, evidência e evolução rastreável.</p>
                <div>
                    <a href="https://github.com/RafaSantos04/NAPI" target="_blank" rel="noreferrer">GitHub</a>
                    <a href="{{ route('admin.home') }}">Admin</a>
                </div>
            </div>
        </footer>
    </body>
</html>
