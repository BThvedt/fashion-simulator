import { notFound, redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import { drupalFetch, type JsonApiSingle } from "@/lib/drupal";

export const metadata = { title: "Fashion Video" };

interface FashionVideoAttributes {
  title: string;
  created: string;
}

export default async function ContentPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  if (!(await getToken())) {
    redirect("/login");
  }

  const { id } = await params;

  const res = await drupalFetch(`/jsonapi/node/fashion_video/${id}`, {
    cache: "no-store",
  });
  if (!res.ok) {
    notFound();
  }

  const { data } = (await res.json()) as JsonApiSingle<FashionVideoAttributes>;
  const title = data.attributes.title;

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <p className="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
          Fashion Video
        </p>
        <h1 className="text-3xl font-semibold tracking-tight text-foreground">
          {title}
        </h1>
      </main>
    </div>
  );
}
