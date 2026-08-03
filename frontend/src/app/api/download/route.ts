import { NextRequest } from "next/server";

/**
 * Same-origin download proxy. The media lives on presigned S3 URLs (a different
 * origin), where the browser ignores the `download` attribute. Streaming those
 * bytes back through here with a `Content-Disposition: attachment` header makes
 * the browser save the file instead of navigating to it.
 *
 * SSRF guard: only https URLs on AWS S3 hosts are proxied.
 */
export async function GET(req: NextRequest) {
  const url = req.nextUrl.searchParams.get("url");
  const name = req.nextUrl.searchParams.get("name") || "download";
  if (!url) {
    return new Response("Missing url", { status: 400 });
  }

  let target: URL;
  try {
    target = new URL(url);
  } catch {
    return new Response("Bad url", { status: 400 });
  }
  if (target.protocol !== "https:" || !target.hostname.endsWith(".amazonaws.com")) {
    return new Response("Forbidden", { status: 403 });
  }

  const upstream = await fetch(target, { cache: "no-store" });
  if (!upstream.ok || !upstream.body) {
    return new Response("Upstream error", { status: 502 });
  }

  const headers = new Headers();
  headers.set(
    "Content-Type",
    upstream.headers.get("content-type") ?? "application/octet-stream"
  );
  const len = upstream.headers.get("content-length");
  if (len) headers.set("Content-Length", len);
  const safe = name.replace(/[^\w.\- ]+/g, "_").slice(0, 120) || "download";
  headers.set("Content-Disposition", `attachment; filename="${safe}"`);
  headers.set("Cache-Control", "no-store");

  return new Response(upstream.body, { status: 200, headers });
}
