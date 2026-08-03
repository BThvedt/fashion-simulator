"use client";

import { useEffect, useState } from "react";
import {
  shareFashionVideo,
  unshareFashionVideo,
} from "@/app/actions/content";

type ModalKind = "shared" | "confirm-private" | null;

/**
 * Top-right share control for a video page. The button toggles public sharing:
 *
 * - While private, clicking it shares the video (minting a hard-to-guess token)
 *   and pops a modal explaining the page is now public at that URL.
 * - While shared, clicking it pops a confirmation modal; confirming revokes the
 *   share so the public link goes dead.
 *
 * A link icon to the right opens the public page in a new tab; it's disabled
 * whenever the video isn't shared.
 */
export default function ShareControls({
  id,
  initialShared,
  initialToken,
}: {
  id: string;
  initialShared: boolean;
  initialToken: string | null;
}) {
  const [shared, setShared] = useState(initialShared);
  const [token, setToken] = useState<string | null>(initialToken);
  const [modal, setModal] = useState<ModalKind>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  // Absolute URL shown in the "now public" modal; filled in the click handler
  // (where `window` is available) to avoid SSR/hydration mismatches.
  const [shareUrl, setShareUrl] = useState("");

  useEffect(() => {
    if (modal === null) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") closeModal();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [modal]);

  // Relative path is enough for the link icon (the browser resolves it); the
  // modal shows the absolute `shareUrl` for copying.
  const sharePath = token ? `/share/${token}` : "";

  function closeModal() {
    setModal(null);
    setError(null);
    setCopied(false);
  }

  async function onShareClick() {
    if (busy) return;
    if (shared) {
      setModal("confirm-private");
      return;
    }
    setBusy(true);
    setError(null);
    const result = await shareFashionVideo(id);
    setBusy(false);
    if (result.ok) {
      setShared(true);
      setToken(result.token);
      setShareUrl(`${window.location.origin}/share/${result.token}`);
      setModal("shared");
    } else {
      setError(result.error);
      setModal("shared");
    }
  }

  async function confirmPrivate() {
    if (busy) return;
    setBusy(true);
    setError(null);
    const result = await unshareFashionVideo(id);
    setBusy(false);
    if (result.ok) {
      setShared(false);
      setToken(null);
      closeModal();
    } else {
      setError(result.error);
    }
  }

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1800);
    } catch {
      setCopied(false);
    }
  }

  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        onClick={onShareClick}
        disabled={busy}
        aria-label={shared ? "Sharing settings" : "Share this video"}
        className={
          shared
            ? "inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
            : "inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-4 py-1.5 text-sm font-medium text-foreground transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
        }
      >
        <ShareIcon />
        {busy && !shared ? "Sharing…" : shared ? "Shared" : "Share"}
      </button>

      {shared && sharePath ? (
        <a
          href={sharePath}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Open the public page"
          title="Open the public page"
          className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card text-foreground transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <LinkIcon />
        </a>
      ) : (
        <span
          aria-label="Public link (video is private)"
          aria-disabled="true"
          title="Share the video to enable this link"
          className="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-full border border-border bg-muted text-muted-foreground/40"
        >
          <LinkIcon />
        </span>
      )}

      {modal !== null && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
          role="dialog"
          aria-modal="true"
          onClick={closeModal}
        >
          <div
            className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            {modal === "shared" ? (
              <>
                <h2 className="text-lg font-semibold text-card-foreground">
                  {error ? "Couldn’t share" : "Your video is public"}
                </h2>
                {error ? (
                  <p className="mt-2 text-sm text-destructive">{error}</p>
                ) : (
                  <>
                    <p className="mt-2 text-sm text-muted-foreground">
                      Anyone with the link below can view this page — it lives at
                      a hard-to-guess URL and isn’t listed anywhere. Make it
                      private again anytime and the link will stop working.
                    </p>
                    <div className="mt-4 flex items-center gap-2">
                      <input
                        readOnly
                        value={shareUrl}
                        onFocus={(e) => e.currentTarget.select()}
                        className="min-w-0 flex-1 truncate rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground outline-none"
                      />
                      <button
                        type="button"
                        onClick={copyLink}
                        className="shrink-0 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                      >
                        {copied ? "Copied!" : "Copy"}
                      </button>
                    </div>
                  </>
                )}
                <div className="mt-6 flex justify-end">
                  <button
                    type="button"
                    onClick={closeModal}
                    className="rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted"
                  >
                    Done
                  </button>
                </div>
              </>
            ) : (
              <>
                <h2 className="text-lg font-semibold text-card-foreground">
                  Make this private again?
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                  The public link will stop working immediately and anyone who
                  has it will no longer be able to view this page.
                </p>
                {error && (
                  <p className="mt-2 text-sm text-destructive">{error}</p>
                )}
                <div className="mt-6 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={closeModal}
                    disabled={busy}
                    className="rounded-md border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted disabled:opacity-60"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={confirmPrivate}
                    disabled={busy}
                    className="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60"
                  >
                    {busy ? "Working…" : "Make private"}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function ShareIcon() {
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
      <circle cx="18" cy="5" r="3" />
      <circle cx="6" cy="12" r="3" />
      <circle cx="18" cy="19" r="3" />
      <path d="m8.6 13.5 6.8 4M15.4 6.5 8.6 10.5" />
    </svg>
  );
}

function LinkIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
      <path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
    </svg>
  );
}
