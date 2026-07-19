export interface VideoThumbnail {
  id: string;
  title: string;
  thumbnailUrl: string;
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
          <div className="group overflow-hidden rounded-xl border border-border bg-card">
            <div className="aspect-video overflow-hidden bg-muted">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={video.thumbnailUrl}
                alt={video.title}
                className="h-full w-full object-cover transition-transform group-hover:scale-105"
              />
            </div>
            <p className="truncate px-3 py-2 text-sm font-medium text-card-foreground">
              {video.title}
            </p>
          </div>
        </li>
      ))}
    </ul>
  );
}
