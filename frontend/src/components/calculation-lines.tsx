import { money, type CalculationLine, type CalculationOrder, type Order } from "@/lib/api";

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
  snapshots = [],
}: {
  items: CalculationLine[];
  orders?: CalculationOrder[];
  percentage?: string;
  snapshots?: Order[];
}) {
  const orderNumbers = [...new Set(items.map((line) => line.orderNumber))];

  return (
    <div className="calculation-orders">
      <p className="muted calculation-explanation">
        Confira a venda e a referência de cada produto. A comissão por produto considera a diferença
        × percentual, antes do frete. O frete é descontado na base do pedido e o arredondamento
        ocorre no total da comissão.
      </p>
      {orderNumbers.map((number) => {
        const lines = items.filter((line) => line.orderNumber === number);
        const totals = orders.find((order) => order.orderNumber === number);
        const snapshot = snapshots.find((order) => order.orderNumber === number);
        const context = lines[0]?.context;

        return (
          <section
            className="calculation-order"
            key={number}
            aria-label={`Cálculo do pedido ${number}`}
          >
            <div className="calculation-order-heading">
              <h3>Pedido #{number}</h3>
              <span>
                {undefined !== context
                  ? `Filial ${context.branch} · Tabela ${context.orderRegion} · PSD ${context.psdRegion} · PSCF ${context.pscfRegion}`
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
                    <th className="number">Referência unit.</th>
                    <th className="number">Referência total</th>
                    <th className="number">Diferença / base</th>
                    <th className="number">%</th>
                    <th className="number">Comissão antes do frete</th>
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
                            <summary>Conferir preços e composição</summary>
                            <small>
                              PSD unit.:{" "}
                              {undefined === line.unitPsd || null === line.unitPsd
                                ? "—"
                                : unitMoney(line.unitPsd)}
                            </small>
                            <small>
                              PSCF unit.:{" "}
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
                                combo · PSD {unitMoney(component.unitPsd)} / PSCF{" "}
                                {unitMoney(component.unitPscf)}
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
                          {undefined === line.commissionBeforeFreight
                            ? "—"
                            : unitMoney(line.commissionBeforeFreight)}
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
                      <td className="number" colSpan={3}>
                        Base após frete: {money(totals.baseAmount)}
                      </td>
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
            {lines.some((line) => undefined === line.commissionBeforeFreight) && (
              <p className="muted calculation-history">
                Este lançamento histórico não preservou o valor individual da comissão por produto.
              </p>
            )}
          </section>
        );
      })}
    </div>
  );
}
