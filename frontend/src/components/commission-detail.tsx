"use client";
import { CalculationLines } from "./calculation-lines";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { ArrowLeft, Check, Printer, Link2, Wallet, ShieldCheck } from "lucide-react";
import { api, date, money, type Commission } from "@/lib/api";
import { allowed, useUser } from "./shell";
import { OrderDetails } from "./collections";
import { Loading, Modal, ErrorNotice, Status } from "./ui";
export function CommissionDetail({ id }: { id: string }) {
  const user = useUser();
  const [data, setData] = useState<Commission>();
  const [error, setError] = useState("");
  const [modal, setModal] = useState<"pay" | "link" | "approve">();
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);
  const [manualAmount, setManualAmount] = useState(false);
  const [paymentAmount, setPaymentAmount] = useState("");
  useEffect(() => {
    let active = true;
    api<Commission>(`/commissions/${id}`)
      .then((d) => {
        if (active) {
          setData(d);
          setError("");
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [id, revision]);
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError("");
    const fields = Object.fromEntries(new FormData(e.currentTarget));
    try {
      await api(
        `/commissions/${id}/${modal === "pay" ? "payment" : modal === "link" ? "payment/winthor" : "approve"}`,
        {
          method: "POST",
          body: JSON.stringify(modal === "pay" ? { ...fields, manualAmount } : fields),
        },
      );
      setModal(undefined);
      setRevision((r) => r + 1);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  if (!data)
    return (
      <>
        <ErrorNotice message={error} />
        {!error && <Loading />}
      </>
    );
  return (
    <>
      <Link href="/commissions" className="text-link back-link">
        <ArrowLeft size={16} />
        Voltar para comissões
      </Link>
      <div className="page-heading">
        <div>
          <span className="eyebrow">DETALHES DA COMISSÃO</span>
          <h1>{data.customerName}</h1>
          <p className="mono">{data.code}</p>
        </div>
        <div className="heading-actions">
          <button className="button secondary" onClick={() => window.print()}>
            <Printer size={16} />
            Imprimir
          </button>
          {allowed(user, "ROLE_FINANCE") && data.status === "pending" && (
            <button className="button primary" onClick={() => setModal("approve")}>
              <Check size={17} />
              Aprovar comissão
            </button>
          )}
          {allowed(user, "ROLE_FINANCE") && data.status === "approved" && (
            <button
              className="button primary"
              onClick={() => {
                setManualAmount(false);
                setPaymentAmount(data.netAmount);
                setModal("pay");
              }}
            >
              <Wallet size={17} />
              Confirmar pagamento
            </button>
          )}
        </div>
      </div>
      <ErrorNotice message={error} />
      <div className="commission-strip">
        <Status value={data.status} />
        <span>
          Criada por <strong>{data.createdBy.name}</strong> em {date(data.createdAt)}
        </span>
        {data.approvedBy && (
          <span>
            Aprovada por <strong>{data.approvedBy}</strong>
          </span>
        )}
      </div>
      <div className="stat-grid three">
        <article className="stat-card">
          <span>Comissão bruta</span>
          <strong>{money(data.grossAmount)}</strong>
        </article>
        <article className="stat-card">
          <span>Deduções</span>
          <strong>{money(data.deductions)}</strong>
        </article>
        <article className="stat-card accent">
          <span>Valor líquido</span>
          <strong>{money(data.netAmount)}</strong>
        </article>
      </div>
      <section className="panel detail-panel">
        <div className="panel-heading">
          <div>
            <h2>Memória de cálculo</h2>
            <p>Versão {data.calculation?.version}</p>
          </div>
          <ShieldCheck size={19} />
        </div>
        {data.calculation?.rule && (
          <div className="notice info">
            <strong>
              Regra #{data.calculation.rule.version} ·{" "}
              {Number(data.calculation.percentageApplied ?? data.calculation.rule.percentage)}% ·{" "}
              {(data.calculation.effectiveBasis ?? data.calculation.rule.basis) === "margin_psd"
                ? `Margem PSD por filial/tabela`
                : (data.calculation.effectiveBasis ?? data.calculation.rule.basis) ===
                    "margin_table"
                  ? "Margem PTABELA"
                  : "Venda dos itens"}
            </strong>
            <p>
              Venda {money(data.calculation.sales ?? "0")} − referência{" "}
              {money(data.calculation.reference ?? "0")} − frete{" "}
              {money(data.calculation.deductedFreight ?? "0")} = base{" "}
              {money(data.calculation.baseAmount ?? "0")}.
            </p>
          </div>
        )}
        <p className="preserve-lines">{data.calculation?.reason}</p>
        {data.calculation?.items && <CalculationLines items={data.calculation.items} />}
        {data.calculation?.items && (
          <details className="raw-details">
            <summary>Memória por item e regra preservada</summary>
            <pre>{JSON.stringify(data.calculation, null, 2)}</pre>
          </details>
        )}
      </section>
      {data.adjustments && data.adjustments.length > 0 && (
        <section className="panel">
          <div className="panel-heading">
            <h2>Deduções aplicadas</h2>
          </div>
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
                {data.adjustments.map((a) => (
                  <tr key={a.id}>
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
                    <td>
                      {a.reason}
                      {a.sourceSnapshot && (
                        <details className="raw-details">
                          <summary>Itens e cálculo da dedução</summary>
                          <pre>{JSON.stringify(a.sourceSnapshot, null, 2)}</pre>
                        </details>
                      )}
                    </td>
                    <td className="number">{money(a.amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
      {data.orders?.map((o) => (
        <section className="panel detail-panel" key={o.id}>
          <div className="panel-heading">
            <h2>Pedido #{o.orderNumber}</h2>
            <span className="tag">{o.itemCount} itens</span>
          </div>
          <OrderDetails order={o} />
        </section>
      ))}
      {data.payment && (
        <section className="panel detail-panel">
          <div className="panel-heading">
            <div>
              <h2>Pagamento confirmado</h2>
              <p>
                {date(data.payment.paidAt)} · Confirmado por {data.payment.confirmedBy}
              </p>
            </div>
            {allowed(user, "ROLE_FINANCE") && !data.payment.winthor && (
              <button className="button secondary" onClick={() => setModal("link")}>
                <Link2 size={16} />
                Vincular RECNUM
              </button>
            )}
          </div>
          <dl className="winthor-details">
            <div>
              <dt>Valor calculado</dt>
              <dd>{money(data.payment.calculatedAmount)}</dd>
            </div>
            <div>
              <dt>Valor pago</dt>
              <dd>{money(data.payment.amount)}</dd>
            </div>
            <div>
              <dt>Origem do valor</dt>
              <dd>
                {true === data.payment.manualAmount
                  ? "Informado manualmente"
                  : "Calculado pelo sistema"}
              </dd>
            </div>
          </dl>
          {true === data.payment.manualAmount && (
            <div className="notice info">
              <strong>Pagamento com valor manual</strong>
              <p className="preserve-lines">{data.payment.manualReason}</p>
            </div>
          )}
          <p className="preserve-lines">{data.payment.notes}</p>
          {data.payment.winthor && (
            <div className="payment-reference">
              <span className="eyebrow">WINTHOR · ROTINA 749</span>
              <h3>RECNUM {data.payment.winthor.recnum}</h3>
              <p>
                {data.payment.winthor.verification === "winthor_lookup"
                  ? "Lançamento localizado em PCLANC. Detalhes preservados no momento do vínculo."
                  : "Referência informada manualmente. Consulta ao Winthor não habilitada."}
              </p>
              {data.payment.winthor.details && (
                <div>
                  {data.payment.winthor.details.records.map((record, i) => (
                    <section key={String(record.RECNUM ?? i)}>
                      <h4>Registro PCLANC · RECNUM {String(record.RECNUM)}</h4>
                      <dl className="winthor-details">
                        {Object.entries(record)
                          .filter(([key, value]) => {
                            const field = key.toUpperCase();
                            const hasValue =
                              null !== value &&
                              undefined !== value &&
                              "" !== String(value).trim() &&
                              "—" !== String(value).trim();
                            const isInternalField =
                              "INDICE" === field ||
                              "VERSAO" === field ||
                              "VERSAOROTINA" === field ||
                              "VERSION" === field;

                            return true === hasValue && false === isInternalField;
                          })
                          .map(([key, value]) => (
                            <div key={key}>
                              <dt>{key}</dt>
                              <dd>{String(value)}</dd>
                            </div>
                          ))}
                      </dl>
                    </section>
                  ))}
                </div>
              )}
            </div>
          )}
        </section>
      )}
      {modal && (
        <Modal
          title={
            modal === "pay"
              ? "Confirmar pagamento"
              : modal === "link"
                ? "Vincular lançamento da rotina 749"
                : "Aprovar comissão"
          }
          close={() => {
            setModal(undefined);
            setError("");
          }}
        >
          <form className="form-stack" onSubmit={submit}>
            <ErrorNotice message={error} />
            {modal === "approve" ? (
              <p className="form-description">
                Confirme a aprovação de {money(data.netAmount)} após conferir os pedidos, a memória
                de cálculo e as deduções.
              </p>
            ) : (
              <>
                {modal === "pay" && (
                  <>
                    <div className="notice info">
                      Valor líquido: <strong>{money(data.netAmount)}</strong>
                    </div>
                    <p className="form-description">
                      Use o valor calculado. A alteração manual é uma exceção e ficará registrada na
                      auditoria, com os dois valores preservados.
                    </p>
                    <label>
                      Valor do pagamento
                      <input
                        name="amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        max="999999999999.99"
                        required
                        readOnly={false === manualAmount}
                        value={true === manualAmount ? paymentAmount : data.netAmount}
                        onChange={(event) => setPaymentAmount(event.target.value)}
                      />
                    </label>
                    <label className="checkbox-row">
                      <input
                        type="checkbox"
                        checked={manualAmount}
                        disabled={busy}
                        onChange={(event) => {
                          setManualAmount(event.target.checked);
                          setPaymentAmount(data.netAmount);
                        }}
                      />
                      Preciso informar um valor manual
                    </label>
                    {true === manualAmount && (
                      <label>
                        Justificativa da alteração manual
                        <textarea
                          name="manualReason"
                          required
                          minLength={10}
                          maxLength={2000}
                          rows={3}
                          placeholder="Explique por que o pagamento difere do valor calculado."
                        />
                      </label>
                    )}
                    <label>
                      Data do pagamento
                      <input
                        name="paidAt"
                        type="date"
                        required
                        max={new Date().toLocaleDateString("en-CA")}
                        defaultValue={new Date().toLocaleDateString("en-CA")}
                      />
                    </label>
                    <label>
                      Observação / Comprovante
                      <textarea
                        name="notes"
                        required
                        minLength={10}
                        maxLength={2000}
                        rows={3}
                        placeholder="Descreva como o pagamento foi conferido."
                      />
                    </label>
                  </>
                )}
                <label>
                  RECNUM da rotina 749 {modal === "pay" ? "(opcional)" : ""}
                  <input
                    name="recnum"
                    inputMode="numeric"
                    pattern="[1-9][0-9]{0,17}"
                    maxLength={18}
                    required={modal === "link"}
                    placeholder="Número da transação no Winthor"
                  />
                </label>
                <p className="form-description">
                  {modal === "pay"
                    ? "Você pode confirmar sem RECNUM e vincular o lançamento depois. Ao informar, o sistema consulta a linha correspondente na PCLANC e guarda os detalhes."
                    : "O vínculo consulta o RECNUM em PCLANC e preserva os detalhes do registro. Confira valor e beneficiário antes de associar."}
                </p>
              </>
            )}
            <div className="form-actions">
              <button className="button primary" disabled={busy}>
                {busy ? "Processando…" : "Confirmar"}
                <Check size={17} />
              </button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
