import { redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import CreateStudio from "@/components/CreateStudio";

export const metadata = { title: "Create Fashion" };

export default async function CreatePage() {
  if (!(await getToken())) {
    redirect("/login");
  }

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <h1 className="mb-6 text-2xl font-semibold tracking-tight text-foreground">
          Create Fashion
        </h1>
        <CreateStudio />
      </main>
    </div>
  );
}
