"use client";
import Link from "next/link";
import { Brand } from "./brand";
import { usePathname, useRouter } from "next/navigation";
import { createContext, useContext, useEffect, useState } from "react";
import {
  LayoutDashboard,
  BadgePercent,
  PackageSearch,
  SlidersHorizontal,
  ChartNoAxesCombined,
  ShieldCheck,
  Users,
  LogOut,
  Menu,
  X,
  ArrowUpRight,
} from "lucide-react";
import { api, type User } from "@/lib/api";
import { ErrorNotice, Loading } from "./ui";
const UserContext = createContext<User | null>(null);
export function useUser() {
  const user = useContext(UserContext);
  if (!user) throw new Error("Sessão ausente");
  return user;
}
export function allowed(user: User, role: string) {
  return user.roles.includes("ROLE_ADMIN") || user.roles.includes(role);
}
const nav = [
  { href: "/", label: "Visão geral", icon: LayoutDashboard },
  { href: "/commissions", label: "Comissões", icon: BadgePercent },
  { href: "/winthor", label: "Consultas Winthor", icon: PackageSearch },
  { href: "/orders", label: "Pedidos", icon: PackageSearch },
  { href: "/adjustments", label: "Débitos e devoluções", icon: SlidersHorizontal },
  { href: "/reports", label: "Relatórios", icon: ChartNoAxesCombined },
  { href: "/audit", label: "Auditoria", icon: ShieldCheck, role: "ROLE_AUDITOR" },
  { href: "/settings", label: "Cálculo da comissão", icon: SlidersHorizontal, role: "ROLE_ADMIN" },
  { href: "/users", label: "Usuários", icon: Users, role: "ROLE_ADMIN" },
];
export function Shell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const path = pathname === "/dashboard" ? "/" : pathname;
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [open, setOpen] = useState(false);
  const [error, setError] = useState("");
  useEffect(() => {
    api<User>("/me")
      .then(setUser)
      .catch((e) => {
        if (e.status === 401) router.replace("/login");
        else setError(e.message);
      });
  }, [router]);
  async function logout() {
    try {
      await api("/logout", { method: "POST" });
      router.replace("/login");
    } catch (e) {
      setError((e as Error).message);
    }
  }
  if (!user)
    return (
      <main className="session-loading">
        <ErrorNotice message={error} />
        {error ? (
          <button onClick={() => window.location.reload()}>Tentar novamente</button>
        ) : (
          <Loading />
        )}
      </main>
    );
  return (
    <UserContext value={user}>
      <div className="app-shell">
        <aside className={`sidebar ${open ? "mobile-open" : ""}`}>
          <Link href="/" className="brand">
            <Brand />
          </Link>
          <button
            className="mobile-close icon-button"
            aria-label="Fechar menu"
            onClick={() => setOpen(false)}
          >
            <X />
          </button>
          <div className="nav-label">WORKSPACE</div>
          <nav>
            {nav
              .filter((n) => !n.role || allowed(user, n.role))
              .map((n) => (
                <Link
                  key={n.href}
                  href={n.href}
                  className={`nav-link ${path === n.href ? "active" : ""}`}
                  onClick={() => setOpen(false)}
                >
                  <n.icon size={19} />
                  {n.label}
                </Link>
              ))}
          </nav>
          <div className="sidebar-bottom">
            <div className="network-status">
              <span />
              Ambiente interno <ArrowUpRight size={14} />
            </div>
            <div className="user-card">
              <span className="avatar">
                {user.name
                  .split(" ")
                  .map((n) => n[0])
                  .slice(0, 2)
                  .join("")}
              </span>
              <div>
                <strong>{user.name}</strong>
                <small>
                  {user.roles.includes("ROLE_ADMIN")
                    ? "Administrador"
                    : user.roles.includes("ROLE_FINANCE")
                      ? "Financeiro"
                      : user.roles.includes("ROLE_AUDITOR")
                        ? "Auditoria"
                        : "Operação"}
                </small>
              </div>
              <button className="icon-button" aria-label="Sair" onClick={logout}>
                <LogOut size={17} />
              </button>
            </div>
          </div>
        </aside>
        <div className="main-area">
          <header className="topbar">
            <div>
              <button
                className="mobile-toggle icon-button"
                aria-label="Abrir menu"
                onClick={() => setOpen(true)}
              >
                <Menu />
              </button>
              <span>DTS</span>
              <span className="breadcrumb-slash">/</span>
              <strong>{nav.find((n) => n.href === path)?.label ?? "Comissão"}</strong>
            </div>
            <span className="topbar-note">
              <ShieldCheck size={15} /> Acesso restrito à rede interna
            </span>
          </header>
          <main className="content">
            <ErrorNotice message={error} />
            {children}
          </main>
          <footer className="app-footer">
            DTS · GCOM<span>Rastreabilidade em cada etapa</span>
          </footer>
        </div>
      </div>
    </UserContext>
  );
}
