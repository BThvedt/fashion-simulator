import "server-only";
import { cookies } from "next/headers";

const COOKIE_NAME = "access_token";
const REFRESH_COOKIE_NAME = "refresh_token";
// Fallback access-token TTL, matching the Simple OAuth consumer (1 hour). The
// real value comes from the token response's `expires_in`; this is only used if
// that's absent. The refresh token lives for 14 days (also per the consumer)
// and is used by the middleware to silently mint a fresh access token once the
// short-lived one lapses.
const COOKIE_MAX_AGE = 3600;
const REFRESH_COOKIE_MAX_AGE = 60 * 60 * 24 * 14;

export async function setTokens(
  accessToken: string,
  refreshToken?: string,
  expiresIn: number = COOKIE_MAX_AGE
) {
  const cookieStore = await cookies();
  const secure = process.env.NODE_ENV === "production";
  cookieStore.set(COOKIE_NAME, accessToken, {
    httpOnly: true,
    sameSite: "lax",
    secure,
    maxAge: expiresIn,
    path: "/",
  });
  if (refreshToken) {
    cookieStore.set(REFRESH_COOKIE_NAME, refreshToken, {
      httpOnly: true,
      sameSite: "lax",
      secure,
      maxAge: REFRESH_COOKIE_MAX_AGE,
      path: "/",
    });
  }
}

export async function clearTokenCookie() {
  const cookieStore = await cookies();
  cookieStore.delete(COOKIE_NAME);
  cookieStore.delete(REFRESH_COOKIE_NAME);
}

export async function getToken(): Promise<string | undefined> {
  const cookieStore = await cookies();
  return cookieStore.get(COOKIE_NAME)?.value;
}

export async function getRefreshToken(): Promise<string | undefined> {
  const cookieStore = await cookies();
  return cookieStore.get(REFRESH_COOKIE_NAME)?.value;
}
