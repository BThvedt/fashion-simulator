"use client";

import { useEffect, useRef, useState } from "react";
import {
  ensureFashionVideo,
  getFashionVideoMedia,
  getVideoQueueStatus,
  type VideoQueueStatus,
} from "@/app/actions/content";
// Reuse the capture studio's "showtime" overlay styles (sweeping spotlights +
// premiere searchlights) so playback matches the webcam capture look exactly.
import studio from "./CreateStudio.module.css";

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
 *
 * While the finished video is actually playing, the page dims into "showtime":
 * dark mode is forced and the same sweeping spotlights / premiere searchlights
 * from the capture flow sweep over it. Pausing or ending restores the page.
 */
export default function VideoFilm({
  id,
  initialVideo,
  readOnly = false,
}: {
  id: string;
  initialVideo: string | null;
  /** Public/shared view: never triggers or polls generation (auth-only). */
  readOnly?: boolean;
}) {
  const [video, setVideo] = useState<string | null>(initialVideo ?? null);
  const [failed, setFailed] = useState(false);
  const [playing, setPlaying] = useState(false);
  const [queue, setQueue] = useState<VideoQueueStatus | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (readOnly || video || started.current) return;
    started.current = true;

    let cancelled = false;
    let attempts = 0;

    const tick = async () => {
      if (cancelled) return;
      attempts += 1;

      // Idempotent nudge — starts the render once images exist and a queue slot
      // is free, otherwise a cheap no-op thanks to the backend lock/queue.
      await ensureFashionVideo(id);
      if (cancelled) return;

      const media = await getFashionVideoMedia(id);
      if (cancelled) return;

      if (media?.video) {
        setVideo(media.video);
        return;
      }

      // Surface the queue standing ("generating now" vs "number X in line").
      const q = await getVideoQueueStatus(id);
      if (cancelled) return;
      if (q) setQueue(q);

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
  }, [id, video, readOnly]);

  // Force dark mode during playback and restore the user's real preference when
  // it stops / unmounts. We never touch localStorage, so the persisted choice
  // is untouched — mirrors CreateStudio's enter/restore theme logic.
  useEffect(() => {
    if (!playing) return;
    const rootEl = document.documentElement;
    const prevDark = rootEl.classList.contains("dark");
    rootEl.classList.add("dark");
    return () => {
      rootEl.classList.toggle("dark", prevDark);
    };
  }, [playing]);

  if (video) {
    const show = playing ? "true" : "false";
    return (
      <div className="mt-8">
        {/* Full-viewport premiere searchlights, only while the film plays. */}
        <div className={studio.skylights} data-show={show} aria-hidden="true">
          <span className={`${studio.beam} ${studio.beamL}`} />
          <span className={`${studio.beam} ${studio.beamC}`} />
          <span className={`${studio.beam} ${studio.beamR}`} />
        </div>

        <div className="relative mx-auto w-full max-w-xs overflow-hidden rounded-2xl">
          <video
            src={video}
            controls
            playsInline
            onPlay={() => setPlaying(true)}
            onPause={() => setPlaying(false)}
            onEnded={() => setPlaying(false)}
            className="block aspect-[9/16] w-full rounded-2xl border border-border bg-black object-cover"
          />

          {/* Sweeping colored spotlights over the video (screen-blended). */}
          <div
            className={studio.spotlights}
            data-show={show}
            aria-hidden="true"
          >
            <div className={`${studio.spot} ${studio.spot1}`} />
            <div className={`${studio.spot} ${studio.spot2}`} />
            <div className={`${studio.spot} ${studio.spot3}`} />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="mt-8">
      <div className="mx-auto flex aspect-[9/16] w-full max-w-xs items-center justify-center rounded-2xl border border-border bg-muted text-center">
        <div className="px-6">
          {readOnly ? (
            <p className="text-sm text-muted-foreground">
              This video isn&apos;t ready yet. Check back in a bit.
            </p>
          ) : failed ? (
            <p className="text-sm text-muted-foreground">
              We couldn&apos;t produce your video this time. Try refreshing in a
              bit.
            </p>
          ) : queue?.status === "queued" ? (
            <>
              <div
                className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
                aria-label="Waiting in queue"
              />
              <p className="mt-3 text-sm font-medium text-foreground">
                You&apos;re number {queue.position} in the queue
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                We generate one video at a time — hang tight, yours will start
                soon.
              </p>
            </>
          ) : (
            <>
              <div
                className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
                aria-label="Producing"
              />
              <p className="mt-3 text-sm text-muted-foreground">
                {queue?.status === "generating"
                  ? "Generating your video now… this takes a few minutes."
                  : "Producing your film… this takes a few minutes."}
              </p>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
