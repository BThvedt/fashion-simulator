import { notFound, redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import { drupalFetch } from "@/lib/drupal";
import AiRunway from "@/components/AiRunway";

export const metadata = { title: "Fashion Video" };

interface StyleAnalysis {
  aesthetic: string;
  era: string;
  description: string;
  accessory: string;
  props: string[];
}

interface FashionVideoMedia {
  title: string;
  poses: string[];
  aiImages: string[];
  analysis: StyleAnalysis | null;
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

  const data = (await res.json()) as Partial<FashionVideoMedia>;
  const title = data.title ?? "";
  const poses = data.poses ?? [];
  const aiImages = data.aiImages ?? [];
  const analysis = data.analysis ?? null;
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

        {analysis && (
          <section className="mt-10 rounded-2xl border border-border bg-card p-6">
            <p className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
              We think you&apos;re giving&hellip;
            </p>
            <h2 className="mt-1 text-2xl font-semibold tracking-tight text-card-foreground">
              {analysis.aesthetic}
            </h2>
            {analysis.era && (
              <p className="mt-1 text-sm text-muted-foreground">{analysis.era}</p>
            )}
            {analysis.description && (
              <p className="mt-4 text-card-foreground">{analysis.description}</p>
            )}

            <dl className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
              {analysis.accessory && (
                <div>
                  <dt className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                    Suggested accessory
                  </dt>
                  <dd className="mt-1 text-card-foreground">
                    {analysis.accessory}
                  </dd>
                </div>
              )}
              {analysis.props.length > 0 && (
                <div>
                  <dt className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                    Props for the vibe
                  </dt>
                  <dd className="mt-1">
                    <ul className="flex flex-wrap gap-2">
                      {analysis.props.map((prop) => (
                        <li
                          key={prop}
                          className="rounded-full bg-muted px-3 py-1 text-sm text-card-foreground"
                        >
                          {prop}
                        </li>
                      ))}
                    </ul>
                  </dd>
                </div>
              )}
            </dl>
          </section>
        )}

        {poses.length > 0 && (
          <AiRunway
            id={id}
            initialImages={aiImages}
            expected={Math.min(poses.length, 3)}
          />
        )}
      </main>
    </div>
  );
}
