import { redirect } from "next/navigation";
import { getToken } from "@/lib/auth";
import { getCurrentUser } from "@/lib/drupal";
import ProfileForms from "@/components/ProfileForms";

export const metadata = { title: "Profile" };

export default async function ProfilePage() {
  if (!(await getToken())) {
    redirect("/login");
  }

  const user = await getCurrentUser();

  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-xl flex-1 px-4 py-10 sm:px-6">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Profile
        </h1>
        <p className="mt-2 mb-8 text-muted-foreground">
          Manage your account settings.
        </p>

        {user ? (
          <ProfileForms currentUsername={user.name} />
        ) : (
          <p className="text-destructive">
            We couldn&apos;t load your account. Try signing out and back in.
          </p>
        )}
      </main>
    </div>
  );
}
