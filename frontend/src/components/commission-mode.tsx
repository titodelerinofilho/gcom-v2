export function CommissionMode({ mode, detailed = false }: { mode?: string; detailed?: boolean }) {
  const atg = "atg" === mode;

  return (
    <span
      className={`commission-mode ${true === atg ? "commission-mode-atg" : "commission-mode-normal"}`}
      title={true === atg ? "Modalidade ATG — Autoagenciamento" : "Modalidade normal"}
    >
      {true === atg ? (true === detailed ? "ATG · Autoagenciamento" : "ATG") : "Normal"}
    </span>
  );
}
