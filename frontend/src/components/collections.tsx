"use client";
import { CommissionSummary } from "./commission-summary";
import { CalculationLines } from "./calculation-lines";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { Plus, Download, ArrowUpRight, Check, Search, ShieldCheck, Pencil } from "lucide-react";
import {
  api,
  date,
  money,
  type Page,
  type Order,
  type Commission,
  type Adjustment,
  type User,
  type Audit,
  type CalculationPreview,
} from "@/lib/api";
import { useUser, allowed } from "./shell";
import { Empty, ErrorNotice, Loading, Modal, Pagination, Status } from "./ui";
function useCollection<T>(path: string) {
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Page<T>>();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(true);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    let active = true;
    const join = path.includes("?") ? "&" : "?";
    api<Page<T>>(`${path}${join}page=${page}`)
      .then((d) => {
        if (active) {
          setData(d);
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
  }, [path, page, revision]);
  return { page, setPage, data, error, setError, busy, reload: () => setRevision((r) => r + 1) };
}
export function Heading({
  eyebrow,
  title,
  text,
  children,
}: {
  eyebrow: string;
  title: string;
  text: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="page-heading">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
        <p>{text}</p>
      </div>
      {children}
    </div>
  );
}
export function FieldsForm({
  children,
  submit,
  done,
  label = "Salvar",
  submitDisabled = false,
}: {
  children: React.ReactNode;
  submit: (f: FormData) => Promise<unknown>;
  done: () => void;
  label?: string;
  submitDisabled?: boolean;
}) {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  async function send(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      await submit(new FormData(e.currentTarget));
      done();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <form onSubmit={send} className="form-stack">
      <ErrorNotice message={error} />
      {children}
      <div className="form-actions">
        <button className="button primary" disabled={busy || submitDisabled}>
          {busy ? "Salvando…" : label}
          <Check size={16} />
        </button>
      </div>
    </form>
  );
}
export function OrdersPage() {
  const user = useUser();
  const [customer, setCustomer] = useState("");
  const list = useCollection<Order>(`/orders?customer=${encodeURIComponent(customer)}`);
  const [open, setOpen] = useState(false);
  const [detail, setDetail] = useState<Order>();
  async function show(id: number) {
    try {
      setDetail(await api<Order>(`/orders/${id}`));
    } catch (e) {
      list.setError((e as Error).message);
    }
  }
  return (
    <>
      <Heading
        eyebrow="ORIGEM DA COMISSÃO"
        title="Pedidos"
        text="Pedidos do Winthor, com todos os itens preservados no momento da importação."
      >
        {allowed(user, "ROLE_OPERATOR") && (
          <button className="button primary" onClick={() => setOpen(true)}>
            <Download size={17} />
            Importar pedido
          </button>
        )}
      </Heading>
      <div className="toolbar">
        <label className="search-field">
          <Search size={17} />
          <input
            placeholder="Filtrar por código do cliente"
            aria-label="Código do cliente"
            value={customer}
            onChange={(e) => {
              setCustomer(e.target.value);
              list.setPage(1);
            }}
          />
        </label>
        <span className="toolbar-note">Snapshots preservados · Somente leitura</span>
      </div>
      <ErrorNotice message={list.error} />
      <section className="panel">
        {list.busy ? (
          <Loading />
        ) : list.data?.items.length ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Cliente</th>
                  <th>Itens</th>
                  <th>Importado em</th>
                  <th>Situação</th>
                  <th className="number">Valor do pedido</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.data.items.map((o) => (
                  <tr key={o.id}>
                    <td className="mono">#{o.orderNumber}</td>
                    <td>
                      <strong>{o.customerName}</strong>
                      <small>Cód. {o.customerCode}</small>
                    </td>
                    <td>{o.itemCount}</td>
                    <td>{date(o.capturedAt)}</td>
                    <td>
                      <span className={`tag ${o.commissionId ? "" : "positive"}`}>
                        {o.commissionId ? "Com comissão" : "Disponível"}
                      </span>
                    </td>
                    <td className="number">{money(o.total)}</td>
                    <td>
                      <button
                        className="icon-button"
                        aria-label={`Detalhes do pedido ${o.orderNumber}`}
                        onClick={() => show(o.id)}
                      >
                        <ArrowUpRight size={18} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty
            title="Nenhum pedido importado"
            text="Informe um número de pedido faturado para consultar o Winthor."
          />
        )}
        {list.data && <Pagination page={list.page} total={list.data.total} change={list.setPage} />}
      </section>
      {open && (
        <Modal title="Importar pedido do Winthor" close={() => setOpen(false)}>
          <FieldsForm
            label="Importar pedido"
            done={() => {
              setOpen(false);
              list.reload();
            }}
            submit={(f) =>
              api("/orders/import", {
                method: "POST",
                body: JSON.stringify({ orderNumber: f.get("orderNumber") }),
              })
            }
          >
            <p className="form-description">
              O cabeçalho e todos os itens serão guardados como uma cópia permanente. Apenas pedidos
              faturados podem ser importados.
            </p>
            <label>
              Número do pedido
              <input
                name="orderNumber"
                inputMode="numeric"
                pattern="[1-9][0-9]{0,11}"
                required
                autoFocus
                placeholder="Ex.: 123456"
              />
            </label>
          </FieldsForm>
        </Modal>
      )}
      {detail && (
        <Modal title={`Pedido #${detail.orderNumber}`} close={() => setDetail(undefined)}>
          <OrderDetails order={detail} />
        </Modal>
      )}
    </>
  );
}
export function OrderDetails({ order }: { order: Order }) {
  return (
    <div className="order-detail">
      <div className="detail-meta">
        <div>
          <small>Cliente principal</small>
          <strong>{order.customerName}</strong>
          <span>Cód. {order.customerCode}</span>
        </div>
        <div>
          <small>Valor do pedido</small>
          <strong>{money(order.total)}</strong>
          <span>Importado em {date(order.capturedAt)}</span>
        </div>
      </div>
      <p className="muted">
        Filial {String(order.header?.CODFILIAL ?? "—")} · Tabela{" "}
        {String(order.header?.NUMREGIAO ?? "—")} · Plano {String(order.header?.CODPLPAG ?? "—")} ·
        Cliente final {String(order.header?.CODCLI ?? "—")} /{" "}
        {String(order.header?.COMMISSION_FINAL_CUSTOMER_NAME ?? "—")} · Frete{" "}
        {money(String(order.header?.VLFRETE ?? "0"))}
      </p>
      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Produto</th>
              <th className="number">Quantidade</th>
              <th className="number">Preço unit.</th>
              <th className="number">Preço tabela</th>
            </tr>
          </thead>
          <tbody>
            {order.items?.map((i) => (
              <tr key={i.id}>
                <td>
                  <strong>{i.description}</strong>
                  <small>Cód. {i.productCode}</small>
                </td>
                <td className="number">{Number(i.quantity).toLocaleString("pt-BR")}</td>
                <td className="number">{money(i.unitPrice)}</td>
                <td className="number">{money(i.referencePrice)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <details className="raw-details">
        <summary>Dados originais do pedido e dos itens</summary>
        <pre>
          {JSON.stringify({ header: order.header, items: order.items?.map((i) => i.raw) }, null, 2)}
        </pre>
      </details>
    </div>
  );
}
export function CommissionsPage() {
  const user = useUser();
  const [status, setStatus] = useState("");
  const list = useCollection<Commission>(`/commissions?status=${status}`);
  const [open, setOpen] = useState(false);
  useEffect(() => {
    if (
      new URLSearchParams(window.location.search).get("new") === "1" &&
      allowed(user, "ROLE_OPERATOR")
    )
      setOpen(true);
  }, [user]);
  return (
    <>
      <Heading
        eyebrow="GESTÃO FINANCEIRA"
        title="Comissões"
        text="Controle os valores, acompanhe aprovações e confirme cada pagamento."
      >
        {allowed(user, "ROLE_OPERATOR") && (
          <button className="button primary" onClick={() => setOpen(true)}>
            <Plus size={17} />
            Nova comissão
          </button>
        )}
      </Heading>
      <div className="toolbar">
        <div className="tabs">
          {[
            ["", "Todas"],
            ["pending", "Pendentes"],
            ["approved", "A pagar"],
            ["paid", "Pagas"],
          ].map(([v, t]) => (
            <button
              key={v}
              className={status === v ? "selected" : ""}
              onClick={() => {
                setStatus(v);
                list.setPage(1);
              }}
            >
              {t}
            </button>
          ))}
        </div>
      </div>
      <ErrorNotice message={list.error} />
      <section className="panel">
        {list.busy ? (
          <Loading />
        ) : list.data?.items.length ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Comissão / Cliente</th>
                  <th>Responsável</th>
                  <th>Data</th>
                  <th>Status</th>
                  <th className="number">Líquido</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.data.items.map((c) => (
                  <tr key={c.id}>
                    <td>
                      <strong>{c.customerName}</strong>
                      <small className="mono">{c.code}</small>
                    </td>
                    <td>{c.createdBy.name}</td>
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
                        <ArrowUpRight size={18} />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty title="Nenhuma comissão nesta etapa" />
        )}
        {list.data && <Pagination page={list.page} total={list.data.total} change={list.setPage} />}
      </section>
      {open && (
        <Modal title="Nova comissão" wide close={() => setOpen(false)}>
          <NewCommission
            done={() => {
              setOpen(false);
              list.reload();
            }}
          />
        </Modal>
      )}
    </>
  );
}
type AvailableOrder = {
  orderNumber: string;
  customerCode: string;
  customerName: string;
  orderDate: string;
  branch: string;
  total: string;
  priceContext: {
    orderRegion: number;
    psdRegion: number;
    pscfRegion: number | null;
    paymentPlan: string;
    priceColumn: string;
    normalBasis: string;
    ruleVersion: number;
  } | null;
  priceContextError: string | null;
};

function NewCommission({ done }: { done: () => void }) {
  const today = new Date().toLocaleDateString("en-CA");

  const [customer, setCustomer] = useState("");
  const [from, setFrom] = useState(`${today.slice(0, 7)}-01`);
  const [to, setTo] = useState(today);

  const [orders, setOrders] = useState<AvailableOrder[] | null>(null);
  const [adjustments, setAdjustments] = useState<Page<Adjustment> | null>(null);
  const [adjustmentPage, setAdjustmentPage] = useState(1);

  const [selected, setSelected] = useState<string[]>([]);
  const [orderIds, setOrderIds] = useState<number[]>([]);

  const [mode, setMode] = useState("normal");
  const [preview, setPreview] = useState<CalculationPreview>();

  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  function clearSearch() {
    setOrders(null);
    setAdjustments(null);
    setSelected([]);
    setOrderIds([]);
    setPreview(undefined);
    setError("");
  }

  async function search() {
    clearSearch();

    if (false === /^[1-9][0-9]{0,17}$/.test(customer) || "" === from || "" === to) {
      setError("Informe o cliente principal e as duas datas.");

      return;
    }

    if (from > to) {
      setError("A data inicial deve ser anterior ou igual à data final.");

      return;
    }

    setBusy(true);

    try {
      const params = new URLSearchParams({ customer, from, to });
      const [available, pending] = await Promise.all([
        api<{ items: AvailableOrder[] }>(`/winthor/orders/available?${params}`),
        api<Page<Adjustment>>(`/adjustments?available=1&customer=${customer}`),
      ]);
      setOrders(available.items);
      setAdjustments(pending);
      setAdjustmentPage(1);
    } catch (exception) {
      setError((exception as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function loadAdjustments(page: number) {
    setBusy(true);

    try {
      setAdjustments(
        await api<Page<Adjustment>>(`/adjustments?available=1&customer=${customer}&page=${page}`),
      );
      setAdjustmentPage(page);
    } catch (exception) {
      setError((exception as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function simulate() {
    setBusy(true);
    setPreview(undefined);
    setError("");

    try {
      const ids: number[] = [];

      for (const orderNumber of selected) {
        const order = await api<Order>("/orders/import", {
          method: "POST",
          body: JSON.stringify({ orderNumber }),
        });

        if (order.customerCode !== customer) {
          throw new Error("O cliente principal do pedido mudou. Faça uma nova busca.");
        }

        if (null !== order.commissionId) {
          throw new Error(
            `O pedido ${orderNumber} já pertence a uma comissão. Faça uma nova busca.`,
          );
        }

        ids.push(order.id);
      }

      const result = await api<CalculationPreview>("/commissions/preview", {
        method: "POST",
        body: JSON.stringify({ orderIds: ids, adjustmentIds: [], mode }),
      });
      setOrderIds(ids);
      setPreview(result);
    } catch (exception) {
      setError((exception as Error).message);
    } finally {
      setBusy(false);
    }
  }

  function toggle(number: string) {
    setPreview(undefined);
    setSelected(
      true === selected.includes(number)
        ? selected.filter((value) => value !== number)
        : [...selected, number],
    );
  }

  return (
    <FieldsForm
      label={
        undefined === preview ? "Registrar comissão" : `Lançar comissão de ${money(preview.net)}`
      }
      submitDisabled={undefined === preview || true === busy}
      done={done}
      submit={async (fields) => {
        setBusy(true);

        try {
          return await api("/commissions", {
            method: "POST",
            body: JSON.stringify({
              orderIds,
              mode,
              adjustmentIds: [],
              ruleVersion: preview?.calculation.rule.version,
              expectedAdjustmentIds: preview?.adjustmentIds,
              reason: fields.get("reason"),
            }),
          });
        } finally {
          setBusy(false);
        }
      }}
    >
      <p className="form-description">
        Informe o cliente principal e o período para buscar pedidos faturados no Winthor. Selecione
        os pedidos, simule e registre a comissão. Um usuário com perfil Financeiro poderá aprová-la.
      </p>
      <div className="form-grid">
        <label>
          Cliente principal
          <input
            value={customer}
            required
            pattern="[1-9][0-9]*"
            disabled={busy}
            inputMode="numeric"
            onChange={(event) => {
              setCustomer(event.target.value);
              clearSearch();
            }}
            placeholder="Código do cliente principal"
          />
        </label>
        <label>
          Data inicial
          <input
            type="date"
            value={from}
            required
            disabled={busy}
            onChange={(event) => {
              setFrom(event.target.value);
              clearSearch();
            }}
          />
        </label>
        <label>
          Data final
          <input
            type="date"
            value={to}
            required
            disabled={busy}
            onChange={(event) => {
              setTo(event.target.value);
              clearSearch();
            }}
          />
        </label>
        <label>
          Modalidade
          <select
            value={mode}
            disabled={busy}
            onChange={(event) => {
              setMode(event.target.value);
              setPreview(undefined);
            }}
          >
            <option value="normal">Comissão normal</option>
            <option value="atg">Autoagenciamento (ATG / PTABELA)</option>
          </select>
        </label>
      </div>
      <button
        type="button"
        className="button secondary"
        disabled={true === busy || "" === customer || "" === from || "" === to}
        onClick={search}
      >
        {true === busy ? "Processando…" : "Buscar pedidos no Winthor"}
      </button>
      <ErrorNotice message={error} />
      {null === orders && (
        <p className="muted">
          Preencha os filtros e clique em buscar. Nenhum pedido foi consultado ainda.
        </p>
      )}
      {null !== orders && (
        <fieldset>
          <legend>Pedidos disponíveis · {orders.length} encontrados</legend>
          <p className="muted">
            Data do pedido no Winthor. Pedidos já comissionados são excluídos. Até 500 pedidos por
            busca; reduza o período quando necessário.
          </p>
          {orders.map((order) => (
            <label className="checkbox-row" key={order.orderNumber}>
              <input
                type="checkbox"
                disabled={true === busy || null === order.priceContext}
                checked={selected.includes(order.orderNumber)}
                onChange={() => toggle(order.orderNumber)}
              />
              <span>
                <strong>
                  #{order.orderNumber} · {order.customerName} (cliente {order.customerCode})
                </strong>
                <small>
                  {"" === order.orderDate ? "Data indisponível" : date(order.orderDate)} · Filial{" "}
                  {order.branch} · {money(order.total)}
                </small>
                {null !== order.priceContext && (
                  <>
                    <small>
                      Tabela/região do pedido: {order.priceContext.orderRegion} · PSD{" "}
                      {order.priceContext.psdRegion} / PSCF {order.priceContext.pscfRegion ?? "—"} ·
                      Regra #{order.priceContext.ruleVersion}
                    </small>
                    <small>
                      Plano {order.priceContext.paymentPlan} → {order.priceContext.priceColumn} nos
                      itens comuns; combos usam PVENDA1 da composição.
                    </small>
                    <small>
                      Referência da comissão:{" "}
                      {"atg" === mode
                        ? "PTABELA do item (ATG)"
                        : "margin_psd" === order.priceContext.normalBasis
                          ? `PSD ${order.priceContext.psdRegion} / ${order.priceContext.priceColumn}`
                          : "margin_table" === order.priceContext.normalBasis
                            ? "PTABELA do item"
                            : "Valor de venda"}
                      .
                    </small>
                  </>
                )}
                {null !== order.priceContextError && (
                  <small role="alert">{order.priceContextError}</small>
                )}
              </span>
            </label>
          ))}
          {0 === orders.length && (
            <p className="muted">
              Nenhum pedido elegível encontrado para esse cliente principal no período.
            </p>
          )}
        </fieldset>
      )}
      {null !== adjustments && (
        <fieldset>
          <legend>Deduções pendentes do cliente principal (automáticas)</legend>
          {adjustments.items.map((adjustment) => (
            <div className="checkbox-row" key={adjustment.id}>
              <span>
                <strong>
                  {adjustment.type === "return"
                    ? "Devolução"
                    : adjustment.type === "cancellation"
                      ? "Cancelamento"
                      : "Débito"}{" "}
                  · {money(adjustment.amount)}
                </strong>
                <small>{adjustment.reason}</small>
              </span>
            </div>
          ))}
          {0 === adjustments.items.length && <p className="muted">Sem deduções pendentes.</p>}
          <Pagination page={adjustmentPage} total={adjustments.total} change={loadAdjustments} />
        </fieldset>
      )}
      <button
        type="button"
        className="button secondary"
        disabled={0 === selected.length || true === busy}
        onClick={simulate}
      >
        {true === busy ? "Processando…" : "Simular comissão"}
      </button>
      {undefined !== preview && (
        <section
          className="commission-review"
          aria-label="Conferência da comissão"
          aria-live="polite"
        >
          <div>
            <h2>Confira os valores antes de lançar</h2>
            <p className="muted">
              Regra #{preview.calculation.rule.version} ·{" "}
              {Number(preview.calculation.percentageApplied).toLocaleString("pt-BR")}%
            </p>
          </div>
          <CommissionSummary
            gross={preview.gross}
            deductions={preview.deductions}
            net={preview.net}
            adjustments={preview.adjustments}
          />
          <div className="notice info">
            Venda {money(preview.calculation.sales)} − referência{" "}
            {money(preview.calculation.reference)} − frete{" "}
            {money(preview.calculation.deductedFreight)} = base{" "}
            {money(preview.calculation.baseAmount)}.
          </div>
          <h3>Cálculo por produto do pedido</h3>
          <CalculationLines
            items={preview.calculation.items}
            orders={preview.calculation.orders}
            percentage={preview.calculation.percentageApplied}
          />
          <details className="commission-applied-deductions">
            <summary>Deduções consideradas nesta simulação ({preview.adjustments.length})</summary>
            {0 === preview.adjustments.length ? (
              <p className="muted">Sem deduções pendentes.</p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Tipo / Referência</th>
                      <th>Justificativa</th>
                      <th className="number">Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    {preview.adjustments.map((adjustment) => (
                      <tr key={adjustment.id}>
                        <td>
                          {"return" === adjustment.type
                            ? "Devolução"
                            : "cancellation" === adjustment.type
                              ? "Cancelamento"
                              : "Débito"}
                          <small>{adjustment.sourceReference}</small>
                        </td>
                        <td className="reason-cell">{adjustment.reason}</td>
                        <td className="number">{money(adjustment.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </details>
        </section>
      )}
      <label>
        Memória de cálculo e justificativa (opcional)
        <textarea
          name="reason"
          maxLength={2000}
          rows={3}
          disabled={busy}
          placeholder="Descreva a origem e a conferência da comissão."
        />
      </label>
      <div className="notice info">
        A simulação guarda os pedidos selecionados e todos os itens no GCOM. Todas as deduções
        pendentes do principal serão descontadas, inclusive as de períodos anteriores. Cancelamentos
        de pedidos já comissionados também são conferidos na simulação. A memória de cálculo fica
        preservada.
      </div>
    </FieldsForm>
  );
}

export function AdjustmentsPage() {
  const user = useUser();
  const list = useCollection<Adjustment>("/adjustments");
  const [open, setOpen] = useState(false);
  return (
    <>
      <Heading
        eyebrow="AJUSTES DA OPERAÇÃO"
        title="Débitos e devoluções"
        text="Cadastre débitos do cliente principal para abatimento automático na próxima comissão. Consulte os vínculos nos relatórios."
      >
        {allowed(user, "ROLE_OPERATOR") && (
          <button className="button primary" onClick={() => setOpen(true)}>
            <Plus size={17} />
            Nova dedução
          </button>
        )}
      </Heading>
      <ErrorNotice message={list.error} />
      <section className="panel">
        {list.busy ? (
          <Loading />
        ) : list.data?.items.length ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Tipo / Referência</th>
                  <th>Justificativa</th>
                  <th>Situação</th>
                  <th className="number">Valor</th>
                </tr>
              </thead>
              <tbody>
                {list.data.items.map((a) => (
                  <tr key={a.id}>
                    <td>Cód. {a.customerCode}</td>
                    <td>
                      <strong>
                        {a.type === "return"
                          ? "Devolução"
                          : a.type === "cancellation"
                            ? "Cancelamento"
                            : "Débito"}
                      </strong>
                      <small>{a.sourceReference}</small>
                    </td>
                    <td className="reason-cell">{a.reason}</td>
                    <td>
                      {a.commissionId ? (
                        <Link className="text-link" href={`/commissions/${a.commissionId}`}>
                          Aplicada <ArrowUpRight size={13} />
                        </Link>
                      ) : (
                        <span className="tag positive">Disponível</span>
                      )}
                    </td>
                    <td className="number">{money(a.amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty />
        )}
        {list.data && <Pagination page={list.page} total={list.data.total} change={list.setPage} />}
      </section>
      {open && (
        <Modal title="Nova dedução" close={() => setOpen(false)}>
          <FieldsForm
            done={() => {
              setOpen(false);
              list.reload();
            }}
            submit={(f) =>
              api("/adjustments", { method: "POST", body: JSON.stringify(Object.fromEntries(f)) })
            }
          >
            <div className="form-grid">
              <label>
                Código do cliente
                <input
                  name="customerCode"
                  required
                  pattern="[0-9]+"
                  inputMode="numeric"
                  maxLength={30}
                />
              </label>
              <label>
                Tipo
                <select name="type">
                  <option value="debt">Débito</option>
                  <option value="return">Devolução</option>
                </select>
              </label>
            </div>
            <label>
              Referência de origem
              <input
                name="sourceReference"
                required
                maxLength={100}
                placeholder="Número do documento ou devolução"
              />
            </label>
            <label>
              Valor (R$)
              <input name="amount" required type="number" min="0.01" step="0.01" />
            </label>
            <label>
              Justificativa
              <textarea name="reason" required minLength={10} maxLength={2000} rows={3} />
            </label>
          </FieldsForm>
        </Modal>
      )}
    </>
  );
}
const roleLabels: Record<string, string> = {
  ROLE_ADMIN: "Administrador",
  ROLE_OPERATOR: "Operação",
  ROLE_FINANCE: "Financeiro",
  ROLE_AUDITOR: "Auditoria",
};
export function UsersPage() {
  const list = useCollection<User>("/users");
  const [editing, setEditing] = useState<User | "new">();
  return (
    <>
      <Heading
        eyebrow="ACESSOS E PERMISSÕES"
        title="Usuários"
        text="Defina responsabilidades e mantenha o acesso da equipe sob controle."
      >
        <button className="button primary" onClick={() => setEditing("new")}>
          <Plus size={17} />
          Novo usuário
        </button>
      </Heading>
      <ErrorNotice message={list.error} />
      <section className="panel">
        {list.busy ? (
          <Loading />
        ) : list.data?.items.length ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>Perfis</th>
                  <th>Situação</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.data.items.map((u) => (
                  <tr key={u.id}>
                    <td>
                      <strong>{u.name}</strong>
                      <small>{u.email}</small>
                    </td>
                    <td>
                      <div className="role-tags">
                        {u.roles
                          .filter((r) => r !== "ROLE_USER")
                          .map((r) => (
                            <span className="tag" key={r}>
                              {roleLabels[r] ?? r}
                            </span>
                          ))}
                      </div>
                    </td>
                    <td>
                      <span className={`tag ${u.active ? "positive" : ""}`}>
                        {u.active ? "Ativo" : "Desativado"}
                      </span>
                    </td>
                    <td>
                      <button
                        className="icon-button"
                        aria-label={`Editar ${u.name}`}
                        onClick={() => setEditing(u)}
                      >
                        <Pencil size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty />
        )}
        {list.data && <Pagination page={list.page} total={list.data.total} change={list.setPage} />}
      </section>
      <div className="notice info">
        <ShieldCheck size={18} /> Operação registra comissões. Financeiro aprova e confirma
        pagamentos. Auditoria consulta o histórico. Administradores gerenciam os acessos.
      </div>
      {editing && (
        <Modal
          title={editing === "new" ? "Novo usuário" : "Editar usuário"}
          close={() => setEditing(undefined)}
        >
          <UserForm
            user={editing === "new" ? undefined : editing}
            done={() => {
              setEditing(undefined);
              list.reload();
            }}
          />
        </Modal>
      )}
    </>
  );
}
function UserForm({ user, done }: { user?: User; done: () => void }) {
  const [roles, setRoles] = useState(
    user?.roles.filter((r) => r !== "ROLE_USER") ?? ["ROLE_OPERATOR"],
  );
  return (
    <FieldsForm
      done={done}
      submit={(f) =>
        api(user ? `/users/${user.id}` : "/users", {
          method: user ? "PATCH" : "POST",
          body: JSON.stringify({
            ...Object.fromEntries(f),
            roles,
            active: f.get("active") === "true",
          }),
        })
      }
    >
      <label>
        Nome
        <input name="name" defaultValue={user?.name} required minLength={2} maxLength={120} />
      </label>
      <label>
        Email
        <input name="email" type="email" defaultValue={user?.email} required maxLength={180} />
      </label>
      <label>
        {user ? "Nova senha (deixe vazia para manter)" : "Senha"}
        <input
          name="password"
          type="password"
          autoComplete="new-password"
          required={!user}
          minLength={12}
          maxLength={128}
        />
      </label>
      <fieldset>
        <legend>Perfis de acesso</legend>
        {Object.entries(roleLabels).map(([r, label]) => (
          <label className="checkbox-row" key={r}>
            <input
              type="checkbox"
              checked={roles.includes(r)}
              onChange={() =>
                setRoles(roles.includes(r) ? roles.filter((x) => x !== r) : [...roles, r])
              }
            />
            {label}
          </label>
        ))}
      </fieldset>
      <label>
        Situação
        <select name="active" defaultValue={String(user?.active ?? true)}>
          <option value="true">Ativo</option>
          <option value="false">Desativado</option>
        </select>
      </label>
    </FieldsForm>
  );
}
export function AuditPage() {
  const [action, setAction] = useState("");
  const list = useCollection<Audit>(`/audit?action=${encodeURIComponent(action)}`);
  const [detail, setDetail] = useState<Audit>();
  return (
    <>
      <Heading
        eyebrow="RASTREABILIDADE"
        title="Auditoria"
        text="Quem fez, quando fez e quais dados participaram de cada operação."
      />
      <div className="toolbar">
        <label className="search-field">
          <Search size={17} />
          <input
            placeholder="Filtrar ação: commission.paid"
            aria-label="Ação"
            value={action}
            onChange={(e) => {
              setAction(e.target.value);
              list.setPage(1);
            }}
          />
        </label>
        <span className="toolbar-note">
          <ShieldCheck size={15} /> Histórico preservado
        </span>
      </div>
      <ErrorNotice message={list.error} />
      <section className="panel">
        {list.busy ? (
          <Loading />
        ) : list.data?.items.length ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Data / Hora</th>
                  <th>Responsável</th>
                  <th>Ação</th>
                  <th>Registro</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.data.items.map((a) => (
                  <tr key={a.id}>
                    <td>{new Date(a.createdAt).toLocaleString("pt-BR")}</td>
                    <td>{a.actor}</td>
                    <td>
                      <span className="tag mono">{a.action}</span>
                    </td>
                    <td className="mono">{a.subject}</td>
                    <td>
                      <button
                        className="icon-button"
                        aria-label={`Detalhes do evento ${a.id}`}
                        onClick={() => setDetail(a)}
                      >
                        <ArrowUpRight size={18} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <Empty />
        )}
        {list.data && <Pagination page={list.page} total={list.data.total} change={list.setPage} />}
      </section>
      {detail && (
        <Modal title="Detalhes da auditoria" close={() => setDetail(undefined)}>
          <div className="detail-meta">
            <div>
              <small>Responsável</small>
              <strong>{detail.actor}</strong>
            </div>
            <div>
              <small>IP</small>
              <strong>{detail.ip}</strong>
            </div>
          </div>
          <p className="mono muted">Requisição: {detail.requestId}</p>
          <pre className="json-block">{JSON.stringify(detail.details, null, 2)}</pre>
        </Modal>
      )}
    </>
  );
}
