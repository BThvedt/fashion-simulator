import "server-only";
import { getToken } from "@/lib/auth";

const BASE = process.env.NEXT_PUBLIC_API_URL;

/** Authenticated fetch — attaches the access_token httpOnly cookie as Bearer. */
export async function drupalFetch(
  path: string,
  init: RequestInit = {}
): Promise<Response> {
  const token = await getToken();
  return fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Accept: "application/vnd.api+json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init.headers as Record<string, string>),
    },
  });
}

/** Unauthenticated fetch — public read-only JSON:API endpoints only. */
export async function drupalPublicFetch(
  path: string,
  init: RequestInit = {}
): Promise<Response> {
  return fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Accept: "application/vnd.api+json",
      ...(init.headers as Record<string, string>),
    },
  });
}
