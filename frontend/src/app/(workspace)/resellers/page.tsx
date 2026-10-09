import { Suspense } from "react";
import { ResellerProfile } from "@/components/reseller-profile";
import { Loading } from "@/components/ui";
export default function Page() {
  return (
    <Suspense fallback={<Loading />}>
      <ResellerProfile />
    </Suspense>
  );
}
