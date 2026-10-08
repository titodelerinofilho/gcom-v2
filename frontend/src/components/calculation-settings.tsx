"use client";
import { useEffect, useState } from "react";
import { api, date, type CommissionRule, type Page, type PriceContext } from "@/lib/api";
import { FieldsForm, Heading } from "./collections";
import { allowed, useUser } from "./shell";
import { ErrorNotice, Loading, Pagination } from "./ui";

export const basisLabels = {
  margin_psd: "Margem sobre tabela PSD (Revenda)",
  margin_table: "Margem sobre PTABELA do item",
  sales: "Valor de venda dos itens",
};

export function CalculationSettings() {
  const user = useUser();
  const [contexts, setContexts] = useState<PriceContext[]>([]);
  const [rule, setRule] = useState<CommissionRule>();
  const [history, setHistory] = useState<Page<CommissionRule>>();
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [error, setError] = useState("");
  useEffect(() => {
    if (!allowed(user, "ROLE_ADMIN")) return;
    let active = true;
    Promise.all([
      api<CommissionRule>("/settings/commission-calculation"),
      api<Page<CommissionRule>>(`/settings/commission-calculation/history?page=${page}`),
    ])
      .then(([r, h]) => {
        if (active) {
          setRule(r);
          setContexts(r.priceContexts ?? []);
          setHistory(h);
          setError("");
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [user, page, revision]);
  if (!allowed(user, "ROLE_ADMIN"))
    return <ErrorNotice message="Somente administradores podem configurar o cálculo." />;
  return (
    <>
      <Heading
        eyebrow="CONFIGURAÇÃO ADMINISTRATIVA"
        title="Cálculo da comissão"
        text="Defina a fórmula para novas comissões. O histórico permanece preservado."
      />
      <ErrorNotice message={error} />
      {!rule ? (
        <Loading />
      ) : (
        <section className="panel form-panel">
          <FieldsForm
            key={rule.version}
            label="Publicar nova regra"
            done={() => {
              setRevision((r) => r + 1);
              setPage(1);
            }}
            submit={(f) =>
              api("/settings/commission-calculation", {
                method: "POST",
                body: JSON.stringify({
                  expectedVersion: rule.version,
                  percentage: f.get("percentage"),
                  basis: f.get("basis"),
                  priceContexts: contexts,
                  atgPercentage: f.get("atgPercentage"),
                  returnPercentage: f.get("returnPercentage"),
                  atgReturnPercentage: f.get("atgReturnPercentage"),
                  subtractFreight: f.get("subtractFreight") === "on",
                  applyReferenceDiscount: f.get("applyReferenceDiscount") === "on",
                  reason: f.get("reason"),
                }),
              })
            }
          >
            <p className="form-description">
              Regra vigente #{rule.version}. Bruto = (venda dos itens − referência − frete
              selecionado) × percentual / 100. Débitos e devoluções são deduzidos depois. Na base
              por venda, a referência é zero.
            </p>
            <div className="form-grid">
              <label>
                Percentual (%)
                <input
                  name="percentage"
                  type="number"
                  min="0.0001"
                  max="100"
                  step="0.0001"
                  defaultValue={rule.percentage}
                  required
                />
              </label>
            </div>
            <label>
              Comissão ATG (%)
              <input
                name="atgPercentage"
                type="number"
                min="0.0001"
                max="100"
                step="0.0001"
                defaultValue={rule.atgPercentage ?? rule.percentage}
                required
              />
            </label>
            <div className="form-grid">
              <label>
                Devolução normal (%)
                <input
                  name="returnPercentage"
                  type="number"
                  min="0.0001"
                  max="100"
                  step="0.0001"
                  defaultValue={rule.returnPercentage ?? "80"}
                  required
                />
              </label>
              <label>
                Devolução ATG (%)
                <input
                  name="atgReturnPercentage"
                  type="number"
                  min="0.0001"
                  max="100"
                  step="0.0001"
                  defaultValue={rule.atgReturnPercentage ?? "100"}
                  required
                />
              </label>
            </div>
            <h3>Pareamentos por filial e tabela do pedido</h3>
            <p className="muted">
              Cada pedido usa sua própria filial e NUMREGIAO. Uma filial específica tem prioridade
              sobre *. Bahia: PSD 30 / PSCF 32. Pernambuco: PSD 31 / PSCF 33. Tabelas sem pareamento
              bloqueiam o cálculo.
            </p>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Filial</th>
                    <th>Tabela do pedido</th>
                    <th>PSD (Revenda)</th>
                    <th>PSCF (Consumidor Final)</th>
                    <th>Ação</th>
                  </tr>
                </thead>
                <tbody>
                  {contexts.map((c, i) => (
                    <tr key={i}>
                      {(["branch", "orderRegion", "psdRegion", "pscfRegion"] as const).map(
                        (field) => (
                          <td key={field}>
                            <input
                              aria-label={`${field} do pareamento ${i + 1}`}
                              value={c[field]}
                              type={field === "branch" ? "text" : "number"}
                              min={field === "branch" ? undefined : 1}
                              required
                              onChange={(e) =>
                                setContexts((rows) =>
                                  rows.map((row, index) =>
                                    index === i
                                      ? {
                                          ...row,
                                          [field]:
                                            field === "branch"
                                              ? e.target.value
                                              : Number(e.target.value),
                                        }
                                      : row,
                                  ),
                                )
                              }
                            />
                          </td>
                        ),
                      )}
                      <td>
                        <button
                          type="button"
                          className="button secondary"
                          onClick={() =>
                            setContexts((rows) => rows.filter((_, index) => index !== i))
                          }
                        >
                          Remover
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <button
              type="button"
              className="button secondary"
              onClick={() =>
                setContexts((rows) => [
                  ...rows,
                  { branch: "*", orderRegion: 1, psdRegion: 1, pscfRegion: 2 },
                ])
              }
            >
              Adicionar pareamento
            </button>
            <label>
              Base de cálculo
              <select name="basis" defaultValue={rule.basis}>
                {Object.entries(basisLabels).map(([value, text]) => (
                  <option key={value} value={value}>
                    {text}
                  </option>
                ))}
              </select>
            </label>
            <label className="checkbox-row">
              <input name="subtractFreight" type="checkbox" defaultChecked={rule.subtractFreight} />
              Descontar frete antes de aplicar o percentual
            </label>
            <label className="checkbox-row">
              <input
                name="applyReferenceDiscount"
                type="checkbox"
                defaultChecked={rule.applyReferenceDiscount}
              />
              Aplicar PERCENTUALDESC ao preço de referência
            </label>
            <p className="muted">
              ATG usa PTABELA do item, com percentual próprio. PSD usa PCTABPR e o plano
              PCPLPAG.NUMPR preservados na importação. Desconto exige PERCENTUALDESC no item; dados
              ausentes bloqueiam o cálculo. Combos usam QTMP por unidade e PVENDA1 dos componentes
              da filial/tabela.
            </p>
            <label>
              Justificativa da alteração
              <textarea name="reason" minLength={10} maxLength={2000} rows={3} required />
            </label>
          </FieldsForm>
        </section>
      )}
      {history && (
        <section className="panel">
          <div className="panel-heading">
            <h2>Histórico de regras</h2>
          </div>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Versão / Data</th>
                  <th>Fórmula</th>
                  <th>Responsável / Justificativa</th>
                </tr>
              </thead>
              <tbody>
                {history.items.map((r) => (
                  <tr key={r.version}>
                    <td>
                      #{r.version}
                      <small>{date(r.createdAt)}</small>
                    </td>
                    <td>
                      {Number(r.percentage)}% · {basisLabels[r.basis]}
                      <small>
                        {true === Array.isArray(r.priceContexts)
                          ? `${r.priceContexts.length} pareamentos por filial/tabela`
                          : `PSD (Revenda): região ${r.psdRegion ?? "não informada"} (histórico)`}{" "}
                        · Frete {r.subtractFreight ? "descontado" : "incluído"} · Desconto{" "}
                        {r.applyReferenceDiscount ? "aplicado" : "desativado"}
                      </small>
                    </td>
                    <td>
                      {r.createdBy ?? "Configuração inicial"}
                      <small>{r.reason}</small>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} total={history.total} change={setPage} />
        </section>
      )}
    </>
  );
}
