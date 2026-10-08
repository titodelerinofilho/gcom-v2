import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  title: "GCOM",
  description: "Pedidos, comissões e pagamentos com rastreabilidade.",
};
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
