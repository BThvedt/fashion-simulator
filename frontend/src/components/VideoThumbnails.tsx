import Link from "next/link";

export interface VideoThumbnail {
  id: string;
  title: string;
  thumbnailUrl: string;
}

/** Generic placeholder shown until real thumbnails are generated. */
function ThumbnailPlaceholder() {
  return (
    <div className="flex h-full w-full items-center justify-center bg-muted text-muted-foreground">
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={1.5}
        className="h-10 w-10"
        aria-hidden="true"
      >
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <circle cx="8.5" cy="8.5" r="1.5" />
        <path d="m21 15-5-5L5 21" />
      </svg>
    </div>
  );
}

export default function VideoThumbnails({
  videos,
}: {
  videos: VideoThumbnail[];
}) {
  if (videos.length === 0) {
    return (
      <div className="flex items-center justify-center rounded-2xl border border-dashed border-border bg-card/50 px-6 py-16 text-center">
        <p className="text-muted-foreground">No fashion videos yet.</p>
      </div>
    );
  }

  return (
    <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      {videos.map((video) => (
        <li key={video.id}>
          <Link
            href={`/videos/${video.id}`}
            className="group block overflow-hidden rounded-xl border border-border bg-card transition-colors hover:border-primary"
          >
            <div className="aspect-video overflow-hidden bg-muted">
              {video.thumbnailUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={video.thumbnailUrl}
                  alt={video.title}
                  className="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
              ) : (
                <ThumbnailPlaceholder />
              )}
            </div>
            <p className="truncate px-3 py-2 text-sm font-medium text-card-foreground">
              {video.title}
            </p>
          </Link>
        </li>
      ))}
    </ul>
  );
}
