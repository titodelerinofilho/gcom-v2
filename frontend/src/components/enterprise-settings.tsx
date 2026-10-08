"use client";
import { useEffect, useState } from "react";
import { api, type Enterprise } from "@/lib/api";
import { FieldsForm, Heading } from "./collections";
import { allowed, useUser } from "./shell";
import { ErrorNotice, Loading } from "./ui";

const fields = [
  { name: "legalName", label: "Razão social", max: 180, required: true },
  { name: "tradeName", label: "Nome fantasia", max: 120, required: true },
  { name: "cnpj", label: "CNPJ", max: 18 },
  { name: "email", label: "Email", max: 180, type: "email" },
  { name: "phone", label: "Telefone", max: 30 },
  { name: "address", label: "Endereço", max: 500 },
  { name: "commissionPrefix", label: "Prefixo das comissões", max: 20, required: true },
] as const;

export function EnterpriseSettings() {
  const user = useUser();
  const [enterprise, setEnterprise] = useState<Enterprise>();
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);
  useEffect(() => {
    if (false === allowed(user, "ROLE_ADMIN")) return;
    let active = true;
    api<Enterprise>("/settings/enterprise")
      .then((value) => {
        if (true === active) setEnterprise(value);
      })
      .catch((e: Error) => {
        if (true === active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [user]);
  if (false === allowed(user, "ROLE_ADMIN"))
    return <ErrorNotice message="Somente administradores podem configurar a empresa." />;
  return (
    <>
      <Heading
        eyebrow="CONFIGURAÇÃO ADMINISTRATIVA"
        title="Empresa"
        text="Dados da empresa e prefixo para novas comissões. Os códigos existentes permanecem preservados."
      />
      <ErrorNotice message={error} />
      {true === saved && <p role="status">Dados da empresa salvos.</p>}
      {undefined === enterprise ? (
        <Loading />
      ) : (
        <section className="panel form-panel">
          <FieldsForm
            label="Salvar empresa"
            done={() => {
              setSaved(true);
              window.dispatchEvent(new Event("enterprise-updated"));
            }}
            submit={async (form) => {
              setSaved(false);
              const payload = Object.fromEntries(
                fields.map(({ name }) => [name, String(form.get(name) ?? "").trim()]),
              );
              payload.commissionPrefix = payload.commissionPrefix.toUpperCase();
              const value = await api<Enterprise>("/settings/enterprise", {
                method: "PUT",
                body: JSON.stringify(payload),
              });
              setEnterprise(value);
            }}
          >
            <div className="form-grid">
              {fields.map((field) => (
                <label key={field.name}>
                  {field.label}
                  <input
                    name={field.name}
                    defaultValue={enterprise[field.name]}
                    maxLength={field.max}
                    required={"required" in field && true === field.required}
                    type={"type" in field ? field.type : "text"}
                    pattern={
                      field.name === "commissionPrefix"
                        ? "[A-Za-z0-9][A-Za-z0-9_-]{0,19}"
                        : undefined
                    }
                  />
                </label>
              ))}
            </div>
          </FieldsForm>
        </section>
      )}
    </>
  );
}
