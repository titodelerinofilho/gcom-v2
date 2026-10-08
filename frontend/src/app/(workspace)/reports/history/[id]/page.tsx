import { SavedReportPage } from "@/components/report-history";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <SavedReportPage id={id} />;
}
