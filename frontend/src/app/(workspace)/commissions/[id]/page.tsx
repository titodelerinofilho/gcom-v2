import { CommissionDetail } from "@/components/commission-detail";
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <CommissionDetail id={id} />;
}
