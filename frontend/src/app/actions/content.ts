"use server";

import { drupalFetch } from "@/lib/drupal";

export type CreateFashionVideoResult =
  | { ok: true; id: string }
  | { ok: false; error: string };

export interface CreateFashionVideoInput {
  /** Captured pose photos as base64 data URLs. */
  images?: string[];
  /** The recorded voice line as a base64 audio data URL. */
  voice?: string | null;
  /** Filename of the background song used during capture, e.g. "3.mp3". */
  song?: string | null;
}

interface JsonApiErrorBody {
  errors?: { detail?: string; title?: string }[];
}

async function firstError(res: Response, fallback: string): Promise<string> {
  try {
    const body = (await res.json()) as JsonApiErrorBody;
    return body.errors?.[0]?.detail ?? body.errors?.[0]?.title ?? fallback;
  } catch {
    return fallback;
  }
}

/**
 * Creates a new `fashion_video` node titled with the current date and time,
 * then uploads the captured pose images to it. The images are stored as
 * private, S3-backed media under a per-user / per-video folder by the Drupal
 * `fashion_video` module. Returns the new node's UUID so the caller can
 * navigate to its content page.
 *
 * Image upload failures are non-fatal: the node still exists, so we proceed to
 * its page rather than losing the whole submission.
 */
export async function createFashionVideo(
  input: CreateFashionVideoInput = {}
): Promise<CreateFashionVideoResult> {
  const { images = [], voice = null, song = null } = input;

  // Title is a sortable date + timestamp, e.g. "2026-07-20 16:19:32". It also
  // becomes the per-video subfolder name in S3 (sanitized by the backend).
  const now = new Date();
  const p = (n: number) => String(n).padStart(2, "0");
  const title =
    `${now.getFullYear()}-${p(now.getMonth() + 1)}-${p(now.getDate())} ` +
    `${p(now.getHours())}:${p(now.getMinutes())}:${p(now.getSeconds())}`;

  const res = await drupalFetch("/jsonapi/node/fashion_video", {
    method: "POST",
    headers: { "Content-Type": "application/vnd.api+json" },
    body: JSON.stringify({
      data: {
        type: "node--fashion_video",
        attributes: { title },
      },
    }),
  });

  if (!res.ok) {
    return {
      ok: false,
      error: await firstError(res, "Could not create your fashion video."),
    };
  }

  const { data } = (await res.json()) as { data: { id: string } };

  if (images.length || voice || song) {
    await uploadCaptureAssets(data.id, images, voice, song);
  }

  return { ok: true, id: data.id };
}

/**
 * Kicks off AI runway-image generation for a node. Fire-and-forget: the backend
 * keeps working after the client aborts (it sets ignore_user_abort), so we use a
 * short timeout and swallow the resulting abort/error. Poll `getFashionVideoMedia`
 * for the results.
 */
export async function ensureFashionImages(id: string): Promise<void> {
  try {
    await drupalFetch(`/fashion-video/${id}/generate-images`, {
      method: "POST",
      signal: AbortSignal.timeout(4000),
    });
  } catch {
    // Expected: request is intentionally abandoned; generation continues server-side.
  }
}

/**
 * Kicks off talking-head video generation for a node. Fire-and-forget, same as
 * `ensureFashionImages`: the backend keeps working after the client aborts, so
 * we use a short timeout and swallow the abort. Poll `getFashionVideoMedia` for
 * the resulting video.
 */
export async function ensureFashionVideo(id: string): Promise<void> {
  try {
    await drupalFetch(`/fashion-video/${id}/generate-video`, {
      method: "POST",
      signal: AbortSignal.timeout(4000),
    });
  } catch {
    // Expected: request is intentionally abandoned; generation continues server-side.
  }
}

/**
 * Debug/admin tool: deletes the generated AI images for a node so they can be
 * regenerated. After this resolves, reload the page — the runway component sees
 * no images and re-triggers generation. Returns whether the reset succeeded.
 */
export async function resetFashionImages(id: string): Promise<boolean> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/reset-images`, {
      method: "POST",
    });
    return res.ok;
  } catch {
    return false;
  }
}

/**
 * Debug/admin tool: deletes the generated lip-sync video (and clips) and clears
 * the remembered D-ID talk so a fresh one is produced. Regenerating spends a
 * D-ID credit. Returns whether the reset succeeded.
 */
export async function resetFashionVideo(id: string): Promise<boolean> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/reset-video`, {
      method: "POST",
    });
    return res.ok;
  } catch {
    return false;
  }
}

/**
 * Reads the current pose + AI image URLs (presigned) plus the generated video
 * URL for a node. Used to poll while runway images / the video are generated.
 */
export async function getFashionVideoMedia(
  id: string
): Promise<{ poses: string[]; aiImages: string[]; video: string | null } | null> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/media`, {
      cache: "no-store",
    });
    if (!res.ok) return null;
    const data = (await res.json()) as {
      poses?: string[];
      aiImages?: string[];
      video?: string | null;
    };
    return {
      poses: data.poses ?? [],
      aiImages: data.aiImages ?? [],
      video: data.video ?? null,
    };
  } catch {
    return null;
  }
}

/**
 * Sends the captured assets (pose images, voice recording, and the background
 * song filename) to the custom Drupal endpoint that stores them on the node.
 * Swallows errors (best-effort) so a storage hiccup doesn't strand the user on
 * the capture screen.
 */
async function uploadCaptureAssets(
  id: string,
  images: string[],
  voice: string | null,
  song: string | null
): Promise<void> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/pose-images`, {
      method: "POST",
      body: JSON.stringify({ images, voice, song }),
    });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      console.error(
        `[fashion_video] capture-asset upload failed: ${res.status} ${res.statusText} — ${body.slice(0, 500)}`
      );
    }
  } catch (err) {
    // Non-fatal — the node was created successfully.
    console.error("[fashion_video] capture-asset upload request error:", err);
  }
}
