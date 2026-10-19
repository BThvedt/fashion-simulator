"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { logout } from "@/app/actions/auth";

const links = [
  { href: "/", label: "Videos" },
  { href: "/tutorial", label: "Tutorial" },
  { href: "/privacy", label: "Policy" },
  { href: "/profile", label: "Profile" },
];

export default function TopNav() {
  const pathname = usePathname();

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur">
      <nav className="mx-auto flex h-14 w-full max-w-5xl items-center gap-2 px-4 sm:gap-6 sm:px-6">
        <Link
          href="/"
          className="shrink-0 text-sm font-semibold tracking-tight text-foreground"
        >
          Fashion Simulator
        </Link>

        <ul className="flex min-w-0 items-center gap-0 overflow-x-auto sm:gap-1">
          {links.map(({ href, label }) => {
            const active =
              pathname === href || pathname.startsWith(`${href}/`);
            return (
              <li key={href}>
                <Link
                  href={href}
                  aria-current={active ? "page" : undefined}
                  className={`rounded-md px-2 py-1.5 text-sm font-medium transition-colors sm:px-3 ${
                    active
                      ? "bg-accent text-accent-foreground"
                      : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                  }`}
                >
                  {label}
                </Link>
              </li>
            );
          })}
        </ul>

        <div className="ml-auto flex shrink-0 items-center gap-4">
          <form action={logout}>
            <button
              type="submit"
              className="rounded-md px-2 py-1.5 text-sm font-medium text-muted-foreground transition-colors sm:px-3 hover:bg-accent hover:text-accent-foreground"
            >
              Sign out
            </button>
          </form>
        </div>
      </nav>
    </header>
  );
}
