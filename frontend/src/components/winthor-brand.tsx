import Image from "next/image";

export function WinthorBrand() {
  return (
    <Image
      className="winthor-logo"
      src="/logo-winthor.svg"
      alt="Winthor"
      width={56}
      height={56}
      unoptimized
    />
  );
}
