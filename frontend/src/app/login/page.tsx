import LoginForm from "@/components/LoginForm";

export const metadata = { title: "Sign in" };

export default function LoginPage() {
  return (
    <div className="flex flex-1 items-center justify-center bg-background px-4">
      <div className="w-full max-w-sm rounded-2xl border border-border bg-card p-8 shadow-sm">
        <h1 className="mb-6 text-xl font-semibold tracking-tight text-card-foreground">
          Sign in to Fashion Simulator
        </h1>
        <LoginForm />
      </div>
    </div>
  );
}
