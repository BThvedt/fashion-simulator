import { notFound, redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import { drupalFetch } from "@/lib/drupal";

export const metadata = { title: "Fashion Video" };

interface FashionVideoMedia {
  title: string;
  poses: string[];
}

export default async function VideoPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  if (!(await getToken())) {
    redirect("/login");
  }

  const { id } = await params;

  // Custom endpoint returns the title plus short-lived presigned S3 URLs for
  // the pose images (private s3fs files aren't presigned on their own).
  const res = await drupalFetch(`/fashion-video/${id}/media`, {
    cache: "no-store",
  });
  if (!res.ok) {
    notFound();
  }

  const { title, poses } = (await res.json()) as FashionVideoMedia;
  // Title is stored with seconds (e.g. "2026-07-20 16:19:32"); display to the
  // minute.
  const display = (title ?? "").replace(/:\d{2}$/, "");

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <p className="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
          Fashion Video
        </p>
        <h1 className="text-3xl font-semibold tracking-tight text-foreground">
          {display}
        </h1>

        {poses.length > 0 && (
          <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {poses.map((src, i) => (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                key={src}
                src={src}
                alt={`Pose ${i + 1}`}
                className="w-full rounded-lg border border-border object-cover"
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
