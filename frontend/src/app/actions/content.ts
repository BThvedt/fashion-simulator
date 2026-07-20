"use server";

import { drupalFetch } from "@/lib/drupal";

export type CreateFashionVideoResult =
  | { ok: true; id: string }
  | { ok: false; error: string };

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
  images: string[] = []
): Promise<CreateFashionVideoResult> {
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

  if (images.length) {
    await uploadPoseImages(data.id, images);
  }

  return { ok: true, id: data.id };
}

/**
 * Sends captured pose images to the custom Drupal endpoint that stores them as
 * private media on the node. Swallows errors (best-effort) so a storage hiccup
 * doesn't strand the user on the capture screen.
 */
async function uploadPoseImages(id: string, images: string[]): Promise<void> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/pose-images`, {
      method: "POST",
      body: JSON.stringify({ images }),
    });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      console.error(
        `[fashion_video] pose-image upload failed: ${res.status} ${res.statusText} — ${body.slice(0, 500)}`
      );
    }
  } catch (err) {
    // Non-fatal — the node was created successfully.
    console.error("[fashion_video] pose-image upload request error:", err);
  }
}
