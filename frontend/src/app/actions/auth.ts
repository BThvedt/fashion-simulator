"use server";

import { redirect } from "next/navigation";
import { setTokenCookie, clearTokenCookie } from "@/lib/auth";

export type LoginState =
  | { error: string }
  | { error: null }
  | undefined;

interface TokenResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
  error?: string;
  error_description?: string;
}

export async function login(
  _prevState: LoginState,
  formData: FormData
): Promise<LoginState> {
  const username = formData.get("username") as string;
  const password = formData.get("password") as string;

  if (!username || !password) {
    return { error: "Username and password are required." };
  }

  const res = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL}/oauth/token`,
    {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        grant_type: "password",
        client_id: process.env.OAUTH_CLIENT_ID!,
        client_secret: process.env.OAUTH_CLIENT_SECRET!,
        username,
        password,
      }),
    }
  );

  const body = (await res.json()) as TokenResponse;

  if (!res.ok || body.error) {
    return {
      error: body.error_description ?? "Invalid credentials. Please try again.",
    };
  }

  await setTokenCookie(body.access_token);
  redirect("/");
}

export async function logout() {
  await clearTokenCookie();
  redirect("/login");
}
