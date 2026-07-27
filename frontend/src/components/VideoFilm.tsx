"use client";

import { useEffect, useRef, useState } from "react";
import {
  ensureFashionVideo,
  getFashionVideoMedia,
} from "@/app/actions/content";

const POLL_INTERVAL_MS = 5000;
// D-ID queues jobs (several minutes on trial plans) on top of the runway
// images having to finish first, so give it a generous budget (~12 min).
const MAX_ATTEMPTS = 150;

/**
 * Shows the AI-generated "talking head" video for a fashion video. If none
 * exists yet, it triggers generation on the backend and polls until it appears.
 *
 * Generation depends on the runway images existing first, so the trigger is
 * re-fired on each poll: the backend endpoint is idempotent and locked, so a
 * repeat call is a cheap no-op ("exists"/"in_progress"/"images_pending") until
 * the images are ready and the render can actually start.
 */
export default function VideoFilm({
  id,
  initialVideo,
}: {
  id: string;
  initialVideo: string | null;
}) {
  const [video, setVideo] = useState<string | null>(initialVideo ?? null);
  const [failed, setFailed] = useState(false);
  const started = useRef(false);

  useEffect(() => {
    if (video || started.current) return;
    started.current = true;

    let cancelled = false;
    let attempts = 0;

    const tick = async () => {
      if (cancelled) return;
      attempts += 1;

      // Idempotent nudge — starts the render once images exist, otherwise a
      // cheap no-op thanks to the backend lock/guards.
      await ensureFashionVideo(id);
      if (cancelled) return;

      const media = await getFashionVideoMedia(id);
      if (cancelled) return;

      if (media?.video) {
        setVideo(media.video);
        return;
      }
      if (attempts >= MAX_ATTEMPTS) {
        setFailed(true);
        return;
      }
      window.setTimeout(tick, POLL_INTERVAL_MS);
    };

    void tick();
    return () => {
      cancelled = true;
    };
  }, [id, video]);

  if (video) {
    return (
      <div className="mt-8">
        <video
          src={video}
          controls
          playsInline
          className="mx-auto aspect-[9/16] w-full max-w-xs rounded-2xl border border-border bg-black object-cover"
        />
      </div>
    );
  }

  return (
    <div className="mt-8">
      <div className="mx-auto flex aspect-[9/16] w-full max-w-xs items-center justify-center rounded-2xl border border-border bg-muted text-center">
        <div className="px-6">
          {failed ? (
            <p className="text-sm text-muted-foreground">
              We couldn&apos;t produce your video this time. Try refreshing in a
              bit.
            </p>
          ) : (
            <>
              <div
                className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
                aria-label="Producing"
              />
              <p className="mt-3 text-sm text-muted-foreground">
                Producing your film&hellip; this takes a few minutes.
              </p>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
