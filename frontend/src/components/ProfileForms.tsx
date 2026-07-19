"use client";

import { useActionState } from "react";
import {
  updateUsername,
  updatePassword,
  type ProfileState,
} from "@/app/actions/profile";

const fieldClass =
  "rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50";
const labelClass = "text-sm font-medium text-foreground";
const buttonClass =
  "flex h-10 w-fit items-center justify-center rounded-full bg-primary px-5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50";

function StatusMessage({ state }: { state: ProfileState }) {
  if (!state) return null;
  return (
    <p
      role="status"
      aria-live="polite"
      className={`text-sm ${state.ok ? "text-primary" : "text-destructive"}`}
    >
      {state.ok ? state.message : state.error}
    </p>
  );
}

export default function ProfileForms({
  currentUsername,
}: {
  currentUsername: string;
}) {
  const [nameState, nameAction, namePending] = useActionState<
    ProfileState,
    FormData
  >(updateUsername, undefined);
  const [passState, passAction, passPending] = useActionState<
    ProfileState,
    FormData
  >(updatePassword, undefined);

  return (
    <div className="flex flex-col gap-8">
      <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
        <h2 className="text-lg font-semibold tracking-tight text-card-foreground">
          Username
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Change the name you sign in with.
        </p>

        <form action={nameAction} className="mt-5 flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="username" className={labelClass}>
              New username
            </label>
            <input
              id="username"
              name="username"
              type="text"
              autoComplete="username"
              defaultValue={currentUsername}
              required
              disabled={namePending}
              className={fieldClass}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="username-current-password" className={labelClass}>
              Current password
            </label>
            <input
              id="username-current-password"
              name="current_password"
              type="password"
              autoComplete="current-password"
              required
              disabled={namePending}
              className={fieldClass}
            />
          </div>

          <StatusMessage state={nameState} />

          <button type="submit" disabled={namePending} className={buttonClass}>
            {namePending ? "Saving…" : "Save username"}
          </button>
        </form>
      </section>

      <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
        <h2 className="text-lg font-semibold tracking-tight text-card-foreground">
          Password
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Use a strong password you don&apos;t use elsewhere.
        </p>

        <form action={passAction} className="mt-5 flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="password-current-password" className={labelClass}>
              Current password
            </label>
            <input
              id="password-current-password"
              name="current_password"
              type="password"
              autoComplete="current-password"
              required
              disabled={passPending}
              className={fieldClass}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="new-password" className={labelClass}>
              New password
            </label>
            <input
              id="new-password"
              name="new_password"
              type="password"
              autoComplete="new-password"
              required
              disabled={passPending}
              className={fieldClass}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="confirm-password" className={labelClass}>
              Confirm new password
            </label>
            <input
              id="confirm-password"
              name="confirm_password"
              type="password"
              autoComplete="new-password"
              required
              disabled={passPending}
              className={fieldClass}
            />
          </div>

          <StatusMessage state={passState} />

          <button type="submit" disabled={passPending} className={buttonClass}>
            {passPending ? "Saving…" : "Save password"}
          </button>
        </form>
      </section>
    </div>
  );
}
