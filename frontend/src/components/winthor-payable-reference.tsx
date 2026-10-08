import { money, type Payment } from "@/lib/api";
import { WinthorBrand } from "./winthor-brand";

type PayableReference = NonNullable<Payment["winthor"]>;
type PayableField = { key: string; label: string; format?: "money" | "date" };

const identificationFields: PayableField[] = [
  { key: "CODFORNEC", label: "Favorecido (código)" },
  { key: "CODCONTA", label: "Conta (código)" },
  { key: "CODFILIAL", label: "Filial" },
  { key: "NUMNOTA", label: "Nota fiscal" },
  { key: "DUPLIC", label: "Parcela" },
  { key: "NOMEFUNC", label: "Usuário do lançamento" },
  { key: "TIPOLANC", label: "Tipo de lançamento" },
  { key: "TIPOPARCEIRO", label: "Tipo de parceiro" },
  { key: "MOEDA", label: "Moeda" },
  { key: "NFSERVICO", label: "Nota de serviço" },
];

const dateFields: PayableField[] = [
  { key: "DTLANC", label: "Lançamento", format: "date" },
  { key: "DTEMISSAO", label: "Emissão", format: "date" },
  { key: "DTVENC", label: "Vencimento", format: "date" },
  { key: "DTPAGTO", label: "Pagamento", format: "date" },
];

const summaryFields: PayableField[] = [
  { key: "VALOR", label: "Valor do lançamento", format: "money" },
  { key: "VPAGO", label: "Valor pago no Winthor", format: "money" },
  { key: "DTCOMPETENCIA", label: "Data de competência", format: "date" },
];

function hasValue(value: unknown): boolean {
  return (
    null !== value &&
    undefined !== value &&
    "" !== String(value).trim() &&
    "—" !== String(value).trim()
  );
}

function formatValue(value: unknown, format?: PayableField["format"]): string {
  if (false === hasValue(value)) {
    return "—";
  }

  const text = String(value).trim();

  if ("money" === format && true === Number.isFinite(Number(text))) {
    return money(text);
  }

  if ("date" === format && true === /^\d{4}-\d{2}-\d{2}/.test(text)) {
    const date = new Date(`${text.slice(0, 10)}T12:00:00`);

    if (false === Number.isNaN(date.getTime())) {
      return new Intl.DateTimeFormat("pt-BR").format(date);
    }
  }

  return text;
}

function PayableFields({
  record,
  fields,
}: {
  record: Record<string, unknown>;
  fields: PayableField[];
}) {
  return (
    <dl className="payable-fields">
      {fields
        .filter((field) => true === hasValue(record[field.key]))
        .map((field) => (
          <div key={field.key}>
            <dt>{field.label}</dt>
            <dd>{formatValue(record[field.key], field.format)}</dd>
          </div>
        ))}
    </dl>
  );
}

export function WinthorPayableReference({ reference }: { reference: PayableReference }) {
  const consulted = "winthor_lookup" === reference.verification;
  const records = reference.details?.records ?? [];

  return (
    <section className="payable-reference" aria-label="Lançamento de contas a pagar no Winthor">
      <div className="payable-heading">
        <div className="winthor-heading">
          <WinthorBrand />
          <div>
            <p className="payable-routine">WINTHOR · ROTINA 749</p>
            <h3>Lançamento de contas a pagar</h3>
            <p className="payable-number">
              Lançamento nº <strong>{reference.recnum}</strong>
            </p>
          </div>
        </div>
        <span className={`payable-status ${true === consulted ? "consulted" : "manual"}`}>
          {true === consulted ? "Consultado no Winthor" : "Número informado"}
        </span>
      </div>
      <p className="payable-context">
        {true === consulted
          ? "Dados do lançamento preservados no momento do vínculo com este pagamento."
          : "Número associado ao pagamento sem consulta ao Winthor. Confira o lançamento na rotina 749."}
      </p>

      {records.map((source, index) => {
        const record = Object.fromEntries(
          Object.entries(source).map(([key, value]) => [key.toUpperCase(), value]),
        );
        const hasIdentification = identificationFields.some(
          (field) => true === hasValue(record[field.key]),
        );
        const hasDates = dateFields.some((field) => true === hasValue(record[field.key]));

        return (
          <div className="payable-record" key={`${reference.recnum}-${index}`}>
            <dl className="payable-summary">
              {summaryFields.map((field) => (
                <div key={field.key}>
                  <dt>{field.label}</dt>
                  <dd>{formatValue(record[field.key], field.format)}</dd>
                </div>
              ))}
            </dl>
            {true === hasValue(record.HISTORICO) && (
              <div className="payable-history">
                <h4>Histórico do lançamento</h4>
                <p>{formatValue(record.HISTORICO)}</p>
              </div>
            )}
            <div className="payable-sections">
              {true === hasIdentification && (
                <section className="payable-section" aria-label="Identificação do lançamento">
                  <h4>Identificação</h4>
                  <PayableFields record={record} fields={identificationFields} />
                </section>
              )}
              {true === hasDates && (
                <section className="payable-section" aria-label="Datas do lançamento">
                  <h4>Datas do lançamento</h4>
                  <PayableFields record={record} fields={dateFields} />
                </section>
              )}
            </div>
          </div>
        );
      })}
      {true === consulted && 0 === records.length && (
        <p className="payable-context">
          Os detalhes deste lançamento não estão disponíveis no registro do pagamento.
        </p>
      )}
    </section>
  );
}
