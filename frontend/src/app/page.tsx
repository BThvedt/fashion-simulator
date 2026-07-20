import Link from "next/link";
import { getToken } from "@/lib/auth";
import RetroLanding from "@/components/RetroLanding";
import VideoThumbnails, {
  type VideoThumbnail,
} from "@/components/VideoThumbnails";

export default async function Home() {
  const token = await getToken();
  const isLoggedIn = Boolean(token);

  if (!isLoggedIn) {
    return <RetroLanding />;
  }

  // TODO: load the signed-in user's videos from JSON:API via drupalFetch.
  const videos: VideoThumbnail[] = [];

  return (
    <div className="flex flex-1 flex-col bg-background font-sans">
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
        <h1
          id="videos-heading"
          className="mb-8 text-center text-2xl font-semibold tracking-tight text-foreground"
        >
          Ready to be Fabulous?
        </h1>

        <div className="mb-10 flex flex-col items-center gap-3">
          <Link
            href="/create"
            className="flex h-14 items-center justify-center rounded-full bg-primary px-10 text-xl font-bold text-primary-foreground transition-colors hover:bg-primary/90"
          >
            Create Video!
          </Link>
          <div className="flex items-center gap-4">
            <Link
              href="/tutorial"
              className="text-lg font-medium text-primary underline underline-offset-4 transition-colors hover:text-primary/80"
            >
              Tutorial
            </Link>
            <Link
              href="/privacy"
              className="text-lg font-medium text-primary underline underline-offset-4 transition-colors hover:text-primary/80"
            >
              Privacy Policy
            </Link>
          </div>
        </div>

        <section aria-labelledby="videos-heading">
          <VideoThumbnails videos={videos} />
        </section>
      </main>
    </div>
  );
}
