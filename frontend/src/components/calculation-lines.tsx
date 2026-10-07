import { money, type CalculationLine } from "@/lib/api";

export function CalculationLines({ items }: { items: CalculationLine[] }) {
  return (
    <div className="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pedido / Produto</th>
            <th>Filial / Tabelas</th>
            <th>Quantidade</th>
            <th>Venda unit.</th>
            <th>PSD unit.</th>
            <th>PSCF unit.</th>
            <th>PTABELA unit.</th>
            <th>Referência aplicada</th>
            <th>Diferença total</th>
          </tr>
        </thead>
        <tbody>
          {items.map((line, index) => (
            <tr key={index}>
              <td>
                {line.orderNumber}
                <small>Produto {line.productCode}</small>
                {line.combo?.components.map((c, i) => (
                  <small key={i}>
                    Componente {c.productCode} ·{" "}
                    {Number(c.quantityPerCombo).toLocaleString("pt-BR")} por combo · PSD{" "}
                    {money(c.unitPsd)} / PSCF {money(c.unitPscf)}
                  </small>
                ))}
              </td>
              <td>
                {line.context
                  ? `Filial ${line.context.branch} · Pedido ${line.context.orderRegion}`
                  : "Regra histórica"}
                <small>
                  PSD {line.context?.psdRegion ?? "—"} / PSCF {line.context?.pscfRegion ?? "—"} ·{" "}
                  {line.priceColumn}
                </small>
              </td>
              <td className="number">{Number(line.quantity).toLocaleString("pt-BR")}</td>
              <td className="number">{money(line.unitSale)}</td>
              <td className="number">{line.unitPsd == null ? "—" : money(line.unitPsd)}</td>
              <td className="number">{line.unitPscf == null ? "—" : money(line.unitPscf)}</td>
              <td className="number">{line.unitTable == null ? "—" : money(line.unitTable)}</td>
              <td className="number">{money(line.unitReference)}</td>
              <td className="number">{line.margin == null ? "—" : money(line.margin)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
