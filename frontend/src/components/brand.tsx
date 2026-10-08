import Image from "next/image";
export function Brand() {
  return (
    <span className="brand-lockup">
      <Image src="/logo-dts.png" alt="DTS Distribuidora" width={118} height={37} priority />
      <span className="brand-divider" aria-hidden="true" />
      <Image
        className="gcom-logo"
        src="/logo-gcom.png"
        alt="GCOM · Gestão de comissão"
        width={150}
        height={50}
        priority
      />
    </span>
  );
}
