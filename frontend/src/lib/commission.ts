export function commissionReferenceLabel(basis?: string): string {
  if ("margin_psd" === basis) {
    return "Referência (PSD/Revenda)";
  }

  if ("margin_table" === basis) {
    return "Referência (PTABELA)";
  }

  if ("sales" === basis) {
    return "Referência (zero)";
  }

  return "Referência preservada";
}
