"use client";

import { useEffect, useRef, useState } from "react";
import {
  ensureFashionImages,
  getFashionVideoMedia,
} from "@/app/actions/content";
import DownloadButton from "@/components/DownloadButton";

// Circular download button that fades in over a still on hover/focus.
const OVERLAY_CLS =
  "absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur transition hover:bg-black/80 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white group-hover:opacity-100";

const POLL_INTERVAL_MS = 5000;
const MAX_ATTEMPTS = 48; // ~4 minutes
const BODY_COUNT = 3;

/**
 * Shows the captured pose photos next to their AI-generated counterparts: the
 * three body poses sit above their three runway looks, and the face closeup
 * sits beside its generated portrait. If the generated images don't exist yet,
 * generation is triggered and polled, with spinners in the generated slots
 * meanwhile.
 */
export default function AiRunway({
  id,
  poses,
  initialImages,
  readOnly = false,
}: {
  id: string;
  poses: string[];
  initialImages: string[];
  /** Public/shared view: never triggers or polls generation (auth-only). */
  readOnly?: boolean;
}) {
  const [images, setImages] = useState<string[]>(initialImages ?? []);
  const [failed, setFailed] = useState(false);
  const started = useRef(false);

  useEffect(() => {
    if (readOnly || images.length > 0 || started.current) return;
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
  }, [id, images.length, readOnly]);

  const pending = !readOnly && images.length === 0 && !failed;

  const bodyPoses = poses.slice(0, BODY_COUNT);
  const closeupPose = poses[BODY_COUNT];
  const bodyImages = images.slice(0, BODY_COUNT);
  const faceImage = images[BODY_COUNT];

  // A still image with a download button that appears on hover.
  const still = (
    src: string,
    alt: string,
    aspect: string,
    filename: string
  ) => (
    <div className="group relative">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={src}
        alt={alt}
        className={`w-full rounded-lg border border-border object-cover ${aspect}`}
      />
      <DownloadButton url={src} filename={filename} className={OVERLAY_CLS} />
    </div>
  );

  // A generated image, or a placeholder (spinner while generation is pending).
  const generated = (
    src: string | undefined,
    alt: string,
    aspect: string,
    filename: string
  ) =>
    src ? (
      still(src, alt, aspect, filename)
    ) : (
      <div
        className={`flex w-full items-center justify-center rounded-lg border border-border bg-muted ${aspect}`}
      >
        {pending && (
          <div
            className="h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground"
            aria-label="Generating"
          />
        )}
      </div>
    );

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
          We couldn&apos;t generate your runway looks this time. Try refreshing
          in a bit.
        </p>
      )}

      {/* Each pose paired with its generated runway look (stacked). Stacks to a
          single column on small screens and sits three-up from sm. */}
      {bodyPoses.length > 0 && (
        <div className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3 sm:gap-4">
          {bodyPoses.map((src, i) => (
            <div key={`pair-${i}`} className="flex flex-col gap-4">
              {still(src, `Pose ${i + 1}`, "aspect-video", `pose-${i + 1}.jpg`)}
              {generated(
                bodyImages[i],
                `Runway look ${i + 1}`,
                "aspect-[2/3]",
                `runway-look-${i + 1}.png`
              )}
            </div>
          ))}
        </div>
      )}

      {/* Face closeup beside its generated portrait, 50% each on larger screens. */}
      {closeupPose && (
        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          {still(closeupPose, "Closeup", "aspect-[3/4]", "closeup.jpg")}
          {generated(
            faceImage,
            "Generated closeup",
            "aspect-[3/4]",
            "generated-closeup.png"
          )}
        </div>
      )}
    </section>
  );
}
