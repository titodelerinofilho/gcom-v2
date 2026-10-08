"use client";
import { useEffect, useRef } from "react";
import { X, ArrowLeft, ArrowRight, LoaderCircle, Inbox } from "lucide-react";
export function Status({ value }: { value: string }) {
  const names: Record<string, string> = {
    pending: "Aguardando aprovação",
    approved: "Pronta para pagamento",
    paid: "Paga",
    rejected: "Reprovada",
  };
  return (
    <span className={`status ${value}`}>
      <span />
      {names[value] ?? value}
    </span>
  );
}
export function Empty({
  title = "Nenhum registro encontrado",
  text = "Os registros aparecerão aqui conforme as operações forem realizadas.",
}: {
  title?: string;
  text?: string;
}) {
  return (
    <div className="empty">
      <Inbox size={32} />
      <strong>{title}</strong>
      <p>{text}</p>
    </div>
  );
}
export function Loading() {
  return (
    <div className="empty">
      <LoaderCircle className="spin" />
      Carregando dados…
    </div>
  );
}
export function Pagination({
  page,
  total,
  change,
}: {
  page: number;
  total: number;
  change: (p: number) => void;
}) {
  return (
    <div className="pagination">
      <span>
        {total} registros · Página {page} de {Math.max(1, Math.ceil(total / 30))}
      </span>
      <div>
        <button
          className="icon-button"
          aria-label="Página anterior"
          disabled={page === 1}
          onClick={() => change(page - 1)}
        >
          <ArrowLeft size={16} />
        </button>
        <button
          className="icon-button"
          aria-label="Próxima página"
          disabled={page * 30 >= total}
          onClick={() => change(page + 1)}
        >
          <ArrowRight size={16} />
        </button>
      </div>
    </div>
  );
}
export function Modal({
  title,
  children,
  close,
  wide = false,
}: {
  title: string;
  children: React.ReactNode;
  close: () => void;
  wide?: boolean;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current;
    dialog?.showModal();
    return () => dialog?.close();
  }, []);
  return (
    <dialog ref={ref} onCancel={close} className={true === wide ? "modal modal-wide" : "modal"}>
      <div className="modal-heading">
        <h2>{title}</h2>
        <button className="icon-button" type="button" aria-label="Fechar" onClick={close}>
          <X size={20} />
        </button>
      </div>
      {children}
    </dialog>
  );
}
export function ErrorNotice({ message }: { message: string }) {
  return message ? (
    <div role="alert" className="notice error">
      {message}
    </div>
  ) : null;
}
