import { money, type CalculationLine, type CalculationOrder, type Order } from "@/lib/api";

import { commissionReferenceLabel } from "@/lib/commission";

const unitMoney = (value: string) =>
  new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  }).format(Number(value));

export function CalculationLines({
  items,
  orders = [],
  percentage,
  basis,
  snapshots = [],
  orderDetails = [],
}: {
  items: CalculationLine[];
  orders?: CalculationOrder[];
  percentage?: string;
  basis?: string;
  snapshots?: Order[];
  orderDetails?: Pick<
    Order,
    "orderNumber" | "invoiceNumber" | "authorCustomerCode" | "authorCustomerName"
  >[];
}) {
  const referenceLabel = commissionReferenceLabel(basis);
  const orderNumbers = [...new Set(items.map((line) => line.orderNumber))];

  return (
    <div className="calculation-orders">
      <p className="muted calculation-explanation">
        Confira os valores de venda e de {referenceLabel} de cada produto. A comissão por produto
        considera a diferença entre venda e referência, desconta o frete proporcional ao valor
        vendido e aplica o percentual. Os centavos são ajustados para a soma fechar com a comissão
        bruta do pedido. Débitos, cancelamentos e devoluções são deduzidos depois, no total da
        comissão.
      </p>
      {orderNumbers.map((number) => {
        const lines = items.filter((line) => line.orderNumber === number);
        const totals = orders.find((order) => order.orderNumber === number);
        const snapshot = snapshots.find((order) => order.orderNumber === number);
        const orderDetail = snapshot ?? orderDetails.find((order) => order.orderNumber === number);
        const context = lines[0]?.context;

        return (
          <section
            className="calculation-order"
            key={number}
            aria-label={`Cálculo do pedido ${number}`}
          >
            <div className="calculation-order-heading">
              <div>
                <h3 className="order-number-invoice">
                  Pedido #{number}
                  <span className="order-invoice">NF {orderDetail?.invoiceNumber ?? "—"}</span>
                </h3>
                {undefined !== orderDetail && (
                  <p className="calculation-order-customer">
                    {orderDetail.authorCustomerCode ?? "—"} ·{" "}
                    {orderDetail.authorCustomerName ?? "Nome não preservado"}
                  </p>
                )}
              </div>
              <span>
                {undefined !== context
                  ? `Filial ${context.branch} · Tabela ${context.orderRegion} · PSD (Revenda) ${context.psdRegion} · PSCF (Consumidor Final) ${context.pscfRegion}`
                  : "Referências preservadas no lançamento"}
              </span>
            </div>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th className="number">Qtd.</th>
                    <th className="number">Venda unit.</th>
                    <th className="number">Venda total</th>
                    <th className="number calculation-reference">{referenceLabel} unit.</th>
                    <th className="number calculation-reference">{referenceLabel} total</th>
                    <th className="number">Diferença / base</th>
                    <th className="number">%</th>
                    <th className="number calculation-commission">Comissão do produto</th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, index) => {
                    const savedItem = snapshot?.items?.find(
                      (item) => item.productCode === line.productCode,
                    );
                    const appliedPercentage = line.percentageApplied ?? percentage;

                    return (
                      <tr key={`${line.productCode}-${index}`}>
                        <td className="calculation-product">
                          <strong>
                            {line.description ??
                              savedItem?.description ??
                              `Produto ${line.productCode}`}
                          </strong>
                          <small>
                            Cód. {line.productCode}
                            {undefined !== line.unit && "" !== line.unit ? ` · ${line.unit}` : ""}
                          </small>
                          <details>
                            <summary>Conferir cálculo e preços</summary>
                            {undefined !== line.allocatedFreight && (
                              <small>
                                Frete rateado na base: {unitMoney(line.allocatedFreight)}
                              </small>
                            )}
                            {undefined !== line.roundingAdjustment &&
                              "0.00" !== line.roundingAdjustment && (
                                <small>Ajuste de centavos: {money(line.roundingAdjustment)}</small>
                              )}
                            <small>
                              PSD (Revenda) unit.:{" "}
                              {undefined === line.unitPsd || null === line.unitPsd
                                ? "—"
                                : unitMoney(line.unitPsd)}
                            </small>
                            <small>
                              PSCF (Consumidor Final) unit.:{" "}
                              {undefined === line.unitPscf || null === line.unitPscf
                                ? "—"
                                : unitMoney(line.unitPscf)}
                            </small>
                            <small>
                              PTABELA:{" "}
                              {undefined === line.unitTable || null === line.unitTable
                                ? "—"
                                : unitMoney(line.unitTable)}
                            </small>
                            <small>
                              Plano {line.paymentPlan ?? "—"} · {line.priceColumn ?? "—"}
                            </small>
                            {undefined !== line.discountPercentage && (
                              <small>
                                Desconto do item:{" "}
                                {Number(line.discountPercentage).toLocaleString("pt-BR")}%
                              </small>
                            )}
                            {line.combo?.components.map((component, componentIndex) => (
                              <small key={componentIndex}>
                                Componente {component.productCode} ·{" "}
                                {Number(component.quantityPerCombo).toLocaleString("pt-BR")} por
                                combo · PSD (Revenda) {unitMoney(component.unitPsd)} / PSCF
                                (Consumidor Final) {unitMoney(component.unitPscf)}
                              </small>
                            ))}
                          </details>
                        </td>
                        <td className="number">
                          {Number(line.quantity).toLocaleString("pt-BR", {
                            maximumFractionDigits: 6,
                          })}
                        </td>
                        <td className="number">{unitMoney(line.unitSale)}</td>
                        <td className="number">{money(line.sales)}</td>
                        <td className="number">{unitMoney(line.unitReference)}</td>
                        <td className="number">{money(line.reference)}</td>
                        <td className="number">
                          {undefined === line.margin ? "—" : money(line.margin)}
                        </td>
                        <td className="number">
                          {undefined === appliedPercentage
                            ? "—"
                            : `${Number(appliedPercentage).toLocaleString("pt-BR")}%`}
                        </td>
                        <td className="number calculation-commission">
                          {undefined === line.commissionAmount ? "—" : money(line.commissionAmount)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
                {undefined !== totals && (
                  <tfoot>
                    <tr>
                      <th colSpan={3}>Totais do pedido</th>
                      <td className="number">{money(totals.sales)}</td>
                      <td />
                      <td className="number">{money(totals.reference)}</td>
                      <td className="number" colSpan={2}>
                        Base após frete: {money(totals.baseAmount)}
                      </td>
                      <td className="number calculation-commission">{money(totals.grossAmount)}</td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
            {undefined !== totals && (
              <div className="calculation-order-total">
                <span>
                  Frete descontado da base: <strong>{money(totals.deductedFreight)}</strong>
                </span>
                <span>
                  Comissão bruta do pedido: <strong>{money(totals.grossAmount)}</strong>
                </span>
              </div>
            )}
            {lines.some((line) => undefined === line.commissionAmount) && (
              <p className="muted calculation-history">
                A memória deste lançamento não contém dados suficientes para conferir a comissão por
                produto.
              </p>
            )}
          </section>
        );
      })}
    </div>
  );
}
