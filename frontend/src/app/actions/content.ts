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
 * Creates a new `fashion_video` node titled with the current date and time.
 * The pose/AI image and generated video media fields are left empty for now —
 * they'll be populated in a later step. Returns the new node's UUID so the
 * caller can navigate to its content page.
 */
export async function createFashionVideo(): Promise<CreateFashionVideoResult> {
  const title = new Date().toLocaleString("en-US", {
    dateStyle: "long",
    timeStyle: "short",
  });

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
  return { ok: true, id: data.id };
}
