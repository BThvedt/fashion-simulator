"use server";

import { revalidatePath } from "next/cache";
import { drupalFetch, getCurrentUser } from "@/lib/drupal";

export type ProfileState =
  | { ok: true; message: string }
  | { ok: false; error: string }
  | undefined;

interface JsonApiErrorBody {
  errors?: { detail?: string; title?: string }[];
}

const JSON_API_HEADERS = { "Content-Type": "application/vnd.api+json" };

async function firstError(res: Response, fallback: string): Promise<string> {
  try {
    const body = (await res.json()) as JsonApiErrorBody;
    return body.errors?.[0]?.detail ?? body.errors?.[0]?.title ?? fallback;
  } catch {
    return fallback;
  }
}

export async function updateUsername(
  _prev: ProfileState,
  formData: FormData
): Promise<ProfileState> {
  const name = (formData.get("username") as string)?.trim();
  const currentPassword = formData.get("current_password") as string;

  if (!name || !currentPassword) {
    return { ok: false, error: "New username and current password are required." };
  }

  const user = await getCurrentUser();
  if (!user) return { ok: false, error: "Your session has expired. Please sign in again." };

  const res = await drupalFetch(`/jsonapi/user/user/${user.id}`, {
    method: "PATCH",
    headers: JSON_API_HEADERS,
    body: JSON.stringify({
      data: {
        type: "user--user",
        id: user.id,
        attributes: { name, pass: { existing: currentPassword } },
      },
    }),
  });

  if (!res.ok) {
    return { ok: false, error: await firstError(res, "Could not update username.") };
  }

  revalidatePath("/profile");
  return { ok: true, message: "Username updated." };
}

export async function updatePassword(
  _prev: ProfileState,
  formData: FormData
): Promise<ProfileState> {
  const currentPassword = formData.get("current_password") as string;
  const newPassword = formData.get("new_password") as string;
  const confirmPassword = formData.get("confirm_password") as string;

  if (!currentPassword || !newPassword) {
    return { ok: false, error: "Current and new password are required." };
  }
  if (newPassword !== confirmPassword) {
    return { ok: false, error: "New passwords do not match." };
  }

  const user = await getCurrentUser();
  if (!user) return { ok: false, error: "Your session has expired. Please sign in again." };

  const res = await drupalFetch(`/jsonapi/user/user/${user.id}`, {
    method: "PATCH",
    headers: JSON_API_HEADERS,
    body: JSON.stringify({
      data: {
        type: "user--user",
        id: user.id,
        attributes: { pass: { existing: currentPassword, value: newPassword } },
      },
    }),
  });

  if (!res.ok) {
    return { ok: false, error: await firstError(res, "Could not update password.") };
  }

  return { ok: true, message: "Password updated." };
}
