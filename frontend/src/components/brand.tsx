import Image from "next/image";
import type { Enterprise } from "@/lib/api";
export function Brand({ enterprise }: { enterprise?: Enterprise }) {
  const logoUrl = enterprise?.logoUrl ?? null;
  return (
    <span className="brand-lockup">
      {null !== logoUrl && (
        <>
          <Image
            className="enterprise-brand-logo"
            src={logoUrl}
            alt={enterprise?.tradeName ?? "Logo da empresa"}
            width={118}
            height={37}
            unoptimized
            priority
          />
          <span className="brand-divider" aria-hidden="true" />
        </>
      )}
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
