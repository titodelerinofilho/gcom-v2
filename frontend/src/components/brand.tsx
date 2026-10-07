import Image from "next/image";
export function Brand() {
  return (
    <span className="brand-lockup">
      <Image src="/logo-dts.png" alt="DTS Distribuidora" width={118} height={37} priority />
      <span className="brand-divider" aria-hidden="true" />
      <span className="gcom-wordmark">
        GCOM<small>Gestão de comissões</small>
      </span>
    </span>
  );
}
