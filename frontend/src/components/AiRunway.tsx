"use client";

import { useEffect, useRef, useState } from "react";
import {
  ensureFashionImages,
  getFashionVideoMedia,
} from "@/app/actions/content";

const POLL_INTERVAL_MS = 5000;
const MAX_ATTEMPTS = 48; // ~4 minutes

/**
 * Shows the AI-generated runway images for a fashion video. If none exist yet,
 * it triggers generation on the backend and polls until they appear, showing
 * placeholders in the meantime.
 */
export default function AiRunway({
  id,
  initialImages,
  expected,
}: {
  id: string;
  initialImages: string[];
  expected: number;
}) {
  const [images, setImages] = useState<string[]>(initialImages ?? []);
  const [failed, setFailed] = useState(false);
  const started = useRef(false);

  useEffect(() => {
    if (images.length > 0 || started.current) return;
    started.current = true;

    let cancelled = false;
    let attempts = 0;

    void ensureFashionImages(id);

    const poll = async () => {
      if (cancelled) return;
      attempts += 1;
      const media = await getFashionVideoMedia(id);
      if (cancelled) return;

      if (media && media.aiImages.length > 0) {
        setImages(media.aiImages);
        return;
      }
      if (attempts >= MAX_ATTEMPTS) {
        setFailed(true);
        return;
      }
      window.setTimeout(poll, POLL_INTERVAL_MS);
    };

    window.setTimeout(poll, POLL_INTERVAL_MS);
    return () => {
      cancelled = true;
    };
  }, [id, images.length]);

  const pending = images.length === 0 && !failed;
  const slots = Math.max(expected, 1);

  return (
    <section className="mt-10">
      <h2 className="text-2xl font-semibold tracking-tight text-foreground">
        Your runway looks
      </h2>
      {pending && (
        <p className="mt-1 text-sm text-muted-foreground">
          Striking a pose on the AI catwalk&hellip; this takes a minute.
        </p>
      )}
      {failed && (
        <p className="mt-1 text-sm text-muted-foreground">
          We couldn&apos;t generate your runway looks this time. Try refreshing in
          a bit.
        </p>
      )}

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {images.length > 0
          ? images.map((src, i) => (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                key={src}
                src={src}
                alt={`Runway look ${i + 1}`}
                className="w-full rounded-lg border border-border object-cover"
              />
            ))
          : Array.from({ length: slots }).map((_, i) => (
              <div
                key={i}
                className="flex aspect-[2/3] w-full items-center justify-center rounded-lg border border-border bg-muted"
              >
                {pending && (
                  <div
                    className="h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
                    aria-label="Generating"
                  />
                )}
              </div>
            ))}
      </div>
    </section>
  );
}
