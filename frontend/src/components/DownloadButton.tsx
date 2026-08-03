/**
 * A download link that routes through the same-origin `/api/download` proxy so
 * cross-origin S3 media is saved (not opened) by the browser. Presentational
 * only — usable from server and client components.
 */
export default function DownloadButton({
  url,
  filename,
  className,
  label,
  iconSize = 16,
}: {
  url: string;
  filename: string;
  className?: string;
  /** Optional visible text; when omitted the button is icon-only. */
  label?: string;
  iconSize?: number;
}) {
  const href = `/api/download?url=${encodeURIComponent(url)}&name=${encodeURIComponent(filename)}`;
  return (
    <a
      href={href}
      download={filename}
      aria-label={label ?? "Download"}
      title={label ?? "Download"}
      className={className}
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width={iconSize}
        height={iconSize}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <path d="M12 3v12" />
        <path d="m7 10 5 5 5-5" />
        <path d="M5 21h14" />
      </svg>
      {label && <span>{label}</span>}
    </a>
  );
}
