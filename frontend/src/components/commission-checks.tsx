import { date, money, type CommissionChecks } from "@/lib/api";
import Link from "next/link";
import { WinthorBrand } from "./winthor-brand";

export function CommissionChecksPanel({
  checks,
  selectedReturns,
  onSelect,
}: {
  checks?: CommissionChecks;
  selectedReturns?: string[];
  onSelect?: (transactions: string[]) => void;
}) {
  if (undefined === checks) return null;

  return (
    <section className="commission-checks" aria-label="Conferência no Winthor">
      <div className="panel-heading winthor-heading">
        <WinthorBrand />
        <div>
          <h3>Conferência no Winthor</h3>
          <p>Consultado em {date(checks.checkedAt)}</p>
        </div>
      </div>
      <div className="checks-summary">
        <span>
          <strong>{checks.overdueTitles.length}</strong> títulos vencidos
        </span>
        <span>
          Em aberto: <strong>{money(checks.overdueTotal)}</strong>
        </span>
        <span>
          <strong>{checks.returnsFound}</strong> devoluções disponíveis
        </span>
      </div>
      <p className="form-description">
        Os títulos vencidos são apresentados para conferência e não são abatidos diretamente da
        comissão.
      </p>
      {0 === checks.overdueTitles.length ? (
        <p className="muted">Nenhum título vencido encontrado para o cliente ou seus vinculados.</p>
      ) : (
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Cliente</th>
                <th>NF / parcela</th>
                <th>Vencimento</th>
                <th>Atraso</th>
                <th>Cobrança</th>
                <th className="number">Em aberto</th>
              </tr>
            </thead>
            <tbody>
              {checks.overdueTitles.map((title) => (
                <tr key={`${title.customerCode}-${title.transaction}-${title.installment}`}>
                  <td>
                    {title.customerCode} · {title.customerName}
                  </td>
                  <td>
                    {title.invoiceNumber} / {title.installment}
                  </td>
                  <td>{date(title.dueDate)}</td>
                  <td>{title.overdueDays} dias</td>
                  <td>{title.collectionCode}</td>
                  <td className="number">{money(title.amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <h3>Devoluções</h3>
      <p className="form-description">
        {undefined !== onSelect
          ? "Selecione as devoluções que devem ser abatidas e simule novamente para conferir o valor líquido. As demais ficam disponíveis para outro lançamento."
          : "As devoluções marcadas foram selecionadas pelo operador neste lançamento."}
      </p>
      {true === checks.returns.some((item) => 0 < (item.items?.length ?? 0)) && (
        <p className="form-description">
          O histórico cruza pedido, produto, cliente e filial com pagamentos confirmados neste GCOM.
          Pagamentos existentes somente no legado não estão incluídos.
        </p>
      )}
      {0 === checks.returns.length ? (
        <p className="muted">Nenhuma devolução disponível pelos critérios do legado.</p>
      ) : (
        checks.returns.map((item) => (
          <div className="return-candidate" key={item.transaction}>
            <label className="checkbox-row">
              <input
                type="checkbox"
                disabled={undefined === onSelect}
                checked={
                  undefined === selectedReturns
                    ? item.selected
                    : true === selectedReturns.includes(item.transaction)
                }
                onChange={(event) => {
                  if (undefined !== onSelect)
                    onSelect(
                      true === event.target.checked
                        ? [...(selectedReturns ?? []), item.transaction]
                        : (selectedReturns ?? []).filter((value) => value !== item.transaction),
                    );
                }}
              />
              <span>
                <strong>
                  NF {item.invoiceNumber} · Pedido {item.orderNumbers.join(", ")}
                </strong>
                <small>
                  {item.customerCode} · {item.customerName}
                  {null !== item.date ? ` · ${date(item.date)}` : ""}
                </small>
                <small>
                  {null === item.deductionAmount
                    ? "O valor da dedução será calculado ao selecionar e simular."
                    : `Dedução: ${money(item.deductionAmount)}`}
                </small>
              </span>
            </label>
            {0 < (item.items?.length ?? 0) && (
              <ul className="return-payment-items" aria-label="Comissões dos produtos devolvidos">
                {item.items?.map((product, index) => (
                  <li key={`${product.orderNumber}-${product.productCode}-${index}`}>
                    <div>
                      <strong>
                        {product.productCode} · {product.description}
                      </strong>
                      <small>
                        {"0" === product.orderNumber || "" === product.orderNumber
                          ? "Sem pedido vinculado"
                          : `Pedido ${product.orderNumber}`}{" "}
                        · Quantidade devolvida: {Number(product.quantity).toLocaleString("pt-BR")}
                      </small>
                    </div>
                    <div className="return-payment-history">
                      {"paid" === product.paymentStatus ? (
                        product.paidCommissions.map((payment) => (
                          <div key={payment.commissionId}>
                            <span className="return-paid-badge">Comissão paga</span>
                            <Link
                              href={`/commissions/${payment.commissionId}`}
                              target="_blank"
                              rel="noopener noreferrer"
                            >
                              {payment.commissionCode} ↗
                            </Link>
                            <small>
                              {"atg" === payment.mode ? "ATG" : "Normal"} · Paga em{" "}
                              {date(payment.paidAt)}
                            </small>
                          </div>
                        ))
                      ) : (
                        <span className="muted">
                          {"unmatched" === product.paymentStatus
                            ? "Dados insuficientes para correlacionar o pagamento"
                            : "Sem comissão paga encontrada neste GCOM"}
                        </span>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ))
      )}
    </section>
  );
}
