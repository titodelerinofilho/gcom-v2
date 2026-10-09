"use client";
import Link from "next/link";
import { useState } from "react";
import { ArrowUpRight, BookOpen, CheckCircle2, ChevronDown, Search, X } from "lucide-react";
import { faqTopics, normalizeFaqText } from "@/lib/faq";
import { Heading } from "./collections";
import { allowed, useUser } from "./shell";

const journey = [
  { title: "Selecionar", text: "Informe o cliente principal e selecione os pedidos elegíveis." },
  { title: "Conferir", text: "Simule, escolha as devoluções e revise a memória de cálculo." },
  { title: "Aprovar", text: "Registre a comissão e encaminhe para a conferência do Financeiro." },
  { title: "Pagar", text: "Confirme o pagamento realizado e confira o comprovante." },
];
export function FaqPage() {
  const user = useUser();
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState("all");
  const search = normalizeFaqText(query);
  const tokens = search.split(/\s+/).filter((token) => 0 < token.length);
  const topics = faqTopics
    .filter((topic) => "all" === selected || topic.id === selected)
    .map((topic) => {
      const topicText = normalizeFaqText(
        [topic.title, topic.description, ...topic.steps].join(" "),
      );
      const questions = topic.questions.filter((entry) => {
        const text = `${topicText} ${normalizeFaqText([entry.question, ...entry.answer].join(" "))}`;
        return tokens.every((token) => text.includes(token));
      });
      return { ...topic, questions };
    })
    .filter((topic) => 0 < topic.questions.length);
  const count = topics.reduce((total, topic) => total + topic.questions.length, 0);
  const totalQuestions = faqTopics.reduce((total, topic) => total + topic.questions.length, 0);
  const reset = () => {
    setQuery("");
    setSelected("all");
  };
  return (
    <div className="faq-page">
      <Heading
        eyebrow="CENTRAL DE AJUDA"
        title="FAQ e guia de uso"
        text="Encontre o passo a passo de cada área e tire suas dúvidas sobre a operação do GCOM."
      />
      <section className="panel faq-search-panel" aria-label="Busca na ajuda">
        <div className="faq-search-intro">
          <span className="faq-book">
            <BookOpen size={23} aria-hidden="true" />
          </span>
          <div>
            <h2>Como podemos ajudar?</h2>
            <p>Busque por uma dúvida, uma tela ou uma etapa do processo.</p>
          </div>
        </div>
        <label className="faq-search-label" htmlFor="faq-search">
          Buscar na ajuda
        </label>
        <div className="faq-search-input">
          <Search size={20} aria-hidden="true" />
          <input
            id="faq-search"
            type="search"
            placeholder="Ex.: aprovar comissão, devolução, RECNUM ou logo"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setSelected("all");
            }}
          />
          {0 < query.length && (
            <button
              type="button"
              className="icon-button"
              aria-label="Limpar busca"
              onClick={() => setQuery("")}
            >
              <X size={18} />
            </button>
          )}
        </div>
        <span className="faq-search-hint">
          {faqTopics.length} tópicos · {totalQuestions} perguntas · Instruções para os recursos
          desta versão
        </span>
      </section>
      {"all" === selected && 0 === search.length && (
        <section className="panel faq-journey" aria-labelledby="faq-journey-title">
          <div className="faq-section-heading">
            <div>
              <span className="eyebrow">COMECE POR AQUI</span>
              <h2 id="faq-journey-title">O caminho de uma comissão</h2>
            </div>
            <Link href="/commissions" className="text-link">
              Abrir comissões <ArrowUpRight size={15} aria-hidden="true" />
            </Link>
          </div>
          <ol>
            {journey.map((step, index) => (
              <li key={step.title}>
                <span className="faq-step-number">{index + 1}</span>
                <h3>{step.title}</h3>
                <p>{step.text}</p>
              </li>
            ))}
          </ol>
        </section>
      )}
      <div className="faq-layout">
        <div className="panel faq-mobile-topics">
          <label htmlFor="faq-mobile-topic">Explorar por tópico</label>
          <select
            id="faq-mobile-topic"
            value={selected}
            onChange={(event) => setSelected(event.target.value)}
          >
            <option value="all">Todos os tópicos</option>
            {faqTopics.map((topic) => (
              <option key={topic.id} value={topic.id}>
                {topic.title}
              </option>
            ))}
          </select>
        </div>
        <nav className="panel faq-topic-nav" aria-label="Tópicos da ajuda">
          <h2>Explorar por tópico</h2>
          <button
            type="button"
            className={"all" === selected ? "active" : ""}
            aria-pressed={"all" === selected}
            onClick={() => setSelected("all")}
          >
            <span>Todos os tópicos</span>
            <span className="faq-topic-count">{totalQuestions}</span>
          </button>
          {faqTopics.map((topic) => (
            <button
              type="button"
              key={topic.id}
              className={topic.id === selected ? "active" : ""}
              aria-pressed={topic.id === selected}
              onClick={() => setSelected(topic.id)}
            >
              <span>{topic.title}</span>
              <span className="faq-topic-count">{topic.questions.length}</span>
            </button>
          ))}
        </nav>
        <div className="faq-results">
          <p className="faq-result-count" role="status" aria-live="polite">
            {count} {1 === count ? "pergunta encontrada" : "perguntas encontradas"}
            {0 < search.length ? ` para “${query.trim()}”` : ""}
          </p>
          {0 === topics.length ? (
            <section className="panel faq-empty">
              <Search size={30} aria-hidden="true" />
              <h2>Nenhuma resposta encontrada</h2>
              <p>Tente outro termo, como pagamento, cliente, pedido ou devolução.</p>
              <button type="button" className="button secondary" onClick={reset}>
                Ver todos os tópicos
              </button>
            </section>
          ) : (
            topics.map((topic) => (
              <section
                key={topic.id}
                id={`faq-${topic.id}`}
                className="panel faq-topic"
                aria-labelledby={`faq-title-${topic.id}`}
              >
                <div className="faq-section-heading">
                  <div>
                    <span className="faq-audience">{topic.audience}</span>
                    <h2 id={`faq-title-${topic.id}`}>{topic.title}</h2>
                  </div>
                  {undefined !== topic.href &&
                    (undefined === topic.linkRole || true === allowed(user, topic.linkRole)) && (
                      <Link
                        href={topic.href}
                        className="faq-area-link"
                        aria-label={`Abrir área: ${topic.title}`}
                      >
                        <ArrowUpRight size={19} aria-hidden="true" />
                        <span>Abrir área</span>
                      </Link>
                    )}
                </div>
                <p className="faq-topic-description">{topic.description}</p>
                <div className="faq-howto">
                  <h3>
                    <CheckCircle2 size={17} aria-hidden="true" /> Passo a passo
                  </h3>
                  <ol>
                    {topic.steps.map((step) => (
                      <li key={step}>{step}</li>
                    ))}
                  </ol>
                </div>
                <h3 className="faq-questions-heading">Perguntas frequentes</h3>
                <div className="faq-questions">
                  {topic.questions.map((entry) => (
                    <details key={entry.question} open={0 < search.length}>
                      <summary>
                        <span>{entry.question}</span>
                        <ChevronDown size={18} aria-hidden="true" />
                      </summary>
                      <div className="faq-answer">
                        {entry.answer.map((paragraph) => (
                          <p key={paragraph}>{paragraph}</p>
                        ))}
                      </div>
                    </details>
                  ))}
                </div>
              </section>
            ))
          )}
          <aside className="faq-support">
            <BookOpen size={20} aria-hidden="true" />
            <div>
              <h2>Precisa de ajuda com um caso específico?</h2>
              <p>
                Informe ao responsável a tela, o horário, o pedido ou comissão e o identificador da
                requisição, quando houver. Confira se a operação já foi registrada antes de
                repeti-la.
              </p>
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}
