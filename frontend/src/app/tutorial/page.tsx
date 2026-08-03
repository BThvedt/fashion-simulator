import { redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import TutorialCarousel from "@/components/TutorialCarousel";

export const metadata = { title: "Tutorial" };

export default async function TutorialPage() {
  if (!(await getToken())) {
    redirect("/login");
  }

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-semibold tracking-tight text-foreground">
            How to make a fashion video
          </h1>
          <p className="mt-2 text-muted-foreground">
            Seven quick steps from webcam to catwalk.
          </p>
        </div>
        <TutorialCarousel />
      </main>
    </div>
  );
}
