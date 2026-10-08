"use client";

import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { api, dateTime, money, type Audit, type Payment } from "@/lib/api";
import { AuditPage as AuditEventsPage, Heading } from "./collections";
import { Empty, ErrorNotice, Loading, Pagination } from "./ui";
import { CommissionMode } from "./commission-mode";
import { WinthorBrand } from "./winthor-brand";
import { WinthorPayableReference } from "./winthor-payable-reference";

type PaidCommission = {
  id: number;
  code: string;
  customerCode: string;
  customerName: string;
  netAmount: string;
  mode: string;
};
type Change = {
  section: string;
  record: string;
  field: string;
  saved: string | null;
  current: string | null;
};
type Verification = {
  commission: PaidCommission;
  payment: Payment | null;
  checkedAt: string;
  events: Audit[];
  paymentCheck?: {
    recnum: string;
    commissionAmount: string;
    confirmedAmount: string;
    launchAmount: string | null;
    paidAmount: string | null;
    source: "current" | "snapshot" | "unavailable";
    notice: string | null;
    differences: { label: string; expected: string; actual: string; difference: string }[];
  } | null;
  orders: {
    orderNumber: string;
    invoiceNumber: string;
    capturedAt: string;
    status: "missing" | "changed" | "incomplete" | "unchanged";
    invoiceBaselineAvailable: boolean;
    changes: Change[];
    currentInvoices: { invoiceNumber: string; cancelledAt: string | null }[];
  }[];
};
const actionLabels: Record<string, string> = {
  "commission.created": "Comissão lançada",
  "commission.approved": "Comissão aprovada",
  "commission.rejected": "Comissão reprovada",
  "commission.paid": "Pagamento confirmado",
  "payment.winthor_linked": "Vínculo com a rotina 749 de contas a pagar",
  "commission.verified": "Conferência dos pedidos e notas no Winthor",
};
const fieldLabels: Record<string, string> = {
  QT: "Quantidade",
  PVENDA: "Preço de venda",
  PUNIT: "Preço unitário",
  VLTOTAL: "Valor total",
  NUMNOTA: "Número da NF",
  NUMTRANSVENDA: "Transação da venda",
  CODCLI: "Cliente",
  CODPRACA: "Praça",
  CODFILIAL: "Filial",
  VLFRETE: "Frete",
  POSICAO: "Posição do pedido",
  DTCANCEL: "Cancelamento",
  DTSAIDA: "Saída",
  DATA: "Data do pedido",
  CODPROD: "Produto",
  CONDVENDA: "Condição de venda",
  CODPLPAG: "Plano de pagamento",
  DESCRICAO: "Descrição",
};

