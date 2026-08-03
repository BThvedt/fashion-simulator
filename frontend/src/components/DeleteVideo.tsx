"use client";

import { useEffect, useState } from "react";
import { deleteFashionVideo } from "@/app/actions/content";

/**
 * A destructive "Delete video" action for the bottom of a video page. Opens a
 * confirmation modal; on confirm it deletes the node (the backend cleans up the
 * associated S3 assets) and returns the user to their video list.
 */
export default function DeleteVideo({ id }: { id: string }) {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape" && !busy) close();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, busy]);

  function close() {
    setOpen(false);
    setError(null);
  }

  async function confirmDelete() {
    if (busy) return;
    setBusy(true);
    setError(null);
    const result = await deleteFashionVideo(id);
    if (result.ok) {
      // Hard-navigate to the landing page so the (now-deleted) node page is
      // fully left behind and the list loads fresh.
      window.location.assign("/");
    } else {
      setBusy(false);
      setError(result.error);
    }
  }

  return (
    <div className="mt-12 flex flex-col items-center border-t border-border pt-8">
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-destructive transition hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive"
      >
        <TrashIcon />
        Delete video
      </button>
      <p className="mt-1 text-xs text-muted-foreground">
        This deletes all your stills on this video as well.
      </p>

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          role="dialog"
          aria-modal="true"
          onClick={() => !busy && close()}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            <h2 className="text-lg font-semibold text-card-foreground">
              Delete this video?
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
              This permanently removes the video, its photos and generated
              images. This can’t be undone.
            </p>
            {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={close}
                disabled={busy}
                className="rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted disabled:opacity-60"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={confirmDelete}
                disabled={busy}
                className="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60"
              >
                {busy ? "Deleting…" : "Delete"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function TrashIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="15"
      height="15"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M3 6h18" />
      <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
      <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
      <path d="M10 11v6M14 11v6" />
    </svg>
  );
}
