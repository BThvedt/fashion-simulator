"use client";

import { useEffect, useRef, useState } from "react";
import { updateFashionVideoTitle } from "@/app/actions/content";

/**
 * The video-page title with an inline rename affordance: a pencil button that
 * fades in on hover/focus and swaps the heading for a text input. Saves on
 * Enter or blur, cancels on Escape. The node is addressed by UUID, so the save
 * only succeeds for the owner/admin (enforced server-side).
 */
export default function EditableTitle({
  id,
  initialTitle,
}: {
  id: string;
  initialTitle: string;
}) {
  const [title, setTitle] = useState(initialTitle);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(initialTitle);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (editing) {
      inputRef.current?.focus();
      inputRef.current?.select();
    }
  }, [editing]);

  function startEdit() {
    setDraft(title);
    setError(null);
    setEditing(true);
  }

  function cancel() {
    setEditing(false);
    setError(null);
  }

  async function save() {
    const next = draft.trim();
    if (!next || next === title) {
      cancel();
      return;
    }
    setSaving(true);
    setError(null);
    const result = await updateFashionVideoTitle(id, next);
    setSaving(false);
    if (result.ok) {
      setTitle(next);
      setEditing(false);
    } else {
      setError(result.error);
    }
  }

  if (editing) {
    return (
      <div>
        <div className="flex items-center gap-2">
          <input
            ref={inputRef}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                void save();
              } else if (e.key === "Escape") {
                e.preventDefault();
                cancel();
              }
            }}
            onBlur={() => void save()}
            disabled={saving}
            aria-label="Video title"
            className="w-full max-w-md rounded-md border border-border bg-background px-3 py-1 text-3xl font-semibold tracking-tight text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring disabled:opacity-60"
          />
          {saving && (
            <span className="text-sm text-muted-foreground">Saving…</span>
          )}
        </div>
        {error && <p className="mt-1 text-sm text-destructive">{error}</p>}
      </div>
    );
  }

  return (
    <div className="group flex items-center gap-2">
      <h1 className="text-3xl font-semibold tracking-tight text-foreground">
        {title}
      </h1>
      <button
        type="button"
        onClick={startEdit}
        aria-label="Rename video"
        title="Rename"
        className="rounded-md p-1.5 text-muted-foreground opacity-0 transition hover:bg-muted hover:text-foreground focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring group-hover:opacity-100"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <path d="M12 20h9" />
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </svg>
      </button>
    </div>
  );
}
