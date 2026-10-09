"use client";
import Link from "next/link";
import { useSearchParams, useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import {
  Search,
  FileDown,
  Users,
  BadgePercent,
  Wallet,
  Undo2,
  Ban,
  Medal,
  ArrowLeft,
  ArrowRight,
  TrendingUp,
  ShieldCheck,
} from "lucide-react";
import { api, date, dateTime, money } from "@/lib/api";
import type { ProfilePage, ProfileMonth, ResellerProfile as Profile } from "@/lib/reseller";
import { Heading } from "./collections";
import { Empty, ErrorNotice, Loading, Status } from "./ui";

function initialPeriod() {
  const today = new Date();
  return {
    from: new Date(today.getFullYear(), today.getMonth() - 11, 1).toLocaleDateString("en-CA"),
    to: today.toLocaleDateString("en-CA"),
  };
}
const firstPages = { paidPage: 1, returnsPage: 1, debtsPage: 1, cancellationsPage: 1 };
type PageKey = keyof typeof firstPages;

function ProfilePagination<T>({
  data,
  change,
  label,
  disabled,
}: {
  data: ProfilePage<T>;
  change: (page: number) => void;
  label: string;
  disabled: boolean;
}) {
  const last = Math.max(1, Math.ceil(data.total / data.pageSize));
  return (
    <nav className="pagination" aria-label={`Paginação de ${label}`}>
      <span>
        {data.total} registros · Página {data.page} de {last}
      </span>
      <div>
        <button
          type="button"
          className="icon-button"
          disabled={true === disabled || data.page <= 1}
          aria-label={`${label}: página anterior`}
          onClick={() => change(data.page - 1)}
        >
          <ArrowLeft size={16} />
        </button>
        <button
          type="button"
          className="icon-button"
          disabled={true === disabled || data.page >= last}
          aria-label={`${label}: próxima página`}
          onClick={() => change(data.page + 1)}
        >
          <ArrowRight size={16} />
        </button>
      </div>
    </nav>
  );
}

function MonthlyChart({
  months,
  commissions = false,
}: {
  months: ProfileMonth[];
  commissions?: boolean;
}) {
  const series =
    true === commissions
      ? [
          { key: "generatedAmount" as const, label: "Líquidas geradas", color: "#098a14" },
          { key: "paidAmount" as const, label: "Pagas", color: "#7395c5" },
        ]
      : [{ key: "salesAmount" as const, label: "Vendas faturadas", color: "#098a14" }];
  const max = Math.max(1, ...months.flatMap((month) => series.map((s) => Number(month[s.key]))));
  const width = 760,
    height = 235,
    left = 62,
    baseline = 195,
    plot = width - left - 14;
  const step = plot / Math.max(1, months.length);
  const bar = Math.min(26, step / (series.length + 1.5));
  const compact = new Intl.NumberFormat("pt-BR", { notation: "compact", maximumFractionDigits: 1 });
  return (
    <>
      <div className="reseller-chart-legend">
        {series.map((s) => (
          <span key={s.key}>
            <i style={{ background: s.color }} />
            {s.label}
          </span>
        ))}
      </div>
      <svg
        className="reseller-chart"
        viewBox={`0 0 ${width} ${height}`}
        role="img"
        aria-label={
          true === commissions
            ? "Evolução mensal das comissões líquidas geradas e pagas"
            : "Evolução mensal das vendas agenciadas faturadas"
        }
      >
        <title>Valores em reais; dados exatos na tabela mensal abaixo</title>
        {[0, 0.5, 1].map((fraction) => (
          <g key={fraction}>
            <line
              x1={left}
              x2={width - 10}
              y1={baseline - fraction * 165}
              y2={baseline - fraction * 165}
              stroke="#e5ebe7"
            />
            <text
              x={left - 8}
              y={baseline - fraction * 165 + 4}
              textAnchor="end"
              fontSize="11"
              fill="#718077"
            >
              {compact.format(max * fraction)}
            </text>
          </g>
        ))}
        {months.map((month, index) => (
          <g key={month.month}>
            {series.map((s, position) => {
              const value = Math.max(0, Number(month[s.key]));
              const h = (value / max) * 165;
              return (
                <rect
                  key={s.key}
                  x={left + index * step + step / 2 - (series.length * bar) / 2 + position * bar}
                  y={baseline - h}
                  width={bar - 3}
                  height={h}
                  rx={3}
                  fill={s.color}
                >
                  <title>
                    {month.month} · {s.label}: {money(value)}
                  </title>
                </rect>
              );
            })}
            <text
              x={left + index * step + step / 2}
              y={baseline + 23}
              textAnchor="middle"
              fontSize="10"
              fill="#718077"
            >
              {month.month.slice(5)}/{month.month.slice(2, 4)}
            </text>
          </g>
        ))}
      </svg>
    </>
  );
}

function ListPanel({
  title,
  text,
  total,
  children,
}: {
  title: string;
  text: string;
  total: number;
  children: ReactNode;
}) {
  return (
    <section className="panel reseller-list">
      <div className="panel-heading">
        <div>
          <h2>{title}</h2>
          <p>{text}</p>
        </div>
        <span className="tag">{total}</span>
      </div>
      {children}
    </section>
  );
}

export function ResellerProfile() {
  const search = useSearchParams();
  const router = useRouter();
  const defaults = initialPeriod();
  const [customer, setCustomer] = useState(search.get("customer") ?? "");
  const [from, setFrom] = useState(search.get("from") ?? defaults.from);
  const [to, setTo] = useState(search.get("to") ?? defaults.to);
  const [query, setQuery] = useState<{ customer: string; from: string; to: string } | null>(() =>
    null === search.get("customer")
      ? null
      : {
          customer: search.get("customer") ?? "",
          from: search.get("from") ?? defaults.from,
          to: search.get("to") ?? defaults.to,
        },
  );
  const [pages, setPages] = useState(firstPages);
  const [profile, setProfile] = useState<Profile>();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    if (null === query) return;
    let active = true;
    setBusy(true);
    setError("");
    const params = new URLSearchParams({
      ...query,
      ...Object.fromEntries(Object.entries(pages).map(([key, value]) => [key, String(value)])),
    });
    api<Profile>(`/resellers/profile?${params}`)
      .then((value) => {
        if (true === active) setProfile(value);
      })
      .catch((e: Error) => {
        if (true === active) {
          setProfile(undefined);
          setError(e.message);
        }
      })
      .finally(() => {
        if (true === active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, [query, pages, revision]);
  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (from > to) {
      setError("A data inicial deve ser anterior à data final.");
      return;
    }
    const selected = { customer: customer.trim(), from, to };
    setProfile(undefined);
    setPages(firstPages);
    setQuery(selected);
    setRevision((value) => value + 1);
    router.replace(`/resellers?${new URLSearchParams(selected)}`, { scroll: false });
  }
  function change(key: PageKey, page: number) {
    setPages((value) => ({ ...value, [key]: page }));
  }
  async function exportPdf() {
    if (undefined === profile) return;
    setExporting(true);
    setError("");
    try {
      const params = new URLSearchParams({
        customer: profile.customer.code,
        from: profile.from,
        to: profile.to,
      });
      const response = await fetch(`/api/resellers/profile.pdf?${params}`, {
        credentials: "same-origin",
        cache: "no-store",
      });
      if (false === response.ok) {
        const body = await response
          .json()
          .catch(() => ({ error: "Não foi possível gerar o PDF." }));
        throw new Error(body.error ?? "Não foi possível gerar o PDF.");
      }
      const url = URL.createObjectURL(await response.blob());
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = `ficha-revenda-${profile.customer.code}-${profile.from}-${profile.to}.pdf`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setExporting(false);
    }
  }
  return (
    <div className="reseller-profile">
      <Heading
        eyebrow="RELACIONAMENTO E PERFORMANCE"
        title="Ficha do Cliente Revenda"
        text="Vendas agenciadas, comissões e saúde financeira da carteira em uma só visão."
      />
      <form className="panel reseller-search" onSubmit={submit}>
        <label>
          Código do cliente principal
          <input
            name="customer"
            inputMode="numeric"
            pattern="[1-9][0-9]{0,17}"
            maxLength={18}
            required
            placeholder="Ex.: 100"
            value={customer}
            onChange={(e) => setCustomer(e.target.value)}
          />
        </label>
        <label>
          De
          <input
            name="from"
            type="date"
            required
            value={from}
            max={to}
            onChange={(e) => setFrom(e.target.value)}
          />
        </label>
        <label>
          Até
          <input
            name="to"
            type="date"
            required
            value={to}
            min={from}
            onChange={(e) => setTo(e.target.value)}
          />
        </label>
        <button className="button primary" type="submit" disabled={true === busy}>
          <Search size={17} />
          {true === busy ? "Consultando…" : "Consultar ficha"}
        </button>
      </form>
      <ErrorNotice message={error} />
      {true === busy && undefined === profile && <Loading />}
      {null === query && (
        <section className="panel">
          <Empty
            title="Conheça a performance do seu revenda"
            text="Informe o código do cliente principal. A ficha reúne vendas dos clientes vinculados por CODREVENDA e valores de comissão registrados no GCOM."
          />
        </section>
      )}
      {undefined !== profile && (
        <div aria-busy={busy}>
          <section className="reseller-hero">
            <div className="reseller-identity">
              <span className="reseller-avatar">
                <Users size={28} />
              </span>
              <div>
                <span className="eyebrow">CLIENTE PRINCIPAL · {profile.customer.code}</span>
                <h2>{profile.customer.name}</h2>
                <p>
                  {date(profile.from)} a {date(profile.to)} · {profile.activity.linkedCustomers}{" "}
                  clientes vinculados
                </p>
              </div>
            </div>
            <div className="reseller-hero-actions">
              <span className={`reseller-medal ${profile.rating.tier}`}>
                <Medal size={19} />
                {profile.rating.label}
                <strong>{profile.rating.score}/100</strong>
              </span>
              <button
                type="button"
                className="button secondary"
                onClick={exportPdf}
                disabled={true === exporting || true === busy}
              >
                <FileDown size={17} />
                {true === exporting ? "Gerando…" : "Exportar ficha PDF"}
              </button>
            </div>
            <p className="reseller-freshness">
              <ShieldCheck size={14} /> Consultado em {dateTime(profile.generatedAt)} · Débitos e
              vínculos atuais
            </p>
          </section>
          <div className="stat-grid reseller-stat-grid">
            {[
              {
                label: "Vendas agenciadas",
                value: money(profile.activity.salesAmount),
                hint: `${profile.activity.soldOrders} pedidos faturados`,
                icon: TrendingUp,
              },
              {
                label: "Comissões pagas",
                value: money(profile.finance.paidAmount),
                hint: `${profile.finance.paidCount} pagamentos no período`,
                icon: Wallet,
              },
              {
                label: "Comissões a pagar",
                value: money(profile.finance.pendingAmount),
                hint: "Pendentes/aprovadas criadas no período",
                icon: BadgePercent,
              },
              {
                label: "Devoluções abatidas",
                value: money(profile.finance.appliedReturnsAmount),
                hint: `${profile.appliedReturns.total} abatimentos em comissão`,
                icon: Undo2,
              },
            ].map((stat) => (
              <article className="stat-card" key={stat.label}>
                <div>
                  <span>{stat.label}</span>
                  <span className="stat-icon green">
                    <stat.icon size={18} />
                  </span>
                </div>
                <strong>{stat.value}</strong>
                <small>{stat.hint}</small>
              </article>
            ))}
          </div>
          <div className="reseller-overview-grid">
            <section className="panel reseller-rating">
              <div className="panel-heading">
                <div>
                  <h2>Qualidade do relacionamento</h2>
                  <p>
                    {profile.rating.confidence} · Critério {profile.rating.version}
                  </p>
                </div>
                <span className={`reseller-medal ${profile.rating.tier}`}>
                  {profile.rating.label}
                </span>
              </div>
              {profile.rating.components.map((part) => (
                <div className="reseller-rating-row" key={part.label}>
                  <div>
                    <span>{part.label}</span>
                    <strong>
                      {part.points}
                      <small> / {part.maximum}</small>
                    </strong>
                  </div>
                  <meter
                    min={0}
                    max={part.maximum}
                    value={part.points}
                    aria-label={`${part.label}: ${part.points} de ${part.maximum}`}
                  />
                  <p>{part.detail}</p>
                </div>
              ))}
              <p className="reseller-method-note">
                Ouro ≥ 80 · Prata ≥ 55 · Bronze &lt; 55. Amostra reduzida ou atraso de 60 dias
                limita a Bronze. Sem vendas: sem classificação. O rating orienta o acompanhamento
                comercial.
              </p>
            </section>
            <section className="panel reseller-risk">
              <div className="panel-heading">
                <div>
                  <h2>Saúde da carteira</h2>
                  <p>Débitos atuais, inclusive títulos a vencer</p>
                </div>
                <ShieldCheck size={21} />
              </div>
              <div className="reseller-debt-total">
                <span>Total em aberto</span>
                <strong>{money(profile.debtSummary.openAmount)}</strong>
                <small>{profile.debtSummary.openTitles} títulos em aberto</small>
              </div>
              <div className="reseller-debt-split">
                <div>
                  <span>Revenda · vencidos</span>
                  <strong>{money(profile.debtSummary.ownOverdueAmount)}</strong>
                </div>
                <div>
                  <span>Vinculados · vencidos</span>
                  <strong>{money(profile.debtSummary.linkedOverdueAmount)}</strong>
                </div>
              </div>
              <p
                className={
                  0 < profile.debtSummary.overdueTitles ? "reseller-alert" : "reseller-good"
                }
              >
                {0 < profile.debtSummary.overdueTitles
                  ? `${profile.debtSummary.overdueTitles} títulos vencidos · maior atraso de ${profile.debtSummary.maxLateDays} dias`
                  : "Nenhum título vencido na consulta atual"}
              </p>
              <div className="reseller-losses">
                <div>
                  <Ban size={18} />
                  <span>
                    Cancelamentos
                    <strong>
                      {profile.activity.cancelledOrders} pedidos ·{" "}
                      {money(profile.activity.cancelledAmount)}
                    </strong>
                    <small>
                      {null === profile.rating.cancellationRate
                        ? "Sem base de comparação"
                        : `${profile.rating.cancellationRate}% dos pedidos faturados + cancelados`}
                    </small>
                  </span>
                </div>
                <div>
                  <Undo2 size={18} />
                  <span>
                    Mercadorias devolvidas<strong>{money(profile.activity.returnedAmount)}</strong>
                    <small>
                      {null === profile.rating.returnRate
                        ? "Sem base de comparação"
                        : `${profile.rating.returnRate}% do volume faturado no período`}
                    </small>
                  </span>
                </div>
              </div>
            </section>
          </div>
          <div className="reseller-charts-grid">
            <section className="panel">
              <div className="panel-heading">
                <div>
                  <h2>Vendas agenciadas</h2>
                  <p>Evolução por mês do pedido</p>
                </div>
              </div>
              <MonthlyChart months={profile.monthly} />
            </section>
            <section className="panel">
              <div className="panel-heading">
                <div>
                  <h2>Comissões geradas e pagas</h2>
                  <p>Criação e pagamento no período</p>
                </div>
              </div>
              <MonthlyChart months={profile.monthly} commissions />
            </section>
          </div>
          <details className="panel reseller-months">
            <summary>Ver valores mensais e totais de comissão</summary>
            <p>
              Bruto gerado: <strong>{money(profile.finance.grossAmount)}</strong> · Deduções:{" "}
              <strong>{money(profile.finance.deductions)}</strong> · Líquido:{" "}
              <strong>{money(profile.finance.netAmount)}</strong>
            </p>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Mês</th>
                    <th>Pedidos</th>
                    <th>Vendas</th>
                    <th>Líquidas geradas</th>
                    <th>Pagas</th>
                  </tr>
                </thead>
                <tbody>
                  {profile.monthly.map((month) => (
                    <tr key={month.month}>
                      <td>{month.month}</td>
                      <td>{month.orders}</td>
                      <td>{money(month.salesAmount)}</td>
                      <td>{money(month.generatedAmount)}</td>
                      <td>{money(month.paidAmount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </details>
          <ListPanel
            title="Clientes que mais movimentaram vendas"
            text={`${profile.activity.activeCustomers} de ${profile.activity.linkedCustomers} vinculados tiveram pedidos faturados no período`}
            total={profile.topCustomers.length}
          >
            {0 === profile.topCustomers.length ? (
              <Empty title="Sem vendas no período" />
            ) : (
              <div className="reseller-top-customers">
                {profile.topCustomers.map((client, index) => (
                  <div key={client.code}>
                    <span className="reseller-rank">{index + 1}</span>
                    <div>
                      <strong>{client.name}</strong>
                      <small>
                        Código {client.code} · {client.orders} pedidos
                      </small>
                    </div>
                    <strong>{money(client.salesAmount)}</strong>
                  </div>
                ))}
              </div>
            )}
          </ListPanel>
          <div className="reseller-charts-grid reseller-financial-lists">
            <ListPanel
              title="Comissões pagas"
              text="Valor confirmado e data de pagamento · 5 por página"
              total={profile.paidCommissions.total}
            >
              {0 === profile.paidCommissions.items.length ? (
                <Empty title="Nenhum pagamento nesta página" />
              ) : (
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Comissão</th>
                        <th>Pagamento</th>
                        <th>Pago</th>
                      </tr>
                    </thead>
                    <tbody>
                      {profile.paidCommissions.items.map((commission) => (
                        <tr key={commission.id}>
                          <td>
                            <Link href={`/commissions/${commission.id}`}>{commission.code}</Link>
                            {true === commission.manualAmount && (
                              <small>Valor manual confirmado</small>
                            )}
                          </td>
                          <td>{date(commission.paidAt)}</td>
                          <td className="money">{money(commission.paidAmount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <ProfilePagination
                data={profile.paidCommissions}
                change={(page) => change("paidPage", page)}
                label="comissões pagas"
                disabled={busy}
              />
            </ListPanel>
            <ListPanel
              title="Devoluções abatidas em comissões"
              text="Pela data da criação da comissão · 5 por página"
              total={profile.appliedReturns.total}
            >
              {0 === profile.appliedReturns.items.length ? (
                <Empty title="Nenhuma devolução abatida nesta página" />
              ) : (
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Devolução / comissão</th>
                        <th>Abatimento</th>
                        <th>Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      {profile.appliedReturns.items.map((item) => (
                        <tr key={item.id}>
                          <td>
                            <strong>{item.reference}</strong>
                            <small>
                              <Link href={`/commissions/${item.commissionId}`}>
                                {item.commissionCode}
                              </Link>
                            </small>
                            <Status value={item.commissionStatus} />
                          </td>
                          <td>{date(item.appliedAt)}</td>
                          <td className="money">{money(item.amount)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <ProfilePagination
                data={profile.appliedReturns}
                change={(page) => change("returnsPage", page)}
                label="devoluções abatidas"
                disabled={busy}
              />
            </ListPanel>
          </div>
          <ListPanel
            title="Débitos do revenda e clientes vinculados"
            text="Títulos atuais em aberto; vencidos primeiro · 10 por página"
            total={profile.debts.total}
          >
            {0 === profile.debts.items.length ? (
              <Empty title="Nenhum título em aberto nesta página" />
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Cliente</th>
                      <th>Origem</th>
                      <th>NF / parcela</th>
                      <th>Vencimento</th>
                      <th>Situação</th>
                      <th>Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    {profile.debts.items.map((item) => (
                      <tr key={`${item.customerCode}-${item.transaction}-${item.installment}`}>
                        <td>
                          <strong>{item.customerName}</strong>
                          <small>Código {item.customerCode}</small>
                        </td>
                        <td>
                          <span className="tag">{true === item.own ? "Revenda" : "Vinculado"}</span>
                        </td>
                        <td>
                          {item.invoice ?? "—"} / {item.installment}
                        </td>
                        <td>{null === item.dueDate ? "Não informado" : date(item.dueDate)}</td>
                        <td>
                          <span
                            className={0 < item.daysLate ? "reseller-late" : "reseller-current"}
                          >
                            {0 < item.daysLate
                              ? `${item.daysLate} dias em atraso`
                              : null === item.dueDate
                                ? "Sem vencimento"
                                : "A vencer / em dia"}
                          </span>
                        </td>
                        <td className="money">{money(item.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <ProfilePagination
              data={profile.debts}
              change={(page) => change("debtsPage", page)}
              label="débitos"
              disabled={busy}
            />
          </ListPanel>
          <ListPanel
            title="Cancelamentos de vendas agenciadas"
            text="Pedidos e cancelamentos fiscais, agrupados por pedido · 5 por página"
            total={profile.cancellations.total}
          >
            {0 === profile.cancellations.items.length ? (
              <Empty title="Nenhum cancelamento nesta página" />
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Pedido</th>
                      <th>Cliente vinculado</th>
                      <th>Motivo / origem</th>
                      <th>Cancelado em</th>
                      <th>Volume</th>
                    </tr>
                  </thead>
                  <tbody>
                    {profile.cancellations.items.map((item) => (
                      <tr key={`${item.source}-${item.orderNumber}-${item.customerCode}`}>
                        <td>{item.orderNumber}</td>
                        <td>
                          <strong>{item.customerName}</strong>
                          <small>Código {item.customerCode}</small>
                        </td>
                        <td>
                          {item.reason}
                          <small>
                            {item.source === "order" ? "Pedido" : "Cancelamento fiscal"}
                          </small>
                        </td>
                        <td>{date(item.cancelledAt)}</td>
                        <td className="money">{money(item.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <ProfilePagination
              data={profile.cancellations}
              change={(page) => change("cancellationsPage", page)}
              label="cancelamentos"
              disabled={busy}
            />
          </ListPanel>
          <details className="panel reseller-method">
            <summary>Critérios da ficha, classificação e fontes dos dados</summary>
            {profile.notes.map((note) => (
              <p key={note}>{note}</p>
            ))}
            <p>
              O PDF faz uma nova consulta, indica seu horário de geração e inclui todas as listas
              (até 1.000 registros no total). Ele usa um layout A4 próprio para impressão.
            </p>
          </details>
        </div>
      )}
    </div>
  );
}
