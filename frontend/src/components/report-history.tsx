"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, date, dateTime } from "@/lib/api";
import { Empty, ErrorNotice, Loading, Pagination } from "./ui";
import { Heading } from "./collections";

export type SavedReport = {
  id: string;
  kind: string;
  from: string;
  to: string;
  filters: Record<string, string | null>;
  recordCount: number;
  createdAt: string;
  createdBy: string;
  url: string;
};

const labels: Record<string, string> = {
  customer: "Cliente principal",
  orderNumber: "Pedido",
  status: "Status",
  mode: "Modalidade",
  type: "Tipo",
  state: "Situação",
  dateBasis: "Data considerada",
  normal: "Normal",
  atg: "ATG",
  paid: "Paga / Pagamento",
  pending: "Pendente",
  approved: "Aprovada",
  rejected: "Reprovada",
  debt: "Débito",
  return: "Devolução",
  cancellation: "Cancelamento",
  deducted: "Deduzido",
  created: "Cadastro",
  applied: "Abatimento",
};

export function ReportHistory({ revision = 0 }: { revision?: number }) {
  const [page, setPage] = useState(1);
  const [data, setData] = useState<{ items: SavedReport[]; total: number }>();
  const [error, setError] = useState("");
  useEffect(() => {
    let active = true;
    api<{ items: SavedReport[]; total: number }>("/reports/history?page=" + page)
      .then((result) => {
        if (true === active) {
          setData(result);
          setError("");
        }
      })
      .catch((e: Error) => {
        if (true === active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [page, revision]);
  return (
    <section className="panel report-history">
      <div className="panel-heading">
        <div>
          <h2>Histórico de relatórios</h2>
          <p>Dados preservados em cada exportação. Abra um registro para baixar novamente.</p>
        </div>
      </div>
      <ErrorNotice message={error} />
      {undefined === data ? (
        <Loading />
      ) : 0 === data.items.length ? (
        <Empty
          title="Nenhum relatório salvo"
          text="Ao exportar um relatório, ele será guardado aqui com um link permanente."
        />
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Relatório</th>
                <th>Período</th>
                <th>Gerado em / Responsável</th>
                <th className="number">Registros</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.items.map((item) => (
                <tr key={item.id}>
                  <td>
                    <Link className="text-link" href={item.url}>
                      {item.kind === "commissions"
                        ? "Comissões e pagamentos"
                        : "Débitos, devoluções e cancelamentos"}
                    </Link>
                  </td>
                  <td>
                    {date(item.from)} a {date(item.to)}
                  </td>
                  <td>
                    {dateTime(item.createdAt)}
                    <small>{item.createdBy}</small>
                  </td>
                  <td className="number">{item.recordCount}</td>
                  <td>
                    <Link className="button secondary" href={item.url}>
                      Abrir relatório
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {undefined !== data && <Pagination page={page} total={data.total} change={setPage} />}
    </section>
  );
}

export function SavedReportPage({ id }: { id: string }) {
  const [data, setData] = useState<SavedReport>();
  const [error, setError] = useState("");
  useEffect(() => {
    api<SavedReport>("/reports/history/" + id)
      .then(setData)
      .catch((e: Error) => setError(e.message));
  }, [id]);
  return (
    <>
      <Heading
        eyebrow="HISTÓRICO DE RELATÓRIOS"
        title="Relatório salvo"
        text="Cada download utiliza os dados preservados na geração original."
      />
      <ErrorNotice message={error} />
      {undefined === data ? (
        "" === error && <Loading />
      ) : (
        <section className="panel form-panel">
          <h2>
            {data.kind === "commissions"
              ? "Comissões e pagamentos"
              : "Débitos, devoluções e cancelamentos"}
          </h2>
          <dl className="report-saved-meta">
            <div>
              <dt>Dados preservados em</dt>
              <dd>{dateTime(data.createdAt)}</dd>
            </div>
            <div>
              <dt>Responsável</dt>
              <dd>{data.createdBy}</dd>
            </div>
            <div>
              <dt>Período</dt>
              <dd>
                {date(data.from)} a {date(data.to)}
              </dd>
            </div>
            <div>
              <dt>Registros</dt>
              <dd>{data.recordCount}</dd>
            </div>
            {Object.entries(data.filters)
              .filter(([, value]) => null !== value)
              .map(([key, value]) => (
                <div key={key}>
                  <dt>{labels[key] ?? key}</dt>
                  <dd>{labels[value ?? ""] ?? value}</dd>
                </div>
              ))}
          </dl>
          <p className="form-description">
            Alterações posteriores nas comissões ou pagamentos não modificam este relatório. O
            arquivo informa a data original dos dados e a data e hora da nova geração, no horário de
            Fortaleza.
          </p>
          <div className="export-actions">
            {["pdf", "xlsx", "csv"].map((format) => (
              <a
                key={format}
                className="button primary"
                href={"/api/reports/history/" + id + "." + format}
              >
                Baixar {format.toUpperCase()}
              </a>
            ))}
          </div>
          <p>
            <Link href="/reports" className="text-link">
              Voltar aos relatórios
            </Link>
          </p>
        </section>
      )}
    </>
  );
}
