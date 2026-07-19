import "server-only";
import { getToken } from "@/lib/auth";

const BASE = process.env.NEXT_PUBLIC_API_URL;

export interface JsonApiResource<T = Record<string, unknown>> {
  id: string;
  type: string;
  attributes: T;
}

export interface JsonApiSingle<T = Record<string, unknown>> {
  data: JsonApiResource<T>;
}

export interface CurrentUser {
  id: string;
  name: string;
  mail: string;
}

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

/**
 * Resolves the currently authenticated user via the JSON:API `meta.links.me`
 * link, then loads their `name` and `mail`. Returns null when unauthenticated.
 */
export async function getCurrentUser(): Promise<CurrentUser | null> {
  const entry = await drupalFetch("/jsonapi", { cache: "no-store" });
  if (!entry.ok) return null;

  const entryBody = (await entry.json()) as {
    meta?: { links?: { me?: { meta?: { id?: string } } } };
  };
  const id = entryBody.meta?.links?.me?.meta?.id;
  if (!id) return null;

  const res = await drupalFetch(
    `/jsonapi/user/user/${id}?fields[user--user]=name,mail`,
    { cache: "no-store" }
  );
  if (!res.ok) return null;

  const { data } = (await res.json()) as JsonApiSingle<{
    name: string;
    mail: string;
  }>;

  return { id: data.id, name: data.attributes.name, mail: data.attributes.mail };
}
