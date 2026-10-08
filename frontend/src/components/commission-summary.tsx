import { money, type Adjustment } from "@/lib/api";

export function CommissionSummary({
  gross,
  deductions,
  net,
  adjustments,
}: {
  gross: string;
  deductions: string;
  net: string;
  adjustments?: Adjustment[];
}) {
  const totals = (adjustments ?? []).reduce(
    (amounts, adjustment) => {
      amounts[adjustment.type] = (amounts[adjustment.type] ?? 0) + Number(adjustment.amount);
      return amounts;
    },
    {} as Record<string, number>,
  );

  return (
    <section className="commission-summary" aria-label="Resumo dos valores da comissão">
      <div className="stat-grid three">
        <article className="stat-card">
          <span>Comissão bruta gerada</span>
          <strong>{money(gross)}</strong>
          <small>Antes das deduções do cliente</small>
        </article>
        <article className="stat-card deduction-card">
          <span>Deduções totais</span>
          <strong>− {money(deductions)}</strong>
          <small>Débitos, cancelamentos e devoluções</small>
        </article>
        <article className="stat-card accent">
          <span>Valor líquido a lançar</span>
          <strong>{money(net)}</strong>
          <small>Bruto − deduções</small>
        </article>
      </div>
      {undefined !== adjustments && (
        <dl className="deduction-breakdown">
          {[
            ["debt", "Débitos"],
            ["cancellation", "Cancelamentos"],
            ["return", "Devoluções"],
          ].map(([type, label]) => (
            <div key={type}>
              <dt>{label}</dt>
              <dd>{money(totals[type] ?? 0)}</dd>
            </div>
          ))}
        </dl>
      )}
    </section>
  );
}
