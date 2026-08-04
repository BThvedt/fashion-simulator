"use client";

import { useState } from "react";
import {
  generateFashionMotion,
  resetFashionImages,
  resetFashionVideo,
} from "@/app/actions/content";

/**
 * Small admin/debug panel at the bottom of a fashion video page. Lets an admin
 * wipe the generated stills or the lip-sync clip and regenerate them: after the
 * reset succeeds the page is reloaded, at which point the runway/film components
 * see no assets and re-trigger generation on their own.
 *
 * Only rendered when the backend reports the current user may regenerate
 * (admins), since regenerating the video (and the fal motion clip) spends real
 * provider credits.
 */
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

export default function RegenerateControls({
  id,
  motionClips = [],
}: {
  id: string;
  motionClips?: string[];
}) {
  const [busy, setBusy] = useState<null | "images" | "video" | "motion">(null);
  const [motionMsg, setMotionMsg] = useState<string | null>(null);

  const run = async (which: "images" | "video") => {
    if (busy) return;
    setBusy(which);
    const ok =
      which === "images"
        ? await resetFashionImages(id)
        : await resetFashionVideo(id);
    if (ok) {
      // Reload so the server re-fetches media (now empty) and the runway/film
      // components remount and restart generation from scratch.
      window.location.reload();
    } else {
      setBusy(null);
    }
  };

  // fal motion generation is a poll-driven state machine: fire repeatedly until
  // it reaches a terminal status. Kling can take a couple of minutes, so we
  // poll every 5s for up to ~4 minutes.
  const runMotion = async () => {
    if (busy) return;
    setBusy("motion");
    setMotionMsg("Submitting to fal\u2026");
    for (let i = 0; i < 48; i++) {
      const status = await generateFashionMotion(id);
      if (status === "done") {
        window.location.reload();
        return;
      }
      if (status === "failed" || status === "error") {
        setMotionMsg("Motion generation failed. See logs.");
        setBusy(null);
        return;
      }
      if (status === "not_configured") {
        setMotionMsg("fal.ai key not configured.");
        setBusy(null);
        return;
      }
      if (status === "images_pending") {
        setMotionMsg("No runway stills yet \u2014 generate stills first.");
        setBusy(null);
        return;
      }
      setMotionMsg("Generating motion clip\u2026 (this can take a couple minutes)");
      await sleep(5000);
    }
    setMotionMsg("Still working \u2014 reload later to see the clip.");
    setBusy(null);
  };

  return (
    <section className="mt-16 border-t border-border pt-6">
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        Debug tools
      </p>
      <div className="mt-3 flex flex-wrap gap-3">
        <button
          type="button"
          onClick={() => run("images")}
          disabled={busy !== null}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted disabled:opacity-50"
        >
          {busy === "images" ? "Regenerating\u2026" : "Regenerate stills"}
        </button>
        <button
          type="button"
          onClick={() => run("video")}
          disabled={busy !== null}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted disabled:opacity-50"
        >
          {busy === "video" ? "Regenerating\u2026" : "Regenerate lip sync"}
        </button>
        <button
          type="button"
          onClick={runMotion}
          disabled={busy !== null}
          className="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted disabled:opacity-50"
        >
          {busy === "motion" ? "Generating\u2026" : "Generate motion clip (fal)"}
        </button>
      </div>
      {motionMsg && (
        <p className="mt-2 text-xs text-muted-foreground">{motionMsg}</p>
      )}
      <p className="mt-2 text-xs text-muted-foreground">
        Regenerating the lip sync spends a lip-sync credit; the motion clip
        spends a fal.ai credit.
      </p>

      {motionClips.length > 0 && (
        <div className="mt-6">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Motion clips (fal)
          </p>
          <div className="mt-3 flex flex-wrap gap-4">
            {motionClips.map((src) => (
              <video
                key={src}
                src={src}
                controls
                playsInline
                loop
                muted
                className="aspect-[9/16] w-40 rounded-lg border border-border bg-black object-cover"
              />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}
