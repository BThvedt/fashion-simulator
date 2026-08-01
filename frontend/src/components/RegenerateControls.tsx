"use client";

import { useState } from "react";
import {
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
 * (admins), since regenerating the video spends a real D-ID credit.
 */
export default function RegenerateControls({ id }: { id: string }) {
  const [busy, setBusy] = useState<null | "images" | "video">(null);

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
      </div>
      <p className="mt-2 text-xs text-muted-foreground">
        Regenerating the lip sync spends a D-ID credit.
      </p>
    </section>
  );
}
