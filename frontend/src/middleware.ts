import { NextRequest, NextResponse } from "next/server";

const ACCESS_COOKIE = "access_token";
const REFRESH_COOKIE = "refresh_token";
const REFRESH_MAX_AGE = 60 * 60 * 24 * 14; // 14 days, matches the OAuth consumer

/**
 * Keeps the session alive across the short (5 min) access-token lifetime.
 *
 * Simple OAuth access tokens expire quickly; without this, every request made
 * after that window goes out anonymous and Drupal returns 403 (e.g. the
 * "Generate My Fashion Video!" create call). When the access-token cookie is
 * gone but a refresh-token cookie is still present, we exchange it for a fresh
 * pair here — before any page or server action reads the cookies — and write
 * the new tokens onto both the forwarded request (so the current render sees
 * them) and the response (so the browser persists them).
 */
export async function middleware(request: NextRequest) {
  const hasAccess = Boolean(request.cookies.get(ACCESS_COOKIE)?.value);
  const refreshToken = request.cookies.get(REFRESH_COOKIE)?.value;

  // Still authenticated, or nothing to refresh with — let the request through.
  if (hasAccess || !refreshToken) {
    return NextResponse.next();
  }

  let tokens: {
    access_token?: string;
    refresh_token?: string;
    expires_in?: number;
  } | null = null;
  try {
    const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/oauth/token`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        grant_type: "refresh_token",
        client_id: process.env.OAUTH_CLIENT_ID!,
        client_secret: process.env.OAUTH_CLIENT_SECRET!,
        refresh_token: refreshToken,
      }),
      cache: "no-store",
    });
    if (res.ok) {
      tokens = (await res.json()) as typeof tokens;
    }
  } catch {
    tokens = null;
  }

  // Refresh failed (token expired/revoked): drop the stale cookie so the app
  // treats the user as cleanly logged out and routes them to /login.
  if (!tokens?.access_token) {
    const response = NextResponse.next();
    response.cookies.delete(REFRESH_COOKIE);
    return response;
  }

  // Expose the new access token to this same request (server components and
  // actions read cookies() off the incoming request).
  request.cookies.set(ACCESS_COOKIE, tokens.access_token);
  if (tokens.refresh_token) {
    request.cookies.set(REFRESH_COOKIE, tokens.refresh_token);
  }

  const response = NextResponse.next({ request });
  const secure = process.env.NODE_ENV === "production";
  response.cookies.set(ACCESS_COOKIE, tokens.access_token, {
    httpOnly: true,
    sameSite: "lax",
    secure,
    maxAge: tokens.expires_in ?? 3600,
    path: "/",
  });
  if (tokens.refresh_token) {
    response.cookies.set(REFRESH_COOKIE, tokens.refresh_token, {
      httpOnly: true,
      sameSite: "lax",
      secure,
      maxAge: REFRESH_MAX_AGE,
      path: "/",
    });
  }
  return response;
}

export const config = {
  // Run on everything except Next internals and static assets (files with an
  // extension). Page navigations and server-action POSTs are all covered.
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.).*)"],
};
