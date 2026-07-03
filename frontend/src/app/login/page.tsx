import LoginForm from "@/components/LoginForm";

export const metadata = { title: "Sign in" };

export default function LoginPage() {
  return (
    <div className="flex flex-1 items-center justify-center bg-zinc-50 dark:bg-black px-4">
      <div className="w-full max-w-sm rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 p-8 shadow-sm">
        <h1 className="mb-6 text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
          Sign in to Fashion Simulator
        </h1>
        <LoginForm />
      </div>
    </div>
  );
}
