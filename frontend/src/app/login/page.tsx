"use client";
import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ArrowRight, ShieldCheck, Layers3, Fingerprint } from "lucide-react";
import { api } from "@/lib/api";
import { Brand } from "@/components/brand";
import { ErrorNotice } from "@/components/ui";
export default function Login() {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const router = useRouter();
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError("");
    const f = new FormData(e.currentTarget);
    try {
      await api("/login", {
        method: "POST",
        body: JSON.stringify({ email: f.get("email"), password: f.get("password") }),
      });
      router.replace("/");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="login-screen">
      <section className="login-story">
        <div className="login-brand">
          <Brand />
        </div>
        <div>
          <span className="eyebrow light">GESTÃO COMERCIAL</span>
          <h1>
            Mais clareza.
            <br />
            Mais controle.
            <br />
            <em>Em cada comissão.</em>
          </h1>
          <p>Do pedido ao pagamento, acompanhe toda a operação em um único lugar.</p>
          <div className="login-features">
            <span>
              <Layers3 size={19} /> Pedidos e itens preservados
            </span>
            <span>
              <Fingerprint size={19} /> Histórico completo de ações
            </span>
          </div>
        </div>
        <small>Um espaço de trabalho para a sua equipe.</small>
      </section>
      <section className="login-form-area">
        <form className="login-form" onSubmit={submit}>
          <span className="eyebrow">BEM-VINDO AO GCOM</span>
          <h2>Acesse o GCOM</h2>
          <p>Use suas credenciais para continuar.</p>
          <ErrorNotice message={error} />
          <label>
            Email
            <input
              name="email"
              type="email"
              required
              autoComplete="username"
              placeholder="voce@empresa.com.br"
            />
          </label>
          <label>
            Senha
            <input
              name="password"
              type="password"
              required
              autoComplete="current-password"
              placeholder="Sua senha"
            />
          </label>
          <button className="button primary" disabled={busy}>
            {busy ? "Entrando…" : "Entrar"}
            <ArrowRight size={18} />
          </button>
          <div className="login-security">
            <ShieldCheck size={17} /> Acesso exclusivo à rede interna
          </div>
          <p className="login-help">Precisa de acesso? Entre em contato com o administrador.</p>
        </form>
      </section>
    </main>
  );
}
