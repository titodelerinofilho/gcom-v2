"use client";
import { useState, type FormEvent } from "react";
import { api, money } from "@/lib/api";
import { Heading } from "./collections";
import { allowed, useUser } from "./shell";
import { Empty, ErrorNotice } from "./ui";

type Row = Record<string, unknown>;
export function WinthorPage() {
  const user = useUser();
  const [kind, setKind] = useState("orders");
  const [customer, setCustomer] = useState("");
  const [atg, setAtg] = useState(false);
  const [rows, setRows] = useState<Row[]>();
  const [queried, setQueried] = useState<{ kind: string; customer: string; atg: boolean }>();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  async function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const fields = new FormData(event.currentTarget);
    const params = new URLSearchParams({
      customer,
      atg: atg ? "1" : "0",
      from: String(fields.get("from")),
      to: String(fields.get("to")),
      square: String(fields.get("square") ?? ""),
    });
    setBusy(true);
    setError("");
    setNotice("");
    setRows(undefined);
    try {
      const result = await api<{ items: Row[] }>(`/winthor/${kind}?${params}`);
      setRows(result.items);
      setQueried({ kind, customer, atg });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function importRow(row: Row) {
    if (!queried) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await api(queried.kind === "orders" ? "/orders/import" : "/winthor/returns/import", {
        method: "POST",
        body: JSON.stringify(
          queried.kind === "orders"
            ? { orderNumber: String(row.NUMPED) }
            : {
                customer: queried.customer,
                numtransent: String(row.NUMTRANSENT),
                atg: queried.atg,
              },
        ),
      });
      setNotice(
        queried.kind === "orders"
          ? "Pedido e todos os itens importados. Consulte Pedidos para gerar a comissão."
          : "Devolução calculada e registrada. Todos os itens do NUMTRANSENT foram preservados; a dedução será aplicada na próxima comissão do cliente principal.",
      );
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  const resultKind = queried?.kind ?? kind;
  return (
    <>
      <Heading
        eyebrow="INTEGRAÇÃO WINTHOR"
        title="Consultas da operação"
        text="Busque pedidos, devoluções, cancelamentos e títulos em atraso do cliente principal."
      />
      <section className="panel form-panel">
        <form className="form-stack" onSubmit={search}>
          <div className="form-grid">
            <label>
              Consulta
              <select
                value={kind}
                onChange={(e) => {
                  setKind(e.target.value);
                  setRows(undefined);
                }}
              >
                <option value="orders">Pedidos elegíveis</option>
                <option value="returns">Devoluções (últimos 90 dias)</option>
                <option value="cancellations">Cancelamentos</option>
                <option value="overdue">Títulos em atraso</option>
              </select>
            </label>
            <label>
              Cliente principal
              <input
                value={customer}
                onChange={(e) => setCustomer(e.target.value)}
                pattern="[1-9][0-9]*"
                required
              />
            </label>
            {(kind === "orders" || kind === "cancellations") && (
              <>
                <label>
                  De
                  <input type="date" name="from" required />
                </label>
                <label>
                  Até
                  <input type="date" name="to" required />
                </label>
              </>
            )}
            {kind === "orders" && (
              <label>
                Praça (opcional)
                <input name="square" pattern="[0-9]+" />
              </label>
            )}
          </div>
          {kind === "returns" && (
            <label className="checkbox-row">
              <input type="checkbox" checked={atg} onChange={(e) => setAtg(e.target.checked)} />
              Devolução ATG
            </label>
          )}
          <p className="muted">
            Os preços são resolvidos pela filial e tabela de cada pedido. Cancelamentos integrais de
            pedidos já comissionados são conferidos na simulação e deduzidos na próxima comissão.
            Títulos atrasados são informativos; débitos exigem registro próprio.
          </p>
          <button className="button primary" disabled={busy}>
            {busy ? "Consultando…" : "Consultar"}
          </button>
        </form>
      </section>
      <ErrorNotice message={error} />
      {notice && (
        <div className="notice info" role="status">
          {notice}
        </div>
      )}
      {rows && (
        <section className="panel">
          {rows.length ? (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Pedido / Transação</th>
                    <th>{"orders" === resultKind ? "Cliente principal" : "Cliente / Produto"}</th>
                    <th>Filial / Tabela</th>
                    <th>Quantidade / Valor</th>
                    <th>Detalhes / Ação</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, i) => (
                    <tr key={i}>
                      <td>
                        <span className="order-number-invoice">
                          {String(row.NUMPED ?? "—")}
                          <span className="order-invoice">NF {String(row.NUMNOTA ?? "—")}</span>
                        </span>
                        <small>{String(row.NUMTRANSENT ?? row.NUMTRANSVENDA ?? "—")}</small>
                      </td>
                      <td>
                        {"orders" === resultKind ? (
                          queried?.customer
                        ) : (
                          <>
                            {String(row.CLIENTE ?? row.DESCRICAO ?? "—")}
                            <small>
                              Cliente {String(row.CODCLI ?? row.FINAL_CUSTOMER ?? "—")} · Produto{" "}
                              {String(row.CODPROD ?? "—")}
                            </small>
                          </>
                        )}
                      </td>
                      <td>
                        {String(row.CODFILIAL ?? "—")} / {String(row.NUMREGIAO ?? "—")}
                        <small>
                          {String(
                            row.MOVEMENT_DATE ?? row.CANCELLED_AT ?? row.DUE_DATE ?? row.DATA ?? "",
                          )}
                        </small>
                      </td>
                      <td>
                        {String(row.QT ?? "—")}
                        <small>
                          {money(String(row.VLTOTAL ?? row.VALOR ?? "0"))}
                          {row.ATRASO != null && ` · ${row.ATRASO} dias`}
                        </small>
                      </td>
                      <td>
                        {allowed(user, "ROLE_OPERATOR") &&
                          (resultKind === "orders" || resultKind === "returns") && (
                            <button
                              className="button secondary"
                              disabled={busy}
                              onClick={() => importRow(row)}
                            >
                              {resultKind === "orders"
                                ? "Importar pedido"
                                : "Registrar NUMTRANSENT"}
                            </button>
                          )}
                        <details className="raw-details">
                          <summary>Dados consultados</summary>
                          <pre>{JSON.stringify(row, null, 2)}</pre>
                        </details>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <Empty />
          )}
        </section>
      )}
    </>
  );
}
