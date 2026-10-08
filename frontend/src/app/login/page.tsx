"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ArrowRight, Eye, EyeOff } from "lucide-react";
import { api } from "@/lib/api";
import { Brand } from "@/components/brand";
import { ErrorNotice } from "@/components/ui";

export default function Login() {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [passwordVisible, setPasswordVisible] = useState(false);
  const router = useRouter();

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const fields = new FormData(event.currentTarget);
    setBusy(true);
    setError("");

    try {
      await api("/login", {
        method: "POST",
        body: JSON.stringify({ email: fields.get("email"), password: fields.get("password") }),
      });
      router.replace("/");
    } catch (exception) {
      setError((exception as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="access-page">
      <header className="access-header">
        <Brand />
        <span className="access-environment">Uso interno da distribuidora</span>
      </header>

      <div className="access-content">
        <section className="access-introduction" aria-labelledby="access-title">
          <p className="access-kicker">VENDAS AGENCIADAS · WINTHOR</p>
          <h1 id="access-title">
            Comissões de
            <br />
            vendas agenciadas.
          </h1>
          <p className="access-description">
            Sistema de gestão de comissões de vendas agenciadas para distribuidores que utilizam o
            Winthor.
          </p>
          <p className="access-context">
            Do pedido faturado ao pagamento, confira a apuração por produto e acompanhe os débitos,
            cancelamentos e devoluções da operação.
          </p>

          <ol className="access-workflow" aria-label="Etapas da gestão de comissões">
            <li>
              <span className="access-step" aria-hidden="true">
                01
              </span>
              <div>
                <strong>Pedidos do Winthor</strong>
                <p>Pedidos faturados, itens e referências de preço.</p>
              </div>
            </li>
            <li>
              <span className="access-step" aria-hidden="true">
                02
              </span>
              <div>
                <strong>Apuração da comissão</strong>
                <p>Cálculo por produto e deduções do cliente principal.</p>
              </div>
            </li>
            <li>
              <span className="access-step" aria-hidden="true">
                03
              </span>
              <div>
                <strong>Conferência e pagamento</strong>
                <p>Valor líquido, aprovação e histórico dos lançamentos.</p>
              </div>
            </li>
          </ol>
        </section>

        <section className="access-entry" aria-labelledby="access-form-title">
          <form className="access-form" onSubmit={submit} aria-busy={busy}>
            <div className="access-form-heading">
              <p className="access-kicker">CONTA DE ACESSO</p>
              <h2 id="access-form-title">Entrar no GCOM</h2>
              <p>Use a conta cadastrada pelo administrador da sua distribuidora.</p>
            </div>

            <ErrorNotice message={error} />

            <label htmlFor="access-email">Email</label>
            <input
              id="access-email"
              name="email"
              type="email"
              required
              autoComplete="username"
              autoCapitalize="none"
              spellCheck={false}
              disabled={busy}
              placeholder="nome@distribuidora.com.br"
            />

            <label htmlFor="access-password">Senha</label>
            <div className="access-password">
              <input
                id="access-password"
                name="password"
                type={true === passwordVisible ? "text" : "password"}
                required
                autoComplete="current-password"
                disabled={busy}
              />
              <button
                type="button"
                className="access-password-toggle"
                aria-label={true === passwordVisible ? "Ocultar senha" : "Mostrar senha"}
                aria-pressed={passwordVisible}
                aria-controls="access-password"
                disabled={busy}
                onClick={() => setPasswordVisible((visible) => false === visible)}
              >
                {true === passwordVisible ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>

            <button className="button primary access-submit" disabled={busy}>
              {true === busy ? "Entrando…" : "Entrar"}
              <ArrowRight size={18} aria-hidden="true" />
            </button>

            <p className="access-help">
              Precisa de acesso ou esqueceu a senha?
              <br />
              Procure o administrador do sistema.
            </p>
          </form>
        </section>
      </div>

      <footer className="access-footer">
        <span>GCOM · Gestão de comissões</span>
        <span>Pedidos, deduções e pagamentos com histórico de conferência.</span>
      </footer>
    </main>
  );
}
