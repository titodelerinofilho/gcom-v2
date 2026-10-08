import Image from "next/image";
export function Brand() {
  return (
    <span className="brand-lockup">
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
