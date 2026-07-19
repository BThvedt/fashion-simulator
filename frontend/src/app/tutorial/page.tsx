import { redirect } from "next/navigation";
import { getToken } from "@/lib/auth";

export const metadata = { title: "Tutorial" };

export default async function TutorialPage() {
  if (!(await getToken())) {
    redirect("/login");
  }

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Tutorial
        </h1>
        <p className="mt-2 text-muted-foreground">Coming soon.</p>
      </main>
    </div>
  );
}
