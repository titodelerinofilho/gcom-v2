"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import {
  ArrowUpRight,
  Plus,
  Wallet,
  CircleCheck,
  BadgePercent,
  SlidersHorizontal,
  ArrowRight,
  PackageSearch,
} from "lucide-react";
import { api, money, date, type Summary, type Commission, type Page } from "@/lib/api";
import { useUser, allowed } from "./shell";
import { Empty, ErrorNotice, Loading, Status } from "./ui";
export function Dashboard({ reports = false }: { reports?: boolean }) {
  const user = useUser();
  const [summary, setSummary] = useState<Summary>();
  const [recent, setRecent] = useState<Commission[]>([]);
  const [error, setError] = useState("");
  const [from, setFrom] = useState(`${new Date().getFullYear()}-01-01`);
  const [to, setTo] = useState(new Date().toLocaleDateString("en-CA"));
  const [busy, setBusy] = useState(true);
  useEffect(() => {
    let active = true;
    Promise.all([
      api<Summary>(`/reports/summary?from=${from}&to=${to}`),
      api<Page<Commission>>("/commissions"),
    ])
      .then(([s, c]) => {
        if (active) {
          setSummary(s);
          setRecent(c.items.slice(0, 5));
          setError("");
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      })
      .finally(() => {
        if (active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, [from, to]);
  const stats = summary
    ? [
        {
          label: "Comissões geradas",
          value: money(summary.totals.gross),
          hint: `${summary.totals.count} comissões no período`,
          icon: BadgePercent,
          tone: "primary-tone",
        },
        {
          label: "A pagar",
          value: money(summary.totals.outstanding),
          hint: "Pendentes e aprovadas",
          icon: Wallet,
          tone: "amber",
        },
        {
          label: "Pagamentos confirmados",
          value: money(summary.totals.paid),
          hint: "Comissões com pagamento confirmado",
          icon: CircleCheck,
          tone: "green",
        },
        {
          label: "Deduções aplicadas",
          value: money(summary.totals.deductions),
          hint: "Débitos e devoluções",
          icon: SlidersHorizontal,
          tone: "lime",
        },
      ]
    : [];
  const max = Math.max(...(summary?.monthly.map((m) => Number(m.amount)) ?? []), 1);
  return (
    <>
      <div className="page-heading">
        <div>
          <span className="eyebrow">{reports ? "INTELIGÊNCIA DA OPERAÇÃO" : "SEU WORKSPACE"}</span>
          <h1>{reports ? "Relatórios" : `Olá, ${user.name.split(" ")[0]}.`}</h1>
          <p>
            {reports
              ? "Uma visão completa das comissões, clientes e pagamentos."
              : "Acompanhe as comissões e mantenha a operação em dia."}
          </p>
        </div>
        {reports ? (
          <div className="export-actions">
            {["pdf", "xlsx", "csv"].map((format) => (
              <a
                key={format}
                className={`button ${format === "pdf" ? "primary" : "secondary"}`}
                href={`/api/reports/commissions.${format}?from=${from}&to=${to}`}
              >
                Exportar {format.toUpperCase()} <ArrowUpRight size={17} />
              </a>
            ))}
          </div>
        ) : (
          allowed(user, "ROLE_OPERATOR") && (
            <Link className="button primary" href="/commissions?new=1">
              <Plus size={17} />
              Nova comissão
            </Link>
          )
        )}
      </div>
      <div className="period-filter">
        <span>Período de criação</span>
        <label className="sr-only" htmlFor="from">
          Data inicial
        </label>
        <input
          id="from"
          aria-label="Data inicial"
          type="date"
          value={from}
          onChange={(e) => setFrom(e.target.value)}
        />
        <span>até</span>
        <label className="sr-only" htmlFor="to">
          Data final
        </label>
        <input
          id="to"
          aria-label="Data final"
          type="date"
          value={to}
          onChange={(e) => setTo(e.target.value)}
        />
      </div>
      <ErrorNotice message={error} />
      {busy ? (
        <Loading />
      ) : (
        summary && (
          <>
            <div className="stat-grid">
              {stats.map((s) => (
                <article className="stat-card" key={s.label}>
                  <div>
                    <span>{s.label}</span>
                    <span className={`stat-icon ${s.tone}`}>
                      <s.icon size={18} />
                    </span>
                  </div>
                  <strong>{s.value}</strong>
                  <small>{s.hint}</small>
                </article>
              ))}
            </div>
            <div className="dashboard-grid">
              <section className="panel">
                <div className="panel-heading">
                  <div>
                    <h2>Evolução das comissões</h2>
                    <p>Valor líquido por mês de criação</p>
                  </div>
                  <span className="tag">{new Date(from).getUTCFullYear()}</span>
                </div>
                {summary.monthly.length ? (
                  <div className="bar-chart">
                    {summary.monthly.map((m) => (
                      <div className="chart-column" key={m.month}>
                        <span className="chart-value">{money(m.amount)}</span>
                        <div className="bar-track">
                          <div
                            style={{ height: `${Math.max(2, (Number(m.amount) / max) * 100)}%` }}
                          />
                        </div>
                        <small>{m.month}</small>
                      </div>
                    ))}
                  </div>
                ) : (
                  <Empty
                    title="O histórico começa aqui"
                    text="A evolução será exibida após o registro das primeiras comissões."
                  />
                )}
              </section>
              <section className="panel status-panel">
                <div className="panel-heading">
                  <div>
                    <h2>Fluxo de pagamentos</h2>
                    <p>Da aprovação à confirmação</p>
                  </div>
                </div>
                <div className="flow-list">
                  {["pending", "approved", "paid"].map((status, i) => {
                    const row = summary.byStatus.find((s) => s.status === status);
                    return (
                      <div className="flow-row" key={status}>
                        <span className={`flow-step step-${i}`}>{i + 1}</span>
                        <div>
                          <Status value={status} />
                          <small>{row?.count ?? 0} comissões</small>
                        </div>
                        <strong>{money(row?.amount ?? 0)}</strong>
                      </div>
                    );
                  })}
                </div>
                <div className="panel-note">
                  <ShieldIcon /> Vincule opcionalmente o RECNUM da rotina 749 ao pagamento.
                </div>
              </section>
            </div>
            {reports ? (
              <section className="panel">
                <div className="panel-heading">
                  <div>
                    <h2>Comissões por cliente</h2>
                    <p>Os 20 clientes com maior valor líquido no período</p>
                  </div>
                </div>
                {summary.byCustomer.length ? (
                  <div className="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>Cliente</th>
                          <th>Comissões</th>
                          <th className="number">Valor líquido</th>
                        </tr>
                      </thead>
                      <tbody>
                        {summary.byCustomer.map((c) => (
                          <tr key={c.customer_code}>
                            <td>
                              <strong>{c.customer_name}</strong>
                              <small>Cód. {c.customer_code}</small>
                            </td>
                            <td>{c.count}</td>
                            <td className="number">{money(c.amount)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <Empty />
                )}
              </section>
            ) : (
              <section className="panel">
                <div className="panel-heading">
                  <div>
                    <h2>Comissões recentes</h2>
                    <p>Últimos registros da operação</p>
                  </div>
                  <Link className="text-link" href="/commissions">
                    Ver todas <ArrowRight size={15} />
                  </Link>
                </div>
                {recent.length ? (
                  <div className="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>Comissão / Cliente</th>
                          <th>Criada em</th>
                          <th>Status</th>
                          <th className="number">Valor líquido</th>
                          <th />
                        </tr>
                      </thead>
                      <tbody>
                        {recent.map((c) => (
                          <tr key={c.id}>
                            <td>
                              <strong>{c.customerName}</strong>
                              <small>{c.code}</small>
                            </td>
                            <td>{date(c.createdAt)}</td>
                            <td>
                              <Status value={c.status} />
                            </td>
                            <td className="number">{money(c.netAmount)}</td>
                            <td>
                              <Link
                                className="icon-button"
                                aria-label={`Abrir ${c.code}`}
                                href={`/commissions/${c.id}`}
                              >
                                <ArrowUpRight size={17} />
                              </Link>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <Empty
                    title="Tudo pronto para a primeira comissão"
                    text="Importe um pedido do Winthor e registre a comissão para começar."
                  />
                )}
              </section>
            )}
          </>
        )
      )}
      {!reports && allowed(user, "ROLE_OPERATOR") && (
        <Link href="/orders" className="callout">
          <span className="callout-icon">
            <PackageSearch size={23} />
          </span>
          <div>
            <strong>Do Winthor para sua gestão</strong>
            <p>Importe pedidos faturados e preserve todos os itens da venda.</p>
          </div>
          <ArrowRight size={20} />
        </Link>
      )}
    </>
  );
}
function ShieldIcon() {
  return <CircleCheck size={16} />;
}
