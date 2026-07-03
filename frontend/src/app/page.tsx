import Link from "next/link";
import { getToken } from "@/lib/auth";
import { logout } from "@/app/actions/auth";

export default async function Home() {
  const token = await getToken();
  const isLoggedIn = Boolean(token);

  return (
    <div className="flex flex-col flex-1 items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex flex-1 w-full max-w-3xl flex-col items-center justify-center gap-6 py-32 px-16 bg-white dark:bg-black">
        <h1 className="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
          Fashion Simulator
        </h1>

        {isLoggedIn ? (
          <div className="flex flex-col items-center gap-4">
            <p className="text-zinc-600 dark:text-zinc-400">
              You are signed in.
            </p>
            <form action={logout}>
              <button
                type="submit"
                className="flex h-10 items-center justify-center rounded-full border border-zinc-300 dark:border-zinc-700 px-5 text-sm font-medium text-zinc-900 dark:text-zinc-100 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-900"
              >
                Sign out
              </button>
            </form>
          </div>
        ) : (
          <Link
            href="/login"
            className="flex h-10 items-center justify-center rounded-full bg-zinc-900 dark:bg-zinc-100 px-5 text-sm font-medium text-white dark:text-zinc-900 transition-colors hover:bg-zinc-700 dark:hover:bg-zinc-300"
          >
            Sign in
          </Link>
        )}
      </main>
    </div>
  );
}