export function CommissionAuditPage() {
  const [tab, setTab] = useState("commissions");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [data, setData] = useState<{ items: PaidCommission[]; total: number }>();
  const [verification, setVerification] = useState<Verification>();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  useEffect(() => {
    let active = true;
    api<{ items: PaidCommission[]; total: number }>(
      "/audit/commissions?query=" + encodeURIComponent(query) + "&page=" + page,
    )
      .then((value) => {
        if (true === active) setData(value);
      })
      .catch((e: Error) => {
        if (true === active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [query, page, revision]);

  async function verify(id: number) {
    setBusy(true);
    setError("");
    setVerification(undefined);
    try {
      setVerification(
        await api<Verification>("/audit/commissions/" + id + "/verify", { method: "POST" }),
      );
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setRevision((value) => value + 1);
    setData(undefined);
    setError("");
    setQuery(String(new FormData(event.currentTarget).get("query") ?? "").trim());
  }

  return (
    <>
      <div className="audit-tabs" role="group" aria-label="Área da auditoria">
        <button
          className={tab === "commissions" ? "button primary" : "button secondary"}
          onClick={() => setTab("commissions")}
        >
          Conferência de comissões
        </button>
        <button
          className={tab === "events" ? "button primary" : "button secondary"}
          onClick={() => setTab("events")}
        >
          Eventos do sistema
        </button>
      </div>
      {"events" === tab ? (
        <AuditEventsPage />
      ) : (
        <>
          <Heading
            eyebrow="AUDITORIA FINANCEIRA"
            title="Conferência de comissões pagas"
            text="Compare os pedidos e notas do Winthor com os dados preservados no lançamento e acompanhe cada etapa da comissão."
          />
          <section className="panel form-panel">
            <form onSubmit={search} className="audit-search">
              <label>
                Comissão, cliente ou pedido
                <input
                  name="query"
                  maxLength={100}
                  placeholder="Código da comissão, código/nome do cliente ou número do pedido"
                />
              </label>
              <button className="button primary">Buscar comissões pagas</button>
            </form>
          </section>
          <ErrorNotice message={error} />
          <section className="panel">
            {undefined === data ? (
              <Loading />
            ) : 0 === data.items.length ? (
              <Empty title="Nenhuma comissão paga encontrada" />
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Comissão / Cliente</th>
                      <th>Modalidade</th>
                      <th className="number">Líquido calculado</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>
                            {item.customerCode} · {item.customerName}
                          </strong>
                          <small>{item.code}</small>
                        </td>
                        <td>
                          <CommissionMode mode={item.mode} />
                        </td>
                        <td className="number">{money(item.netAmount)}</td>
                        <td>
                          <button
                            className="button secondary"
                            disabled={busy}
                            onClick={() => verify(item.id)}
                          >
                            Auditar comissão
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {undefined !== data && <Pagination page={page} total={data.total} change={setPage} />}
          </section>
          {true === busy && <Loading />}
          {undefined !== verification && (
            <>
              <section className="panel form-panel audit-verification">
                <div className="panel-heading winthor-heading">
                  <WinthorBrand />
                  <div>
                    <h2>{verification.commission.code}</h2>
                    <p>Conferência realizada em {dateTime(verification.checkedAt)}</p>
                  </div>
                </div>
                <p>
                  <strong>
                    {verification.commission.customerCode} · {verification.commission.customerName}
                  </strong>
                </p>
                <p className="form-description">
                  A comparação considera os dados gravados na importação dos pedidos. Ela preserva o
                  cálculo financeiro original e registra o resultado desta conferência.
                </p>
                {undefined !== verification.paymentCheck && null !== verification.paymentCheck && (
                  <>
                    {0 < verification.paymentCheck.differences.length && (
                      <section
                        className="payment-audit-alert"
                        role="alert"
                        aria-label="Divergência no pagamento"
                      >
                        <h3>Divergência no pagamento da comissão</h3>
                        <p>
                          O valor do pagamento ou do lançamento da rotina 749 difere do líquido
                          registrado na comissão. Lançamento nº {verification.paymentCheck.recnum}.
                        </p>
                        <div className="table-scroll">
                          <table>
                            <thead>
                              <tr>
                                <th>Valor conferido</th>
                                <th className="number">Líquido da comissão</th>
                                <th className="number">Valor encontrado</th>
                                <th className="number">Diferença</th>
                              </tr>
                            </thead>
                            <tbody>
                              {verification.paymentCheck.differences.map((difference) => (
                                <tr key={difference.label}>
                                  <td>{difference.label}</td>
                                  <td className="number">{money(difference.expected)}</td>
                                  <td className="number">{money(difference.actual)}</td>
                                  <td className="number">{money(difference.difference)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                        {"string" === typeof verification.payment?.manualReason && (
                          <p>Justificativa do valor manual: {verification.payment.manualReason}</p>
                        )}
                      </section>
                    )}
                    {null !== verification.paymentCheck.notice && (
                      <p className="payment-audit-notice">{verification.paymentCheck.notice}</p>
                    )}
                  </>
                )}
                <Link className="text-link" href={"/commissions/" + verification.commission.id}>
                  Ver detalhes da comissão
                </Link>
                {verification.orders.map((order) => (
                  <article key={order.orderNumber} className="audit-order">
                    <div className="audit-order-heading">
                      <div>
                        <h3>
                          Pedido {order.orderNumber} · NF {order.invoiceNumber}
                        </h3>
                        <small>Dados preservados em {dateTime(order.capturedAt)}</small>
                      </div>
                      <span className={"audit-result " + order.status}>
                        {
                          {
                            missing: "Pedido não encontrado",
                            changed: "Alterações encontradas",
                            incomplete: "Comparação parcial",
                            unchanged: "Sem alterações",
                          }[order.status]
                        }
                      </span>
                    </div>
                    {order.currentInvoices.map(
                      (invoice) =>
                        null !== invoice.cancelledAt && (
                          <p className="audit-caution" key={invoice.invoiceNumber}>
                            NF {invoice.invoiceNumber} está cancelada no Winthor:{" "}
                            {invoice.cancelledAt}
                          </p>
                        ),
                    )}
                    {false === order.invoiceBaselineAvailable && (
                      <p className="audit-caution">
                        Este pedido foi importado sem o snapshot completo da nota fiscal. Os dados
                        do pedido e dos produtos são comparados; a comparação completa da NF não
                        está disponível.
                      </p>
                    )}
                    {0 < order.changes.length ? (
                      <div className="table-scroll">
                        <table>
                          <thead>
                            <tr>
                              <th>Registro</th>
                              <th>Campo</th>
                              <th>Gravado</th>
                              <th>Atual no Winthor</th>
                            </tr>
                          </thead>
                          <tbody>
                            {order.changes.map((change, index) => (
                              <tr key={index}>
                                <td>
                                  {change.section} {change.record}
                                </td>
                                <td>{fieldLabels[change.field] ?? change.field}</td>
                                <td>{change.saved ?? "—"}</td>
                                <td>{change.current ?? "—"}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    ) : (
                      <p className="muted">
                        Nenhuma diferença encontrada nos campos disponíveis para comparação.
                      </p>
                    )}
                  </article>
                ))}
                {null !== verification.payment?.winthor &&
                  undefined !== verification.payment?.winthor && (
                    <WinthorPayableReference reference={verification.payment.winthor} />
                  )}
              </section>
              <section className="panel form-panel">
                <h2>Histórico da comissão</h2>
                <p className="form-description">
                  Inclui lançamentos reprovados anteriores que utilizaram os mesmos pedidos, quando
                  houver.
                </p>
                <ol className="audit-timeline">
                  {verification.events.map((event) => (
                    <li key={event.id}>
                      <div>
                        <strong>{actionLabels[event.action] ?? event.action}</strong>
                        <span>{dateTime(event.createdAt)}</span>
                      </div>
                      <p>
                        {event.actor} · {event.subject}
                      </p>
                      {event.subject !== verification.commission.code && (
                        <small>Comissão anterior relacionada aos mesmos pedidos</small>
                      )}
                      {"string" === typeof event.details.reason && (
                        <p>Justificativa: {event.details.reason}</p>
                      )}
                      {"string" === typeof event.details.amount && (
                        <p>Valor pago: {money(event.details.amount)}</p>
                      )}
                      {"string" === typeof event.details.netAmount && (
                        <p>Líquido calculado: {money(event.details.netAmount)}</p>
                      )}
                      {"string" === typeof event.details.recnum && (
                        <p>Rotina 749 · Lançamento de contas a pagar nº {event.details.recnum}</p>
                      )}
                      <details>
                        <summary>Dados do evento</summary>
                        <pre className="json-block">{JSON.stringify(event.details, null, 2)}</pre>
                      </details>
                    </li>
                  ))}
                </ol>
              </section>
            </>
          )}
        </>
      )}
    </>
  );
}
