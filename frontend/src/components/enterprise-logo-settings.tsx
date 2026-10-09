"use client";

import Image from "next/image";
import { useEffect, useState } from "react";
import { api, type Enterprise } from "@/lib/api";
import { FieldsForm } from "./collections";
import { ErrorNotice } from "./ui";

export function EnterpriseLogoSettings({
  enterprise,
  updated,
}: {
  enterprise: Enterprise;
  updated: (value: Enterprise) => void;
}) {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [removing, setRemoving] = useState(false);
  useEffect(() => {
    if (null === file) {
      setPreview(null);
      return;
    }
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);
  const notify = (value: Enterprise, text: string) => {
    updated(value);
    setMessage(text);
    window.dispatchEvent(new Event("enterprise-updated"));
  };
  return (
    <section className="panel form-panel enterprise-logos">
      <h2>Logos</h2>
      <p className="muted">
        A logo GCOM identifica o sistema. A logo da empresa aparece junto dela no menu e nos PDFs.
      </p>
      <div className="enterprise-logo-grid">
        <div>
          <h3>GCOM</h3>
          <div className="enterprise-logo-preview">
            <Image src="/logo-gcom.png" alt="GCOM" width={180} height={70} />
          </div>
        </div>
        <div>
          <h3>{enterprise.tradeName}</h3>
          <div className="enterprise-logo-preview">
            {null !== (preview ?? enterprise.logoUrl) ? (
              <Image
                src={(preview ?? enterprise.logoUrl)!}
                alt="Logo da empresa"
                width={180}
                height={90}
                unoptimized
              />
            ) : (
              <span className="muted">Nenhuma logo enviada</span>
            )}
          </div>
        </div>
      </div>
      <ErrorNotice message={error} />
      {message && <p role="status">{message}</p>}
      {false === enterprise.configured ? (
        <p>Salve os dados da empresa para enviar a logo.</p>
      ) : (
        <>
          <FieldsForm
            label="Enviar logo da empresa"
            done={() => setMessage("Logo da empresa atualizada.")}
            submitDisabled={removing}
            submit={async (form) => {
              setMessage("");
              setError("");
              const selected = form.get("logo");
              if (false === selected instanceof File || 0 === selected.size)
                throw new Error("Selecione uma imagem.");
              if (1048576 < selected.size) throw new Error("A imagem deve ter até 1 MB.");
              const body = new FormData();
              body.set("logo", selected);
              const value = await api<Enterprise>("/settings/enterprise/logo", {
                method: "POST",
                body,
              });
              setFile(null);
              notify(value, "Logo da empresa atualizada.");
            }}
          >
            <label>
              Imagem PNG ou JPEG (até 1 MB, máximo 4096 × 4096 pixels)
              <input
                name="logo"
                type="file"
                accept="image/png,image/jpeg"
                required
                disabled={removing}
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              />
            </label>
          </FieldsForm>
          {enterprise.logoUrl && (
            <button
              type="button"
              className="button secondary"
              disabled={removing}
              onClick={async () => {
                setRemoving(true);
                setError("");
                setMessage("");
                try {
                  notify(
                    await api<Enterprise>("/settings/enterprise/logo", { method: "DELETE" }),
                    "Logo da empresa removida.",
                  );
                  setFile(null);
                } catch (e) {
                  setError((e as Error).message);
                } finally {
                  setRemoving(false);
                }
              }}
            >
              Remover logo da empresa
            </button>
          )}
        </>
      )}
    </section>
  );
}
