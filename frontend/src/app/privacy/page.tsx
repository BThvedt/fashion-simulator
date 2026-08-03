export const metadata = { title: "Privacy Policy" };

export default function PrivacyPage() {
  return (
    <div className="flex flex-1 flex-col bg-background">
      <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-10 sm:px-6">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Privacy Policy
        </h1>

        <div className="mt-6 rounded-2xl border border-border bg-card p-6">
          <p className="text-lg font-medium text-card-foreground">
            All images are private and secure unless you choose to share them.
          </p>
          <p className="mt-3 text-card-foreground">
            Content is auto-deleted after 30 days, so make sure you share or
            download your videos!
          </p>
        </div>

        <section className="mt-8 space-y-6 text-foreground">
          <div>
            <h2 className="text-lg font-semibold text-foreground">
              Your photos and videos are private by default
            </h2>
            <p className="mt-2 text-muted-foreground">
              The photos you capture and the images and videos we generate are
              stored privately and are only visible to you when you&apos;re
              signed in. We don&apos;t list them publicly or sell them.
            </p>
          </div>

          <div>
            <h2 className="text-lg font-semibold text-foreground">
              Sharing is always your choice
            </h2>
            <p className="mt-2 text-muted-foreground">
              A video only becomes viewable to others when you turn on sharing.
              Doing so creates a hard-to-guess link that anyone with it can open
              — it&apos;s never listed anywhere. You can make a video private
              again at any time, which immediately disables the link.
            </p>
          </div>

          <div>
            <h2 className="text-lg font-semibold text-foreground">
              Content is auto-deleted after 30 days
            </h2>
            <p className="mt-2 text-muted-foreground">
              To keep things tidy and protect your privacy, videos and their
              associated photos are automatically and permanently deleted about
              30 days after they&apos;re created. Once deleted, they can&apos;t
              be recovered — so if you love a look, download it or share it
              before then. You can also delete any video yourself at any time.
            </p>
          </div>

          <div>
            <h2 className="text-lg font-semibold text-foreground">
              How your data is used
            </h2>
            <p className="mt-2 text-muted-foreground">
              Your captured photos and voice recording are used only to generate
              your fashion video (including sending them to the AI services that
              create the images and talking-head clip). We don&apos;t use your
              content for advertising.
            </p>
          </div>
        </section>
      </main>
    </div>
  );
}
