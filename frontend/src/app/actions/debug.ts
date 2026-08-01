"use server";

// DEBUG-ONLY server actions backing /debug/voice. They let the voice-FX lab
// load samples already stored on the Drupal side so you can tune the chain on
// real captures without re-recording.
// TODO(cleanup): remove this file (and app/debug/) before shipping.

import { drupalFetch } from "@/lib/drupal";

export interface VoiceSample {
  /** Node UUID. */
  id: string;
  /** Node title (the capture timestamp). */
  title: string;
}

interface VoiceListBody {
  data?: {
    id: string;
    attributes?: { title?: string };
    relationships?: { field_voice?: { data?: unknown } };
  }[];
}

/**
 * Lists fashion_video nodes that have a stored voice recording, newest first.
 * Node-access grants scope the result (own videos; everyone's for admins).
 */
export async function listFashionVoiceSamples(): Promise<VoiceSample[]> {
  try {
    const res = await drupalFetch(
      "/jsonapi/node/fashion_video?include=field_voice&fields[node--fashion_video]=title,field_voice&sort=-created&page[limit]=100",
      { cache: "no-store" }
    );
    if (!res.ok) return [];
    const body = (await res.json()) as VoiceListBody;
    return (body.data ?? [])
      .filter((n) => n.relationships?.field_voice?.data)
      .map((n) => ({ id: n.id, title: n.attributes?.title ?? n.id }));
  } catch {
    return [];
  }
}

/**
 * Fetches a node's stored voice recording and returns it as a base64 data URL.
 * The fetch happens server-side (the file lives behind a short-lived presigned
 * S3 URL), so the browser gets usable bytes without any S3 CORS setup.
 *
 * NOTE: for captures made after the polish chain landed, this file is already
 * processed — re-processing it in the lab will compound the effect.
 */
export async function getFashionVoiceSample(id: string): Promise<string | null> {
  try {
    const res = await drupalFetch(`/fashion-video/${id}/media`, {
      cache: "no-store",
    });
    if (!res.ok) return null;
    const { voice } = (await res.json()) as { voice?: string | null };
    if (!voice) return null;

    const fileRes = await fetch(voice, { cache: "no-store" });
    if (!fileRes.ok) return null;
    const contentType =
      fileRes.headers.get("content-type") || "application/octet-stream";
    const buf = Buffer.from(await fileRes.arrayBuffer());
    return `data:${contentType};base64,${buf.toString("base64")}`;
  } catch {
    return null;
  }
}
