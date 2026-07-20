import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  experimental: {
    serverActions: {
      // Captured pose images are sent to a server action as base64 data URLs;
      // a few JPEGs can exceed the default 1MB body limit.
      bodySizeLimit: "8mb",
    },
  },
};

export default nextConfig;
