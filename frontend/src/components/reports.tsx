"use client";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { api, money } from "@/lib/api";
import { Heading } from "./collections";
import { Empty, ErrorNotice, Loading, Pagination } from "./ui";

type Row = Record<string, string | number | null>;
type Result = { items: Row[]; total: number; amount: string; page: number };
const labels: Record<string, string> = {
  normal: "Normal",
  atg: "ATG",
  unspecified: "Não informado",
  pending: "Pendente",
  approved: "Aprovada",
  paid: "Paga",
  deducted: "Deduzido",
  debt: "Débito",
  return: "Devolução",
  cancellation: "Cancelamento",
};
export function ReportsPage() {
  const today = new Date().toLocaleDateString("en-CA");
  const [kind, setKind] = useState("commissions");
  const [type, setType] = useState("");
  const [query, setQuery] = useState({
    kind: "commissions",
    params: `from=${new Date().getFullYear()}-01-01&to=${today}`,
  });
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Result>();
  const [error, setError] = useState("");
  useEffect(() => {
    let active = true;
    api<Result>(`/reports/${query.kind}?${query.params}&page=${page}`)
      .then((result) => {
        if (active) {
          setData(result);
          setError("");
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [query, page]);
  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(event.currentTarget))
      if (value !== "") params.set(key, String(value));
    setPage(1);
    setData(undefined);
    setError("");
    setQuery({ kind, params: params.toString() });
  }
  const commissions = query.kind === "commissions";
  return (
    <>
      <Heading
        eyebrow="RELATÓRIOS DA OPERAÇÃO"
        title="Relatórios"
        text="Consulte pagamentos e acompanhe onde cada débito, devolução ou cancelamento foi abatido."
      />
      <section className="panel detail-panel">
        <form onSubmit={search}>
          <div className="form-grid">
            <label>
              Relatório
              <select value={kind} onChange={(e) => setKind(e.target.value)}>
                <option value="commissions">Comissões e pagamentos</option>
                <option value="adjustments">Débitos, devoluções e cancelamentos</option>
              </select>
            </label>
            <label>
              Cliente principal
              <input name="customer" pattern="[1-9][0-9]*" placeholder="Todos os clientes" />
            </label>
            <label>
              De
              <input
                name="from"
                type="date"
                defaultValue={`${new Date().getFullYear()}-01-01`}
                required
              />
            </label>
            <label>
              Até
              <input name="to" type="date" defaultValue={today} required />
            </label>
            <label>
              Data considerada
              <select name="dateBasis" key={kind}>
                <option value="created">Data do cadastro</option>
                <option value={kind === "commissions" ? "paid" : "applied"}>
                  {kind === "commissions" ? "Data do pagamento" : "Data do abatimento na comissão"}
                </option>
              </select>
            </label>
            <label>
              Status {kind === "adjustments" ? "da comissão vinculada" : "da comissão"}
              <select name="status">
                <option value="">Todos</option>
                <option value="pending">Pendente</option>
                <option value="approved">Aprovada</option>
                <option value="paid">Paga</option>
              </select>
            </label>
            {kind === "commissions" ? (
              <>
                <label>
                  Número do pedido
                  <input name="orderNumber" pattern="[1-9][0-9]*" placeholder="Todos os pedidos" />
                </label>
                <label>
                  Modalidade
                  <select name="mode">
                    <option value="">Todas</option>
                    <option value="normal">Normal</option>
                    <option value="atg">Autoagenciamento (ATG)</option>
                  </select>
                </label>
              </>
            ) : (
              <>
                <label>
                  Tipo
                  <select name="type" value={type} onChange={(e) => setType(e.target.value)}>
                    <option value="">Todos</option>
                    <option value="debt">Débito</option>
                    <option value="return">Devolução</option>
                    <option value="cancellation">Cancelamento</option>
                  </select>
                </label>
                <label>
                  Situação do abatimento
                  <select name="state">
                    <option value="">Todos</option>
                    <option value="pending">Pendente para próxima comissão</option>
                    <option value="deducted">Já deduzido</option>
                  </select>
                </label>
                {type === "return" && (
                  <label>
                    Modalidade da devolução
                    <select name="mode">
                      <option value="">Todas</option>
                      <option value="normal">Normal</option>
                      <option value="atg">ATG</option>
                    </select>
                  </label>
                )}
              </>
            )}
          </div>
          <p className="muted">
            Deduzido indica vínculo com uma comissão. Confira o status e a data do pagamento para
            saber se ela foi paga. Débitos pendentes serão abatidos na próxima comissão do cliente
            principal.
          </p>
          <button className="button primary">Pesquisar</button>
        </form>
      </section>
      <ErrorNotice message={error} />
      {data ? (
        <section className="panel">
          <div className="panel-heading">
            <div>
              <h2>{commissions ? "Comissões encontradas" : "Abatimentos encontrados"}</h2>
              <p>
                {data.total} registros · Total {commissions ? "líquido" : "dos abatimentos"}:{" "}
                {money(data.amount)}
              </p>
            </div>
            <div className="export-actions">
              {["pdf", "xlsx", "csv"].map((format) => (
                <a
                  key={format}
                  className="button secondary"
                  href={`/api/reports/${query.kind}.${format}?${query.params}`}
                >
                  Exportar {format.toUpperCase()}
                </a>
              ))}
            </div>
          </div>
          {data.items.length ? (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    {(commissions
                      ? [
                          "Comissão / Cliente",
                          "Modalidade / Pedidos",
                          "Bruto",
                          "Deduções",
                          "Líquido",
                          "Status / Pagamento",
                        ]
                      : [
                          "Cliente / Tipo",
                          "Referência / Motivo",
                          "Valor",
                          "Situação",
                          "Comissão do abatimento",
                          "Datas / Pagamento",
                        ]
                    ).map((label) => (
                      <th key={label}>{label}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.items.map((row) => (
                    <tr key={String(row.id)}>
                      {commissions ? (
                        <>
                          <td>
                            <Link className="text-link" href={`/commissions/${row.id}`}>
                              {row.code}
                            </Link>
                            <small>
                              {row.customer_code} · {row.customer_name}
                            </small>
                          </td>
                          <td>
                            {labels[String(row.mode)] ?? row.mode}
                            <small>{row.orders}</small>
                          </td>
                          <td className="number">{money(String(row.gross_amount))}</td>
                          <td className="number">{money(String(row.deductions))}</td>
                          <td className="number">
                            {money(String(row.net_amount))}
                            {null !== row.paid_amount && undefined !== row.paid_amount && (
                              <small>Pago: {money(String(row.paid_amount))}</small>
                            )}
                            {row.manual_amount === "Sim" && (
                              <small title={String(row.manual_reason)}>Valor manual</small>
                            )}
                          </td>
                          <td>
                            {labels[String(row.status)]}
                            <small>{row.paid_at ?? "Pagamento não confirmado"}</small>
                          </td>
                        </>
                      ) : (
                        <>
                          <td>
                            {row.customer_code}
                            <small>
                              {labels[String(row.type)]} · {labels[String(row.mode)]}
                            </small>
                          </td>
                          <td>
                            {row.source_reference}
                            <small>{row.reason}</small>
                          </td>
                          <td className="number">{money(String(row.amount))}</td>
                          <td>{labels[String(row.state)]}</td>
                          <td>
                            {row.commission_id ? (
                              <Link
                                className="text-link"
                                href={`/commissions/${row.commission_id}`}
                              >
                                {row.commission_code}
                              </Link>
                            ) : (
                              "Próxima comissão"
                            )}
                            <small>{labels[String(row.commission_status)] ?? ""}</small>
                          </td>
                          <td>
                            Cadastro: {row.created_at}
                            <small>Abatimento: {row.deducted_at ?? "Pendente"}</small>
                            <small>Pagamento: {row.paid_at ?? "Não confirmado"}</small>
                          </td>
                        </>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <Empty />
          )}
          <Pagination
            page={page}
            total={data.total}
            change={(next) => {
              setData(undefined);
              setPage(next);
            }}
          />
        </section>
      ) : (
        !error && <Loading />
      )}
    </>
  );
}
